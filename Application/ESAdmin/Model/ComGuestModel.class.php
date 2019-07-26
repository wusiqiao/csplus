<?php

namespace ESAdmin\Model;


class ComGuestModel extends SysUserModel {
    protected $tableName = 'sys_user';
    protected $_filter = ['is_follow' => 0,'mobile' =>array(array('eq',''),array('exp','is null'),array('eq',0),'or'),
        'user_TYPE' =>array('between','3,5')];

    protected $_link = array(
        "Company" => array(
            "join_name" => "LEFT",
            'class_name' => "SysBranch",
            'foreign_key' => 'branch_id',
            'mapping_name' => 'company',
            'mapping_fields' => 'name',
            "mapping_key" => "id"
        ),
        "Group" => array(
            "join_name" => "LEFT",
            'class_name' => "sysTargetGroup",
            'foreign_key' => 'group_id',
            'mapping_name' => 'groups',
            'mapping_fields' => 'value',
            "mapping_key" => "id"
        )
    );
}