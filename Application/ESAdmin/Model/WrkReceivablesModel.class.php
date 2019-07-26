<?php

namespace ESAdmin\Model;

use Common\Lib\Model\ComplexDataModel;

class WrkReceivablesModel extends ComplexDataModel {
    protected $tableName = 'wrk_receivables';

    protected $_createrField = "creater_id"; //创建人字段，如果是客户，可以设置成客户对应的字段
    protected $_leaderField = "leader_id";//负责人字段
    protected $_visiblersField = "visiblers"; //可见人字段
    protected $_collaboratorsField  = "collaborators"; //可见人字段
    protected $_companyField = "company_id"; //可见人字段
    //到款确认
    public function sendWXConfirmMessage($id,$amount,$balance_amount){
        $templateId = getWxTemplateIdByStandardId("OPENTM415437052");
        if(!$templateId){
            return false;
        }
        // $data["first"] = "您好，您的服务费用已收，谢谢您对我们工作的支持。";
        $open_id = $this->getOpenId($id);
        $data['url'] = "" ;
        // 交易类型
        $data["keyword1"] = "线下付款";
        // 交易金额
        $data["keyword2"] = $amount;
        // 交易时间
        $data["keyword3"] = date("Y-m-d",time());
        // 账户余额
        $data["keyword4"] = $balance_amount;
        $data['remark'] = "";
        //只发给客户
        // $data["first"] = getWxTemplateCurrencyTip("branch_offline_payment_artificial_notice");
        // $data['open_id'] = $open_id['visiblers'];
        // if(!empty($data['open_id'])){
        //     $this->handlerWXSendData($data,$templateId);
        // }
        $data["first"] = getWxTemplateCurrencyTip("offline_payment_artificial_notice");
        $data['open_id'] = $open_id['leader_id'];
        if(!empty($data['open_id'])){
            $this->handlerWXSendData($data,$templateId);
        }

    }

    public function updateAccesData($module,$instance_id,$leader_id,$branch_id,$visiblers = null,$collaborators = null) {
        M('SysUserModuleAccess')->where(['module'=>$module,'instance_id'=>$instance_id])->delete();
        $acces_data = [];
        foreach ($visiblers as $k => $v) {
            $acces_data[]  = array(
            "module" => $module,
            'instance_id' => $instance_id,
            'user_id' => $v,
            'permit_value' => DAC_PERMIT_VALUE_VISIBLER,
            'branch_id' =>$branch_id,
            'type' => 1);
        }
        foreach ($collaborators as $k => $v) {
            $acces_data[]  = array(
            "module" => $module,
            'instance_id' => $instance_id,
            'user_id' => $v,
            'permit_value' => DAC_PERMIT_VALUE_COLLABORATOR,
            'branch_id' =>$branch_id,
            'type' => 1);
        }


        $condition = [];
        $condition["company_id"] = M($module)->where("id = ".$instance_id)->getField('company_id');
        $condition["module"] = $module;
        $condition["permit_value"] = DAC_PERMIT_VALUE_NOTICER;
        $condition['type'] = DAC_SETTING_TYPE_BRANCH;
        $noticers = M("SysUserModuleSetting")->where($condition)
        ->getField('user_id',true);
        foreach ($noticers as $k => $v) {
            $acces_data[]  = array(
            "module" => $module,
            'instance_id' => $instance_id,
            'user_id' => $v,
            'permit_value' => DAC_PERMIT_VALUE_NOTICER,
            'branch_id' =>$branch_id,
            'type' => 1);
        }

        M('SysUserModuleAccess')->addAll($acces_data);
    }

