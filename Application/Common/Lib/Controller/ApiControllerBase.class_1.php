<?php

namespace Common\Lib\Controller;

use Think\Controller;
use Think\Log;

class ApiControllerBasex extends Controller {
    protected $_user_session;
    protected $_access_token;
    protected $_is_single_user = true;

    public function _initialize() {
        //检查是否登录
        if (ACTION_NAME !== "login") {;
            if (APP_DEBUG){
                Log::write(ACTION_NAME);
                Log::write(json_encode($_POST));
            }
            $this->_access_token = $_SERVER["HTTP_".strtoupper(ACCESS_TOKEN_TAG)]; //先取Header的
            if (!$this->_access_token){
                $this->_access_token = I(ACCESS_TOKEN_TAG, "");
            }
            $this->_user_session = S($this->_access_token);
            if ($this->_is_single_user) //单用户限制
            {
                if (!$this->_user_session) {
                    $this->responseJSON(buildMessage("尚未登录或该用户在其他地方登录，请重新登录！", 1));
                }
            }
        }
    }

    final public function loginAction($account = "", $password = "") {
        $condition["account"] = $account;
        $user = M("SysUser")->where($condition)->find();
        if ($user) {
            $this->logout($account); //再次登录，删除旧的缓存
            if (check_md5_plus($password, $user["password"])) {
                $access_token = md5(ACCESS_TOKEN_TAG . $account . microtime());
                $user_session = D("SysUser")->getLoginUserInfo($user, true); //不获取功能权限
                S($access_token, $user_session);
                $user_key = md5(USER_SESSION_KEY . $account); //保存对应用户键
                S($user_key, $access_token);
                $login_user["id"] = $user_session->userId;
                $login_user["name"] = $user_session->userName;
                $login_user["account"] = $user_session->account;
                $login_user["depart_name"] = $user_session->currBranchName;
                $login_user["depart_id"] = $user_session->currBranchId;
                $login_user["depart_type"] = $user_session->currBranchType;//ORG_BRANCH=0;ORG_COMPANY=1;ORG_MAINTEN=6;
                $login_user["depart_code"] = $user_session->currBranchCode;
                $result["access-token"] = $access_token;
                $result["user"] = $login_user;
                $this->responseJSON(buildResult($result));
            }
        }
        $this->responseJSON(buildMessage("用户名或密码错误！", 1));
    }

    final public function logoutAction() {
        if ($this->_user_session) {
            $this->logout($this->_user_session->account);
            $this->responseJSON(buildMessage("成功退出登录！"));
        }else{
            $this->responseJSON(buildMessage("尚未登录！", 1));
        }
    }

    private function logout($account) {
        $user_key = md5(USER_SESSION_KEY . $account);
        $access_token = S($user_key);
        if ($access_token) {
            S($access_token, null);
        }
        S($user_key, null);
        $this->_user_session = null;
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
}
