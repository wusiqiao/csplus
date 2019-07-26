<?php

namespace Common\Lib\Controller;

use Common\Lib\Controller\ControllerBase;

class DataController extends ControllerBase {

    const ACTION_LIST = 0;      //  浏览
    const ACTION_ADD = 1;      //  新增显示
    const ACTION_DETAIL = 2;   //  修改显示
    const ACTION_COPY = 3; //复制
    const ACTION_BOTH = 32;      //  包含上面两种方式

    public function indexAction() {
        $this->assignPermissions();
        $this->display($this->_get_index_template());
    }

    public function selectAction() {
        $this->display();
    }

    public function listAction() {
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $_order = array();
        $this->_parseOrder($_order);
        $_filter = array();
        $this->_parseFilter($_filter);
        if ($this->hasRelationCondition($_filter)) { //条件中是否有关联表的查询字段，关联字段查询格式为 q-b*xxx(q:查询模式，b管理部的别名,xxx关联表字段
            $count = D(CONTROLLER_NAME)->setDacFilter("a")->relation(true)->where($_filter)->count();
        }else{
            $count = D(CONTROLLER_NAME)->setDacFilter("a")->where($_filter)->count();
        }
        $list = D(CONTROLLER_NAME)->setDacFilter("a")->relation(true)->field("a.*")->where($_filter)->page($page_index, $page_size)->order($_order)->select();
        $this->_before_list($list);
        $result["total"] = $count;
        $result["rows"] = $list;
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($result));
    }

    public function selectListAction() {
        $this->listAction();
    }

    public function _before_addAction() {
        define("__FORM_ACTION__", "add");
    }

    protected function _getAddData() {
        $record = array();
        $this->_before_add($record);
        return $record;
    }

    protected function _setAddData($action) {
        $this->checkRolePermit(); //版本权限检查
        $model = D(CONTROLLER_NAME);
        $post_data = I('post.');
        $this->_before_write($action, $post_data);
        if ($data = $model->create($post_data)) {
            try {
                $model->startTrans();
                $last_id = $model->add($data, array("callback"=>true));
                $model->commit();
            } catch (\Think\Exception $ex) {
                $model->rollback();
                return buildMessage("新增失败：" . $ex->getMessage(), 1);
            }
            if ($last_id !== false) {
                $this->addLog($last_id);
                $result_data = $this->_getLastData($last_id);
                return buildMessage($result_data);
            } else {
                return buildMessage("新增失败：" . $model->getError(), 1);
            }
        } else {
            return buildMessage("新增失败：" . $model->getError(), 1);
        }
    }

    public function addAction() {
        if (IS_POST) {
            $result = $this->_setAddData(self::ACTION_ADD);
            $this->responseJSON($result);
        } else {
            $record = $this->_getAddData();
            $this->assign("model", $record);
            $this->assignPermissions();
            exit($this->fetch($this->_get_detail_template($record)));
        }
    }

    public function copyAction($id = 0) {
        if (IS_POST) {
            $_POST["_COPY_DATA_ID_"] = I("post.id");
            unset($_POST["id"]);
            $result = $this->_setAddData(self::ACTION_COPY);
            $this->responseJSON($result);
        } else {
            define("__FORM_ACTION__", "copy");
            if ($id){
                $condition["a.id"] = $id;
                $record = D(CONTROLLER_NAME)->setDacFilter("a")->relation(true)->field("a.*")->where($condition)->find();
            }
//            $record = D(CONTROLLER_NAME)->where("id=$id")->find();
            $this->_before_copy($record);
            $this->assign("model", $record);
            $this->assignPermissions();
            exit($this->fetch($this->_get_detail_template($record)));
        }
    }

    /**跨部门复制
     * @param int $id
     */
    public function copyToAction() {
        if ($this->_user_session->isAdmin) {
            if (IS_POST) {
                $source_id = I("post.source_id");
                $dest_branch = I("post.dest_branch");
                if (is_array($source_id) && $dest_branch) {
                    $conditon["id"] = array("in", $source_id);
                    $recordset = M(CONTROLLER_NAME)->where($conditon)->select();
                    foreach ($recordset as $key=>$record) {
                        $source_id = $record["id"];
                        $this->_before_copyTo($recordset[$key], $dest_branch);
                        unset($recordset[$key]["id"]);//id主键要清空，否则主键重复
                        $recordset[$key]["branch_id"] = $dest_branch;
                        $last_id = M(CONTROLLER_NAME)->add($recordset[$key]);
                        $recordset[$key]["id"] = $last_id;
                        $this->_after_copyTo($recordset[$key] ,$source_id, $dest_branch);
                    }
                    $this->responseJSON(buildMessage($recordset[$key]));
                }else{
                    $this->responseJSON(buildMessage("参数错误", 1));
                }
            } else {
                $this->display("Public:choise_branch");
            }
        }
    }

    public function batchAddAction() {
        if (IS_POST) {
            $result = buildMessage("批次新增完成");
            $datas = I('data');
            $model = D(CONTROLLER_NAME);
            try {
                $model->startTrans();
                foreach ($datas as $data) {
                    $this->_before_write(self::ACTION_ADD, $data);
                    $model->add($data, array("callback" => true));
                }
                $model->commit();
            } catch (\Think\Exception $ex) {
                $model->rollback();
                $result = buildMessage("保存失败：".$ex->getMessage(),1);
            };
            $this->responseJSON($result);
        } else {
            $this->display("Public:batch_add");
        }
    }

    protected function _before_copyTo(&$source_data, $dest_branch){

    }

    protected function _after_copyTo(&$new_data, $source_id, $dest_branch){
    }

    protected function _before_copy(&$data) {
        $this->_before_detail($data);
    }

    public function _before_detailAction() {
        define("__FORM_ACTION__", "update");
    }

    protected function _getDetailData($id) {
        $record = array();

        if ($id){
            $condition["a.id"] = $id;
            $record = D(CONTROLLER_NAME)->setDacFilter("a")->relation(true)->field("a.*")->where($condition)->find();
        }
        $this->_before_detail($record);
        return $record;
    }
    
    protected function _getLastData($id) {
        $record = array();
        if ($id){
            $condition["a.id"] = $id;
            $record = D(CONTROLLER_NAME)->setDacFilter("a")->relation(true)->field("a.*")->where($condition)->find();
        }
        $this->_before_detail($record);
        return $record;
    }

    public function detailAction($id = null) {
        $this->assignPermissions();
        $record = $this->_getDetailData($id);
        $this->assign("model", $record);
        exit($this->fetch($this->_get_detail_template($record)));
    }

    public function updateAction($id) {
        if (IS_POST) {
            $model = D(CONTROLLER_NAME);
            if (!$model->checkDataPermission($id)) {
                $this->responseJSON(buildMessage("保存失败：您没有权限更新此记录！", 1));
            }
            $post_data = I('post.');
            $this->_before_write(self::ACTION_DETAIL, $post_data);
            if ($data = $model->create($post_data)) {
                $updated = false;
                try {
                    $model->startTrans();
                    $updated = $model->where("id=$id")->update($data);
                    $model->commit();
                } catch (\Think\Exception $ex) {
                    $model->rollback();
                    $this->responseJSON(buildMessage("保存失败：" . $ex->getMessage(), 1));
                }

                if ($updated !== false) {
                    $this->addLog($id);
                    $result_data = $this->_getLastData($id);
                    $this->responseJSON(buildMessage($result_data));
                }
            } else {
                $this->responseJSON(buildMessage("保存失败：" . $model->getError(), 1));
            }
        }
    }

    public function deleteAction($id = 0) {
        $this->_before_delete($id);
        $model = D(CONTROLLER_NAME);
        $condition["id"] = array("in", $id);
        if (!$model->checkDataPermission($id)) {
            $this->responseJSON(buildMessage("删除失败：您没有权限删除此记录！", 1));
        }
        $result = false;
        try {
            $model->startTrans();
            $result = $model->where($condition)->delete();
            $model->commit();
        } catch (\Think\Exception $ex) {
            $model->rollback();
            $this->responseJSON(buildMessage("删除失败：" . $ex->getMessage(), 1));
        }
        if ($result) {
            $this->addLog($id);
            $this->responseJSON(buildMessage("删除成功！"));
        }
    }

    //关键字异步查询时，查询关键字对应的条件，一般数据资料把关键字作为name或querykey字段查询,
    //单据的话重载查询字段改为bill_no条件
    protected function getChosenKeywordCondition($keyword) {
        return sprintf("a.querykey like '%s%%' OR a.name like '%s%%'", $keyword, $keyword);
    }

    /* 显示的字段，默认为name */

    protected function getChosenNameField() {
        return "a.name";
    }

    public function getChosenSearchCondition($selected = "", $term = "") {
        $condition = array();
        //附加条件，在联动选择的时候用到
        $queryParams = I("params");
        if ($queryParams && is_array($queryParams)) {
            foreach ($queryParams as $key => $value) {
                $condition["a.$key"] = $value;
            }
        }
        if (empty($term)) {
            if ($selected) { //前端传入的选择ID列表
                // $condition['_string'] = "(a.id in ($selected))";//条件应该为OR，移出此函数
            }
        } else { //输入查询
            $condition['_string'] = $this->getChosenKeywordCondition($term);
        }
        return $condition;
    }

    protected function getKeyNameList($selected = "", $term = "", $select_all = false, $relation = false, $order = "a.id") {
        $condition = $this->getChosenSearchCondition($selected, $term);
        $this->_before_getKeyNameList($condition);
        $name_field = $this->getChosenNameField();
        $fields = "a.id as value,$name_field as text";
        $ext_fields = I("post.fields");
        if ($ext_fields) {
            $ext_fields = "a." . str_replace(',', ',a.', $ext_fields);  //添加前缀
            $fields = $fields . "," . $ext_fields;
        }
        $list = D(CONTROLLER_NAME)->getKeyNameList($select_all, $selected, $condition, $fields, $relation, $order);
        $this->_after_getKeyNameList($list);
        return $list;
    }

    /* 过滤条件处理 */

    protected function _before_getKeyNameList(&$condition) {
        
    }

    protected function _get_detail_template($record) {
        return "edit";
    }

    protected function _get_index_template() {
        return "index";
    }

    public function keyNameListAction($selected = "", $term = "", $select_all = false) {
        $list = $this->getKeyNameList($selected, $term, $select_all);
        $this->ajaxReturn($list);
    }

    //新增前检查商城版本
    private function checkRolePermit(){
        //版本限制
//        if ($this->_user_session->branchRole != ROLE_ID_COMPANY_MANAGER) {
//            return buildMessage("新增失败：记录数超过版本限制", 1);
//        }
    }
    //crete后，save或add前调用,子类重新，请勿删除
    protected function _before_write($type, &$data) {
        if ($data["name"] && empty($data["querykey"])) {
            $data["querykey"] = firstPinyin($data["name"]);
        }

        if (self::ACTION_ADD === $type || self::ACTION_COPY === $type) {
            if (empty($data["branch_id"])) {
                $data["branch_id"] = $this->_user_session->currBranchId;
            }
            $data["creater"] = $this->_user_session->userName;
            $data["creater_id"] = $this->_user_session->userId;
            //保证附件编号不重复
            $attach_group = $data['attach_group'];
            if ($attach_group){
                $att_condition["attach_group"] = $attach_group;
                if (D(CONTROLLER_NAME)->where($att_condition)->count() > 0){
                    $data['attach_group'] = genUniqidKey();
                }
            }
        }
        if (empty($data["user_id"])) {
            $data["user_id"] = $this->_user_session->userId;
        }
        //设置为空标志
        foreach ($data as $key => $value) {
            if (strtoupper($value) == "NULL") {
                $data[$key] = null;
            }
        }
    }

    protected function build_tree($list, $parent_id, $leafList, $relationList, $onlyShowExists = false) {
        if ($relationList) {
            foreach ($relationList as $value) {
                $combine = $value["parent_id"] . "_" . $value["id"];
                $relation_combineList[$combine] = $value;
            }
        }
        return $this->internal_build_tree($list, $parent_id, $leafList, $relation_combineList, $onlyShowExists);
    }

    //$leafList:叶子节点列表，如功能权限里面的操作列表
    private function internal_build_tree($list, $parent_id, $leafList, $relation_combineList, $onlyShowExists) {
        $result = array();
        foreach ($list as $key => $value) {
            if ($value["parent_id"] == $parent_id) {
                $children = $this->internal_build_tree($list, $value["id"], $leafList, $relation_combineList, $onlyShowExists);
                if ($children) {
                    $value['children'] = $children;
                } else {
                    if ($leafList) {
                        foreach ($leafList as $item) {
                            $combine = $value["id"] . "_" . $item["id"];
                            $exists = (!empty($relation_combineList) && !empty($relation_combineList[$combine]));
                            $item["checked"] = $exists && ($relation_combineList[$combine]["checked"]);
                            $item["parent_id"] = $value["id"];
                            $item["leaf"] = true;
                            if (($exists && $onlyShowExists) || (!$onlyShowExists)) {
                                $value['children'][] = $item;
                            }
                        }
                        if (!empty($value['children'])) {
                            $value['leaf'] = false;
                            $value['state'] = "closed";
                        }
                    } else {
                        $value['leaf'] = true;
                    }
                }
                $result[] = $value;
            }
        }
        return $result;
    }
    
    public function addLog($content = "", $kind = 0) {
        $data["user_name"] = $this->_user_session->staffName == "" ? $this->_user_session->userName : $this->_user_session->staffName;
        $data["branch_name"] = $this->_user_session->currBranchName;
        $data["kind"] = $kind;
        $data["func"] = CONTROLLER_NAME;
        $data["operation"] = ACTION_NAME;
        $data["content"] = $content;
        $data["create_time"] = time();
        $data["ip"] = get_client_ip();
        M("SysLog")->add($data);
    }

    public function exportImageAction() {
        if (IS_POST) {
            Vendor('PHPExcel18.PHPExcel');
            Vendor('PHPExcel18.PHPExcel.Writer.Excel2007');
            //或者include 'PHPExcel/Writer/Excel5.php'; 用于输出.xls的
            //创建一个excel
            $objPHPExcel = new \PHPExcel();
            //保存excel—2007格式
            $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);

            $objDrawing = new \PHPExcel_Worksheet_Drawing();
            /* 设置图片路径 切记：只能是本地图片 */
            $objDrawing->setPath("./Uploads/20151023/5629c9fa9b87f.png");
            /* 设置图片高度 */
            $objDrawing->setHeight(180); //照片高度
            $objDrawing->setWidth(150); //照片宽度
            /* 设置图片要插入的单元格 */
            $objDrawing->setCoordinates('E2');
            /* 设置图片所在单元格的格式 */
            $objDrawing->setOffsetX(5);
            $objDrawing->setRotation(5);
            $objDrawing->getShadow()->setVisible(true);
            $objDrawing->getShadow()->setDirection(50);
            $objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
            header("Pragma: public");
            header("Expires: 0");
            header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
            header("Content-Type:application/force-download");
            header("Content-Type:application/vnd.ms-execl");
            header("Content-Type:application/octet-stream");
            header("Content-Type:application/download");
            header('Content-Disposition:attachment;filename="resume.xls"');
            header("Content-Transfer-Encoding:binary");
            $objWriter->save('php://output');
        } else {
            $this->display("./Application/Common/Layout/Default/export.html");
        }
    }

    protected function _before_add(&$data) {
        $data["is_valid"] = 1;
        //传入带"q-"前缀的表示需要传到新增窗体，统一存入model，
        //保证修改和新增不需要设置两次
        foreach ($_REQUEST as $key => $value) {
            if (stripos($key, "q-") === 0) {
                $real_key = ltrim($key, "q-");
                $data[$real_key] = I($key);
            }
        }
        $this->_before_display_dataview($data);
    }

    protected function _before_detail(&$data) {
        if ($data["comments"]) {
            $data["comments"] = html_entity_decode($data["comments"]);
        }
        $this->_before_display_dataview($data);
    }

    protected function _before_list(&$list) {

    }

    protected function _before_display_dataview(&$data) {
        
    }

    protected function _after_getKeyNameList(&$list) {
        
    }

    final protected function uploadOne($name, $path, $types, $maxSize = 10240000) {
        if (!empty($_FILES)) {
            $uploader = getUploader($path, $types, $maxSize);
            if ($_FILES[$name]["error"] === 0) {
                $info = $uploader->uploadOne($_FILES[$name]);
                if (!$info) {// 上传错误提示错误信息
                    $this->responseJSON(buildMessage("上传失败:" . $uploader->getError(), 1));
                } else {
                    return $uploader->rootPath . $info['savepath'] . $info['savename'];
                }
            }
            unset($uploader);
        }
        return '';
    }

    /**根据模块获取最大单据编号--存在一个表存不同类型单据的情况，所以和getTableMaxBillNo会有不同
     * @param string $billdate_field
     * @param string $billno_field
     * @param int $serinal_size
     * @param array $condition
     * @return string
     */
    protected function getMaxBillNo($billdate_field = "bill_date", $billno_field = "bill_no", $serinal_size = 4, $condition = array()) {
        return D(CONTROLLER_NAME)->getMaxBillNo($billdate_field, $billno_field, $serinal_size, $condition);
    }

    /**根据表获取最大单据编号
     * @param string $billdate_field
     * @param string $billno_field
     * @param int $serinal_size
     * @param array $condition
     * @return string
     */
    protected function getTableMaxBillNo($billdate_field = "bill_date", $billno_field = "bill_no", $serinal_size = 4, $condition = array()) {
        return D(CONTROLLER_NAME)->getTableMaxBillNo($billdate_field, $billno_field, $serinal_size, $condition);
    }


    protected function getTableMaxBillNoByUserBranch($billdate_field = "bill_date", $billno_field = "bill_no", $serinal_size = 4) {
        $condition["branch_id"] = $this->_user_session->currBranchId;
        return $this->getTableMaxBillNo($billdate_field, $billno_field, $serinal_size, $condition);
    }

    protected function getMaxBillNoByUserBranch($billdate_field = "bill_date", $billno_field = "bill_no", $serinal_size = 4) {
        $condition["branch_id"] = $this->_user_session->currBranchId;
        return $this->getMaxBillNo($billdate_field, $billno_field, $serinal_size, $condition);
    }

