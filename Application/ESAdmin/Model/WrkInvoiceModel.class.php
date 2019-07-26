<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;
use Think\Log;
use Think\Think;
use Common\Lib\Model\ComplexDataModel;

class WrkInvoiceModel extends ComplexDataModel {
    protected $tableName = 'wrk_invoice_plan';

    protected $_leaderField = "leader_id";//负责人字段，在子类设置,一定要设置
    protected $_visiblersField = "visiblers"; //可见人字段，如果原有此字段，就设置，没有就不需要
    protected $_collaboratorsField = "collaborators"; //协作人，如果原有此字段，就设置，没有就不需要
    protected $_companyField = "company_id"; //公司字段，一定要设置

    protected function _options_filter(&$options) {
        parent::_options_filter($options);
    }

    protected $_link = array(
        "WrkAgreement" => array(
            "join_name" => "INNER",
            'class_name' => "WrkAgreement",
            'foreign_key' => 'agreement_id',
            'mapping_name' => 'ag',
            'mapping_fields' => 'id,name,company_id,customer_leader_id,leader_id,agreement_sn,agreement_money,sys_sn,start_time,finish_time,visiblers',
            "mapping_key" => "id"
        ),
        "SysBranch" => array(
            "join_name" => "LEFT",
            'class_name' => "SysBranch",
            'foreign_key' => 'ag.company_id',
            'mapping_name' => 'branch',
            'mapping_fields' => 'id,name',
            "mapping_key" => "id"
        )
    );
}