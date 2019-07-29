<?php

namespace EShop\Model;

use Think\Model;

class ComFinanceModel extends Model {
    protected $_MODEL = 'ComFinance';
    protected $_PREFIX= 'com_';
    protected $_SYS_PREFIX = 'sys_';
    protected $_ORDER = 'ComOrder';
    /**
     * 线下审核 -- 需求和服务
     * @param type $unline_id
     * @param type $pay_status
     * @param type $remark
     * @return boolean
     * @date Jan 2 , 2018 Unline Audit For Task And Product
     */
    public function unlinesAudit($unline_id, $pay_status, $remark) {
        $payment = D("ComRecharge")->where("id=$unline_id")->find();
        if ($payment['pay_status'] != 0 && !empty($payment['pay_time'])) {
            return false;
        }
        if ($payment["source"] != FIN_PAY_OFFLINE){ //线下转账
            return false;
        }
        try {
            $this->startTrans();
            //更新交易记录
            $amount = floatval($payment['account']); //充值金额
            $recharge["pay_status"] = $pay_status;
            $recharge["pay_time"]   = time();
            $recharge["remark"]     = $remark;
            $OrderModal       = D('ComOrder');
            D("ComRecharge")->where("id=$unline_id")->save($recharge);
            //审核成功才过账，产生对账单
            if ($pay_status == 1) {
                $user_id = $payment['user_id'];
                $members = D("SysUser");
                $user_data = $members->where("id=$user_id")->find();
                //新增财务对账单
                $finance_table = M($this->_MODEL);
                $financein['fina_type'] = $payment['money_type']; //从充值类型带过来
                $financein['fina_cash'] = $amount;
                $financein['fina_time'] = time();
                $financein['user_id']   = $user_id;
                $financein['user_name'] = $user_data["name"];
                $financein['order_sn'] = $payment["order_sn"];
                $financein['third_fee'] = 0;
                $financein['branch_id'] = $payment['branch_id'];
                //服务

                $order_id         = $OrderModal ->where('order_sn = \''.$payment['order_sn'].'\'')->getField('id');
                $pay_message      = $OrderModal->getOrderTemporaryData($order_id);
                //红包券的使用记录
                if($pay_message['service_ticket_id'] > 0 && $pay_message['service_ticket_id'] != '' && !empty($pay_message['service_ticket_id'])){
                    $ticket[]   =   $pay_message['service_ticket_id'];
                }
                if($pay_message['voucher_ticket_id'] > 0 && $pay_message['voucher_ticket_id'] != '' && !empty($pay_message['voucher_ticket_id'])){
                    $ticket[]   =   $pay_message['voucher_ticket_id'];
                }
                if(!empty($ticket) && count($ticket) > 0){
                    $ticket_stock_table =   M("SpTicketStock");
                    $ticket_stock_data['use_object']    =   TICKET_OBJECT_ORDER;
                    $ticket_stock_data['state']         =   TICKET_CARD_STATE_USED;
                    $ticket_stock_data['used_time']     =   time();
                    $ticket_stock_data['object_id']     =   $order_id;
                    foreach ($ticket as $key => $value) {
                        if($value > 0){
                            $ticket_stock_table->data($ticket_stock_data)->where('id = '.$value)->save();
                        }
                    }
                }
                //红包代金卷信息
                $order_save['voucher_cash']         = $pay_message['voucher_cash'];
                $order_save['service_voucher_cash'] = $pay_message['service_voucher_cash'];
                $order_save['id']                   = $order_id;
                $order_save['surety_state']         = ORDER_PAY;
                $order_save["order_state"]          = ORDER_STATE_SERVICE;
                $order_save['payment_money']        = $pay_message['payment_money'];
                $OrderModal->save($order_save);
                $order            = $OrderModal ->where('order_sn = \''.$payment['order_sn'].'\'')->find();

                $financein['platform_fee'] = 0;
                $financein['title'] = '用户支付（线下转账）';
                $result = $finance_table->data($financein)->add();
                D("ComProduct")->where("id = ".$order['product_id'])->setInc("order_count", 1);
                /*新增 start*/
                //获取用户的上级归属
                if(isToggleDistribution()){
                    $belongs		=	D("SysUser")->getUserBelongData($order['user_id']);
                    if($belongs){
                        $member_bounty_one		=	getMemberBountyOne();
                        $member_bounty_two		=	getMemberBountyTwo();
                        //生成一条记录
                        foreach ($belongs as $key => $value) {
                            $add				=	array();
                            $add['mb_type']		=	MEMBER_BOUNTY_TYPE_ASSURE;
                            $add['on_time']		=	time();
                            $add['object_type']	=	MEMBER_BOUNTY_OBJECT_TYPE_ORDER;
                            $add['object_id']	=	$order['id'];
                            $add['user_id'] 	= 	$order['user_id'];
                            $add['belong_id'] 	= 	$value['salesman_id'];
                            $add['belong_level']= 	$value['level'];
                            $add['branch_id']   =   $payment['branch_id'];
                            if($key == 'one'){
                                $add['mb_fee'] = $order['real_cash'] * $member_bounty_one;
                            }elseif ($key == 'two') {
                                $add['mb_fee'] = $order['real_cash'] * $member_bounty_two;
                            }
                            $add_lists[]	=	$add;
                        }
                        M("ComMemberBounty")->addAll($add_lists);
                    }
                }
                /*新增 end*/
                //记录业务进度
                $report_table = D('SysReport');
                $report_message['desc'] = '商家确认收款';
                $report_message['report_service_desc'] = session('user_name').'确认收款';
                $report_message2['desc'] = '商家开始服务';
                $report_message2['report_service_desc'] = session('user_name').'开始服务';
                $report_table->addOrderReport($order["id"],$report_message);
                $report_table->addOrderReport($order["id"],$report_message2);
                $OrderModal->setOrderTemporaryDete($order["id"]);
//                //微信消息和系统消息
//                D("ComOrder")->sendWXPayedMessageOrder($order["id"]);
//                D('ComComment')->sendSystemMessageFromUserPayedProduct($order["id"]);
                //topics-0008
//                D("ComOrder")->sendWXCompleteUnlineMessage($order['id']);
//                D("ComComment")->sendSysCompleteUnlineMessage($order['id']);
            }else{
                $order        = $OrderModal ->where('order_sn = \''.$payment['order_sn'].'\'')->find();
                $report_table = D('SysReport');
                $report_message['desc'] = '商家未收到款';
                $report_message['report_service_desc'] = session('user_name').'未收到款';
                $report_message['title'] = '待商家确认收款';
                $report_message['topic'] = 0;
                $report_table->addOrderReport($order["id"],$report_message);
                //topics-0007
//                D("ComOrder")->sendWXDontUnlineMessage($order['id']);
//                D("ComComment")->sendSysDontUnlineMessage($order['id']);
            }

            $this->commit();
            return true;
        } catch (\Exception $ex) {
            $this->rollback();
            return false;
        }
    }
    /**
     * 服务退款
     * @date Jan 11,2018
     */
    public function orderRefundConfirm($order) {
        try {
            if ($order["trade_type"] == 1 ){
                return array("code" => 1, "message" => "您的服务是线下结算不能进行退款！");
            }
            if ($order["refund_state"] == TASK_REFUND_STATE_COMPLETE ){
                return array("code" => 1, "message" => "已经确认退款，请勿重复确认！");
            }
            $finance_data = M($this->_MODEL)->where(array("order_sn" => $order["order_sn"]))->find();
            if (!$finance_data) {
                return array("code" => 1, "message" =>"需求托管交易单：" . $order["order_sn"] . "不存在，数据出错！");
            }
            $this->startTrans();
            //获取退款信息
            $refund = M('ComRefund')->where('order_id = '.$order['order_id'])->find();
            //增加服务商资金处理
            unset($finance_data["fina_id"]);
            //客户
            $finance_user                   = $finance_data;
            $finance_user['fina_type']      = FIN_USER_REFUND;
            $finance_user['user_id']        = $order['user_id'];
            $finance_user['fina_cash']      = $refund['user_cash'];
            $finance_user['fina_time']      = time();
            $finance_user['branch_id']      = getBrowseBranchId();
            $finance_user['title']          = '用户需求退款';
            $finance_user['platform_fee']   = 0;
            $finance_user['third_fee']      = 0;
            M($this->_MODEL)->data($finance_user)->add();
            $ids = $this->getBranchIds();
            //服务商
            if($refund['service_cash'] > 0){
                $finance_service               = $finance_data;
                $finance_service['fina_type']  = FIN_ORDER_INCOME;
                $finance_service['user_id']    = $ids[0];
                $finance_service['fina_cash']  = $refund['service_cash'];
                $finance_service['branch_id']  = getBrowseBranchId();
                $finance_service['fina_time']  = time();
                $finance_service['title']      = '用户需求退款';
                $finance_service['platform_fee']= $refund['platform_fee'];
                $finance_service['third_fee']  = 0;
                M($this->_MODEL)->data($finance_service)->add();
            }
            //退款表更新
            $save_refund['update_time'] = time();
            $save_refund['refund_reply']        = '服务商已同意该申请';
            $save_refund['refund_service_reply']= '您已同意该申请';
            $save_refund['finally_service_desc']= '';
            $save_refund['finally_desc']        = '';
            $save_refund['finally_desc'].= '服务商已同意退款'.$refund['user_cash'].'元,请等待服务商转账';
            $save_refund['finally_service_desc'].= '您已同意退款'.$refund['user_cash'].'元,请转账给客户';
            M('ComRefund')->where(array('refund_id' => $refund['id']))->save($save_refund);
            //需求表更新
            $save_task['update_time'] = time();
            $save_task['refund_state']= ORDER_REFUND_STATE_COMPLETE;
            $save_task['order_state']  = ORDER_STATE_CLOSED;
            M('ComOrder')->where(array('id' => $order['order_id']))->save($save_task);
            //更改member_bounty表状态
            $where['object_type']   =   MEMBER_BOUNTY_OBJECT_TYPE_ORDER;
            $where['object_id']     =   $order['order_id'];
            $mb_save['mb_type']     =   MEMBER_BOUNTY_TYPE_FAIL;
            $mb_save['end_time']    =   time();
            $mb_id                  =   M("ComMemberBounty")->where($where)->getField('id',true);
            if($mb_id){
                $mb_where['id']     =   array('in',$mb_id);
                M("ComMemberBounty")->where($mb_where)->save($mb_save);
            }
            //记录业务进度
            $report_table = M('SysReport');
            $data['order_id'] = $order["order_id"];
            $data['user_id']  = $ids[0];
            $data['report_time'] = time();
            $data['report_desc'] ='服务商确认退款,交易关闭';
            $data['report_service_desc'] = '确认退款,交易关闭';
            $report_table->data($data)->add();
            $this->commit();
            return array("code" => 0, "message" => "确认退款完成！");
        } catch (\Exception $ex) {
            $this->rollback();
            \Think\Log::write("付款失败：".$ex->getMessage());
            return array("code" => 1, "message" => "确认退款失败！");
        }
    }
    /**
     * 订单确认完成，付款
     */
    public function orderPayConfirm($order,$title = '用户确认订单完成')
    {
        try {
            if ($order["order_state"] == ORDER_STATE_WAITING_JUDGE || $order["order_state"] == ORDER_STATE_HAS_JUDGE) {
                return array("code" => 1, "message" => "已经确认付款，请勿重复确认！");
            }
            $finance_data = M($this->_MODEL)->where(array("order_sn" => $order["order_sn"]))->find();
            if (!$finance_data) {
                return array("code" => 1, "message" => "订单托管交易单：" . $order["order_sn"] . "不存在，数据出错！");
            }
            $this->startTrans();
            $ids = $this->getBranchIds();
            //确认付款，公司余额中增加金额
            if ($order['trade_type'] == 0) {
                //红包金额
                unset($finance_data["fina_id"]); //一定要释放id,否则add会报错
                $finance_data['fina_type'] = FIN_ORDER_INCOME;
                $finance_data['user_id'] = $ids[0]; //来源是服务商
                $finance_data['fina_cash'] = floatval($finance_data['fina_cash']); //商户收入金额（除去佣金）
                $finance_data['fina_time'] = time();
                $finance_data['branch_id'] = $order['branch_id'];
                $finance_data['title'] = $title;
                $finance_data['third_fee'] = 0;
                M($this->_MODEL)->data($finance_data)->add();
                //服务商收入服务金额
                $condition["id"] = $ids[0];
                M('SysUser')->where($condition)->setInc('user_money', floatval($finance_data['fina_cash']));
                $order_data["order_state"] = ORDER_STATE_WAITING_JUDGE;
                $order_data["update_time"] = time();
                $order_data['finish_time'] =  time();
                M('ComOrder')->where(array('id' => $order['order_id']))->save($order_data);
            }
            //更改member_bounty表状态
            $where['object_type']	=	MEMBER_BOUNTY_OBJECT_TYPE_ORDER;
            $where['object_id']		=	$order['order_id'];
            $mb_save['mb_type'] 	= 	MEMBER_BOUNTY_TYPE_COMPLETE;
            $mb_save['end_time'] 	= 	time();
            $mb_id					=	M("ComMemberBounty")->where($where)->getField('id',true);
            if($mb_id){
                $mb_where['id']		=	array('in',$mb_id);
                M("ComMemberBounty")->where($mb_where)->save($mb_save);
            }
            D("ESAdmin/ComOrder")->unfreezeOrderCommisionFinishWork($order['order_id']);
            $this->commit();
            return array("code" => 0, "message" => "确认付款完成！");
        } catch (\Exception $ex) {
            $this->rollback();
            \Think\Log::write("付款失败：" . $ex->getMessage());
            return array("code" => 1, "message" => "确认付款失败！");
        }
    }
    //**充值未审核,线下的（source=9）才可能未审核，微信支付即时审核
    public function getUserMoneyDetail($user_id,$page=1,$search = 0){
        $branch_id = getBrowseBranchId();
        $limit_amount = 20;
        $limit ='limit '. ($page - 1) * $limit_amount . ',' . $limit_amount;
            $where1 = "fina.branch_id = ".$branch_id ." and fina.user_id = ".$user_id." and "." fina.fina_type = '". FIN_USER_REFUND ."'";
            //$where2 = "fina.branch_id = ".$branch_id ."  and "." fina.fina_type in (".FIN_ORDER_LINE_PAY.",".FIN_ORDER_PAY.")"." and o.user_id = ".$user_id;
            $where3 = "fina.branch_id = ".$branch_id ."  and "." fina.fina_type in (".FIN_CIZ_WITHDRAW_FLOW_TO_COMPANY.")"." and fina.user_id = ".$user_id;
            //$where4 = "fina.branch_id = ".$branch_id ."  and "." fina.withdrawal_type in (".FIN_UIZ_WITHDRAW.")"." and fina.user_id = ".$user_id." and fina.status != 2";
            $where4 = "fina.branch_id = ".$branch_id ."  and "." fina.withdrawal_type in (".FIN_UIZ_WITHDRAW.")"." and fina.user_id = ".$user_id;
            //$where5 = "fina.branch_id = ".$branch_id ."  and "." fina.money_type in (".FIN_UIZ_RECHARGE.",".FIN_DIZ_RECHARGE.")"." and fina.user_id = ".$user_id." and fina.pay_status<>2 ";
            $where5 = "fina.branch_id = ".$branch_id ."  and "." fina.money_type in (".FIN_UIZ_RECHARGE.",".FIN_DIZ_RECHARGE.")"." and fina.user_id = ".$user_id;
            if ($search == 1)
            {
                $where2 = "fina.fina_id = 0";
                $where3 = "fina.fina_id = 0";
                $where4 = "fina.id = 0";
            } else if($search == 2) {
                $where1 = "fina.fina_id = 0";
                $where5 = "fina.id = 0";
            }
            $list_sql1 = D('ComOrder')
                            ->setDacFilter('o')
                            ->field("fina.fina_cash as amount,fina.fina_time as pay_time,fina.title,'' as state,fina.order_sn,fina.fina_type,'+' as polarity,'commission' as icon,'订单收入' as tip")
                            ->join('inner join com_finance as fina on fina.order_sn = o.order_sn')
                            ->where($where1)
                            ->fetchSql(true)->select();
            /*$list_sql2 = D('ComOrder')
                            ->setDacFilter('o')
                            ->field("fina.fina_cash as amount,fina.fina_time as pay_time,fina.title,'' as state,fina.order_sn,fina.fina_type,'-' as polarity,'transfer' as icon,'订单支付' as tip")
                            ->join('inner join com_finance as fina on fina.order_sn = o.order_sn')
                            ->where($where2)
                            ->fetchSql(true)->select();*/
        $list_sql3 = D('ComFinance')
                        ->alias('fina')
                        ->field("fina.fina_cash as amount,fina.fina_time as pay_time,fina.title,'' as state,fina.order_sn,fina.fina_type,'-' as polarity,(case fina.fina_type when ".FIN_CIZ_WITHDRAW." then 'withdrawal' when ".FIN_CIZ_WITHDRAW_FLOW_TO_COMPANY." then 'transfer' end)  as icon,(case fina.fina_type when ".FIN_CIZ_WITHDRAW." then '提现' when ".FIN_CIZ_WITHDRAW_FLOW_TO_COMPANY." then '转账' end)  as tip")
                        ->where($where3)
                        ->fetchSql(true)->select();
        $list_sql4 = D('ComWithdrawals')
                        ->alias('fina')
                        ->field("fina.money as amount,fina.create_time as pay_time,if(fina.status = 0 ,'提现中','') as title,case fina.status when 0 then '提现中' when 2 then '提现失败' else '' end as state,fina.order_sn,fina.withdrawal_type,'-' as polarity,'withdrawal' as icon,'提现' as tip")
                        ->where($where4)
                        ->fetchSql(true)->select();
        $list_sql5 = D('ComRecharge')
                        ->alias('fina')
                        //->field("fina.account as amount,fina.ctime as pay_time,'充值中' as title,if(fina.pay_status = 0 ,'充值中','') else if (fina.pay_status = 2 ,'充值1','') as state,fina.order_sn,fina.money_type,'+' as polarity,(case fina.source when ".FIN_PAY_OFFLINE." then 'unlink-recharge' when ".FIN_PAY_DISTRIBUTION." then 'commission' else 'payment' end )  as icon,( case fina.money_type when ".FIN_DIZ_RECHARGE."  then '佣金入账'  else (case fina.source when ".FIN_PAY_OFFLINE." then '线下转账' else '微信支付' end) end ) as tip")
                        ->field("fina.account as amount,fina.ctime as pay_time,'充值中' as title,case fina.pay_status when 0 then (if( fina.source = 0,'充值失败','充值中')) when 2 then '充值失败' else '' end as state,fina.order_sn,fina.money_type,'+' as polarity,(case fina.source when ".FIN_PAY_OFFLINE." then 'unlink-recharge' when ".FIN_PAY_DISTRIBUTION." then 'commission' else 'payment' end )  as icon,( case fina.money_type when ".FIN_DIZ_RECHARGE."  then '佣金入账'  else (case fina.source when ".FIN_PAY_OFFLINE." then '线下转账' else '微信支付' end) end ) as tip")
                        ->where($where5)
                        ->fetchSql(true)->select();
        $sql = '( '.$list_sql1 . ') union ('.$list_sql3.') union ('.$list_sql4.') union ('.$list_sql5.')   order by pay_time desc '.$limit;
        $list = $this->query($sql);
        return $list;
    }
    //**充值未审核,线下的（source=9）才可能未审核，微信支付即时审核
    public function getCompanyMoneyDetail($company_id,$page,$search = 0){
        $limit_amount = 20;
        $limit ='limit '. ($page - 1) * $limit_amount . ',' . $limit_amount;
        $branch_id = getBrowseBranchId();
//        if(!handleIsManager()){
            //添加充值 提现 转账信息
            $where1 = "fina.branch_id = ".$branch_id ."  and "." fina.fina_type in (".FIN_CIZ_WITHDRAW_FLOW_TO_COMPANY.",".FIN_USER_REFUND.") and fina.company_id = ".$company_id;
            //$where2 = "fina.branch_id = ".$branch_id ."  and "." fina.withdrawal_type in (".FIN_CIZ_WITHDRAW.")"." and fina.company_id = ".$company_id." and fina.status <> 2";
            $where2 = "fina.branch_id = ".$branch_id ."  and "." fina.withdrawal_type in (".FIN_CIZ_WITHDRAW.")"." and fina.company_id = ".$company_id;
            //$where3 = "fina.branch_id = ".$branch_id ."  and "." fina.money_type in (".FIN_CIZ_RECHARGE.")"." and fina.company_id = ".$company_id." and fina.pay_status <> 2 ";
            $where3 = "fina.branch_id = ".$branch_id ."  and "." fina.money_type in (".FIN_CIZ_RECHARGE.")"." and fina.company_id = ".$company_id;
            $where4 = "fina.branch_id = ".$branch_id ."  and "." fina.fina_type in (".FIN_PROMPT_BALANCE_PAY.") and fina.company_id = ".$company_id;

        if ($search == 1) {
                $where2 = "fina.id = 0";
                $where4 = "fina.fina_id = 0";
            } else if ($search == 2) {
                $where1 = "fina.fina_id = 0";
                $where3 = "fina.id = 0";
            }
            $list_sql1 = M('ComFinance')
                ->alias('fina')
                ->field("fina.fina_cash as amount,fina.fina_time as pay_time,'' as state,fina.order_sn,fina.fina_type,'+' as polarity,'transfer'  as icon,if(fina.fina_type = ".FIN_CIZ_WITHDRAW_FLOW_TO_COMPANY." ,'转账','退款')  as tip")
                ->where($where1)
                ->fetchSql(true)->select();
            $list_sql2 = M('ComWithdrawals')
                ->alias('fina')
                //->field("fina.money as amount,fina.create_time as pay_time,if(fina.status = 0 ,'提现中','') as state,fina.order_sn,fina.withdrawal_type,'-' as polarity,'withdrawal' as icon,'提现' as tip")
                ->field("fina.money as amount,fina.create_time as pay_time,case fina.status when 0 then '提现中' when 2 then '提现失败' else '' end as state,fina.order_sn,fina.withdrawal_type,'-' as polarity,'withdrawal' as icon,'提现' as tip")
                ->where($where2)
                ->fetchSql(true)->select();
            $list_sql3 = M('ComRecharge')
                ->alias('fina')
                ->field("fina.account as amount,fina.ctime as pay_time,case fina.pay_status when 0 then '充值中' when 2 then '充值失败' else '' end as state,fina.order_sn,fina.money_type,'+' as polarity,if(fina.source = ".FIN_PAY_OFFLINE." ,'unlink-recharge','payment') as icon,if(fina.source = ".FIN_PAY_OFFLINE." ,'线下充值','微信充值') as tip")
                ->where($where3)
                ->fetchSql(true)->select();
            $list_sql4 = M('ComFinance')
                ->alias('fina')
                ->field("fina.fina_cash as amount,fina.fina_time as pay_time,'' as state,fina.order_sn,fina.fina_type,'-' as polarity,'transfer'  as icon,'余额付款'  as tip")
                ->where($where4)
                ->group("order_sn")
                ->fetchSql(true)->select();
            $sql = '( '.$list_sql1 . ') union ('.$list_sql2.') union ('.$list_sql3.') union ('.$list_sql4.') order by pay_time desc '.$limit;
//            $sql = 'select * from (( '.$list_sql1 . ') union ('.$list_sql2.') union ('.$list_sql3.') order by pay_time desc) b '.$limit;
            $list = $this->query($sql);
//        }

        return $list;
    }

