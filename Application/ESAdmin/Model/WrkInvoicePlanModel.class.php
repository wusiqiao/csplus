<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;
use Common\Lib\Model\ComplexDataModel;

class WrkInvoicePlanModel extends ComplexDataModel {
    //protected $_filter = ['type' =>array('eq','0')];//无计划

    protected $_leaderField = "leader_id";//负责人字段，在子类设置,一定要设置
    protected $_visiblersField = "visiblers"; //可见人字段，如果原有此字段，就设置，没有就不需要
    protected $_collaboratorsField = "collaborators"; //协作人，如果原有此字段，就设置，没有就不需要
    protected $_companyField = "company_id"; //公司字段，一定要设置

    protected function _options_filter(&$options) {
        /*if($options['where']['a.state'] == 2){
            $beginToday=mktime(0,0,0,date('m'),date('d'),date('Y'));
            $this->addOptionsFilter($options, array("ag*finish_time" =>array("lt",$beginToday)));
        }*/
        parent::_options_filter($options);
    }

    public function _after_update($data, $options)
    {
        parent::_after_update($data, $options); // TODO: Change the autogenerated stub
    }

    protected $_link = array(
        "WrkAgreement" => array(
            "join_name" => "INNER",
            'class_name' => "WrkAgreement",
            'foreign_key' => 'agreement_id',
            'mapping_name' => 'ag',
            'mapping_fields' => 'id,name,company_id,customer_leader_id,leader_id,agreement_sn,agreement_money,sys_sn,start_time,finish_time,visiblers',
            "mapping_key" => "id"
        ),
        "SysBranch" => array(
            "join_name" => "LEFT",
            'class_name' => "SysBranch",
            'foreign_key' => 'ag.company_id',
            'mapping_name' => 'branch',
            'mapping_fields' => 'id,name',
            "mapping_key" => "id"
        )
    );

    public function getCompanyLeader($company_id,$type){
        $con['a.object_various']= $type ;
        $con['a.company_id']= $company_id;
        $con['a.object_type']= 1 ;
        $tmp = M("ComAccountJurisdiction")
            ->alias("a")
            ->join("sys_user as b on a.leader_id = b.id")
            ->where($con)
            ->field("b.name as customer_leader,a.visiblers as customer_visiblers")->find();
        $data['customer_leader'] = $tmp['customer_leader'];
        if($tmp['customer_visiblers'] != ""){
            $visiblers = explode(",",$tmp['customer_visiblers']);
            foreach ($visiblers as $key=>$val){
                $tmp1 = M("SysUser")->where("id = ".$val)->field("name,id")->find();
                if($data['customer_visiblers'] == ""){
                    $data['customer_visiblers'] = $tmp1['name'];
                }else{
                    $data['customer_visiblers'] = $data['customer_visiblers'].";" . $tmp1['name'];
                }
            }
        }
        return $data;
    }

    public function getOpenId($id,$type){
        $agreement = M("WrkInvoiceRecord")
            ->alias("a")
            ->join("wrk_invoice_plan as b on a.plan_id = b.id")
            ->join("wrk_agreement as c on b.agreement_id = c.id")
            ->where("a.id = ".$id)
            ->field("c.company_id,b.is_sendwx")->find();
        if($agreement['is_sendwx'] != 1){
            return false;
        }
        $openid = [];
        //客户通知人
        $customer = D("WrkAgreement")->getCustomerUserData("WrkInvoicePlan",$agreement['company_id']);
        foreach ($customer as $k => $v){
            $openid[] = $v['openid'];
        }
        //商户通知人
        $branch_openid = [];
        $notifiers = D("WrkAgreement")->getModuleNotifiersOpenid("WrkInvoicePlan",$agreement['company_id']);
        if($notifiers){
            $branch_openid = array_column($notifiers,"openid");
        }
        return array("openid"=>array_unique($openid),"branch_openid"=>$branch_openid);
    }

