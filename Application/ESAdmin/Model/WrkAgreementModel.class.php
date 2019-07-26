<?php

namespace ESAdmin\Model;
use Common\Lib\Model\ComplexDataModel;


class WrkAgreementModel extends ComplexDataModel {

    protected $_createrField = "creater_id"; //创建人字段，如果是客户，可以设置成客户对应的字段
    protected $_leaderField = "leader_id";//负责人字段
    protected $_visiblersField = "visiblers"; //可见人字段
    protected $_collaboratorsField = "collaborators"; //协作人，如果原有此字段，就设置，没有就不需要
    protected $_invisiblersField = "invisiblers"; //不可见人字段

    protected $_link = array(
        "WrkInvoicePlan" => array(
            "join_name"=>"INNER",
            "class_name"=>"WrkInvoicePlan",
            "foreign_key"=>"id",
            "mapping_name"=>"wip",
            "mapping_fields"=>"state,amount_paid,leader_id,visiblers,is_sendwx,type",
            "mapping_key"=>"agreement_id"
        ),
        "WrkReceivables" => array(
            "join_name"=>"LEFT",
            "class_name"=>"WrkReceivables",
            "foreign_key"=>"id",
            "mapping_name"=>"wr",
            "mapping_fields"=>"status",
            "mapping_key"=>"contract_id"
        )
    );

    protected function _before_delete($options)
    {
        foreach ($options["where"]['id'][1] as $id){
            $agreement = M("WrkAgreement")->where("id = $id")->field("state,leader_id")->find();
            if($agreement['state'] != 0){
                E("已激活合同不能删除！");
                return false;
            }
        }
        parent::_before_delete($options);
    }

    protected function _after_delete($data, $options) {
        $plan_id = M("WrkInvoicePlan")->where("agreement_id = ".$data['id'][1][0])->getField("id");
        M("WrkInvoicePlan")->where("id = $plan_id")->delete();
        M("WrkInvoicePlanDetail")->where("plan_id = $plan_id")->delete();
    }

    protected function _after_insert($data, $options) {
        $this->addInvoicePlan($data);
        parent::_after_insert($data, $options);
    }

    //添加无计划开票工作
    public function addInvoicePlan($data,$id=""){
        $tmp['branch_id'] = $data['branch_id'];
        $tmp['type'] = 0;
        //$tmp['leader_id'] = M("SysBranch")->where("id = ".$data['branch_id'])->getField("leader_id");
        $tmp['agreement_id'] = $data['id'];
        $tmp['create_time'] = time();
        $tmp['attach_group'] = genUniqidKey();
        if($id != ""){
            $tmp['agreement_id'] = $id;
        }
        /*if($data['invoice_type'] != 0){
            $tmp["creater_id"] = $data['creater_id'];
        }*/
        $tmp["is_sendWX"] = 1;
        M("WrkInvoicePlan")->add($tmp);
    }

    //修改合同价格发送通知给客户
    public function sendWXUpdateMoneyMessage($id,$money){
        $templateId = getWxTemplateIdByStandardId("OPENTM414006304");
        if(!$templateId){
            return false;
        }
        $agreement = M("WrkAgreement a")
            ->join("sys_branch b on a.branch_id = b.id")
            ->where("a.id = $id")->field("a.company_id,a.branch_id,b.name as originator,a.name")->find();
        $openid = [];
        //合同客户负责人
        $customer = $this->getCustomerUserData(CONTROLLER_NAME,$agreement['company_id']);
        foreach ($customer as $k => $v){
            $openid[] = $v['openid'];
        }
        $data['invoice_id'] = $agreement['name']; //合同名称
        $data['invoice_day'] = $agreement['originator']; // 发起人（商户名称）
        $data['invoice_sum'] = date("Y-m-d H:i"); //变更内容
        $data['remark'] = "合同金额修改为：".$money;
        //合同商户通知人
        $notifiers = $this->getModuleNotifiersOpenid(CONTROLLER_NAME,$agreement['company_id']);
        if($notifiers){
            $data['url'] = str_replace('shop','shop'.$agreement['branch_id'],SHOP_ROOT)."/WrkAgreement/detail.html?id=$id" ;
            $data['first'] = getWxTemplateCurrencyTip("branch_agreement_update_money_notice");
            $data['openid'] = array_column($notifiers,"openid");
            D("WrkInvoicePlan")->handlerWXSendInvoiceData($data,$templateId);
        }
        $data['first'] = getWxTemplateCurrencyTip("agreement_update_money_notice");
        $data['url'] = str_replace('shop','shop'.$agreement['branch_id'],SHOP_ROOT)."/ComAgreement/detail.html?id=$id" ;
        $data['openid'] = array_unique($openid);
        if($data['openid'] != ""){
            return D("WrkInvoicePlan")->handlerWXSendInvoiceData($data,$templateId);
        }
    }

    public function getAgreementLeaderOpenid($company_id){
        $condition['object_various']= CAJ_BRANCH_AGREEMENT ;
        $condition['company_id']= $company_id;
        $condition['object_type']= 1 ;
        $openid = [];
        $branch = M("ComAccountJurisdiction")->where($condition)->field("leader_id,visiblers")->find();
        if($branch['visiblers']){
            $visiblers = explode(",",$branch['visiblers']);
            if($visiblers){
                foreach ($visiblers as $k=>$v){
                    $openid[] = M("SysUser")->where("id = ".$v)->getField("openid");
                }
            }
        }
        /*if($branch['leader_id']){
            $openid[] = M("SysUser")->where("id = ".$branch['leader_id'])->getField("openid");
        }*/
        return $openid;
    }

    //获取模块通知人openid
    public function getModuleNotifiersOpenid($module,$company_id){
        $condition['a.module'] = $module;
        $condition['a.company_id'] = $company_id;
        $condition['a.branch_id'] = getBrowseBranchId();
        $condition['a.permit_value'] = DAC_PERMIT_VALUE_NOTICER;//通知人
        $condition['a.type'] = DAC_SETTING_TYPE_BRANCH;
        $notifiers = M("SysUserModuleSetting a")->join("sys_user b on a.user_id = b.id")->where($condition)->field("b.openid")->select();
        return $notifiers;
    }

    //获取客户端模块人员
    public function getCustomerUserData($module,$company_id){
        $condition['a.module'] = $module;
        $condition['a.company_id'] = $company_id;
        $condition['a.branch_id'] = getBrowseBranchId();
        $condition['a.permit_value'] = DAC_PERMIT_VALUE_NOTICER;
        $condition['a.type'] = DAC_SETTING_TYPE_CUSTOMER;
        $result = [];
        if($module && $company_id){
            $result = M("SysUserModuleSetting a")
                ->join("sys_user b on a.user_id = b.id")
                ->field("b.id,b.name,b.staff_name,b.openid")
                ->where($condition)->select();
            //如果未设置此模块人员 则返回客户档案绑定的客户管理员
            if(!$result){
                $result = M("SysBranch a")
                    ->join("sys_user b on a.customer_leader_id = b.id")
                    ->where("a.id = $company_id")->field("b.id,b.staff_name,b.name,b.openid")->select();
            }
        }
        return $result;
    }

    //获取合同实际结束日期
    public function getActualFinishTime($data){
        if($data['state'] == 3){
            $condition['func'] = CONTROLLER_NAME;
            $condition['operation'] = "finishAgreement";
            $condition['content'] = $data['id'];
            $end_time = M("SysLog")->where($condition)->getField("create_time");
            return empty($end_time) ? "/" : date("Y/m/d",$end_time);
        }else{
            return "-";
        }
    }
}