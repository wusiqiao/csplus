<?php

namespace ESAdmin\Controller;

use Think\Controller;
use \Think\Verify;
use EShop\Model\PlatformModel;
use ESAdmin\Model\WxPlatformConfigModel;

class LoginController extends Controller {

    public function indexAction() {
        $this->user_account = cookie("user_account");
        $account = I("account");
        $password = I("password");
        if ($account && $password){
            $condition["account"] = $account;
            $condition["is_valid"] = 1;
            $user = M("SysUser")->where($condition)->find();
            if ($user) {
                if (check_md5_plus($password, $user["password"])) {
                    $this->userLogin($user);
                    header("Location:/Index");
                    exit();
                }
            }
        }
        $this->display();
    }

    public function registerAction() {
        if (IS_GET){
            $this->display();
        }else{
            $data["name"] = I("post.name");
            $data["type"] = 0;
            $data["is_valid"] = 1;
            $data["branch_id"] = 1;
            $data["querykey"] = firstPinyin($data["name"]);
            $data["user_password"] = I("post.user_password");
            $model = D("SysBranch");
            if ($model->create($data)){
                if ($model->add($data)){
                    $this->ajaxReturn(buildMessage("注册成功"));
                }else{
                    $this->ajaxReturn(buildMessage("注册失败：".$model->getError(), 1));
                }
            }else{
                $this->ajaxReturn(buildMessage("注册失败：".$model->getError(), 1));
            }
        }
    }

    public function verifyAction() {
        $config["imageW"] = 120;
        $config["imageH"] = 30;
        $config["useNoise"] = false;
        $config["fontSize"] = 14;
        $config["useCurve"] = false;
        $verify = new Verify($config);
        $verify->entry();
        unset($verify);
    }

    public function loginAction() {
        $postData = I("post.");
        //判断登陆方式是否为验证码登陆
        if($postData['login_type'] == "sms"){
            $condition['user.mobile'] = $postData['account'];
            $error_msg = "手机号或验证码错误！";
        }else{
            $where["user.mobile"] = $postData['account'];
            $where["user.account"] = $postData['account'];
            $where['_logic'] = 'or';
            $condition['_complex'] = $where;
            $error_msg = "用户名或密码错误！";
        }
        //选择的登陆方
        if($postData["login_user_type"] == 1 && $postData['account'] != "admin"){
            $condition['user.user_type'] = USER_TYPE_COMPANY_MANAGER;
        }elseif($postData["login_user_type"] == 0 && $postData['account'] != "admin"){
            $condition['_string'] = "user.user_type in (".USER_TYPE_CUSTOMER.",".USER_TYPE_PROSPECTIVE.")";
        }
        if($postData['user_id']){
            $condition['user.id'] = $postData['user_id'];
        }
        $users = M("SysUser user")
            ->join("sys_branch branch on user.branch_id = branch.id")
            ->where($condition)->order("user.id desc")->field("user.*,branch.name as branch_name")->select();
        if(count($users) > 1){
            //如果条件中branch_id为空，该手机号找到多个账户的时候选择登陆商户
            $data = [];
            foreach ($users as $k=>$v){
                $data[] = ["branch_name"=>$v['branch_name'],"user_name"=>$v['name'],"branch_id"=>$v['branch_id'],"user_id"=>$v['id']];
            }
            $this->ajaxReturn(array("message"=>"请选择服务商","code"=>"5","data"=>$data));
        }
        $user = $users[0];
        //$user = M("SysUser")->where($condition)->order("id desc")->find();
        if ($user) {
            if($user['is_valid'] == 0){
                $this->ajaxReturn(buildMessage("该账号已被禁用，请联系管理员。",1));
            }
            if (check_md5_plus($postData['password'], $user["password"]) || $postData['password'] == $_SESSION['login_code']) {
                $user_session = $this->userLogin($user);
                //1.客户绑定0家公司时，提示未绑定公司，不允许登陆
                //2.客户绑定1家公司时，设置session中currBranchId为该家公司id
                //3.客户绑定大于1家公司时，返回选择公司
                if(count($user_session->branchList) > 1){
                    $this->ajaxReturn(buildMessage("请选择公司",2));
                } else if (count($user_session->branchList) == 0 && $user_session->userType != USER_TYPE_COMPANY_MANAGER && !$user_session->isPlatformUser){
                    unset($_SESSION[USER_SESSION_KEY]);
                    $this->ajaxReturn(buildMessage("未绑定任何公司",3));
                }
                if(count($user_session->branchList) == 1 && $user_session->userType != USER_TYPE_COMPANY_MANAGER && !$user_session->isPlatformUser){
                    D("SysUser")->setUserBranch($user_session, $user_session->branchList[0]['id']);
                }else{
                    D("SysUser")->setUserBranch($user_session, $user["branch_id"]);
                }
                $result["code"] = 0;
            }else {
                $result["code"] = 1;
                $result["message"] = $error_msg;
            }
        }else {
            $result["code"] = 1;
            $result["message"] = "该账号不存在，请重新输入。";
        }
        $this->ajaxReturn($result);
    }