    /*
     * $id type=1为作废的发票ids type=2 为开票记录
     * */
    public function sendWXInvoiceMessage($id,$type){
        $templateId = getWxTemplateIdByStandardId("OPENTM411013137");
        if(!$templateId){
            return false;
        }
        if($type == 1){
            //发票作废通知
            $ids = explode(",",$id);
            $data['remark'] = "点击查看详情";
            $tmp = $this->getOpenId($ids[0],1);
            foreach ($ids as $k=>$v){
                $result = M("WrkInvoiceRecord")->where("id = $v")->find();
                $data["invoice_id"] = $result['invoice_id'];
                $data["invoice_day"] = date("Y/m/d",$result['invoice_day']);
                $data["invoice_sum"] = $result['invoice_sum'];
                $data["openid"] = $tmp['openid'];
                $data["first"] = getWxTemplateCurrencyTip("cancel_invoice_notice");
                if($data['openid'] != ""){
                    $agreement_id = M("WrkInvoicePlan")->where("id = ".$result['plan_id'])->getField("agreement_id");
                    $data['url'] = str_replace('shop','shop'.$result['branch_id'],SHOP_ROOT)."/ComAgreement/invoiceDetail/id/$agreement_id";
                    $this->handlerWXSendInvoiceData($data,$templateId);
                }
                if($tmp['branch_openid']){
                    $data['url'] = str_replace('shop','shop'.$result['branch_id'],SHOP_ROOT)."/WrkInvoicePlan/detail/plan_id/".$result['plan_id'];
                    $data["first"] = getWxTemplateCurrencyTip("branch_cancel_invoice_notice");
                    $data['openid'] = $tmp['branch_openid'];
                    if($data['openid'] != ""){
                        $this->handlerWXSendInvoiceData($data,$templateId);
                    }
                }
            }
        }else{
            //开票通知
            $data['remark'] = "点击查看详情";
            $tmp = $this->getOpenId($id[0]['id'],2);
            foreach ($id as $k=>$v){
                $data['openid'] = $tmp['openid'];
                $data["first"] = getWxTemplateCurrencyTip("new_invoice_notice");
                $data["invoice_id"] = $v['invoice_id'];
                $data["invoice_day"] = $v['invoice_day'];
                $data["invoice_sum"] = $v['invoice_sum'];
                if($data['openid'] != ""){
                    $agreement_id = M("WrkInvoicePlan")->where("id = ".$v['plan_id'])->getField("agreement_id");
                    $data['url'] = str_replace('shop','shop'.$v['branch_id'],SHOP_ROOT)."/ComAgreement/invoiceDetail/id/$agreement_id";
                    $this->handlerWXSendInvoiceData($data,$templateId);
                }
                if($tmp['branch_openid']){
                    $data['url'] = str_replace('shop','shop'.$v['branch_id'],SHOP_ROOT)."/WrkInvoicePlan/detail/plan_id/".$v['plan_id'];
                    $data["first"] = getWxTemplateCurrencyTip("branch_new_invoice_notice");
                    $data['openid'] = $tmp['branch_openid'];
                    if($data['openid'] != ""){
                        $this->handlerWXSendInvoiceData($data,$templateId);
                    }
                }
            }
        }
    }

    //取消开票申请通知
    public function sendCancelApplyMessage($id){
        $templateId = getWxTemplateIdByStandardId("OPENTM411013137");
        if(!$templateId){
            return false;
        }
        $result = M("WrkInvoicePlanDetail")
            ->alias("a")
            ->join("wrk_agreement as b on a.agreement_id = b.id")
            ->where("a.id = $id")
            ->field("b.company_id,a.plan_day,a.plan_money,a.plan_id")->find();
        $openid = [];
        //客户通知人
        $customer = D("WrkAgreement")->getCustomerUserData("WrkInvoicePlan",$result['company_id']);
        foreach ($customer as $k => $v){
            $openid[] = $v['openid'];
        }
        $data["first"] = getWxTemplateCurrencyTip("cancel_apply_notice");
        $data['remark'] = "";
        $data['url'] = "" ;
        $data['invoice_id'] = "无"; //合同编号
        $data['invoice_day'] = date("Y/m/d",$result['plan_day']); // 客户姓名
        $data['invoice_sum'] = $result['plan_money']; //变更内容
        $data['openid'] = array_unique($openid);
        if($data['openid'] != ""){
            $this->handlerWXSendInvoiceData($data,$templateId);
        }
        $notifiers = D("WrkAgreement")->getModuleNotifiersOpenid("WrkInvoicePlan",$result['company_id']);
        if($notifiers){
            $data["first"] = getWxTemplateCurrencyTip("branch_cancel_apply_notice");
            $data['openid'] = array_column($notifiers,"openid");
            $this->handlerWXSendInvoiceData($data,$templateId);
        }
    }

    public function getUserOpenid($user_id){
        $openid = M("SysUser")->where("id = $user_id")->getField("openid");
        return $openid;
    }

    public function handlerWXSendInvoiceData($data,$templateId){
        if($data){
            $message = array();
            $body = array();
            $message['template_id'] = $templateId;
            $message['url'] = $data['url'];
            $body['first']['value'] = $data['first'];
            $body['keyword1']['value'] = $data['invoice_id'];
            $body['keyword2']['value'] = $data['invoice_day'];
            $body['keyword3']['value'] = $data['invoice_sum'];
            $body['remark']['value'] = $data['remark'];
            $message['body'] = $body;
            if(is_array($data['openid'])){
                foreach($data['openid'] as $val){
                    if($val != ""){
                        $message['openid'] = $val;
                        return send_wx_message($message);
                    }
                }
            }else{
                $message['openid'] = $data['openid'];
                return send_wx_message($message);
            }
        }
    }

