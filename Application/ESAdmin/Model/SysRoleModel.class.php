<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class SysRoleModel extends DataModel {

    protected function _options_filter(&$options) {
//        $branch_id = getBrowseBranchId();
//        if($branch_id != ""){
//            $this->addOptionsFilter($options, array("branch_id" => $branch_id));
//        }
        //系统角色不需要看到商户设定的角色
        $condition["branch_id"] = array("EXP", "IS NULL");
        $this->addOptionsFilter($options, $condition);
        parent::_options_filter($options);
    }


    /*删除前检查*/
    protected function _before_delete($options) {
        $list = $this->field("id")->where($options["where"])->select();
        foreach ($list as $value) {
           if ($value["id"] <= ROLE_ID_CUSTOMER){
               $this->error = "系统预设角色，无法删除！";
               return false;
           } 
        }
        parent::_before_delete($options);        
    }


    protected function _after_delete($data, $options) {
        M("SysRoleOperation")->where(array("role_id" => $data["id"]))->delete();
        parent::_after_delete($data, $options);
    }
       
    
    protected function _after_copy($data, $options) {
        if ($data["_COPY_DATA_ID_"]){
            $sql =sprintf("Insert into sys_role_operation(role_id,operation_id,menu_id) "
                    . "select %d as role_id,operation_id,menu_id from sys_role_operation where role_id=%d",$data["id"], $data["_COPY_DATA_ID_"]);
            $this->execute($sql);
        }
    }

    function getPermit($_user_session, $id = 0) {
        if ($_user_session->isAdmin) {
            $menuList = D("SysMenu")->where("is_valid=1")->field("id,name as text, 'closed' as state, parent_id")->select();
            $operationList = M("SysOperation")->field("id,name as text")->select();
        }else{
            $user_id = $_user_session->userId;
            $role_menu_operations = M("SysUserRole a")->join("inner join sys_role_operation b on b.role_id=a.role_id")
                ->field("b.menu_id,b.operation_id")
                ->group("b.menu_id,b.operation_id")
                ->where("a.user_id=$user_id")->select();
            $role_menus =  array_unique(array_column($role_menu_operations, "menu_id"));
            $menuList = D("SysMenu")->where(array("id"=>array("in", $role_menus)))->field("id,name as text, 'closed' as state, parent_id,code")->select();
            $menu_codes = array();
            foreach ($menuList as $menu){
                $codes = explode("_", $menu["code"], 2);
                $menu_codes[] = sprintf("code = '%s'", $codes[0]);
            }
            //$where = "(".implode(" or ", $menu_codes).") and is_valid = 1";
            //$where = "(".implode(" or ", $menu_codes).") and is_show = 1";
            $where = "(".implode(" or ", $menu_codes).") and is_show = 1 and name != '智能凭证'";
            $parent_menuList = D("SysMenu")->where($where)->field("id,name as text, 'closed' as state, parent_id,code")->select();
            $menuList = array_merge($menuList, $parent_menuList);
            $role_operations = array_column($role_menu_operations, "operation_id");
            $operationList = M("SysOperation")->where(array("id"=>array("in", $role_operations)))->field("id,name as text")->select();
        }
        $operation_menuList = M("SysMenuOperation")->field("menu_id as parent_id,operation_id as id, true as checked")->select();
        $condition["role_id"] = intval($id);
        $role_operationList = M("SysRoleOperation")->where($condition)->field("menu_id as parent_id,operation_id as id")->select();
        $combineList = array();
        foreach ($role_operationList as $value) {
            $combine_id = $value["parent_id"] . "_" . $value["id"];
            $combineList[$combine_id] = $value;
        }
        foreach ($operation_menuList as &$value) {
            $combine_id = $value["parent_id"] . "_" . $value["id"];
            $value["checked"] = !empty($combineList[$combine_id]);
        }
        return array($menuList, $operationList, $operation_menuList);
    }

    public  function updatePermit($id, $data){
        $id = intval($id);
        if ($id > 0) {
            $condition["role_id"] = $id;
            M("SysRoleOperation")->where($condition)->delete();
            if(!$data){
                return (buildMessage("更新成功！"));
            }
        }
        if ($data) {
            $dataList = array();
            foreach ($data as $value) {
                list($menu_id, $operation_id) = explode("_", $value);
                if ($menu_id && $operation_id) {
                    $dataList[] = array("role_id" => $id, "menu_id" => intval($menu_id), "operation_id" => intval($operation_id));
                }
            }
            if ($dataList) {
                if (M("SysRoleOperation")->addAll($dataList) !== false) {
                    //更新user表内的updated_at
                    //更新user表的update
                    $condition_user = 'FIND_IN_SET ('.$id.',role_ids)';
                    $save_user['updated_at'] = time();
                    M('SysUser')->where($condition_user)->save($save_user);
                    return (buildMessage("更新成功！"));
                } else {
                    return (buildMessage("更新失败！", 1));
                }
            }
        }
    }
}
