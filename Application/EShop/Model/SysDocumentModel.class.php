<?php

namespace EShop\Model;

use Think\Model;


class SysDocumentModel extends DataModel {

    protected $_model =  [

    ];
    protected $_filter = ['is_valid' => 1];
    protected $user_branch;
    protected $target_branch;//用于分别用户和服务商登陆的branch_id
    protected $storage;

    public function _initialize()
    {
        $this->_model = (Object) $this->_model;
        $this->user_branch = getBrowseBranchId();
    }

    public function getDocumentLists($target)
    {
        $condition['a.is_valid'] = 1;
        if ($this->target_branch === null) {
            return [];
        }
        $condition['a.name'] = array("like","%".I("post.keyword")."%");
        $this->handlerConditionBranch($condition,$target == 'myBranch' ? 'me' : 'ta');
        $documents = $this->alias('a')->where($condition)->select();
        $ids = array_column($documents,"id");
        if(I("post.keyword") != "" && $ids){
            unset($condition['a.name']);
            //$id = implode(",",$ids);
            //$condition['_string'] = " and a.id not in ($id)";
            $condition['a.parent_id'] = array("in",$ids);
            $children = $this->alias('a')->where($condition)->select();
            foreach ($children as $child){
                array_push($documents,$child);
            }
            $documents['0']["ids"] = $ids;
        }
        return $documents;
    }
    public function newFolder($request)
    {
        $this->handlerConditionBranch($append,'me','');
        $append['parent_id'] = $request->pid;
        $append['name'] = $request->name;
        $append['type'] = $request->type;
        $append['user_id'] = session('user_id');
        $append['created_at'] = time();
        $append['updated_at'] = time();
        //添加权限
        $append['user_branch_id'] = getUserCompanyId();
        $append['creator_id'] = session('user_id');

        $result = $this->add($append);
        if ($result) {
            $append['id'] = $result;
            return ['error'=>0,'message'=>'文件夹添加成功','data'=>$append];
        } else {
            return ['error'=>1,'message'=>'文件夹添加失败'];
        }
    }
    public function rename($request)
    {
        $condition['id'] = $request->id;
        $save['name'] = $request->name;
        $save['updated_at'] = time();
        $result = $this->where($condition)->save($save);
        if ($result) {
            return ['error'=>0,'message'=>'文件名称修改成功'];
        } else {
            return ['error'=>1,'message'=>'文件名称修改失败'];
        }
    }
    public function delete($request)
    {
        $where['id'] = array('in',$request->ids);
        $files = $this->where($where)->select();
        $close = [];
        foreach($files as $key => $value) {
            $close[] = $value['id'];
            $this->handlerDeleteFile($value,$close);
        }
        $where['id'] = array('in',$close);
        $save['is_valid'] = 0;
        $save['updated_at'] = time();
        $save['deleted_at'] = time();
        $result = $this->where($where)->save($save);
        return $result ? ['error'=>0,'message'=>'文件删除成功'] : ['error'=>1,'message'=>'文件删除失败'];
    }
    public function getFirstCompanySingle($single,$id = 0)
    {
        $condition['parent_id'] = $this->user_branch;
        $condition['is_valid'] = 1;
        $condition['id'] = $id;
        return D('sysBranch')->field($single)->where($condition)->find();
    }
    public function getFirstUserBindCompanySingle($single,$id = 0)
    {
        $condition['user_branch.user_id'] = session('user_id');
        $condition['branch.is_valid'] = 1;
        $condition['id'] = $id;
        return D('sysBranch')
                ->alias('branch')
                ->field($single)
                ->join('sys_user_branch as user_branch on user_branch.branch_id = branch.id')
                ->where($condition)->find();
    }
    public function getMoveFolders($request)
    {
        if ($request->id > 0) {
            $checked = $this->where('id = '.$request->id ) -> find();
        } else {
            $checked = null;
        }
        $condition['a.type'] = 0;
        $this->handlerConditionBranch($condition);
        $_order = array("a.parent_id asc");
        $list = $this->alias('a')->field("a.id,a.parent_id,'closed' as state,a.name")->where($condition)->order($_order)->select();
        foreach ($list as $key => $value) {
            $list[$key]["parent_id"] = $value['parent_id'] ?? 0;
            if ($checked['parent_id'] == $value['id']) {
                $list[$key]['is_checked'] = false;
            } else {
                $list[$key]['is_checked'] = true;
            }
        }

        return ['list' => $list,'checked' => $checked];

    }
    public function moveFile($request)
    {
        $this->handlerConditionBranch($condition,'me','');
        $condition['id'] = $request->id;
        $save['parent_id'] = $request->pid;
        $save['updated_at'] = time();
        $result = $this->where($condition)->save($save);
        return $result ? ['error' => 0,'message' => '文件移动成功'] : ['error' => 1,'message' => '文件移动失败'];
    }

