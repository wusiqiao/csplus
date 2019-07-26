<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;
use Common\Lib\Model\ComplexDataModel;

class WrkInvoicePlanDetailModel extends ComplexDataModel {
    protected $tableName = "wrk_invoice_plan";
    protected $_access_module = "WrkInvoicePlan";
    //protected $_filter = ['type' =>array('eq','0')];

    //protected $_createrField = "creater_id"; //创建人字段，如果是客户，可以设置成客户对应的字段
    protected $_leaderField = "leader_id";//负责人字段
    protected $_visiblersField = "visiblers"; //可见人字段
    protected $_collaboratorsField = "collaborators"; //协作人
    protected $_companyField = "company_id"; //客户字段

    protected $_link = array(
        /*"WrkInvoicePlan" => array(
            "join_name" => "INNER",
            'class_name' => "WrkInvoicePlan",
            'foreign_key' => 'plan_id',
            'mapping_name' => 'wip',
            'mapping_fields' => 'agreement_id,amount_paid,state,leader_id,visiblers,comments,attach_group,creater_id,is_set_plan,is_sendWX',
            "mapping_key" => "id"
        ),*/
        "WrkInvoicePlanDetail" => array(
            "join_name" => "INNER",
            'class_name' => "WrkInvoicePlanDetail",
            'foreign_key' => 'id',
            'mapping_name' => 'wipd',
            'mapping_fields' => 'id,plan_day,plan_money,state,true_money,invoice_day,is_invoice',
            "mapping_key" => "plan_id"
        ),
        "WrkAgreement" => array(
            "join_name" => "INNER",
            'class_name' => "WrkAgreement",
            'foreign_key' => 'a.agreement_id',
            'mapping_name' => 'ag',
            'mapping_fields' => 'id,name,company_id,customer_leader_id,leader_id,agreement_sn,sys_sn,agreement_money,finish_time,start_time',
            "mapping_key" => "id"
        ),
        "SysBranch" => array(
            "join_name" => "LEFT",
            'class_name' => "SysBranch",
            'foreign_key' => 'ag.company_id',
            'mapping_name' => 'company',
            'mapping_fields' => 'id,name',
            "mapping_key" => "id"
        )
    );
}