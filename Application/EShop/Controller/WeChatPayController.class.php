<?php

namespace EShop\Controller;

use Think\Controller;
use Think\Log;

class WeChatPayController extends Controller {

    public function __construct() {
        parent::__construct();
        Vendor("WxPay.WxPayApi");
        Vendor("WxPay.JsApiPay");
        Vendor("WxPay.WxPayNotify");
        Vendor("WxPay.log");
    }
    /**
     * 保证金付款,商户充值
     */
    public function rechargePayAction() {
        if (!isMicroMessengerBrower()) {
            $this->error("请在微信内部支付，如有疑问，请联系平台客服！", $_SERVER['HTTP_REFERER']);
            die();
        }
//        $orderid = I('get.id');
        $orderid = str_replace('recharge_','',I('get.id'));
        \Think\Log::write('WXPay0:'.$orderid);
        $payment = D("ComRecharge")->where("order_sn='$orderid' and pay_status=0")->find();
        try {
            $jsApiPay = new \JsApiPay();
            $input = new \WxPayUnifiedOrder();
            setPayParams($input);
            $input->SetBody("账户充值");
            $input->SetAttach("消费");
            $input->SetOut_trade_no($orderid);
            $input->SetTotal_fee($payment['account'] * 100);
            $input->SetTime_start(date("YmdHis"));
            $input->SetTime_expire(date("YmdHis", time() + 600));
            $input->SetGoods_tag("service");
            $input->SetNotify_url(WEB_ROOT . "/WeChatPay/rechargeNotify");
            $input->SetTrade_type("JSAPI");
            $input->SetOpenid($_SESSION['openid']);
            $order = \WxPayApi::unifiedOrder($input);
            $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ($payment['company_id'] > 0 ? U("Money/company/",array('id',$payment['company_id'])) : U("Money"));
            $jsApiParameters = $jsApiPay->GetJsApiParameters($order, getWxPayOptions('wx_pay_key'));
        }catch (\Exception $e) {
//            file_put_contents("./log/wxpay.log", $e->getMessage() . "\r\n", FILE_APPEND);
            $this->error("支付错误，请联系平台客服！", $referurl);
            return;
        }
        $this->assign('yiyi',$payment['company_id'] > 0 ? U("Money/company?id=".$payment['company_id']) : U("Money/index"));
        $this->assign('orderid', $orderid);
        $this->assign('price', $payment['account']);
        $this->assign('jsApiParameters', $jsApiParameters);
        $this->display('rechargePay');
    }
    /**
     * 服务订单付款
     */
    public function orderPayAction() {
        if(strpos(I('get.id'),'_') === false && I('get.id') > 0) {
            if (!isMicroMessengerBrower()) {
                $this->error("请在微信内部支付，如有疑问，请联系平台客服！", $_SERVER['HTTP_REFERER']);
                die();
            }
            $id = I('get.id', '', 'strip_tags');
            $payment = D("ComOrder")->where("id=$id and order_state=" . ORDER_STATE_USER_BUY . ' and surety_state = ' . ORDER_DONT_PAY)->find();
            if ($payment == false) {
                echo "<script language='javascript'>self.location=document.referrer;</script>";
                exit();
            }
            if ($payment) {
                $service = M("ComProduct")->where("id=" . $payment['product_id'])->find();
                $yiyi = 1;
            }
            if ($payment['order_sn'] == '') {
                $orderid = getOrderNo(SERVICE_ORDER_SN);
                $data["order_sn"] = $orderid;
                D("ComOrder")->where("id=$id")->save($data);
            } else {
                $orderid = $payment['order_sn'];
            }
            $pay_message = D('ComOrder')->getOrderTemporaryData($id);
            try {
                $jsApiPay = new \JsApiPay();
                $input = new \WxPayUnifiedOrder();
                setPayParams($input);
                $input->SetBody("托管交易");
                $input->SetAttach("消费");
                $input->SetOut_trade_no($orderid);
                $input->SetTotal_fee($pay_message['payment_money'] * 100);
                $input->SetTime_start(date("YmdHis"));
                $input->SetTime_expire(date("YmdHis", time() + 600));
                $input->SetGoods_tag("");
                $input->SetNotify_url(WEB_ROOT . "/WeChatPay/orderPayNotify/bid/".getBrowseBranchId());
                $input->SetTrade_type("JSAPI");
                $input->SetOpenid($_SESSION['openid']);
                $order = \WxPayApi::unifiedOrder($input);
                $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Order");
                $jsApiParameters = $jsApiPay->GetJsApiParameters($order, getWxPayOptions('wx_pay_key'));
            } catch (\Exception $e) {
//            file_put_contents("./log/wxpay.log", $e->getMessage() . "\r\n", FILE_APPEND);
                $this->error("支付错误，请联系平台客服！", $referurl);
                return;
            }
            $this->assign('id', $id);
            $this->assign('yiyi', $yiyi);
            $this->assign('orderid', $orderid);
            $this->assign('price', $pay_message['payment_money']);
            $this->assign('jsApiParameters', $jsApiParameters);
            $this->display();
        } else if(strpos(I('get.id'),'_') !== false && strpos(I('get.id'),'recharge_') !== false){
            $this->rechargePayAction();
        }

    }

