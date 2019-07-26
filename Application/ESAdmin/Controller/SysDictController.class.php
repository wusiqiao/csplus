<?php
namespace ESAdmin\Controller;
use Common\Lib\Controller\DataController;

class SysDictController extends DataController {

    public function getGroupValuesAction($group = ""){
       $list = D(CONTROLLER_NAME)->getGroupValues($group);
       $this->ajaxReturn($list); 
    }
    
    public function getGroupsAction(){
       $list = M(CONTROLLER_NAME)->field("id as value,name as text,querykey")->where("parent_id=0")->select();
       $this->ajaxReturn($list); 
    }  

     public function treeAction() {
        $dicts = D(CONTROLLER_NAME)->field("id,name as text, 'closed' as state, parent_id")->where("parent_id=0")->select();
        $this->ajaxReturn(list_to_tree($dicts));
    }
    
    protected function _before_add(&$data) {
        $data["parent_id"] = I("parent_id");
    }
}