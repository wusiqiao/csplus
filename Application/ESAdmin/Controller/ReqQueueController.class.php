<?php

namespace ESAdmin\Controller;

use Think\Controller;

class ReqQueueController extends Controller {
    protected $_EShop_model = 'EShop';
    private $queueid;

    protected function _initialize() {
        $id = $_SERVER["HTTP_QUEUEID"];
        $token = $_SERVER["HTTP_TOKEN"];
        $this->queueid = $_SERVER["HTTP_QUEUEID"];
        $salt = substr($id, 0, 4) . substr($id, -1, 4);
        $md5code = md5($salt . $id);
        // if ($md5code != $token) {
        //     $message = "Error:invalidate request!queueid:".$this->queueid;
        //     \Think\Log::write($message);
        //     die($message);
        // }
    }
    //发送微信模板消息定时回调
    public function send_wx_messageAction($id) {
        D("SysMq")->process_wx_message_byid($id);
        $this->reply_success();
    }
    public function send_wx_group_messageAction($id)
    {
        D("SysMq")->process_wx_group_message_byid($id);
        $this->reply_success();
    }
     //发送短信消息定时回调
    public function send_sms_messageAction($id) {
        D("SysMq")->process_sms_message_byid($id);
        $this->reply_success();
    }
    //收款客户端微信支付回调
    public function WeChatPayAction($order_sn,$receivables_id,$pay_amount,$balance_amount,$user_id,$branch_id,$sp_ticket_stock_id = null,$reduce_cost = 0) {
        $receivables = M("WrkReceivables")->where(['id' =>$receivables_id])->find();
        //去除重复支付
        $record = M("wrkReceivablesRecord")->where(['order_sn'=>$order_sn,'branch_id'=>$branch_id])->find();
        if ( !empty($record) ) {
            return false;
        }
        //使用优惠卷
        if (!empty($sp_ticket_stock_id)) {
            D("ESAdmin/WrkReceivables")->payByCoupon($receivables_id,$sp_ticket_stock_id,$branch_id,$user_id);
        }
        //使用余额付款的情况
        $company_id =  $receivables['company_id'];
        $sysBranch = M("SysBranch")->where(['id' =>$company_id])->find();
        $sysBranch['balance_amount'] = $sysBranch['money'] - $sysBranch['money_auditing'];
        if ($sysBranch['balance_amount'] >= $balance_amount && $balance_amount > 0) {
            $money = $sysBranch['money'];
            $money = $money - $balance_amount;
            // $notice_data['is_balance'] = 1;
            M("SysBranch")->where(['id' =>$company_id])->save(['money'=>$money]);
            $rst = D("ESAdmin/WrkReceivables")->payByBlance($receivables_id,$balance_amount,$company_id);
            D("ESAdmin/WrkReceivables")->payByTimer($receivables_id,null);
        }
        //生成付款记录
        $account_id = M("wrkReceivablesAccount")->where(['is_wx' =>1,'branch_id'=>$branch_id])->getField('id');
        $record_data['receivables_id'] = $receivables_id;
        $record_data['account_id'] = $account_id;
        $record_data['pay_date'] = time();
        $record_data['pay_amount'] = (float)$pay_amount + (float)$reduce_cost + (float)$balance_amount;
        $record_data['net_amount'] = $pay_amount;
        $record_data['wechat_amount'] = $pay_amount;
        $record_data['coupon_amount'] = $reduce_cost;
        $record_data['balance_amount'] = $balance_amount;
        // $record_data['type'] = 1;
        $record_data['order_sn'] = $order_sn;
        $record_data['created_time'] = time();
        $record_data['updated_time'] = time();
        $record_data['branch_id'] = $branch_id;
        $record_id = M("wrkReceivablesRecord")->add($record_data);

        $accumulated_amount = M("wrkReceivablesAccount")->where(['id'=>$account_id])->getField('accumulated_amount');
        $accumulated_amount = (float)$pay_amount + (float)$accumulated_amount;
            M("wrkReceivablesAccount")->where(['id'=>$account_id])->save(['accumulated_amount'=>$accumulated_amount]);
        
        //修改微信已收款
        $sum = $pay_amount;
        $item = M("WrkReceivablesItem")->where(['receivables_id' =>$receivables_id,'status'=>array('in',[0,1,3])])
        ->order("id asc")->select();
        foreach ($item as $k => $v) {
            $unpay_amount = (float)$v['receivables_amount'] - (float)$v['actual_amount'];
            if ($sum >= $unpay_amount) {
                //已收金额与未收金额之和
                $item_arr['actual_amount'] = $v['receivables_amount'];
                //线下金额增加
                $item_arr['wechat_amount'] = (float)$v['wechat_amount'] + (float)$unpay_amount;
                $item_arr['actual_date'] = time();
                $item_arr['status'] = 2;
                M('WrkReceivablesItem')->where(['id'=>$v['id']])->save($item_arr);
                M('wrk_receivables_item_record')->add([
                    'record_id'=>$record_id,
                    'item_id'=>$v['id']
                ]);
                $sum = (float)$sum - (float)$unpay_amount;
                if ($sum == 0) {break;}
            } else {
                $item_arr['actual_amount'] = (float)$v['actual_amount'] + (float)$sum;
                //线下金额增加
                $item_arr['wechat_amount'] = (float)$v['wechat_amount'] + (float)$sum;
                $item_arr['actual_date'] = time();
                $item_arr['status'] = 1;
                M('WrkReceivablesItem')->where(['id'=>$v['id']])->save($item_arr);
                M('wrk_receivables_item_record')->add([
                    'record_id'=>$record_id,
                    'item_id'=>$v['id']
                ]);
                break;
            }
        }
        //全部款项已收时结束收款计划
        $num = M("WrkReceivablesItem")->where([
                'receivables_id' =>$receivables_id,
                'status'=>array('neq',2)
            ])->count();
        if ($num == 0) {
            M("WrkReceivables")->where(['id' =>$receivables_id])->save(['status'=>1]);
        }
        //微信收款部分增加充值以及消费记录
        D("ESAdmin/WrkReceivables")->addFinanceDetail($pay_amount,$branch_id,$company_id,$order_sn,$account_id);
        D("ESAdmin/WrkReceivables")->addRechargeRecord($pay_amount,$branch_id,$company_id,$order_sn,$account_id,$user_id);
        //更新坏账
        D("ESAdmin/WrkReceivables")->updateBadDept($receivables_id);
        //生成付款通知
        $notice_data['receivables_id'] = $receivables_id;
        $notice_data['pay_amount'] = $pay_amount;
        $notice_data['pay_date'] =  time();
        $notice_data['wechat_amount'] = $pay_amount;
        $notice_data['balance_amount'] = $balance_amount;
        $notice_data['order_sn'] = $order_sn;
        $notice_data['create_time'] = time();
        $notice_data['creater_id'] = $user_id;
        $notice_data['coupon_amount'] = $reduce_cost;
        $notice_data['attach_group'] = I('attach_group');
        $notice_data['branch_id'] = $branch_id;
        $notice_data['status'] = 0;
        $notice_id = M("wrkReceivablesNotice")->add($notice_data);
        //更新商户余额
        $branch_money = M("SysBranch")->where("id = $branch_id")->getField('money');
        M("SysBranch")->where("id = $branch_id")->setField('money',$branch_money+$pay_amount);
        //发送客户缴费通知
        D("ESAdmin/WrkReceivables")->sendWXcustomerPayMessage($receivables_id,$pay_amount,"微信付款",$notice_id);
    }