    /*
     * 	短信套餐购买
     * 	@datetime 2017-01-25
     * */

    public function smsSetmealPay() {
        if (!isMicroMessengerBrower()) {
            $this->error("请在微信内部支付，如有疑问，请联系平台客服！", $_SERVER['HTTP_REFERER']);
            die();
        }
        $orderid = I('get.orderid', '', 'strip_tags');
        $payment = M("SmsBuyrecord")->where("id='$orderid' and pay_status=0")->find();
        try {
            $jsApiPay = new \JsApiPay();
            //②、统一下单
            $input = new \WxPayUnifiedOrder();
            setPayParams($input);
            $input->SetBody("服务套餐");
            $input->SetAttach("服务套餐");
            $input->SetOut_trade_no($payment['order_sn']);
            $input->SetTotal_fee($payment['price'] * 100);
            // $input->SetTotal_fee(1);
            $input->SetTime_start(date("YmdHis"));
            $input->SetTime_expire(date("YmdHis", time() + 600));
            $input->SetGoods_tag("fwtc");
            $input->SetNotify_url(WEB_ROOT . "/WeChat.php/WeChatPay/smsSetmealPayNotify");
            $input->SetTrade_type("JSAPI");
            $input->SetOpenid($_SESSION['openid']);
            $order = \WxPayApi::unifiedOrder($input);
            $jsApiParameters = $jsApiPay->GetJsApiParameters($order, getWxPayOptions('wx_pay_key'));
        } catch (\Exception $e) {
            $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("User");
            file_put_contents("./log/wxpay.log", $e->getMessage() . "\r\n", FILE_APPEND);
            $this->error("支付错误，请联系平台客服！", $referurl);
            return;
        }
        $this->assign('id', $orderid);
        $this->assign('orderid', $payment['order_sn']);
        $this->assign('price', $payment['price']);
        $this->assign('jsApiParameters', $jsApiParameters);
        $this->display();
    }

    /**
     * 保证金付款回调
     */
    public function rechargeNotifyAction() {
        $this->load_notify_class("RechargeNotify.class.php");
        $notify = new \RechargeNotifyCallBack();
        $notify->SetAPPKey(getWxPayOptions('wx_pay_key'));
        $notify->Handle(false);
        payLog($notify->GetReturn_msg());
    }

    /**
     * 需求付款回调
     */
    public function taskPayNotify() {
        $this->load_notify_class("TaskPayNotify.class.php");
        $notify = new \TaskPayNotifyCallBack();
        $notify->Handle(false);
        payLog($notify->GetReturn_msg());
    }