    public function getStaffMoneyDetail($user_id,$page=1,$search = 0){
        $branch_id = getBrowseBranchId();
        $limit_amount = 20;
        $limit ='limit '. ($page - 1) * $limit_amount . ',' . $limit_amount;
        //用户提现
        $where4 = "fina.branch_id = ".$branch_id ."  and fina.withdrawal_type in (".FIN_UIZ_WITHDRAW.")"." and fina.user_id = ".$user_id;
        //个人充值 佣金入账
        $where5 = "fina.branch_id = ".$branch_id ."  and fina.money_type in (".FIN_DIZ_RECHARGE.",".FIN_UIZ_RECHARGE.") and fina.user_id = ".$user_id;
        if ($search == 1)
        {
            $where4 = "fina.id = 0";
        } else if($search == 2) {
            $where5 = "fina.id = 0";
        }
        $list_sql4 = D('ComWithdrawals')
            ->alias('fina')
            ->field("fina.money as amount,fina.create_time as pay_time,if(fina.status = 0 ,'提现中','') as title,case fina.status when 0 then '提现中' when 2 then '提现失败' else '' end as state,fina.order_sn,fina.withdrawal_type,'-' as polarity,'withdrawal' as icon,'提现' as tip")
            ->where($where4)
            ->fetchSql(true)->select();
        $list_sql5 = D('ComRecharge')
            ->alias('fina')
            //->field("fina.account as amount,fina.ctime as pay_time,'充值中' as title,if(fina.pay_status = 0 ,'充值中','') else if (fina.pay_status = 2 ,'充值1','') as state,fina.order_sn,fina.money_type,'+' as polarity,(case fina.source when ".FIN_PAY_OFFLINE." then 'unlink-recharge' when ".FIN_PAY_DISTRIBUTION." then 'commission' else 'payment' end )  as icon,( case fina.money_type when ".FIN_DIZ_RECHARGE."  then '佣金入账'  else (case fina.source when ".FIN_PAY_OFFLINE." then '线下转账' else '微信支付' end) end ) as tip")
            ->field("fina.account as amount,fina.ctime as pay_time,'充值中' as title,case fina.pay_status when 0 then (if( fina.source = 0,'充值失败','充值中')) when 2 then '充值失败' else '' end as state,fina.order_sn,fina.money_type,'+' as polarity,(case fina.source when ".FIN_PAY_OFFLINE." then 'unlink-recharge' when ".FIN_PAY_DISTRIBUTION." then 'commission' else 'payment' end )  as icon,( case fina.money_type when ".FIN_DIZ_RECHARGE."  then '佣金入账'  else (case fina.source when ".FIN_PAY_OFFLINE." then '线下转账' else '微信支付' end) end ) as tip")
            ->where($where5)
            ->fetchSql(true)->select();
        $sql = '( '.$list_sql4 . ') union ('.$list_sql5.') order by pay_time desc '.$limit;
        $list = $this->query($sql);
        return $list;
    }


