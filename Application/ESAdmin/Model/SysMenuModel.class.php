<?php

namespace ESAdmin\Model;

use Common\Lib\Model\TreeDataModel;


class SysMenuModel extends TreeDataModel {

    protected function _after_insert($data, $options) {
        parent::_after_insert($data, $options);
        $this->insert_operations($data, $options);
        S(MENU_LIST_KEY, null);
    }

    private function insert_operations($data, $options) {
        $dataList = array();
        $inputs = I("post.menu_operation_inputs");
        if($inputs == ""){
            $inputs = array();
        }
        $tmp['name'] = I("post.operation_name");
        $tmp['action'] = I("post.operation_action");
        $tmp1['branch_id'] = getBrowseBranchId();
        $tmp1['menu_id'] = $data['id'];
        if($tmp['name'] && $tmp['action']){
            $name = explode(",",$tmp['name']);
            $action = explode(",",$tmp['action']);
            for($i=0;$i<count($name);$i++){
                $tmp1['querykey']= firstPinyin($name[$i]);
                $tmp1['name'] = $name[$i];
                $tmp1['action'] = $action[$i];
                $id = M("SysOperation")->add($tmp1);
                $result = M("SysOperation")->where("id = $id")->find();
                if(in_array($result['name'],$inputs)){
                    array_push($inputs,$result['id']);
                    $inputs = array_diff($inputs, [$result['name']]);
                }
            }
        }
        foreach ($inputs as $key => $value) {
            if ($value > 0) {
                $dataList[] = array("menu_id" => $data["id"], "operation_id" => $value);
            }
        }
        M("SysMenuOperation")->addAll($dataList);
    }
    
    protected function _after_update($data, $options) {
        parent::_after_update($data, $options);
        if (I("post.menu_operation_inputs_changed")){
            M("SysMenuOperation")->where(array("menu_id" => $data["id"]))->delete();
            $this->insert_operations($data, $options);
        }
        S(MENU_LIST_KEY, null);
    }
    protected function _after_delete($data, $options) {
        M("SysMenuOperation")->where(array("menu_id" => $data["id"]))->delete();
        M("SysRoleOperation")->where(array("menu_id" => $data["id"]))->delete();
        S(MENU_LIST_KEY, null);
        parent::_after_delete($data, $options);
    }

    public function queryModuleUsers($module, $query){
        $user_session = session(USER_SESSION_KEY);
        $sql = sprintf("select distinct user.id,user.name,user.staff_name,user.mobile,user.querykey from sys_user user 
                inner join sys_user_role ur on ur.user_id=user.id
                inner join sys_role_operation ro on ro.role_id=ur.role_id
                inner join sys_menu menu on menu.id=ro.menu_id
                where user.branch_id=%d and menu.url='%s' and user.user_type = %d
                and (user.name like '%s%%' or user.querykey like '%s%%' or user.mobile like '%s%%' or user.staff_name like '%s%%')",
            $user_session->currBranchId, $module,USER_TYPE_COMPANY_MANAGER ,$query, $query, $query,$query);
        return $this->query($sql);
    }
}