    public function badDept($id = null,$branch_id = null) {
        $item = M("WrkReceivablesItem")->alias('a')
        ->join('LEFT JOIN wrk_receivables_account b ON b.id = a.account_id')
        ->field('a.id,a.receivable_date,a.receivables_amount,a.actual_date,a.actual_amount,b.name as account_name,a.status,a.begin_date,a.end_date')
        ->where(['a.receivables_id' =>$id])
        ->select();
        $total_amount = 0;
        $pay_amount = 0;
        foreach ($item as $k => $v) {
           $total_amount += (float)$v['receivables_amount'];
           $pay_amount += (float)$v['actual_amount'];
        }
        $unpay_amount = (float)$total_amount - (float)$pay_amount;
        if ($unpay_amount == 0) {
            M("WrkReceivables")->where(['id' =>$id])->save(['status'=>1]);
        } else {
            $data['receivables_id'] = $id;
            $data['bad_dept_amount'] = $unpay_amount;
            $data['bad_dept_date'] = time();
            $data['create_time'] = time();
            // $data['attach_group'] = genUniqidKey();
            $data['branch_id'] = getBrowseBranchId();
            M("WrkReceivables")->where(['id' =>$id])->save(['status'=>2]);
            M("WrkBadDept")->add($data);
            // $this->createLog($id,"add_badDept");
            A("WrkReceivables")->createLog($id,"add_badDept");
            D("WrkReceivables")->sendWXBadDeptMessage($id,$data['bad_dept_amount'],'');
        }
        $rst = array('code'=>0,'message'=>'坏账处理成功');
        return $rst;
    }


    //客户缴费通知
    public function sendWXcustomerPayMessage($id,$amount,$type,$notice_id){
        $templateId = getWxTemplateIdByStandardId("OPENTM402025191");
        // var_dump($templateId);
        if(!$templateId){
            return false;
        }
        // $data["first"] = "客户续费通知，点击查看详情";

        $receivables = M("WrkReceivables")
        ->alias("a")
        ->join('LEFT JOIN wrk_agreement b ON b.id = a.contract_id')
        ->join('LEFT JOIN sys_branch c ON c.id = b.company_id')
        ->where(["a.id"=>$id])
        ->field("a.id,c.name,c.contact,b.agreement_sn,b.branch_id")
        ->find();

        $data['url'] = str_replace('shop','shop'.$receivables['branch_id'],SHOP_ROOT)."/WrkReceivables/detail/id/".$receivables['id'].'/notice_id/'.$notice_id;
        // 客户名称
        $data["keyword1"] = $receivables['name'];
        // 客户电话
        $data["keyword2"] = $receivables['contact'];
        // 付款金额
        $data["keyword3"] = sprintf("%.2f",$amount);
        // 支付方式
        $data["keyword4"] = $type;
        // 对应单号
        $data["keyword5"] = $receivables['agreement_sn'];
        $data['remark'] = "";
        //消息只发给商户通知人
        $open_id = $this->getOpenId($id);
        $data["first"] = getWxTemplateCurrencyTip("tct_branch_manual_renewal_notice");
        $data['open_id'] = $open_id['visiblers'];
        if(!empty($data['open_id'])){
            $this->handlerWXSendData($data,$templateId);
        }
        $data['templateId'] = $templateId;
        return $data;
    }
    //自动扣款
    public function sendWXAutoMessage($id,$amount,$balance_amount){
        $templateId = getWxTemplateIdByStandardId("OPENTM415437052");
        if(!$templateId){
            return false;
        }
        // $data["first"] = "您好，您的服务费已从资金账户支付，谢谢您对我们工作的支持。";
        $data['url'] = "" ;
        // $data['open_id'] = $this->getOpenId($id);
        // 交易类型
        $data["keyword1"] = "线下付款";
        // 交易金额
        $data["keyword2"] = $amount;
        // 交易时间
        $data["keyword3"] = date("Y-m-d",time());
        // 账户余额
        $data["keyword4"] = $balance_amount;
        $data['remark'] = "";
        // if(!empty($data['open_id'])){
        //     $this->handlerWXSendData($data,$templateId);
        // }

        $open_id = $this->getOpenId($id);
        $data["first"] = getWxTemplateCurrencyTip("branch_balance_payment_automatic_notice");
        $data['open_id'] = $open_id['visiblers'];
        if(!empty($data['open_id'])){
            $this->handlerWXSendData($data,$templateId);
        }
        $data["first"] = getWxTemplateCurrencyTip("balance_payment_automatic_notice");
        $data['open_id'] = $open_id['leader_id'];
        if(!empty($data['open_id'])){
            $this->handlerWXSendData($data,$templateId);
        }
        return $data;
    }


