<?php

namespace EShop\Controller;

use Think\Controller;
use EShop\Model\WxReplyModel;

class WeChatController extends Controller{
    public function indexAction()
    {
        $branch_id = $_GET["bid"];
        $weObj = $this->getWeChatInstance($branch_id);
        $weObj->valid(); //明文或兼容模式可以在接口验证通过后注释此句，但加密模式一定不能注释，否则会验证失败
        $type = $weObj->getRev()->getRevType();
        switch ($type) {
            case \Wechat::MSGTYPE_TEXT:
                if(!$this->textReply($weObj, $branch_id)){
                    $weObj->text("欢迎！")->reply();
                }
                break;
            case \Wechat::MSGTYPE_EVENT:
                $events = $weObj->getRevEvent();
                switch ($events['event']) {
                    case \Wechat::EVENT_SUBSCRIBE: //关注
                        $inviter_id = 0;
                        if ($events["key"] && stripos($events["key"], "inviter_") !== false) { //返回：qrscene_xxxxx
                            $inviter_id = intval(substr($events["key"], 16));
                        }

                        $this->processSubscribeNScan($weObj, $branch_id, $inviter_id);
                        break;
                    case \Wechat::EVENT_SCAN: //扫码，已经关注过的
                        if ($events["key"] && stripos($events["key"], "inviter_") !== false) { //返回：inviter_xxxxx
                            $inviter_id = intval(substr($events["key"], 8));
                            $this->processSubscribeNScan($weObj, $branch_id, $inviter_id);
                        }
                        break;
                    case \Wechat::EVENT_UNSUBSCRIBE://取消关注
                        //D("SysUser")->unsubscribe($weObj->getRevFrom());//用户取消关注
                        D("DistributionRelation")->unSubscribe($weObj->getRevFrom());
                        break;
                    case \Wechat::EVENT_MENU_CLICK:
                        $content = D('WxMenu')->getKeyContent($branch_id, $events['key']);
                        $weObj->text($content)->reply();
                        break;
                    default:
                        break;
                }
                break;
            case \Wechat::MSGTYPE_IMAGE:
                break;
            default:
                $weObj->text("欢迎！")->reply();
        }
    }


    private function textReply($weObj, $branch_id)
    {
        $data = (new WxReplyModel)->searchInteractiveByBranchId($branch_id, $weObj->getRevContent());
        if (empty($data)) {
            return false;
        }

        return $this->sendMessage($weObj, $data);
    }

    private function sendMessage($weObj, $data = [])
    {
        if(empty($data)){
            return false;
        }
        switch (intval($data['reply_type'])) {
            case WxReplyModel::REPLY_TYPE_10 :
                $weObj->text($data['content'])->reply();
                break;
            case WxReplyModel::REPLY_TYPE_20 :
                if (empty($data['child'])) {
                    return false;
                }
                $news = [];
                foreach ($data['child'] as $item) {
                    array_push($news, [
                        'Title' => $item['title'],
                        'Description' => $item['digest'],
                        'PicUrl' => $item['thumb_url'],
                        'Url' => $item['url']
                    ]);
                }
                $weObj->news($news)->reply();
                break;
            case WxReplyModel::REPLY_TYPE_30 :
                $weObj->image($data['content'])->reply();
                break;
        }

        return true;
    }

    private function getWeChatInstance($branch_id)
    {
        static $_tpWeChat = null;
        if (empty($_tpWeChat)) {
            Vendor('Wechat.TPWechat', '', '.class.php');
            $sysParams = M("WxConfig")->field('token,appid,appsecret,encoding_aeskey')->where('branch_id = ' . $branch_id)->find();
            $options = array(
                'token' => $sysParams['token'], //填写你设定的key
                'appid' => $sysParams['appid'],
                'appsecret' => $sysParams['appsecret'],
                'encodingaeskey' => $sysParams['encoding_aeskey'] //填写加密用的EncodingAESKey，如接口为明文模式可忽略
            );
            $_tpWeChat = new \TpWechat($options);
        }
        return $_tpWeChat;
    }

    private function processSubscribeNScan($weObj, $branch_id, $inviter_id)
    {
        D("DistributionRelation")->subscribe($weObj->getRevFrom(), $inviter_id); //关注，设置关注时间，如果有$inviter_id，设置关系
        if ($inviter_id) {
            $result = M("SysUser")->where("id = $inviter_id")->find();
            if ($result["marketing_url"]) {
                $news = array(
                    "0" => array(
                        'Title' => $result['name'],
                        'Description' => $result['marketing_content'],
                        'Url' => $result['marketing_url']
                    )
                );
                $weObj->news($news)->reply();
            }
        }

        $auto_reply = (new WxReplyModel)->findAttentionByBranchId($branch_id);
        $this->sendMessage($weObj, $auto_reply);
//        $auto_reply = M("WxConfig")->where("branch_id=$branch_id")->getField("wx_auto_reply");
//        if ($auto_reply) {
//            $weObj->text($auto_reply)->reply();
//        }
    }
}

?>
