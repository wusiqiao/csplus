<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/5
 * Time: 16:42
 */

namespace ESAdmin\Controller;
use Common\Lib\Controller\DataController;


class StaffPermitController extends DataController{
    public function permitAction($id = 0) {
        $result = D("SysRole")->getPermit($this->_user_session, $id);
        list($menuList, $operationList, $operation_menuList) = $result;
        $tree = $this->build_tree($menuList, 0, $operationList, $operation_menuList, true);
        $this->ajaxReturn($tree);
    }

    function updatePermitsAction($id = 0, $data = null) {
        if (IS_POST) {
            $result = D("SysRole")->updatePermit($id, $data);
            $this->ajaxReturn($result);
        }
    }

    public function roleListAction(){
        $condition['branch_id'] = $this->_user_session->currBranchId;
        $condition['is_admin'] = array(array("neq",1),array("exp","is null"),"or");
        $list = M('SysRole')->where($condition)->field('id,name,sort')->order("sort asc")->select();
        foreach ($list as $k=>$role){
            $condition['role_id'] = $role['id'];
            $condition['branch_id'] = $this->_user_session->currBranchId;
            $user_role = M("SysUserRole")->where($condition)->find();
            if($user_role){
                $list[$k]['has_user'] = 1;
            }else{
                $list[$k]['has_user'] = 0;
            }
            if($role['sort'] == "" || $role['sort'] == 0){
                $maxSort = M("SysRole")->where("branch_id = ".$this->_user_session->currBranchId." and sort != ''")->order("sort desc")->limit(0,1)->getField("sort");
                M("SysRole")->where("id = ".$role['id'])->setField("sort",$maxSort +1);
            }
        }
        $adminRole = array("id"=>"","name"=>"超级管理员（系统默认）","is_admin_role"=>1);
        array_unshift($list,$adminRole);
        $list1['children'] = $list;
        $list1['id'] = "all";
        $list1['name'] = $this->_user_session->currBranchName;
        $this->ajaxReturn($list1);
    }

    public function roleListForCopyAction() {
        $name = I("get.q");
        if($name != ""){
            $condition["name"] = array("like",'%'.$name."%");
        }
        $condition["branch_id"] = $this->_user_session->currBranchId;
        $condition['is_admin'] = array(array("neq",1),array("exp","is null"),"or");
        $list = M("SysRole")->where($condition)->field("id,name")->select();
        $this->ajaxReturn($list);
    }

    //角色复制
    public function role_copyAction(){
        if(IS_POST){
            $id = I("post.id");
            $name = I("post.name");
            if(mb_strlen($name) > 10){
                $this->ajaxReturn(array("error"=>1,"message"=>"最多输入10个字！"));
            }
            $is_rep = M("SysRole")->where("name = '$name'")->find();
            if($is_rep){
                $this->ajaxReturn(array("error"=>1,"message"=>"角色名称已存在！"));
            }
            $role = M("SysRole")->where("id = $id")->find();
            if($role){
                unset($role['id']);
                $role['name'] = $name;
                $role['querykey'] = firstPinyin($name);
                $new_roleId = M("SysRole")->add($role);
                if($new_roleId){
                    $role_operation = M("SysRoleOperation")->where("role_id = $id")->select();
                    foreach($role_operation as $k=>$v){
                        $role_operation[$k]['role_id'] = $new_roleId;
                    }
                    $result = M("SysRoleOperation")->addAll($role_operation);
                    if($result){
                        $this->ajaxReturn(array("error"=>0,"message"=>"复制成功！"));
                    }else{
                        M("SysRole")->where("id = $new_roleId")->delete();
                        $this->ajaxReturn(array("error"=>1,"message"=>"复制失败！"));
                    }
                }else{
                    $this->ajaxReturn(array("error"=>1,"message"=>"复制失败！"));
                }
            }else{
                $this->ajaxReturn(array("error"=>1,"message"=>"角色不存在！"));
            }
        }else{
            $branch_id = getBrowseBranchId();
            $roles = M("SysRole")->where("branch_id = $branch_id")->select();
            $this->assign("roles",$roles);
            $this->display();
        }
    }

    public function sortRoleAction($id,$type){
        $condition['branch_id'] = $this->_user_session->currBranchId;
        if($type == "up"){
            $sort = M("SysRole")->where("id = $id")->getField("sort");
            $condition['sort'] = $sort-1 >0 ? $sort-1 : $sort;
        }else{
            $sort = M("SysRole")->where("id = $id")->getField("sort");
            $condition['sort'] = $sort+1;
        }
        $result = M("SysRole")->where($condition)->setField("sort",$sort);
        $result1 = M("SysRole")->where("id = $id")->setField("sort",$condition['sort']);
        if($result && $result1){
            $this->ajaxReturn(array("error"=>0,"message"=>"操作成功！"));
        }else{
            $this->ajaxReturn(array("error"=>1,"message"=>"操作失败！"));
        }
    }
}