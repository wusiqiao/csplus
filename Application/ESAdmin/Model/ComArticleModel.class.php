<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class ComArticleModel extends DataModel {
    protected $_link = array(
        "Category" => array(
            "join_name" => "LEFT",
            'class_name' => "ComArticleCategory",
            'foreign_key' => 'category_id',
            'mapping_name' => 'category',
            'mapping_fields' => 'name',
            "mapping_key" => "id"
        )
    );
}