<?php

namespace ESAdmin\Model;

use ESAdmin\Model\BranchBaseModel;


class ComDepartmentModel extends BranchBaseModel {
    protected $tableName = 'sys_branch';
    protected $_branch_type = ORG_DEPARTMENT;

    protected $_link = array(
        "SysUser" => array(
            "join_name" => "LEFT",
            'class_name' => "SysUser",
            'foreign_key' => 'director_id',
            'mapping_name' => 'director',
            'mapping_fields' => 'name',
            "mapping_key" => "id"
        )
     );
    protected  function getRelativeController(){
        return 'SysBranch';
    }
}