    private function addLog($user){
        $data["user_name"] = $user["name"];
        $data["kind"] = 1;
        $data["create_time"] = time();
        M("SysLog")->add($data);
    }
    private function userLogin($user){
        $login_count = intval($user["login_count"]);
        $save_data = array("last_time" => time(), "login_count" => $login_count + 1);
        M("SysUser")->where("id=".$user["id"])->save($save_data);
        $this->addLog($user);
        $remember_account = I("remember_account");
        if ($remember_account) {
            cookie("user_account", $user["account"]);
        }else{
            cookie("user_account",null);
        }
        $user_session = D("SysUser")->getLoginUserInfo($user);
        if($user_session == false){
            $this->ajaxReturn(buildMessage("您未获得任何权限，请联系管理员进行授权！",1));
        }
        session(USER_SESSION_KEY, $user_session);
        return $user_session;
    }

    //多公司选择
    public function choise_branchAction($branch_id = null) {
        $user_session = session(USER_SESSION_KEY);
        if (!$user_session) {
            redirect(U("Login/index"));
        }
        if (IS_POST){
            if (empty($branch_id)){
                $this->ajaxReturn(buildMessage("公司不能为空", 1));
            }else{
                D("SysUser")->setUserBranch($user_session, $branch_id);
                $this->ajaxReturn(buildMessage($branch_id));
            }
        }else{
            $branchs = D("SysUser")->getUserBranchs($user_session->userId, $branch_id);
            $this->assign("branchs", $branchs);
            $this->display();
        }
    }

    public function logoutAction() {
        session_destroy();
        redirect(U("Login/index"));
    }

    //管理员界面进入各个店铺
    public function enterShopAction($shop_id){
        $user_session = session(USER_SESSION_KEY);
        if ($user_session->isPlatformUser){
            $condition["a.branch_id"] = $shop_id;
            $condition["b.id"] = $shop_id;
            $condition["a.account"] = array("neq", "admin");
            $shop_adminuser_data = M("SysUser a")
                ->field("a.*")
                ->join("sys_branch b on a.branch_id=b.id and a.id=b.leader_id")
                ->where($condition)->order("a.id")->find();
            if ($shop_adminuser_data){
                $this->userLogin($shop_adminuser_data);
                $this->ajaxReturn(buildMessage("登录成功"));
            }
        }
        $this->ajaxReturn(buildMessage("登录失败", 1));
    }

    //业务员进入各个店铺
    public function trackerEnterShopAction($shop_id){
        $user_session = session(USER_SESSION_KEY);
        $tracker_id = M("SysBranch")->where("id = $shop_id")->getField("tracker_id");
        //当前用户是跟踪人员或者平台用户
        if ($tracker_id == $user_session->userId ||  $user_session->isPlatformUser){
            $condition["a.branch_id"] = $shop_id;
            $condition["b.id"] = $shop_id;
            $condition["a.account"] = array("neq", "admin");
            $shop_adminuser_data = M("SysUser a")
                ->field("a.*")
                ->join("sys_branch b on a.branch_id=b.id and a.id=b.leader_id")
                ->where($condition)->order("a.id")->find();
            if ($shop_adminuser_data){
                $this->userLogin($shop_adminuser_data);
                $this->ajaxReturn(buildMessage("登录成功"));
            }
        }
        $this->ajaxReturn(buildMessage("登录失败", 1));
    }