    private function reply_success() {
        if ($this->queueid) {
            M("SysReqQueue")->where(array("id"=>$this->queueid))->setField("status", 2);
        }
        exit("OK");
    }
    
    private function reply_fail() {
        exit("FAIL");
    }
    /*
     * 处理订单未支付
     */
    public function HandleOrderPayMessageAction($id,$second = 1){
        if($id > 0){
            $order = D($this->_EShop_model.'/ComOrder')->getOrderDetailData($id);
            if($order){
                $the_timer = $this->getTimerNumber($second,'d',true) + $order['update_time'];
                if($order['surety_state'] != ORDER_PAY && $order['order_state'] == ORDER_STATE_USER_BUY && $the_timer <= time()){
                    if($second < 3){
                        //发送微信消息
                        D($this->_EShop_model."/ComOrder")->sendWXOrderPayMessage($id,$second);
                        D($this->_EShop_model."/ComComment")->sendSysOrderPayMessage($id,$second);
                        $timer = $this->getTimerNumber(1,'d');
                        D('SysMq')->add_timer($timer,'ReqQueue/HandleOrderPayMessage/id/'.$id.'/second/'.($second + 1).'.html');
                        $this->reply_success();
                    }elseif ($second == 3){
                        //关闭订单
                        $res = D($this->_EShop_model.'/ComOrder')->setOrderClose($id,'sys');
                        if($res){
                            D($this->_EShop_model.'/SysReport')->addOrderReport($id,['desc'=>'客户72小时未付款系统自动关闭订单','user_id'=>$order['user_id']]);
                            //发送微信消息
                            D($this->_EShop_model."/ComOrder")->sendWXOrderAutomaticClose($id);
                            D($this->_EShop_model."/ComComment")->sendSysOrderAutomaticClose($id);
                            $this->reply_success();
                        }
                    }
                }
            }
        }
        $this->reply_fail();
    }