    /**
     * 订单付款回调
     */
    public function orderPayNotifyAction() {
        $this->load_notify_class("OrderPayNotify.class.php");
        $notify = new \OrderPayNotifyCallBack();
        $notify->SetAPPKey(getWxPayOptions('wx_pay_key'));
        $notify->Handle(false);
        payLog($notify->GetReturn_msg());
    }

    /**
     * 小程序订单支付回调
     */
    public function miniProgramPayNotifyAction(){
        $this->load_notify_class("MiniProgramPayNotify.class.php");
        $notify = new \OrderPayNotifyCallBack();
        $notify->SetAPPKey(getWxPayOptions('wx_pay_key'));
        $notify->Handle(false);
        payLog($notify->GetReturn_msg());
    }

    /**
     * 购买短信付款回调
     */
    public function smsSetmealPayNotify() {
        $this->load_notify_class("SmsSetmealPayNotify.class.php");
        $notify = new \SmsSetmealPayCallBack();
        $notify->Handle(false);
        payLog($notify->GetReturn_msg());
    }

    private function load_notify_class($file) {
        require_once dirname(dirname(__FILE__)) . "/PayNotify/$file";
    }

    /**
     * 参加活动支付确认
     */
    public function activityPay() {
        if (!isMicroMessengerBrower()) {
            $this->error("请在微信内部支付，如有疑问，请联系平台客服！", $_SERVER['HTTP_REFERER']);
            die();
        }
        $order_sn = I('get.order_sn');
        $payment = D("ChannelPay")->where("order_sn='$order_sn' and pay_status=0")->find();
        $input = new \WxPayUnifiedOrder();
        setPayParams($input);
        $input->SetBody("参加活动充值");
        $input->SetAttach("消费");
        $input->SetOut_trade_no($order_sn);
        $input->SetTotal_fee($payment['amount'] * 100);
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));
        $input->SetGoods_tag("activity");
        $input->SetNotify_url(WEB_ROOT . "/WeChat.php/WeChatPay/activityPayNotify");
        $input->SetTrade_type("JSAPI");
        $input->SetOpenid($_SESSION['openid']);
        $order = \WxPayApi::unifiedOrder($input);
        $jsApiParameters = \JsApiPay::GetJsApiParameters($order, getWxPayOptions('wx_pay_key'));
        $this->assign('order_sn', $order_sn);
        $this->assign('amount', $payment['amount']);
        $spm = $payment["spm"];
        $cskx_code = md5("akunzeng$%@" . "_" . $spm);
        $return_url = sprintf(WEB_ROOT . "/index.php/Channel/index/payed/1/channel_id/%d/spm/%s/cskx_code/%s", $payment["channel_id"], $spm, $cskx_code); //链接签字
        $this->assign('return_url', $return_url);
        $this->assign('jsApiParameters', $jsApiParameters);
        $this->display();
    }

    /**
     * 参加活动回调
     */
    public function activityPayNotify() {
        $this->load_notify_class("ActivityPayNotify.class.php");
        $notify = new \ActivityPayNotifyCallBack();
        $notify->Handle(false);
        payLog($notify->GetReturn_msg());
    }

    /**
     * 企业付零钱
     * http://wx.caisuikx.com/WeChat.php/WeChatPay/Pay/order_no/
     */
    public function Pay() {
        if (!isMicroMessengerBrower()) {
             $this->ajaxReturn("请在微信内部支付，如有疑问，请联系平台客服！");
            die();
        }
        if ($_SESSION['openid']) {
            $condition["openid"] = $_SESSION['openid'];
            $condition["money"] = array("GT", 0);
            $condition["withdrawals"] = 0;
            if ($data = M("TestUserScore")->where($condition)->find()) {
                $input = new \WxPayTransfer();
                $input->SetAppid(getWxPayOptions('appid')); //公众账号ID
                $input->SetMch_id(getWxPayOptions('wx_mchid')); //商户号
                $input->SetAPPKey(getWxPayOptions('wx_pay_key'));
                $input->SetAmount($data['money'] * 100);
                $input->SetCheck_name("NO_CHECK");
                $input->SetOpenid($_SESSION['openid']);
                $input->SetSpbill_create_ip(get_client_ip());
                $input->SetPartner_trade_no(md5($_SESSION['openid']));
                $input->SetDesc("王者会计-奖金发放");
                $order_result = \WxPayApi::transferpay($input);
                if ($order_result["return_code"] == "SUCCESS") {
                    if ($order_result["result_code"] == "SUCCESS") {
                        M("TestUserScore")->where("id=".$data["id"])->setField("withdrawals", 1);
                         \Think\Log::write(json_encode($order_result, JSON_UNESCAPED_UNICODE));
                        $this->ajaxReturn("奖金已经发放到您的零钱，请查收");
                    } else {
                        \Think\Log::write(json_encode($order_result, JSON_UNESCAPED_UNICODE));
                        $this->ajaxReturn("奖金支付失败，请联系客服处理");
                    }
                } else {
                    $this->ajaxReturn("网络通信失败，请联系客服处理");
                }
            }else{
               $this->ajaxReturn("不符合领取条件"); 
            }
        } else {
            $this->ajaxReturn("无效领奖请求");
        }
    }
    
    /**
     * 企业付零钱(指定openid)
     * http://wx.caisuikx.com/WeChat.php/WeChatPay/Pay/order_no/
     */
    public function Pay1($openid, $money) {
//        if (!isMicroMessengerBrower()) {
//             $this->ajaxReturn("请在微信内部支付，如有疑问，请联系平台客服！");
//            die();
//        }        
        $user_id = $_SESSION["user_id"]; //管理员
        if ($openid && $money <=100 && $user_id == 2) {
            $condition["openid"] = $openid;
            $condition["prizecode"] = array("exp", "is not null");
            $condition["withdrawals"] = array("neq", 2);
            if ($data = M("TestUserScore")->where($condition)->find()) {
                $input = new \WxPayTransfer();
                $input->SetAppid(getWxPayOptions('appid')); //公众账号ID
                $input->SetMch_id(getWxPayOptions('wx_mchid')); //商户号
                $input->SetAPPKey(getWxPayOptions('wx_pay_key'));
                $input->SetAmount($money * 100);
                $input->SetCheck_name("NO_CHECK");
                $input->SetOpenid($openid);
                $input->SetSpbill_create_ip(get_client_ip());
                $input->SetPartner_trade_no(md5(time()));
                $input->SetDesc("王者会计-奖金发放");
                $order_result = \WxPayApi::transferpay($input);
                if ($order_result["return_code"] == "SUCCESS") {
                    if ($order_result["result_code"] == "SUCCESS") {
                        M("TestUserScore")->where("id=".$data["id"])->setField("withdrawals", 2);
                         \Think\Log::write(json_encode($order_result, JSON_UNESCAPED_UNICODE));
                        $this->ajaxReturn("奖金已经发放到您的零钱，请查收");
                    } else {
                        \Think\Log::write(json_encode($order_result, JSON_UNESCAPED_UNICODE));
                        $this->ajaxReturn("奖金支付失败，请联系客服处理");
                    }
                } else {
                    $this->ajaxReturn("网络通信失败，请联系客服处理");
                }
            }else{
               $this->ajaxReturn("不符合领取条件"); 
            }
        } else {
            $this->ajaxReturn("无效领奖请求,user_id:".$user_id);
        }
    }

    /**
     * 小程序支付
     */
    public function miniPayAction() {
        $openid=I('post.openid');
        $money=I('post.money');
        $orderSn=I('post.orderSn');
        try {
            $jsApiPay = new \JsApiPay();
            $input = new \WxPayUnifiedOrder();
            miniPayParams($input);
            $input->SetBody("托管交易");
            $input->SetAttach("消费");
            $input->SetOut_trade_no($orderSn);
            $input->SetTotal_fee($money * 100);
            $input->SetTime_start(date("YmdHis"));
            $input->SetTime_expire(date("YmdHis", time() + 600));
            $input->SetGoods_tag("");
            $input->SetNotify_url(WEB_ROOT . "/WeChatPay/orderPayNotify/bid/".getBrowseBranchId());
            $input->SetTrade_type("JSAPI");
            $input->SetOpenid($openid);
            $order = \WxPayApi::unifiedOrder($input);
            $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Order");
            $jsApiParameters = $jsApiPay->GetJsApiParameters($order, getWxPayOptions('wx_pay_key'));
            echo  json_encode($jsApiParameters);
        } catch (\Exception $e) {
//            file_put_contents("./log/wxpay.log", $e->getMessage() . "\r\n", FILE_APPEND);
//            echo  json_encode($e->getMessage());
            $this->error("支付错误，请联系平台客服！", $referurl);
            return;
        }
    }

    /**
     * 参加活动回调
     */
    public function PayNotify() {
        $this->load_notify_class("ActivityPayNotify.class.php");
        $notify = new \ActivityPayNotifyCallBack();
        $notify->Handle(false);
        payLog($notify->GetReturn_msg());
    }

    public function receivablesPayAction($id,$order_sn,$total_fee,$balance_amount = 0,$reduce_cost = 0,
                                         $sp_ticket_stock_id = null) {
        if (!isMicroMessengerBrower()) {
            $this->error("请在微信内部支付，如有疑问，请联系平台客服！", $_SERVER['HTTP_REFERER']);
            die();
        }
        $branch_id = getBrowseBranchId();
        $url = str_replace('shop','shop'.getBrowseBranchId(),SHOP_ROOT)."/WeChatPay/receivablesNotify/order_sn/".$order_sn."/receivables_id/".$id."/pay_amount/".$total_fee."/balance_amount/".$balance_amount."/user_id/".$_SESSION['user_id']."/branch_id/".$branch_id;
        if (!empty($sp_ticket_stock_id)) {
            $url = $url."/reduce_cost/".$reduce_cost."/sp_ticket_stock_id/".$sp_ticket_stock_id;
        }
        try {
            $jsApiPay = new \JsApiPay();
            $input = new \WxPayUnifiedOrder();
            setPayParams($input);
            $input->SetBody("消费");
            $input->SetAttach("托管交易");
            $input->SetOut_trade_no($order_sn);
            $input->SetTotal_fee($total_fee*100);
            $input->SetTime_start(date("YmdHis"));
            $input->SetTime_expire(date("YmdHis", time() + 600));
            $input->SetGoods_tag("service");
            $input->SetNotify_url($url);
            $input->SetTrade_type("JSAPI");
            $input->SetOpenid($_SESSION['openid']);
            $order = \WxPayApi::unifiedOrder($input);
            $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("WrkReceivables/customer/id/$id");
            $jsApiParameters = $jsApiPay->GetJsApiParameters($order, getWxPayOptions('wx_pay_key'));
        }catch (\Exception $e) {
//            file_put_contents("./log/wxpay.log", $e->getMessage() . "\r\n", FILE_APPEND);
            $this->error("支付错误，请联系平台客服！", $referurl);
            return;
        }
        //$this->assign('yiyi',$payment['company_id'] > 0 ? U("Money/company?id=".$payment['company_id']) : U("Money/index"));
        $this->assign('id', $id);
        $this->assign('orderid', $order_sn);
        $this->assign('price', $total_fee);
        $this->assign('jsApiParameters', $jsApiParameters);
        $this->display('WrkReceivables/wechatPay');
    }

    //移动端客户收款缴费回调
    public function receivablesNotifyAction($order_sn,$receivables_id,$pay_amount,$balance_amount,$user_id,$branch_id,$sp_ticket_stock_id = null,$reduce_cost = 0) {
        A("ESAdmin/ReqQueue")->WeChatPayAction($order_sn,$receivables_id,$pay_amount,$balance_amount,$user_id,$branch_id,$sp_ticket_stock_id,$reduce_cost);
    }



}
