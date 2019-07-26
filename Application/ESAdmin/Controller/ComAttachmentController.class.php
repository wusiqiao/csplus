<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\AttachmentController;

class  ComAttachmentController extends AttachmentController {
    private $_user_session;
    public function _initialize()
    {
        $this->_user_session = session(USER_SESSION_KEY);
    }
    protected function getUserId(){
        return $this->_user_session->userId;
    }

    protected function getBranchId(){
        return $this->_user_session->currBranchId;
    }

    protected function getUserName(){
        return $this->_user_session->userName;
    }

    public function noteRecordAction(){
        $this->display('Public/noterecord');
    }
}