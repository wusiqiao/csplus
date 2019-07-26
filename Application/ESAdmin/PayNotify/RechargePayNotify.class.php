<?php

//初始化日志

class RechargeNotifyCallBack extends WxPayNotify {
    protected $_branch_id = '';
    public function setBranchId($id){
        $this->_branch_id = $id;
    }
//查询订单
    public function Queryorder($transaction_id) {
        //查询交易单号
        $input = new \WxPayOrderQuery();
        $userSession = new \ESAdmin\Model\SysUserModel();
        $userSession->parentBranchId = $this->_branch_id;
        session(USER_SESSION_KEY, $userSession);
        setPayParams($input);
        $input->SetTransaction_id($transaction_id);
        $result = \WxPayApi::orderQuery($input);
        $material = S('FILE_WxCodePay_'.$result['out_trade_no']);
        \Think\Log::write('wxpay-pc:FILE_WxCodePay_'.$result['out_trade_no']);
        \Think\Log::write(json_encode($material));
        if ($material === false) {
            return true;
        }
        $out_trade_no = $result['out_trade_no'];
        $branch_id = $material['branch_id'];
        //充值记录是否存在
        $payment = D("ComRecharge")->where("order_sn='".$out_trade_no."'")->find();
        if ($payment['pay_status'] == 1) {
            return true;
        }
        $finance_table = M('ComFinance');
        //更新充值记录
        try {

            $finance_table->startTrans();
            //收款账号处理 wrk_receivables_account
            $qra_condition['branch_id'] = $branch_id;
            $qra_condition['name'] = '微信';
            $qra = M('wrk_receivables_account') ->where($qra_condition)->find();
            if (empty($qra)) {
                $branch = M('SysBranch') ->where('id = '.$branch_id)->find();
                $qra_add['branch_id'] = $branch_id;
                $qra_add['name'] = '微信';
                $qra_add['account'] = 'weChat';
                $qra_add['status'] = 1;
                $qra_add['create_time'] = time();
                $qra_add['creater_id'] = $branch['leader_id'] ? $branch['leader_id'] : 0 ;
                $qra_add['update_time'] = time();
                $qra_add['update_id'] = $branch['leader_id'] ? $branch['leader_id'] : 0 ;
                $qra_add['initial_balance'] = 0;
                $qra_add['accumulated_amount'] = 0;
                $qra_add['id'] = M('wrk_receivables_account') ->add($qra_add);
                $qra = $qra_add;
            }
            $amount = floatval($material['account']); //充值金额
            $fee = 0;
            //充值添加
            if ($material['object_type'] == FIN_CIZ_RECHARGE) {
                $recharge["company_id"] = $material['object_id'];
            }
            $recharge['account'] = $material['account'];
            $recharge["user_id"] = $material['creator_id'];
            $recharge["creator_id"] = $material['creator_id'];
            $recharge["branch_id"] = $branch_id;
            $recharge["account"] = $amount;
            $recharge["ctime"] = time();
            $recharge["pay_name"] = $material['object_type'] == FIN_UIZ_RECHARGE ? "个人账户充值(微信业务充值)" :  "公司账户充值(微信业务充值)";;
            $recharge["pay_status"] = 1;
            $recharge["money_type"] = $material['object_type'];
            $recharge["source"] = FIN_PAY_WEIXIN;
            $recharge["pay_code"] = $transaction_id;
            $recharge["pay_time"] = time();
            $recharge["third_fee"] = $fee; //渠道手续费
            $recharge["receivable_id"] = $qra['id'];
            $recharge["order_sn"] = $out_trade_no;
            $recharge_id = M("ComRecharge")->add($recharge);
            $payment =  M("ComRecharge")->where('id = '.$recharge_id)->find();
            //产生对账单
            //新增财务对账单
            $financein['fina_type'] = $payment['money_type']; //从充值类型带过来
            $financein['fina_cash'] = $amount;
            $financein['fina_time'] = time();
            $financein['user_id'] = $payment['user_id'];
            $financein['branch_id'] = $payment['branch_id'];
            $financein['company_id'] = $payment['company_id'];
            $financein['order_sn'] = $out_trade_no;
            $financein['pay_code'] = $transaction_id;
            $financein['third_fee'] = $fee;
            $financein["receivable_id"] = $qra['id'];
            if ($payment['money_type'] == FIN_UIZ_RECHARGE) {  //个人账号充值
                $financein['title'] = '个人账户充值';
                $members = M("SysUser");
                $user_data = $members->where("id=".$payment['user_id'])->find();
                if ($finance_table->data($financein)->add()) {
                    $user_money = floatval($user_data['user_money']) + $amount;
                    $members->where("id=".$payment['user_id'])->setField("user_money", $user_money);
                    //充值成功通知
                    $data["transaction_type"] = '账户充值(成功)';
                    $data["account"] = $amount;
                    $data["pay_time_view"] = date('Y-m-d H:i:s',time());
                    $data["account_balance"] = sprintf('%.2f',$user_money - $user_data['user_money_auditing']);
                    $branch_visiblers = $data;
                    $branch_visiblers['url'] = str_replace('shop','shop'.$branch_id,SHOP_ROOT).'/ComBranchCapital/capitalDetails/id/u:'.$payment['user_id'].'.html';;
                    $jurisdiction =  D('ComAccountJurisdiction');
                    $jurisdiction->setObjectId($data['user_id']);
                    $jurisdiction->setObjectType(2);
                    $jurisdiction->setObjectVarious([CAJ_BRANCH_RECHARGE]);
                    $jurisdiction->getAccountNoticeSendUsers('branch',false);
                    $branch_visiblers['openid'] = $jurisdiction->getStore('branch_visiblers');
                    $data["openid"] = $user_data['openid'];
                    $data['url'] =  str_replace('shop','shop'.$branch_id,SHOP_ROOT). '/Money.html';
                    D('EShop/ComFinance')->sendWxTemplate(TCT_RECHARGE_COMPLETE_NOTICE,$data);
                    D('EShop/ComFinance')->sendWxTemplate(TCT_BRANCH_RECHARGE_COMPLETE_NOTICE,$branch_visiblers);
                }
            } else {  //公司账户充值
                $financein['title'] = '公司账户充值';
                $members = M('SysBranch');
                $company_data = $members->where("id=".$payment['company_id'])->find();
                if ($finance_table->data($financein)->add()) {
                    $company_money = floatval($company_data['money']) + $amount;
                    $members->where("id=".$payment['company_id'])->setField("money", $company_money);
                    $data["transaction_type"] = '账户充值(成功)';
                    $data["account"] = $amount;
                    $data["pay_time_view"] = date('Y-m-d H:i:s',time());
                    $data["account_balance"] = sprintf('%.2f',$company_money - $company_data['money_auditing']);
                    $branch_visiblers = $data;
                    $branch_visiblers['url'] = str_replace('shop','shop'.$branch_id,SHOP_ROOT).'/ComBranchCapital/capitalDetails/id/c:'.$payment['company_id'].'.html';
                    $jurisdiction =  D('ESAdmin/ComAccountJurisdiction');
                    $jurisdiction->setObjectId($payment['company_id']);
                    $jurisdiction->setObjectType(1);
                    $jurisdiction->setObjectVarious([CAJ_BRANCH_CUSTOMER_CAPITAL]);
                    $jurisdiction->getAccountNoticeSendUsers('user');
                    $data['openid'] = $jurisdiction->getStore('user_visiblers');
                    $jurisdiction->setObjectVarious([CAJ_BRANCH_RECHARGE]);
                    $jurisdiction->getAccountNoticeSendUsers('branch',false);
                    $branch_visiblers['openid'] = $jurisdiction->getStore('branch_visiblers');
                    $data['url'] =  str_replace('shop','shop'.$branch_id,SHOP_ROOT) . '/Money/company/id/'.$payment['company_id'].'.html';
                    D('EShop/ComFinance')->sendWxTemplate(TCT_RECHARGE_COMPLETE_NOTICE,$data);
                    D('EShop/ComFinance')->sendWxTemplate(TCT_BRANCH_RECHARGE_COMPLETE_NOTICE,$branch_visiblers);
                }
            }
            $save['accumulated_amount'] = $qra['accumulated_amount'] + $amount;
            M('wrk_receivables_account')->where('id = '.$qra['id'])->data($save)->save();
            $finance_table->commit();
        } catch (\Exception $ex) {
            $finance_table->rollback();
        }
        session(USER_SESSION_KEY, '');
        return true;
    }

//重写回调处理函数
    public function NotifyProcess($data, &$msg) {
        //Log::DEBUG("call back:woaini" . json_encode($data));
        $notfiyOutput = array();

        if (!array_key_exists("transaction_id", $data)) {
            $msg = "输入参数不正确";
            return false;
        }
//查询订单，判断订单真实性
        if (!$this->Queryorder($data["transaction_id"])) {
            $msg = "订单查询失败";
            return false;
        }
        return true;
    }

}