    protected function handlerDeleteFile($file,&$data)
    {
        $condition['parent_id'] = $file['id'];
        $result = $this->where($condition)->getField('id',true);
        if ($result) {
            foreach($result as $key =>$val) {
                $data[] = $val;
                $where['id'] = $val;
                $this->handlerDeleteFile($this->where($where)->find(),$data);
            }
        }
    }
    //验证
    public function ValidateNewFile($request)
    {
//        $condition['branch_id'] = $this->target_branch;
        $this->handlerConditionBranch($condition);
        $condition['a.parent_id'] = $request->pid;
        $condition['a.name'] = $request->name;
        $condition['a.type'] = $request->type;
        $count = $this->alias('a')->where($condition)->count();
        return $count > 0 ? ['error'=>1,'message'=>'名称重复'] : ['error'=>0];
    }
    public function ValidateRenameFile($request)
    {
//        $condition['branch_id'] = $this->target_branch;
        $this->handlerConditionBranch($condition);
        $condition['a.parent_id'] = $request->pid;
        $condition['a.id'] = array('neq',$request->id);
        $condition['a.name'] = $request->name;
        $condition['a.type'] = $request->type;
        $count = $this->alias('a')->where($condition)->count();
        return $count > 0 ? ['error'=>1,'message'=>'名称重复'] : ['error'=>0];
    }
    public function ValidateDeleteFile(&$request)
    {
        $this->handlerConditionBranch($condition);
        $condition['a.id'] = array('in',$request->ids);
        $result = $this->alias('a')->where($condition)->getField('id',true);
        if ($result) {
            $request->ids = $result;
        }
        return count($result) > 0 ? ['error'=>0] : ['error'=>1,'message'=>'数据出错,您没有权利删除所选文件'];
    }
    public  function ValidateUploadFile($request)
    {
//        $condition['branch_id'] = $this->target_branch;
        $this->handlerConditionBranch($condition);
        $condition['a.name'] = $request->name;
        $condition['a.type'] = 1;
        $condition['a.parent_id'] = $request->pid;
        $condition['a.is_valid'] = 1;
        $count = $this->alias('a')->where($condition)->count();
        if ($count > 0) {
            $condition['name'] = array('like',sprintf('%s(_)',$request->name));
            $overflow = $this->alias('a')->field('a.name')->where($condition)->order('a.name desc')->find();
            if ($overflow) {
                $reg= '/.*\(\D*(\d*).*/i';
                preg_match_all($reg,$overflow['name'],$want);
                return $request->name.'('.($want[1][0] + 1).')';
            } else {
                return $request->name.'(1)';
            }
        } else {
            return $request->name;
        }
    }
    public function ValidateMoveFile($request)
    {
//        $condition['branch_id'] = $this->target_branch;
        $this->handlerConditionBranch($condition);
        $condition['a.id'] = $request->id;
        $res = $this->alias('a')->where($condition)->find();

        if (!$res) {
            return ['error'=>1,'message'=>'文件不存在'];
        } else {
            if ($res['parent_id'] == $request->pid) {
                return ['error'=>1,'message'=>'不能移动到文件的原本目录'];
            }
            $condition['a.parent_id'] = $request->pid;
            $condition['a.name'] = $res['name'];
            $condition['a.type'] = $res['type'];
            unset($condition['a.id']);
            $result = $this->alias('a')->where($condition)->count();
            if ($result > 0) {
                return ['error'=>1,'message'=>'该文件已经有相同文件名的文件存在'];
            } else {
                return true;
            }
        }

    }

