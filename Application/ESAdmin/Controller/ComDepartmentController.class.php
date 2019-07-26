<?php

namespace ESAdmin\Controller;

use ESAdmin\Controller\BranchBaseController;

class ComDepartmentController extends BranchBaseController {
        /*按类别过滤*/
    public function keyNameListAction($selected = "", $term = "", $select_all = false) {
        $condition = $this->getChosenSearchCondition($selected, $term);
        $tree_list = D(CONTROLLER_NAME)->getUserBranchList($condition, $this->_user_session, false);
        $this->ajaxReturn($tree_list);
    }
    /*用于获取下级公司*/
    public function treeCompanyAction() {
        $condition = array();
        if (trim(I('keyword')) != '') {
            $condition['a.name'] = array('like',sprintf('%%%s%%',trim(I('keyword')) ));
        }
        $condition['a.type'] = 1;
        $user_session = session(USER_SESSION_KEY);
        if ($user_session->userIdentity == USR_IDENTITY_NORMAL) {
            if ($user_session->userDataAccess['_branchs']) {
                $condition['a.id'] = array('in',$user_session->userDataAccess['_branchs']);
            } else {
                $this->ajaxReturn([]);
            }
        }
        $departments = D(CONTROLLER_NAME)->getUserBranchTree($condition, $this->_user_session);
        $this->ajaxReturn($departments[0]['children']);
    }
    /*用于获取下级公司*/
    public function treeCompanyListAction() {
        $condition = array();
        if (trim(I('keyword')) != '') {
            $condition['a.name'] = array('like',sprintf('%%%s%%',trim(I('keyword')) ));
        }
        $condition['a.type'] = 1;
//        $user_session = session(USER_SESSION_KEY);
//        if ($user_session->userIdentity == USR_IDENTITY_NORMAL) {
//            if ($user_session->userDataAccess['_branchs']) {
//                $condition['a.id'] = array('in',$user_session->userDataAccess['_branchs']);
//            } else {
//                $this->ajaxReturn([]);
//            }
//        }
        $departments = D("ComCompany")->getUserBranchTree($condition, $this->_user_session);
        $this->ajaxReturn($departments[0]['children']);
    }
}
