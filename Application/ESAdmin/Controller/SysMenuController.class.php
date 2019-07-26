<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\TreeController;

class SysMenuController extends TreeController {

    protected function _before_detail(&$data) {
        parent::_before_detail($data);
        $condition["menu_id"] = $data["id"];
        $operations = M("SysMenuOperation")->where($condition)->field("operation_id")->select();
        foreach ($operations as $value) {
            $operationList[] = $value["operation_id"];
        }
        $data["operations"] = implode(",", $operationList);
    }
    
    protected function _before_add(&$data) {
        parent::_before_add($data);
        $data["is_dac"] = 1;
        $data["is_show"] = 1;
        $data["is_online"] = 1;
        $data["style"] = 0;
    }

    protected function _parseOrder(&$order) {
        $order = "sort";
    }

    public function comingSoonShowAction()
    {
        $this->display('coming_soon');
    }

    public function addOperationAction($menu_id=""){
        $this->assign("menu_id",$menu_id);
        $this->display('addOperation');
    }

    public function checkOperationAction($action,$menu_id){
        if($menu_id != ""){
            $count = M("SysOperation")->where("(menu_id is NULL or menu_id = 0 or menu_id = $menu_id ) and action = '$action' ")->count();
        }else{
            $count = M("SysOperation")->where("(menu_id is NULL or menu_id = 0) and action = '$action' ")->count();
        }
        if($count >=1){
            $this->ajaxReturn(array("err"=>1,"message"=>"权限动作重复！"));
        }else{
            $this->ajaxReturn(array("err"=>0,"message"=>"添加成功！"));
        }


    }
}
