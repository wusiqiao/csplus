<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\ControllerBase;

class  VcrCompareController extends ControllerBase {

    public function listAction(){
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $accounting_section = I("post.accounting_section");
        $result = D(CONTROLLER_NAME)->getValueTaxBill($this->_user_session->currBranchId, $accounting_section, $page_index, $page_size);
        $this->responseJSON($result);
    }

    public function compareAction() {
        set_time_limit(0);
        if (!empty($_FILES)) {
            $uploader = getUploader("temp/", array('xls', 'xlsx'));
            $info = $uploader->uploadOne($_FILES["auth-file"]);
            if (!$info) {
                $msg = buildMessage($uploader->getError(), 1);
            } else {
                $filePath = ltrim($uploader->rootPath, ".") . $info['savepath'] . $info['savename'];
                $type = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                $msg = D(CONTROLLER_NAME)->compare($filePath, $this->_user_session->currBranchId);
                unset($uploader);
            }
            $this->responseJSON($msg);
        } else {
            $this->responseJSON(buildMessage("文件不能为空！", 1));
        }
    }

    /**更新认证状态
     * @param $id
     */
    public function updateAuthStateAction($id){
        $branch_id = $this->_user_session->currBranchId;
        if ($branch_id && $id){
            $id_array = explode("_", $id);
            if (count($id_array) == 1){ //增值税单证主表
                M("VcrBill")->where(array("branch_id"=>$branch_id, "id"=>$id))->setField("authed", 1);
            }else{
                if (count($id_array) == 2){ //其他单证子表，传如主表 id _  子表id
                    $bill_id = $id_array[0];
                    $item_id = $id_array[1];
                    if (M("VcrBill")->where(array("branch_id"=>$branch_id, "id"=>$bill_id))->count() > 0){
                        M("VcrBillDetail")->where("id=$item_id")->setField("authed", 1);
                    }
                }
            }
            $this->responseJSON(buildMessage("认证确认完成！"));
        }else{
            $this->responseJSON(buildMessage("参数错误！", 1));
        }
    }

}