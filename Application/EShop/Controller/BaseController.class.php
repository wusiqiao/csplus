<?php

namespace Eshop\Controller;

use Think\Controller;
/*
 * 该类似主要用于角色权限控制,为EShop基类
 * 相同于ESAdmin的角色权限控制
 * 由于EShop中的代码与ESAdmin中的代码而言较不规范,故新增_permission_name (模块控制器) _permission_action_name (模块方法)
 * 引用BaseController的Controller可以模拟使用对应的权限控制
 */
class BaseController extends Controller {
    protected $_permissionList = null;
    protected $_permission_name = null; //功能权限关键字
    protected $_permission_action_name = null;
    protected $userId; //登录用户ID
    protected $userName; //登录用户名
    protected $companyId; //登录用户公司ID
    protected $companyName; //登录用户公司
    protected $isManager; //商户管理员
    protected $user_branch;//商户id
    protected $version = 100; //版本号
   public function _initialize(){
       checkLogin();
       $allow_pass = false;
       if (session('user_id')) {
           $this->_permissionList = session('permissionList');
           $this->userId = session('user_id');
           $this->userName = session('user_name');
           $this->companyId = session('currBranchId');
           $this->companyName = session('currBranchName');
           $this->user_branch = getBrowseBranchId();
           $this->isManager = $this->getHasManager();
           $allow_pass = $this->isManager;
           $this->handlerPermissionsProcessing();
           if (empty($this->_permission_name)) {
               $this->_permission_name = CONTROLLER_NAME;
           }
           if (empty($this->_permission_action_name)){
               $this->_permission_action_name = ACTION_NAME;
           }
           if (!$this->isManager) {
               if (in_array($this->_permission_action_name,explode('|',$this->_permissionList[ACCESS_SYS_ACTIONS_KEY])) === false) { //不在权限范围，放行
                   $allow_pass = true;
               } else {
                   $menuList = $this->_permissionList[ACCESS_MENUS_KEY];
                   if ($menuList[$this->_permission_name] && $menuList[$this->_permission_name]["allow"]) {
                       if ($menuList[$this->_permission_name][ACCESS_MENU_ACTIONS_KEY][$this->_permission_action_name]) {
                           $allow_pass = true;
                       }
                   }else{
                       $allow_pass = $this->_checkAllowPermits($this->_permission_action_name);
                   }
               }
           }
           if(session("user_type") == USER_TYPE_COMPANY_MANAGER){
               $user['is_valid'] = M("SysUser")->where("id = ".session('user_id'))->getField("is_valid");
               if(!$user['is_valid']){
                   $allow_pass = false;
               }
           }
           $this->validBranchAgreement();
       }
       if (!$allow_pass) {
           if (IS_AJAX) {
               $this->responseJSON(buildMessage("无此功能操作权限！", 1));
           } else {
               die("无此功能操作权限");
           }
       }
       $this->assignPermissions($this->_permission_name);
       if(IS_GET) {
           $this->assign('version',$this->version);
       }
   }

   protected function validBranchAgreement(){
       //服务暂停到期
       $allow = true;
       $condition['branch_id'] = getBrowseBranchId();
       if($condition['branch_id'] != 1){
           $branch_role = M("SysBranch")->where("id = ".$condition['branch_id'])->getField("branch_role");
           if($branch_role == ROLE_ID_COMPANY_FREE){
               return false;
           }
           $agreement = M("SysBranchAgreement")->where($condition)->count();
           if($agreement){
               $where['is_valid'] = 0;
               $where['end_date'] = array("elt",date("Y-m-d"));
               $where['_logic'] = "or";
               $condition['_complex'] = $where;
               $count = M("SysBranchAgreement")->where($condition)->count();
               if($count != 0){
                   $allow = false;
               }
           }else{
               $allow = false;
           }
           if(!$allow){
               $this->display("Index/user_loss");
               die();
           }
       }
   }

    //除了设置权限，还可以在controller里面定时特殊情况,比如可以取数据，但是不能查看编辑的情况
    protected function _checkAllowPermits($action){
        return false;
    }
    public function assignPermissions($controller = CONTROLLER_NAME) {
        $permissions = array();
        $menuList = $this->_permissionList[ACCESS_MENUS_KEY];
        if ($menuList[$this->_permission_name] && ($this->isManager || $menuList[$this->_permission_name]["allow"])) {
            $permissions = $menuList[$controller][ACCESS_MENU_ACTIONS_KEY];
        }
        if ($this->isManager) {
            $permissions["_IS_Manager_"] = 1;
        }
        $this->assign('menuList',$menuList);
        $this->assign("permissions", $permissions);
    }
    protected function responseJSON($data) {
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (!$jsonData){
            $jsonData = json_encode(try_convert_to_utf8($data),JSON_UNESCAPED_UNICODE);
            if (!$jsonData) {
                $this->ajaxReturn(json_encode(buildMessage(json_last_error_msg()), 1), "EVAL");
            }
        }
        $this->ajaxReturn($jsonData, "EVAL");
    }
    //计算用户角色，1表示Leader管理员，2表示为管理员的角色,Leader是平台指定的，角色为ROLE_COMPANY_MANAGER
    private function getHasManager(){
        $result = false;
        if (session('user_type') == USER_TYPE_COMPANY_MANAGER) {
            $where['id'] = $this->user_branch;
            $where['leader_id'] = $this->userId;
            $where['parent_id'] = 1;
            if (M("SysBranch")->where($where)->count() > 0) {
                $result = true;
            } else {
                if (!empty(session("user_role"))) {
                    $condition["id"] = array("in", session("user_role"));
                    $condition["is_admin"] = 1;
                    if (M("SysRole")->where($condition)->count() > 0) {
                        $result = true;
                    }
                }
            }
        }
        return $result;
    }
    /*
     * 处理程序权限处理
     * 用于模拟ESAdmin的角色权限管理
     */
    protected function handlerPermissionsProcessing()
    {

    }
}
