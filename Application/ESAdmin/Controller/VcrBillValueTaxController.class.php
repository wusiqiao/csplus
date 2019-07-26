<?php

namespace ESAdmin\Controller;

class VcrBillValueTaxController extends VcrBillController {

    protected function _before_add(&$data) {
        parent::_before_add($data);
    }

    public function indexAction() {
        //一般纳税人才能导入
        $company_data = M("SysBranch")->field("ent_scale")->find($this->_user_session->currBranchId);
        $this->ent_scale = $company_data["ent_scale"];
        parent::indexAction();
    }

}
