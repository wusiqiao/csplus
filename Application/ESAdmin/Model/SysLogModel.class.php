<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class SysLogModel extends DataModel {
    protected $_link = array(
        "SysMenu" => array(
            "join_name" => "LEFT",
            'class_name' => "SysMenu",
            'foreign_key' => 'func',
            "mapping_key" => "url",
            'mapping_name' => 'menu',
            'mapping_fields' => 'name'
        )
    );
}
