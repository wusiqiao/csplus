<?php

namespace ESAdmin\Model;
use Common\Lib\Model\DataModel;

class ComSundryItemModel extends DataModel
{
    protected function _before_insert(&$data, $options)
    {
        parent::_before_insert($data, $options);
        $data['created_at'] = time();
        $data['updated_at'] = time();
    }
    protected function _before_update(&$data, $options)
    {
        parent::_before_update($data, $options);
        $data['updated_at'] = time();
    }

    protected $_link = array(
        "Sundry" => array(
            "join_name" => 'LEFT',
            'class_name' => 'ComSundry',
            'foreign_key' => 'sundry_id',
            'mapping_name' => 'sundry',
            'mapping_fields' => 'name',
            "mapping_key" => 'id'
        )
    );
}