<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;


class SysMenuOperationModel extends DataModel {
    
    public function bulid_menu_operation_tree(&$children = null) {
        static $operationList = array(), $operation_menuList = array(), $menu_tree = array();
        if (empty($menu_tree)) {
            $menuList = M("SysMenu")->where("is_valid=1")->field("id,name as text, 'closed' as state, parent_id")->select();
            $menu_tree = list_to_tree($menuList);        
            $operationList = M("SysOperation")->field("id,name as text,menu_id")->select();
            $list = M("SysMenuOperation")->field("menu_id,operation_id, true as checked")->select();
            foreach ($list as $value) {
                $operation_menuList[$value["menu_id"] . "_" . $value["operation_id"]] = $value;
            }
            $children = &$menu_tree;
        }
        foreach ($children as $key => &$value) {
            if ($value["children"]) {
                $this->bulid_menu_operation_tree($value["children"]);
            } else {
                foreach ($operationList as $operation) {
                    if (empty($operation["menu_id"]) || ($operation["menu_id"] == $value["id"])) {//空的menu_id或menu_id存在
                        if ($operation_menuList[$value["id"] . "_" . $operation["id"]]) {
                            $operation["checked"] = true;
                        }
                        $operation["leaf"] = true;
                        $operation["parent_id"] = $value["id"];
                        $value["children"][] = $operation;
                    }
                }
            }
        }
        return $menu_tree;
    }
    
    public function updateRelation($data){
        $this->where("1=1")->delete();
        if ($data) {
            $dataList = array();
            foreach ($data as $value) {
                list($menu_id, $operation_id) = explode("_", $value);
                if ($menu_id && $operation_id) {
                    $dataList[] = array("menu_id" => intval($menu_id), "operation_id" => intval($operation_id));
                }
            }
            if ($dataList) {
                return ($this->addAll($dataList) !== false);
            }
        }
        return true;
    }
}
