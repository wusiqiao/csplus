<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;


class SysRoleController extends DataController {

    function permitAction($id = 0) {
        $result = D(CONTROLLER_NAME)->getPermit($this->_user_session, $id);
        list($menuList, $operationList, $operation_menuList) = $result;
        $tree = $this->build_tree($menuList, 0, $operationList, $operation_menuList, true);
        $data["permits"] = json_encode($tree);
        $data["id"] = $id;
        $this->assign("model", $data);
        $this->display();
    }

    function updatePermitAction($id = 0, $data = null) {
        if (IS_POST) {
            $result = D("SysRole")->updatePermit($id, $data);
            $this->ajaxReturn($result);
        }
    }
    public function keyNameListAction($selected = "", $term = "", $select_all = false) {
        if ($this->_user_session->isPlatformUser) {
           parent::keyNameListAction($selected, $term, true);
        } else {
            switch ($this->_user_session->userType){
                case USER_TYPE_SYSTEM_MANAGER:
                   $condition["id"] = array("lt", ROLE_ID_PLATFORM_MAX);
                   break;
                case USER_TYPE_COMPANY_MANAGER:
                    $branch_role = M("SysBranch")->where("id=".$this->_user_session->currBranchId)->getField("branch_role");
                    if ($branch_role){ //商户角色，可能为免费版或付费版
                        $conditon["id"] = array("in", array($branch_role, ROLE_ID_CUSTOMER));
                    }else{
                        $conditon["id"] = array("in", array(ROLE_ID_CUSTOMER));
                    }
//                case USER_TYPE_COMPANY_SALES:
//                    $conditon["id"] = array("in", array(ROLE_ID_COMPANY_SALES, ROLE_ID_CUSTOMER));
                    break;
                case USER_TYPE_CUSTOMER:
                    $condition["id"]  = ROLE_ID_CUSTOMER;
                    break;
                default:
                    $condition["id"]  = 0;
            }
            $result = M("SysRole")->field("id as value,name as text")->where($condition)->select();
            $this->ajaxReturn($result);
        }
    }
//     public function keyNameListAction($selected = "", $term = "", $select_all = false) {
//        if ($this->_user_session->isAdmin) {
//           parent::keyNameListAction($selected, $term, true);
//        } else {
//            $result = array();
//            $user_data = M("SysUser")->where("id=".$this->_user_session->userId)->field("role_ids")->find();
//            if ($user_data["role_ids"]){
//                $role_menu_list = M("SysRoleOperation")->field("role_id,menu_id")->group("role_id,menu_id")->select();
//                $roles = array();
//                foreach ($role_menu_list as $key=>$value) {
//                    $roles[$value["role_id"]][] = & $role_menu_list[$key]["menu_id"];
//                }
//                $user_rols_menus = array();
//                $role_id_array  = explode(",", $user_data["role_ids"]);
//                foreach ($role_id_array as $value) {
//                    $user_rols_menus = array_merge($user_rols_menus, $roles[$value]);
//                }
//                $son_role_array = array();
//                foreach ($roles as $key=>$value) {
//                    $inter_array = array_intersect($user_rols_menus, $value);
//                    if (count($inter_array) == count($value)){
//                        $son_role_array[] = $key;
//                    }
//                }
//                $result = array();
//                if ($son_role_array){
//                    $result = M("SysRole")->field("id as value,name as text")
//                            ->where(array("id"=>array("in", $son_role_array)))
//                            ->select();
//                }
//                $this->ajaxReturn($result);
//            }
//        }
//    }


}
