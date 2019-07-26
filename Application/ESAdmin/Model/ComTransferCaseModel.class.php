<?php

namespace ESAdmin\Model;



use Common\Lib\Model\DataModel;

class ComTransferCaseModel extends DataModel {

    protected $tableName = 'com_recharge';
    protected $_unline_type = FIN_ORDER_LINE_PAY;
    protected $_link = array(
        "Order" => array(
            "join_name" => "LEFT",
            'class_name' => "ComOrder",
            'foreign_key' => 'order_sn',
            'mapping_name' => 'co',
            'mapping_fields' => 'id,contacts',
            "mapping_key" => "order_sn"
        ),
        "Product" => array(
            "join_name" => "LEFT",
            'class_name' => "ComProduct",
            'foreign_key' => 'co.product_id',
            'mapping_name' => 'pro',
            'mapping_fields' => 'product_title',
            "mapping_key" => "id"
        )
    );
    protected function _options_filter(&$options) {
        $this->addOptionsFilter($options, array("money_type" => $this->_unline_type));
        parent::_options_filter($options);
    }

     

}
