<?php

namespace Common\Lib\Controller;

use Think\Controller;
use Think\Log;

class ApiControllerBase extends Controller {
    protected $_user_session;
    
    public function _initialize() {
        //检查是否登录
        if (ACTION_NAME !== "login") {
            $this->_user_session = $_SESSION[USER_SESSION_KEY];  
            if (!$this->_user_session){
                $this->responseJSON(buildMessage("请重新登录！", 1));
                return false;
            }else{
                $allow_pass = false;
                $menuList = $this->_user_session->permissionList[ACCESS_MENUS_KEY];
                if ($menuList)
                {
                    if (strpos($this->_user_session->permissionList[ACCESS_SYS_ACTIONS_KEY], ACTION_NAME) === false) { //不在权限范围，放行
                        $allow_pass = true;
                    }else{
                        if ($menuList[CONTROLLER_NAME] && $menuList[CONTROLLER_NAME]["allow"]) {
                            if ($menuList[CONTROLLER_NAME][ACCESS_MENU_ACTIONS_KEY][ACTION_NAME]) {
                                $allow_pass = true;
                            }
                        }
                    }
                }
                if (!$allow_pass){
                   $this->responseJSON(buildMessage("没有此权限！", 1));
                }
            }
        }
    }

    final public function loginAction($account = "", $password = "") {
        $condition["account"] = $account;
        $user = M("SysUser")->where($condition)->find();
        if ($user) {
            if (check_md5_plus($password, $user["password"])) {
                $user_session = D("SysUser")->getLoginUserInfo($user);
                session(USER_SESSION_KEY, $user_session);
                $login_user["id"] = $user_session->userId;
                $login_user["name"] = $user_session->userName;
                $login_user["account"] = $user_session->account;
                $login_user["depart_id"] = $user_session->departmentId;
                $login_user["company_id"] = $user_session->currBranchId;
                $login_user["company_name"] = $user_session->currBranchName;
                $login_user["company_type"] = $user_session->currBranchType;//ORG_BRANCH=0;ORG_COMPANY=1;ORG_MAINTEN=6;
                $login_user["company_code"] = $user_session->currBranchCode;
                $login_user["permissions"] = implode(",", array_keys($user_session->permissionList[ACCESS_MENUS_KEY][CONTROLLER_NAME][ACCESS_MENU_ACTIONS_KEY]));
                $login_user["companys"] = array_values($user_session->branchList); //2018/01/06 
                $this->after_login($login_user);
                $result["access-token"] = session_id();
                $result["user"] = $login_user;
                $this->responseJSON(buildResult($result));
            }
        }
        $this->responseJSON(buildMessage("用户名或密码错误！", 1));
    }

    //登陆回调函数
    protected  function after_login(&$login_user){
        
    }
    
    final public function logoutAction() {
        session_destroy();
        $this->responseJSON(buildMessage("成功退出登录！"));
    }

    final public function changePasswordAction($old_password, $new_password) {
        $model = M("SysUser");
        if ($this->_user_session) {
            $condition["id"] = $this->_user_session->userId;
            $user = $model->where($condition)->field("password")->find();
            $checkpass = check_md5_plus($old_password, $user["password"]);
            if ($checkpass) {
                if (false !== $model->where($condition)->setField("password", md5_plus($new_password))) {
                    $this->responseJSON(buildMessage("修改成功！"));
                }
            }
        }
        $this->responseJSON(buildMessage("用户名或密码错误！", 1));
    }

    final public function addSuggestAction($title = null, $content = null) {
        if ($this->_user_session) {            
            if ($title && $content){
                $data["title"] = $title;
                $data["content"] = $content;
                $data["type"] = 1;
                $data["id"] = $this->_user_session->userId;
                $data['update_time'] = time();
                $data["branch_id"] = $this->_user_session->currBranchId;
                if (M("ComNotice")->add($data)){
                   $this->responseJSON(buildMessage("提交成功，感谢您的参与！")); 
                }else{
                   $this->responseJSON(buildMessage("提交失败，请联系管理员！", 1)); 
                }
            }else{
                $this->responseJSON(buildMessage("标题或内容不能为空白！", 1));
            }
            
        }
    }
    
    final public function getUserInfoAction() {
        $list["code"] = 0;
        $user["userId"] = $this->_user_session->userId;
        $user["userName"] = $this->_user_session->userName;
        $user["account"] = $this->_user_session->account;
        $user["branchId"] = $this->_user_session->currBranchId;
        $user["branchName"] = $this->_user_session->currBranchName;
        $list["message"] = $user;
        $this->responseJSON($list);
    }
    
    final protected function responseJSON($data){
       if (APP_DEBUG){
           Log::write("ajaxResponse:", json_encode($data, JSON_UNESCAPED_UNICODE));
       }
       $this->ajaxReturn($data, "JSON", JSON_UNESCAPED_UNICODE); 
    }
    
    final protected function decodeRequestJson($json_name){
        $json_data = $_POST[$json_name];
        if ($json_data)
        {
            if (ini_get("magic_quotes_gpc") == "1") {
                $json_data = stripslashes($json_data);
            }       
            return json_decode($json_data, true);
        }
        return null;
    }
}