    public function setTargetBranch($inc){
        $this->target_branch = $inc;
    }
    protected function handlerConditionBranch(&$condition,$type='me',$alias = 'a')
    {
        if ($alias !== '') {
            $alias = $alias.'.';
        }
        if ( $this->hasBranchStaff() && $type == 'me') {
            $condition[$alias.'branch_id'] = $this->user_branch;
            $condition[$alias.'target_branch'] = $this->target_branch;
            $where['module'] = CONTROLLER_NAME;
            $where['branch_id'] = getBrowseBranchId();
            $where['company_id'] = $this->target_branch;
            $where['user_id'] = session('user_id');
            $where['type'] = DAC_SETTING_TYPE_BRANCH;;
            if (!$this->getHasManager()) {
                $company_ids = M("SysUserModuleSetting")->where($where)->getField('company_id',true);
                if ($company_ids) {
                    $condition[$alias.'target_branch'] = array(array('in',$company_ids),array('eq',$condition[$alias.'target_branch']),'and');
                } else {
                    $condition[$alias.'target_branch'] = -1;
                }
            }
        } else if(!$this->hasBranchStaff() && $type == 'me') {
            $condition[$alias.'branch_id'] = $this->target_branch;
            $condition[$alias.'target_branch'] = 0;
        } else if ($this->hasBranchStaff() && $type == 'ta') {
            $condition[$alias.'branch_id'] = $this->target_branch;
            $condition[$alias.'target_branch'] = 0;
            $where['module'] = CONTROLLER_NAME;
            $where['branch_id'] = getBrowseBranchId();
            $where['company_id'] = $this->target_branch;
            $where['user_id'] = session('user_id');
            $where['type'] = DAC_SETTING_TYPE_BRANCH;
            if (!$this->getHasManager()) {
                $company_ids = M("SysUserModuleSetting")->where($where)->getField('company_id',true);
                if (!$company_ids) {
                    $condition[$alias.'target_branch'] = -1;
                }
            }
        } else if(!$this->hasBranchStaff() && $type == 'ta') {
            $condition[$alias.'branch_id'] = $this->user_branch;
            $condition[$alias.'target_branch'] = $this->target_branch;
        }
    }
    protected function hasBranchStaff()
    {
        return session('user_type') == USER_TYPE_COMPANY_MANAGER ? true : false;
    }
    protected function _options_filter(&$options)
    {
        if ($this->_filter) {
            $this->addOptionsFilter($options, $this->_filter);
        }
        parent::_options_filter($options);
    }
    protected function addOptionsFilter(&$options, $otherCondition) {
        if (is_array($otherCondition)) {
            $alias = empty($options["alias"]) ? "" : $options["alias"] . ".";
            foreach ($otherCondition as $key => $value) {
                if(stripos($key, "_") ===  0){ //开头是_的表示是系统定义的特殊标识符，比如_string, _complex
                    $options["where"][$key] = $value;
                }else{
                    $options["where"][$alias . $key] = $value;
                }
            }
        }
    }
    //计算用户角色，1表示Leader管理员，2表示为管理员的角色,Leader是平台指定的，角色为ROLE_COMPANY_MANAGER
    private function getHasManager(){
        $result = false;
        if (session('user_type') == USER_TYPE_COMPANY_MANAGER) {
            $where['id'] = getBrowseBranchId();
            $where['leader_id'] = session('user_id');
            $where['parent_id'] = 1;
            if (M("SysBranch")->where($where)->count() > 0) {
                $result = true;
            } else {
                if (!empty(session("user_role"))) {
                    $condition["id"] = array("in", session("user_role"));
                    $condition["is_admin"] = 1;
                    if (M("SysRole")->where($condition)->count() > 0) {
                        $result = true;
                    }
                }
            }
        }
        return $result;
    }
}
