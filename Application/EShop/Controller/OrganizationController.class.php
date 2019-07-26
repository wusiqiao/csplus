<?php

namespace EShop\Controller;

use Think\Controller;

class  OrganizationController extends BaseController {
    public function searchAction() {
        $branch_id = I("branch_id");

        $_filter = array();
        // $this->_parseFilter($_filter);
        $_filter['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
        $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
        $where['a.is_follow']  = 1;
        $where['_logic'] = 'or';
        $_filter['_complex'] = $where;

        $_filter['a.branch_id'] = $this->user_branch;
        if(!empty($branch_id) && $branch_id!=$this->user_branch){
        	$user_ids = [];
        	$where = $_filter;
        	$where['b.branch_id'] = $branch_id;
	        $sysUser = M('SysUser')
        	->alias("a")
        	->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
        	->field("a.id")->where($where)
        	->select();
        	if (!empty($sysUser)) {
	        	foreach ($sysUser as $k => $v) {
	        		array_push($user_ids,$v['id']);
	        	}
	        	$_filter['a.id'] = array('in',$user_ids);
        	}else{
        		$_filter['a.id'] = 0;
        	}
        }else{
	    	$condition = [];
			$condition['a.branch_id'] = $this->user_branch;
			$condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
            $where = [];
            $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
            $where['a.is_follow']  = 1;
            $where['_logic'] = 'or';
            $condition['_complex'] = $where;

			$condition['b.type'] = 2;
			$sysUser = M('SysUser')->alias('a')
	        ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
			->where($condition)->field('a.id,a.name')->select();

			$user_ids = [];
			if (!empty($sysUser)) {
				foreach ($sysUser as $k => $v) {
					array_push($user_ids,$v['id']);
				}
			}
	    	$condition = [];
			if (!empty($user_ids)) {
				$_filter['a.id']  = array('not in',$user_ids);
			}
        }
        $_filter['a.is_leader'] = 0;
        $list = M('SysUser')
        	->alias("a")
        	->field("a.*")->where($_filter)
        	->order('id desc')
        	->select();
        if (!empty($list)) {
	        foreach($list as $k => $v) {
	            if (empty($list[$k]['staff_name'])) {
		            if (empty($list[$k]['comments'])) {
		            	$list[$k]['staff_name'] = $list[$k]['name'];
					}else{
						$list[$k]['staff_name'] = $list[$k]['comments'];
					}
	            }
	            if (empty($list[$k]['telephone'])) {
	            	$list[$k]['telephone'] = $list[$k]['mobile'];
	            }
	        }
        }

        $this->ajaxReturn($list);
    }

   public function staffListAction($dept_id = null) {
        $_filter = array();
        if($dept_id == -1){
            //已禁用
            $_filter['a.is_valid'] = 0;
            $_filter['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
            $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
            $where['a.is_follow']  = 1;
            $where['_logic'] = 'or';
            $_filter['_complex'] = $where;
            $_filter['a.branch_id'] = $this->user_branch;
            //$_filter['a.id'] = array("neq",$this->userId);
        }elseif($dept_id == -2){
            //总公司
            $_filter['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
            $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
            $where['a.is_follow']  = 1;
            $where['_logic'] = 'or';
            $_filter['_complex'] = $where;
            $_filter['a.branch_id'] = $this->user_branch;
            $_filter['a.is_valid'] = 1;
            $condition = $_filter;
            $condition['b.type'] = 2;
            $user_branch = M("SysUser a")->join("sys_user_branch b on a.id = b.user_id")->where($condition)->select();
            $user_ids = [];
            foreach($user_branch as $user){
                array_push($user_ids,$user['id']);
            }
            if (!empty($user_ids)) {
                $_filter['a.id']  = array('not in',$user_ids);
            }
            $_filter['a.is_valid']  = 1;
        } else{
            $_filter['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
            $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
            $where['a.is_follow']  = 1;
            $where['_logic'] = 'or';
            $_filter['_complex'] = $where;
            $_filter['a.branch_id'] = $this->user_branch;
            $branch_id = $dept_id;
            if($branch_id == $this->user_branch){
                $condition = [];
                $condition['a.branch_id'] = $this->user_branch;
                $condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
                $where = [];
                $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
                $where['a.is_follow']  = 1;
                $where['_logic'] = 'or';
                $condition['_complex'] = $where;

                $condition['b.type'] = 2;
                $sysUser = M('SysUser')->alias('a')
                    ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
                    ->where($condition)->field('a.id,a.name')->select();

                $user_ids = [];
                if (!empty($sysUser)) {
                    foreach ($sysUser as $k => $v) {
                        array_push($user_ids,$v['id']);
                    }
                }
                array_push($user_ids,$this->userId);
                $condition = [];
                if (!empty($user_ids)) {
                    //$_filter['a.id']  = array('not in',$user_ids);
                }
            }elseif(!empty($branch_id)){
                $user_ids = [];
                $where = $_filter;
                $where['b.branch_id'] = $branch_id;
                //$where['b.user_id'] = array("neq",$this->userId);
                $sysUser = M('SysUser')
                    ->alias("a")
                    ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
                    ->field("a.id")->where($where)
                    ->select();
                if (!empty($sysUser)) {
                    foreach ($sysUser as $k => $v) {
                        array_push($user_ids,$v['id']);
                    }
                    $_filter['a.id'] = array('in',$user_ids);
                }else{
                    $_filter['a.id'] = 0;
                }
            }

            //$_filter['a.role_ids'] = array('neq',ROLE_ID_COMPANY_MANAGER);
            //$_filter['a.is_valid'] = 1;
        }
       $list = M('SysUser')
           ->alias("a")
           ->field("a.*")->where($_filter)
           ->order('id desc')
           ->select();
       if (!empty($list)) {
           foreach($list as $k => $v) {
               if (empty($list[$k]['staff_name'])) {
                   if (empty($list[$k]['comments'])) {
                       $list[$k]['staff_name'] = $list[$k]['name'];
                   }else{
                       $list[$k]['staff_name'] = $list[$k]['comments'];
                   }
               }
               if (empty($list[$k]['telephone'])) {
                   $list[$k]['telephone'] = $list[$k]['mobile'];
               }
               $this->getDeptment($list[$k]);
               if(($v['role_ids']) != ""){
                   $roles = explode(",",$list[$k]['role_ids']);
                   $role_name = "";
                   foreach ($roles as $val){
                       $name = M("SysRole")->where("id = $val and branch_id = ".getBrowseBranchId())->getField("name");
                       $role_name = $role_name == "" ? $name : $role_name.";".$name;
                   }
                   if(mb_strlen($role_name,"utf-8") > 10){
                       $list[$k]['role_name'] = mb_substr($role_name,0,10)."...";
                   }else{
                       $list[$k]['role_name'] = $role_name;
                   }
               }
           }
       }
        $this->ajaxReturn($list);
    }

    protected function getDeptment(&$data) {
        $condition = [];
        $condition["a.user_id"] = $data['id'];
        $condition["b.type"] = 2;
        $deptment = M("SysUserBranch")
            ->alias("a")
            ->join('LEFT JOIN sys_branch b ON b.id = a.branch_id')
            ->where($condition)->find();
        if (empty($deptment)) {
        	$sysBranch = M("SysBranch")
            ->where(['id'=>$this->user_branch])->find();
	        $data["deptment_id"] = $this->user_branch;
	        //$data["deptment_name"] = $sysBranch['name'];
	        $data["deptment_name"] = "无部门";
        }else{
            $data["deptment_id"] = $deptment['id'];
	        $data["deptment_name"] = $deptment['name'];
        }
    }

    protected function getParentDeptById($id) {
        $condition = [];
        $condition["id"] = $id;

        $deptment = M("sys_branch")
                ->where($condition)->field('id,name,parent_id')->find();
        return $deptment;

        
    }

    public function indexAction() {
        $condition = [];
        $condition['branch_id'] = $this->user_branch;
        $roles = M('SysRole')->where($condition)->field('id,name as text')->select();
        $this->handlerOrganizationJurisdiction();
        $this->roles = json_encode($roles);
        $this->title = "员工管理";
        $this->display('index');
    }		

    public function staffRoleAction($role_id = null) {
        $_filter['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
        $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
        $where['a.is_follow']  = 1;
        $where['_logic'] = 'or';
        $_filter['_complex'] = $where;
        $_filter['a.branch_id'] = $this->user_branch;

        // $role_id = I("role_id");
        if (!empty($role_id)) {
            $user_ids = [];
            $condition = [];
            $condition['b.branch_id'] = $this->user_branch;
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
            }else {
                $_filter['a.id'] = 0;
            }
        }
        $list = D('SysUser')->alias("a")->field("a.id,a.staff_name,name,comments,head_pic")->where($_filter)->order("id desc")->select();
        if (!empty($list)) {
	        foreach($list as $k => $v) {
	            if (empty($list[$k]['staff_name'])) {
		            if (empty($list[$k]['comments'])) {
		            	$list[$k]['staff_name'] = $list[$k]['name'];
					}else{
						$list[$k]['staff_name'] = $list[$k]['comments'];
					}
	            }
				$this->getDeptment($list[$k]);
	        }
        }
        $this->ajaxReturn($list);
    }

    public function addRoleStaffAction($role_id) {
        if (IS_POST) {
            $user_ids = I('post.user_ids');
            $condition = [];
            $sys_user_role_data = [];
            foreach ($user_ids as $k => $v) {

                $condition = [];
                $condition['id'] = $v;
                $data['role_ids'] = $role_id;
                $sysUser = D("SysUser")->where($condition)->save($data);
                D("SysUserRole")
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
            $condition['a.branch_id'] = $this->user_branch;
            $condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
            $condition['a.is_leader'] = 0;
            $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
            $where['a.is_follow']  = 1;
            $where['_logic'] = 'or';
            $condition['_complex'] = $where;
            $condition['b.role_id'] = $role_id;

            $sysUser['in'] = M('SysUser')->alias('a')
            ->join('LEFT JOIN sys_user_role b ON b.user_id = a.id')
            ->where($condition)->field('a.id,a.name')->order('id desc')->group('a.id')->select();

            $user_ids = [];
            if (!empty($sysUser['in'])) {
                foreach ($sysUser['in'] as $k => $v) {
                    array_push($user_ids,$v['id']);
                }
            }
            $condition = [];
            $condition['branch_id'] = $this->user_branch;
            $condition['user_type'] = USER_TYPE_COMPANY_MANAGER;
            $condition['is_leader'] = 0;
            $where = [];
            $where['mobile']  = [['exp','is not null'],['exp','<> ""']];
            $where['is_follow']  = 1;
            $where['_logic'] = 'or';
            $condition['_complex'] = $where;
            // var_dump($condition);
            if (!empty($user_ids)) {
                $condition['id'] = array('not in',$user_ids);
            }
            $sysUser['out'] = M('SysUser')
            ->where($condition)->field('id,name as text,staff_name,comments')->select();

            if (!empty($sysUser['out'])) {
                foreach($sysUser['out'] as $k => $v) {
                    if ($sysUser['out'][$k]['staff_name'] != "") {
                        $sysUser['out'][$k]['text'] = $sysUser['out'][$k]['staff_name'];
                    }else{
                        if($sysUser['out'][$k]['comments'] != ""){
                            $sysUser['out'][$k]['text'] = $sysUser['out'][$k]['comments'];
                        }
                    }
                }
            }
            /*var_dump($sysUser['out']);*/
            $this->ajaxReturn($sysUser['out']);
            // $this->assign("model", $sysUser);
            // $this->role_id = $role_id;
            // $this->display('addStaff');
        }
    }

    public function deleteRoleStaffAction() {
        if (IS_POST) {
            $condition = [];
            $user_ids = I('post.user_ids');
            $condition['user_id'] = array('in',$user_ids);
            M('SysUserRole')->where($condition)->delete();
            // var_dump($user_ids);
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

    public function treeListAction($type = null)
    {
    	$condition = [];
    	$condition['id'] = $this->user_branch;
    	$sysBranch = M('SysBranch')->where($condition)->field('id,name,type')->find();
    	$this->getChildren($sysBranch);

    	$condition = [];
		$condition['a.branch_id'] = $this->user_branch;
		$condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
        $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
        $where['a.is_follow']  = 1;
        $where['_logic'] = 'or';
        $condition['_complex'] = $where;
		$condition['b.type'] = 2;
		$sysUser = M('SysUser')->alias('a')
        ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
		->where($condition)->field('a.id,a.name')->select();

		$user_ids = [];
		if (!empty($sysUser)) {
			foreach ($sysUser as $k => $v) {
				array_push($user_ids,$v['id']);
			}
		}
    	$condition = [];
		if (!empty($user_ids)) {
			$condition['id'] = array('not in',$user_ids);
		}
    	$condition['user_type'] = USER_TYPE_COMPANY_MANAGER;
        $where['mobile']  = [['exp','is not null'],['exp','<> ""']];
        $where['is_follow']  = 1;
        $where['_logic'] = 'or';
        $condition['_complex'] = $where;
    	$condition['branch_id'] = $this->user_branch;
        $condition['is_leader'] = 0;
        $condition['is_valid'] = 1;
    	$sysBranch['user_count'] = M('SysUser')->alias('a')
			->where($condition)->count();

		$sysBranch['text'] = $sysBranch['name']." (".$sysBranch['user_count'].")";
		$sysBranch['opened'] = true;
		/*if ($type == 1) {
			$rst[] = [
				'id'=>'',
				'text'=>'全选'
			];
		}*/
    	$rst[] = $sysBranch;

    	$this->type = $type;
    	$this->sysBranch = json_encode($rst);
    	$this->assign("model",json_encode($sysBranch));
    	$this->display('treeList1');
    }
    protected function getChildren(&$data)
    {
    	$condition = [];
    	$condition['parent_id'] = $data['id'];
    	$condition['type'] = 2;
    	$condition['branch_id'] = $this->user_branch;
    	$list = M('SysBranch')->where($condition)->field('id,name as text,type,parent_id')->order('sort asc')->select();
    	$num = 0;
    	if (!empty($list)) {
	    	foreach ($list as $k => $v) {
	    		$this->getChildren($list[$k]);
	    	}
	    	$data['children'] = $list;
    	}else{
    		$data['children'] = [];
    	}
    	$condition = [];
    	$condition['b.branch_id'] = $data['id'];
    	$condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
        // $condition['a.is_follow'] = 1;
        $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
        $where['a.is_follow']  = 1;
        $where['_logic'] = 'or';
        $condition['_complex'] = $where;
        //$condition['role_ids'] = array('neq',ROLE_ID_COMPANY_MANAGER);
    	$condition['a.branch_id'] = $this->user_branch;
    	$condition['a.is_valid'] = 1;
    	//$condition['a.id'] = array("neq",$this->userId);
    	$data['user_count'] = M('SysUser')->alias('a')
            ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
			->where($condition)->count();
        if(mb_strlen($data['text'],"utf-8") > 5){
            $data['name'] = mb_substr($data['text'],0,5)."... (".$data['user_count'].")";
        }else{
            $data['name'] = $data['text']." (".$data['user_count'].")";
        }
		//$data['name'] = $data['text']." (".$data['user_count'].")";
    }

    public function DeptListAction() {
    	$condition = [];
    	if (I("branch_id")) {
    		$condition['id'] = I("branch_id");
    	}else{
    		$condition['id'] = $this->user_branch;
    	}
    	$sysBranch[0] = M('SysBranch')->where($condition)->field('id,name as text,parent_id')->find();
    	$this->getChildren($sysBranch[0]);

    	if ($sysBranch[0]['id'] != $this->user_branch) {
	    	$condition = [];
	    	$condition['b.branch_id'] = $sysBranch[0]['id'];
	    	$condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
	    	//$condition['a.id'] = array("neq",$this->userId);
	        $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
	        $where['a.is_follow']  = 1;
	        $where['_logic'] = 'or';
	        $condition['_complex'] = $where;
	    	$condition['a.branch_id'] = $this->user_branch;
	    	$condition['a.is_valid'] = 1;
            //$condition['a.role_ids'] = array('neq',ROLE_ID_COMPANY_MANAGER);
	    	$sysBranch[0]['user_count'] = M('SysUser')->alias('a')
	            ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
				->where($condition)->count();
    	}else{
	    	$condition = [];
			$condition['a.branch_id'] = $this->user_branch;
			$condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;

            $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
            $where['a.is_follow']  = 1;
            $where['_logic'] = 'or';
            $condition['_complex'] = $where;
            $condition['b.type'] = 2;
			$sysUser = M('SysUser')->alias('a')
	        ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
			->where($condition)->field('a.id,a.name')->select();
			$user_ids = [];
			if (!empty($sysUser)) {
				foreach ($sysUser as $k => $v) {
					array_push($user_ids,$v['id']);
				}
			}
            array_push($user_ids,$this->userId);
	    	$condition = [];
			if (!empty($user_ids)) {
				//$condition['id'] = array('not in',$user_ids);
			}
	    	$condition['user_type'] = USER_TYPE_COMPANY_MANAGER;
	        $where['mobile']  = [['exp','is not null'],['exp','<> ""']];
	        $where['is_follow']  = 1;
	        $where['_logic'] = 'or';
	        $condition['_complex'] = $where;
	    	$condition['branch_id'] = $this->user_branch;
            //$condition['role_ids'] = array('neq',ROLE_ID_COMPANY_MANAGER);
            //$condition['is_valid'] = 1;
	    	$sysBranch[0]['user_count'] = M('SysUser')->alias('a')->where($condition)->count();
    	}
        $sysBranch[0]['parent_deptments'] = [];
        if ($sysBranch[0]['id'] != $this->user_branch) {
            $parent = $this->getParentDeptById($sysBranch[0]['parent_id']);
            $sysBranch[0]['parent_deptments'][] = $parent;
        }
        if ($parent != null &&  $this->user_branch != $parent['id']) {
            $parent = $this->getParentDeptById($parent['parent_id']);
            $sysBranch[0]['parent_deptments'][] = $parent;
        }   
        if ($parent != null &&  $this->user_branch != $parent['id']) {
            $parent = $this->getParentDeptById($parent['id']);
            $sysBranch[0]['parent_deptments'][] = $parent;
        }


        $type = I("get.type");
        $con['is_valid'] = 0;
        $con['branch_id'] = $this->user_branch;
        $con['user_type'] = USER_TYPE_COMPANY_MANAGER;
        $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
        $where['a.is_follow']  = 1;
        $where['_logic'] = 'or';
        $con['_complex'] = $where;
        //$con['id'] = array("neq",$this->userId);
        $in_valid_count =  M('SysUser')->alias('a')->where($con)->count();
        if($type != 2){
            $con['is_valid'] = 0;
            $con['branch_id'] = $this->user_branch;
            $con['user_type'] = USER_TYPE_COMPANY_MANAGER;
            $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
            $where['a.is_follow']  = 1;
            $where['_logic'] = 'or';
            $con['_complex'] = $where;
            //$con['id'] = array("neq",$this->userId);
            $sysBranch[1]['count'] =  $in_valid_count;
            $sysBranch[1]['name'] = "其他用户";
            $sysBranch[1]['show'] = "0";
            $sysBranch[1]['type'] = "1";
            $sysBranch[1]['id'] = "0";
            $sysBranch[1]['children'] = array();
            $sysBranch[1]['children'][0] = array("id"=>"","text"=>"已禁用账户","name"=>"已禁用账户"."(".$sysBranch[1]['count'].")","show"=>0);
            $sysBranch[1]['children'][0]["children"] = array();
            array_push($sysBranch[0]["children"],$sysBranch[1]);
        }
        $tmp = [];
        $tmp['id'] = "-2";
        $tmp['text'] = "无部门";
        $condition = [];
        $where['mobile']  = [['exp','is not null'],['exp','<> ""']];
        $where['is_follow']  = 1;
        $where['_logic'] = 'or';
        $condition['_complex'] = $where;
        $condition['a.is_valid'] = 1;
        $condition['a.branch_id'] = getBrowseBranchId();
        $condition['b.type'] = 2;
        $condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
        $count = M('SysUser')->alias('a')
            ->join('sys_user_branch b ON b.user_id = a.id')
            ->where($condition)->count();
        $tmp['user_count'] = $sysBranch[0]['user_count'] - $count-$in_valid_count;
        $tmp['name'] = "无部门(".$tmp['user_count'].")";
        $tmp['children'] = [];
        array_unshift($sysBranch[0]['children'],$tmp);
        $sysBranch['aaa'] = $count;
    	$this->ajaxReturn($sysBranch);
    }


    public function addDeptAction($dept_id = null) {
    	if (IS_POST) {
	    	$condition = [];
	    	$condition["type"] = 2;
	   		$condition["branch_id"] = $this->user_branch;
	   		$condition["name"] = I("post.name");
            if(mb_strlen($condition["name"],'utf-8') > 10){
                $this->ajaxReturn(buildMessage('最多输入10个字！',1));
            }
			$sysBranch = M("SysBranch")->where($condition)->find();
	   		if (!empty($sysBranch)) {
	   			$this->ajaxReturn(buildMessage('已存在同名的部门',1));
	   		}elseif (empty($condition["name"])) {
	   			$this->ajaxReturn(buildMessage('部门名称不能为空',1));
	   		}else{
	   		    $branch_name = M("SysBranch")->where("id = ".$condition["branch_id"])->getField("name");
	   		    if($condition["name"] == $branch_name){
                    $this->ajaxReturn(buildMessage('名称与公司名称重复',1));
                }
		   		$data = $condition;
                $data["is_valid"] = 1;
		   		$data["parent_id"] = $dept_id;
		   		$tmp = M("SysBranch")->where("type = 2 and parent_id = $dept_id and branch_id = ".$condition["branch_id"])->order("sort desc")->find();
		   		$data["sort"] = $tmp['sort']+1;
		  		$model = M("SysBranch");
	            try {
	                $model->startTrans();
	                $last_id = $model->add($data, array("callback"=>true));
	                $model->commit();
	            } catch (Think\Exception $ex) {
	                $model->rollback();
	                $last_id = false;
	            }

	            if($last_id){
	            	$this->ajaxReturn(array('code'=>0,'message'=>'添加子部门成功','id'=>$last_id,"name"=>$data['name']));
	            }else{
	            	$this->ajaxReturn(array('code'=>1,'message'=>'添加子部门失败'));
	            }
	   		}
		}
    }

    public function editDeptAction($id) {
        $condition = [];
        // $condition["branch_id"] = $this->user_branch;
        $condition["id"] = $id;
        $model = M("SysBranch");
        if (IS_POST) {
            $name = I('post.name');
            $data['name'] = $name;
            $condition = [];
            $condition["type"] = 2;
            $condition["id"] = array('neq',$id);
            $condition["branch_id"] = $this->user_branch;
            $condition["name"] = $name;
            if(mb_strlen($condition["name"],'utf-8') > 10){
                $this->ajaxReturn(buildMessage('最多输入10个字！',1));
            }
            $sysBranch = M("SysBranch")->where($condition)->find();
            if (!empty($sysBranch)) {
                $this->ajaxReturn(buildMessage('已存在同名的部门',1));
            }elseif (empty($condition["name"])) {
                $this->ajaxReturn(buildMessage('部门名称不能为空',1));
            }else{
                try {
                    $model->startTrans();
                    $condition = [];
                    $condition["id"] = $id;
                    $updated = $model->where($condition)->save($data);
                    $model->commit();
                } catch (\Think\Exception $ex) {
                    $model->rollback();
                    $this->ajaxReturn(array('code'=>1,'message'=>"保存失败：" . $ex->getMessage()));
                }
                if ($updated !== false) {
                    $this->ajaxReturn(array('code'=>0,'message'=>'编辑部门成功'));
                }
            }
        } else {
            $sysBranch = M("SysBranch")->where($condition)->field('id,name')->find();
            // $this->dept_action = "editDept";
            $this->ajaxReturn($sysBranch);
            // $this->assign("model", $sysBranch);
            // $this->display('addDept');
        }
    }

    public function deleteDeptAction($id) {
    	if (IS_POST) {
	    	$condition = [];
	    	$condition['id'] = $id;
	    	$condition['type'] = 2;
	    	$sysBranch = M('SysBranch')->where($condition)->field('id,name as text,parent_id')->find();
			$this->getChildren($sysBranch);

	   		/*if (!empty($sysBranch) && $sysBranch['user_count'] > 0) {
	   			$this->ajaxReturn(array('code' => 1, 'message' => '部门内还存在员工，请先删除员工'));
	   		}elseif(!empty($sysBranch['children'])){
                $this->isHasStaff($sysBranch['children']);
                $this->deleteChildDept($sysBranch['children']);
                M("SysBranch")->where("id = ".$sysBranch['id'])->delete();
                $this->ajaxReturn(array('code' => 0, 'message' =>  '删除部门成功'));
            }else {
				M("SysBranch")->where($condition)->delete();
                $this->ajaxReturn(array('code' => 0, 'message' => '删除部门成功'));
	   		}*/

            $model = M("SysBranch");
            try{
                $model->startTrans();
                $this->handlerDeleteDept($sysBranch);
                $model->commit();
                $this->ajaxReturn(array("code"=>0,"message"=>"删除成功！"));
            }catch(\Think\Exception $ex){
                $model->rollback();
                $this->ajaxReturn(array("code"=>1,"message"=>"删除失败！"));
            }

    	}
    }

    /*public function isHasStaff($children){
        foreach($children as $child){
            if($child['user_count'] != 0){
                $this->ajaxReturn(array('code' => 1, 'message' => '部门内还存在员工(含已禁用)，请先移除员工'));
            }
            if(!empty($child['children'])){
                $this->isHasStaff($child['children']);
            }
        }
    }

    public function deleteChildDept($children){
        foreach($children as $child){
            M("SysBranch")->where("id = ".$child['id'])->delete();
            if(!empty($child['children'])){
                $this->deleteChildDept($child['children']);
            }
        }
    }*/

    //删除部门以及子部门，员工移到上级部门
    public function handlerDeleteDept($sysBranch){
        foreach ($sysBranch["children"] as $k=>$v) {
            if(!empty($v['children'])){
                $this->handlerDeleteDept($v);
            }
            $user_count = M("SysUserBranch")->where("branch_id = ".$v['id']." and type = 2")->count();
            if($user_count){
                /*//移到上级部门
                if($v['parent_id'] == $this->user_branch){
                    M("SysUserBranch")->where("branch_id = ".$v['id']." and type = 2")->delete();
                }else{
                    M("SysUserBranch")->where("branch_id = ".$v['id']." and type = 2")->setField("branch_id",$v['parent_id']);
                }*/
                //移到总公司
                M("SysUserBranch")->where("branch_id = ".$v['id']." and type = 2")->delete();
            }
            M("SysBranch")->where("id = ".$v['id']." and type = 2")->delete();
        }
        $user_count = M("SysUserBranch")->where("branch_id = ".$sysBranch['id']." and type = 2")->count();
        if($user_count){
            /*if($sysBranch['parent_id'] == $this->user_branch){
                M("SysUserBranch")->where("branch_id = ".$sysBranch['id']." and type = 2")->delete();
            }else{
                M("SysUserBranch")->where("branch_id = ".$sysBranch['id']." and type = 2")->setField("branch_id",$sysBranch['parent_id']);
            }*/
            M("SysUserBranch")->where("branch_id = ".$sysBranch['id']." and type = 2")->delete();
        }
        M("SysBranch")->where("id = ".$sysBranch['id']." and type = 2")->delete();
    }


    //置顶部门
    public function stickDeptAction(){
        $id = I("post.id");
        $condition['type'] = 2;
        $condition['branch_id'] = $this->user_branch;
        $model = M("SysBranch");
        try{
            $model->startTrans();
            $tmp = M("SysBranch")->where("id = $id")->field("parent_id,sort")->find();
            $condition['parent_id'] = $tmp['parent_id'];
            $condition['id'] = array("neq",$id);
            //当前部门置顶
            M("SysBranch")->where("id = $id")->setField("sort",1);
            //其他部门
            $siblings = M("SysBranch")->where($condition)->select();
            foreach ($siblings as $k=>$val) {
                if($val['sort'] < $tmp['sort']){
                    $sort = $val['sort'] + 1;
                    M("SysBranch")->where("id = ".$val['id'])->setField("sort",$sort);
                }elseif($val['sort'] == null){
                    unset($condition['id']);
                    $sort = M("SysBranch")->where($condition)->order("sort desc")->limit(1)->getField("sort") + 1;
                    M("SysBranch")->where("id = ".$val['id'])->setField("sort",$sort);
                }
            }
            $model->commit();
            $this->ajaxReturn(array("error"=>0,"message"=>"置顶成功！"));
        } catch (\Think\Exception $ex) {
            $model->rollback();
            $this->ajaxReturn(array("error"=>1,"message"=>"置顶失败！"));
        }
    }

    public function departmentAction(){
        $id = I("get.id");
        if($id){
            $name = M("SysBranch")->where("id = $id")->getField("name");
            $this->id = $id;
            $this->title = $name??"无部门";
        }else{
            $this->id = -1;
            $this->title = "已禁用账户";
        }
        $this->display();
    }

    public function editStaffAction($id) {
    	if (IS_POST) {
	    	$condition = [];
	    	$condition['id'] = $id;
	    	$condition['branch_id'] = $this->user_branch;
	    	$post_data = I('post.');
	    	$data = $post_data;
	    	if($data['is_add'] == 1){
	    	    $data['dac_type'] = 1;
                if (empty($data['staff_name'])) {
                    $this->ajaxReturn(array('code' => 1, 'message' => '员工姓名不能为空'));
                }elseif(empty($data['mobile'])){
                    $this->ajaxReturn(array('code' => 1, 'message' => '手机号不能为空'));
                }
                $result = M("SysUser")->where("branch_id = ".$condition['branch_id']." and mobile = ".$data['mobile']." and id != $id")->find();
                if($result){
                    $this->ajaxReturn(array('code' => 1, 'message' => '手机号已存在'));
                }
	    	}
	    	if(empty($id)){
	    		$this->ajaxReturn(array('code' => 1, 'message' => '请先查询一位员工'));
	    	}elseif(empty($data['role_ids'])){
                $this->ajaxReturn(array('code' => 1, 'message' => '角色不能为空'));
            }
	    	elseif(empty($data['deptment_id'])){
	    		$this->ajaxReturn(array('code' => 1, 'message' => '部门不能为空'));
	    	}else{
	    		$data['user_type'] = USER_TYPE_COMPANY_MANAGER;
		    	$sysUser = M('SysUser')->where($condition)->save($data);
		    	M("SysUserRole")->where("user_id = ".$data['id'])->delete();
		    	$role_ids = explode(",",$data["role_ids"]);
		    	$tmp = [];
		    	foreach($role_ids as $k=>$v){
                    $tmp[] = [
                        "role_id"=>$v,
                        "user_id"=>$data['id']
                    ];
                }
                if($tmp){
                    M("SysUserRole")->addAll($tmp);
                }
		    	//保存自定义属性
	            M("SysCustomerInformation")->where(array("user_id" => $id))->delete();
		        $data_title = I('custom_title');
		        $data_value = I('custom_value');
		        foreach ($data_title as $k => $v) {
		            $custom_datas[] = array(
		            	"user_id" => $id,
		            	"title" => $v,
		            	"value" => $data_value[$k],
		            	"branch_id" =>$this->user_branch,
		                "type"=> 0,
		                "created_at"=> time(),
		                "updated_at"=> time());
		        }
		        D("SysCustomerInformation")->addAll($custom_datas, null, true);

		        if ($data['deptment_id'] != null){
		            $condition = [];
		            $condition['user_id'] = $id;
		            $condition['type'] = 2;
		            M('SysUserBranch')->where($condition)->delete();
		            if ($data['deptment_id'] != $this->user_branch) {
		                $sys_user_branch_data = [];
		                $sys_user_branch_data['user_id'] = $data['id'];
		                $sys_user_branch_data['type'] = 2;
		                $sys_user_branch_data['branch_id'] = $data['deptment_id'];
		                M('SysUserBranch')->add($sys_user_branch_data);
		            }
		        }
                if (!empty($data['is_add'])) {
                    /*//新增发送邀请通知
                    $users = D('SysUser')->where("id = ".$data['id'])->field('id,name,openid')->select();
                    A("ComFan")->notifyUserAction();*/
                    $this->ajaxReturn(array('code' => 0, 'message' => '新增员工成功'));
                } else {
		    	     $this->ajaxReturn(array('code' => 0, 'message' => '编辑员工成功'));
                }
                
	    	}
    	}else{
	    	$condition = [];
	    	$condition['id'] = $id;
	    	$condition['branch_id'] = $this->user_branch;
	    	$sysUser = M('SysUser')->where($condition)
	    	->field('id,name,mobile,staff_name,comments,telephone,email,qq,head_pic,account,director_id,role_ids,is_leader')
	    	->find();
            if($sysUser['director_id'] != "" && $sysUser['director_id'] != 0){
                $director = M("SysUser")->where("id = ".$sysUser['director_id'])->field("name,staff_name")->find();
                $sysUser['director_name'] = $director['staff_name'] == "" ?  $director['name'] : $director['staff_name'];
            }
	    	//部门
	    	$condition = [];
            $condition['b.user_id'] = $sysUser['id'];
            $condition['b.type'] = 2;
            $sysBranch = M('SysBranch')->alias('a')
            ->join('LEFT JOIN sys_user_branch b ON b.branch_id = a.id')
            ->where($condition)->field('id,name')
            ->find();
            if (!empty($sysBranch)) {
	            $sysUser["deptment_id"] = $sysBranch['id'];
            	$sysUser["deptment_name"] = $sysBranch['name'];
            }else{
	            $sysBranch = M('SysBranch')->where(['id'=>$this->user_branch])->field('id,name')->find();
	            $sysUser["deptment_id"] = $this->user_branch;
            	//$sysUser["deptment_name"] = $sysBranch['name'];
            	$sysUser["deptment_name"] = "无部门";
            }
            //自定义属性
	        $condition = [];
        	$condition["user_id"] = $sysUser['id'];
        	$list = M("SysCustomerInformation")->field("id,title,type,value")->where($condition)->order('id asc')->select();
        	$sysUser["custom"] = $list;
        	if($sysUser['role_ids']){
                $role_ids = explode(",",$sysUser['role_ids']);
                $name = [];
                foreach ($role_ids as $k=>$v){
                    $name[$k] = M("SysRole")->where("id = $v")->getField("name");
                }
                $sysUser["role_name"] = implode(";",$name);
            }
            if($sysUser['telephone'] == ""){
                $sysUser['telephone'] = $sysUser['mobile'];
            }
            if($sysUser['staff_name'] == ""){
                $sysUser['staff_name'] = $sysUser['name'];
            }
            $this->ajaxReturn($sysUser);
    	}
    }
    public function searchStaffAction() {
        $str = I('q');
        $condition = [];
        if (!empty($str)) {
            $where['name']  = array('like', '%'.$str.'%');
            $where['querykey']  = array('like', '%'.$str.'%');
            $where['mobile']  = array('like', '%'.$str.'%');
            $where['_logic'] = 'or';
            $condition['_complex'] = $where;
        }
        $condition['branch_id'] = $this->user_branch;
        $condition['user_type'] = array('neq',USER_TYPE_COMPANY_MANAGER);
        $condition['_string'] = '(is_follow = 1 or (mobile is not null and mobile <> "" ) )';
    	$sysUser = M('SysUser')->where($condition)
    	->field('id,name,mobile,staff_name,comments,telephone,email,qq,head_pic,querykey,account')
    	->select();
    	foreach ($sysUser as $k => $v) {
    		//部门
	    	$condition = [];
            $condition['b.user_id'] = $sysUser[$k]['id'];
            $condition['b.type'] = 2;
            $sysBranch = M('SysBranch')->alias('a')->join('LEFT JOIN sys_user_branch b ON b.branch_id = a.id')
            ->where($condition)->field('id,name')->find();
            if (!empty($sysBranch)) {
	            $sysUser[$k]["deptment_id"] = $sysBranch['id'];
            	$sysUser[$k]["deptment_name"] = $sysBranch['name'];
            }else{
	            $sysBranch = M('SysBranch')->where(['id'=>$this->user_branch])->field('id,name')->find();
	            $sysUser[$k]["deptment_id"] = $this->user_branch;
            	//$sysUser[$k]["deptment_name"] = $sysBranch['name'];
            	$sysUser[$k]["deptment_name"] = "无部门";
            }
            //自定义属性
	        $condition = [];
	    	$condition["user_id"] = $sysUser[$k]['id'];
	    	$list = M("SysCustomerInformation")->field("id,title,type,value")->where($condition)->order('id asc')->select();
	    	$sysUser[$k]["custom"] = $list;
    	}
        $this->ajaxReturn($sysUser);
    }
    //删除员工
    public function deleteStaffAction() {
    	if (IS_POST) {
	    	$condition = [];
	    	$user_ids = I('post.user_ids');
	    	$condition['user_id'] = array('in',$user_ids);
	    	$condition['type'] = 2;

	    	M('SysUserBranch')->where($condition)->delete();

	    	$condition = [];
	    	$condition['id'] = array('in',$user_ids);
	    	$condition['branch_id'] = $this->user_branch;
	    	$data['user_type'] = USER_TYPE_PROSPECTIVE;

			M("SysUser")->where($condition)->save($data);
			$this->ajaxReturn(array('code'=>0,'message'=>'删除员工成功'));
    	}
    }

    //添加人员、调整部门
    public function adjustStaffAction($dept_id = null,$search = null) {
    	if (IS_POST) {
			$user_ids = I('post.user_ids');
            $condition = [];
            $condition['user_id'] = array('in',$user_ids);
            $condition['type'] = 2;
            M('SysUserBranch')
            	->where($condition)->delete();
            $sys_user_branch_data = [];
            if (!empty($user_ids) && $dept_id != $this->user_branch) {
	            foreach ($user_ids as $k => $v) {
		            $sys_user_branch_data[$k]['user_id'] = $v;
		            $sys_user_branch_data[$k]['type'] = 2;
		            $sys_user_branch_data[$k]['branch_id'] = $dept_id;
	            }
            	$result = M('SysUserBranch')->addAll($sys_user_branch_data);
            }
            $this->ajaxReturn(array('code' => 0, 'message' => '添加人员成功'));
    	} else {
    		if (!empty($search)) {
		    	$condition = [];
	    		$condition['a.branch_id'] = $this->user_branch;
	    		$condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
	    		$condition['_string'] = '(a.is_follow = 1 or (a.mobile is not null and a.mobile <> "") )';
                $where['a.staff_name']  = array('like','%'.$search.'%');
                $where['a.name']  = array('like','%'.$search.'%');
                $where['a.comments']  = array('like','%'.$search.'%');
                $where['c.name']  = $search;
                $where['_logic'] = 'or';
                $condition['_complex'] = $where;
				$condition['c.type'] = 2;

	    		$list = M('SysUser')->alias('a')
	            ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
	            ->join('LEFT JOIN sys_branch c ON c.id = b.branch_id')
	            ->field('b.user_id')
				->where($condition)->select();

				$user_ids = [];
				if (!empty($list)) {
					foreach ($list as $k => $v) {
						array_push($user_ids,$v['user_id']);
					}
				}
                if (!empty($user_ids)) {
				    $search_condition['id'] = array('in',$user_ids);
                }else{
                    $search_condition['id'] = array('in',[0]);
                }

    		}

    		if ($dept_id != $this->user_branch) {
	    		$sysUser = [];
		    	$condition = [];
	    		$condition['a.branch_id'] = $this->user_branch;
	    		$condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
                $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
                $where = [];
                $where['a.is_follow']  = 1;
                $where['_logic'] = 'or';
                $condition['_complex'] = $where;
	    		$condition['b.branch_id'] = $dept_id;
				$condition['b.type'] = 2;
                $condition['is_leader'] = 0;
	    		$sysUser['in'] = M('SysUser')->alias('a')
	            ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
	            ->field('id,name,telephone,mobile,head_pic')
				->where($condition)->select();

				$user_ids = [];
				if (!empty($sysUser['in'])) {
					foreach ($sysUser['in'] as $k => $v) {
						array_push($user_ids,$v['id']);
					}
				}

				$condition = [];
				$condition['branch_id'] = $this->user_branch;
				$condition['user_type'] = USER_TYPE_COMPANY_MANAGER;
                $where = [];
                $where['mobile']  = [['exp','is not null'],['exp','<> ""']];
                $where['is_follow']  = 1;
                $where['_logic'] = 'or';
                $condition['_complex'] = $where;
                $condition['is_leader'] = 0;

				if (!empty($user_ids)) {
					$condition['id'][] = array('not in',$user_ids);
				}

				if (!empty($search_condition)) {
					$condition['id'][] = $search_condition['id'];
				}

	    		$sysUser['out'] = M('SysUser')
				->where($condition)->field('id,name,telephone,mobile,staff_name,head_pic')->select();

				foreach ($sysUser['out'] as $k => $v) {
					$this->getDeptment($sysUser['out'][$k]);
				}
    		}else{
    			$dept_id = $this->user_branch;
	    		$sysUser = [];
		    	$condition = [];
	    		$condition['a.branch_id'] = $this->user_branch;
	    		$condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
                $where = [];
                $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
                $where['a.is_follow']  = 1;
                $where['_logic'] = 'or';
                $condition['_complex'] = $where;

				$condition['b.type'] = 2;


				if (!empty($search_condition)) {
					$condition['id'][] = $search_condition['id'];
				}

	    		$sysUser['out'] = M('SysUser')->alias('a')
	            ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
				->where($condition)->field('a.id,a.name,a.telephone,a.mobile,a.staff_name,a.head_pic')->select();

				foreach ($sysUser['out'] as $k => $v) {
					$this->getDeptment($sysUser['out'][$k]);
				}
    		}
    		if (!empty($sysUser['out'])) {
		        foreach($sysUser['out'] as $k => $v) {
		            if (empty($sysUser['out'][$k]['staff_name'])) {
			            if (empty($sysUser['out'][$k]['comments'])) {
			            	$sysUser['out'][$k]['staff_name'] = $sysUser['out'][$k]['name'];
						}else{
							$sysUser['out'][$k]['staff_name'] = $sysUser['out'][$k]['comments'];
						}
		            }

		        }
    		}
    		$this->ajaxReturn($sysUser);

    	}
    }

    public function adjustDeptAction() {
        if (IS_POST) {
            $user_ids = I('post.user_ids');
            $dept_id = I('post.dept_id');
            $condition = [];
            $condition['user_id'] = array('in',$user_ids);
            $condition['type'] = 2;
            M('SysUserBranch')
                ->where($condition)->delete();
            $sys_user_branch_data = [];
            if (!empty($user_ids) && $dept_id != $this->user_branch) {
                foreach ($user_ids as $k => $v) {
                    $sys_user_branch_data[$k]['user_id'] = $v;
                    $sys_user_branch_data[$k]['type'] = 2;
                    $sys_user_branch_data[$k]['branch_id'] = $dept_id;
                }
                $result = M('SysUserBranch')->addAll($sys_user_branch_data);
            }
            $this->ajaxReturn(array('code' => 0, 'message' => '操作成功'));
        }
    }
    protected function handlerOrganizationJurisdiction()
    {
        $permissionNames = [
            ['controller_name'=>'Organization','action_name'=>'detail'],
            ['controller_name'=>'StaffRole','action_name'=>'detail'],
            ['controller_name'=>'StaffList','action_name'=>'detail'],
        ];
        $permissions = [];
        $first_permissions = '';
        foreach($permissionNames as $value) {
            $permissions[$value['controller_name']][$value['action_name']] = $this->handlerPermission($value) ? 1 : 0;
            if ($this->handlerPermission($value) && $first_permissions === ''){
                $first_permissions = $value['controller_name'];
            }
        }
        $this->menu_permissions = $permissions;
        $this->first_permissions = $first_permissions;
    }
    protected function handlerPermission($_permission_name)
    {
        $menuList = $this->_permissionList[ACCESS_MENUS_KEY];
        if (($menuList[$_permission_name['controller_name']]['allow'] == 1 && $menuList[$_permission_name['controller_name']][ACCESS_MENU_ACTIONS_KEY][$_permission_name['action_name']]) || $this->isManager) {
            return true;
        } else {
            return false;
        }
    }
    protected function handlerPermissionsProcessing()
    {
        parent::handlerPermissionsProcessing();
        switch (ACTION_NAME) {
            case 'staffRole':
                $this->_permission_name = 'StaffRole';
                $this->_permission_action_name = 'list';
                break;
            case 'addRoleStaff':
                $this->_permission_name = 'StaffRole';
                $this->_permission_action_name = 'addStaff';
                break;
            case 'deleteRoleStaff':
            case 'deleteStaff' :
                $this->_permission_name = 'StaffList';
                $this->_permission_action_name = 'deleteStaff';
                break;
            case 'adjustDept':
                $this->_permission_name = 'StaffList';
                $this->_permission_action_name = 'adjustDept';
                break;
            case 'editStaff':
                $this->_permission_name = 'StaffList';
                $this->_permission_action_name = 'editStaff';
                break;
            case 'addStaff':
                $this->_permission_name = 'StaffList';
                $this->_permission_action_name = 'add';
                break;
            case 'adjustStaff':
                $this->_permission_name = 'Organization';
                $this->_permission_action_name = 'addStaff';
                break;
            case 'addDept':
                $this->_permission_name = 'Organization';
                $this->_permission_action_name = 'addDept';
                break;

        }
    }

    public function disableStaffAction(){
        $id = I("post.id");
        $type = I("post.type");
        if($id == $this->userId){
            $this->ajaxReturn(array("error"=>1,"message"=>"操作失败！"));
        }
        if($type == 1){
            $result = M("SysUser")->where("id = $id")->setField("is_valid",0);
        }else{
            $result = M("SysUser")->where("id = $id")->setField("is_valid",1);
        }
        if($result !== false){
            $this->ajaxReturn(array("error"=>0,"message"=>"操作成功！"));
        }else{
            $this->ajaxReturn(array("error"=>1,"message"=>"操作失败！"));
        }
    }

    public function addEmployeesAction(){
        $id = I("get.id");
        if($id){
            $this->assign("id",$id);
            $this->assign("curr_userId",$this->userId);
            $this->assign("title","编辑员工");
        }else{
            $this->assign("title","新增员工");
        }
        $this->display();
    }

    public function getStaffForDirectorAction(){
        $id = I("post.id");
        if($id){
            $condition['id'] = array("neq",$id);
        }
        $condition['is_valid'] = 1;
        $condition['user_type'] = USER_TYPE_COMPANY_MANAGER;
        $condition['branch_id'] = $this->user_branch;
        $result = M("SysUser")->where($condition)->field("id,name")->select();
        $this->ajaxReturn($result);
    }


    public function select_roleAction(){
        if(IS_POST){
            $name = I("post.name");
            $page = I("post.page");
            if($name){
                $condition['name'] = array("like","%".$name."%");
            }
            $condition['branch_id'] = $this->user_branch;
            $condition['is_valid'] = 1;
            $data = M("SysRole")->where($condition)->page($page)->select();
            $this->ajaxReturn(array("data"=>$data,"user_total"=>count($data)));
        }else{
            $this->display();
        }
    }

    public function select_deptAction(){
        $this->display();
    }

    public function select_staffAction(){
        $this->userListsAssign(1);
        $this->assign("type","selectStaff");
        $this->display("select_user_staff");
    }
    public function select_userAction(){
        $this->userListsAssign(2);
        $this->assign("type","selectUser");
        $this->display("select_user_staff");
    }


    public function userListsAssign($type){
        if($type == 1){//选择员工
            $condition_groups["a.user_type"] = USER_TYPE_COMPANY_MANAGER;
            $condition_groups_other["a.user_type"] = USER_TYPE_COMPANY_MANAGER;
            $condition_tags["user.user_type"] = USER_TYPE_COMPANY_MANAGER;
        }else{
            $condition_groups["a.user_type"] = array("neq",USER_TYPE_COMPANY_MANAGER);
            $condition_groups_other["a.user_type"] = array("neq",USER_TYPE_COMPANY_MANAGER);
            $condition_tags["user.user_type"] = array("neq",USER_TYPE_COMPANY_MANAGER);
        }
        $UserSupervise = A('UserSupervise');
        $condition_groups['a.is_valid'] = 1;
        $condition_groups['a.is_follow'] = 1;
        $condition_groups['a.branch_id'] = $this->user_branch;
        $condition_tags['user.is_valid'] = 1;
        $condition_tags['user.is_follow'] = 1;
        $condition_tags['user.branch_id'] = $this->user_branch;
        //标签
        $tags = $UserSupervise->getTargetTags();
        foreach($tags as $k=>$v){
            $condition_tags['urt.tag'] = $v['id'];
            $tags[$k]['user_count'] = D('SysUserRelationTag')
                ->setDacFilter('urt')
                ->join('sys_user as user on user.id = urt.user_id')
                ->where($condition_tags)
                ->count();
        }
        $this->tags = json_encode($tags ?? []);
        //分组
        $groups = $UserSupervise->getTargetGroups();
        foreach ($groups as $k => $v) {
            $condition_groups['a.group_id'] = $v['id'];
            $groups[$k]['user_count'] = D('SysUser')->setDacFilter('a')->where($condition_groups)->count();
        }
        $tmp = array('id'=>'other','value' => '未分组','branch_id' => $this->user_branch);
        $condition_groups_other['_string'] = 'a.group_id is null or a.group_id =""';
        $condition_groups_other['a.branch_id'] = $this->user_branch;
        $condition_groups_other['a.is_valid'] = 1;
        $condition_groups_other['a.is_follow'] = 1;
        $tmp['user_count'] = D('SysUser')
            ->setDacFilter('a')
            ->where($condition_groups_other)
            ->count();
        array_unshift($groups,$tmp);
        $this->groups = json_encode($groups);
    }

    public function bound_staffAction(){
        if(IS_POST){

        }else{
            $mobile = I("get.mobile");
            $this->assign("mobile",$mobile);
            $this->display();
        }
    }

    //获取新增员工验证码
    public function getCodeForAddStaffAction(){
        $_SESSION['regcode'] = rand(1000,9999);
        $mobile = I("post.mobile","","strip_tags");
        $beg_time = strtotime(date("Y-m-d"));
        /*$user = M("SysUser")->where("mobile = $mobile")->count();
        if(!$user){
            $this->ajaxReturn(array("error"=>1,"message"=>"发送失败，该手机号未绑定账户！"));
        }*/
        $condition['mobile'] = $mobile;
        $condition['type'] = "新增员工验证";
        $condition['begtime'] = $beg_time;
        $sms_all = D("sms_log")->where($condition)->count();
        if($sms_all > 5){
            $this->ajaxReturn(array("error"=>1,"message"=>"发送失败，您今天短信接收已超量！"));
        }else{
            $returnstatus = D("SmsLog")->send_sms_message($mobile,SMS_REG_CODE,array("code"=>$_SESSION['regcode']));
            if($returnstatus == "Success"){
                D("sms_log")->add($condition);
                $this->ajaxReturn(array("error"=>0,"message"=>"发送成功！","code"=>$_SESSION['regcode']));
            }else{
                $this->ajaxReturn(array("error"=>1,"message"=>"发送失败！"));
            }
        }
    }

    public function staffConfirmAction(){
        $code = I('post.code', '', 'strip_tags');
        if (trim($code) == "") {
            echo json_encode(array("error" => "1", "msg" => "验证码不能为空"));
            exit();
        }
        if (trim($code) <> $_SESSION['regcode']) {
            echo json_encode(array("error" => "1", "msg" => "验证码输入不正确"));
            exit();
        }
        $user_mobile = M("SysUser")->where("id = ".$this->userId)->getField("mobile");
        $mobile = I("post.mobile");
        if($user_mobile == $mobile){
            echo json_encode(array("error" => 0,"msg"=>'手机设置成功'));
            exit();
        }else{
            A("UserController")->mobileChangeAction();
        }
    }

}