<?php

namespace ESAdmin\Controller;
use Common\Lib\Controller\TreeController;

class  VcrSysSubjectController extends TreeController {
    public function indexAction() {
        L(include MODULE_PATH . 'Lang/' . LANG_SET . '/' . strtolower("VcrSubject") . '.php');
        parent::indexAction();
    }

    public function listAction($id = null) {
        $ent_type_id = I("q-ent_type_id");
        if ($id == null && empty($ent_type_id)){
            $this->ajaxReturn([]);
        }
        $_filter = array();
        $this->_parsefilter($_filter);
        if ($ent_type_id && count($_filter) == 1) { //只有传入企业类型，没其他条件，只列出父节点
            $_filter["a.parent_id"] = intval($id);
        }
        $sort = I("post.sort");
        $order = I("post.order");
        $_order = empty($sort) ? array("a.id") : array("a.$sort $order");
        $list = D(CONTROLLER_NAME)->setDacFilter("a")->relation(true)->field("a.*")->where($_filter)->order($_order)->select();
        foreach ($list as $key => $value) {
            $list[$key]["state"] = empty($value["child_count"]) ? "opened" : "closed";
            $list[$key]["type_name"] = SUBJECT_TYPRS[$value["type_id"]];
        }
        $this->ajaxReturn($list);
    }

    protected function _before_display_dataview(&$data) {
        L(include MODULE_PATH . 'Lang/' . LANG_SET . '/' . strtolower("VcrSubject") . '.php');
        $data["type_name"] = SUBJECT_TYPRS[$data["type_id"]];//ENTERPRISE_TYPES;
        $data["ent_type_name"] = ENTERPRISE_TYPES[$data["ent_type_id"]];
        parent::_before_display_dataview($data);
    }   



    public function importAction() {
        if (IS_GET) {
            $this->display();
        } else {
            set_time_limit(0);
            if (!empty($_FILES)) {
                $msg = "";
                $uploader = getUploader("temp/", array('xls', 'xlsx', 'csv'));
                $info = $uploader->uploadOne($_FILES["subject-file"]);
                if (!$info) {
                    $msg = buildMessage($uploader->getError(), 1);
                } else {
                    $ent_type = I("post.ent_type");
                    if (!isset($ent_type)){
                        $this->responseJSON(buildMessage("企业类型不能为空！", 1));
                    }
                    $filePath = ltrim($uploader->rootPath, ".") . $info['savepath'] . $info['savename'];
                    $saveResult = D(CONTROLLER_NAME)->saveDataFromExcel($filePath, $ent_type);
                    if (!is_array($saveResult)) {
                        $msg = buildMessage("导入完成，导入" . $saveResult . "条数据");
                    } else {
                        $msg = $saveResult;
                    }
                    unset($uploader);
                }
                $this->responseJSON($msg);
            } else {
                $this->responseJSON(buildMessage("文件不能为空！", 1));
            }
        }
    }

    protected  function setAutocompleteExtentQuery(&$filter){
        $ent_type_id = I("get.ent_type_id");
        if ($ent_type_id){
            $filter["a.ent_type_id"] = $ent_type_id;
        }
    }

    public function getMaxNoByParentAction($parent_id, $ent_type_id){
        if ($parent_id && $ent_type_id){
           exit(D(CONTROLLER_NAME)->getMaxNo($parent_id, $ent_type_id));
        }
        exit("");
    }
}