   /**
     * 输出对应的时间戳 
     * @param number 计数数量
     * @param date   日期类型 s 秒 m 分钟 h 小时 d 天数
     * @param adv    是否空余1秒
     * @date Jan 9,2018
     */
    private function getTimerNumber($number,$date,$adv = false){
        $dates   = strtolower($date);
        $second  = 1;
        $minute  = 60;
        $hour    = 60;
        $day     = 24;
        $date_str= array('s','m','h','d');
        $strtime= array($second,$minute,$hour,$day);
        $sum    = $number;
        if(in_array($dates,$date_str)){
            for ($i=0; $i <= array_search($dates,$date_str); $i++) { 
                $sum *= $strtime[$i];
            }
            if($adv){
                $sum--; 
            }
            return  $sum;
        }else{
            return 1;
        }
    }
    /*
     * 每个月同步生成发送计划 每月1日 00:01 分进行生成
     */
    public function planGeneratorAction()
    {
        $condition['mold'] = 1;
        $branch_ids = M('wx_notice_template_library')->where($condition) ->getField('branch_id',true);
        if ($branch_ids) {
            foreach ($branch_ids as $key => $value) {
                D('SysMq')->add_timer($key + 1, 'ReqQueue/handlerPlanGeneratorAction/id/' . $value . '.html');
            }
        }
        $timer = strtotime('+1 month') - time();
        D('SysMq')->add_timer($timer,'ReqQueue/planGenerator.html');
    }
    public function handlerPlanGeneratorAction($id)
    {
        $condition['mold'] = 1;
        $condition['branch_id'] = $id;
        $notices = M('wx_notice_template_library')->where($condition) ->select();
        foreach($notices as $key =>$value){
            $where['notice_id'] = $value['id'];
            $where['object_type'] = 1;
            $where['use'] = 0;
            unset($value['id']);
            $value['life'] = strtotime(date('Y-m',time()));
            $result = M('wx_notice_template_library')->add($value);
            if ($result) {
                $relation_data = M('wx_notice_relation_user')->where($where) ->select();
                $adds = [];
                if ($relation_data) {
                    foreach($relation_data as $k =>$v){
                        unset($v['id']);
                        $v['notice_id'] = $result;
                        $adds[] = $v;
                    }
                    if ($adds) {
                        M('wx_notice_relation_user')->addAll($adds);
                    }
                };
            }
        }
    }
    /**解冻佣金
     * @param $id 佣金记录ID
     */
    public function unfreezeCommision($id){
        $data["status"] = 1;
        $data["unfrozen_time"] = time();
        M("DistributionIncome")->where("id=$id")->save($data);
        $this->reply_success();
    }

    public function overdueByTimerAction($id) {
        $receivables = M("WrkReceivables")->where(['id'=>$id])->find();
        M('WrkAgreement')->where(['id'=>$receivables['contract_id']])->save(['state'=>2]);
    }

    public function payByTimerAction($id,$receivables_item_id){
        $rst = D("WrkReceivables")->payByTimer($id,$receivables_item_id);
    }

    public function sendMessageByTimerAction($id,$prompt_date_id){
        $rst = D("WrkPrompt")->sendMessageByTimer($id,$prompt_date_id);
    }
}

