<?php

namespace ESAdmin\Controller;

class VcrBillSalaryController extends VcrBillController {
    
//    protected function _before_write($type, &$data) {
//        parent::_before_write($type, $data);
//        $data["goods_name"] = "员工薪资";
//        $data["name"] = $this->_user_session->currBranchName;
//    }

    public function importAction() {
        if (IS_GET) {
            $this->display();
        } else {
            set_time_limit(0);
            $branch_id = $this->_user_session->currBranchId;
            $accounting_section = I("post.accounting_section");
            $fee_department = I("post.fee_department");
            if (empty($accounting_section) || empty($fee_department)){
                $this->responseJSON(buildMessage("会计期间或费用部门不能为空！", 1));
            }
            $condition["accounting_section"] = $accounting_section;
            $condition["branch_id"] = $branch_id;
            $condition["bill_flag"] = FLAG_BILL_SALARY;
            $condition["fee_department"] = array('in',$fee_department);
            
            if (M("VcrBill")->where($condition)->count() >0) {
                $this->responseJSON(buildMessage("本月工资已经存在，如需修改，请直接修改单证资料！", 1));
            }
            if (!empty($_FILES)) {
                $uploader = getUploader("temp/", array('xls', 'xlsx'));
                $info = $uploader->uploadOne($_FILES["salary-file"]);
                $filePath = "";
                if (!$info) {
                    $message = buildMessage($uploader->getError(), 1);
                } else {
                    $filePath = ltrim($uploader->rootPath, ".") . $info['savepath'] . $info['savename'];
                    $type = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                    $message = D(CONTROLLER_NAME)->saveDataFromExcel($filePath);
                    unset($uploader);
                }
                if ($message["code"] > 0 ){
                    $errmsg = sprintf("用户编号：%s（%s）导入工资册失败!错误原因：%s，文件路径：%s",
                        $this->_user_session->userId,
                        $this->_user_session->currBranchName,
                        $message["message"],
                        WEB_ROOT."/".$filePath);
                    // add_timer(0, WEB_ROOT . "/ReqQueue/sendMail/message/".base64_encode($errmsg));
                }
                $this->responseJSON($message);
            } else {
                $this->responseJSON(buildMessage("文件不能为空！", 1));
            }
        }
    }
}
