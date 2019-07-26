<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;
//ceshi
class SysUserRelationTagModel extends DataModel {

    protected $_link = array(
        "SysUser" => array(
            "join_name" => "LEFT",
            'class_name' => "SysUser",
            'foreign_key' => 'user_id',
            'mapping_name' => 'user',
            'mapping_fields' => 'name',
            "mapping_key" => "id"
        ),
        "SysTargetTag" => array(
            "join_name" => "LEFT",
            'class_name' => "SysTargetTag",
            'foreign_key' => 'tag',
            'mapping_name' => 'tag',
            'mapping_fields' => 'value',
            "mapping_key" => "id"
        )

    );

}
