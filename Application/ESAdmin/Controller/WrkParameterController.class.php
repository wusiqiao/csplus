<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/1/11
 * Time: 16:48
 */

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class WrkParameterController extends DataController{
	/**
	 * 合同参数
	 */
    public function indexAction() {
        define("__FORM_ACTION__", "update");
        $_filter['branch_id'] = getBrowseBranchId();
        $_filter = ['various' =>['in',[
            TCT_AGREEMENT_UPDATE_MONEY_NOTICE,
            TCT_BRANCH_AGREEMENT_UPDATE_MONEY_NOTICE,
        ]]];
        $parameter = D(CONTROLLER_NAME)->setDacFilter("a")->field("a.*")->where($_filter)->select();
        $model = [];
        if ($parameter) {
            foreach ($parameter as $key => $value) {
                $model[$value['various']] = $value['message'];
            }
        }
        $model['is_auto_agreement'] = M("ComStore")->where("branch_id = ".getBrowseBranchId())->getField("is_auto_agreement");
        $this->assign('model',$model);
        $this->assignPermissions(); //权限设置
        $this->display();
    }

	/**
	 * 收款参数
	 */
	public function skIndexAction()
	{
		define("__FORM_ACTION__", "update");
		$_filter['branch_id'] = getBrowseBranchId();
		$_filter = ['various' =>['in',[
			TCT_INVOICE_NOTICE,
			TCT_BRANCH_INVOICE_NOTICE,
			TCT_CANCEL_INVOICE_NOTICE,
			TCT_BRANCH_CANCEL_INVOICE_NOTICE,
			TCT_CANCEL_APPLY_NOTICE,
			TCT_BRANCH_CANCEL_APPLY_NOTICE,
			TCT_FINISH_INVOICE_NOTICE,
			TCT_BRANCH_FINISH_INVOICE_NOTICE,
			TCT_OVERDUE_FREEZE_ASSIGNMENT_DAY,
			TCT_OFFLINE_PAYMENT_ARTIFICIAL_NOTICE,
			TCT_BRANCH_OFFLINE_PAYMENT_ARTIFICIAL_NOTICE,
			TCT_BALANCE_PAYMENT_AUTOMATIC_NOTICE,
			TCT_BRANCH_BALANCE_PAYMENT_AUTOMATIC_NOTICE,
			TCT_REFUND_AUTOMATIC_NOTICE,
			TCT_BRANCH_REFUND_AUTOMATIC_NOTICE,
			TCT_BAD_DEBT_AUTOMATIC_NOTICE,
			TCT_BRANCH_BAD_DEBT_AUTOMATIC_NOTICE,
			TCT_FREEZE_AUTOMATIC_NOTICE,
			TCT_BRANCH_FREEZE_AUTOMATIC_NOTICE,
		]]];
		$parameter = D(CONTROLLER_NAME)->setDacFilter("a")->field("a.*")->where($_filter)->select();
		$model = [];
		if ($parameter) {
			foreach ($parameter as $key => $value) {
				$model[$value['various']] = $value['message'];
			}
		}
		$model['is_auto_agreement'] = M("ComStore")->where("branch_id = ".getBrowseBranchId())->getField("is_auto_agreement");
		$this->assign('model',$model);
		$this->assignPermissions(); //权限设置
		$this->display();
	}

	/**
	 * 催款参数
	 */
	public function ckIndexAction()
	{
		define("__FORM_ACTION__", "update");
		$_filter['branch_id'] = getBrowseBranchId();
		$_filter = ['various' =>['in',[
			TCT_AUTOMATIC_RENEWAL_NOTICE,
			TCT_BRANCH_AUTOMATIC_RENEWAL_NOTICE,
			TCT_MANUAL_RENEWAL_NOTICE,
			TCT_BRANCH_MANUAL_RENEWAL_NOTICE,
		]]];
		$parameter = D(CONTROLLER_NAME)->setDacFilter("a")->field("a.*")->where($_filter)->select();
		$model = [];
		if ($parameter) {
			foreach ($parameter as $key => $value) {
				$model[$value['various']] = $value['message'];
			}
		}
		$model['is_auto_agreement'] = M("ComStore")->where("branch_id = ".getBrowseBranchId())->getField("is_auto_agreement");
		$this->assign('model',$model);
		$this->assignPermissions(); //权限设置
		$this->display();
	}

	/**
	 * 开票参数
	 */
	public function kpIndexAction()
	{
		define("__FORM_ACTION__", "update");
		$_filter['branch_id'] = getBrowseBranchId();
		$_filter = ['various' =>['in',[
			TCT_DELETE_RECEIPT_RECORD_NOTICE,
			TCT_BRANCH_DELETE_RECEIPT_RECORD_NOTICE
		]]];
		$parameter = D(CONTROLLER_NAME)->setDacFilter("a")->field("a.*")->where($_filter)->select();
		$model = [];
		if ($parameter) {
			foreach ($parameter as $key => $value) {
				$model[$value['various']] = $value['message'];
			}
		}
		$model['is_auto_agreement'] = M("ComStore")->where("branch_id = ".getBrowseBranchId())->getField("is_auto_agreement");
		$this->assign('model',$model);
		$this->assignPermissions(); //权限设置
		$this->display();
	}


    public function updateAction() {
        if (IS_POST) {
            $_user_session = session(USER_SESSION_KEY);
            $add = [];
            $post_data = I('post.');
            $condition['branch_id'] = getBrowseBranchId();
            if($post_data['is_auto_agreement'] != 1){
                $post_data['is_auto_agreement'] = 0;
            }
            M("ComStore")->where("branch_id = ".$condition['branch_id'])->setField("is_auto_agreement",$post_data['is_auto_agreement']);
            $various = D(CONTROLLER_NAME)->where($condition)->getField('various',true);
            foreach($post_data as $key => $value) {
                $message = strval($value);
                if (!empty($various) && in_array($key,$various)) {
                    $condition['various'] = $key;
                    $save['message'] = $message;
                    $save['updated_at'] = time();
                    $result = D(CONTROLLER_NAME)->where($condition)->data($save)->save();
                } else {
                    $add[] = [
                        'branch_id' =>$condition['branch_id'],
                        'message' =>$message,
                        'various' => $key,
                        'user_id' => $_user_session->userId,
                        'creator_id' =>  $_user_session->userId,
                        'created_at' => time(),
                        'updated_at' => time()
                    ];
                }
            }
            if ($add) {
                D(CONTROLLER_NAME)->addAll($add);
            }
            $this->ajaxReturn(buildMessage('保存成功',0));
        }
    }
}