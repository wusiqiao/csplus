<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class CompanyInfoController extends DataController {

    public function indexAction() {
         L(include MODULE_PATH.'Lang/'.LANG_SET.'/'.strtolower("ComCompany").'.php');
        $this->assignPermissions(); //权限设置
//        if ($this->_user_session->userType == USER_TYPE_COMPANY_SALES) {
//            $model = D("ComCompany")->where("id=" . $this->_user_session->currBranchId)->find();
//            $this->assign("model", $model);
//            $this->assign("enterprise_types", getEnterprseType());
//        }
        $this->display();
    }

    public function updateAction($id){
       if (IS_POST) {
            $model = D("ComCompany");
            $data = $model->create();
            if ($data) {
                $updated = false;
                try{
                    $model->startTrans();
                    $updated = $model->where("id=$id")->update($data);
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

}
