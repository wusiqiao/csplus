<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class ComRechargeModel extends DataModel {

    protected $_link = array(
        "user" => array(
            "join_name" => "LEFT",
            'class_name' => "SysUser",
            'foreign_key' => 'user_id',
            'mapping_name' => 'user',
            'mapping_fields' => 'name,id',
            "mapping_key" => "id"
        ),
        "company" => array(
            "join_name" => "LEFT",
            'class_name' => "SysBranch",
            'foreign_key' => 'company_id',
            'mapping_name' => 'company',
            'mapping_fields' => 'name,id',
            "mapping_key" => "id"
        )
    );
    public function appendLink(array $link,string $name)
    {
        $this->_link[$name] = $link;
    }
    public function rechargeAdopt($payment,$user_id)
    {
        try {
            $this->startTrans();
            //更新充值记录 or 新增
            if ($payment === null) {
                $payment = D('ComRecharge');
                //判断u or c
                $capital_account_id = explode(':',I('post.capital_account_id'));
                if ($capital_account_id[0] == 'c') {
                    $payment->company_id = $capital_account_id[1];
                    $payment->user_id = $user_id;
                    $payment->money_type = FIN_CIZ_RECHARGE;
                    $payment->order_sn = getOrderNo("CIZ_");
                    $payment->pay_name = '公司账户充值(线下转账)';
                } else {
                    $payment->user_id = $capital_account_id[1];
                    $payment->money_type = FIN_UIZ_RECHARGE;
                    $payment->order_sn = getOrderNo("CIZ_");
                    $payment->pay_name = '个人账户充值(线下转账)';
                }
                $payment->message = I('post.message');
                $payment->third_fee = I('post.third_fee');
                $payment->creator_id = $user_id;
                $payment->branch_id =  getBrowseBranchId();
                $payment->account = I('post.account');
                $payment->ctime = time();
                $payment->source = FIN_PAY_OFFLINE;
                $payment->pay_status = 1;
                $payment->pay_time = time();
                $payment->audit_time = time();
                $payment->receivable_id = I('post.origin');
                $payment->attach_group = I('post.attach_group');
                $id = $payment->add();
                $payment = D('ComRecharge')->where('id = '.$id)->find();
                $amount = floatval($payment['account']); //充值金额
                $action = 'add';
            } else {
                $amount = floatval($payment['account']); //充值金额
                $recharge["pay_status"] = 1;
                $recharge["pay_time"] = time();
                $recharge["audit_time"] = time();
                $recharge['third_fee'] = I('post.third_fee');
                $recharge["receivable_id"] = I('post.origin');
                $recharge["attach_group"] = I('post.attach_group');
                D('ComRecharge')->where("id=".$payment['id'])->save($recharge);
                $action = 'edit';
            }
            //新增财务对账单
            $finance_table = M('ComFinance');
            $financein['fina_type'] = $payment['money_type']; //从充值类型带过来
            $financein['fina_cash'] = $amount;
            $financein['fina_time'] = time();
            $financein['user_id'] = $payment['user_id'];
            $financein['branch_id'] = $payment['branch_id'];
            $financein['company_id'] = $payment['company_id'];
            $financein['order_sn'] = $payment['order_sn'];
            $financein['third_fee'] = I('post.third_fee');
            $financein['receivable_id'] = I('post.origin');
            if ($payment['money_type'] == FIN_UIZ_RECHARGE) {  //个人账号充值
                $financein['title'] = '个人账户充值';
                $members = D("SysUser");
                $user_data = $members->where("id=".$payment['user_id'])->find();
                if ($finance_table->data($financein)->add()) {
                    //更新用户保证金余额
                    $deposit_money = floatval($user_data['user_money']) + $amount;
                    $members->where("id=".$payment['user_id'])->setField("user_money", $deposit_money);
                    //充值成功通知
                }
            } else {  //公司账户充值
                $financein['title'] = '公司账户充值';
                $members = M('SysBranch');
                $company_data = $members->where("id=".$payment['company_id'])->find();
                if ($finance_table->data($financein)->add()) {
                    $company_money = floatval($company_data['money']) + $amount;
                    $members->where("id=".$payment['company_id'])->setField("money", $company_money);
                }
            }
            //收款账户添加
            $receivables = M('wrk_receivables_account')->where('id = '. $financein['receivable_id'])->getField('accumulated_amount');
            $receivables_money = $receivables + $amount;
            M('wrk_receivables_account')->where('id = '. $financein['receivable_id'])->setField("accumulated_amount", $receivables_money);
            $this->commit();
            $condition["a.id"] = $payment['id'];
            $record = D('ComRecharge')
                            ->alias("a")
                            ->field("a.*,company.name as company_name,user.name as user_name")
                            ->join('left join sys_user as user on user.id = a.user_id')
                            ->join('left join sys_branch as company on company.id = a.company_id')
                            ->where($condition)->find();
            $record['capital_account'] = $record['money_type'] == FIN_CIZ_RECHARGE ? $record['company_name'] : $record['user_name'];
            $record['actual_money'] = $record['pay_status'] == 1 ? sprintf("%.2f", $record['account'] - $record['third_fee']):'';
            $record['object_type'] = $record['money_type'] == FIN_CIZ_RECHARGE ? 'c':'u';
            return array('code'=>0,'message' => '充值成功','id' =>$payment['id'],'row'=>$record,'action' =>$action);

        } catch (\Exception $ex) {
            $this->rollback();
            return array('code' =>1 ,'message' =>"系统出错，请联系管理员");
        }
    }

    //客户充值提现时发送通知给商户相应负责人(移动端PC端都调用此方法)
    public function sendCapitalMsgToBranch($fina_type,$customer_id,$customer_type,$fina_cash){
        $templateId = getWxTemplateIdByStandardId("OPENTM415437052");
        if(!$templateId){
            return false;
        }
        if($customer_type == "user" && $customer_id){
            $customer = M("SysUser")->where("id = $customer_id")->field("name,user_money as money")->find();
        }elseif($customer_id){
            $customer = M("SysBranch")->where("id = $customer_id")->field("name,money")->find();
        }
        $data["first"] = $fina_type == 'comrecharge' ? "客户已线下付款，请确认是否收到款项" :"客户申请提现，请确认";
        $url = str_replace('shop','shop'.getBrowseBranchId(),SHOP_ROOT)."/ComBranchCapital/";
        $url .= $fina_type == 'comrecharge' ? 'recharge':'withdrawal';
        $data['url'] = $url;
        $data["keyword1"] = $fina_type == 'comrecharge' ? '账户充值（线下付款）':"账户提现";// 交易类型
        $data["keyword2"] = sprintf("%.2f",$fina_cash);// 交易金额
        $data["keyword3"] = date("Y-m-d H:i:s",time());// 交易时间
        $data["keyword4"] = $fina_type == 'comrecharge' ? "" :$customer['money'];// 账户余额
        $data['remark'] = "点击查看详情";
        $data['open_id'] = $this->getCapitalLeaderOpenid($fina_type);
        if(!empty($data['open_id'])){
            return D("WrkReceivables")->handlerWXSendData($data,$templateId);
        }
        return false;
    }

    public function getCapitalLeaderOpenid($fina_type){
        $condition['a.module'] = is_array($fina_type) ? array("in",$fina_type) : $fina_type;
        $condition['a.branch_id'] = getBrowseBranchId();
        $condition['a.permit_value'] = DAC_PERMIT_VALUE_LEADER;
        $condition['a.type'] = DAC_SETTING_TYPE_BRANCH;
        $result = M("SysUserModuleSetting a")
            ->join("sys_user b on a.user_id = b.id")
            ->where($condition)->field('b.openid')->select();
        if(!$result){
            $result = M("SysBranch a")
                ->join("sys_user b on a.leader_id = b.id")
                ->where("a.id = ".getBrowseBranchId())->field('b.openid')->select();
        }
        return array_column($result,'openid');
    }
}