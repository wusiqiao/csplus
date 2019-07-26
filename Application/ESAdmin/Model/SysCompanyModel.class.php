<?php

namespace ESAdmin\Model;
use ESAdmin\Model\BranchBaseModel;

class SysCompanyModel extends BranchBaseModel {
    protected $_branch_type = ORG_BRANCH;
    
    protected function _options_filter(&$options) {
        $this->addOptionsFilter($options, array("parent_id" => 0));
    }
}