    //结束开票通知
    public function sendFinishInvoiceMessage($agreement_id){
        $templateId = getWxTemplateIdByStandardId("OPENTM414006304");
        if(!$templateId){
            return false;
        }
        $openid = [];
        $agreement = M("WrkAgreement a")
            ->join("left join sys_branch b on a.branch_id = b.id")
            ->where("a.id = ".$agreement_id)->field("a.company_id,a.name,b.name as branch_name")->find();
        $customer = D("WrkAgreement")->getCustomerUserData("WrkInvoicePlan",$agreement['company_id']);
        foreach ($customer as $k => $v){
            $openid[] = $v['openid'];
        }
        $data['invoice_id'] = $agreement['name']; //合同名称
        $data['invoice_day'] = $agreement['branch_name']; // 发起商户
        $data['invoice_sum'] = date("Y-m-d H:i"); //变更内容
        $data['remark'] = "合同结束开票";
        //商户通知人
        $notifiers = D("WrkAgreement")->getModuleNotifiersOpenid("WrkInvoicePlan",$agreement['company_id']);
        $plan_id = M("WrkInvoicePlan")->where("agreement_id = $agreement_id")->getField("id");
        if($notifiers){
            $data['url'] = str_replace('shop','shop'.getBrowseBranchId(),SHOP_ROOT)."/WrkInvoicePlan/detail/plan_id/".$plan_id;
            $data['first'] = getWxTemplateCurrencyTip("branch_finish_invoice_notice");
            $data['openid'] = array_column($notifiers,"openid");
            $this->handlerWXSendInvoiceData($data,$templateId);
        }
        $data['first'] = getWxTemplateCurrencyTip("finish_invoice_notice");
        $data['url'] = str_replace('shop','shop'.getBrowseBranchId(),SHOP_ROOT)."/ComAgreement/invoiceDetail/id/".$agreement_id;
        $data['openid'] = array_unique($openid);
        if($data['openid'] != ""){
            D("WrkInvoicePlan")->handlerWXSendInvoiceData($data,$templateId);
        }
    }

    //获得今日本周本月区间
    public function getQdrDate($type){
        $date = [];
        if($type == 1){
            //今日
            $date['begin']=mktime(0,0,0,date('m'),date('d'),date('Y'));
            $date['end']=mktime(0,0,0,date('m'),date('d')+1,date('Y'));
        }elseif($type == 2){
            //本周
            $date['begin']=mktime(0,0,0,date('m'),date('d')-date('w')+1,date('Y'));
            $date['end']=mktime(23,59,59,date('m'),date('d')-date('w')+7,date('Y'));
        }elseif($type == 3){
            //本月
            $date['begin']=mktime(0,0,0,date('m'),1,date('Y'));
            $date['end']=mktime(23,59,59,date('m'),date('t'),date('Y'));
        }elseif($type == 4){
            //本年
            $date['begin'] = strtotime(date("Y",time())."-1"."-1"); //本年开始
            $date['end'] = strtotime(date("Y",time())."-12"."-31"); //本年结束
        }
        return $date;
    }

    public function addModuleUserAccessData($module,$id,$company_id = 0){
        $visiblers = I("post.visiblers");
        $collaborators = I("post.collaborators");
        $branch_id = getBrowseBranchId();
        $data = [];
        M("SysUserModuleAccess")->where("module = '$module' and instance_id = $id")->delete();
        foreach($visiblers as $k=>$v){
            $data[] = ["module"=>$module,"instance_id"=>$id,"branch_id"=>$branch_id,"permit_value"=>DAC_PERMIT_VALUE_VISIBLER,"user_id"=>$v];
        }
        foreach($collaborators as $k=>$v){
            $data[] = ["module"=>$module,"instance_id"=>$id,"branch_id"=>$branch_id,"permit_value"=>DAC_PERMIT_VALUE_COLLABORATOR,"user_id"=>$v];
        }
        if($company_id){
            $condition['company_id'] = $company_id;
            $condition['module'] = $module;
            $condition['branch_id'] = $branch_id;
            $condition['permit_value'] = DAC_PERMIT_VALUE_NOTICER;
            $condition["type"] = DAC_SETTING_TYPE_BRANCH;
            $notifiers = M("SysUserModuleSetting")->where($condition)->field("user_id")->select();
            foreach($notifiers as $k=>$v){
                $data[] = ["module"=>$module,"instance_id"=>$id,"branch_id"=>$branch_id,"permit_value"=>DAC_PERMIT_VALUE_NOTICER,"user_id"=>$v['user_id']];
            }
        }
        M("SysUserModuleAccess")->addAll($data);
    }
}