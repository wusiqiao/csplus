<?php

namespace EShop\Controller;

use Think\Controller;

class RegController extends Controller {

    public function indexAction() {
        if (IS_GET) {
            $wechat_user = getCurrentWXUserInfo(false);
            session('openid',  $wechat_user['openid']);
            session('head_pic',  $wechat_user['headimgurl']);
            session('nickname',  $wechat_user['nickname']);
            $this->assign('login_pic',getLoginInterfacePic().'?v='.time());
            $this->display();
        } else {
            $account = I("post.account");
            $password = I("post.password");
            if(I('post.phonecode') == ''){
                exit(json_encode(array("error" => "1", "msg" => "请输入验证码")));
            }
            if($_SESSION['regcode'] != I('post.phonecode')){
                exit(json_encode(array("error" => "1", "msg" => "验证码不正确")));
            }
            if (empty($account) || empty($password)){
                exit(json_encode(array("error" => "1", "msg" => "账号密码不能为空")));
            }
            if (strlen($password) < 6){
                exit(json_encode(array("error" => "1", "msg" => "密码最少为6位")));
            }
            D("SysUser")->userRegisterByMobile(session('openid'), session('nickname'), session('head_pic'),$account, $password);
            $last_url = getLastVisitUrl("/index/user");
            exit(json_encode(array("error" => "0", "msg" => "注册成功", "url" => $last_url)));
        }
    }
    
    public function role(){
        if (session("user_id")){
            $this->assign('title',getComStoreData('name'));
            $this->display();
        }
    }
}
