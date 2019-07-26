<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class ComAccountJurisdictionModel extends DataModel {
    const OBJECT_TYPE_FOR_COMPANY = 1;
    const OBJECT_TYPE_FOR_USER = 2;
    protected $jurisdiction = [];//存放权限数据
    protected $object_various = [];//对象多样性存放
    protected $object_id = 0 ;//对象id
    protected $object_type = self::OBJECT_TYPE_FOR_COMPANY;//1 公司 2 个人
    protected $store = []; //数据商店
    protected $corresponding = [];//various的数据对应
    protected $options = [];
    protected $_login_user;

    public function _initialize()
    {
        $this->handlerVariousViewString();
        if (session('user_id') > 0){
            $this->_login_user = session('user_id');
        } else {
            $_session_user = session(USER_SESSION_KEY);
            $this->_login_user = $_session_user->userId;
        }
    }

    public function setObjectVarious(array $value){
        foreach($value as $key =>$val) {
            $value[$key] = strtolower($val);
        }
        $this->object_various = $value;
    }
    public function setObjectId( $value){
        $this->object_id = $value;
    }
    public function setObjectType( $value){
        $this->object_type = $value;
    }
    public function setCorresponding($key,$value){
        $this->corresponding[$key] = $value;
    }
    public function setOptions($key,$value)
    {
        $this->options[$key] = $value;
    }

    public function resetJurisdiction(){
        $this->jurisdiction = [];
    }
    public function getStore(string $value) {
        return $this->store[$value];
    }
    public function getJurisdiction() {
        return $this->jurisdiction;
    }

    public function saveAccountJurisdiction()
    {
        if ($this->object_various) {
            foreach ($this->object_various as $key => $value) {
                if ($this->options['object_type'] == self::OBJECT_TYPE_FOR_USER) {
                    $condition['user_id'] = $this->options['object_id'];
                } else {
                    $condition['company_id'] =  $this->options['object_id'];
                }
                $condition['object_various'] = $value;
                $condition['object_type'] = $this->options['object_type'];
                $condition['branch_id'] = getBrowseBranchId();
                $aj = M('com_account_jurisdiction')->where($condition) ->find();
                if ($aj) {
                    $action_data['updated_at'] = time();
                    $action_data['leader_id'] = $this->nullCalculation($this->options[$value]['leader_id'] , null);
                    $action_data['visiblers'] = $this->options[$value]['visiblers'];
                    $result = M('com_account_jurisdiction')->data($action_data)->where('id = '.$aj['id'])->save();
                } else {
                    $action_data['leader_id'] = $this->nullCalculation($this->options[$value]['leader_id'] , null);
                    $action_data['visiblers'] = $this->nullCalculation($this->options[$value]['visiblers'] , null);
                    $action_data['object_various'] = $value;
                    $action_data['object_type'] = $this->options['object_type'];
                    $action_data['company_id'] = $this->options['object_type'] == self::OBJECT_TYPE_FOR_COMPANY ? $this->options['object_id'] : null;
                    $action_data['user_id'] = $this->options['object_type'] == self::OBJECT_TYPE_FOR_USER ? $this->options['object_id'] : null;
                    $action_data['branch_id'] = getBrowseBranchId();
                    $action_data['updated_at'] = time();
                    $action_data['created_at'] = time();
                    $result = M('com_account_jurisdiction')->data($action_data)->add();
                }
            }
            return $result;
        } else {
            return false;
        }

    }
    // object_id 对象id
    // object_type 对象类型
    // object_various 对象的多姿多彩
    // default 对象是否带入默认值 false 全部拒绝 true 全部带入 array 指向性带入
    public function getAccountSystemManage()
    {
        //获取账户管理资料
        $condition['object_type'] = $this->object_type;
        if ($this->object_type == self::OBJECT_TYPE_FOR_USER) {
            $condition['user_id'] = $this->object_id;
        } else {
            $condition['company_id'] =  $this->object_id;
        }
        $condition['branch_id'] = getBrowseBranchId();
        $condition['object_various'] = array('in',$this->object_various);
        $result = M('ComAccountJurisdiction')->where($condition)->select();
        //$jurisdiction 转化
        if ($result) {
            foreach ($result as $key=>$val) {
                if (isset($this->corresponding[$val['object_various']])) {
                    $this->jurisdiction[$this->corresponding[$val['object_various']].'_leader_id'] =  $this->nullCalculation($val['leader_id'] , null);
                    $this->jurisdiction[$this->corresponding[$val['object_various']].'_visiblers'] =  $this->nullCalculation($val['visiblers'] , null);
                } else {
                    $this->jurisdiction[$val['object_various'].'_leader_id'] =  $this->nullCalculation($val['leader_id'] , null);
                    $this->jurisdiction[$val['object_various'].'_visiblers'] =  $this->nullCalculation($val['visiblers'] , null);
                }
            }
        } else {
            foreach ($this->object_various as $key=>$val) {
                if (isset($this->corresponding[$val['object_various']])) {
                    $this->jurisdiction[$this->corresponding[$val['object_various']].'_leader_id'] =  null;
                    $this->jurisdiction[$this->corresponding[$val['object_various']].'_visiblers'] =  null;
                } else {
                    $this->jurisdiction[$val.'_leader_id'] =  null;
                    $this->jurisdiction[$val.'_visiblers'] =  null;
                }
            }
        }
    }
    public function handlerAccountCapitalJurisdiction($field = [])
    {
        if ($field === []) {
            $field = [CAJ_BRANCH_RECHARGE,CAJ_BRANCH_WITHDRAWAL,CAJ_BRANCH_CUSTOMER_CAPITAL];
        }
        $branch_leader = M('SysBranch')->field('leader_id')
                                        ->where('id = '.getBrowseBranchId())->find();
        if ($this->object_type == self::OBJECT_TYPE_FOR_USER) {
            $custorm_manager['customer_leader_id'] = $this->object_id;
//            $custorm_manager['customer_visiblers'] = $this->object_id;
        } else {
            $qualification['branch.parent_id'] = getBrowseBranchId();
            $qualification['branch.id'] = $this->object_id;
            $custorm_manager =  M('SysBranch')->alias('branch')
                                              ->field('user.id')
                                              ->join('inner join sys_user as user on user.id=branch.customer_leader_id')
                                              ->where($qualification)->find();
            $custorm_manager['customer_leader_id'] = $this->nullCalculation($custorm_manager['id'] , null);
            $custorm_manager['customer_visiblers'] = null;
        }
        if (in_array(CAJ_BRANCH_RECHARGE,$field)) {
            $condition['module'] = 'ComRecharge';
            $condition['branch_id'] = getBrowseBranchId();
            $condition['permit_value'] = 8;
            $condition["type"] = DAC_SETTING_TYPE_BRANCH;
            $recharge_leader = M("SysUserModuleSetting")->where($condition)->find();
            $leader_default = $branch_leader['leader_id'];
            if ($recharge_leader) {
                $leader_default = $recharge_leader['user_id'];
            }
            $this->store['capital'][$this->corresponding[CAJ_BRANCH_RECHARGE].'_leader_id'] = $this->nullCalculation($this->jurisdiction[$this->corresponding[CAJ_BRANCH_RECHARGE].'_leader_id'] , $leader_default);
            $this->store['capital'][$this->corresponding[CAJ_BRANCH_RECHARGE].'_visiblers'] = $this->nullCalculation($this->jurisdiction[$this->corresponding[CAJ_BRANCH_RECHARGE].'_visiblers'] , null);
        } else {
            $this->store['capital'][$this->corresponding[CAJ_BRANCH_RECHARGE].'_leader_id'] = $this->nullCalculation($this->jurisdiction[$this->corresponding[CAJ_BRANCH_RECHARGE].'_leader_id'] , null);
            $this->store['capital'][$this->corresponding[CAJ_BRANCH_RECHARGE].'_visiblers'] = $this->nullCalculation($this->jurisdiction[$this->corresponding[CAJ_BRANCH_RECHARGE].'_visiblers'] , null);
        }
        if (in_array(CAJ_BRANCH_WITHDRAWAL,$field)) {
            $condition['module'] = 'ComWithdrawal';
            $condition['branch_id'] = getBrowseBranchId();
            $condition['permit_value'] = 8;
            $condition["type"] = DAC_SETTING_TYPE_BRANCH;
            $recharge_leader = M("SysUserModuleSetting")->where($condition)->find();
            $leader_default = $branch_leader['leader_id'];
            if ($recharge_leader) {
                $leader_default = $recharge_leader['user_id'];
            }
            $this->store['capital'][$this->corresponding[CAJ_BRANCH_WITHDRAWAL].'_leader_id'] = $this->nullCalculation($this->jurisdiction[$this->corresponding[CAJ_BRANCH_WITHDRAWAL].'_leader_id'] , $leader_default);
            $this->store['capital'][$this->corresponding[CAJ_BRANCH_WITHDRAWAL].'_visiblers'] = $this->nullCalculation($this->jurisdiction[$this->corresponding[CAJ_BRANCH_WITHDRAWAL].'_visiblers'] , null);
        } else {
            $this->store['capital'][$this->corresponding[CAJ_BRANCH_WITHDRAWAL].'_leader_id'] = $this->nullCalculation($this->jurisdiction[$this->corresponding[CAJ_BRANCH_WITHDRAWAL].'_leader_id'] , null);
            $this->store['capital'][$this->corresponding[CAJ_BRANCH_WITHDRAWAL].'_visiblers'] = $this->nullCalculation($this->jurisdiction[$this->corresponding[CAJ_BRANCH_WITHDRAWAL].'_visiblers'] , null);
        }
        if (in_array(CAJ_BRANCH_CUSTOMER_CAPITAL,$field)){
            $this->store['capital'][$this->corresponding[CAJ_BRANCH_CUSTOMER_CAPITAL].'_leader_id'] = $this->nullCalculation($this->jurisdiction[$this->corresponding[CAJ_BRANCH_CUSTOMER_CAPITAL].'_leader_id'],$custorm_manager['customer_leader_id']);
            $this->store['capital'][$this->corresponding[CAJ_BRANCH_CUSTOMER_CAPITAL].'_visiblers'] = $this->nullCalculation($this->jurisdiction[$this->corresponding[CAJ_BRANCH_CUSTOMER_CAPITAL].'_visiblers'],$custorm_manager['customer_visiblers']);
        } else {
            $this->store['capital'][$this->corresponding[CAJ_BRANCH_CUSTOMER_CAPITAL].'_leader_id'] = $this->nullCalculation($this->jurisdiction[$this->corresponding[CAJ_BRANCH_CUSTOMER_CAPITAL].'_leader_id'] ,null);
            $this->store['capital'][$this->corresponding[CAJ_BRANCH_CUSTOMER_CAPITAL].'_visiblers'] = $this->nullCalculation($this->jurisdiction[$this->corresponding[CAJ_BRANCH_CUSTOMER_CAPITAL].'_visiblers'] ,null);
        }
    }

    //获取账户列表 various 必填
    public function getVariousBelongAccount($condition){
        if ($this->object_various){
            $condition['object_various'] = $this->object_various[0];
            if ($this->object_type){
                $condition['object_type'] = $this->object_type;
            }
            if ($this->object_id) {
                if ($this->object_type == self::OBJECT_TYPE_FOR_COMPANY) {
                    $condition['company_id'] = $this->object_id;
                } elseif($this->object_type == self::OBJECT_TYPE_FOR_USER) {
                    $condition['user_id'] = $this->object_id;
                } else {
                    return false;
                }
            }
            return $this->field('company_id,user_id,object_type')->where($condition)->select();
        } else {
            return false;
        }
    }
    //
    // 设置 options['jurisdiction'] 参数 - leader 负责 visiblers 可见 - 即输出类型
    // 设置 object_various 多样化类型 数组 例如 ['comrecharge','comwithdrawal'] 得出两种类型符合条件的用户/公司
    // 赋值 $this->store['jurisdiction']  all - 商户话事人  / false - 不符合条件 / array [user/company] [various] 分别为用户/公司符合条件
    public function getLoginUserJurisdiction(){
        if ($this->object_various) {
            //判断是否是商户话事人
            $branch_id = getBrowseBranchId();
            $is_branch_leader = M('SysBranch')->where('id = '.$branch_id.' and leader_id = '.$this->_login_user)->find();
            $where['module'] = array("in",$this->object_various);
            $where['branch_id'] = getBrowseBranchId();
            $where['permit_value'] = DAC_PERMIT_VALUE_LEADER;
            $where["type"] = DAC_SETTING_TYPE_BRANCH;
            $is_setting_leader = M("SysUserModuleSetting")->where($where)->find();
            if ($is_branch_leader || $is_setting_leader) {
                $this->store['jurisdiction'] = 'all';
            } else if (in_array($this->options['jurisdiction'],['leader','visiblers'])){
                if ($this->options['jurisdiction'] == 'leader') {
                    $condition['_string'] = ' leader_id  = '.$this->_login_user;
                    $where['permit_value'] = DAC_PERMIT_VALUE_LEADER;
                } else if ($this->options['jurisdiction'] == 'visiblers') {
                    $condition['_string'] = ' leader_id  = '.$this->_login_user.' or FIND_IN_SET('.$this->_login_user.',visiblers)';
                    $where['permit_value'] = array("egt",DAC_PERMIT_VALUE_VISIBLER);
                }
                $condition['branch_id'] = $branch_id;
                $condition['object_various'] = array('in',$this->object_various);
                $jurisdiction = $this->field('company_id,user_id,object_type,object_various')->where($condition)->select();
                $users = [];
                $companys = [];
                if ($jurisdiction) {
                    foreach ($jurisdiction as $key=>$value) {
                        if ($value['object_type'] == self::OBJECT_TYPE_FOR_COMPANY) {
                            $companys[$value['object_various']][] = $value['company_id'];
                        } else {
                            $users[$value['object_various']][] = $value['user_id'];
                        }
                    }
                }
                if (empty($companys) && empty($users)) {
                    $this->store['jurisdiction'] = false;
                } else {
                    $this->store['jurisdiction']['companys'] = $this->nullCalculation($companys,null);
                    $this->store['jurisdiction']['users'] = $this->nullCalculation($users,null);
                }
                //设置在SysUserModuleSetting中的充值或提现负责人，则能看到所有
                $where['branch_id'] = $branch_id;
                $where['module'] = array('in',$this->object_various);
                $where['type'] = DAC_SETTING_TYPE_BRANCH;
                $where['user_id'] = $this->_login_user;
                $module_setting = M("SysUserModuleSetting")->field('company_id,user_id')->where($where)->select();
                if($module_setting){
                    $this->store['jurisdiction'] = 'all';
                }
            } else {
                $this->store['jurisdiction'] = false;
            }
        }
    }
    //判断是否是该账户的leader
    // 传参 single 是否是单个
    // 设置 object_various object_type object_id
    // 赋值 store['has_leader'] 商户话事人 true / 不符合 false / 多个 array [$various] true/false
    public function handlerHasAccountLeader($single = true)
    {
        if ($this->object_various) {
            //判断是否是商户话事人
            $branch_id = getBrowseBranchId();
            $is_branch_leader = M('SysBranch')->where('id = '.$branch_id.' and leader_id = '.$this->_login_user)->find();
            $where['module'] = array("in",$this->object_various);
            $where['branch_id'] = getBrowseBranchId();
            $where['permit_value'] = DAC_PERMIT_VALUE_LEADER;
            $where["type"] = DAC_SETTING_TYPE_BRANCH;
            $is_setting_leader = M("SysUserModuleSetting")->where($where)->find();
            if ($is_branch_leader || $is_setting_leader) {
                $this->store['has_leader'] = true;
            } else if(in_array($this->object_type,[self::OBJECT_TYPE_FOR_USER,self::OBJECT_TYPE_FOR_COMPANY])){
                if ($this->object_type == self::OBJECT_TYPE_FOR_USER) {
                    $condition['user_id'] = $this->object_id;
                } else if ($this->object_type == self::OBJECT_TYPE_FOR_COMPANY){
                    $condition['company_id'] = $this->object_id;
                }
                $condition['object_type'] = $this->object_type;
                $condition['leader_id'] = $this->_login_user;
                $condition['branch_id'] = getBrowseBranchId();
                $condition['object_various'] = $single === true ? $this->object_various[0] : array('in',$this->object_various);
                $aj = $this->where($condition)->getField('object_various',true);
                if ($aj) {
                    if ($single === true) {
                        $this->store['has_leader'] = true;
                    } else {
                        foreach ($this->object_various as $key => $value){
                            $this->store['has_leader'][$value] = in_array($value,$aj) ? true : false;
                        }
                    }
                } else {
                    $this->store['has_leader'] = false;
                }
            } else {
                $this->store['has_leader'] = false;
            }
        }
    }
    //判断是否是该公司的leader - 客户专用
    // 传参 single 是否是单个
    // 设置 object_various object_type object_id
    // 赋值 store['has_leader'] 商户话事人 true / 不符合 false / 多个 array [$various] true/false
    public function handlerHasCompanyLeader($single = true)
    {
        if ($this->object_various) {
            //判断是否是商户话事人
            $is_branch_leader = M('SysBranch')->where('id = '.$this->object_id.' and customer_leader_id = '.$this->_login_user)->find();
            if ($is_branch_leader) {
                $this->store['has_leader'] = true;
            } else if(in_array($this->object_type,[self::OBJECT_TYPE_FOR_COMPANY])){
                $condition['company_id'] = $this->object_id;
                $condition['object_type'] = $this->object_type;
                $condition['leader_id'] = $this->_login_user;
                $condition['branch_id'] = getBrowseBranchId();
                $condition['object_various'] = $single === true ? $this->object_various[0] : array('in',$this->object_various);
                $aj = $this->where($condition)->getField('object_various',true);
                if ($aj) {
                    if ($single === true) {
                        $this->store['has_leader'] = true;
                    } else {
                        foreach ($this->object_various as $key => $value){
                            $this->store['has_leader'][$value] = in_array($value,$aj) ? true : false;
                        }
                    }
                } else {
                    $this->store['has_leader'] = false;
                }
            } else {
                $this->store['has_leader'] = false;
            }
        }
    }
    // 客户专用
    // 设置 options['jurisdiction'] 参数 - leader 负责 visiblers 可见 - 即输出类型
    // 设置 object_various 多样化类型 数组 例如 ['comrecharge','comwithdrawal'] 得出两种类型符合条件的用户/公司
    // 赋值 $this->store['jurisdiction']  all - 商户话事人  / false - 不符合条件 / array [user/company] [various] 分别为用户/公司符合条件
    public function getLoginUserHasCompanyVisiblers(){
        if ($this->object_various) {
            //判断是否是商户话事人   客户负责人是customer_leader_id
            $is_branch_leader = M('SysBranch')->where('id = '.$this->object_id.' and customer_leader_id = '.$this->_login_user)->find();
            if ($is_branch_leader) {
                $this->store['jurisdiction'] = true;
            } else{
                $condition['object_type'] = 1;
                $condition['object_id'] = $this->object_id;
                $condition['_string'] = ' leader_id  = '.$this->_login_user.' or FIND_IN_SET('.$this->_login_user.',visiblers)';
                $condition['branch_id'] = getBrowseBranchId();
                $condition['object_various'] = array('in',$this->object_various);
                $jurisdiction = $this->where($condition)->find();
                if (empty($jurisdiction)) {
                    $this->store['jurisdiction'] = false;
                } else {
                    $this->store['jurisdiction'] = true;
                }
            }
        }
    }
    //获取资金账户可见人 设置object_id
    public function getAccountNoticeSendUsers($name,$has_leader = true,$openid = true)
    {
        if ($this->object_id > 0 && $this->object_type && $this->object_various) {
            $Visiblers= [];
            //获取账户管理资料
            $condition['object_type'] = $this->object_type;
            if ($this->object_type == self::OBJECT_TYPE_FOR_COMPANY) {
                $condition['company_id'] =  $this->object_id;
            } else {
                $condition['user_id'] =  $this->object_id;
            }
            $condition['branch_id'] = getBrowseBranchId();
            $condition['object_various'] = $this->object_various[0];
            $result = M('ComAccountJurisdiction')->where($condition)->find();
            if ($result['leader_id'] > 0 && $has_leader) {
                $Visiblers[] =$result['leader_id'];
            }
            if (!empty($result['visiblers'])) {
                $temp = explode(',',$result['visiblers']);
                foreach ($temp as $key => $value){
                    $Visiblers[] = $value;
                }
            }
            if($this->object_type == self::OBJECT_TYPE_FOR_COMPANY && !$Visiblers && $name = 'user'){
                $Visiblers[] = M("SysBranch a")->where("a.id = ".$this->object_id)->getField('customer_leader_id');
            }
            if ($Visiblers) {
                if ($openid) {
                    $where['id'] = array('in',$Visiblers);
                    $this->store[$name.'_visiblers'] =  M('SysUser')->where($where)->distinct(true)->getField('openid',true);
                } else {
                    $this->store[$name.'_visiblers'] = $Visiblers;
                }
            } else{
                $this->store[$name.'_visiblers'] = false;
            }
        }
    }
    public function getAccountLeaderId($branch_leader = true)
    {
        if (count($this->object_various) == 1) {
            //获取账户管理资料
            $condition['object_type'] = $this->object_type;
            if ($this->object_type == self::OBJECT_TYPE_FOR_USER) {
                $condition['user_id'] = $this->object_id;
            } else {
                $condition['company_id'] =  $this->object_id;
            }
            $condition['branch_id'] = getBrowseBranchId();
            $condition['object_various'] = array('in',$this->object_various);
            $result = M('ComAccountJurisdiction')->field('leader_id')->where($condition)->find();
            if ($result && $result['leader_id'] > 0) {
                return $result['leader_id'];
            } else if ($branch_leader){
                $where['id'] = getBrowseBranchId();
                $result_branch = M('SysBranch')->field('leader_id')->where($where)->find();
                if ($result_branch && $result_branch['leader_id'] > 0) {
                    return $result_branch['leader_id'];
                } else {
                    return null;
                }
            } else {
                return null;
            }
        } else {
            return false;
        }

    }
    protected function handlerVariousViewString()
    {
        $this->corresponding = [
            CAJ_BRANCH_RECHARGE => 'recharge',
            CAJ_BRANCH_WITHDRAWAL => 'withdrawal',
            CAJ_BRANCH_CUSTOMER_CAPITAL => 'customer_capital'
        ];
    }
    protected function nullCalculation($object,$spare)
    {
        return empty($object) ? $spare : $object;
    }
}