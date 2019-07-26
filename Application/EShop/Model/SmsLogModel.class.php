<?php

namespace EShop\Model;

use Think\Model;

class SmsLogModel extends Model {
    const SMS_HK_ACCOUNT = "gbz6qv";
    const SMS_HK_PASSWORD = "oTj5I3Lb";

     function send_sms_message ($mobile, $tpl, $message){
         if ($this->is_domestic_mobile($mobile)) {
             return $this->sendsms_internal_domestic($mobile, $tpl, $message);
         } else {
             return $this->sendsms_internal_foreign($mobile, $tpl, $message);
         }
     }
     protected function is_domestic_mobile($mobile) {
         $regex = "/^(0|86|17951)?(13[0-9]|15[012356789]|17[678]|18[0-9]|14[57])[0-9]{8}$/";
         if (preg_match($regex, $mobile, $matches)) {
             return true;
         }
         return false;
     }
     protected function sendsms_internal_domestic($mobile, $tpl, $message) {
         Vendor("aliyun.TopSdk");
         $client = new \TopClient;
         $signName = "财穗加";
         $client->appkey = "23405826";
         $client->secretKey = "04ebd73fe3bd7e07d5b05cb8ad79c684";
         $req = new \AlibabaAliqinFcSmsNumSendRequest;
         $req->setExtend("123456");
         $req->setSmsType("normal");
         $req->setSmsFreeSignName($signName);
         $message["product"] = $signName;
         $message["code"] = strval($message["code"]);
         $req->setSmsParam(json_encode($message));
         $req->setRecNum($mobile); //手机号
         $req->setSmsTemplateCode($tpl);
         $resp = $client->execute($req);
         if ($resp->code) {
             \Think\Log::write("短信发送失败:" . $resp->sub_msg);
         }
         $res = $resp->code ? $resp->sub_msg : "Success";
         return $res;
     }
     protected function sendsms_internal_foreign($mobile, $tpl, $content) {
         $templates = array(
             SMS_REG_CODE => '验证码${code}，您正在注册成为${product}用户，感谢您的支持！',
             SMS_RESET_PASSWORD_CODE => '您的短信验证码是${code}，您正在重置密码，如非本人操作，请忽略该短信。',
             SMS_INIT_PASSWORD => '您已被添加为业务员，初始登录密码是${code}，如非本人操作，请忽略该短信。',
             SMS_CHANGE_MOBILE_CODE => '您的短信验证码是${code}，您正在修改账户，如非本人操作，请忽略该短信。',
             SMS_PAY_NOTICE => '客户${user}已经付款！请进入财穗快线公众号查看详情。',
             SMS_USR_MESSAGE => '${user}给您留言了，请进入财穗快线公众号查看留言',
             SMS_NEW_ORDER => '有客户发布新需求：${name}，财小二请您进入财穗快线接单咯。',
             SMS_BIND_CARD => '您的短信验证码是${code}，您正在绑定银行卡，如非本人操作，请忽略该短信。'
         );
         $message = $templates[$tpl];
         if ($message) {
             $replaces = array();
             if (preg_match_all("/[$]\{(.*?)\}/i", $message, $matches)) {
                 foreach ($matches[1] as $value) {
                     $replaces[] = $content[$value];
                 }
                 $message = str_replace($matches[0], $replaces, $message);
             }
             $api = "http://api2.santo.cc/submit?command=MT_REQUEST&cpid=" . self::SMS_HK_ACCOUNT . "&cppwd=" . self::SMS_HK_PASSWORD . "&da=$mobile&sm=$message";
             try {
                 $resp = file_get_contents($api);
             } catch (Exception $e) {
                 \Think\Log::write("SMS SEND ERROR:" . $e->getMessage());
                 return "Fail";
             }
             if (preg_match_all('/mterrcode=(\d+)/i', $resp, $matches)) {
                 if ($matches[1][0] == "000") {
                     \Think\Log::write("SMS SEND OK:$resp");
                     return "Success";
                 } else {
                     \Think\Log::write("SMS SEND ERROR:$resp");
                 }
             } else {
                 \Think\Log::write("SMS SEND ERROR:$resp");
             }
         }
         return "Fail";
     }

}
