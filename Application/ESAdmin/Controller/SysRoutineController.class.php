<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/5
 * Time: 14:43
 */

namespace ESAdmin\Controller;


use Common\Lib\Controller\DataController;

class SysRoutineController extends DataController
{
    public function indexAction() {
        $this->assignPermissions(); //权限设置
        if ($this->_user_session->userType == USER_TYPE_COMPANY_MANAGER) {
            $model = D("SysRoutine")->where("branch_id=" . $this->_user_session->currBranchId)->find();
            if($model['tool_manager'] == 'all'){
                $tool_idall = $this->getToolListSingleId();
                $model['tool_manager'] = implode(',',$tool_idall);
            }
            define("__FORM_ACTION__", empty($model)?"add":"update");
            $this->assign("model", $model);
        }
        $this->display();
    }
    public function ToolNameListAction(){
        $tool = D('SysRoutine')->toolList();
        $data = array();
        foreach ($tool as $key => $val) {
            $data[] = ['value'=>$key,'text'=> $val];
        }
        $this->ajaxReturn($data);
    }
    protected function getToolListSingleId(){
        $tool = D('SysRoutine')->toolList();
        $data = array();
        foreach ($tool as $key => $val) {
            $data[] = $key;
        }
        return $data;
    }
//    protected function _before_write($type, &$data)
//    {
//        parent::_before_write($type, $data); // TODO: Change the autogenerated stub
//        var_dump($_POST);die;
//    }

}