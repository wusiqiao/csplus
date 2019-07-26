<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class  DistributionWithdrawalController extends DataController {
    public function checkAction($id, $agree){
        $condition["branch_id"] = getBrowseBranchId();
        $data = M(CONTROLLER_NAME)->find($id);
        if ($data){
            if ($data["branch_id"] == getBrowseBranchId()){
                $saveData["review_time"] = time();
                $saveData["status"] = ($agree == 0)?2:1;
                $saveData["review_user"] = $this->_user_session->userName;
                if (M(CONTROLLER_NAME)->where("id=$id")->save($saveData) != false){
                    $this->ajaxReturn(buildResult($saveData));
                }else{
                    $this->ajaxReturn(buildMessage("更新失败",1));
                }
            }
        }else{
            $this->ajaxReturn(buildMessage("找不到对应提现单据",1));
        }
    }

    protected function _parsefilter(&$filter) {
        parent::_parsefilter($filter);
        $inviter_name = I("post.inviter_name");
        if ($inviter_name){
            if (preg_match("/13[123569]{1}\d{8}|15[1235689]\d{8}|188\d{8}/", $inviter_name)){
                $condition["mobile"] = array("like", '%'.$inviter_name .'%');
            }else{
                $condition["name"] = array("like", '%'.$inviter_name .'%');
            }
            $users = M("SysUser")->where($condition)->getField("id", true);
            if ($users){
                $filter["a.user_id"] = array("in", implode(",", $users));
            }else{
                $filter["a.user_id"] = 0;
            }
        }
    }
}