<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;


class SysParamController extends DataController {

    public function indexAction(){
        $param_data = D(CONTROLLER_NAME)->getParamValue();
        $this->assign("model", $param_data);
        parent::indexAction();
    }
    
    public function updateAction() {
        if (IS_POST) {
            $model = D(CONTROLLER_NAME);
            $dataList = array();
            $data   =   I('post.');
            foreach ($data as $key => $value) {
               $dataList[] = array("key" => $key, "value"=>$value);             
            }
            $model->addAll($dataList, null, array("value"));
            S(SYS_PARAM_CACHE_KEY, null); //更新后要清除缓存
            $this->ajaxReturn(buildMessage("更新成功！"));
        }
    }
    
    public function uploadAction(){
        if ($_FILES){
            
        }
    }
}