    //删除到款
    public function sendWXDeleteRecordMessage($id,$amount,$balance_amount){
        $templateId = getWxTemplateIdByStandardId("OPENTM415437052");
        if(!$templateId){
            return false;
        }
        $open_id = $this->getOpenId($id);
        $data['url'] = "" ;
        // 交易类型
        $data["keyword1"] = "删除到款";
        // 交易金额
        $data["keyword2"] = $amount;
        // 交易时间
        $data["keyword3"] = date("Y-m-d",time());
        // 账户余额
        $data["keyword4"] = $balance_amount;
        $data['remark'] = "";
        $data["first"] = getWxTemplateCurrencyTip("branch_delete_receipt_record_notice");
        $data['open_id'] = $open_id['visiblers'];
        if(!empty($data['open_id'])){
            $this->handlerWXSendData($data,$templateId);
        }
        $data["first"] = getWxTemplateCurrencyTip("delete_receipt_record_notice");
        $data['open_id'] = $open_id['leader_id'];
        if(!empty($data['open_id'])){
            $this->handlerWXSendData($data,$templateId);
        }
    }

    //逾期通知
    public function sendWXOverdueMessage($id,$amount,$remark){
        $templateId = getWxTemplateIdByStandardId("OPENTM410800170");
        if(!$templateId){
            return false;
        }
        // $data["first"] = $remark;
        $open_id = $this->getOpenId($id);
        $data['remark'] = $remark;
        $data['url'] = "" ;
        $data["keyword1"] = $amount;
        $data["keyword2"] = date("Y-m-d",time());
        // if(!empty($data['open_id'])){
        //     $this->handlerWXSendData($data,$templateId);
        // }
        $data["first"] = getWxTemplateCurrencyTip("branch_freeze_automatic_notice");
        $data['open_id'] = $open_id['visiblers'];
        if(!empty($data['open_id'])){
            $this->handlerWXSendData($data,$templateId);
        }
        $data["first"] = getWxTemplateCurrencyTip("freeze_automatic_notice");
        $data['open_id'] = $open_id['leader_id'];
        if(!empty($data['open_id'])){
            $this->handlerWXSendData($data,$templateId);
        }
    }



    public function sendWXRefundMessage($id,$amount,$date,$remark){
        $templateId = getWxTemplateIdByStandardId("OPENTM415964302");
        if(!$templateId){
            return false;
        }
        // $data["first"] = "";
        $data['url'] = "" ;
        $data['remark'] = "" ;
        $data["keyword1"] = $amount;
        $data["keyword2"] = "一般退款";
        $data["keyword3"] = $date;
        $data["keyword4"] = "返回账户";
        $data['keyword5'] = $remark;
        // $data['open_id'] = $this->getOpenId($id);
        // if(!empty($data['open_id'])){
        //     $this->handlerWXSendData($data,$templateId);
        // }
        $open_id = $this->getOpenId($id);
        $data["first"] = getWxTemplateCurrencyTip("branch_refund_automatic_notice");
        $data['open_id'] = $open_id['visiblers'];
        if(!empty($data['open_id'])){
            $this->handlerWXSendData($data,$templateId);
        }
        $data["first"] = getWxTemplateCurrencyTip("refund_automatic_notice");
        $data['open_id'] = $open_id['leader_id'];
        if(!empty($data['open_id'])){
            $this->handlerWXSendData($data,$templateId);
        }
        return $data;
    }




