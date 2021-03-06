<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/5
 * Time: 14:43
 */

namespace ESAdmin\Controller;


use Common\Lib\Controller\DataController;

class ComTweetsAdditionalController extends DataController
{
    public function indexAction() {
        $this->assignPermissions(); //权限设置
        if ($this->_user_session->userType == USER_TYPE_COMPANY_MANAGER) {
            $model = D("ComTweetsAdditional")->where("branch_id=" . $this->_user_session->currBranchId)->find();
            define("__FORM_ACTION__", empty($model)?"add":"update");
            $this->assign("model", $model);
        }
        $this->display();
    }

    public function clearPictureAction($name, $file){
        if($name == 'bottom_pic2' || $name == 'bottom_pic1' || $name == 'top_pic'){
            M(CONTROLLER_NAME)->where("branch_id=" . $this->_user_session->currBranchId)->setField($name,'');
            $file_name = realpath(".")."/".$file;
            unlink($file_name);
            $this->ajaxReturn();
        }
    }
//    protected function _before_write($type, &$data)
//    {
//        parent::_before_write($type, $data); // TODO: Change the autogenerated stub
//        var_dump($_POST);die;
//    }

}