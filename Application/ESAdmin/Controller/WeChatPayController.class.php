<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/10
 * Time: 9:41
 */

namespace ESAdmin\Controller;


use Think\Controller;
use Think\Log;

class WeChatPayController extends Controller
{
    public function __construct() {
        parent::__construct();
        Vendor("WxPay.WxPayApi");
        Vendor("WxPay.JsApiPay");
        Vendor("WxPay.WxPayNative");
        Vendor("WxPay.WxPayNotify");
        Vendor("WxPay.log");
    }
    /**
     * 订单付款回调
     */
    public function rechargePayNotifyAction() {
        $this->load_notify_class("RechargePayNotify.class.php");
        $branch_id = I('get.branch_id');
        $options = M("WxConfig")->field('token,appid,appsecret,encoding_aeskey,wx_pay_key,wx_mchid')->where('branch_id = '.$branch_id)->find();
        $notify = new \RechargeNotifyCallBack();
        $notify->setBranchId($branch_id);
        $notify->SetAPPKey($options['wx_pay_key']);
        $notify->Handle(false);
        payLog($notify->GetReturn_msg());
    }
    private function load_notify_class($file) {
        require_once dirname(dirname(__FILE__)) . "/PayNotify/$file";
    }
}