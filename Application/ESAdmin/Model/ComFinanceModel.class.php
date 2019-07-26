<?php

namespace ESAdmin\Model;

use Think\Log;
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
    public function unlinesAudit($unline_id, $pay_status, $remark,$attach) {
        $payment = D("ComRecharge")->where("id=$unline_id")->find();
        if ($payment['pay_status'] != 0 && !empty($payment['pay_time'])) {
            return "已经审核过，不能再次审核！";
        }
        if ($payment["source"] != FIN_PAY_OFFLINE){ //线下转账
            return "非线下转账充值，无需审核";
        }
        try {
            $this->startTrans();
            //更新交易记录
            $amount = floatval($payment['account']); //充值金额
            $recharge["pay_status"] = $pay_status;
            $recharge["pay_time"]   = time();
            $recharge["message"]     = $remark;
            if ($pay_status == 1) {
                $recharge['receivable_id'] = $attach['origin'];
            }
            $recharge['attach_group'] = $attach['attach_group'];
            D("ComRecharge")->where("id=$unline_id")->save($recharge);
            $user_login_data = session(USER_SESSION_KEY);
            $OrderModal       = D('ComOrder');
            $order_data         = $OrderModal ->where('order_sn = \''.$payment['order_sn'].'\'')->find();
            $order_id = $order_data['id'];
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
                $financein['title'] = '用户担保支付（线下转账）';
                $financein['receivable_id'] = $attach['origin'];
                $financein['attach_group'] = $attach['attach_group'];
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
                $report_table = M('SysReport');
                $data['order_id']    = $order["id"];
                $data['user_id']     = $user_login_data->userId;
                $data['report_time'] = time();
                $data['report_desc'] = '商家确认收款';
                $data['report_service_desc'] = $user_login_data->userName.'确认收款';
                $report_table->data($data)->add();
                $data['report_desc'] = '商家开始服务';
                $data['report_service_desc'] = $user_login_data->userName.'开始服务';
                $report_table->data($data)->add();
                $OrderModal->setOrderTemporaryDete($order["id"]);
                D("ComOrder")->createAgreementByOrder($order['order_sn']);
                //收款账号增加金额
                //收款账户添加
                $receivables = M('wrk_receivables_account')->where('id = '. $financein['receivable_id'])->getField('accumulated_amount');
                $receivables_money = $receivables + $amount;
                M('wrk_receivables_account')->where('id = '. $financein['receivable_id'])->setField("accumulated_amount", $receivables_money);
                //微信消息和系统消息
//                D("ComOrder")->sendWXPayedMessageOrder($order["id"]);
//                D('ComComment')->sendSystemMessageFromUserPayedProduct($order["id"]);
            }else{
                $report_table = M('SysReport');
                $data['order_id']    = $order_id;
                $data['user_id']     = $user_login_data->userId;
                $data['report_time'] = time();
                $data['report_desc'] = '商家未收到款';
                $data['topic'] = 0;
                $data['report_service_desc'] = $user_login_data->userName.'未收到款';
                $report_table->data($data)->add();
            }
            $this->commit();
            return true;
        } catch (\Exception $ex) {
            $this->rollback();
            Log::write('RollBack:'.$ex->getMessage());
            return false;
        }
    }


}
