<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class SysOperationModel extends DataModel {
    
     protected $_link = array(
        "SysMenu" => array(
            "join_name" => "LEFT",
            'class_name' => "SysMenu",
            'foreign_key' => 'menu_id',
            'mapping_name' => 'menu',
            'mapping_fields' => 'name',
            "mapping_key" => "id"
        )
      );
     
     protected function _after_delete($data, $options) {
        $this->deleteRelative($data["id"]);
        parent::_after_delete($data, $options);
    }
    
    protected function _after_update($data, $options) {
        parent::_after_update($data, $options);        
        if ($data["menu_id"]){
            $this->deleteRelative($data["id"]);
            M("SysMenuOperation")->add(array("operation_id" => $data["id"], "menu_id"=>$data["menu_id"]));
        }
    }
    
    protected function _after_insert($data,$options) {
        parent::_after_insert($data, $options);
        if ($data["menu_id"]){
            M("SysMenuOperation")->add(array("operation_id" => $data["id"], "menu_id"=>$data["menu_id"]));
        }
    }
    
    private function deleteRelative($id){
        M("SysRoleOperation")->where(array("operation_id" => $id))->delete();
        M("SysMenuOperation")->where(array("operation_id" => $id))->delete();
    }
}
