<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class WrkInvoiceApplyModel extends DataModel {
    protected $tableName = 'wrk_invoice_plan_detail';
    //protected $_filter = ['type' =>array('eq','1')];
    protected $_filter = ['type' =>array('eq','1'),'state'=>array("neq",5)];

    protected $_link = array(
        "WrkAgreement" => array(
            "join_name" => "INNER",
            'class_name' => "WrkAgreement",
            'foreign_key' => 'agreement_id',
            'mapping_name' => 'ag',
            'mapping_fields' => 'id,name,company_id,customer_leader_id,leader_id,agreement_sn,agreement_money,sys_sn,start_time,finish_time,is_invoice_plan',
            "mapping_key" => "id"
        ),
        "WrkInvoicePlan" => array(
            "join_name" => "INNER",
            'class_name' => "WrkInvoicePlan",
            'foreign_key' => 'plan_id',
            'mapping_name' => 'wip',
            'mapping_fields' => 'leader_id,visiblers,creater_id',
            "mapping_key" => "id"
        ),
        "SysBranch" => array(
            "join_name" => "INNER",
            'class_name' => "SysBranch",
            'foreign_key' => 'ag.company_id',
            'mapping_name' => 'branch',
            'mapping_fields' => 'id,name',
            "mapping_key" => "id"
        )
    );
}