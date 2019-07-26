<?php

namespace ESAdmin\Model;
use Common\Lib\Model\DataModel;

class ComSundryRecordModel extends DataModel
{
    protected $_link = array(
        "SysUser" => array(
            "join_name" => 'LEFT',
            'class_name' => 'SysUser',
            'foreign_key' => 'user_id',
            'mapping_name' => 'user',
            'mapping_fields' => 'name',
            "mapping_key" => 'id'
        )
    );
}