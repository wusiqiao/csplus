<?php

namespace ESAdmin\Controller;

class VcrBillBankAccountController extends VcrBillController {

    protected function _before_display_dataview(&$data) {
        parent::_before_display_dataview($data);        
        //$data["bill_type_text"] = getBankBillType($data["bank_bill_type"]);
    }

    public function chooseTypeAction() {
        $this->assign("list", getBankBillType());
        $this->display();
    }

    public function _get_detail_template($record) {
        $type = $record["bank_bill_type"];
        return "edit_$type";
    }
    
    protected function _before_list(&$list) {
        $bill_types = getBankBillType();
        foreach ($list as $key => $value) {
           $list[$key]["bill_type_text"] = $bill_types[$value["bank_bill_type"]];
        }
    }
    
    public function _before_write($type, &$data) {
        if ($data["bank_bill_type"] == 8){ //电子缴税凭证,会把税费项目放在动态数组里面
            $fee_inputs = I("post.fee_input");
            $fee_titles = I("post.fee_title");
            if (!is_array($fee_inputs) || !is_array($fee_titles)){
                $this->responseJSON(buildMessage("数据格式错误",1));
            }
            $amount = 0;
            $datas = array();
            foreach ($fee_inputs as $key=>$fee_item) {
                $amount = $amount + floatval($fee_item);
                $datas[] = array("name"=>$fee_titles[$key],"amount"=>$fee_item);
            }
            $data["amount"] = $amount;
            $data["fee_items"] = json_encode($datas);
        }
        parent::_before_write($type, $data);
    }

    public function importAction() {
        if (IS_GET) {
            $this->display();
        } else {
            set_time_limit(0);
//            $branch_id = $this->_user_session->currBranchId;
//            $accounting_section = I("post.accounting_section");
            $bank_subject = I("post.bank_subject");
            //银行对账单可能有多个月份，所以不能传入月份条件，需要在获取的数据内判断
//            $condition["accounting_section"] = $accounting_section;
//            $condition["branch_id"] = $branch_id;
//            $condition["bill_flag"] = FLAG_BILL_BANK;
//            $condition["bank_subject"] = $bank_subject;
//            if (M("VcrBill")->where($condition)->count() >0) {
//                $this->responseJSON(buildMessage("本月银行对账已经存在，如需修改，请直接修改单证资料！", 1));
//            }
            if (!empty($_FILES)) {
                $uploader = getUploader("temp/", array('xls', 'xlsx'));
                $info = $uploader->uploadOne($_FILES["bank-file"]);
                if (!$info) {
                    $message = buildMessage($uploader->getError(), 1);
                } else {
                    $filePath = ltrim($uploader->rootPath, ".") . $info['savepath'] . $info['savename'];
                    $type = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                    $message = D(CONTROLLER_NAME)->saveDataFromExcel($filePath);
                    unset($uploader);
                }
                if ($message["code"] > 0 ){
                    $errmsg = sprintf("用户编号：%s（%s）导入银行对账单失败!错误原因：%s，文件路径：%s",
                        $this->_user_session->userId,
                        $this->_user_session->currBranchName,
                        $message["message"],
                        WEB_ROOT."/".$filePath);
                    add_timer(0, WEB_ROOT . "/ReqQueue/sendMail/message/".base64_encode($errmsg));
                }
                $this->responseJSON($message);
            } else {
                $this->responseJSON(buildMessage("文件不能为空！", 1));
            }
        }
    }

}
