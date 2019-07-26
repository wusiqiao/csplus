<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class  OrganizationController extends SysUserController {

    protected $storage = [];

    public function listAction() {
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $branch_id = I("branch_id");

        $_order = array();
        $this->_parseOrder($_order);
        $_filter = array();

        // $this->_parseFilter($_filter);
        $_filter['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
        $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
        $where['a.is_follow']  = 1;
        $where['_logic'] = 'or';
        $_filter['_complex'] = $where;
        $_filter['a.branch_id'] = $this->_user_session->currBranchId;
        if(!empty($branch_id) && $branch_id!=$this->_user_session->currBranchId){
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
            $_filter['a.is_valid'] = 1;
        }else{
	    	$condition = [];
			$condition['a.branch_id'] = $this->_user_session->currBranchId;
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
            /*改为点击公司名称显示所有员工*/
            if($branch_id == 0 && $branch_id != ""){
                $_filter['a.is_valid'] = 1;
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
        }
        //?管理员为什么不显
        $disable = I("post.disable");
        if($disable){
            $_filter = [];
            $_filter['is_valid'] = 0;
            $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
            $where['a.is_follow']  = 1;
            $where['_logic'] = 'or';
            $_filter['_complex'] = $where;
            $_filter['branch_id'] = $this->_user_session->currBranchId;
            $_filter['user_type'] = USER_TYPE_COMPANY_MANAGER;
        }
        /*if($branch_id != ""){
            $_filter['b.type'] = 2;
        }*/
        if(I("post.id")){
            $_filter['a.id'] = I("post.id");
        }
        $count = M('SysUser')
        	->alias("a")
            ->where($_filter)
        	->order($_order)
        	->count();

        $list = M('SysUser')
        	->alias("a")
        	->field("a.*")->where($_filter)
        	->page($page_index, $page_size)->order($_order)
        	->select();

        $this->_before_list($list);
        $result["total"] = $count;
        $result["rows"] = $list;
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($result));
    }

    protected function _before_list(&$list)
    {
        parent::_before_list($list);
        foreach($list as $key => $val) {
            /*if (empty($list[$key]['telephone'])) {
            	$list[$key]['telephone'] = $list[$key]['mobile'];
            }*/
            if (empty($list[$key]['staff_name'])) {
                $list[$key]['staff_name'] = $list[$key]['name'];
            }
            $condition['a.user_id'] = $val['id'];
            $condition['a.type'] = 2;
            $dept_name = M("SysUserBranch a")->join("sys_branch b on a.branch_id = b.id")->where($condition)->getField("b.name");
            if($dept_name){
                $list[$key]['dept_name'] = $dept_name;
            }else{
                //$list[$key]['dept_name'] = M("SysBranch")->where("id = " .$val['branch_id'])->getField("name");
                $list[$key]['dept_name'] = "无部门";
            }
            if($val['role_ids']){
                $role_names = "";
                $role = explode(",",$val['role_ids']);
                foreach ($role as $v){
                    if($v == ROLE_ID_COMPANY_MANAGER){
                        $role_names = '系统管理员';
                        break;
                    }
                    $role_name = M("SysRole")->where("id = $v and branch_id = ".$this->_user_session->currBranchId)->getField("name");
                    $role_names = $role_names == "" ? $role_name : $role_names."；".$role_name;
                }
                $list[$key]['role_name'] = $role_names;
            }
            if($val['director_id']){
                $user = M("SysUser")->where("id = ".$val['director_id'])->field("staff_name,name")->find();
                $list[$key]['director_name'] = $user['staff_name'] != "" ? $user['staff_name'] : $user['name'];
            }
        }
    }

    public function treeListAction()
    {
    	$condition = [];
    	$condition['id'] = $this->_user_session->currBranchId;
    	$sysBranch = M('SysBranch')->where($condition)->field('id,name,type')->find();
    	$this->getChildren($sysBranch);

    	$condition = [];
		$condition['a.branch_id'] = $this->_user_session->currBranchId;
		$condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
        $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
        $where['a.is_follow']  = 1;
        $where['_logic'] = 'or';
        $condition['_complex'] = $where;
        $condition['a.is_valid'] = 1;
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
        //array_push($user_ids,$this->_user_session->userId);
    	$condition = [];
		if (!empty($user_ids)) {
			//$condition['id'] = array('not in',$user_ids);
		}
    	$condition['user_type'] = USER_TYPE_COMPANY_MANAGER;
        $where['mobile']  = [['exp','is not null'],['exp','<> ""']];
        $where['is_follow']  = 1;
        $where['_logic'] = 'or';
        $condition['_complex'] = $where;
    	$condition['branch_id'] = $this->_user_session->currBranchId;
    	$sysBranch['user_count'] = M('SysUser')->alias('a')
			->where($condition)->count();
    	/*if(mb_strlen($sysBranch['name'],"utf-8") > 5){
            $sysBranch['text'] = mb_substr($sysBranch['name'],0,5)."... (".$sysBranch['user_count'].")";
        }else{*/
            $sysBranch['text'] = $sysBranch['name']." (".$sysBranch['user_count'].")";
        //}
    	$rst[0] = $sysBranch;
    	$type = I("get.type");
        $con['is_valid'] = 0;
        $con['branch_id'] = $this->_user_session->currBranchId;
        $con['user_type'] = USER_TYPE_COMPANY_MANAGER;
        $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
        $where['a.is_follow']  = 1;
        $where['_logic'] = 'or';
        $con['_complex'] = $where;
        $in_valid_count = M('SysUser')->alias('a')->where($con)->count();
    	if(!$type){
            $rst[1]['show'] = "0";
            $rst[1]['count'] = $in_valid_count ;
            $rst[1]['text'] = "已禁用账户"."(".$rst[1]['count'].")";
            $rst[1]['children'] = [];
            //array_push($rst[0]['children'],$rst[1]);
        }
        $tmp = [];
        $tmp['id'] = 0;
        $tmp['name'] = "无部门";
        $tmp['text'] = "无部门";
        $tmp['user_count'] = $sysBranch['user_count'] - count($user_ids)-$in_valid_count;
        $tmp['children'] = [];
        array_unshift($rst[0]['children'],$tmp);
    	$this->ajaxReturn($rst);
    }

    public function customPermissionAction(){
        if (IS_POST) {
            $post_data = I('post.');
            $user_ids = $post_data['user_ids'];
            $data = [];
            if (!empty($user_ids)) {
                foreach ($user_ids as $k => $v) {
                    $condition = [];
                    $condition['user_id'] = $post_data['target_id'];
                    $condition['target_id'] = $v;
                    $condition['type'] = 1;
                    $sysUserDataAccess = M('SysUserDataAccess')->where($condition)->find();
                    if (empty($sysUserDataAccess)) {
                        $data[] = $condition;
                    }
                }
                M('SysUserDataAccess')->addAll($data);
            }
            
            $data = [];
            $company_ids = $post_data['company_ids'];
            M("SysUserDataAccess")->where("user_id = ".$post_data['target_id']." and type = 0")->delete();
            if (!empty($company_ids)) {
                foreach ($company_ids as $k => $v) {
                    $condition = [];
                    $condition['user_id'] = $post_data['target_id'];
                    $condition['target_id'] = $v;
                    $condition['type'] = 0;
                    $sysUserDataAccess = M('SysUserDataAccess')->where($condition)->find();
                    if (empty($sysUserDataAccess)) {
                        $data[] = $condition;
                    }
                }
                M('SysUserDataAccess')->addAll($data);
            }
            //更新user表的update
            $condition_user['id'] = array('in',$post_data['target_id']);
            $save_user['updated_at'] = time();
            M('SysUser')->where($condition_user)->save($save_user);
            $this->ajaxReturn(array('code'=>0,'message'=>'保存客户权限成功'));
        } else {
            $id = I("get.id");
            $this->id = $id;
            $this->display('customPermission');
        }        
    }



    //部门列表排序
    public function sortTreeListAction(){
        $type = I("post.type");
        $id = I("post.id");
        $parent_id = I("post.parent_id");
        $condition['type'] = 2;
        $condition['parent_id'] = $parent_id;
        $depts = M("SysBranch")->where($condition)->order("sort desc")->select();
        $model = M("SysBranch");
        try{
            $model->startTrans();
            foreach ($depts as $k=>$v){
                if($v['sort'] == ""){
                    $maxSort = M("SysBranch")->where("parent_id = ".$v['parent_id']." and sort != ''")->order("sort desc")->getField("sort");
                    M("SysBranch")->where("id = ".$v['id'])->setField("sort",$maxSort +1);
                }
            }
            if($type == "up"){
                $sort = M("SysBranch")->where("id = $id")->getField("sort");
                $condition['sort'] = $sort-1 >0 ? $sort-1 : $sort;
            }else{
                $sort = M("SysBranch")->where("id = $id")->getField("sort");
                $condition['sort'] = $sort+1;
            }
            M("SysBranch")->where($condition)->setField("sort",$sort);
            M("SysBranch")->where("id = $id")->setField("sort",$condition['sort']);
            $model->commit();
            $this->ajaxReturn(array("error"=>0,"message"=>"操作成功！"));
        }catch(\Think\Exception $ex){
            $model->rollback();
            $this->ajaxReturn(array("error"=>1,"message"=>"操作成功！"));
        }
    }

    protected function getChildren(&$data,$is_valid = 1)
    {
    	$condition = [];
    	$condition['parent_id'] = $data['id'];
    	$condition['type'] = 2;
    	$condition['branch_id'] = $this->_user_session->currBranchId;
    	$list = M('SysBranch')->where($condition)->field('id,name,type,sort,parent_id')->order('sort asc')->select();
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
    	//$condition['a.id'] = array("neq",$this->_user_session->userId);
    	//if($is_valid != 1){
            $condition['a.is_valid'] = $is_valid;
        //}
        $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
        $where['a.is_follow']  = 1;
        $where['_logic'] = 'or';
        $condition['_complex'] = $where;

    	$condition['a.branch_id'] = $this->_user_session->currBranchId;
    	$data['user_count'] = M('SysUser')->alias('a')
            ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
			->where($condition)->count();
        if(mb_strlen($data['name'],"utf-8") > 5){
            $data['text'] = mb_substr($data['name'],0,5)."... (".$data['user_count'].")";
        }else{
            $data['text'] = $data['name']." (".$data['user_count'].")";
        }
    }

    public function DeptNameListAction() {
    	$condition["type"] = 2;
   		$condition["branch_id"] = $this->_user_session->currBranchId;
  		$list = M("SysBranch")->where($condition)->field("id,name as text")->select();
  		$this->ajaxReturn($list);
    }

    public function roleListAction() {
        $condition["branch_id"] = $this->_user_session->currBranchId;
        $condition['is_admin'] = array(array("neq",1),array("exp","is null"),"or");
        $list = M("SysRole")->where($condition)->field("id as value,name as text")->select();
        $this->ajaxReturn($list);
    }

    public function DeptListAction() {
    	$condition = [];
    	if (I("branch_id")) {
    		$condition['id'] = I("branch_id");
    	}else{
    		$condition['id'] = $this->_user_session->currBranchId;
    	}
    	$sysBranch = M('SysBranch')->where($condition)->field('id,name')->find();
    	$this->getChildren($sysBranch);
    	$this->ajaxReturn($sysBranch['children']);
    }

    public function addDeptAction($dept_id = null) {
    	if (IS_POST) {
	    	$condition = [];
	    	$condition["type"] = 2;
	   		$condition["branch_id"] = $this->_user_session->currBranchId;
	   		$condition["name"] = I("post.name");
            if(mb_strlen($condition["name"],'utf-8') > 10){
                $this->ajaxReturn(buildMessage('最多输入10个字！',1));
            }
			$sysBranch = M("SysBranch")->where($condition)->find();
	   		if (!empty($sysBranch)) {
	   			$this->ajaxReturn(buildMessage('已存在同名的部门',1));
	   		}else{
	   		    $branch_id = getBrowseBranchId();
	   		    $branch_name = M("SysBranch")->where("id = $branch_id")->getField("name");
	   		    if($condition["name"] == $branch_name){
                    $this->ajaxReturn(buildMessage('名称与公司名称重复',1));
                }
		   		$data = $condition;
                $data["is_valid"] = 1;
		   		$data["parent_id"] = $dept_id;
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
	            	$this->ajaxReturn(array('code'=>0,'message'=>'添加子部门成功'));
	            }else{
	            	$this->ajaxReturn(array('code'=>1,'message'=>'添加子部门失败'));
	            }
	   		}
		} else {
			if (empty($dept_id)) {
				$dept_id = $this->_user_session->currBranchId;
			}
			// define("__FORM_ACTION__", "");
			$this->dept_action = addDept; 
			$this->dept_id = $dept_id;
            $dept_name = M("SysBranch")->where("id = ".$dept_id)->getField("name");
            $this->dept_name = $dept_name;
			$this->display('addDept');
		}
    }

    public function editDeptAction($id) {
    	$condition = [];
   		// $condition["branch_id"] = $this->_user_session->currBranchId;
   		$condition["id"] = $id;
   		$model = M("SysBranch");
    	if (IS_POST) {
			$post_data = I('post.');
	        if ($data = $model->create($post_data)) {
                $condition = [];
                $condition["id"] = array('neq',$id);
                $condition["branch_id"] = $this->_user_session->currBranchId;
                $condition["type"] = 2;
                $condition["name"] = I("post.name");
                if(mb_strlen($condition["name"],'utf-8') > 10){
                    $this->ajaxReturn(buildMessage('最多输入10个字！',1));
                }
                $sysBranch = M("SysBranch")->where($condition)->find();
                if (!empty($sysBranch)) {
                    $this->ajaxReturn(buildMessage('已存在同名的部门',1));
                }
                $condition = [];
                $condition["id"] = $id;
                $condition["branch_id"] = $this->_user_session->currBranchId;
                $data['type'] = 2;
	            $updated = false;
                // var_dump($condition);
	        	try {
		            $model->startTrans();
		            $updated = $model->where($condition)->save($data);
		            $model->commit();
		        } catch (\Think\Exception $ex) {
		            $model->rollback();
		            $this->responseJSON(buildMessage("保存失败：" . $ex->getMessage(), 1));
		        }
	            if ($updated !== false) {
	                // $result_data = $this->_getLastData($id);
	                // $this->responseJSON(buildMessage($result_data));
                    $this->ajaxReturn(array('code'=>0,'message'=>'编辑部门成功'));
	            }
	        } else {
	            $this->responseJSON(buildMessage("保存失败：" . $model->getError(), 1));
	        }
    	} else {
    		$sysBranch = M("SysBranch")->where($condition)->field('id,name,parent_id')->find();
            $this->getChildren($sysBranch);

    		$this->dept_action = "editDept";
            $this->assignPermissions();
            $dept_name = M("SysBranch")->where("id = ".$sysBranch['parent_id'])->getField("name");
            if($sysBranch['parent_id'] != 1){
                $this->dept_name = $dept_name;
            }
            $this->assign("model", $sysBranch);
    		$this->display('addDept');
    	}
    }

    public function deleteDeptAction($id) {
    	if (IS_POST) {
	    	$condition = [];
	    	$condition['id'] = $id;
	    	$condition['type'] = 2;
	    	$sysBranch = M('SysBranch')->where($condition)->field('id,name as text')->find();
			$this->getChildren($sysBranch,0);
	   		/*if (!empty($sysBranch) && $sysBranch['user_count'] > 0) {
                $this->ajaxReturn(array('code' => 1, 'message' => '部门内还存在员工(含已禁用)，请先移除员工'));
	   		}elseif(!empty($sysBranch['children'])){
   		        //$this->isHasStaff($sysBranch['children']);
                $this->deleteChildDept($sysBranch['children']);
                M("SysBranch")->where("id = ".$sysBranch['id'])->delete();
                $this->ajaxReturn(array('code' => 0, 'message' =>  '删除部门成功'));
            }else {
				M("SysBranch")->where($condition)->delete();
                $this->ajaxReturn(array('code' => 0, 'message' => '删除部门成功'));
	   		}*/
	   		if(!empty($sysBranch)){
                $this->deleteChildDept($sysBranch['children']);
            }
            M("SysBranch")->where("id = ".$sysBranch['id'])->delete();
            M("SysUserBranch")->where("branch_id = ".$sysBranch['id']." and type = 2")->delete();
            $this->ajaxReturn(array('code' => 0, 'message' =>  '删除部门成功'));
    	}else{
    	    $this->id = $id;
    	    $this->display("deleteDept");
        }
    }

    //删除部门将部门及子部门内员工转移到公司
    public function deleteChildDept($children){
        foreach($children as $child){
            M("SysBranch")->where("id = ".$child['id'])->delete();
            M("SysUserBranch")->where("branch_id = ".$child['id']." and type = 2")->delete();
            if(!empty($child['children'])){
                $this->deleteChildDept($child['children']);
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
    }*/

    //删除员工即删除与部门的关联并改为意向客户
    public function deleteStaffAction() {
    	if (IS_POST) {
	    	$condition = [];
	    	$user_ids = I('post.user_ids');
	    	$condition['user_id'] = array('in',$user_ids);
	    	$condition['type'] = 2;
	    	$result = $this->isHasWork($user_ids[0]);
	    	if($result['agreement'] or $result['receivables'] or $result['prompt'] or $result['invoice']){
                $this->ajaxReturn(array("code"=>2,"message"=>"该员工已设置为负责人，请选择交接人员"));
            }
	    	M('SysUserBranch')->where($condition)->delete();
	    	$condition = [];
	    	$condition['id'] = array('in',$user_ids);
	    	$condition['branch_id'] = $this->_user_session->currBranchId;
	    	$data['user_type'] = USER_TYPE_PROSPECTIVE;
            $data['role_ids'] = ROLE_ID_CUSTOMER;
            $data['is_valid'] = 1;
            $data['staff_name'] = "";
			M("SysUser")->where($condition)->save($data);
            $condition = [];
            $condition['user_id'] = array('in',$user_ids);
            M("SysUserRole")->where($condition)->delete();
            $user_role = [];
            foreach ($user_ids as $k=>$v){
                $user_role[] = ["user_id"=>$v,"role_id"=>ROLE_ID_CUSTOMER];
            }
            M("SysUserRole")->addAll($user_role);
            $this->deleteUserModuleSetting($user_ids);
			$this->ajaxReturn(array('code'=>0,'message'=>'删除员工成功'));
    	}
    }

    //删除员工时删除通知人为此员工的记录
    public function deleteUserModuleSetting($user_ids){
        $condition = [];
        $condition['user_id'] = array("in",$user_ids);
        $condition['type'] = DAC_SETTING_TYPE_BRANCH;
        $condition['branch_id'] = getBrowseBranchId();
        $condition['permit_value'] = DAC_PERMIT_VALUE_NOTICER;
        M("SysUserModuleSetting")->where($condition)->delete();
    }

    //删除员工时替换负责人，将权限设置表负责人替换为新员工
    public function handlerReplaceModuleUser($user_ids,$replace_id){
        $condition = [];
        $condition['permit_value'] = DAC_PERMIT_VALUE_LEADER;
        $condition['type'] = DAC_SETTING_TYPE_BRANCH;
        $condition['branch_id'] = getBrowseBranchId();
        $condition['user_id'] = array("in",$user_ids);
        M("SysUserModuleSetting")->where($condition)->setField("user_id",$replace_id);
    }

    //判断该员工是否是负责人
    public function handlerReplaceStaffAction(){
        if(IS_POST){
            $replace_id = I("post.replace_id");//新替换员工
            $user_ids = I("post.user_ids");//即将删除员工
            $result = $this->isHasWork($user_ids[0]);
            $data = [];
            foreach ($result['agreement'] as $k=>$v){
                $data['leader_id'] = $replace_id;
                M("WrkAgreement")->where("id = ".$v['id'])->save($data);
            }
            foreach ($result['receivables'] as $k=>$v){
                $data['leader_id'] = $replace_id;
                M("WrkReceivables")->where("id = ".$v['id'])->save($data);
            }
            foreach ($result['prompt'] as $k=>$v){
                $data['leader_id'] = $replace_id;
                M("WrkPrompt")->where("id = ".$v['id'])->save($data);
            }
            foreach ($result['invoice'] as $k=>$v){
                $data['leader_id'] = $replace_id;
                M("WrkInvoicePlan")->where("id = ".$v['id'])->save($data);
            }
            $this->handlerReplaceModuleUser($user_ids,$replace_id);
            $this->deleteStaffAction();
        }else{
            $this->id = I("get.id");
            $this->display();
        }
    }

    public function isHasWork($id){
        //foreach($user_ids as $k=>$v){
        /*$where["leader_id"] = $id;
        $where["_string"] = "FIND_IN_SET($id,visiblers)";
        $where['_logic'] = "or";
        $filter['_complex'] = $where;*/
        $filter['leader_id'] = $id;
        $result['agreement'] = M("WrkAgreement")->where($filter)->field("id,visiblers,leader_id")->select();
        $result['receivables'] = M("WrkReceivables")->where($filter)->field("id,visiblers,leader_id")->select();
        $result['prompt'] = M("WrkPrompt")->where($filter)->field("id,visiblers,leader_id")->select();
        $result['invoice'] = M("WrkInvoicePlan")->where($filter)->field("id,visiblers,leader_id")->select();
        return $result;
        /*if($agreement or $receivables or $prompt or $invoice){
            $this->ajaxReturn(buildMessage("该员工已设置为负责人或可见人，请选择交接人员",1));
        }*/
        //}
    }

    public function userListAction()
    {
            $str = I('q');
            $condition = [];
            if (!empty($str)) {
                $where['name']  = array('like', '%'.$str.'%');
                $where['querykey']  = array('like', '%'.$str.'%');
                $where['mobile']  = array('like', '%'.$str.'%');
                $where['comments']  = array('like', '%'.$str.'%');
                $where['_logic'] = 'or';
                $condition['_complex'] = $where;
            }
            $condition['branch_id'] = $this->_user_session->currBranchId;
            $condition['user_type'] = array('neq',USER_TYPE_COMPANY_MANAGER);

            $condition['_string'] = '(is_follow = 1 or (mobile is not null and mobile <> "" ) )';
            $company_data = M('SysUser')
                ->where($condition)
                ->field("id,name,querykey,head_pic,mobile,comments,staff_name,telephone,account,email,qq")
                ->select();
            foreach ($company_data as $k => $v) {
	            //自定义属性
	            $condition = [];
	       		$condition["user_id"] = $v['id'];
	       		$list = M("SysCustomerInformation")->field("id,title,type,value")->where($condition)->order('id asc')->select();
	       		$company_data[$k]["custom"] = $list;
	       		//部门
	        	$condition = [];
		        $condition["a.user_id"] = $v['id'];
		        $condition["b.type"] = 2;
		        $deptment = M("SysUserBranch")
		            ->alias("a")
		            ->join('LEFT JOIN sys_branch b ON b.id = a.branch_id')
		            ->where($condition)->find();
		        $company_data[$k]["deptment_id"] = isset($deptment['id'])?$deptment['id']:null;
                if(!empty($sysUser[$k]['comments'])) {
                    $company_data[$k]['name'] = $company_data[$k]['comments'];
                }
            }
            return $this->ajaxReturn($company_data);
    }

    //新增员工
    public function addStaffAction() {
    	if (IS_POST) {
    	}else{
    		define("__FORM_ACTION__", "update");
    		$this->display('edit');
    	}
    }

    //添加人员、调整部门
    public function adjustStaffAction($dept_id = null) {
    	if (IS_POST) {
			$user_ids = I('post.user_ids');
            $condition = [];
            $condition['user_id'] = array('in',$user_ids);
            $condition['type'] = 2;
            M('SysUserBranch')
            	->where($condition)->delete();
            $sys_user_branch_data = [];
            if (!empty($user_ids) && $dept_id != $this->_user_session->currBranchId) {
	            foreach ($user_ids as $k => $v) {
		            $sys_user_branch_data[$k]['user_id'] = $v;
		            $sys_user_branch_data[$k]['type'] = 2;
		            $sys_user_branch_data[$k]['branch_id'] = $dept_id;
	            }
                // var_dump($sys_user_branch_data);
            	$result = M('SysUserBranch')->addAll($sys_user_branch_data);
            }
            return $this->ajaxReturn(array('code' => 0, 'message' => '添加人员成功'));
    	} else {
    		if ($dept_id!= null) {
	    		$sysUser = [];
		    	$condition = [];
	    		$condition['a.branch_id'] = $this->_user_session->currBranchId;
	    		$condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
                $condition['a.is_leader'] = 0;
                $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
                $where['a.is_follow']  = 1;
                $where['_logic'] = 'or';
                $condition['_complex'] = $where;

	    		$condition['b.branch_id'] = $dept_id;
				$condition['b.type'] = 2;

	    		$list = M('SysUser')->alias('a')
	            ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
				->where($condition)->select();

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
                $where = [];
                $where['mobile']  = [['exp','is not null'],['exp','<> ""']];
                $where['is_follow']  = 1;
                $where['_logic'] = 'or';
                $condition['_complex'] = $where;

				if (!empty($user_ids)) {
					$condition['id'] = array('not in',$user_ids);
				}
	    		$list = M('SysUser')
				->where($condition)->field('id,name,staff_name,comments,telephone,mobile')->select();
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

    		}else{
    			$dept_id = $this->_user_session->currBranchId;
	    		$sysUser = [];
		    	$condition = [];
	    		$condition['a.branch_id'] = $this->_user_session->currBranchId;
	    		$condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
                $condition['a.is_leader'] = 0;
                $where = [];
                $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
                $where['a.is_follow']  = 1;
                $where['_logic'] = 'or';
                $condition['_complex'] = $where;

				$condition['b.type'] = 2;

	    		$list = M('SysUser')->alias('a')
	            ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
				->where($condition)->field('a.id,a.name,a.staff_name,a.comments')->select();

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

				$user_ids = [];
				if (!empty($sysUser['out'])) {
					foreach ($sysUser['out'] as $k => $v) {
						array_push($user_ids,$v['id']);
					}
				}
				$condition = [];
				$condition['branch_id'] = $this->_user_session->currBranchId;
				$condition['user_type'] = USER_TYPE_COMPANY_MANAGER;
                $condition['is_leader'] = 0;
                $where = [];
                $where['mobile']  = [['exp','is not null'],['exp','<> ""']];
                $where['is_follow']  = 1;
                $where['_logic'] = 'or';
                $condition['_complex'] = $where;
				if (!empty($user_ids)) {
					$condition['id'] = array('not in',$user_ids);
				}
	    		$list = M('SysUser')
				->where($condition)->field('id,name,staff_name,comments,telephone,mobile')->select();

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

    		}

			$this->dept_id = $dept_id;
    		$this->assign("model", $sysUser);
			$this->display('adjustStaff');
    	}
    }

    public function showTipAction()
    {
        $this->display('show_tip');
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
            if (!empty($user_ids) && $dept_id != $this->_user_session->currBranchId) {
	            foreach ($user_ids as $k => $v) {
		            $sys_user_branch_data[$k]['user_id'] = $v;
		            $sys_user_branch_data[$k]['type'] = 2;
		            $sys_user_branch_data[$k]['branch_id'] = $dept_id;
	            }
            	$result = M('SysUserBranch')->addAll($sys_user_branch_data);
            }
            return $this->ajaxReturn(array('code' => 0, 'message' => '操作成功'));
    	} else {
    		$this->dept_id = $this->_user_session->currBranchId;
			$this->display('adjustDept');
    	}
    }

    public function customerListAction()
    {
        $name = I('name');
        $mobile = I('mobile');
        $group_id = I('group_id');
        $tag_id = I('tag_id');

        $condition = [];
        $condition['_string'] = '(a.is_follow = 1 or (a.mobile is not null and a.mobile <> "" ))';
        if (!empty($name)) {
            $condition['a.name']  = array('like', '%'.$name.'%');
        }
        if (!empty($mobile)) {
            $condition['a.mobile']  = array('like', '%'.$mobile.'%');
        }
        if (!empty($group_id)) {
            if ($group_id == 'other') {
                $condition['_string'] .= ' and ((a.group_id is null ) or (a.group_id = ""))';
            }else{
                $condition['a.group_id'] = $group_id;
            }
        }
        if (!empty($tag_id)) {
            $this->handlerTagsSearch($tag_id,$condition);
        }
        $condition['a.branch_id'] = $this->_user_session->currBranchId;
        $condition['a.user_type'] = array('neq',USER_TYPE_COMPANY_MANAGER);

        $user_ids = [];
        $sysUserBranch = M('SysUserBranch')->alias('a')
        ->join('LEFT JOIN sys_branch b ON b.id = a.branch_id')->where(['b.type' =>['neq',2]])->field('a.user_id,a.branch_id')->select();
        if (!empty($sysUserBranch)) {
            foreach ($sysUserBranch as $k => $v) {
                array_push($user_ids, $v['user_id']);
            }
            $condition["a.id"][] = array('not in',$user_ids);
        }

        $sysUser = M('SysUser')
            ->where($condition)
            ->alias('a')
            ->join('LEFT JOIN sys_target_group b ON b.id = a.group_id')
            ->field("a.id,a.querykey,a.head_pic,a.name,a.mobile,b.value as group_name,a.comments")
            ->select();

        foreach ($sysUser as $k => $v) {
            $sysUser[$k]['tags_value'] = D('UserParse')->getGroupNames($v);
            $sysUser[$k]['group_name'] = isset($sysUser[$k]['group_name'])?$sysUser[$k]['group_name']:"未分组";
        }
        $this->ajaxReturn($sysUser);
    }

    //客户权限获取已选择公司
    public function getCheckedCompanyAction(){
        $id = I("post.id");
        $result = M("SysUserDataAccess a")
            ->join("sys_branch b on a.target_id = b.id")
            ->where("a.user_id = $id and a.type = 0")
            ->field("b.id,b.name")->select();
        $this->ajaxReturn($result);
    }

    public function companyListAction()
    {
        $id = I("get.id");
        $condition = [];
        $name = I('name');
        if (!empty($name)) {
            $condition['name']  = array('like', '%'.$name.'%');
        }
        $condition['parent_id'] = $this->_user_session->currBranchId;
        $condition['type'] = array('neq',2);
        $sysBranch = M('SysBranch')
            ->where($condition)
            ->field("id,name,linkman,contact")
            ->select();
        $branch_chencked = M("SysUserDataAccess a")
            ->join("sys_branch b on a.target_id = b.id")
            ->where("a.user_id = $id and a.type = 0")
            ->field("b.id,b.name")->select();
        $branch = array_column($sysBranch,"id");
        foreach ($branch as $k=>$v){
            if(in_array($v,array_column($branch_chencked,"id"))){
                $sysBranch[$k]['checked'] = true;
            }
        }
        $this->ajaxReturn($sysBranch);
    }

    public function staffListForSearchAction()
    {
        $str = I('q');
        $condition = [];
        $where['staff_name']  = array('like', '%'.$str.'%');
        $where['name']  = array('like', '%'.$str.'%');
        //$where['querykey']  = array('like', '%'.$str.'%');
        $where['mobile']  = array('like', '%'.$str.'%');
        //$where['comments']  = array('like', '%'.$str.'%');
        $where['_logic'] = 'or';
        $condition['_complex'] = $where;
        if(!$this->_user_session->isAdmin){
            $branch_id = $this->_user_session->currBranchId;
            if($branch_id != null){
                $condition['branch_id'] = $branch_id;
            }else{
                $condition['branch_id'] = 1;
            }
        }else{
            $condition['branch_id'] = 1;
        }
        $condition['user_type'] = array('eq',USER_TYPE_COMPANY_MANAGER);
        //$condition['id'] = array('neq',$this->_user_session->userId);
        $condition['_string'] = '(is_follow = 1 or (mobile is not null and mobile <> "" ) )';
        /*//选择上级主管不能为自己
        $detailId = I("get.detailId");
        if($detailId){
            $condition['id'] = array('neq',$detailId);
        }*/
        $sysUser = M('SysUser')
            ->where($condition)
            ->field("id,querykey,head_pic,name,mobile,comments,staff_name,is_valid")
            ->select();
        if (!empty($sysUser)) {
            foreach ($sysUser as $k => $v) {
                $condition = [];
                $condition["user_id"] = $v['id'];
                $condition['type']  = 2;
                $deptment = M("SysUserBranch")
                    ->where($condition)->find();
                if (!empty($deptment)) {
                    $sysUser[$k]['branch_id'] = $deptment['branch_id'];
                    $sysUser[$k]['branch_name'] = M("SysBranch")->where("id = ".$deptment['branch_id'])->getField("name");
                } else {
                    $sysUser[$k]['branch_id'] = $this->_user_session->currBranchId;
                    $sysUser[$k]['branch_name'] = '无部门';
                }
                if (!empty($sysUser[$k]['staff_name'])) {
                    $sysUser[$k]['name'] = $sysUser[$k]['staff_name'];
                }/*elseif(!empty($sysUser[$k]['comments'])) {
                    $sysUser[$k]['name'] = $sysUser[$k]['comments'];
                }*/
            }
        }
        /*if(!$this->_user_session->isAdmin){
            $admin = M("SysUser")->where("id = 1")->select();
            array_unshift($sysUser,$admin);
        }*/
        $this->ajaxReturn($sysUser);
    }

    public function staffListForDacBrabchsAction()
    {
        $condition = [];
        $condition['id'] = $this->_user_session->currBranchId;
        $sysBranch = M('SysBranch')->where($condition)->field('id,name,type')->find();
        $this->getChildren($sysBranch);

        $condition = [];
        $condition['a.branch_id'] = $this->_user_session->currBranchId;
        $condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
        $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
        $where['a.is_follow']  = 1;
        $where['_logic'] = 'or';
        $condition['_complex'] = $where;
        $condition['a.is_valid'] = 1;
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
        //array_push($user_ids,$this->_user_session->userId);
        $condition = [];
        if (!empty($user_ids)) {
            //$condition['id'] = array('not in',$user_ids);
        }
        $condition['user_type'] = USER_TYPE_COMPANY_MANAGER;
        $where['mobile']  = [['exp','is not null'],['exp','<> ""']];
        $where['is_follow']  = 1;
        $where['_logic'] = 'or';
        $condition['_complex'] = $where;
        $condition['branch_id'] = $this->_user_session->currBranchId;

        $rst[0] = $sysBranch;
        $this->ajaxReturn($rst);
    }

    public function staffListForDacUsersAction(){
    {
        $sysBranch = M('SysBranch')->where(['id'=>$this->_user_session->currBranchId])->field('id,name,type')->find();
        $condition = [];
        $condition['type'] = 2;
        $condition['branch_id'] = $this->_user_session->currBranchId;
        $list = M('SysBranch')->where($condition)->field('id,name,type,sort,parent_id')->order('sort asc')->select();
        $condition = [];
        $condition['b.branch_id'] = $data['id'];
        $condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
        $condition['a.is_valid'] = 1;
        $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
        $where['a.is_follow']  = 1;
        $where['_logic'] = 'or';
        $condition['_complex'] = $where;
        $condition['a.branch_id'] = $this->_user_session->currBranchId;
        if (!empty($list)) {
            foreach ($list as $k => $v) {
                $condition = [];
                $condition['b.branch_id'] = $v['id'];
                $condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
                $condition['a.is_valid'] = 1;
                $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
                $where['a.is_follow']  = 1;
                $where['_logic'] = 'or';
                $condition['_complex'] = $where;
                $condition['a.branch_id'] = $this->_user_session->currBranchId;
                $list[$k]['children'] = M('SysUser')->alias('a')
                    ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
                    ->field('a.id,a.name,a.staff_name')
                    ->where($condition)->select();
                $list[$k]['is_user'] = 0;
            }
            $sysBranch['children'] = $list;
        }else{
            $sysBranch['children'] = [];
        }


        $condition = [];
        $condition['a.branch_id'] = $this->_user_session->currBranchId;
        $condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
        $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
        $where['a.is_follow']  = 1;
        $where['_logic'] = 'or';
        $condition['_complex'] = $where;
        $condition['a.is_valid'] = 1;
        $condition['b.type'] = 2;
        $sysUser = M('SysUser')->alias('a')
        ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
        ->where($condition)->field('a.id,a.name,a.staff_name')->select();

        $user_ids = [];
        if (!empty($sysUser)) {
            foreach ($sysUser as $k => $v) {
                array_push($user_ids,$v['id']);
            }
        }
        $condition = [];
        $where = [];
        if (!empty($user_ids)) {
            $condition['id'] = array('not in',$user_ids);
        }
        $condition['user_type'] = USER_TYPE_COMPANY_MANAGER;
        $where['mobile']  = [['exp','is not null'],['exp','<> ""']];
        $where['is_follow']  = 1;
        $where['_logic'] = 'or';
        $condition['_complex'] = $where;
        $condition['branch_id'] = $this->_user_session->currBranchId;
        $condition['is_valid'] = 1;

        $rst[0] = $sysBranch;
        $tmp = [];
        $tmp['children'] = M('SysUser')->where($condition)->field('id,name,staff_name')->select();
        $tmp['id'] = 0;
        $tmp['name'] = "无部门";
        $tmp['is_user'] = 0;
        array_unshift($rst[0]['children'],$tmp);
        $this->ajaxReturn($rst);
    }






        
        $condition['user_type'] = array('eq',USER_TYPE_COMPANY_MANAGER);
        $condition['_string'] = '(is_follow = 1 or (mobile is not null and mobile <> "" ) )';
        $sysUser = M('SysUser')
            ->where($condition)
            ->field("id,querykey,head_pic,name,mobile,comments,staff_name,is_valid")
            ->select();
        $rst['name'] = '全部';
        $rst['children'] = $sysUser;
        $result[] = $rst;
        $this->ajaxReturn($result);
    }

    //实现导入功能以及导出导出页面的显示
    public function importAction() {
    	if (IS_POST) {

    		$ex = $_FILES['excel'];
            //重设置文件名
            $filename = time().substr($ex['name'],stripos($ex['name'],'.'));
            $filePath = realpath("./").ltrim(MODULE_UPLOAD_PATH, ".").$filename;//设置移动路径
            move_uploaded_file($ex['tmp_name'],$filePath);
            $filesize=abs(filesize($filePath));
            if ($filesize > 4194304) {
                $this->ajaxReturn(array('code' => 1, 'message' => '文件大于4M'));die;
            }

            //载入PHPExcel类型
            vendor('PHPExcel18.PHPExcel');
            $objectReader = \PHPExcel_IOFactory::createReaderForFile($filePath);
            $objPHPExcel  = $objectReader->load($filePath);
            $currentSheet = $objPHPExcel->getSheet(0);
            $this->handlerSheetData($currentSheet);

            if (empty($this->storage)) {
                return array('code'=>1,'message'=>'导入失败,没有所需数据!!');die;
            } else {
                $dept_id = I('dept_id');
                $sysUser = $this->storage;
                $data = [];
                foreach ($sysUser as $k => $v) {
                    $condition = [];
                    $condition['name'] = $v['name'];
                    $condition['mobile'] = $v['mobile'];
                    $condition['branch_id'] = $this->_user_session->currBranchId;
                    $sysUser = M('SysUser')->where($condition)->find();
                    $user_id = isset($sysUser['id'])?$sysUser['id']:null;

                    $data[$k]['id'] = $user_id;
                    $data[$k]['staff_name'] = $user_id;
                    $data[$k]['telephone'] = $v['telephone'];
                    $data[$k]['email'] = $v['email'];
                    $data[$k]['qq'] = $v['QQ'];
                    $data[$k]['depment_id'] = $dept_id;
                }
                foreach ($data as $k => $v) {
                    if ($v['id'] != null) {
                        $user_data = [];
                        $condition = [];
                        $condition['id'] = $v['id'];
                        $user_data['staff_name'] = $v['staff_name'];
                        $user_data['telephone'] = $v['telephone'];
                        $user_data['email'] = $v['email'];
                        $user_data['qq'] = $v['qq'];
                        // M('SysUser')->where($condition)->save($user_data);

                        if ($dept_id != null && $dept_id != ''){
                            $condition = [];
                            $condition['user_id'] = $v['id'];
                            $condition['type'] = 2;
                            // M('SysUserBranch')->where($condition)->delete();
                            $sys_user_branch_data = [];
                            $sys_user_branch_data['user_id'] = $v['id'];
                            $sys_user_branch_data['type'] = 2;
                            $sys_user_branch_data['branch_id'] = $dept_id;
                            // M('SysUserBranch')->add($sys_user_branch_data);
                        }
                    }
                }
                $this->ajaxReturn(array('code' => 0, 'message' => '导入成功'));
            }
    	} else {
    		$this->display();
    	}
    }

    public function exportAction() {
        $_order = array();
        $this->_parseOrder($_order);
        $_filter = array();
        // $this->_parseFilter($_filter);
        $_filter['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
        $where['a.mobile']  = [['exp','is not null'],['exp','<> ""']];
        $where['a.is_follow']  = 1;
        $where['_logic'] = 'or';
        $_filter['_complex'] = $where;

        $_filter['a.branch_id'] = $this->_user_session->currBranchId;
        $data = M('SysUser')
            ->alias("a")
            ->field("a.*")->where($_filter)
            ->order($_order)
            ->select();
        $this->_before_list($data);
        foreach ($data as $k => $v) {
            $condition = [];
            $condition["a.user_id"] = $v['id'];
            $condition["b.type"] = 2;
            $deptment = M("SysUserBranch")
                ->alias("a")
                ->join('LEFT JOIN sys_branch b ON b.id = a.branch_id')
                ->field("b.id,b.name")
                ->where($condition)->find();
            $data[$k]['dept_name'] = $deptment['name'];
        }

        vendor('PHPExcel18.PHPExcel');
        error_reporting(E_ALL);
        date_default_timezone_set('Europe/London');
        $objPHPExcel = new \PHPExcel();
 
        /*以下是一些设置 ，什么作者  标题啊之类的*/
         $objPHPExcel->getProperties()
           ->setTitle("数据EXCEL导出")
           ->setSubject("数据EXCEL导出")
           ->setDescription("备份数据")
           ->setKeywords("excel")
          ->setCategory("result file");
         /*处理Excel里的数据*/
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('A1','头像')
                ->setCellValue('B1','昵称')   
                ->setCellValue('C1','联系电话')   
                ->setCellValue('D1', '姓名')   
                ->setCellValue('E1', '所属部门')   
                ->setCellValue('F1', 'qq')    
                ->setCellValue('G1', '邮箱')
                ->setCellValue('H1', '备注'); 
        foreach($data as $k => $v){
 
            $num=$k+2;
                $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('A'.$num, $v['head_pic'])
                ->setCellValue('B'.$num, $v['name'])   
                ->setCellValue('C'.$num, $v['telephone'])   
                ->setCellValue('D'.$num, $v['staff_name'])   
                ->setCellValue('E'.$num, $v['dept_name'])   
                ->setCellValue('F'.$num, $v['qq'])    
                ->setCellValue('G'.$num, $v['email'])
                ->setCellValue('H'.$num, $v['comments']);        
            }
 
            $objPHPExcel->getActiveSheet()->setTitle('User');
            $objPHPExcel->setActiveSheetIndex(0);
             header('Content-Type: applicationnd.ms-excel');
             $name ="员工列表";
             header('Content-Disposition: attachment;filename="'.$name.'.xls"');
             header('Cache-Control: max-age=0');
             $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
             $objWriter->save('php://output');
             exit;


    }

    private function handlerSheetData($sheet)
    {
        $heightRow = $sheet->getHighestRow();
        $heightColumn = $sheet->getHighestColumn();
        //循环读取excel文件,读取一条,插入一条
        $data = array();
        $showTypeJ = 2;
        $showTypeK = ord('A');
        $showTypeJMax = $heightRow;
        $showTypeKMax = $this->getExcelColumnChar($heightColumn, false);
        $matching = $this->getMatchingData();
        for ($j = $showTypeJ; $j <= $showTypeJMax; $j++) {
            for ($k = $showTypeK; $k <= $showTypeKMax; $k++) {
                $kc = $this->getExcelColumnChar($k, true);
                $cell = $kc . "1";
                $cell_value = "$kc$j";
                if (!empty(trim($sheet->getCell($cell_value)->getValue()))) {
                    foreach ($matching as $key => $val) {
                        if (strpos(trim($sheet->getCell($cell)->getValue()), $val['text']) !== false) {
                            $this->storage[$j][$val['value']] = trim($sheet->getCell($cell_value)->getValue());
                            break;
                        }
                    }
                }
            }
        }
    }

    /**
     * Create Format ASCII
     * @param $index
     * @param bool $getAsc
     * @return float|int|string
     */
    private function getExcelColumnChar($index,$getAsc = true){
        if($getAsc){
            if (chr($index) > "Z") {
                return "A" . chr($index - ord("Z") - 1 + ord("A"));
            } else {
                return chr($index);
            }
        }else{
            if(strlen($index) == 2 && is_string($index)){
                $start_str = "A";
                $end_str   = "Z";
                $str1 = substr_replace($index,'',1,1);
                $str2 = substr_replace($index,'',0,1);
                $cycle = (ord($end_str) - ord($start_str) + 1);
                $cycle_sum = $cycle *( ord($str1) - ord($start_str) + 1 );
                return $cycle_sum + ord($start_str) + ord($str2) - ord($start_str);
            }elseif(!is_string($index)){
                return $this->getExcelColumnChar($index,true);
            }else{
                return ord($index);
            }
        }
    }

    /*
     * 匹配数据 - 导入
     */
    private function getMatchingData()
    {
        $result = [
            ['value' => 'name', 'text' => '昵称'],
            ['value' => 'mobile', 'text' => '绑定电话'],
            ['value' => 'staff_name', 'text' => '姓名'],
            ['value' => 'telephone', 'text' => '联系电话'],
            ['value' => 'email', 'text' => '邮箱'],
            ['value' => 'QQ', 'text' => 'QQ'],
            ['value' => 'deptment', 'text' => '部门']
        ];
        return $result;
    }

    public function downloadTemplateAction() {
    	$http = new \Org\Net\Http;  
        $filename =  realpath("./").ltrim(MODULE_UPLOAD_PATH, ".")."dominant/staffTemplate.xls";  
        $showname="staffTemplate.xls";  
        $http->download($filename, $showname);  
    }
    ///////////多级选项
    public function handlerTagsSearch($data,&$_filter)
    {
        $tags = $this->handlerTagsPolymorphic($data);
        // $tags = ;
        // var_dump($tags);
        if (count($data) == 1 ){
            $condition['tag'] = array('in',$tags);
            $condition['branch_id'] = getBrowseBranchId();
            $user_ids = M('SysUserRelationTag')->where($condition)->getField('user_id',true);
            if ($user_ids){
                $_filter["a.id"][] = array('in',$user_ids);
            }else{
                $_filter["a.id"] = 0;
            }
        } else {
            $user_ids = [];
            foreach ($data as $k => $v) {
                $user_ids = M('SysUserRelationTag')->where(['tag'=>['in',$v]])->getField('user_id',true);

                if ($user_ids){
                    $_filter["a.id"][] = array('in',$user_ids);
                }else{
                    $_filter["a.id"] = 0;
                }
            }
        }
    }

    public function handlerTagsPolymorphic($data)
    {
        if(!$data)
        {
            return [];
        }
        $count = count($data);
        $tags_parse = [];
        if( $count == 1 )
        {
            $inc_data = array_shift($data);
            return isset($inc_data) ? $inc_data : $data;
        }
        else
        {
            for ($i = array_keys($data)[0] ; $i < max(array_keys($data)) ; $i ++)
            {
                if($i == array_keys($data)[0])
                {
                    $tip = $data[$i];
                }
                else
                {
                    $tip = $tags_parse;
                }
                $temp = [];
                for ( $a = 0 ; $a < count($tip); $a++)
                {
                    for ( $n = 0 ; $n < count($data[$i+1]) ; $n ++ )
                    {
                        $temp[] = $tip[$a] . ',' . $data[ $i + 1 ][$n];
                    }
                }
                $tags_parse = $temp;
            }
            return $tags_parse;
        }
    }

    //禁用员工
    public function disableStaffAction(){
        $id = I("post.id");
        $staff = M("SysUser")->where("id = $id")->field("is_valid,user_type")->find();
        if($id == $this->_user_session->userId){
            $this->ajaxReturn(array("error"=>1,"message"=>"您不能禁用您自己！"));
        }
        if($staff['user_type'] == 2 && $staff['is_valid'] == 1){
            $result = M("SysUser")->where("id = $id")->setField("is_valid",0);
            if($result){
                $this->ajaxReturn(array("error"=>0,"message"=>"禁用成功！"));
            }else{
                $this->ajaxReturn(array("error"=>1,"message"=>"禁用失败！"));
            }
        }else{
            $this->ajaxReturn(array("error"=>1,"message"=>"禁用失败！"));
        }
    }

    //启用员工
    public function enableStaffAction(){
        $id = I("post.id");
        $staff = M("SysUser")->where("id = $id")->field("is_valid,user_type")->find();
        if($staff['user_type'] == 2 && $staff['is_valid'] == 0){
            $result = M("SysUser")->where("id = $id")->setField("is_valid",1);
            if($result){
                $this->ajaxReturn(array("error"=>0,"message"=>"启用成功！"));
            }else{
                $this->ajaxReturn(array("error"=>1,"message"=>"启用失败！"));
            }
        }else{
            $this->ajaxReturn(array("error"=>1,"message"=>"启用失败！"));
        }
    }

    public function disableAccountAction(){
        $this->display();
    }

    public function enableAccountAction(){
        $this->display();
    }

    protected function _before_write($type, &$data) {

        if($data['id'] == ""){
            $this->ajaxReturn(array("error"=>1,"message"=>"请选择一位用户！"));
        }
        $user_type = M("SysUser")->where("id = ".$data['id'])->getField("user_type");
        if($data["role_ids"] != 2){
            $data["role_ids"] = implode(",", $data["role_ids"]);
        }
        if($data["role_ids"] == ""){
            $this->ajaxReturn(array("error"=>1,"message"=>"角色不能为空！"));
            //$this->ajaxReturn(buildMessage("123",1));
        }
        if($data['deptment_id'] == 0){
            //I("post.deptment_id") = $this->_user_session->currBranchId;
        }
        //新增员工保存发送邀请通知
        if($user_type != $data['user_type']){
            $condition['id'] = array("neq",$data['id']);
            $condition['branch_id'] = $this->_user_session->currBranchId;
            $condition['mobile'] = $data['mobile'];
            $result = M("SysUser")->where($condition)->find();
            if($result){
                $this->ajaxReturn(array("code"=>1,"message"=>"手机号已存在"));
            }
            $this->sendInviteAction($data['id'],$data['mobile']);
        }
        parent::_before_write($type, $data);
    }

    public function sendInviteAction($id,$mobile){
        /*$id = I("post.id");
        $mobile = I("post.mobile");*/
        $user = [];
        if($id){
            $user[] = M("SysUser")->where("id = $id")->field("id,name,openid")->find();
            $result = A("ComFans")->handlerSendTemplateMessageForNotice($user,$mobile,"addStaff");
            //$this->ajaxReturn($result);
        }
    }

    public function _before_detail(&$data) {
        /*if($data['director_id']){
            $user = M("SysUser")->where("id = ".$data['director_id'])->field("staff_name,name")->find();
            $data['director_name'] = $user['staff_name'] != "" ? $user['staff_name'] : $user['name'];
        }*/
        if($data['telephone'] == ""){
            $data['telephone'] = $data['mobile'];
        }
        if($data['staff_name'] == ""){
            $data['staff_name'] = $data['name'];
        }
        $dept = M("SysUserBranch a")->join("sys_branch b on a.branch_id = b.id")->where("a.user_id = ".$data['id']." and a.type = 2")->field("b.name,b.id")->find();
        if(!$dept){
            //$data["dept_name"] = M("SysBranch")->where("id = ".$data['branch_id'])->getField("name");
            $data["dept_name"] = "无部门";
            $data["deptment_id"] = $this->_user_session->currBranchId;
        }else{
            $data["dept_name"] = $dept["name"];
            $data["deptment_id"] = $dept["id"];
        }
        if($this->_user_session->userId == $data['id'] or $data['is_leader']){
            $this->assign("edit_dac",2);
        }else{
            $this->assign("edit_dac",1);
        }
        parent::_before_detail($data);
    }

    public function set_dac_branchsAction(){
        $this->display("dac_branchs");
    }

    public function set_dac_usersAction(){
        $this->display("dac_users");
    }

    public function select_deptAction(){
        $this->branch_id = $this->_user_session->currBranchId;
        $this->display("select_dept");
    }

}