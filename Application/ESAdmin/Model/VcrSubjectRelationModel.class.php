<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class VcrSubjectRelationModel extends DataModel {

    protected $tableName = 'vcr_sys_subject';
    protected $_link = array(
        "VcrSubject" => array(
            "join_name" => "LEFT",
            'class_name' => "VcrSubject",
            'foreign_key' => 'id',
            'mapping_name' => 'user_subject',
            'mapping_fields' => 'name,id',
            "mapping_key" => "vcr_sys_subject_id"
        )
     );
}
