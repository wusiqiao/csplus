<?php

namespace Common\Lib\Controller;

use Think\Controller;

Class WeChatBaseController extends Controller {

    protected $_user_session = null;    
    protected function _initialize() {
        if (!(session(USER_SESSION_KEY))) {
            $json = getWeChatInstance()->getOauthAccessToken();
            if (!$json) {
                header("Location:" . getWeChatRedirectUrl());
                die();
            }
            if ($json['openid']) {
                $condition['openid'] = $json['openid'];
                $user = D("SysUser")->where($condition)->find();
                if ($user) {
                    $user_session = D("SysUser")->getLoginUserInfo($user);
                    session(USER_SESSION_KEY, $user_session);
                    session('openid', $json['openid']);
                }else{
                    redirect(U("./WeChat/login"));
                    die();
                }
            }else{
                redirect(U("./WeChat/login"));
                die();
            }
        }
        $this->_user_session = session(USER_SESSION_KEY);
    }

    protected function responseJSON($data) {
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->ajaxReturn($jsonData, "EVAL");
    }

    protected function _parsefilter(&$filter) {
        parseQueryParams($filter);
    }

    protected function _parseOrder(&$order) {
        parseQueryOrder($order);
    }

}
