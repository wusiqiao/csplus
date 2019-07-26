<?php
namespace EShop\Controller;

use ESAdmin\Model\WxPlatformConfigModel;
use EShop\Model\WxReplyModel;
use EShop\Model\WechatNoticeModel;
use EShop\Model\PlatformModel;
use Think\Log;
/**
 * @author kcg
 * 第三方平台 消息通知 控制器
 */
class WeChatNoticeController{
    const WECHAT_RESPONSE = 'success';
    //授权事件类型
    const AUTHOR_INFO_TYPE_COMPONENT_VERIFY_TICKET = 'component_verify_ticket';
    const AUTHOR_INFO_TYPE_AUTHORIZED = 'authorized'; //授权成功通知
    const AUTHOR_INFO_TYPE_UNAUTHORIZED = 'unauthorized'; //取消授权通知
    const AUTHOR_INFO_TYPE_UPDATEAUTHORIZED = 'updateauthorized'; //授权更新通知

    public $appid = null;
    public function __construct($config = []){
        $this->_config = WxPlatformConfigModel::getConfig();
    }

    /**
     * 授权事件接收URL
     */
    public function authorAction(){
        $res = $this->getReceive();
        switch ($res['InfoType']) {
            case self::AUTHOR_INFO_TYPE_COMPONENT_VERIFY_TICKET :
                $this->eventVerifyTicket($res['ComponentVerifyTicket']);
                break;
            case self::AUTHOR_INFO_TYPE_AUTHORIZED:
                $this->eventAuthorizer($res);
                break;
            case self::AUTHOR_INFO_TYPE_UNAUTHORIZED :
                $this->eventUNAuthorizer($res);
                break;
            case self::AUTHOR_INFO_TYPE_UPDATEAUTHORIZED :
                $this->eventAuthorizer($res);
                break;
        }

        self::weChatResponse();
    }

