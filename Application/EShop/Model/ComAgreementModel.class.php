<?php
namespace EShop\Model;
use Think\Model;
class ComAgreementModel extends Model {

    protected $tableName = "wrk_agreement";
    protected $modules = ["WrkAgreement","WrkInvoicePlan","WrkReceivables","WxOperateTemplate","SocialSecurityNotice","WrkTaskPlan"];

    //获取客户绑定的公司
    public function getUserCompany($name){
        $condition = [];
        if(!empty($name)){
            $condition['branch.name'] = array("like","%$name%");
        }
        //$condition['user_branch.type'] = ORG_COMPANY;
        $condition['branch.type'] = ORG_COMPANY;
        $condition['branch.parent_id'] = session("branch_id");
        $condition['user_branch.user_id'] = session("user_id");
        $company = M("SysBranch branch")
            ->join("sys_user_branch user_branch on branch.id = user_branch.branch_id")
            ->field("branch.name as text,branch.id as value,branch.customer_leader_id")
            ->where($condition)->select();
        return $company;
    }

    public function getIsLeader($company_id){
        $leader_id = M("SysBranch")->where("id = $company_id")->getField("customer_leader_id");
        return $_SESSION['user_id'] == $leader_id ? 1:0;
    }

    //获取客户端工作模块设置人员
    public function getWorkSettingUsers($module = ""){
        $result = [];
        if($module == ""){
            $result['customercapital'] = $this->getCustomerCapitalUser();
        }
        $modules = $module == "" ? $this->modules : [$module];
        if($module == "customercapital"){
            return $this->getCustomerCapitalUser();
        }
        $customer_leader_id = M("SysBranch")->where(["id"=>$_SESSION['wrk_company_id']])->getField("customer_leader_id");
        if($customer_leader_id){
            $customer_leader = M("SysUser")->where("id = $customer_leader_id")->field("id as user_id,name,staff_name")->select();
        }
        $model = M("SysUserModuleSetting");
        foreach($modules as $k=>$module){
            $condition['a.module'] = $module;
            $condition['a.company_id'] = $_SESSION['wrk_company_id'];
            $condition['a.branch_id'] = getBrowseBranchId();
            $condition['a.permit_value'] = DAC_PERMIT_VALUE_NOTICER;
            $condition['a.type'] = DAC_SETTING_TYPE_CUSTOMER;
            $result[$module] = $model->alias("a")
                ->join("sys_user b on a.user_id = b.id")
                ->where($condition)->field("a.user_id,b.name,b.staff_name,$customer_leader_id as customer_leader_id")->order("b.id asc")->select();
            if(!$result[$module] && !empty($customer_leader)){
                $result[$module] = $customer_leader;
            }
        }
        return $result;
    }

    //获取客户端设置的资金负责人可见人信息
    public function getCustomerCapitalUser(){
        $condition['branch_id'] = getBrowseBranchId();
        $condition['company_id'] = $_SESSION['wrk_company_id'];
        $condition['object_type'] = 1;
        $condition['object_various'] = "customercapital";
        $capital_setting = M("ComAccountJurisdiction")
            ->where($condition)->field("leader_id,visiblers")->find();
        $result = [];
        if($capital_setting['leader_id']){
            $result['leader'] = M("SysUser")->where("id = ".$capital_setting['leader_id'])->field("id,name,staff_name")->select();
        }
        if($capital_setting['visiblers']){
            $result['visiblers'] = M("SysUser")->where("id in (".$capital_setting['visiblers'].")")->field("id,name,staff_name")->select();
        }
        $customer_leader_id = M("SysBranch a")->where("a.id = ".$condition['company_id'])->getField("customer_leader_id");
        if($customer_leader_id){
            $customer_leader = M("SysUser")->where("id = $customer_leader_id")->field("id,name,staff_name")->select();
        }
        if(!$result['leader'] && !empty($customer_leader)){
            //如果未设置资金负责人，则默认显示公司管理员
            $result['leader'] = $customer_leader;
        }
        if(!$result['visiblers'] && !empty($customer_leader)){
            //如果未设置资金可见人，则默认显示公司管理员
            $result['visiblers'] = $customer_leader;
        }
        return $result;
    }

    //获取客户端所有员工
    public function getStaffList(){
        $condition = [] ;
        $condition["a.branch_id"] = $_SESSION['wrk_company_id'] ;
        $condition["a.type"] = array("neq" , ORG_DEPARTMENT) ;
        $result = M("SysUserBranch a")
            ->join("sys_user b on a.user_id = b.id")
            ->join("sys_branch c on a.branch_id = c.id")
            ->field("b.staff_name,b.id,b.head_pic,c.customer_leader_id,b.name")
            ->where($condition)->select();
        return $result;
    }

    //处理客户端设置人员数据
    public function handlerModuleUser(){
        $module = I("post.module");
        $ids = I("post.ids");
        //删除之前的设置
        $condition['branch_id'] = getBrowseBranchId();
        $condition['company_id'] = $_SESSION['wrk_company_id'];
        $condition['type'] = DAC_SETTING_TYPE_CUSTOMER;
        $condition['module'] = $module;
        M("SysUserModuleSetting")->where($condition)->delete();
        //添加现在的设置
        $data = [];
        foreach ($ids as $id){
            $data[] = ["module"=>$module,"company_id"=>$_SESSION['wrk_company_id'],
                "branch_id"=>getBrowseBranchId(),"permit_value"=>DAC_PERMIT_VALUE_NOTICER,
                "user_id"=>$id,"type"=>DAC_SETTING_TYPE_CUSTOMER];
        }
        $result = M("SysUserModuleSetting")->addAll($data);
        return $result;
    }

    public function handlerCapitalUser(){
        $module = I("post.module");
        $pv = I("post.permit_value");
        $ids = I("post.ids");
        $where['branch_id'] = getBrowseBranchId();
        $where['company_id'] = $_SESSION['wrk_company_id'];
        $where['object_type'] = 1;
        $where['object_various'] = $module;
        $model = M("ComAccountJurisdiction");
        $setting = $model->where($where)->find();
        $setting['leader_id'] = $pv == DAC_PERMIT_VALUE_LEADER ? $ids[0] : $setting['leader_id'];
        /*if(in_array($setting['leader_id'],$ids) || in_array($setting['leader_id'],explode(",",$setting['visiblers']))){
            unset($ids[array_search($setting['leader_id'],$ids)]);
            unset($ids[array_search($setting['leader_id'],explode(",",$setting['visiblers']))]);
        }*/
        $setting['visiblers'] = $pv == DAC_PERMIT_VALUE_LEADER ? $setting['visiblers'] : implode(",",$ids);
        if($setting["id"]){
            $setting['updated_at'] = time();
            $result = $model->save($setting);
        }else{
            $where['leader_id'] = $setting['leader_id'];
            $where['visiblers'] = $setting['visiblers'];
            $where['created_at'] = time();
            $where['updated_at'] = time();
            $result = $model->add($where);
        }
        return $result;
    }



}