<?php

namespace ESAdmin\Controller;
use Common\Lib\Controller\DataController;

class  VcrSubjectRelationController extends DataController {
    public function indexAction() {
        L(include MODULE_PATH . 'Lang/' . LANG_SET . '/' . strtolower("VcrSubject") . '.php');
        parent::indexAction();
    }
    protected function _before_display_dataview(&$data) {
        L(include MODULE_PATH . 'Lang/' . LANG_SET . '/' . strtolower("VcrSubject") . '.php');
        parent::_before_display_dataview($data);
    } 
            
    public function updateRelationAction($id, $user_subject_id = null){
        if(empty($user_subject_id)){
            $this->responseJSON(buildMessage("请设置系统对应科目", 1));
        }else{            
            if (M("VcrSubject")->where("id=$user_subject_id")->setField("vcr_sys_subject_id", $id)){
                $this->responseJSON(buildMessage("完成"));
            }else{
                $this->responseJSON(buildMessage("映射失败", 1));
            }
        }
    }
    
    public function relativeAllAction(){
        $vcr_sys_subjects = M("VcrSysSubject")->field("id,name")->select();
        $vcr_sys_subject_kvs = array();
        foreach ($vcr_sys_subjects as $key => $value) {
            $vcr_sys_subject_kvs[$value["name"]] = $value["id"];
        }
        $update_values = array();
        $subjects = D("VcrSubject")->setDacFilter("a")->field("a.id,a.name,a.branch_id")->select();
        foreach ($subjects as $key => $value) {
            if ($value["branch_id"] == $this->_user_session->currBranchId){
                $vcr_sys_subject = $vcr_sys_subject_kvs[$value["name"]];
                if ($vcr_sys_subject){
                    $update_values[] = sprintf("(%d,%d)", $value["id"], $vcr_sys_subject);
                }
            }
        }
        if ($update_values){
            $sql = "insert into vcr_subject (id,vcr_sys_subject_id) values ". implode(",", $update_values) ." on duplicate key update vcr_sys_subject_id=values(vcr_sys_subject_id)";
            if (M()->execute($sql)){
                $this->responseJSON(buildMessage());
            }else{
                $this->responseJSON(buildMessage("映射失败", 1));
            }
        }
    }
}