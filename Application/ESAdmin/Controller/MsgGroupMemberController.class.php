<?php
/**
 * @auhor kcg
 * */
namespace ESAdmin\Controller;

use Common\Lib\Controller\MsgBaseController;

/**
 *消息组成员管理
 * */
class MsgGroupMemberController extends MsgBaseController{
    protected function getUserId(){
        if(!$this->_user_session){
            E("未登录");
        }

        return $this->_user_session->userId;
    }

    protected function getBranchId(){
       return getBrowseBranchId();
    }

    protected function getUserType(){
        if(!$this->_user_session){
            E("未登录");
        }

        return $this->_user_session->userType;
    }

    protected function getCurrBranchId(){
        if(!$this->_user_session){
            E("未登录");
        }

        return $this->_user_session->currBranchId;
    }
    protected function _initialize(){
        $this->_user_session = session(USER_SESSION_KEY);
    }

    protected $_user_session;
}