    public function sendWXBadDeptMessage($id,$amount,$remark){
        $templateId = getWxTemplateIdByStandardId("OPENTM410800170");
        if(!$templateId){
            return false;
        }
        // $data["first"] = "客户合同未付金额已做坏账处理，特此通知。";
        $data['remark'] = $remark;
        $data['url'] = "" ;
        // $data['open_id'] = $this->getOpenIdForBadDept($id) ;
        $data["keyword1"] = $amount;
        $data["keyword2"] = date("Y-m-d",time());
        // if(!empty($data['open_id'])){
        //     return $this->handlerWXSendData($data,$templateId);
        // }
        $open_id = $this->getOpenId($id);
        $data["first"] = getWxTemplateCurrencyTip("branch_bad_debt_automatic_notice");
        $data['open_id'] = $open_id['visiblers'];
        if(!empty($data['open_id'])){
            $this->handlerWXSendData($data,$templateId);
        }
        $data["first"] = getWxTemplateCurrencyTip("bad_debt_automatic_notice")??"";
        $data['open_id'] = $open_id['leader_id'];
        if(!empty($data['open_id'])){
            $this->handlerWXSendData($data,$templateId);
        }
        return $data;
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
            if (isset($data['keyword3'])) {
            	$body['keyword3']['value'] = $data['keyword3'];
            }
			if (isset($data['keyword4'])) {
            	$body['keyword4']['value'] = $data['keyword4'];
            }
            if (isset($data['keyword5'])) {
	            $body['keyword5']['value'] = $data['keyword5'];
            }
            $body['remark']['value'] = $data['remark'];
            $message['body'] = $body;
            if(is_array($data['open_id'])){
                foreach($data['open_id'] as $val){
                    $message['openid'] = $val;
                    return send_wx_message($message);
                }
            }
        }
    }

    public function getOpenId($id){
        $receivables = M("WrkReceivables")->where("id = ".$id)->find();
        $agreement = M("WrkAgreement")->where("id = ".$receivables['contract_id'])->find();
        $open_id = [];
        $open_id['visiblers'] = [];

        $condition["company_id"] = $agreement['company_id'];
        $condition["module"] = 'WrkReceivables';
        $condition["permit_value"] = DAC_PERMIT_VALUE_NOTICER; //类型
        $condition['type'] = DAC_SETTING_TYPE_BRANCH;
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
        // $customer_leader_id  = M("SysUser")->where("id = ".$agreement['customer_leader_id'])->getField("openid");
        // if (!empty($customer_leader_id)) {
        // 	array_push($open_id['leader_id'],$customer_leader_id);
        // }
        return $open_id;
    }


    public function payByTimer($id,$receivables_item_id = null){
        // 执行定时任务
        if (!empty($receivables_item_id)) {
            $condition = [];
            $condition['a.id'] = $receivables_item_id;
            $condition['a.status'] = array("neq",2);
            $receivables_item = M('WrkReceivablesItem')->alias("a")
              ->field("a.id,a.receivables_id,a.receivable_date,a.receivables_amount,a.actual_amount")
              ->where($condition)
              ->find();
            if (!empty($receivables_item)) {
                $receivables_amount = M('WrkReceivablesItem')->where(['receivables_id'=>$id])->sum('receivables_amount');
                $actual_amount = M('WrkReceivablesItem')->where(['receivables_id'=>$id])->sum('actual_amount');
                $unconfirmed_amount = D("WrkReceivables")->where(['id'=>$id])->getField('unconfirmed_amount');
                $unpay_amount = (float)$receivables_amount - (float)$actual_amount;
                if ($unconfirmed_amount >= $unpay_amount) {
                    return false;
                }
                $unpay_amount =  (float)$unpay_amount - (float)$unconfirmed_amount;
                $company_id = M('WrkReceivables')->where(['id'=>$id])->getField("company_id");
                $sysBranch = M("SysBranch")->where(['id' =>$company_id])->find();
                $sysBranch['balance_amount'] = $sysBranch['money'] - $sysBranch['money_auditing'];
                if ($sysBranch['balance_amount'] > 0) {
                    $pay_amount = $receivables_item['receivables_amount'] - $receivables_item['actual_amount'];
                    if ($unpay_amount < $pay_amount) {
                        $pay_amount = $unpay_amount;
                    }
                    if ($sysBranch['balance_amount'] < $pay_amount) {
                        $pay_amount = $sysBranch['balance_amount'];
                    }
                    $this->payByBlance($receivables_item['receivables_id'],$pay_amount,$company_id);
                    $sysBranch = M("SysBranch")->where(['id' =>$company_id])->find();
                    $money = $sysBranch['money'];
                    $money = $money - $pay_amount;
                    M("SysBranch")->where(['id' =>$company_id])->save(['money'=>$money]);
                    $sysBranch = M("SysBranch")->where(['id' =>$company_id])->find();
                    $sysBranch['balance_amount'] = $sysBranch['money'] - $sysBranch['money_auditing'];

                    $record_data['receivables_id'] = $receivables_item['receivables_id'];
                    $record_data['pay_date'] = time();
                    $record_data['pay_amount'] = $pay_amount;
                    $record_data['balance_amount'] = $pay_amount;
                    $record_data['order_sn'] = getOrderNo("CIZ_");
                    $record_data['created_time'] = time();
                    $record_data['updated_time'] = time();
                    $record_data['branch_id'] = $sysBranch['id'];
                    $record_id = M("wrkReceivablesRecord")->add($record_data);

                    $userSession = new UserSessionModel();
                    $userSession->currBranchId = $sysBranch['id'];
                    $userSession->parentBranchId = $sysBranch['parent_id'];
                    session(USER_SESSION_KEY, $userSession);
                    $this->sendWXAutoMessage($id,$pay_amount,$sysBranch['balance_amount']);
                    session(USER_SESSION_KEY,null);
                }
              // $this->sendWXPromptMessage($id,$amount);
            }
        }
        // 生成定时任务
        $condition = [];
        $condition['a.receivables_id'] = $id;
        $condition['a.status'] = array("neq",2);
        $condition['a.receivable_date'] = array('GT',time());
        $receivables_item = M('WrkReceivablesItem')->alias("a")
        ->field("a.id,a.receivables_id,a.receivable_date,a.receivables_amount,a.actual_amount")
        ->where($condition)
        ->order("receivables_amount asc")
        ->find();

        if (!empty($receivables_item)) {
            $intval = $receivables_item['receivable_date'] + (60*60*9) - time();
            D('ESAdmin/SysMq')->add_timer($intval,WEB_ROOT."/ReqQueue/payByTimer/id/".$id."/receivables_item_id/".$receivables_item['id']);

            // D('ESAdmin/SysMq')->add_timer($intval,WEB_ROOT."/WrkReceivables/payByTimer/id/".$id."/receivables_item_id/".$receivables_item['id']);
        }
        $num = M("WrkReceivablesItem")->where([
            'receivables_id' =>$id,
            'status'=>array('neq',2)
            ])->count();
        if ($num == 0) {
            M("WrkReceivables")->where(['id' =>$id])->save(['status'=>1]);
        }
        return $receivables_item;
    }

    public function payByCoupon($receivables_id,$sp_ticket_stock_id,$branch_id,$user_id){
        $item = M("WrkReceivablesItem")->where([
                'receivables_id' =>$receivables_id,
                'status'=>array('neq',2)
            ])
        ->order("id asc")->select();

        $coupon = M("SpTicketStock")->alias('a')
        ->join('LEFT JOIN sp_activity b ON b.id = a.activity_id')
        ->join('LEFT JOIN sp_activity_ticket c ON c.ticket_id = a.ticket_id')
        ->join('LEFT JOIN sp_ticket d ON d.id = a.ticket_id')
        ->where([
            'a.id' =>$sp_ticket_stock_id,
            'a.state' => 1,
            'b.is_scope' => 0,
            'b.activity_type' => 2,
            'b.branch_id' => $branch_id
        ])->field("a.id,d.reduce_cost")
        ->find();
        if (!empty($coupon)) {
            $sum = $coupon['reduce_cost'];
            $item_arr = [];
            foreach ($item as $k => $v) {
                $unpay_amount = (float)$v['receivables_amount'] - (float)$v['actual_amount'];
                if ($sum >= $unpay_amount) {
                    $item_arr['actual_amount'] = $v['receivables_amount'];
                    //已收优惠卷金额增加
                    $item_arr['coupon_amount'] = (float)$v['coupon_amount'] + (float)$unpay_amount;
                    $item_arr['actual_date'] = time();
                    $item_arr['status'] = 2;
                    M('WrkReceivablesItem')->where(['id'=>$v['id']])->save($item_arr);
                    $sum = (float)$sum - (float)$unpay_amount;
                    if ($sum == 0) {break;}
                } else {
                    $item_arr['actual_amount'] = (float)$v['actual_amount'] + (float)$sum;
                    //已收余额增加
                    $item_arr['coupon_amount'] = (float)$v['coupon_amount'] + (float)$sum;
                    $item_arr['actual_date'] = time();
                    $item_arr['status'] = 1;
                    M('WrkReceivablesItem')->where(['id'=>$v['id']])->save($item_arr);
                    $sum = 0;
                    break;
                }
            }
            $coupon_arr = [];
            $coupon_arr['state'] = 2;
            $coupon_arr['use_objec'] = 0;
            $coupon_arr['object_id'] = $user_id;
            $coupon_arr['used_time'] = time();
            M("SpTicketStock")
            ->where([
                'id' =>$sp_ticket_stock_id,
                'branch_id' => $branch_id
            ])->save($coupon_arr);
        }
        return $item;
    }

    public function payByBlance($receivables_id,$sum,$company_id){
        $item = M("WrkReceivablesItem")->where([
                'receivables_id' =>$receivables_id,
                'status'=>array('neq',2)
            ])
        ->order("id asc")->select();
        $blance_amount = $sum;
        $item_arr = [];

        foreach ($item as $k => $v) {
            $unpay_amount = (float)$v['receivables_amount'] - (float)$v['actual_amount'];
            if ($sum >= ($unpay_amount) ) {
                $item_arr['actual_amount'] = $v['receivables_amount'];
                //已收余额增加
                $item_arr['balance_amount'] = (float)$v['balance_amount'] + (float)$unpay_amount;
                $item_arr['actual_date'] = time();
                $item_arr['status'] = 2;
                M('WrkReceivablesItem')->where(['id'=>$v['id']])->save($item_arr);
                $sum = (float)$sum - (float)$unpay_amount;
                if ($sum == 0) {break;}
            } else {
                $item_arr['actual_amount'] = (float)$v['actual_amount'] + (float)$sum;
                //已收余额增加
                $item_arr['balance_amount'] = (float)$v['balance_amount'] + (float)$sum;
                $item_arr['actual_date'] = time();
                $item_arr['status'] = 1;
                M('WrkReceivablesItem')->where(['id'=>$v['id']])->save($item_arr);
                $sum = 0;
                break;
            }
        }
        $finance_data['fina_type'] = FIN_PROMPT_BALANCE_PAY;
        $finance_data['fina_cash'] = $blance_amount - $sum;
        $finance_data['fina_time'] = time();
        // $finance_data['user_id'] = $recharge_data['user_id'];
        $finance_data['branch_id'] = M('SysBranch')->where(['id'=>$company_id])->getField("parent_id");
        $finance_data['company_id'] = $company_id;
        $finance_data['order_sn'] = getOrderNo("CIZ_");
        $finance_data['third_fee'] = 0;
        // $finance_data['receivable_id'] = $data['account_id'];
        $finance_data['title'] = '客户余额消费';
        M("ComFinance")->add($finance_data);
        $branch_money = M("SysBranch")->where("id = ".$finance_data['branch_id'])->getField("money");
        M("SysBranch")->where("id = ".$finance_data['branch_id'])->setField("money",$branch_money+$finance_data['fina_cash']);
        return $item;
    }

    public function updateBadDept($id = null) {
        $item = M("WrkReceivablesItem")->alias('a')
        ->join('LEFT JOIN wrk_receivables_account b ON b.id = a.account_id')
        ->field('a.id,a.receivable_date,a.receivables_amount,a.actual_date,a.actual_amount,b.name as account_name,a.status,a.begin_date,a.end_date')
        ->where(['a.receivables_id' =>$id])
        ->select();
        foreach ($item as $k => $v) {
           $rst['total_amount'] += $v['receivables_amount'];
           $rst['pay_amount'] += $v['actual_amount'];
        }
        $unpay_amount = (float)$rst['total_amount'] - (float)$rst['pay_amount'];
        $badDept = M("WrkBadDept")->alias('a')
        ->join('LEFT JOIN wrk_receivables_account b ON b.id = a.account_id')
        ->field('a.id,a.bad_dept_date,a.bad_dept_amount,a.attach_group,a.status')
        ->where(['a.receivables_id' =>$id])
        ->find();
        if ($unpay_amount == 0) {
            M("WrkBadDept")->where(['id' =>$badDept['id']])->delete();
            $num = M("WrkReceivablesItem")->where([
                'receivables_id' =>$id,
                'status'=>array('neq',2)
                ])->count();
            if ($num == 0) {
                M("WrkReceivables")->where(['id' =>$id])->save(['status'=>1]);
            }
        } else {
            M("WrkBadDept")->where(['id' =>$badDept['id']])->save(['bad_dept_amount'=>$unpay_amount]);
        }
    }

    public function getIsShowReceivablesNotice(){
        $condition['url'] = "WrkReceivables";
        $condition['is_valid'] = 1;
        $condition['is_show'] = 1;
        $condition['is_online'] = 1;
        $receivables_menu = M("SysMenu")->where($condition)->field("parent_id,id")->find();
        if(!$receivables_menu){
            return false;
        }
        $where['a.branch_id'] = getBrowseBranchId();
        $where['b.status'] = 1;
        $notice_count = D("WrkReceivables")->setDacFilter('a')
            ->join("left join wrk_receivables_notice b on a.id = b.receivables_id")
            ->where($where)->count();
        if($notice_count <= 0){
            return false;
        }
        return $receivables_menu;
    }

    public function addFinanceDetail($pay_amount,$branch_id,$company_id,$order_sn,$account_id){
        $finance_data['fina_type'] = FIN_RECEIVABLES_CONFIRMED;
        $finance_data['fina_cash'] = $pay_amount;
        $finance_data['fina_time'] = time();
        $finance_data['branch_id'] = $branch_id;
        $finance_data['company_id'] = $company_id;
        $finance_data['order_sn'] = $order_sn;
        $finance_data['third_fee'] = 0;
        $finance_data['receivable_id'] = $account_id;
        $finance_data['title'] = '微信付款充值';
        M("ComFinance")->add($finance_data);
        //到款确认后会在资金账户多一条充值记录，所以需要多一条余额消费记录抵消
        $finance_data_pay['fina_type'] = FIN_PROMPT_BALANCE_PAY;//缴费付款
        $finance_data_pay['fina_cash'] = $pay_amount;
        $finance_data_pay['fina_time'] = time();
        $finance_data_pay['branch_id'] = $branch_id;
        $finance_data_pay['company_id'] = $company_id;
        $finance_data_pay['order_sn'] = $order_sn;
        $finance_data_pay['third_fee'] = 0;
        $finance_data_pay['title'] = '客户余额消费';
        M("ComFinance")->add($finance_data_pay);
    }

    public function addRechargeRecord($pay_amount,$branch_id,$company_id,$order_sn,$account_id,$user_id){
        $record['user_id'] = $user_id;
        $record['creator_id'] = $user_id;
        $record['order_sn'] = $order_sn;
        $record['account'] = $pay_amount;
        $record['ctime'] = time();
        $record['pay_time'] = time();
        $record['pay_name'] = '缴费充值（微信）';
        $record['pay_status'] = 1;
        $record['money_type'] = FIN_CIZ_RECHARGE;
        $record['branch_id'] = $branch_id;
        $record['company_id'] = $company_id;
        $record['source'] = FIN_PAY_WEIXIN;
        $record['receivables_id'] = $account_id;
        M("ComRecharge")->add($record);
    }
}