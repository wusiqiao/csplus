<?php

namespace Common\Lib\Controller;

use Common\Lib\Controller\DataController;

class TreeController extends DataController {

    public function listAction($id = null) {
        $_filter = array();
        if ($id) { //传入ID，只有在点击父节点的时候才有值
            $_filter["a.parent_id"] = intval($id);
        }else{
            $this->_parsefilter($_filter);
            if (empty($_filter)){ //没有传入条件，且id为空，只显示父节点
                $_filter["a.parent_id"] = intval($id);
            }
        }
        $_order = array("a.id");
        $this->_parseOrder($_order);
        $list = D(CONTROLLER_NAME)->setDacFilter("a")->relation(true)->field("a.*")->where($_filter)->order($_order)->select();
        foreach ($list as $key => $value) {
            $list[$key]["state"] = empty($value["child_count"]) ? "opened" : "closed";
        }
        $this->_before_list($list);
        $this->ajaxReturn($list);
    }

    public function keyNameListAction($selected = "", $term = "", $select_all = false) {
        $list = $this->getKeyNameList($selected, $term, $select_all, true, "a.code");
        $this->ajaxReturn(build_tree_list($list));
    }

    public function keyNameTreeAction() {
        $list = D(CONTROLLER_NAME)->setDacFilter()->field("id,name as text,parent_id,code")->select();
        $this->ajaxReturn(build_tree($list));
    }
    
    public function treeAction($branch_id = 0) {
        if ($branch_id == 0){
            $branch_id = $this->_user_session->currBranchId;
        }
        $condition["branch_id"] = $branch_id;
        $list = D(CONTROLLER_NAME)->where($condition)->field("id,name as text,parent_id")->select();
        $this->ajaxReturn(build_tree($list));
    }
    
    public function simpleKeyNameListAction($selected = "", $term = "", $select_all = false) {
        $list = $this->getKeyNameList($selected, $term, $select_all);
        $this->ajaxReturn($list);
    }

    public function deleteAction($id) {
        $where["parent_id"] = array("in", $id);
        //$where["parent_id"] = array("gt", 0);
        $count = D(CONTROLLER_NAME)->where($where)->count();
        if ($count > 0) {
            $this->ajaxReturn(buildMessage("记录含有子节点，无法删除此记录！", 1));
        }
        parent::deleteAction($id);
    }

}
