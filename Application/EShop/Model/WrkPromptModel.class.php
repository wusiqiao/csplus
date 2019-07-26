<?php

namespace EShop\Model;

// use Common\Lib\Model\ComplexDataModel;

class WrkPromptModel extends ComplexDataModel {
protected $tableName = 'wrk_prompt';

protected $_createrField = "creater_id"; //创建人字段，如果是客户，可以设置成客户对应的字段
protected $_leaderField = "leader_id";//负责人字段
protected $_visiblersField = "visiblers"; //可见人字段
protected $_collaboratorsField  = "collaborators"; //可见人字段
protected $_companyField = "company_id"; //可见人字段
    public function sendWXPromptMessage($id,$amount,$prompt_item_id = nulll,$date = null){
        // $templateId = getWxTemplateIdByStandardId("OPENTM410800170");
        $templateId = getWxTemplateIdByStandardId("OPENTM401929941");
        if(!$templateId){
            return false;
        }

        $prompt = M("WrkPrompt")->where("id = ".$id)->find();
        $agreement = M("WrkAgreement")->where("id = ".$prompt['contract_id'])->find();
        $receivables = M("WrkReceivables")->where("id = ".$prompt['contract_id'])->find();


        // if (!empty($begin_date) && !empty($end_date)) {
        //     $data["first"] = "尊敬的客户，您好，请及时缴纳服务期间".$begin_date."至".$end_date."的业务欠款。";
        // } else {
        //     $data["first"] = "尊敬的客户，您好，请及时缴纳第".$period_number."期的业务欠款。";
        // }
        // $data["first"] = "您好，你的服务费即将到期，系统将在续费日自动扣款续费，请保证资金账户余额充足，谢谢您对我们工作的支持。";
        $data['remark'] = "";
        $data['url'] = str_replace('shop','shop'.$agreement['branch_id'],SHOP_ROOT)."/WrkReceivables/paymentList/id/".$receivables['id']."/prompt_item_id/".$prompt_item_id;
        
        $data["keyword1"] = $agreement['agreement_sn'];
        $data["keyword2"] = $agreement['name'];
        $data["keyword3"] = $amount;
        // $data["keyword4"] = date("Y-m-d",time());
        $data["keyword4"] = $date;
        // $data['url'] = "" ;
        // $data['open_id'] = $this->getOpenId($id);
        $open_id = $this->getOpenId($id);
        // $data["keyword1"] = $amount;
        // $data["keyword2"] = date("Y-m-d",time());
        // if(!empty($data['open_id'])){
        //     return $this->handlerWXSendData($data,$templateId);
        // }

        $data["first"] = getWxTemplateCurrencyTip("branch_manual_renewal_notice");
        $data['open_id'] = $open_id['visiblers'];
        if(!empty($data['open_id'])){
            $this->handlerWXSendData($data,$templateId);
        }
        $data["first"] = getWxTemplateCurrencyTip("manual_renewal_notice");
        $data['open_id'] = $open_id['leader_id'];
        if(!empty($data['open_id'])){
            $rst = $this->handlerWXSendData($data,$templateId);
        }
        return $rst;
    }

    public function handlerWXSendData($data,$templateId){
        if($data){
            $message = array();
            $body = array();
            $message['template_id'] = $templateId;
            $message['url'] = $data['url'];
            $body['first']['value'] = $data['first'];
            $body['keyword1']['value'] = $data['keyword1'];
            $body['keyword2']['value'] = $data['keyword2'];
            $body['keyword3']['value'] = $data['keyword3'];
            $body['keyword4']['value'] = $data['keyword4'];
            $body['remark']['value'] = $data['remark'];
            $message['body'] = $body;
            if(is_array($data['open_id'])){
                foreach($data['open_id'] as $val){
                    $message['openid'] = $val;
                  $rst = send_wx_message($message);
                }
            }
            return $rst;
        }
    }

    public function getOpenId($id){
        $prompt = M("WrkPrompt")->where("id = ".$id)->find();
        $agreement = M("WrkAgreement")->where("id = ".$prompt['contract_id'])->find();
        $open_id = [];
        $open_id['visiblers'] = [];
        $condition["company_id"] = $agreement['company_id'];
        $condition["module"] = 'WrkPrompt';
        $condition["permit_value"] = DAC_PERMIT_VALUE_NOTICER; //类型
        $condition["type"] = DAC_SETTING_TYPE_BRANCH;
        $visiblers_ids = M("SysUserModuleSetting")->where($condition)->getField('user_id',true);
        if(!empty($visiblers_ids)){
            $visiblers = M("SysUser")->where(['id'=>array('in',$visiblers_ids)])->select();
            foreach ($visiblers as $k => $v) {
                if (!empty($v['openid'])) {
                    array_push($open_id['visiblers'],$v['openid']);
                }
            }
        }

        $open_id['leader_id'] = [];
        $userData = D("WrkAgreement")->getCustomerUserData('WrkReceivables',$agreement['company_id']);
        if(!empty($userData)){
            foreach ($userData as $k => $v) {
                if (!empty($v['openid'])) {
                    array_push($open_id['leader_id'],$v['openid']);
                }
            }
        }
        // $open_id['leader_id'] = [];
        // $customer_leader_id  = M("SysUser")->where("id = ".$agreement['customer_leader_id'])->getField("openid");
        // if (!empty($customer_leader_id)) {
        //     array_push($open_id['leader_id'],$customer_leader_id);
        // }
        return $open_id;
    }
}