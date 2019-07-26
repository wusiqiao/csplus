<?php

namespace EShop\Controller;

use Think\Controller;

class LoginController extends Controller {



    public function codeimgAction() {
        $Verify = new \Think\Verify();
        $Verify->fontSize = 12;
        $Verify->length = 4;
        $Verify->imageW = 90;
        $Verify->useNoise = false;
        $Verify->entry();
    }

    public function indexAction() {
        if (IS_GET) {
            $wechat_user = getCurrentWXUserInfo();
            session('openid',  $wechat_user['openid']);
            session('head_pic',  $wechat_user['headimgurl']);
            session('nickname',  $wechat_user['nickname']);
            $this->assign('login_pic',getLoginInterfacePic() ? getLoginInterfacePic().'?v='.time() : false);
            $this->assign('verifyTimes', 0);
            $this->display();
        } else {
            if (!session('openid')){
                die(json_encode(array("error" => "1", "msg" => "请在微信上登录")));
            }
            $login_type = I('post.type');
            //微信登录
            if($login_type == 'wx'){
                $this->loginByWeChat();
            }else{
                //验证码登录
                if ($login_type == "code"){
                    $this->loginByVerifyCode();
                }else{
                    //用户名密码登录
                    $this->loginByPassword();
                }
            }
            $default_url = "/index/user.html";
            $last_url = getLastVisitUrl($default_url);
            die(json_encode(array("error" => "0", "msg" => "登录成功", "url" => $last_url)));
        }
    }
    public function loginAction(){
        $this->display();
    }
    public function wx_loginAction(){
        if(I('get.token')){
            $token = generateBranchToken().I('get.token');
            $wechatInstance = getWeChatInstance();
            $accessTokenData = $wechatInstance->getOauthAccessToken();
            if(!$accessTokenData){
                header("Location:" . getWeChatRedirectUrl(WEB_ROOT.'/Login/wx_login/token/'.I('get.token'), false));
                die();
            }else{
                $user_data = D('SysUser')->isLoginFromOpenid($accessTokenData['openid'],true);
                if($user_data['user_type'] == USER_TYPE_COMPANY_MANAGER){
                    $this->result = array('error'=>1,'message'=>'登录失败,管理员不能登录');
                    $this->display('wx_login_result');
                    die;
                }
                if($user_data){
                    //赋值token
                    $save['token'] = $token;
                    $save['id'] = $user_data['id'];
                    $result = D('SysUser')->save($save);
                }else{
                    $wx_user_info = $wechatInstance->getUserInfo($accessTokenData['openid']);
                    if ($wx_user_info["subscribe"] == 0){ //未关注，获取不到用户昵称，必须再用授权方式获取一次
                        $wx_user_info = $wechatInstance->getOauthUserinfo($accessTokenData["access_token"], $accessTokenData['openid']);
                        $wx_user_info["subscribe"] = 0; //取回来没有此字段，必须再加上
                    }
                    $nicename = $wx_user_info['nickname'] ?? 'Wx_'.substr(md5(time()),-5);
                    $header = $wx_user_info['headimgurl'] ?? getDefalutHeadPic();
                    $this->wx_register_user($accessTokenData['openid'],$nicename,$header);
                }
                $this->result = array('error'=>0,'message'=>'登录成功');
                $this->display('wx_login_result');
                die;
            }
        }
    }
    /**
     * 注册函数
     */
    private function wx_register_user($openid, $nickname, $headimgurl, $account = "", $password = "123456"){
        $branch_id  = getBrowseBranchId();
        if (empty($openid)) {
            die(json_encode(array("error" => "1", "msg" => "注册失败，请允许平台获取您的微信用户信息！")));
        }
        if (!$branch_id){
            die(json_encode(array("error" => "1", "msg" => "注册失败,数据出错！")));
        }
        $user['branch_id']  = $branch_id;
        $user['openid']     = $openid;
        $user['head_pic']   = empty($headimgurl) ? getDefalutHeadPic(): $headimgurl;
        $user['name']       =  removeEmoji($nickname);
//        $user["unionid"]  =  $wxUserInfo['unionid'];
        $user["reg_time"]   = time();
        $user["last_time"]  = $user["reg_time"];
        $user["last_ip"]    = get_client_ip(0, 1);
        $user["user_type"]  = USER_TYPE_CUSTOMER;
        $user["role_ids"]   = ROLE_ID_CUSTOMER;
        $user['is_valid']   = 1;
        $user["account"]    =  empty($account)?$openid:$account;
        $user["mobile"]    =  $account;
        $user['password']   = md5_plus($password);
        $user['token'] =  generateBranchToken().I('get.token');
        $result = D('SysUser')->add($user);
        return $result;
    }
    /**
     * 微信登录
     */
    private function loginByWeChat(){
        //公网访问并且不是微信浏览器，强制微信登录，本地测试可以通过
        if (isRemoteHost() && !isWechatBrower()){
            die(json_encode(array("error" => "1", "msg" => "请在微信上登录")));
        }
        $openid = session('openid');
        $user_data = D('SysUser')->isLoginFromOpenid($openid,true);
        if($user_data){
            setUserSession($user_data);
            ActionLog($user_data['id'], "登录");
        }else{
            $result = D("SysUser")->userRegisterSilence(session('openid'), session('nickname'), session('head_pic'));
            ActionLog($result, "登录");
        }
    }