    //对账单-用户记录
    /**
     *
     * @param type $order
     * @param type $is_cash_pay 是否现金转账（需要第三方渠道手续费），或者余额付款（余额减少）
     * @param type  $transaction_id 转账交易单号
     * @return boolean
     * 服务商的冻结资金为 实际付款金额 + 红包优惠金额 - (订单原本优惠价 * 平台手续费)
     */
    public function orderPay($order, $is_cash_pay = true, $transaction_id = "") {
        $OrderModal	=	D("ComOrder");
        $order['order_id'] = $order['id'];
        $pay_message	=	$OrderModal->getOrderTemporaryData($order['order_id']);
        try {
            if ($order["surety_state"] == 1){
                return array("code" => 1, "message" => "该担保金额已支付，请勿重复支付！");
            }
            //判断是否存在支付信息
            if(!$pay_message){
                return array('error'=>1,'message'=>'数据丢失,请重新操作','url'=>"/Order/service_pay/id/" .$order['order_id'].".html");
            }
            //红包代金卷信息
            $order['payment_money'] = $pay_message['payment_money'];
            $order['voucher_cash']	= $pay_message['voucher_cash'];
            if (!$is_cash_pay) {
                $user = M('ComSys')->where(array('user_id' => $order['user_id']))->find();
                if ($order['payment_money'] > $user['user_money']) {
                    return array("code" => 1, "message" => "可用余额不足！");
                }
            }
            //红包券的使用记录
            if($pay_message['service_ticket_id'] > 0 && $pay_message['service_ticket_id'] != '' && !empty($pay_message['service_ticket_id'])){
                $ticket[]	=	$pay_message['service_ticket_id'];
            }
            if($pay_message['voucher_ticket_id'] > 0 && $pay_message['voucher_ticket_id'] != '' && !empty($pay_message['voucher_ticket_id'])){
                $ticket[]	=	$pay_message['voucher_ticket_id'];
            }
            if(!empty($ticket) && count($ticket) > 0){
                $ticket_stock_table	=	M("SpTicketStock");
                $ticket_stock_data['use_object']	=	TICKET_OBJECT_ORDER;
                $ticket_stock_data['state']			=	TICKET_CARD_STATE_USED;
                $ticket_stock_data['used_time']		=	time();
                $ticket_stock_data['object_id']		=	$order['order_id'];
                foreach ($ticket as $key => $value) {
                    if($value > 0){
                        $ticket_stock_table->data($ticket_stock_data)->where('id = '.$value)->save();
                    }
                }
            }
            $this->startTrans();
            /*新增 start*/
            //获取用户的上级归属
            if(isToggleDistribution()){
                $belongs		=	D("SysUser")->getUserBelongData($order['user_id']);
                if($belongs){
                    $member_bounty_one		=	getMemberBountyOne();
                    $member_bounty_two		=	getMemberBountyTwo();
                    //生成一条记录
                    foreach ($belongs as $key => $value) {
                        $add				=	array();
                        $add['mb_type']		=	MEMBER_BOUNTY_TYPE_ASSURE;
                        $add['on_time']		=	time();
                        $add['object_type']	=	MEMBER_BOUNTY_OBJECT_TYPE_ORDER;
                        $add['object_id']	=	$order['order_id'];
                        $add['user_id'] 	= 	$order['user_id'];
                        $add['belong_id'] 	= 	$value['salesman_id'];
                        $add['belong_level']= 	$value['level'];
                        $add['branch_id']   =   $order['branch_id'];
                        if($key == 'one'){
                            $add['mb_fee'] = $order['real_cash'] * $member_bounty_one;
                        }elseif ($key == 'two') {
                            $add['mb_fee'] = $order['real_cash'] * $member_bounty_two;
                        }
                        $add_lists[]	=	$add;
                    }
                    M("ComMemberBounty")->addAll($add_lists);
                }
            }
            /*新增 end*/
            //确认付款完成 --- 修改订单信息
            $order_data["surety_state"] 		= 	ORDER_PAY;
            $order_data["order_state"] 			= 	ORDER_STATE_SERVICE;
            $order_data["payment_money"] 		= 	$order['payment_money'];
            $order_data['voucher_cash']			=	$pay_message['voucher_cash'];
            $order_data['service_voucher_cash']	=	$pay_message['service_voucher_cash'];
            M('ComOrder')->where(array('id' => $order['order_id']))->save($order_data);
            //收款账号处理 wrk_receivables_account
            $qra_condition['branch_id'] = getBrowseBranchId();
            $qra_condition['name'] = '微信';
            $qra = M('wrk_receivables_account') ->where($qra_condition)->find();
            if (empty($qra)) {
                $branch = M('SysBranch') ->where('id = '.getBrowseBranchId())->find();
                $qra_add['branch_id'] = getBrowseBranchId();
                $qra_add['name'] = '微信';
                $qra_add['account'] = 'weChat';
                $qra_add['status'] = 1;
                $qra_add['create_time'] = time();
                $qra_add['creater_id'] = $branch['leader_id'] ? $branch['leader_id'] : 0 ;;
                $qra_add['update_time'] = time();
                $qra_add['update_id'] = $branch['leader_id'] ? $branch['leader_id'] : 0 ;;
                $qra_add['initial_balance'] = 0;
                $qra_add['accumulated_amount'] = 0;
                $qra_add['id'] = M('wrk_receivables_account') ->add($qra_add);
                $qra = $qra_add;
            }
            //获取服务所属的user_id
            $amount = floatval($order["payment_money"]);//实际支付金额
            $thrid_fee_rate = $is_cash_pay ? getThirdPartyFeeRate() : 0;
            $financein['user_id'] = $order['user_id'];
            $financein['branch_id'] = $order['branch_id'];
            $financein['order_sn'] = $order["order_sn"];
            $financein['fina_cash'] = $amount; //担保付款金额 -- 实际付款金额
            if ($is_cash_pay) {
                $financein['fina_type'] = FIN_ORDER_PAY;
                $financein['title'] = '用户服务交易（现金转账）';
                $financein["pay_code"] = $transaction_id;
            }
            /*新增 end*/
            $financein['third_fee'] = $amount * $thrid_fee_rate;//第三方渠道 -- 实际付款金额 * 第三方渠道手续费
            $financein['fina_time'] = time();
            M("ComFinance")->data($financein)->add();
            //产品订单数量+1
            $product_id = $order["product_id"];
            D("ComProduct")->where("id=$product_id")->setInc("order_count", 1);
            //记录业务进度
            $ids = $this->getBranchIds();
            $report_table = D('SysReport');
            $report_table->addOrderReport($order["order_id"],['desc'=>'客户微信支付成功','user_id'=>$order['user_id']]);
            $report_table->addOrderReport($order["order_id"],['desc'=>'商家开始服务','user_id'=>$ids[0]]);
            $this->commit();
            $OrderModal->setOrderTemporaryDete($order["order_id"]);
            D("ComOrder")->sendWXPayedMessage($order["order_id"]); //托管交易到平台，微信通知
            D('ComComment')->sendSysPayedMessage($order["order_id"]);
            D("ComOrder")->createAgreementByOrder($order['order_sn']);
            $save['accumulated_amount'] = $qra['accumulated_amount'] + $amount;
            M('wrk_receivables_account')->where('id = '.$qra['id'])->data($save)->save();
            return array("code" => 0, "message" => "托管交易成功！");
        } catch (\Exception $ex) {
            $this->rollback();
            \Think\Log::write($ex->getMessage());
            return array("code" => 1, "message" => "托管交易失败！");
        }
    }
    public function rechargePay($payment,$transaction_id)
    {
        $out_trade_no = $payment['order_sn'];
        //更新充值记录
        try {
            $this->startTrans();
            //收款账号处理 wrk_receivables_account
            $qra_condition['branch_id'] = getBrowseBranchId();
            $qra_condition['name'] = '微信';
            $qra = M('wrk_receivables_account') ->where($qra_condition)->find();
            if (empty($qra)) {
                $branch = M('SysBranch') ->where('id = '.getBrowseBranchId())->find();
                $qra_add['branch_id'] = getBrowseBranchId();
                $qra_add['name'] = '微信';
                $qra_add['account'] = 'weChat';
                $qra_add['status'] = 1;
                $qra_add['create_time'] = time();
                $qra_add['creater_id'] = $branch['leader_id'] ? $branch['leader_id'] : 0 ;;
                $qra_add['update_time'] = time();
                $qra_add['update_id'] = $branch['leader_id'] ? $branch['leader_id'] : 0 ;;
                $qra_add['initial_balance'] = 0;
                $qra_add['accumulated_amount'] = 0;
                $qra_add['id'] = M('wrk_receivables_account') ->add($qra_add);
                $qra = $qra_add;
            }

            $amount = floatval($payment['account']); //充值金额
            $fee = 0;
            $recharge["pay_status"] = 1;
            $recharge["pay_code"] = $transaction_id;
            $recharge["pay_time"] = time();
            $recharge["third_fee"] = $fee; //渠道手续费
            $recharge["receivable_id"] = $qra['id'];
            D("ComRecharge")->where("order_sn='$out_trade_no'")->save($recharge);
            //产生对账单
            $user_id = $payment['user_id'];
            //新增财务对账单
            $financein['fina_type'] = $payment['money_type']; //从充值类型带过来
            $financein['fina_cash'] = $amount;
            $financein['fina_time'] = time();
            $financein['user_id'] = $user_id;
            $financein['branch_id'] = $payment['branch_id'];
            $financein['company_id'] = $payment['company_id'];
            $financein['order_sn'] = $out_trade_no;
            $financein['pay_code'] = $transaction_id;
            $financein['third_fee'] = $fee;
            $financein["receivable_id"] = $qra['id'];
            if ($payment['money_type'] == FIN_UIZ_RECHARGE) {  //个人账号充值
                $financein['title'] = '个人账户充值';
                $members = D("SysUser");
                $user_data = $members->where("id=$user_id")->find();
                if ($this->data($financein)->add()) {
                    $user_money = floatval($user_data['user_money']) + $amount;
                    $members->where("id=$user_id")->setField("user_money", $user_money);
                    //充值成功通知
                }
                $data["transaction_type"] = '账户充值(微信付款)';
                $data["account"] = $amount;
                $data["pay_time_view"] = date('Y-m-d H:i:s',time());
                $data["account_balance"] = sprintf('%.2f',$user_money - $user_data['user_money_auditing']);
                $data['user_id'] = $user_id;
                $branch_visiblers = $data;
                $branch_visiblers['url'] = WEB_ROOT.'/ComBranchCapital/capitalDetails/id/u:'.$payment['user_id'].'.html';;
                $jurisdiction =  D('ESAdmin/ComAccountJurisdiction');
                $jurisdiction->setObjectId($user_id);
                $jurisdiction->setObjectType(2);
                $jurisdiction->setObjectVarious([CAJ_BRANCH_RECHARGE]);
                $jurisdiction->getAccountNoticeSendUsers('branch',false);
                \Think\Log::write("短信重复发送:2");
                $branch_visiblers['openid'] = $jurisdiction->getStore('branch_visiblers');
                if(!$branch_visiblers['openid']){
                    $branch_visiblers['openid'] = D("ESAdmin/ComRecharge")->getCapitalLeaderOpenid("comrecharge");
                }
                //$branch_visiblers['account_balance'] = $this->getWxAccountMoney($qra['id']);//获取微信收款账户的余额
                $data["openid"] = $user_data['openid'];
                $data['url'] =  WEB_ROOT . '/Money.html';
                D('ComFinance')->sendWxTemplate(TCT_BRANCH_RECHARGE_COMPLETE_NOTICE,$branch_visiblers);
                D('ComFinance')->sendWxTemplate(TCT_RECHARGE_COMPLETE_NOTICE,$data);
            } else {  //公司账户充值
                $financein['title'] = '公司账户充值';
                $members = D('SysBranch');
                $company_data = $members->where("id=".$payment['company_id'])->find();
                if ($this->data($financein)->add()) {
                    $company_money = floatval($company_data['money']) + $amount;
                    $members->where("id=".$payment['company_id'])->setField("money", $company_money);
                    $data["transaction_type"] = '账户充值(微信付款)';
                    $data["account"] = $amount;
                    $data["pay_time_view"] = date('Y-m-d H:i:s',time());
                    $data["account_balance"] = sprintf('%.2f',$company_money - $company_data['money_auditing']);
                    $branch_visiblers = $data;
                    $branch_visiblers['url'] = WEB_ROOT.'/ComBranchCapital/capitalDetails/id/c:'.$payment['company_id'].'.html';
                    $jurisdiction =  D('ESAdmin/ComAccountJurisdiction');
                    $jurisdiction->setObjectId($payment['company_id']);
                    $jurisdiction->setObjectType(1);
                    $jurisdiction->setObjectVarious([CAJ_BRANCH_CUSTOMER_CAPITAL]);
                    $jurisdiction->getAccountNoticeSendUsers('user');
                    $data['openid'] = $jurisdiction->getStore('user_visiblers');
                    $jurisdiction->setObjectVarious([CAJ_BRANCH_RECHARGE]);
                    $jurisdiction->getAccountNoticeSendUsers('branch',false);
                    $branch_visiblers['openid'] = $jurisdiction->getStore('branch_visiblers');
                    if(!$branch_visiblers['openid']){
                        $branch_visiblers['openid'] = D("ESAdmin/ComRecharge")->getCapitalLeaderOpenid("comrecharge");
                    }
                    //$branch_visiblers['account_balance'] = $this->getWxAccountMoney($qra['id']);//获取微信收款账户的余额
                    $data['url'] =  WEB_ROOT . '/Money/company/id/'.$payment['company_id'].'.html';
                    D('ComFinance')->sendWxTemplate(TCT_RECHARGE_COMPLETE_NOTICE,$data);
                    D('ComFinance')->sendWxTemplate(TCT_BRANCH_RECHARGE_COMPLETE_NOTICE,$branch_visiblers);
                }
            }
            $save['accumulated_amount'] = $qra['accumulated_amount'] + $amount;
            M('wrk_receivables_account')->where('id = '.$qra['id'])->data($save)->save();
            $this->commit();
            return array("code" => 0, "message" => "充值成功！");
        } catch (\Exception $ex) {
            $this->rollback();
            return array("code" => 1, "message" => "充值失败！");
        }
    }
    protected function getBranchIds(){
        return D('SysUser')->getBranchManager('id');
    }
    //微信模板
    public function sendWxTemplate($currency_tip,$data)
    {
        $template_id = getWxTemplateIdByStandardId('OPENTM415437052');
        $message = array();
        $body = array();
        $remark = '感谢使用, 如有问题请及时联系我们!';
        $message["template_id"] = $template_id;
        $message["url"] = $data['url'];
        if(strstr($currency_tip,"branch")){
            $remark = '';
            //$message["url"] = '';
        }
        $body["first"]["value"]    = getWxTemplateCurrencyTip($currency_tip);
        $body["keyword1"]["value"] = $data["transaction_type"];
        $body["keyword2"]["value"] = sprintf("%.2f",$data["account"]);
        $body["keyword3"]["value"] = $data["pay_time_view"];
        $body["keyword4"]["value"] = sprintf("%.2f",$data["account_balance"]);
        $body["remark"]["value"] = $remark;
        $message["body"] = $body;
        if ($data['openid']) {
            if (is_array($data['openid'])) {
                foreach ($data['openid'] as $val){
                    $message["openid"] = $val;
                    send_wx_message($message);
                }
            } else {
                $message["openid"] = $data['openid'];
                send_wx_message($message);
            }
        }
    }

    //获取微信收款账户余额
    public function getWxAccountMoney($account_id){
        $condition = [];
        $model = M('ComFinance');
        $condition['a.fina_type'][] = array('in',[-1,-2,-7,1,2,11,12]);
        $condition['a.receivable_id'] = $account_id;
        //收款账户下的所有记录
        $all_data = $model->alias('a')
            ->field('a.fina_id as id,a.fina_time,a.fina_type,a.fina_cash,a.third_fee,a.platform_fee')
            ->where($condition)->order("a.fina_time asc")->select();
        $actual_money = 0;//实际余额
        foreach($all_data as $k=>$v){
            if($v['fina_type'] > 0){
                $actual_money += ($v['fina_cash']-$v['third_fee']-$v['platform_fee']);
            }else{
                $actual_money -= ($v['fina_cash']-$v['third_fee']-$v['platform_fee']);
            }
        }
        return sprintf("%.2f",$actual_money);
    }
}
