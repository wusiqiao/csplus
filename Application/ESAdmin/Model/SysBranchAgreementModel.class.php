<?php

namespace ESAdmin\Model;

//use ESAdmin\Model\BranchBaseModel;
use Common\Lib\Model\DataModel;

class SysBranchAgreementModel extends DataModel{
    protected $_leaderField = "branch.tracker_id";
    /*protected $tableName = 'sys_branch';*/
    protected $_link = array(
        "SysUser" => array(
            "join_name" => "LEFT",
            'class_name' => "SysUser",
            'foreign_key' => 'leader_id',
            'mapping_name' => 'leader',
            'mapping_fields' => 'name',
            "mapping_key" => "id"
        ),
        "SysBranch" => array(
            "join_name" => "LEFT",
            'class_name' => "SysBranch",
            'foreign_key' => 'branch_id',
            'mapping_name' => 'branch',
            'mapping_fields' => 'attach_group,name,leader_id,tracker_id',
            "mapping_key" => "id"
        )
    );

    protected $_validate = array(
        array('name', '', '商户名称已经存在！', 0, 'unique', 3)
    );
    protected $_branch_type = ORG_BRANCH;

    public function getMaxAgreementNo() {
        //$condition["start_date"] = date("Y-m-d");
        $condition["agreement_no"] = array("like",date("Ymd")."%");
        $max_datebill = M("SysBranchAgreement")->where($condition)->max("agreement_no");
        return $this->incBillNo($max_datebill, 4);
    }

    protected function _before_delete($options){
        //删除该客户最后一个服务则变成 意向客户
        $ids = $options['where']['id'][1];
        foreach($ids as $k=>$v){
            $agreement = M("SysBranchAgreement a")->join("sys_branch_agreement b on a.branch_id = b.branch_id")
                ->where("a.id = $v")->field('b.branch_id')->select();
            if(count($agreement) == 1){
                M("SysBranch")->where("id = ".$agreement[0]['branch_id'])->setField("state",0);
            }
        }
    }
}
