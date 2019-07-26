<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;


class DistributionWithdrawalModel extends DataModel {
    protected $_link = array(
        "SysUser" => array(
            "join_name" => "LEFT",
            'class_name' => "SysUser",
            'foreign_key' => 'user_id',
            'mapping_name' => 'user',
            'mapping_fields' => 'name,mobile',
            "mapping_key" => "id"
        )
    );
}
