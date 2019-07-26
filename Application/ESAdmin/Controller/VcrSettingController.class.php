<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class  VcrSettingController extends DataController {

    public function indexAction() {
        L(include MODULE_PATH.'Lang/'.LANG_SET.'/'.strtolower("ComCompany").'.php');
        $this->assignPermissions(); //权限设置
        $model = D("ComCompany")->where("id=" . $this->_user_session->currBranchId)->find();
        $model["ent_type_name"] = ENTERPRISE_TYPES[$model["ent_type_id"]];
        $this->cfgData = D("VcrConfig")->where("branch_id=" . $this->_user_session->currBranchId)->find();
        $this->assign("model", $model);
        $this->assign("enterprise_types", ENTERPRISE_TYPES);
        $this->display();
    }

    public function updateAction($id){
        if (IS_POST) {
            if(empty($id)){
                $this->responseJSON(buildMessage("网络错误，请刷新重试", 1));
            }
            $ent_type_id =I("post.ent_type_id", null);
            if (empty($ent_type_id)){
                $this->responseJSON(buildMessage("保存失败：企业类型不能为空", 1));
            }
            $ent_scale =I("post.ent_scale", null);
            if (!isset($ent_scale)){
                $this->responseJSON(buildMessage("保存失败：请选择企业规模", 1));
            }
            $voucher_review = I("post.vcr_draf_review",null);
            if (!isset($voucher_review)){
                $this->responseJSON(buildMessage("保存失败：请选择凭证审核选项", 1));
            }
            $model = D("ComCompany");
            $data = $model->create();
            if ($data) {
                $updated = false;
                try{
                    $model->startTrans();
                    $updated = $model->where("id=$id")->update($data);
                    $this->saveVcrConfig($id);
                    if(I("post.reset_mapping_subject")){
                        D("VcrSubject")->mapSubject($data['id']);
                    }
                    $model->commit();
                } catch (Think\Exception $ex) {
                    $model->rollback();
                }
                if ($updated !== false) {
                    $this->addLog($id);
                    $this->responseJSON(buildMessage($data));
                } else {
                    $this->responseJSON(buildMessage("保存失败：" . $model->getError(), 1));
                }
            } else {
                $this->responseJSON(buildMessage("保存失败：" . $model->getError(), 1));
            }
        }
    }

    private function saveVcrConfig($branch_id){
        $VcrConfigModel = M("VcrConfig");
        if ($cfgData = $VcrConfigModel->create()){
            $condition["branch_id"] = $branch_id;
            $result = $VcrConfigModel->where($condition)->count();
            if ($result == 0){
                $cfgData["branch_id"]  = $branch_id;
                $VcrConfigModel->add($cfgData);
            }else{
                $VcrConfigModel->where($condition)->save($cfgData);
            }
        }
    }

}