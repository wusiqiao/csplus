<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\TreeController;

class VcrSubjectController extends TreeController {

    protected $export_type_com = 1;//企业科目导出
    protected $export_type_sys = 2;//标准科目导出

    public function indexAction(){
        $ent_type_id = getEnterpriseType($this->_user_session->currBranchId);
        $this->assign("ent_type_id",$ent_type_id);
        parent::indexAction();
    }

    public function _parsefilter(&$filter){
        parent::_parsefilter($filter);
        if(I("post.subject_type")){
            $type = I("post.subject_type");
            $filter['a.no'] = array("like","$type%");
            $filter['a.level'] = 1;
        }
        //获取未匹配科目
        if(I("get.type") == 1){
            unset($filter['a.level']);
            $filter['a.std_subject_id'] = array("exp","IS NULL");
            $name = I("post.name");
            if($name){
                $filter['a.name'] = array("like","%$name%");
            }
        }
    }

    public function mapedListAction() {
        $_filter["a.std_subject_id"] =array("GT", 0);
        $_order = array("a.no");
        $_filter['a.no'] = array("like",I("post.maped_subject_type")."%");
        $list = D(CONTROLLER_NAME)->setDacFilter("a")->relation(true)->field("a.*")->where($_filter)->order($_order)->select();
        $parent_list = $this->getParents($list);
        $result = array_merge($list, $parent_list);
        foreach ($result as $key => $value) {
            $result[$key]["type_name"] = SUBJECT_TYPRS[$value["type_id"]];
        }
        $treelist = list_to_tree($result);
        $this->ajaxReturn($treelist);
    }

    //先获取未映射的所有科目，由于需要组成树形列表，需要再去获取上级科目（不管是否已经映射）
    public function unMapListAction() {
        $_filter["a.std_subject_id"] = array("exp", "is null");
        $_filter["a.no"] = array("like", I("post.unMap_subject_type")."%");
        $_order = array("a.no");
        $list = D(CONTROLLER_NAME)->setDacFilter("a")->relation(true)->field("a.*")->where($_filter)->order($_order)->select();
        $parent_list = $this->getParents($list);
        $result = array_merge($list, $parent_list);
        foreach ($result as $key => $value) {
            $result[$key]["type_name"] = SUBJECT_TYPRS[$value["type_id"]];
        }
        $treelist = list_to_tree($result);
        $this->ajaxReturn($treelist);
    }

    public function getParents($list){
        $child_codes = [];
        $parents = [];
        //通过code的规则，可以获取每个科目的所有上级，如科目A的code=AAA_000_001,则存在code=AAA和code=AAA_000的两个上级
        foreach ($list as $key => $value) {
            if ($value["parent_code"]) {
                $parent_codes = explode("_", $value["parent_code"]);
                $code_str = "";
                foreach ($parent_codes as $parent_code) {
                    $code_str = mergeString($code_str , $parent_code, "_");
                    $parents[$code_str] = 1;
                }
            }
            $child_codes[] = $value["code"];
        }
        //去掉重复的code，否则数据会重复
        foreach ($child_codes as $code){
            if ($parents[$code]){
                unset($parents[$code]);
            }
        }
        if($parents){
            //根据code，获取上级科目
            $parent_list = M(CONTROLLER_NAME)->alias("a")
                ->join("left join vcr_subject parent on a.parent_id=parent.id")
                ->field("a.*,parent.name as parent_name,parent.code as parent_code")
                ->where(array("a.code"=>array("in", array_keys($parents))))->select();
        }else{
            $parent_list = [];
        }
        return $parent_list;
    }

    public function getGroupCountAction(){
        $_filter["branch_id"] = $this->_user_session->currBranchId;
        $list = M(CONTROLLER_NAME)->where($_filter)->field("std_subject_id")->select();
        $unMappingCount = 0;
        foreach ($list as $value){
            if (empty($value["std_subject_id"])){
                $unMappingCount++;
            }
        }
        $total = count($list);
        $this->responseJSON(array($total, $total - $unMappingCount, $unMappingCount));
    }


