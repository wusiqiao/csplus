<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class  StaffRoleController extends DataController {
    public function listAction() {
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $_order = array();
        $this->_parseOrder($_order);
        $_filter = array();

        $this->_parseFilter($_filter);
        $_filter['a.user_type'] = USER_TYPE_COMPANY_MANAGER;

        $role_id = I("role_id");
        $user_ids = [];
        $condition = [];
        $condition['b.branch_id'] = $this->_user_session->currBranchId;
        $condition['a.role_id'] = $role_id;
        $sysUserRole = M('SysUserRole')
        ->alias("a")
        ->join('LEFT JOIN sys_role b ON b.id = a.role_id')
        ->where($condition)->select();
        if (!empty($sysUserRole)) {
            foreach ($sysUserRole as $k => $v) {
                array_push($user_ids,$v['user_id']);
            }
            $_filter['a.id'] = array('in',$user_ids);
        } else {
            $_filter['a.id'] = 0;
        }

        $count = D('SysUser')->setDacFilter("a")->where($_filter)->count();
        $list = D('SysUser')->setDacFilter("a")->relation(true)->field("a.*")->where($_filter)->page($page_index, $page_size)->order($_order)->select();
        $this->_before_list($list);
        $result["total"] = $count;
        $result["rows"] = $list;
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($result));
    }

    public function roleListAction()
    {
        $condition = [];
        $condition['branch_id'] = $this->_user_session->currBranchId;
        $list = M('SysRole')->where($condition)->field('id,name')->select();
        $this->ajaxReturn($list);
    }
    public function addRoleAction()
    {
        if (IS_POST) {
            $condition = [];
            $condition["branch_id"] = $this->_user_session->currBranchId;
            $condition["name"] = I("post.name");
            if(mb_strlen($condition["name"],'utf-8') > 10){
                $this->ajaxReturn(buildMessage('最多输入10个字！',1));
            }
            $sysUser = M("SysRole")->where($condition)->find();
            if (!empty($sysUser)) {
                $this->ajaxReturn(buildMessage('已存在同名的角色',1));
            }else{
                $data = $condition;
                $data["is_valid"] = 1;
                $data["sort"] = M("SysRole")->where("branch_id = ".$this->_user_session->currBranchId)->order("sort desc")->limit(0,1)->getField("sort")+1;
                //$data["parent_id"] = $parent_id;
                $model = M("SysRole");
                try {
                    $model->startTrans();
                    $last_id = $model->add($data, array("callback"=>true));
                    $model->commit();
                } catch (Think\Exception $ex) {
                    $model->rollback();
                    $last_id = false;
                }
                if($last_id){
                    $this->ajaxReturn(array('code'=>0,'message'=>'添加角色成功'));
                }else{
                    $this->ajaxReturn(array('code'=>1,'message'=>'添加角色失败'));
                }
            }
        }else{
            $this->role_action = 'addRole';
            $this->display('addRole');
        }
    }
    public function editRoleAction($id)
    {
        $condition = [];
        $condition["branch_id"] = $this->_user_session->currBranchId;
        $condition["id"] = $id;
        $model = M("SysRole");
        if (IS_POST) {
            $post_data = I('post.');
            if ($data = $model->create($post_data)) {

                $condition = [];
                $condition["branch_id"] = $this->_user_session->currBranchId;
                $condition["id"] = array('neq',$id);
                $condition["name"] = I("post.name");
                $sysUser = M("SysRole")->where($condition)->find();
                if (!empty($sysUser)) {
                    $this->ajaxReturn(buildMessage('已存在同名的角色',1));
                }

                $condition = [];
                $condition["branch_id"] = $this->_user_session->currBranchId;
                // $condition["name"] = I("post.name");              
                $condition["id"] = $id;
                $updated = false;
                try {
                    $model->startTrans();
                    $updated = $model->where($condition)->save($data);
                    $model->commit();
                } catch (\Think\Exception $ex) {
                    $model->rollback();
                    $this->responseJSON(buildMessage("保存失败：" . $ex->getMessage(), 1));
                }
                if ($updated !== false) {
                    $this->ajaxReturn(array('code'=>0,'message'=>'编辑角色成功'));
                }
            } else {
                $this->responseJSON(buildMessage("保存失败：" . $model->getError(), 1));
            }
        } else {
            $sysRole = M("SysRole")->where($condition)->field('id,name')->find();
            
            $condition = [];
            $condition['b.role_id'] = $id;
            $condition['a.branch_id'] = $this->_user_session->currBranchId;
            $sysUser = M('SysUser')
            ->alias('a')
            ->join('LEFT JOIN sys_user_role b ON b.user_id = a.id')
            ->where($condition)->select();

            if (!empty($sysUser)) {
                $this->has_user = 1;
            }else{
                $this->has_user = 0;
            }

            $this->role_action = 'editRole';
            $this->assign("model", $sysRole);
            $this->display('addRole');
        }
    }

    public function deleteRoleAction($id) {
        if (IS_POST) {
            $condition = [];
            $condition['b.role_id'] = $id;
            $condition['a.branch_id'] = $this->_user_session->currBranchId;
            $sysUser = M('SysUser')
            ->alias('a')
            ->join('LEFT JOIN sys_user_role b ON b.user_id = a.id')
            ->where($condition)->select();

            M("SysRole")->where(['id'=>$id,'branch_id'=>$this->_user_session->currBranchId])->delete();
            if (!empty($sysUser)) {
                M("SysUserRole")->where(['role_id'=>$id])->delete();
                foreach ($sysUser as $k => $v) {
                    $role_ids = [];
                    $sysUserRole = D("SysUserRole")
                    ->where([
                        'role_id'=>$id,
                        'user_id'=>$v['id']
                    ])->select();
                    foreach ($sysUserRole as $k1 => $v1) {
                        array_push($role_ids,$v['role_id']);
                    }

                    $data['role_ids'] = implode(",",$role_ids);
                    M("SysUser")->where(['id'=>$v['id'],'branch_id'=>$this->_user_session->currBranchId])->save($data);
                }
            }
            $this->ajaxReturn(array('code'=>0,'message'=>'删除角色成功'));
            
        }
    }

    public function addStaffAction($role_id) {
        if (IS_POST) {
            $user_ids = I('post.user_ids');
            $condition = [];
            $sys_user_role_data = [];
            foreach ($user_ids as $k => $v) {
                // $role_ids = []; 
                // $condition['user_id'] = $v;
                // $sysUserRole = D("SysUserRole")->where($condition)->select();
                // foreach ($sysUserRole as $k1 => $v1) {
                //     array_push($role_ids,$v1['role_id']);
                // }
                $condition = [];
                $condition['id'] = $v;
                $data['role_ids'] = $role_id;
                $sysUser = M("SysUser")->where($condition)->save($data);
                $sysUserRole = D("SysUserRole")
                    ->where([
                        'user_id'=>$v
                    ])->delete();
                $sysUserRole = D("SysUserRole")
                    ->where([
                        'role_id'=>$role_id,
                        'user_id'=>$v
                    ])->find();
                if (empty($sysUserRole)) {
                    $sys_user_role_data[$k]['user_id'] = $v;
                    $sys_user_role_data[$k]['role_id'] = $role_id;
                }
            }
            $result = M('SysUserRole')->addAll($sys_user_role_data);
            if ($result) {
                $this->ajaxReturn(array('code' => 0, 'message' => '添加人员成功'));
            } else {
                $this->ajaxReturn(array('code' => 1, 'message' => '添加人员失败'));
            }
        }else{
            $sysUser = [];
            $condition = [];
            $condition['a.branch_id'] = $this->_user_session->currBranchId;
            $condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
            $condition['b.role_id'] = $role_id;
            $condition['_string'] = '(a.is_follow = 1 or (a.mobile is not null and a.mobile <> "" ) )';

           $list = M('SysUser')->alias('a')
            ->join('LEFT JOIN sys_user_role b ON b.user_id = a.id')
            ->where($condition)->field('a.id,a.name,a.staff_name,a.comments')->order('id desc')->group('a.id')->select();

            if (!empty($list)) {
                foreach($list as $k => $v) {
                    if (empty($list[$k]['staff_name'])) {
                        if (empty($list[$k]['comments'])) {
                            $list[$k]['staff_name'] = $list[$k]['name'];
                        }else{
                            $list[$k]['staff_name'] = $list[$k]['comments'];
                        }
                    }
                }
            }
            $sysUser['in'] = $list;

            $user_ids = [];
            if (!empty($sysUser['in'])) {
                foreach ($sysUser['in'] as $k => $v) {
                    array_push($user_ids,$v['id']);
                }
            }
            $condition = [];
            $condition['branch_id'] = $this->_user_session->currBranchId;
            $condition['user_type'] = USER_TYPE_COMPANY_MANAGER;
            $condition['is_leader'] = 0;
            $condition['_string'] = '(is_follow = 1 or (mobile is not null and mobile <> "" ) )';
            $condition['id'] = array('neq',$this->_user_session->userId);
            if (!empty($user_ids)) {
                $condition['id'] = array('not in',$user_ids);
            }
            $list = M('SysUser')
            ->where($condition)->field('id,name,staff_name,comments')->select();
            if (!empty($list)) {
                foreach($list as $k => $v) {
                    if (empty($list[$k]['staff_name'])) {
                        if (empty($list[$k]['comments'])) {
                            $list[$k]['staff_name'] = $list[$k]['name'];
                        }else{
                            $list[$k]['staff_name'] = $list[$k]['comments'];
                        }
                    }
                }
            }
            $sysUser['out'] = $list;


            $this->assign("model", $sysUser);
            $this->role_id = $role_id;
            $this->display('addStaff');
        }
    }

    public function deleteStaffAction($role_id) {
        if (IS_POST) {
            $condition = [];
            $user_ids = I('post.user_ids');
            $condition['user_id'] = array('in',$user_ids);
            $condition['role_id'] = $role_id;
            M('SysUserRole')->where($condition)->delete();

            $condition = [];
            foreach ($user_ids as $k => $v) {
                $role_ids = [];            
                $condition['user_id'] = $v;
                $sysUserRole = D("SysUserRole")->where($condition)->select();
                foreach ($sysUserRole as $k1 => $v1) {
                    array_push($role_ids,$v1['role_id']);
                }
                $condition = [];
                $condition['id'] = $v;
                $data['role_ids'] = implode(",",$role_ids);
                $sysUser = D("SysUser")->where($condition)->save($data);
            }
            $this->ajaxReturn(array('code'=>0,'message'=>'删除员工成功'));
        }
    }


}