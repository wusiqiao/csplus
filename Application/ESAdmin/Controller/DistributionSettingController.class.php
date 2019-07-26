<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/5
 * Time: 14:43
 */

namespace ESAdmin\Controller;


use Common\Lib\Controller\DataController;

class DistributionSettingController extends DataController
{
    public function indexAction() {
        $this->assignPermissions(); //权限设置
        if ($this->_user_session->userType == USER_TYPE_COMPANY_MANAGER) {
            $branch_id = $this->_user_session->currBranchId;
            $model = D(CONTROLLER_NAME)->getProductCommisions($branch_id);
            define("__FORM_ACTION__", empty($model["id"])?"add":"update");
            $this->assign("model", $model);
        }
        $this->display();
    }

    protected function _before_write($type, &$data) {
        if($data["is_valid"]) {
            if ($data["commision_type"] == COMMISION_TYPE_FIXED && empty($data["commision_amount"])) {
                $this->responseJSON(buildMessage("请设置固定佣金！", 1));
            }
            if ($data["commision_type"] == COMMISION_TYPE_RATE && empty($data["commision_rate"])) {
                $this->responseJSON(buildMessage("请设置佣金比例！", 1));
            }
            if (I("post.activity_start") > I("post.activity_end")) {
                $this->responseJSON(buildMessage("启用开始日期不能大于结束日期！", 1));
            }
            if ($data["commision_type"] == COMMISION_TYPE_CUSTOM) {
                $products = I("post.product");
                $select_count = 0;
                foreach ($products as $product) {
                    $prd_prd_is_valid = I("post.prd_is_valid_$product");
                    if ($prd_prd_is_valid) {
                        $product_title = I("post.product_title_$product");
                        $prd_commision_amount = I("post.prd_commision_amount_$product");
                        $prd_commision_rate = I("post.prd_commision_rate_$product");
                        if (empty($prd_commision_amount) && empty($prd_commision_rate)) {
                            $this->responseJSON(buildMessage($product_title . "：  固定佣金或比例至少必须设置一个！", 1));
                        }
                        if ($prd_commision_rate >= 100) {
                            $this->responseJSON(buildMessage($product_title . "：  佣金比例不能超过100%！", 1));
                        }
                        if (I("post.prd_activity_start_$product") > I("post.prd_activity_end_$product")) {
                            $this->responseJSON(buildMessage($product_title . "：  开始日期不能大于结束日期！", 1));
                        }
                        $select_count++;
                    }
                }
                if ($select_count == 0) {
                    $this->responseJSON(buildMessage("服务个性设置，至少要选择一项服务", 1));
                }
            }
            $data["activity_start"] = strtotime($data["activity_start"]);
            $data["activity_end"] = strtotime($data["activity_end"]);
        }
        parent::_before_write($type, $data);
    }
}