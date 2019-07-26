<?php

namespace ESAdmin\Controller;

class VcrBillUnCheckController extends VcrBillController {

    public function drafViewAction(){
        $this->display();
    }

    public function checkSubjectAction(){
//        $company_data = M("SysBranch")->field("fee_subject")->where("id=".$this->_user_session->currBranchId)->find();
//        if (empty($company_data["fee_subject"])){
//            $this->responseJSON(buildMessage("请先设置客户资料的【主费用科目】！",1));
//        }else{
//            $this->responseJSON(buildMessage("设置完成！"));
//        }
        //1、检查是否已经导入银行对账单

        //2、是否已经标准科目映射完毕
        $this->responseJSON(buildMessage("检查完成！"));
    }
}
