<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class  ComUserCapitalController extends DataController {
    public function listAction()
    {
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $limit ='limit '. ($page_index - 1) * $page_size . ',' . $page_index * $page_size;
        $branch_id = $this->_user_session->parentBranchId;
        $id = $this->_user_session->userId;
        //用户退款（用户资金账户无退款）
        $where1 = "fina.branch_id = ".$branch_id ." and fina.user_id = ".$id." and "." fina.fina_type = '". FIN_USER_REFUND ."'";
        //线下支付 订单现金支付
        $where2 = "fina.branch_id = ".$branch_id ."  and fina.fina_type in (".FIN_ORDER_LINE_PAY.",".FIN_ORDER_PAY.")"." and o.user_id = ".$id;
        //转账至公司
        $where3 = "fina.branch_id = ".$branch_id ."  and fina.fina_type in (".FIN_CIZ_WITHDRAW_FLOW_TO_COMPANY.")"." and fina.user_id = ".$id;
        //用户提现
        $where4 = "fina.branch_id = ".$branch_id ."  and fina.withdrawal_type in (".FIN_UIZ_WITHDRAW.")"." and fina.user_id = ".$id;
        //个人充值 佣金入账
        $where5 = "fina.branch_id = ".$branch_id ."  and fina.money_type in (".FIN_UIZ_RECHARGE.",".FIN_DIZ_RECHARGE.")"." and fina.user_id = ".$id;
        $sql = $this->getUserCapitalSql($where1,$where2,$where3,$where4,$where5);
        $alldata =  M()->query($sql);
        if (isset($_POST['mold'])) {
            if($_POST['mold'] == 1){//收入
                switch ($_POST['income']) {
                    case '1' ://充值
                        $where1 = "fina.fina_id = 0";$where2 = "fina.fina_id = 0";$where3 = "fina.fina_id = 0";$where4 = "fina.id = 0";
                        $where5 = "fina.branch_id = ".$branch_id ."  and fina.money_type in (".FIN_UIZ_RECHARGE.")"." and fina.user_id = ".$id;
                        break;
                    case '2'://佣金
                        $where2 = "fina.fina_id = 0";$where3 = "fina.fina_id = 0";
                        $where5 = "fina.branch_id = ".$branch_id ."  and fina.money_type in (".FIN_DIZ_RECHARGE.")"." and fina.user_id = ".$id;
                        break;
                    default:
                        $where1 = "fina.fina_id = 0";$where2 = "fina.fina_id = 0";$where3 = "fina.fina_id = 0";$where4 = "fina.id = 0";
                        break;
                }
            }elseif ($_POST['mold'] == 2){//支出
                switch ($_POST['pay']) {
                    case '1' ://付款
                        $where1 = "fina.fina_id = 0";$where3 = "fina.fina_id = 0";$where4 = "fina.id = 0";$where5 = "fina.id = 0";
                        break;
                    case '2'://提现
                        $where1 = "fina.fina_id = 0";$where2 = "fina.fina_id = 0";$where3 = "fina.fina_id = 0";$where5 = "fina.id = 0";
                        break;
                    case '3'://转账
                        $where1 = "fina.fina_id = 0";$where2 = "fina.fina_id = 0";$where4 = "fina.id = 0";$where5 = "fina.id = 0";
                        break;
                    default:
                        $where1 = "fina.fina_id = 0";$where5 = "fina.id = 0";
                        break;
                }
            }
        }
        $sql = $this->getUserCapitalSql($where1,$where2,$where3,$where4,$where5);
        $list = M()->query($sql.$limit);
        $account_data = M('SysUser')->field('user_money as money,user_money_auditing as money_auditing') -> where('id = '.$id) ->find();
        $alldata_income = 0;
        $alldata_pay = 0;
        foreach($alldata as $key=>$value) {
            if ($value['state'] == 1 && $value['polarity'] == '+') {
                $alldata_income = sprintf("%.2f",$alldata_income + $value['income_money']);
            } else if ($value['state'] == 1 && $value['polarity'] == '-'){
                $alldata_pay = sprintf("%.2f",$alldata_pay + $value['pay_money']);
            }
            $alldata[$key]['actual_money'] = sprintf("%.2f",$alldata_income-$alldata_pay);
        }
        $all_ids = array_column($alldata,"id");
        foreach ($list as $key =>$value) {
            //$list[$key]['actual_money'] = $alldata[($page_index - 1) * $page_size + $key]['actual_money'];
            $list[$key]['actual_money'] = $alldata[array_search($value['id'],$all_ids)]['actual_money'];
            $list[$key]['state_view'] = $this->getCapitalDetailStateView($value);
            $list[$key]['operation'] = $this->getCapitalDetailOperation($value);
            $list[$key]['attach_group'] = empty($value['attach_group']) ? '' : $value['attach_group'];
            $list[$key]['income_money'] = $value['polarity'] == '+' ? sprintf("%.2f",$value['income_money']) : '';
            $list[$key]['pay_money'] = $value['polarity'] == '-' ? sprintf("%.2f",$value['pay_money']) : '';
            $list[$key]['created_time'] = date('Y/m/d H:i:s',$value['created_time']);
        }
        $result["total"] = count(M()->query($sql));
        $result["rows"] = $list;
        $result["footer"] = [
            ['created_time' => '合计','income_money'=> $alldata_income,'pay_money'=>$alldata_pay,'actual_money'=>sprintf("%.2f",$account_data['money'])]
        ];
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($result));
    }

    public function rechargeAction()
    {
        if (IS_GET) {
           $user = M('sys_user') -> where('id = '.$this->_user_session->userId)->find();
           $store =  M('com_store')->where('branch_id = '.$this->_user_session->parentBranchId)->find();
            $user['attach_group'] = genUniqidKey();
           $this->assign('model',$user);
           $this->assign('store',$store);
           $this->display();
        } else {
            //判断是否有上传备注附件
            $condition['group'] = I('post.attach_group');
            $attachment = M('com_attachment') ->where($condition) ->find();
            if (!$attachment) {
                $this->ajaxReturn(array('error'=>1,'message' =>'请上传备注附件'));
                die;
            }
            $money = I('post.account', '', 'strip_tags');
            $payment = M('com_recharge');
            $orderid = getOrderNo("UIZ_");
            $money_type	=	FIN_UIZ_RECHARGE;
            $pay_name = "个人账户充值(线下转账)";
            $source = FIN_PAY_OFFLINE;
            $payment->attach_group =I('post.attach_group');
            $payment->user_id = $this->_user_session->userId;
            $payment->creator_id = $this->_user_session->userId;
            $payment->branch_id =  getBrowseBranchId();
            $payment->pay_status = 0;
            $payment->order_sn = $orderid;
            $payment->account = $money;
            $payment->ctime = time();
            $payment->pay_name = $pay_name;
            $payment->money_type = $money_type;
            $payment->source = $source;
            $result = $payment->add();
            if ($result) {
                $send_result = D("ComRecharge")->sendCapitalMsgToBranch('comrecharge',$this->_user_session->userId,'user',$money);
                $msg = $send_result['errcode'] == 0 ? '充值申请提交成功':'充值申请提交成功,模板通知发送失败';
                $this->ajaxReturn(array('error'=>0,'message' =>$msg,"result"=>$send_result));
            } else {
                $this->ajaxReturn(array('error'=>1,'message' =>'充值失败'));
            }
        }
    }
    public function transferAction()
    {
        $user = M('sys_user')
            ->alias('user')
            ->field('user.*,bank.id as bank_id,bank.picurl as bank_pic')
            ->join('left join sys_bank_ico as bank on bank.id = user.deposit')
            ->where('user.id = '.$this->_user_session->userId)
            ->find();
        if (IS_GET) {
            $user['workability_money'] = sprintf("%.2f", $user['user_money'] - $user['user_money_auditing']); //账户余额显示扣除掉审核中余额
            $this->assign('model', $user);
            $this->handlerAssignCompany();
            $this->display();
        } else {
            $postdata = I('post.');
            if ($postdata['money'] > $user['user_money']) {
                $this->ajaxReturn(array("error" => "1", "message" => "个人账户余额不足！"));
                exit();
            }
            if(empty($postdata['money']) || $postdata['money'] == 0 || !($postdata['money'] > 0)) {
                $this->ajaxReturn(array("error" => "1", "message" => "请输入大于0的转账金额！"));
                exit();
            }
            if (empty($postdata['company_id']) ) {
                $this->ajaxReturn(array("error" => "1", "message" => "请选择公司！"));
                exit();
            }
            $company = M('SysBranch')->field('money,name,money_auditing')->where('id = '. $postdata['company_id'])->find();
            if (!$company) {
                $this->ajaxReturn(array("error" => "1", "message" => "该公司不存在！"));
                exit();
            }
            //提现记录
            $data = $postdata;
            $data['user_id'] = $this->_user_session->userId;
            $data['order_sn'] = getOrderNo("UWC_");
            $data['create_time'] = time();
            $data['deposit'] = $company['name'];
            $data['company_id'] = $postdata['company_id'];
            $data['branch_id'] = getBrowseBranchId();
            $data['real_money'] = $postdata['money'];
            $data['handle_type'] = '个人转账至公司';
            $data['fee'] = 0; //
            $data["cardholder"] = $user['real_name'];
            $data['status'] = 1;
            $data["withdrawal_type"] = FIN_CIZ_WITHDRAW_FLOW_TO_COMPANY; //业务提现
            $withdrawals_table = M('com_withdrawals');
            try {
                $withdrawals_table -> startTrans();
                $last_id = $withdrawals_table->data($data)->add();
                //用户资金减少
                $money_data['user_money'] =  $user['user_money'] - $postdata['money'];
                M('sys_user')->data($money_data)->where(array('id' => $this->_user_session->userId))->save();
                //公司资金增加
                $company_money_data['money'] = $postdata['money'] + $company['money'];
                M('SysBranch')->data($company_money_data)->where(array('id' => $postdata['company_id']))->save();
                $finance_table = M('ComFinance');
                //存放至Finance
                $financein['fina_type'] = FIN_CIZ_WITHDRAW_FLOW_TO_COMPANY; //从充值类型带过来
                $financein['fina_cash'] = $postdata['money'];
                $financein['fina_time'] = time();
                $financein['user_id'] = $this->_user_session->userId;
                $financein['branch_id'] = $data['branch_id'];
                $financein['company_id'] = $data['company_id'];
                $financein['order_sn'] =  $data['order_sn'];
                $financein['third_fee'] = 0;
                $financein['platform_fee'] = 0;
                $financein['platform_fee'] = 0;
                $financein['title'] = '公司账户充值(转账)';
                $finance_table->data($financein)->add();
                $withdrawals_table->commit();
                //发送转账信息
                $send_data["transaction_type"] = '账户转账(成功)';
                $send_data["account"] = $postdata['money'];
                $send_data["pay_time_view"] = date('Y-m-d H:i:s',time());
                $send_data["account_balance"] = $money_data['user_money'];
                $send_data["openid"] = $user['openid'];
                $send_data['url'] =  str_replace('shop','shop'.getBrowseBranchId(),SHOP_ROOT) . '/Money.html';
                D('EShop/ComFinance')->sendWxTemplate(TCT_USER_INCOME_COMPLETE_NOTICE,$send_data);
                $send_data["transaction_type"] = '账户转账(已到账)';
                $send_data["account"] = $postdata['money'];
                $send_data["pay_time_view"] = date('Y-m-d H:i:s',time());
                $send_data["account_balance"] = $company_money_data['money'];
                $send_data['url'] =  str_replace('shop','shop'.getBrowseBranchId(),SHOP_ROOT) . '/Money/company/id/'.$data['company_id'].'.html';
                $jurisdiction =  D('ESAdmin/ComAccountJurisdiction');
                $jurisdiction->setObjectId($postdata['company_id']);
                $jurisdiction->setObjectType(1);
                $jurisdiction->setObjectVarious([CAJ_BRANCH_CUSTOMER_CAPITAL]);
                $jurisdiction->getAccountNoticeSendUsers('company');
                $send_data['openid'] = $jurisdiction->getStore('company_visiblers');
                D('EShop/ComFinance')->sendWxTemplate(TCT_COMPANY_INCOME_COMPLETE_NOTICE,$send_data);
                die(json_encode(array("error" => "0", "message" => "已提现到公司账号")));
            } catch (\Exception $e) {
                $withdrawals_table->rollback();
                die(json_encode(array("error" => "1", "message" => "提交错误，请联系客服人员。")));
            }
        }
    }
    public function bankAction()
    {
        if (IS_GET){
            $bank_table = M('sys_bank_ico');
            $banks = $bank_table->cache(true)->select();
            $this->assign('banks', $banks);
            $user = M('sys_user')->alias("user")->join("left join sys_bank_ico bank on bank.id=user.deposit")
                ->field("user.*,bank.title")->where(array('user.id' => $this->_user_session->userId))->find();
            $this->assign('model', $user);
            $this->display();
        } else {
            $data = I('post.');
            if (empty($data['code'])) {
                die(json_encode(array("error" => "1", "message" => "请输入手机验证码!")));
            }
            if ($data['code'] == $_SESSION['regcode']) {
                $data['updated_at'] = time();
                $result = M('SysUser')->data($data)->where(array('id' => $this->_user_session->userId))->save();
                if ($result){
                    $bank = M('sys_bank_ico')->where(array('id' => $data['deposit']))->find();
                    die(json_encode(array("error" => "0", "message" => "银行卡绑定成功!","result"=>$bank)));
                } else {
                    die(json_encode(array("error" => "1", "message" => "银行卡绑定失败!")));
                }
            } else {
                die(json_encode(array("error" => "1", "message" => "手机验证码错误!")));
            }
        }
    }
    public function withdrawalAction()
    {
        $user = M('sys_user')
            ->alias('user')
            ->field('user.*,bank.title,bank.id as bank_id,bank.picurl as bank_pic')
            ->join('left join sys_bank_ico as bank on bank.id = user.deposit')
            ->where('user.id = '.$this->_user_session->userId)
            ->find();
        if (IS_GET) {
            $user['workability_money'] = sprintf('%.2f',$user['user_money'] - $user['user_money_auditing']);
            $store =  M('com_store')->where('branch_id = '.$this->_user_session->parentBranchId)->find();
            $user['attach_group'] = genUniqidKey();
            $user['bank_account'] = $user['bank_account'] ? '尾号'.substr($user['bank_account'],-4) : '';
            $this->assign('model',$user);
            $this->assign('store',$store);
            $this->display();
        } else{
            $postdata = I('post.');
            if (empty($user['deposit']) || empty($user['bank_account'])) {
                $this->ajaxReturn(array("error" => "1", "msg" => "基本账户信息未完善，请完善后再操作！", "url" => ""));
                exit();
            }
            if ($postdata['money'] < 100) {
                $this->ajaxReturn(array("error" => "1", "msg" => "提现金额不能低于100！", "url" => ""));
                exit();
            }
            if ($postdata['money'] > ($user['user_money'] - $user['user_money_auditing'])) {
                $this->ajaxReturn(array("error" => "1", "msg" => "个人账户余额不足！", "url" => ""));
                exit();
            }
            $data = $postdata;
            $data['user_id'] = $this->_user_session->userId;
            $data['order_sn'] = getOrderNo("UIW_");
            $data['create_time'] = time();
            $data['deposit'] = $user['title'];
            $data['handle_type'] = '线下转账';
            $data['bank_account'] =  $user['bank_account'];
            $data['branch_id'] = getBrowseBranchId();
            $data['real_money'] = $postdata['money'];
            $data['fee'] = 0;
            $data["cardholder"] = $user['real_name'];
            $data['status'] =  0;
            $data["withdrawal_type"] = FIN_UIZ_WITHDRAW; //业务提现
            $data['bank_address'] = $user['bank_address'];
            $withdrawals_table = M('com_withdrawals');
            try {
                $withdrawals_table->startTrans();
                $last_id = $withdrawals_table->data($data)->add();
                $money_data['user_money_auditing'] = $postdata['money'] + $user['user_money_auditing'];
                M('sys_user')->data($money_data)->where(array('id' => $this->_user_session->userId))->save();
                $withdrawals_table->commit();
                $send_result = D("ComRecharge")->sendCapitalMsgToBranch('ComWithdrawal',$this->_user_session->userId,'user',$postdata['money']);
                $msg = '提现到账时间，以银行实际到账时间为准，一般为T+3个工作日内，请注意查询银行账户。';
                $msg .= $send_result['errcode'] == 0 ? '':'模板通知发送失败';
                $this->ajaxReturn(array("error" => "0", "msg" => $msg));
            } catch (\Exception $e) {
                $withdrawals_table->rollback();
                $this->ajaxReturn(array("error" => "1", "message" => "提交错误，请联系客服人员。"));
            }
        }
    }
    public function WxNativePayAction()
    {
        if (IS_POST) {
            $orderid = getOrderNo("UIZ_");
            $payment_money = I('post.price');
            Vendor("WxPay.WxPayApi");
            Vendor("WxPay.WxPayNative");
            Vendor("WxPay.WxPayNotify");
            Vendor("phpqrcode.phpqrcode");
            Vendor("WxPay.log");
            $notify = new \NativePay();
            $input = new \WxPayUnifiedOrder();
            setPayParams($input);
            $input->SetBody("扫码支付");
            $input->SetAttach("充值");
            $input->SetOut_trade_no($orderid);
            $input->SetTotal_fee($payment_money * 100);
            $input->SetTime_start(date("YmdHis"));
            $input->SetTime_expire(date("YmdHis", time() + 600));
            $input->SetGoods_tag("");
            $input->SetNotify_url(WEB_ROOT . "/WeChatPay/rechargePayNotify/branch_id/".getBrowseBranchId());
            $input->SetTrade_type("NATIVE");
            $input->SetProduct_id($this->_user_session->userId);
            $result = $notify->GetPayUrl($input);
            ob_start();
            \QRcode::png($result["code_url"],false,0,6);
//            \QRcode::png('www.54lynn.cn',false,0,6);
            $imagerStr = base64_encode(ob_get_contents());
            ob_end_clean();
            S('FILE_WxCodePay_'.$orderid,array(
                    'type'=>'file',
                    'object_type'=> 2,
                    'account' =>$payment_money,
                    'object_id'=>$this->_user_session->userId,
                    'creator_id'=>$this->_user_session->userId,
                    'branch_id'=>$this->_user_session->parentBranchId,
            ),array('type'=>'file','expire'=>1800));
            if (IS_POST){
                $this->ajaxReturn(array('error'=>0,'code'=>$imagerStr,'no'=>$orderid));
            }else{
                $this->code_img = $imagerStr;
            };
        }
    }
    public function hasWXRechargeAction()
    {
        if (IS_POST) {
            $order_sn = I('post.sn');
            $recharge = M('com_recharge') ->where('order_sn = \''.$order_sn.'\'')->find();
            if ($recharge) {
                $this->ajaxReturn(array('error'=>0,'message' =>'微信支付成功!'));
            } else {
                $this->ajaxReturn(array('error'=>1));
            }
        }
    }
    public function setAttachGroupAction()
    {
        if (IS_POST) {
            $table = I('post.operation');
            if ($table == 'ComFinance') {
                $condition['fina_id'] = I('post.id');
            } else {
                $condition['id'] = I('post.id');
            }
            $save['attach_group'] = I('post.attach_group');
            D($table)->where($condition)->data($save)->save();
        }
    }
    public function checkAction(){
        $_SESSION['regcode'] = rand(1000, 9999);
        $phone = I('post.phone', '', 'strip_tags');
        $begtime = strtotime(date("Y-m-d"));
        $smsall = D("sms_log")->where("mobile='$phone' and type='重置密码' and begtime='$begtime'")->count();
        if ($smsall > 5) {
            $this->ajaxReturn(array("result" => "1", "message" => "发送失败，您今天短信接收已超量！"));
            exit();
        }
        $returnstatus=D('EShop/SmsLog')->send_sms_message($phone, SMS_BIND_CARD, array("code"=>$_SESSION['regcode']));
        if ($returnstatus == 'Success') {
            $sms_log = D("sms_log");
            $sms_log->type = "更改银行卡";
            $sms_log->mobile = $phone;
            $sms_log->begtime = $begtime;
            $sms_log->add();
            echo json_encode(array("result" => "0", "message" => "验证码已发送到您手机上"));
            exit();
        } else {
            echo json_encode(array("result" => "1", "message" => "发送超时，请联系客服！"));
            exit();
        }

    }
    protected function getCapitalDetailStateView($capital)
    {
        $capital_entity_library = [
            '提现' => ['提现中','提现成功','提现失败'],
            '微信充值' => ['充值失败','充值成功','充值失败'],
            '线下充值' => ['充值中','充值成功','充值失败'],
            '转账' => ['转账中','已转账','转账失败'],
            '佣金' => ['未解冻','已解冻','解冻失败'],
            '退款' => ['退款中','已退款','退款失败'],
            '付款' => ['付款中','已付','付款失败']
        ];
        return $capital['polarity'] === '+' ?
            $capital_entity_library[$capital['income_type']][$capital['state']] :
            $capital_entity_library[$capital['pay_type']][$capital['state']];
    }
    protected function getCapitalDetailOperation($capital)
    {
        if ($capital['polarity'] === '+' && ($capital['income_type'] ==='充值（线下付款）' || $capital['income_type'] ==='充值（微信付款）' || $capital['income_type'] ==='佣金')){
            return 'ComRecharge';
        } elseif ($capital['polarity'] === '-' && $capital['pay_type'] ==='提现') {
            return 'ComWithdrawals';
        } else {
            return 'ComFinance';
        }
    }
    protected function handlerAssignCompany()
    {
        //判断是否有绑定公司
        $condition['user_branch.user_id'] = $this->_user_session->userId;
        $condition['branch.type']    = 1;
        $company_data =  M('SysBranch')
                        ->alias('branch')
                        ->field('name,id')
                        ->join('sys_user_branch as user_branch on user_branch.branch_id = branch.id')
                        ->where($condition)->select();
        $this->assign('companys',$company_data);
    }

    public function getUserCapitalSql($where1,$where2,$where3,$where4,$where5){
        $list_sql1 = D('ComOrder')
            ->setDacFilter('o')
            ->field("fina.fina_id as id,fina.fina_time as created_time,fina.fina_cash as income_money,'' as pay_money,'收入' as detail_type,'退款' as income_type,'' as pay_type,'1' as state,fina.fina_type as money_type,'+' as polarity,fina.attach_group")
            ->join('inner join com_finance as fina on fina.order_sn = o.order_sn')
            ->where($where1)
            ->fetchSql(true)->select();
        $list_sql2 = D('ComOrder')
            ->setDacFilter('o')
            ->field("fina.fina_id as id,fina.fina_time as created_time,'' as income_money,fina.fina_cash as pay_money,'支出' as detail_type,'' as income_type,'付款' as pay_type,'1' as state,fina.fina_type as money_type,'-' as polarity,fina.attach_group")
            ->join('inner join com_finance as fina on fina.order_sn = o.order_sn')
            ->where($where2)
            ->fetchSql(true)->select();
        $list_sql3 = D('ComFinance')
            ->alias('fina')
            ->field("fina.fina_id as id,fina.fina_time as created_time,'' as income_money,fina.fina_cash as pay_money,'支出' as detail_type,'' as income_type,'转账' as pay_type,'1' as state,fina.fina_type as money_type,'-' as polarity,fina.attach_group")
            ->where($where3)
            ->fetchSql(true)->select();
        $list_sql4 = D('ComWithdrawals')
            ->alias('fina')
            ->field("fina.id,fina.create_time as created_time,'' as income_money,fina.money as pay_money,'支出' as detail_type,'' as income_type,'提现' as pay_type,fina.status as state,fina.withdrawal_type as money_type,'-' as polarity,fina.attach_group")
            ->where($where4)
            ->fetchSql(true)->select();
        $list_sql5 = D('ComRecharge')
            ->alias('fina')
            ->field("fina.id,fina.ctime as created_time,fina.account as income_money,'' as pay_money,'收入' as detail_type,( case fina.money_type when ".FIN_UIZ_RECHARGE." then ( if (fina.source = 0,'微信充值','线下充值')) when ".FIN_DIZ_RECHARGE." then '佣金' else '充值' end ) as income_type,'' as pay_type,fina.pay_status as state,fina.money_type,'+' as polarity,fina.attach_group")
            ->where($where5)
            ->fetchSql(true)->select();
        $sql = '( '.$list_sql1 . ') union ('.$list_sql2.') union ('.$list_sql3.') union ('.$list_sql4.') union ('.$list_sql5.')   order by created_time asc ';
        return $sql;
    }

}