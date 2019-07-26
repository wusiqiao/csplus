<?php

namespace ESAdmin\Model;

use Think\Model;

/**
 * 短信微信消息异步管理，不涉及具体业务。
 */
class SysMqModel extends Model {

    const SMS_HK_ACCOUNT = "gbz6qv";
    const SMS_HK_PASSWORD = "oTj5I3Lb";

    /**
     * 
     * @param type $mobile
     * @param type $tpl
     * @param type $message
     * @param type $sync 异步发送，进入消息列表
     */
    public function send_sms_message($mobile, $tpl, $message, $sync = false, $delay = 0) {
        if (isRemoteServer()) {
            if ($sync && $delay == 0) {
                $delay = 1;
            }
            if ($sync) { //异步
                $package["tpl"] = $tpl;
                $package["mobile"] = $mobile;
                $package["data"] = $message;
                $msg_data["package"] = json_encode($package);
                $msg_data["type"] = "sms";
                $msg_data["md5_key"] = md5($json_data);
                if ($this->where(array("md5_key" => $msg_data["md5_key"]))->count() == 0) { //过滤
                    $result = $this->add($msg_data);
                    if ($result == false) {
                        \Think\Log::write("send_sms_message error:" . $json_data);
                    }
                    $this->add_timer($delay, "/admin.php/ReqQueue/send_sms_message/id/$result");
                } else {
                    \Think\Log::write("短信重复发送:" . $json_data);
                }
            } else {
                return $this->sendsms_internal($mobile, $tpl, $message);
            }
        }
    }
    //是否国内号码
    private function is_domestic_mobile($mobile) {
        $regex = "/^(0|86|17951)?(13[0-9]|15[012356789]|17[678]|18[0-9]|14[57])[0-9]{8}$/";
        if (preg_match($regex, $mobile, $matches)) {
            return true;
        }
        return false;
    }
    /**
     * 发送微信模板消息
     * @param type $data
     * @param type $sync 异步
     */
    public function send_wx_message($data, $sync = false, $delay = 0) {
        //if (isRemoteServer()) {
        if (isRemoteServer()) {
            if ($sync && $delay == 0) {
                $delay = 1;
            }
            if ($sync) {  //异步
                $json_data = json_encode($data);
                $msg_data["package"] = $json_data;
                $msg_data["type"] = "wx";
                $msg_data["md5_key"] = md5($json_data);
                if ($this->where(array("md5_key" => $msg_data["md5_key"]))->count() == 0) { //过滤
                    $result = $this->add($msg_data);
                    if ($result == false) {
                        \Think\Log::write("send_wx_message error:" . $json_data);
                    }
                    $this->add_timer($delay, WEB_ROOT."/ReqQueue/send_wx_message/id/$result");
                } else {
                    \Think\Log::write("微信消息重复发送:" . $json_data);
                }
            } else {
                $this->sendwx_internal($data);
            }
        }
    }
    /**
     * 批量发送微信模板消息
     * @param type $data
     * @param type $sync 异步
     */
    public function send_wx_group_message($data, $sync = true, $delay = 0) {
        //if (isRemoteServer()) {
        if (isRemoteServer()) {
            if ($sync && $delay == 0) {
                $delay = 1;
            }
            if ($sync) {  //异步
                $json_data = json_encode($data);
                $msg_data["package"] = $json_data;
                $msg_data["type"] = "wx";
                $msg_data["md5_key"] = md5($json_data);
                if ($this->where(array("md5_key" => $msg_data["md5_key"]))->count() == 0) { //过滤
                    $result = $this->add($msg_data);
                    if ($result == false) {
                        \Think\Log::write("send_wx_message error:" . $json_data);
                    }
                    $this->add_timer($delay, WEB_ROOT."/ReqQueue/send_wx_group_message/id/$result");
                } else {
                    \Think\Log::write("微信消息重复发送:" . $json_data);
                }
            } else {
                $this->sendwx_template_internal($data);
            }
        }
    }
    /**
     * 发送微信模板消息实现函数
     * @param type $data
     * @return type
     */
    private function sendwx_template_internal($data) {
        $finally['notice_id'] = $data['notice_id'];
        foreach($data['users'] as $key=>$value) {
            $data['message']['openid'] = $value;
            $result = $this->sendwx_internal($data['message']);
            if ($result["errcode"]) {
                $finally['success'][] =  $key;
                \Think\Log::write("微信消息推送：失败:" . $result["errmsg"]);
            } else {
                $finally['error'][] =  $key;
            }
        }
        D('EShop/WxBranchTemplate')->userSendTemplateFinally($finally);
    }
    /**
     * 发送微信模板消息实现函数
     * @param type $data
     * @return type
     */
    private function sendwx_internal($data,$wx_config = null) {
        $wechat = getWeChatInstance($wx_config);
        $message["touser"] = $data["openid"];
        $message["template_id"] = $data["template_id"];
        $message["url"] = $data["url"];
        $message["topcolor"] = "#FF0000";
        $message["data"] = $data["body"];
        if ($data['miniprogram']){
            $message["miniprogram"] = $data["miniprogram"];
        }
        $result["errcode"] = 0;
        if (!$wechat->sendTemplateMessage($message)) {
            $result["errcode"] = $wechat->errCode;
            $result["errmsg"] = $wechat->errMsg;
            \Think\Log::write("send_wx_message error!template_id:" . $message["template_id"]  ." OPENID:".$data["openid"] . "message:" . $wechat->errMsg);
        }
        return $result;
    }
    //回调
    public function process_wx_group_message_byid($id) {
        if ($id) {
            $condition["id"] = $id;
            $condition["state"] = 0;
            $msg = $this->where($condition)->find();
            if ($msg) {
                $package = json_decode($msg["package"], true);
                $finally['notice_id'] = $package['notice_id'];
                $branch_id  = M('wx_notice_template_library')->where('id = '.$finally['notice_id'])->getField('branch_id');
                $wx_config = M("WxConfig")->field('token,appid,appsecret,encoding_aeskey,xcx_appid,xcx_appsecret')->where('branch_id = '.$branch_id)->find();
                if (isset($package['users'])) {
                    foreach($package['users'] as $key=>$value){
                        $package['message']['openid'] = $value['openid'];
                        $result = $this->sendwx_internal($package['message'],$wx_config);
                        if ($result["errcode"] == 0) {
                            $finally['success'][$value['id']] =  ['id' => $value['id'],'errcode'=>$result["errcode"],'errmsg' => $result["errmsg"]];
                            $this->where($condition)->setField("state", 1);
                        } else {
                            $finally['error'][$value['id']] =  ['id' => $value['id'],'errcode'=>$result["errcode"],'errmsg' => $result["errmsg"]];
                            $condition["id"] = $msg["id"];
                            $data["err_code"] = $result["errcode"];
                            $data["err_msg"] = $result["errmsg"];
                            $data["err_time"] = time();
                            $data["state"] = 2; //错误
                            $this->where($condition)->save($data);
                            \Think\Log::write("微信消息推送：失败:" . $result["errmsg"]);
                        }
                    }
                    D('EShop/WxBranchTemplate')->userSendTemplateFinally($finally);
                } else {
                    $url = $package['message']['url'];
                    foreach($package['companys'] as $key=>$value){
                        $package['message']['url'] = $url.$value['keyt'].'/company_id/'.$value['company_id'];
                        $package['message']['openid'] = $value['openid'];
                        $result = $this->sendwx_internal($package['message'],$wx_config);
                        if ($result["errcode"] == 0) {
                            $finally['success'][$value['company_id']] =  ['company_id' => $value['company_id'],'errcode'=>$result["errcode"],'errmsg' => $result["errmsg"]];
                            $this->where($condition)->setField("state", 1);
                        } else {
                            $finally['error'][$value['company_id']] =  ['company_id' => $value['company_id'],'errcode'=>$result["errcode"],'errmsg' => $result["errmsg"]];
                            $condition["id"] = $msg["id"];
                            $data["err_code"] = $result["errcode"];
                            $data["err_msg"] = $result["errmsg"];
                            $data["err_time"] = time();
                            $data["state"] = 2; //错误
                            $this->where($condition)->save($data);
                            \Think\Log::write("微信消息推送：失败:" . $result["errmsg"]);
                        }
                    }
                    D('WxOperateTemplate')->companySendTemplateFinally($finally);
                }

            }
        }
    }
    //回调
    public function process_wx_message_byid($id) {
        if ($id) {
            $condition["id"] = $id;
            $condition["state"] = 0;
            $msg = $this->where($condition)->find();
            if ($msg) {
                $package = json_decode($msg["package"], true);
                $result = $this->sendwx_internal($package);
                if ($result["errcode"]) {
                    $data["err_code"] = $result["errcode"];
                    $data["err_msg"] = $result["errmsg"];
                    $data["err_time"] = time();
                    $data["state"] = 2; //错误
                    $this->where($condition)->save($data);
                    \Think\Log::write("微信消息推送：失败:" . $result["errmsg"]);
                } else {
                    $condition["id"] = $msg["id"];
                    $this->where($condition)->setField("state", 1);
                }
            }
        }
    }
    //测试时显示微信发送模板消息
    public function getMessageForTest($openid) {
        $result = array();
        $list = $this->where("state=0")->select();
        //    echo $openid."--<br>";
        foreach ($list as $key => $value) {
            if ($value["type"] == "wx") {
                $package = json_decode($value["package"], true);
                //echo $package["openid"]."<br>";
                if ($package["openid"] == $openid) {
                    $data["message"] = $package["body"]["first"]["value"] . "<br>" . $package["body"]["keyword1"]["value"] . "<br>" . $package["body"]["keyword2"]["value"]
                            . "<br>" . $package["body"]["keyword3"]["value"] . "<br>" . $package["body"]["remark"]["value"];
                    $data["url"] = $package["url"];
                    $result[] = $data;
                }
            }
        }
        // die(var_export($result, true));
        return $result;
    }
    /**
     * 
     * @param type $delay --秒
     * @param string $url --回调地址
     */
//    public function add_timer($delay, $url, $client = null) {
//        $result = false;
//        if (empty($url)) {
//            \Think\Log::write("url is empty!");
//            return false;
//        }
//        $is_new_client = ($client == null);
//        if ($is_new_client) {
//            $client = new \swoole_client(SWOOLE_SOCK_TCP);
//            //连接到服务器
//            if (!$client->connect(TCP_SERVER_IP, TCP_SERVER_PORT, 0.5)) {
//                \Think\Log::write("connect failed.");
//                return false;
//            }
//        }
//        //插入消息到记录
//        $event_time = time() + $delay;
//        $key = md5($url . $delay);
//        if (!$this->query("select id from sys_req_queue where id='$key'")) {
//            $sql = sprintf("insert sys_req_queue(id,url,event_time) value('%s', '%s', %d)", $key, $url, $event_time);
//            if ($this->execute($sql)) {
//                if (!$client->send($key . "\r\n")) {
//                   \Think\Log::write("client send fail, sql:$sql");
//                   $result = false;
//                }
//            }else{
//                \Think\Log::write("add_timer error:$sql");
//                 $result = false;
//            }
//        }
//        //关闭连接
//        if ($is_new_client) {
//            $client->close();
//        }
//        return $result;
//    }

    public function add_timer($delay, $url, $client = null) {
        $result = false;
        if (empty($url)) {
            \Think\Log::write("url is empty!");
            return false;
        }
        //插入消息到记录
        $event_time = time() + $delay;
        $key = md5($url . $delay);
        if (!$this->query("select id from sys_req_queue where id='$key'")) {
            $sql = sprintf("insert sys_req_queue(id,url,event_time) value('%s', '%s', %d)", $key, $url, $event_time);
            $result = ($this->execute($sql) !== false);
        }
        return $result;
    }
    /**
     * 批量加定时器，效率比单次高
     * @param type $requests array("url"=>"url","delay"=60)
     */
    public function add_timer_batch($requests) {
        if (!is_array($requests)) {
            die("requests is not array!");
        }
        $client = new \swoole_client(SWOOLE_SOCK_TCP);
        //连接到服务器
        if (!$client->connect(TCP_SERVER_IP, TCP_SERVER_PORT, 0.5)) {
            die("connect failed.");
        }
        //插入消息到记录
        foreach ($requests as $value) {
            $this->add_timer($value["delay"], $value["url"], $client);
            usleep(50);
        }
        //关闭连接
        $client->close();
    }


}
