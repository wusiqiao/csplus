<?php

namespace EShop\Controller;

use Think\Controller;

class ReqQueueController extends Controller {
    protected $_EShop_model = 'EShop';
    private $queueid;

    protected function _initialize() {
        $this->queueid = $_SERVER["HTTP_QUEUEID"];
        $token = $_SERVER["HTTP_TOKEN"];
        $salt = substr($this->queueid, 0, 4) . substr($this->queueid, -1, 4);
        $md5code = md5($salt . $this->queueid);
        if ($md5code != $token) {
            $message = "Error:invalidate request!queueid:".$this->queueid;
            \Think\Log::write($message);
            die($message);
        }
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
            $order = D('ComOrder')->getOrderDetailData($id);
            if($order){
                $the_timer = $this->getTimerNumber($second,'d',true) + $order['update_time'];
                if($order['surety_state'] != ORDER_PAY && $order['order_state'] == ORDER_STATE_USER_BUY && $the_timer <= time()){
                    if($second < 3){
                        //发送微信消息
                        D("ComOrder")->sendWXOrderPayMessage($id,$second);
                        D("ComComment")->sendSysOrderPayMessage($id,$second);
                        $timer = $this->getTimerNumber(1,'d');
                        D('ESAdmin/SysMq')->add_timer($timer,WEB_ROOT.'/ReqQueue/HandleOrderPayMessage/id/'.$id.'/second/'.($second + 1).'.html');
                        $this->reply_success();
                    }elseif ($second == 3){
                        //关闭订单
                        $res = D('ComOrder')->setOrderClose($id,'sys');
                        if($res){
                            D('SysReport')->addOrderReport($id,['desc'=>'客户72小时未付款系统自动关闭订单','user_id'=>$order['user_id']]);
                            //发送微信消息
                            D("ComOrder")->sendWXOrderAutomaticClose($id);
                            D("ComComment")->sendSysOrderAutomaticClose($id);
                            $this->reply_success();
                        }
                    }
                }
            }
        }
        $this->reply_fail();
    }
    /*
     * 处理用户申请结束订单
     */
    public function HandleUserApplyCloseAction($id){
        if ($id > 0){
            $order_data = D('ComOrder')->getOrderDetailData($id);
            if($order_data){
                $the_timer = $this->getTimerNumber(3,'d',true) + $order_data['update_time'];
                if($order_data['surety_state'] == ORDER_PAY && $order_data['order_state'] == ORDER_STATE_APPLY_CLOSE && $the_timer <= time()){
                    $order_data['service_ids']	=	D('ComOrder')->getTheOrderProductServiceId();//服务所属的服务商id
                    $order_data['branch_id']	=	getBrowseBranchId();//服务所属的公司id
                    $result		                =	D('ComFinance')->orderPayConfirm($order_data,'系统自动同意结束订单,订单已完成');
                    if($result){
                        $timer = 72 * 60 * 60;
                        D('ESAdmin/SysMq')->add_timer($timer,WEB_ROOT.'/ReqQueue/HandleSystemFavourableComment/id/'.$id);
                        //记录业务进度
                        $report_table = D('SysReport');
                        $report_table->addOrderReport($id, ['desc'=>'商家72小时未处理系统自动结束订单','user_id'=>$order_data['service_ids'][0]]);
                        //通知topics-0016
                        D('ComOrder')->sendWXOrderCloseHandler($id,'sys');
                        D('ComComment')->sendSysOrderCloseHandler($id,'sys');
                        $this->reply_success();
                    }
                }
            }
        }
        $this->reply_fail();
    }
    /*
     * 处理商家提交完成订单
     */
    public function HandlerBranchCompleteOrderAction($id,$second = 1){
        if($id > 0){
            $order = D('ComOrder')->getOrderDetailData($id);
            if($order){
                $the_timer = $this->getTimerNumber($second,'d',true) + $order['update_time'];
                if($order['surety_state'] == ORDER_PAY && $order['order_state'] == ORDER_STATE_WAITING_CHECK && $the_timer <= time()){
                    if($second < 3){
                        //发送微信消息
                        D("ComOrder")->sendWXCheckFinishMessage($id,$second);
                        D("ComComment")->sendSysCheckFinishMessage($id,$second);
                        $timer = 24 * 60 * 60;
                        D('ESAdmin/SysMq')->add_timer($timer,WEB_ROOT.'/ReqQueue/HandlerBranchCompleteOrder/id/'.$id.'/second/'.($second + 1).'.html');
                        $this->reply_success();
                    }elseif ($second == 3){
                        $order['branch_id']	=	getBrowseBranchId();//服务所属的公司id
                        $result		                =	D('ComFinance')->orderPayConfirm($order,'系统自动同意通过验收,订单已完成');
                        if($result){
                            $timer = 72 * 60 * 60;
                            D('ESAdmin/SysMq')->add_timer($timer,WEB_ROOT.'/ReqQueue/HandleSystemFavourableComment/id/'.$id);
                            //记录业务进度
                            $report_table = D('SysReport');
                            $report_table->addOrderReport($id, ['desc'=>'客户72小时未处理系统自动完成订单','user_id'=>$order['user_id']]);
                            //通知topics-0016
                            D('ComOrder')->sendWXSystemAcceptance($id);
                            D('ComComment')->sendSysSystemAcceptance($id);
                            $this->reply_success();
                        }
                    }
                }
            }
        }
        $this->reply_fail();
    }
    /*
     * 处理未评价
     */
    public function HandleSystemFavourableCommentAction($id){
        if($id > 0){
            $OrderModel             = D("ComOrder");
            $order                  = $OrderModel->where('id = '.$id)->find();
            if($order['order_state'] == ORDER_STATE_WAITING_JUDGE){
                $data['obj_id']         = $id;
                $data['star']           = 5;
                $data['content']        = '很好';
                $data['comment_time']   = time();
                $service_ids            = $OrderModel->getTheOrderProductServiceId();
                $data['service_id']     = $service_ids[0];
                $data['origin_id']      = $order['user_id'];
                $data['user_id']        = $order['user_id'];
                $data['obj_type']       = COMMENTS_OBJERT_TYPE_ORDER;
                M('ComComment')->data($data)->add();
                //修改服务订单状态
                $OrderModel->setOrderState($id,ORDER_STATE_HAS_JUDGE);
                $this->reply_success();
            }
        }
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

    public function WeChatPayNotifyAction($order_sn,$receivables_id,$pay_amount,$balance_amount,$user_id,$branch_id,$sp_ticket_stock_id = null,$reduce_cost = 0) {
        A('ESAdmin/ReqQueue')->WeChatPayAction($order_sn,$receivables_id,$pay_amount,$balance_amount,$user_id,$branch_id,$sp_ticket_stock_id,$reduce_cost) ;
    }

}

