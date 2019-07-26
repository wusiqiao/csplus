<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

/**
 * 支付配置
 * */
class ComPayConfigController extends DataController
{
    public function indexAction()
    {
        if (!$this->isCompanyManager()) {
            exit('权限不足!');
        }

        $storeConfig = D("ComStore")->where("branch_id=" . $this->_user_session->currBranchId)->find() OR exit('请先进行门店资料配置');
        $wxConfig = D("WxConfig")->where("branch_id=" . $this->_user_session->currBranchId)->find() OR exit('请先进行公众号配置');

        $this->assign('storeConfig', $storeConfig);
        $this->assign('wxConfig', $wxConfig);

        $this->display();
    }

    public function modifyAction()
    {
        if (!$this->isCompanyManager()) {
            return $this->ajaxReturn(['code' => 1, 'message' => '权限不足!']);
        }

        $updateStore['unline_payee'] = I('post.unline_payee');
        $updateStore['unline_bank_account'] = I('post.unline_bank_account');
        $updateStore['unline_card_number'] = I('post.unline_card_number');
        $updateStore['pay_status'] = I('post.store_pay_status');
        $updateStore['updated_at'] = time();
        $updateWechat['wxpay_open'] = I('post.wxpay_open');
        $updateWechat['wx_mchid'] = I('post.wx_mchid');
        $updateWechat['wx_pay_key'] = I('post.wx_pay_key');
        $updateWechat['updated_at'] = time();
        $default = I('post.default', 'string');
        if (I('post.default') == 'wechat') {
            if ($updateWechat['wxpay_open'] != PAY_STATUS_OPEN) {
                return $this->ajaxReturn(['code' => 1, 'message' => '未开启微信支付，无法设置为默认支付!']);
            }

            if (empty($updateWechat['wx_mchid']) || empty($updateWechat['wx_pay_key'])) {
                return $this->ajaxReturn(['code' => 1, 'message' => '设置微信支付为默认，请填写商户号与密匙']);
            }

            $updateWechat['wxpay_open'] = PAY_STATUS_DEFAULT;
        } else if ($default == 'offline') {
            if ($updateStore['pay_status'] != PAY_STATUS_OPEN) {
                return $this->ajaxReturn(['code' => 1, 'message' => '未开启线下支付，无法设置为默认支付!']);
            }

            $updateStore['pay_status'] = PAY_STATUS_DEFAULT;
        }
        $updateStore['pay_status'] = $updateStore['pay_status'] == 0 ? PAY_STATUS_CLOSE : $updateStore['pay_status'];
        $updateWechat['wxpay_open'] = $updateWechat['wxpay_open'] == 0 ? PAY_STATUS_CLOSE : $updateWechat['wxpay_open'];
        $branchId = $this->_user_session->currBranchId;
        M()->startTrans();
        if (
            D('ComStore')->where(['id' => I('post.store'), 'branch_id' => $branchId])->save($updateStore)
            &&
            D("WxConfig")->where(['branch_id' => $branchId, 'id' => I('post.wechat')])->save($updateWechat)
        ) {
            M()->commit();
            return $this->ajaxReturn(['code' => 0, 'message' => '更新成功!']);
        }

        M()->rollback();
        return $this->ajaxReturn(['code' => 1, 'message' => '更新失败!']);
    }

    private function isCompanyManager()
    {
        return $this->_user_session->userType == USER_TYPE_COMPANY_MANAGER;
    }
}