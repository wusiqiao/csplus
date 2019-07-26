<?php

namespace EShop\Model;
/**
 * 第三方平台接口实现层
 */

class PlatformModel {
    const ACCESS_TOKEN_CACHE_TIME = 105 * 60;  //accessToken保存时长保存 105 分钟 * 60
    const KEY_COMPONENT_ACCESS_TOKEN = 'component_access_token'; //保存
    const BASE_API_URL = 'https://api.weixin.qq.com/cgi-bin/component/'; //基础请求路由
    const ACCESS_TOKEN_URL = 'api_component_token';  //获取accessToken;
    const API_CREATE_PREAUTHCODE_URL = 'api_create_preauthcode?component_access_token='; //获取预授权码pre_auth_code
    const API_QUERY_AUTH_URL = 'api_query_auth?component_access_token='; //使用授权码换取公众号或小程序的接口调用凭据和授权信息
    const API_AUTHORIZER_TOKEN_URL = 'api_authorizer_token?component_access_token='; // 获取（刷新）授权公众号或小程序的接口调用凭据（令牌）
    /**
     *
     * */
    public function __construct($config = []){
        $this->_config = $config;
        $this->_appid  = $config['appid'];
        $this->_secret = $config['secret'];
    }

    /**
     *获取 accessToken
     * */
    public function getAccessToken($bool = true){
        $accessToken = S(self::KEY_COMPONENT_ACCESS_TOKEN);
        if($bool && empty($accessToken) && $this->refreshPlatformAccessToken($this->_config['verify_ticket'])){
            return $this->getAccessToken(false);
        }

        return $accessToken;
    }
    /**
     * 刷新平台的accessToken
     * @param string $ComponentVerifyTicket 微信后台推送的ticket，此ticket会定时推送
     * @param string return component_access_token
     * */
    public function refreshPlatformAccessToken($ComponentVerifyTicket){
        $data['component_appid'] = $this->_appid;
        $data['component_appsecret'] = $this->_secret;
        $data['component_verify_ticket'] = $ComponentVerifyTicket;
        $url = self::BASE_API_URL . self::ACCESS_TOKEN_URL;
        $res = $this->post($url, $data);
        if(!empty($res['component_access_token'])){
            S(self::KEY_COMPONENT_ACCESS_TOKEN,  $res['component_access_token'], 7100);
            //做数据库的保存操作!
            return $res['component_access_token'];
        }

        return $this->setError($res);
    }

    public function existsPlatformAccessToken(){
        if(S(self::KEY_COMPONENT_ACCESS_TOKEN)){
            return true;
        }

        return false;
    }
    /**
     * 获取预授权码pre_auth_code
     * */
    public function getPreAuthCode(){
        $accessToken = $this->getAccessToken();
        if(!$accessToken){
            return false;
        }

        $url = self::BASE_API_URL . self::API_CREATE_PREAUTHCODE_URL . $accessToken;
        $data['component_appid'] = $this->_appid;
        $res = $this->post($url, $data);
        if(!empty($res['pre_auth_code'])){
            return $res['pre_auth_code'];
        }

        if(isset($res['errcode'])  && $res['errcode'] == '40001'){
            switch(intval($res['errcode'])){
                case 40001 :
                    S(self::KEY_COMPONENT_ACCESS_TOKEN, null);
                    return $this->getPreAuthCode();
                    break;
            }
        }
        return false;
    }
    /**
     * 使用授权码换取公众号或小程序的接口调用凭据和授权信息
     * @param  string $authorizationCode 授权码
     * */
    public function queryAuth($authorizationCode){
        $accessToken = $this->getAccessToken();
        if(!$accessToken){
            return false;
        }

        $url = self::BASE_API_URL . self::API_QUERY_AUTH_URL . $accessToken;
        $data['component_appid'] = $this->_appid;
        $data['authorization_code'] = $authorizationCode;
        $res = $this->post($url, $data);

        return $res;
    }
    /**
     * 获取（刷新）授权公众号或小程序的接口调用凭据（令牌）
     * */
    public function refreshTokenAuth($authorizerAppid, $refreshToken){
        $url = $this->getUrlAndToken(self::API_AUTHORIZER_TOKEN_URL);
        if(!$url){
            return false;
        }

        $data['component_appid'] = $this->_appid;
        $data['authorizer_appid'] = $authorizerAppid;
        $data['authorizer_refresh_token'] = $refreshToken;
        $res = $this->post($url, $data);

        return $res;
    }

    public function getOauthAccessToken($code, $appid){

    }

    private function getUrlAndToken($url){
        $accessToken = $this->getAccessToken();
        if(!$accessToken){
            return false;
        }

        return self::BASE_API_URL . $url . $accessToken;
    }

    private function post($url, $data = null){
        $ch = curl_init($url);
        if (stripos($url, "https://") !== FALSE) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在
            //curl_setopt($ch, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        if(is_array($data)){
            $data = json_encode($data, true);
        }

        curl_setopt($ch, CURLOPT_POST, true); // 发送一个常规的Post请求
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 设置超时限制防止死循环
        curl_setopt($ch, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 获取的信息以文件流的形式返回
        $result = curl_exec($ch); // 执行操作
        curl_close($ch); // 关闭CURL会话
        if($result){
            $result = (array) json_decode($result, true);
        }

        return $result;
    }

    private function setError($errors){
        $this->_errors = $errors;

        return false;
    }

    public function getError(){
        return $this->_errors;
    }

    private $_appid;  //第三方平台的appid
    private $_secret; //第三方平台的secret
    private $_config;
    private $_errors;
}