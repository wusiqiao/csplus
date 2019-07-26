<?php

namespace ESAdmin\Model;
use Common\Lib\Model\DataModel;

class ComSundryTransferModel extends DataModel
{
    protected $_link = array(
        "Borrower" => array(
            "join_name" => 'LEFT',
            'class_name' => 'SysUser',
            'foreign_key' => 'borrower',
            'mapping_name' => 'borrower',
            'mapping_fields' => 'name',
            "mapping_key" => 'id'
        ),
        "Lender" => array(
            "join_name" => 'LEFT',
            'class_name' => 'SysUser',
            'foreign_key' => 'lender',
            'mapping_name' => 'lender',
            'mapping_fields' => 'name',
            "mapping_key" => 'id'
        )
    );

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
}