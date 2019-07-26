<?php

namespace EShop\Model;

use Think\Model;

class ComAccountJurisdictionModel extends Model {
    const OBJECT_TYPE_FOR_COMPANY = 1;
    const OBJECT_TYPE_FOR_USER = 2;
    protected $jurisdiction = [];
    protected $object_various = [];
    protected $object_id = 0 ;
    protected $object_type = self::OBJECT_TYPE_FOR_COMPANY;//1 公司 2 个人
    protected $store = [];
    protected $corresponding = [];
    protected $options = [];

    public function _initialize()
    {
        $this->handlerVariousViewString();
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
    public function setObjectType(int $value){
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
    public function getVisiblersCompanys()
    {
        $visiblers = [];
        $condition['object_various'] = CAJ_BRANCH_CUSTOMER_CAPITAL;
        $condition['object_type'] = self::OBJECT_TYPE_FOR_COMPANY;
        $condition['branch_id'] = getBrowseBranchId();
        $condition['_string'] = 'leader_id = '.session('user_id') . ' or FIND_IN_SET('.session('user_id').',visiblers)';
        $companys  = $this->where($condition)->getField('company_id',true);
        if(session("user_type") == USER_TYPE_COMPANY_MANAGER){
            $where['leader_id'] = session('user_id');
        }else{
            $where['customer_leader_id'] = session('user_id');
        }
        $where['branch_id'] = getBrowseBranchId();
        $leaders  = M('SysBranch')->where($where)->getField('id',true);
        if ($companys){
            foreach ($companys as $key => $value){
                $visiblers[] = $value;
            }
        }
        if ($leaders){
            foreach ($leaders as $key => $value){
                $visiblers[] = $value;
            }
        }
        $this->store['visiblers'] = $visiblers;
    }
    public function getHasCompanyLeader()
    {
        $condition['object_various'] = CAJ_BRANCH_CUSTOMER_CAPITAL;
        $condition['object_type'] = self::OBJECT_TYPE_FOR_COMPANY;
        $condition['branch_id'] = getBrowseBranchId();
        $condition['company_id'] = $this->object_id;
        $condition['leader_id'] = session('user_id');
        $companys  = $this->where($condition)->find();
        if ($companys) {
            $this->store['has_leader'] = true;
        } else {
            $where['customer_leader_id'] = session('user_id');
            $where['branch_id'] = getBrowseBranchId();
            $where['id'] = $this->object_id;
            $leaders  = M('SysBranch')->where($where)->find();
            if ($leaders) {
                $this->store['has_leader'] = true;
            } else {
                $this->store['has_leader'] = false;
            }
        }
    }
    //
    // 设置 options['jurisdiction'] 参数 - leader 负责 visiblers 可见 - 即输出类型
    // 设置 object_various 多样化类型 数组 例如 ['comrecharge','comwithdrawal'] 得出两种类型符合条件的用户/公司
    // 赋值 $this->store['jurisdiction']  all - 商户话事人  / false - 不符合条件 / array [user/company] [various] 分别为用户/公司符合条件
    public function getLoginStaffJurisdiction(){
        if ($this->object_various) {
            //判断是否是商户话事人
            $branch_id = getBrowseBranchId();
            $login_staff = session('user_id');
            $is_branch_leader = M('SysBranch')->where('id = '.$branch_id.' and leader_id = '.$login_staff)->find();
            $where['module'] = array("in",$this->object_various);
            $where['branch_id'] = $branch_id;
            $where['permit_value'] = DAC_PERMIT_VALUE_LEADER;
            $where["type"] = DAC_SETTING_TYPE_BRANCH;
            $is_setting_leader = M("SysUserModuleSetting")->where($where)->find();
            if ($is_branch_leader || $is_setting_leader) {
                $this->store['jurisdiction'] = 'all';
            } else if (in_array($this->options['jurisdiction'],['leader','visiblers'])){
                if ($this->options['jurisdiction'] == 'leader') {
                    $condition['_string'] = ' leader_id  = '.$login_staff;
                    $where['permit_value'] = DAC_PERMIT_VALUE_LEADER;
                } else if ($this->options['jurisdiction'] == 'visiblers') {
                    $condition['_string'] = ' leader_id  = '.$login_staff.' or FIND_IN_SET('.$login_staff.',visiblers)';
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
                $where['user_id'] = $login_staff;
                $module_setting = M("SysUserModuleSetting")->field('company_id,user_id')->where($where)->select();
                if($module_setting){
                    $this->store['jurisdiction'] = 'all';
                }
            } else {
                $this->store['jurisdiction'] = false;
            }
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