    /**
     * 消息与事件接收URL
     * @param $appid string 消息产生的公众号或者小程序的appid
     */
    public function messageAction(){
        $receive = $this->getReceive();
        $this->appid = $appid   = $_REQUEST['appid'];
        $weObj = $this->getWeChatInstance($appid, $receive);
        $branch_id = $this->_branchId;
        define('M_SHOP_ID', $branch_id);
        $type = $weObj->getRevType();
        switch ($type) {
            case \Wechat::MSGTYPE_TEXT:
                if(!$this->textReply($weObj, $branch_id)){
                    $this->sendMessage($weObj->xml_encode($weObj->text("欢迎！")->getReplyMsg()));
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
                            if(!$this->processSubscribeNScan($weObj, $branch_id, $inviter_id)){
                                $this->sendMessage($weObj->xml_encode($weObj->text("欢迎！")->getReplyMsg()));
                            }
                        }
                        break;
                    case \Wechat::EVENT_UNSUBSCRIBE://取消关注
                        D("SysUser")->unsubscribe($weObj->getRevFrom());//用户取消关注
                        D("DistributionRelation")->unSubscribe($weObj->getRevFrom());
                        break;
                    case \Wechat::EVENT_MENU_CLICK:
                        $content = D('WxMenu')->getKeyContent($branch_id, $events['key']);
                        $this->sendMessage($weObj->xml_encode($weObj->text($content)->getReplyMsg()));
                        break;
                    default:
                        break;
                }
                break;
            case \Wechat::MSGTYPE_IMAGE:
                break;
            default:
                $this->sendMessage($weObj->xml_encode($weObj->text("欢迎！")->getReplyMsg()));
        }
    }

    private function getWeChatInstance($appid, $receive){
        static $_tpWeChat = null;
        if (empty($_tpWeChat)) {
            Vendor('Wechat.TPWechat', '', '.class.php');
            $sysParams = M("WxConfig")->field('id,branch_id,token,appid,appsecret,encoding_aeskey,is_author,authorizer_refresh_token')->where(['appid' => $appid ])->find();
            if(empty($sysParams)){
                return false;
            }

            $this->_branchId = $sysParams['branch_id'];
            $options = array(
                'token' => $sysParams['token'], //填写你设定的key
                'appid' => $sysParams['appid'],
                'appsecret' => $sysParams['appsecret'],
                'encodingaeskey' => $sysParams['encoding_aeskey'], //填写加密用的EncodingAESKey，如接口为明文模式可忽略
                'is_author' => $sysParams['is_author'],
                'authorizer_refresh_token' => $sysParams['authorizer_refresh_token'],
                'receive' => $receive,
                'id' => $sysParams['id'],
            );

            $_tpWeChat = new \TpWechat($options);
        }

        return $_tpWeChat;
    }
    private function getReceive(){
        $config = $this->_config;
        if (empty($config)) {
            $this->errorLog('读取三方平台配置失败!');
        }

        $msgSignature = $_REQUEST['msg_signature'];
        $timestamp = $_REQUEST['timestamp'];
        $nonce = $_REQUEST['nonce'];
        $xml = self::postXml();
        $model = new WechatNoticeModel($config['token'], $config['key'], $config['appid']);
        $receive = $model->decryptMsg($msgSignature, $timestamp, $nonce, $xml);
        file_put_contents('./Runtime/xml.txt', $xml);
        file_put_contents('./Runtime/get.txt','appid=' . $_REQUEST['appid'] . '&msg_signature=' . $msgSignature . '&timestamp=' . $timestamp . '&nonce=' . $nonce);
        if (!$receive) {
            file_put_contents('./Runtime/error.txt',$model->getError() );
            $this->errorLog($model->getError());
        }

        return $this->_receive = $receive;
    }
    private function eventVerifyTicket($verifyTicket){
        $data['verify_ticket'] = $verifyTicket;
        WxPlatformConfigModel::updateThat($data);
        $model = new  PlatformModel($this->_config);
        if(! $model->existsPlatformAccessToken()){
            $model->refreshPlatformAccessToken($verifyTicket);
        }
    }
    /**
     * 授权事件
     * */
    private function eventAuthorizer($res){
        if (!M('WxConfig')->where(['appid' => $res['AuthorizerAppid']])->find()) {
            $this->errorLog('公众号' . $res['AuthorizerAppid'] . '未开通！');
        }

        $model = new PlatformModel($this->_config);
        $res = $model->queryAuth($res['AuthorizationCode']);
        if (!$res) {
            return $this->errorLog('获取失败!' . $res['AuthorizerAppid']);
        }

        $data = $res['authorization_info'];
        $appid = $data['authorizer_appid'];
        $authorizer_access_token = $data['authorizer_access_token'];
        $authorizer_refresh_token = $data['authorizer_refresh_token'];
        $author = [];
        foreach ($data['func_info'] as $func_info) {
            array_push($author, $func_info['funcscope_category']['id']);
        }

        sort($author);
        $update = ['is_author' => 20,
            'authorizer_access_token' => $authorizer_access_token,
            'authorizer_refresh_token' => $authorizer_refresh_token,
            'author_info' => json_encode($author, true),
            'updated_at' => time(),
        ];

        if (!M('WxConfig')->where(['appid' => $appid])->save($update)) {
            return $this->errorLog($res['AuthorizerAppid'] . '数据保存失败!');
        }
    }
    /**
     * 取消授权事件
     * */
    private function eventUNAuthorizer($res){
        if (!M('WxConfig')->where(['appid' => $res['AuthorizerAppid']])->save(['is_author' => 30])) {
            return $this->errorLog($res['AuthorizerAppid'] . '数据保存失败!');
        }
    }
    private static function postXml(){
        return  $xml = file_get_contents("php://input");
    }
    private function errorLog($errors){
        Log::write($errors, Log::WARN);
        $this->weChatResponse();
    }
    private function weChatResponse(){
        exit(self::WECHAT_RESPONSE);
    }
    private function textReply(\Wechat $weObj, $branch_id){
        if(! $data = (new WxReplyModel)->searchInteractiveByBranchId($branch_id, $weObj->getRevContent())){
            return false;
        }

        return $this->dataMessage($weObj, $data);
    }
    private function dataMessage(\Wechat $weObj, $data = []){
        $xmlData = [];
        switch (intval($data['reply_type'])) {
            case WxReplyModel::REPLY_TYPE_10 :
                $xmlData = $weObj->text($data['content'])->getReplyMsg();
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
                $xmlData = $weObj->news($news)->getReplyMsg();
                break;
            case WxReplyModel::REPLY_TYPE_30 :
                $xmlData = $weObj->image($data['content'])->getReplyMsg();
                break;
            default :
                return false;
        }

        if(empty($xmlData)){
            return false;
        }

        $this->sendMessage($weObj->xml_encode($xmlData));
        return true;
    }
    private function sendMessage($xml){
        $config = $this->_config;
        $model = new WechatNoticeModel($config['token'], $config['key'], $config['appid']);
        $xml = $model->encryptMsg($xml);
        echo $xml;
        die;
    }
    private function processSubscribeNScan($weObj, $branch_id, $inviter_id){
        D("DistributionRelation")->subscribe($weObj->getRevFrom(), $inviter_id, $weObj); //关注，设置关注时间，如果有$inviter_id，设置关系
        if ($inviter_id) {
            $result = M("SysUser")->where(['id' => $inviter_id ])->find();
            if ($result["marketing_url"]) {
                $news = array(
                    "0" => array(
                        'Title' => $result['name'],
                        'Description' => $result['marketing_content'],
                        'Url' => $result['marketing_url']
                    )
                );
                $this->sendMessage($weObj->xml_encode($weObj->news($news)->getReplyMsg()));
            }
        }

        $auto_reply = (new WxReplyModel)->findAttentionByBranchId($branch_id);
        if(empty($auto_reply)){
            return false;
        }

        $this->dataMessage($weObj, $auto_reply);
    }

    private $_config = [];
    private $_receive = [];
    private $_branchId;
    private $_weObj = null;
}