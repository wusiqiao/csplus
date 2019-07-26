<?php

namespace EShop\Model;

// use Common\Lib\Model\ComplexDataModel;

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

        $data["first"] = getWxTemplateCurrencyTip("branch_offline_payment_artificial_notice");
        $data['open_id'] = $open_id['visiblers'];
        if(!empty($data['open_id'])){
            $this->handlerWXSendData($data,$templateId);
        }
        $data["first"] = getWxTemplateCurrencyTip("offline_payment_artificial_notice");
        $data['open_id'] = $open_id['leader_id'];
        if(!empty($data['open_id'])){
            $this->handlerWXSendData($data,$templateId);
        }

    }

    //客户缴费通知
    public function sendWXcustomerPayMessage($id,$amount,$type,$notice_id){
        $templateId = getWxTemplateIdByStandardId("OPENTM402025191");
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
        $data["keyword3"] = $amount;
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
        $data["first"] = getWxTemplateCurrencyTip("bad_debt_automatic_notice");
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
                    send_wx_message($message);
                }
            }
        }
    }

    public function getOpenId($id){
        $receivables = M("WrkReceivables")->where("id = $id")->find();
        $agreement = M("WrkAgreement")->where("id = ".$receivables['contract_id'])->find();
        $open_id = [];
        $open_id['visiblers'] = [];

        $condition["company_id"] = $agreement['company_id'];
        $condition["module"] = 'WrkReceivables';
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
        // 	array_push($open_id['leader_id'],$customer_leader_id);
        // }
        return $open_id;
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
        // var_dump($coupon);
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
        $branch_money = M("SysBranch")->where("id = ".getBrowseBranchId())->getField("money");
        M("SysBranch")->where("id = ".getBrowseBranchId())->setField("money",$finance_data['fina_cash']+$branch_money);
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
                'receivables_id' =>$receivables_id,
                'status'=>array('neq',2)
                ])->count();
            if ($num == 0) {
                M("WrkReceivables")->where(['id' =>$receivables_id])->save(['status'=>1]);
            }
        } else {
            M("WrkBadDept")->where(['id' =>$badDept['id']])->save(['bad_dept_amount'=>$unpay_amount]);
        }
    }
}