    public function updateSujbectAction($branch_id = null){
        if (empty($branch_id)){
            $branch_id = $this->_user_session->currBranchId;
        }
         D(CONTROLLER_NAME)->updateSujbectParent($branch_id);
    }

    public function importAction() {
        if (IS_GET) {
            $this->display();
        } else {
            $branch_id = $this->_user_session->currBranchId;
            $ent_type_id = M("SysBranch")->where("id=$branch_id")->getField("ent_type_id");
            if (empty($ent_type_id)){
                $this->responseJSON(buildMessage("请先设置企业类型！", 1));
            }
            set_time_limit(0);
            if (!empty($_FILES)) {
                $msg = "";
                $uploader = getUploader("temp/", array('xls', 'xlsx', 'csv'));
                $info = $uploader->uploadOne($_FILES["subject-file"]);
                if (!$info) {
                    $msg = buildMessage($uploader->getError(), 1);
                } else {
                    $reset_data = I("post.reset_data");
                    if ($reset_data){
                        M(CONTROLLER_NAME)->where("branch_id=$branch_id")->delete();
                    }
                    $filePath = ltrim($uploader->rootPath, ".") . $info['savepath'] . $info['savename'];
                    $type = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                    if ($type == 'xlsx' || $type == 'xls') {
                        $saveResult = D(CONTROLLER_NAME)->saveDataFromExcel($filePath, $branch_id);
                    } else {
                        $saveResult = D(CONTROLLER_NAME)->saveDataFromCsv($filePath, $branch_id);
                    }
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


    public function mapSubjectAction(){
        D(CONTROLLER_NAME)->mapSubject($this->_user_session->currBranchId);
    }

    public function getBankSubjectAction(){
        $this->responseJSON(getChilrenSubjects($this->_user_session->currBranchId, "银行存款"));        
    }


    public function getPayReceiveKeyNamesAction() {
        $result = D(CONTROLLER_NAME)->getPayReceiveKeyNames($this->_user_session->currBranchId);
        $this->responseJSON(buildResult($result));
    }

    //科目映射
    public function mappingAction($subject_id, $std_subject_id = null){
        if (IS_GET){
            if ($subject_id) {
                $model = M(CONTROLLER_NAME)->where("id=$subject_id")->find();
                $model["ent_type_id"] = getEnterpriseType($this->_user_session->currBranchId);;
                $this->subject = json_encode($model);
                $sys_subjects = M("VcrSysSubject a")
                    ->field("a.id,a.name,a.no,b.name as parent_name")
                    ->join("left join vcr_sys_subject b on a.parent_id=b.id")
                    ->where("a.ent_type_id=".$model["ent_type_id"])
                    ->order("a.no")
                    ->select();
                $this->sys_subjects = json_encode($sys_subjects);
            }
            $this->display();
        }else {
            $model = D(CONTROLLER_NAME);
            if (!$model->checkDataPermission($subject_id)) {
                $this->responseJSON(buildMessage("保存失败：您没有权限更新此记录！", 1));
            }
            $data["std_subject_id"] = $std_subject_id;
            if ($model->where(array("id" =>$subject_id))->save($data) !== false) {

                D(CONTROLLER_NAME)->handlerMappingChildren($subject_id,$std_subject_id);
                $this->responseJSON(buildMessage("匹配成功，继续匹配下一科目"));
            } else {
                $this->responseJSON(buildMessage($model->getError(), 1));
            }
        }
    }

    public function getMappingItemsAction($no, $direction){
        $condition["a.branch_id"] = $this->_user_session->currBranchId;
        $condition["a.std_subject_id"] = array("exp", "is null");
        $order = " asc";
        switch ($direction){
            case 0:
                $condition["a.no"] = $no;
                break;
            case 1:
                $condition["a.no"] = array("GT", $no);
                break;
            case -1:
                $condition["a.no"] = array("LT", $no);
                $order = "desc";
                break;
        }
        $item = M(CONTROLLER_NAME)->alias("a")
            ->join("left join vcr_subject b on a.parent_id=b.id")
            ->field("a.*,b.name as parent_name")
            ->where($condition)->order("a.no $order")->find();
        $result["subject"] = $item;
        if ($item){
            //获取模糊匹配标准科目
            $sys_subjects = D(CONTROLLER_NAME)->getLikeMappingItems($item,$this->_user_session->currBranchId);
            $result["sys_subject_list"] = $sys_subjects;
        }
        $this->responseJSON($result);
    }

    public function disMappingAction($id){
        $model = D(CONTROLLER_NAME);
        if (!$model->checkDataPermission($id)) {
            $this->responseJSON(buildMessage("保存失败：您没有权限更新此记录！", 1));
        }
        if ($model->where(array("id"=>$id))->setField("std_subject_id", array("exp", "null")) !== false){
            $this->responseJSON(buildMessage("取消匹配成功"));
        }else{
            $this->responseJSON(buildMessage($model->getError(), 1));
        }
    }

    //非导入的科目，is_new=1
    protected  function _before_write($type, &$data)
    {
        if (self::ACTION_ADD == $type){
            $data["is_new"] = 1;
        }
        if($data['parent_id']){
            $level = M(CONTROLLER_NAME)->where("id=".$data["parent_id"])->getField("level");
            $data['level'] = $level + 1;
        }else{
            $data['level'] = 1;
        }
        parent::_before_write($type, $data); // TODO: Change the autogenerated stub
    }

    protected function _before_add(&$data) {
        parent::_before_add($data);
        if ($data["parent_id"]){
            $data["no"] = D(CONTROLLER_NAME)->getMaxNo($this->_user_session->currBranchId, $data["parent_id"]);
            $data["parent_name"] = M(CONTROLLER_NAME)->where("id=".$data["parent_id"])->getField("name");
        }
        $data['direction'] = DIRECTION_DEBIT;//默认借贷方向为借
    }

    public function getMaxNoByParentAction($parent_id){
        if ($parent_id){
            exit(D(CONTROLLER_NAME)->getMaxNo($this->_user_session->currBranchId, $parent_id));
        }
        exit("");
    }

    //导出前检查
    public function exportSubjectAction(){
        $condition["branch_id"] = $this->_user_session->currBranchId;
        $type = I("get.type");
        //导出企业科目、标准科目
        if($type == $this->export_type_com){
            $file_name = $this->_user_session->currBranchName."科目.xlsx";//$this->_user_session->currBranchName."-".$accounting_section .".".$tmplateFile["ext"];
            $list = M("VcrSubject")->where($condition)->field("no,name,type_id")->order("no")->select();
        }else{
            $ent_type_id = getEnterpriseType($this->_user_session->currBranchId);
            /*if(!$ent_type_id){
                $this->ajaxReturn(array("error"=>1,"message"=>"请先进行参数设置！"));
            }*/
            $file_name = ENTERPRISE_TYPES[$ent_type_id]."标准科目.xlsx";
            $list = M("VcrSysSubject")->where("ent_type_id = $ent_type_id")->field("no,name,type_id")->order("no")->select();
        }
        vendor('PHPExcel18.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->setCellValue("A1", "科目编号");
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->setCellValue("B1",  "科目名称");
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->setCellValue("C1",  "科目类别");
        $startRow = 2;
        foreach ($list as $value){
            $objPHPExcel->getActiveSheet()->setCellValue("A".$startRow, $value["no"]);//日期
            $objPHPExcel->getActiveSheet()->setCellValue("B".$startRow,  $value["name"]); //凭证字
            $objPHPExcel->getActiveSheet()->setCellValue("C".$startRow,  SUBJECT_TYPRS[$value["type_id"]]); //凭证字
            $startRow++;
        }
        $userBrowser = $_SERVER['HTTP_USER_AGENT'];
        if (preg_match('/MSIE/i', $userBrowser)) {
            $file_name = urlencode($file_name);
        }
        $file_name = iconv('UTF-8', 'GBK//IGNORE', $file_name);
        $this->setExcelHeader($file_name);
        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $objWriter->save('php://output');
        unset($objWriter);
        unset($objPHPExcel);
    }

    //导入标准科目的企业类型
    public function getEnterpriceTypesAction(){
        $types = M("VcrSysSubject")->group("ent_type_id")->getField("ent_type_id",true);
        $result = [];
        foreach (array_keys(ENTERPRISE_TYPES) as $key){
            if (in_array_case($key, $types)) {
                $result[] = ["id" => $key, "name" => ENTERPRISE_TYPES[$key]];
            }
        }
        $this->ajaxReturn($result);
    }

    //全部企业类型
    public function getAllEnterpriceTypesAction(){
        foreach (array_keys(ENTERPRISE_TYPES) as $key){
            $result[] = ["id"=>$key, "name"=>ENTERPRISE_TYPES[$key]];
        }
        $this->ajaxReturn($result);
    }

    public function getSubjectTypesAction(){
        foreach (array_keys(SUBJECT_TYPRS) as $key){
            $result[] = ["id"=>$key, "name"=>SUBJECT_TYPRS[$key]];
        }
        $this->ajaxReturn($result);
    }

    protected  function _before_detail(&$data)
    {
        parent::_before_detail($data); // TODO: Change the autogenerated stub
        $data["type_name"] = SUBJECT_TYPRS[$data["type_id"]];
        if($data['direction'] == ""){
            $data['direction'] = DIRECTION_DEBIT;
        }
    }

    protected function _before_list(&$list) {
        foreach ($list as $key => $value) {
            $list[$key]["type_name"] = SUBJECT_TYPRS[$value["type_id"]];
            if($value['child_count']){
                $condition['a.parent_id'] = $value['id'];
                $condition['a.std_subject_id'] = array("exp","IS NULL");
                $children = D(CONTROLLER_NAME)->setDacFilter("a")->relation(true)->where($condition)->count();
                if($children){
                    unset($condition['a.std_subject_id']);
                    $children = D(CONTROLLER_NAME)->setDacFilter("a")->relation(true)->field("a.*")->where($condition)->select();
                    foreach($children as $c=>$child){
                        if($child['child_count']){
                            $children[$c]['state'] = "closed";
                        }
                    }
                    $list[$key]['children'] = $children;
                    $list[$key]['state'] = "open";
                }
            }
        }
    }

    public function searchSysSubjectAction($keyword){
        $condition1["a.ent_type_id"] = getEnterpriseType($this->_user_session->currBranchId);
        $orCondition["a.name"] = array("like", "%".$keyword."%");
        $orCondition["b.name"] = array("like", "%".$keyword."%");
        $orCondition["_logic"] = "OR";
        $condition1["_complex"] = $orCondition;
        $sys_subjects = M("VcrSysSubject a")
            ->join("left join vcr_sys_subject b on a.parent_id=b.id")
            ->field("a.*,b.name as parent_name")
            ->where($condition1)->select();
        $this->responseJSON($sys_subjects);
    }

    protected  function setAutocompleteExtentQuery(&$filter){
        $parent_id = I("get.parent_id", null);
        if (isset($parent_id)){
            $filter["a.parent_id"] = $parent_id;
        }
        $is_node = I("get.is_node");
        if ($is_node){
            $list = D(CONTROLLER_NAME)->setDacFilter("a")->field("a.id,a.name,a.parent_id")->cache(5)->select();
            $nodes = getTreeNodes($list);
            $filter["a.id"] = array("in", array_keys($nodes));
        }
        //系统自动显示出科目时，autocomplete只获取其二级科目，所以传入query，替换原有q 的条件
        if(I("get.id") && is_numeric( I("get.id") )){
            $id =  I("get.id");
            $where['a.id'] = $id;
            $where['a.parent_id'] = $id;
            $where['_logic'] = "or";
            $filter['_complex'] = $where;
            /*$filter['a.parent_id'] = $id;
            unset($filter['_complex']);*/
        }
    }

    public function getDataByTypeAction($type){
        $condition["no"] = array("like", $type."%");
        $list = D(CONTROLLER_NAME)->setDacFilter("a")->where($condition)->field("a.id,a.name as text,a.parent_id")->select();
        $this->responseJSON(list_to_tree($list));
    }

    public function _parseAutoCompleteOrder(&$_order){
        $_order[] = "a.no asc";
        parent::_parseAutoCompleteOrder($_order);
    }

    public function _before_autoCompleteList(&$list,&$result_fields){
        if(I("get.type") != 1 && I("get.q")){
            foreach ($list as $k=>$v){
                if($v['child_count']){
                    $condition['a.parent_id'] = $v['id'];
                    $condition['a.id'] = array("not in",array_column($list,"id"));
                    $children = D(CONTROLLER_NAME)->setDacFilter("a")->relation(true)->field($result_fields)->where($condition)->select();
                    $list = array_merge($list, $children);
                }
            }
        }
        parent::_before_autoCompleteList($list,$result_fields);
    }

    //获取科目五大类别未匹配的数量
    public function getSubjectTypeCountAction(){
        $result = [];
        $condition['_string'] = sprintf("no like '%d%%' or no like '%d%%' or no like '%d%%' or no like '%d%%' or no like '%d%%'",
            SUBJECT_CATEGORY_CAPITAL,SUBJECT_CATEGORY_DEPT,SUBJECT_CATEGORY_RIGHTS,SUBJECT_CATEGORY_COST,SUBJECT_CATEGORY_INCOME);
        $condition['branch_id'] = $this->_user_session->currBranchId;
        $condition['std_subject_id'] = array("exp","IS NULL");
        $count = M(CONTROLLER_NAME)->where($condition)->field("count(no) as num,substring(no,1,1) as no")->group("substring(no,1,1)")->order("no")->select();
        for ($i = 1;$i<6;$i++){
            $key = array_search($i,array_column($count,"no"));
            $result[$i] = $key !== false ? $count[$key]['num'] : 0;
        }
        $this->ajaxReturn($result);
    }

    //获取模糊匹配的标准科目
    public function getItemsForMappingAction(){
        $condition["a.branch_id"] = $this->_user_session->currBranchId;
        $condition["a.std_subject_id"] = array("exp", "is null");
        $condition["a.id"] = I("post.id");
        $item = M(CONTROLLER_NAME)->alias("a")
            ->join("left join vcr_subject b on a.parent_id=b.id")
            ->field("a.*,b.name as parent_name")
            ->where($condition)->find();
        $result["subject"] = $item;
        if ($item){
            //获取模糊匹配标准科目
            $sys_subjects = D(CONTROLLER_NAME)->getLikeMappingItems($item,$this->_user_session->currBranchId);
            $result["sys_subject_list"] = $sys_subjects;
        }
        $this->ajaxReturn($result);
    }

    //系统科目
    public function sysSubjectListAction(){
        $sys_subjects = [];
        $ent_type_id = getEnterpriseType($this->_user_session->currBranchId);
        if($ent_type_id){
            if(I("post.subject_id")){
                $condition["a.branch_id"] = $this->_user_session->currBranchId;
                $condition["a.id"] = I("post.subject_id");
                $item = M(CONTROLLER_NAME)->alias("a")
                    ->join("left join vcr_subject b on a.parent_id=b.id")
                    ->field("a.*,b.name as parent_name")
                    ->where($condition)->find();
                $sys_subjects = D(CONTROLLER_NAME)->getLikeMappingItems($item,$this->_user_session->currBranchId);
            }else{
                $keyword = I("post.sys_name");
                $filter['a.ent_type_id'] = $ent_type_id;
                $filter['a.name'] = array("like","%$keyword%");
                $type = I("post.sys_subject_type");
                if($type){
                    $filter['a.no'] = array("like","$type%");
                }
                $sys_subjects = M("VcrSysSubject a")
                    ->field("a.id,a.name,a.no,b.name as parent_name")
                    ->join("left join vcr_sys_subject b on a.parent_id=b.id")
                    ->where($filter)->order("a.no")
                    ->select();
            }
        }
        $this->ajaxReturn($sys_subjects);
    }
}