    public function checkAction(){
        if(I("post.type") == "login"){
            $_SESSION['login_code'] = rand(1000, 9999);
            $message = array("code"=>$_SESSION['login_code']);
            $type = "短信登录";
        }else{
            $_SESSION['regcode'] = rand(1000, 9999);
            $message = array("code"=>$_SESSION['regcode']);
            $type = "重置密码";
        }
        $phone = I('post.phone', '', 'strip_tags');
        $begtime = strtotime(date("Y-m-d"));
        $user = D("SysUser");
        $total = $user->where("mobile='$phone'")->count();
        $smsall = D("sms_log")->where("mobile='$phone' and type='$type' and begtime='$begtime'")->count();
        if ($smsall > 5) {
            echo json_encode(array("result" => "1", "msg" => "发送失败，您今天短信接收已超量！"));
            exit();
        }
        if ($total != 0) {
            $returnstatus=D('EShop/SmsLog')->send_sms_message($phone, SMS_RESET_PASSWORD_CODE, $message);
            if ($returnstatus == 'Success') {
                $sms_log = D("sms_log");
                $sms_log->type = $type;
                $sms_log->mobile = $phone;
                $sms_log->begtime = $begtime;
                $sms_log->add();
                if($total > 1){
                    $condition['a.mobile'] = $phone;
                    //$condition['b.type'] = "1";
                    $result = M("SysUser")
                        ->alias('a')
                        ->join("sys_branch b on a.branch_id = b.id")
                        ->field("b.id,b.name")
                        ->where($condition)
                        ->select();
                    $this->ajaxReturn(array("result" => "0", "msg" => "验证码已发送到您手机上","branches"=>$result));
                }else{
                    echo json_encode(array("result" => "0", "msg" => "验证码已发送到您手机上"));
                    exit();
                }
            } else {
                echo json_encode(array("result" => "1", "msg" => "发送超时，请联系客服！"));
                exit();
            }
        }  else {
            echo json_encode(array("result" => "1", "msg" => "发送失败，该手机号未绑定账户！"));
            exit();
        }
    }
    //找回密码
    public function getPasswordAction(){
        if(IS_POST){
            $branch_id = I("post.branch_id");
            $mobile = I("post.mobile");
            $password = I("post.password");
            $re_password = I("post.re_password");
            $code = I("post.code");
            if($code != $_SESSION['regcode']){
                $this->ajaxReturn(buildMessage("验证码错误",1));
            }
            if($password != $re_password){
                $this->ajaxReturn(buildMessage("两次密码不一致",1));
            }
            if($branch_id){
                $condition['branch_id'] = $branch_id;
            }
            $condition["mobile"] = $mobile;
            $user_record = D("SysUser")->where($condition)->field("password")->find();
            if ($user_record){
                $new_password = md5_plus($password);
                $result = M("SysUser")->where($condition)->setField("password", $new_password);
                if ($result !== false){
                    unset($_SESSION['regcode']);
                    $this->ajaxReturn(buildMessage("修改成功！"));
                }else{
                    $this->ajaxReturn(buildMessage("未知错误，请联系管理员",1));
                }
            }else{
                $this->ajaxReturn(buildMessage("用户不存在",1));
            }
        }else{
            $this->display();
        }
    }

    //商户选择凭证客户
    public function selectCompanyAction($branch_id = null) {
        $user_session = session(USER_SESSION_KEY);
        if (!$user_session) {
            redirect(U("Login/index"));
        }
        if (IS_POST){
            if (empty($branch_id)){
                $this->ajaxReturn(buildMessage("公司不能为空", 1));
            }else{
                D("SysUser")->setUserCompany($user_session, $branch_id);
                session("environment", "voucher"); //凭证模式
                $fields = "count(id) as total,sum(case is_new when 1 then 1 else 0 end) as new_total";
                $subjectCounts = M("VcrSubject")->where("branch_id=$branch_id")->field($fields)->find();
                $this->subjectImport = $subjectCounts["total"];
                $this->newTotal = intval($subjectCounts["new_total"]);
                $compayData = M("SysBranch")->find($branch_id);
                $this->hasSeting = (isset($compayData["ent_type_id"]) && isset($compayData["ent_scale"]) && isset($compayData["free_type"]));
                $content = $this->fetch("VcrSetting:warn");
                exit($content);
                //$this->ajaxReturn(buildMessage($branch_id));
            }
        }else{
            $this->display("select_company");
        }
    }

    //用户重新登录
    public function resetLoginUserAction(){
        $user_session = session(USER_SESSION_KEY);
        if ($user_session){
            $condition["id"] = $user_session->userId;
            $userData = M("SysUser")->where($condition)->find();
            if ($userData){
                $user_session = D("SysUser")->getLoginUserInfo($userData);
                session(USER_SESSION_KEY, $user_session);
                $this->ajaxReturn(buildMessage("登录成功"));
            }
        }
        $this->ajaxReturn(buildMessage("登录失败", 1));
    }

    public function searchCompanysAction(){
        $user_session = session(USER_SESSION_KEY);
        $keyword = I("get.q");
        $branchs = D("SysUser")->getUserCompanys($user_session->userId, $keyword);
        $this->ajaxReturn($branchs);
    }


    public function appAuthorAction(){
        $model = new PlatformModel(WxPlatformConfigModel::getConfig());
        $res =  $model->queryAuth(I('get.auth_code'));
        if(! $res){
            $this->assign('msg',  '授权异常， 获取公众号信息失败!');
            return $this->display('aborted');
            die;
        }

        $data = $res['authorization_info'];
        $appid = $data['authorizer_appid'];
        $authorizer_access_token = $data['authorizer_access_token'];
        $authorizer_refresh_token = $data['authorizer_refresh_token'];
        $author = [];
        foreach($data['func_info'] as $func_info){
            array_push($author, $func_info['funcscope_category']['id']);
        }
        $author = json_encode($author, true);
        if(!M('WxConfig')->where(['appid' => $appid])->find()){
            $this->assign('msg', '授权公众号ID[' . $appid  . ']未找到，请联系业务员!');
            return $this->display('aborted');
            die;
        }

        $update = ['is_author' => 20,
            'authorizer_access_token' => $authorizer_access_token,
            'authorizer_refresh_token' => $authorizer_refresh_token,
            'author_info' => $author,
            'updated_at' => time(),
        ];

        if(!M('WxConfig')->where(['appid' => $appid])->save($update)){
            $this->assign('msg', '请求繁忙，授权信息保存失败!');
            return $this->display('aborted');
            die;
        }

        return $this->display('successful');
    }
}
