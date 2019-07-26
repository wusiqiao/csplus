<?php

namespace ESAdmin\Model;


use Think\Exception;

class ComFansModel extends UserParseModel {
    protected $tableName = 'sys_user';
    protected $_filter = ['is_follow' => 1,'mobile' =>array(array('eq',''),array('exp','is null'),array('eq',0),'or')];

    protected function _options_filter(&$options) {
        if($options['where']['a.user_type'] == ""){
            $this->addOptionsFilter($options, array("user_type" =>array("neq",USER_TYPE_COMPANY_MANAGER)));
        }
        parent::_options_filter($options);
    }

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
    public function getUsersData($ids) {
        $condition['id'] = array('in',$ids);
        return $this->where($condition)->field('id,name,openid')->select();
    }

}