    /**
     * 手机校验码登录
     */
    private function loginByVerifyCode(){
        if($_SESSION['regcode'] != I('post.phonecode')){
            exit(json_encode(array("error" => "1", "msg" => "验证码不正确")));
        }
        $account = I('post.account');
        $condition["mobile"] = $account;
        $condition['branch_id'] = getBrowseBranchId();
        $user_data = M("SysUser")->where($condition)->find();
        if($user_data){
            setUserSession($user_data);
            ActionLog($user_data['id'], "登录");
        }else{
            //密码也是手机号码
            $result = D("SysUser")->userRegisterByMobile(session('openid'), session('nickname'), session('head_pic'),$account, $account);
            ActionLog($result, "登录");
        }
    }

    /**
     * 用户名密码登录
     */
    private function loginByPassword(){
        $account = I('post.account');
        $code = I('post.code');
        //如果错误次数超过2次，出现校验码
        $verifyTimes = intval(session('verifyTimes'));
        if ($verifyTimes > 2) {
            if (check_code($code) === false) {
                die(json_encode(array("error" => "1", "verifyTimes" => $verifyTimes, "msg" => "验证码不正确或者已超时")));
            }
        }
        $branch_id = getBrowseBranchId();
        $condition['_string']  = "branch_id=$branch_id and (account='$account' or mobile='$account')";
        $user_data = M("SysUser")->where($condition)->find();
        if ($user_data) {
            $password = I('post.password');
            if (!check_md5_plus($password,$user_data["password"])){
                die(json_encode(array("error" => "1", "msg" => "用户或密码不正确")));
            }
            if ($user_data["is_valid"] == 0){
                die(json_encode(array("error" => "1", "msg" => "该用户账号已被冻结")));
            }
            $data = array();
            //每次登录更新openid，解决一个手机可以登录多个微信问题
            if (isMicroMessengerBrower()){
                //$data['openid'] = session('openid');
            }
            $data['exiting'] = 0;
            M("SysUser")->where("id=".$user_data['id'])->save($data); //登录成功
            setUserSession($user_data);
            //新增 - 留言未读数量
//                $total = D('Home/Comment')->getDontReadAskCount();
//                session('unread_ask', $total);
            session('verifyTimes', 0);
            ActionLog($user_data['id'], "登录");
        } else {
            $verifyTimes = intval(session('verifyTimes'));
            session('verifyTimes', $verifyTimes + 1);
            echo json_encode(array("error" => "1", "verifyTimes" => $_SESSION['verifyTimes'], "msg" => "账户或密码错误"));
            exit();
        }
    }

    //---------------------调用退出模块
    public function logoutAction() {
        $condition['exiting'] = 1;
        $condition['last_time'] = time();
        D("SysUser")->where("id='".$_SESSION['user_id']."'")->data($condition)->save();
        $branch_id = getBrowseBranchId();
        session(null);
        $this->redirect('/login/index/bid/'.$branch_id);
    }
    public function forgetAction() {
        $action = I('post.action', '', 'strip_tags');
        $branch_id = getBrowseBranchId();
        $this->redirect('/login/index/bid/'.$branch_id);
        if ($action == "") {
            $this->display();
        } else {
            $code = I('post.code', '', 'strip_tags');
            $account = I('post.account', '', 'strip_tags');
            $password = I('post.password', '', 'strip_tags');

            if ($account == "" or $password == "") {
                echo json_encode(array("error" => "1", "msg" => "资料不能为空"));
                exit();
            } elseif ($code <> $_SESSION['regcode']) {
                echo json_encode(array("error" => "1", "msg" => "验证码不正确"));
                exit();
            }
            $branch_id = getBrowseBranchId();
            $user = D("SysUser");
            $user->password = md5_plus($password);
            $user->where("account='$account' and branch_id = ".$branch_id)->save();
            $user_data = $user->where("account='$account' and branch_id = ".$branch_id)->find();
            ActionLog($user_data['id'], "找回密码");
            echo json_encode(array("error" => "0", "msg" => "提交成功"));
            exit();
        }
    }

    public function dwAction(){
        $new_filePath = realpath("./").ltrim(MODULE_UPLOAD_PATH,".")."1.jpg";
//        die($new_filePath);
        header("Content-type:application/octet-stream");
        //$filename = basename($new_filePath);
        header("Content-Disposition:attachment;filename = 1.jpg");
        header("Accept-ranges:bytes");
        header("Accept-length:".filesize($new_filePath));
        readfile($new_filePath);
    }

}