/**
 * 获取最大编号
 * @param type $prefix  //前缀
 * @param type $no_field //编号对应字段
 * @param type $serinal_size //编号长度
 * @param type $condition //其他条件
 * @return string
 */
    protected function getMaxNo($prefix = "", $no_field = "no", $serinal_size = 4, $condition = array()) {
        return D(CONTROLLER_NAME)->getMaxNo($prefix, $no_field, $serinal_size, $condition);
    }

    protected function getMaxNoByUserBranch($prefix = "", $no_field = "no", $serinal_size = 4) {
        $condition["branch_id"] = $this->_user_session->currBranchId;
        return $this->getMaxNo($prefix, $no_field, $serinal_size, $condition);
    }
    /**
     * 
     * @param type $PHPExcel
     * @param type $_filter 条件
     * @param type $_order  排序
     * @return type 返回导出的文件名
     */
    protected function doExportAction($PHPExcel, $_filter) {
        return null;
    }

    protected function getShortBillNo() {
        return date("YmdHis");
    }

    public function autocompleteAction(){
        $search = I("get.search");
        $result =  I("get.result");
        $_filter = array();
        $_order = array();
        $this->_parseAutoCompleteOrder($_order);
        if (empty($search) || empty($result)){
            $this->ajaxReturn([]);
        }
        $query = I("q");
        $limit = I("get.limit");
        //result传过来是加号拼接的 "name+mobile+shortname"
        $result_fields = "a.id,a.".str_replace("+", ",a.", $result);
        if ($query) {
            $query = str_replace("'", "", $query);
            $search_fields = explode("+", $search);
            foreach ($search_fields as $search_field) {
                $condition["a." . $search_field] = array('like', '%' . $query . '%');
            }
            $condition['_logic'] = 'or';
            $_filter['_complex'] = $condition;
        }
        $this->setAutocompleteExtentQuery($_filter);
        $model = D(CONTROLLER_NAME)->setDacFilter("a")->relation(true)->field($result_fields)->where($_filter);
        if ($limit){
            $model->limit($limit);
        }
        $list = $model->order($_order)->select();
        $this->_before_autoCompleteList($list,$result_fields);
        $this->ajaxReturn($list);
    }

    protected  function setAutocompleteExtentQuery(&$filter){

    }

    protected function _before_delete($id) {

    }

    public function _before_autoCompleteList(&$list,&$result_fields){

    }

    //autocomplete排序
    public function _parseAutoCompleteOrder(&$_order){

    }
}
