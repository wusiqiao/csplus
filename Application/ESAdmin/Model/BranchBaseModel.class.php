<?php

namespace ESAdmin\Model;

use Common\Lib\Model\TreeDataModel;

class BranchBaseModel extends TreeDataModel {
    protected $tableName = 'sys_branch';
    protected $_branch_type = ORG_DEPARTMENT;
    protected $_auto = array ( 
         array('update_time','time',3,'function') // 对update_time字段在更新的时候写入当前时间戳
    );
    
//     protected $_validate = array(
//        array('name', '', '名称已经存在！！', 0, 'unique', 3)
//    );

    protected function _options_filter(&$options) {
        $this->addOptionsFilter($options, array("type" => $this->_branch_type));
        parent::_options_filter($options);
    }

    public function __construct($name = '', $tablePrefix = '', $connection = '') {
        parent::__construct($name, $tablePrefix, $connection);
        $this->_auto[] = array("type", $this->_branch_type);
    }

    protected function _before_write(&$data) {
        parent::_before_write($data);
        if (empty($data["parent_id"])){
            E("必须有父级部门！");
        }
        $data["branch_id"] = $data["parent_id"];
        if (empty($data["querykey"])){
            $data["querykey"] = firstPinyin($data["name"]);
        }
        //名称检查
        $c["name"] = $data["name"];
        $c["branch_id"] = $data["branch_id"];
        if (($data["id"])){
            $c["id"] = array("neq", $data["id"]);
        }
        if (M("SysBranch")->where($c)->count() > 0){
            E("公司/部门名称已经存在");
        }
    }

    /*获取部门列表，包括公司、项目*/
    public function getUserBranchList($condition, $user_session, $is_filter = true) {
        $fields = "a.id as value,a.name as text,a.parent_id,a.type";
        $condition["a.is_valid"] = 1;
        if (!$user_session->isPlatformUser) {
            $condition["a.parent_id"] = $user_session->currBranchId;
        }
        $branch_list = $this->setDacFilter("a")->callFilter($is_filter)->where($condition)->field($fields)->order("a.code")->select();
//        if (!$user_session->isAdmin) {
//            array_unshift($branch_list,
//                    array("value" => $user_session->currBranchId,
//                        "text" => $user_session->currBranchName,
//                        "parent_id" => $user_session->parentBranchId
//                    )
//            );
//        }
        return build_tree_list($branch_list);
    }

    /*获取项目树形结构，包括公司、项目*/
    public function getUserBranchTree($condition, $user_session, $is_filter = false) {  
        $fields = "a.id,a.name as text, 'open' as state, a.parent_id,a.code,a.type";
        $condition["a.is_valid"] = 1;
        if (!$user_session->isPlatformUser) {
            $condition["a.parent_id"] = $user_session->currBranchId;
        } else {
            $condition["a.type"] = 0;
        }
        $branch_list = $this->setDacFilter("a")->callFilter($is_filter)->where($condition)->field($fields)->order("a.code")->select();
        $parent_id = 0;
        if (!$user_session->isPlatformUser) {
            array_unshift($branch_list, 
               array("id" => $user_session->currBranchId, 
                    "text" => $user_session->currBranchName, 
                    "state"=>"open",
                    "type"=>$user_session->currBranchType,
                    "code"=>$user_session->currBranchCode,
                    "parent_id" => $user_session->parentBranchId
                    )
                );
            $parent_id = $user_session->parentBranchId;
        }
        return list_to_tree($branch_list, $parent_id);       
    }

}
