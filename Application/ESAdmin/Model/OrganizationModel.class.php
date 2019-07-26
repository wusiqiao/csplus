<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

use Think\Exception;

class OrganizationModel extends SysUserModel {
    protected $tableName = 'sys_user';
    // protected $_link = array(
    //     "Deptment" => array(
    //         "join_name" => "LEFT",
    //         'class_name' => "SysBranch",
    //         'foreign_key' => 'dept_id',
    //         'mapping_name' => 'dept',
    //         'mapping_fields' => 'name',
    //         "mapping_key" => "id"
    //     )
    // );
}