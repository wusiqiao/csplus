<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class SysUserBranchModel extends DataModel {

    protected $_link = array(
        "SysUser" => array(
            "join_name" => "LEFT",
            'class_name' => "SysUser",
            'foreign_key' => 'user_id',
            'mapping_name' => 'user',
            'mapping_fields' => 'account,name,role_group_id',
            "mapping_key" => "id"
        ),
        "SysBranch" => array(
            "join_name" => "LEFT",
            'class_name' => "SysBranch",
            'foreign_key' => 'branch_id',
            'mapping_name' => 'branch',
            'mapping_fields' => 'name',
            "mapping_key" => "id"
        )
      
    );
    
    public function loadBranchUser(&$data){
        $user_record = $this->alias("a")->field("user_id")->relation(true)->where("a.branch_id=".$data["id"])->find();
        if ($user_record){
            $data["user_id"] = $user_record["user_id"];
            $data["account"] = $user_record["user_account"];
            $data["role_group_id"] = $user_record["user_role_group_id"];
        }
    }

}
