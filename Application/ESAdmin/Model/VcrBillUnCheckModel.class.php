<?php

namespace ESAdmin\Model;


class VcrBillUnCheckModel extends VcrBillModel {

    protected $tableName = 'vcr_bill';
            
    protected function _options_filter(&$options) {
        $this->addOptionsFilter($options, array("voucher_id" => array("exp", "ISNULL")));
        parent::_options_filter($options);
    }

}
