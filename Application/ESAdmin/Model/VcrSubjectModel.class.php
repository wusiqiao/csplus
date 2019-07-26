<?php

namespace ESAdmin\Model;

use Common\Lib\Model\TreeDataModel;
use Org\Util\Strings;

class VcrSubjectModel extends TreeDataModel {

    protected $_link = array(
        "VcrSubject" => array(
            "join_name" => "LEFT",
            'class_name' => "VcrSubject",
            'foreign_key' => 'parent_id',
            'mapping_name' => 'parent',
            'mapping_fields' => 'id,name,code',
            "mapping_key" => "id"
        ),
        "VcrSysSubject" => array(
            "join_name" => "LEFT",
            'class_name' => "VcrSysSubject",
            'foreign_key' => 'std_subject_id',
            'mapping_name' => 'std_subject',
            'mapping_fields' => 'no,name',
            "mapping_key" => "id"
        ),
        "SysBranch" => array(
            "join_name" => "LEFT",
            'class_name' => "SysBranch",
            'foreign_key' => 'branch_id',
            'mapping_name' => 'branch',
            'mapping_fields' => 'name',
            "mapping_key" => "id"
        )
    );

//    protected $_validate = array(
//        array('no', '', '科目编号已经存在！', 0, 'unique', 3), // 编号字段是否唯一
//    );
    public function saveDataFromExcel($filePath, $branch_id) {
        Vendor('PHPExcel18.PHPExcel');
        $filePath = realpath("./") . $filePath;
        //$objPHPExcel = $objReader->load($inputFileName);
        try {
            $inputFileType = \PHPExcel_IOFactory::identify($filePath);
            $PHPReader = \PHPExcel_IOFactory::createReader($inputFileType);
            $PHPReader->setReadDataOnly(true);
            $PHPExcel = $PHPReader->load($filePath);        //建立excel对象
            $currentSheet = $PHPExcel->getSheet(0);        //**读取excel文件中的指定工作表*/
            $allRow = $currentSheet->getHighestRow();        //**取得一共有多少行*/
            $columns = array();
            for($col="A"; $col<"H";$col++){
                $columns[$col] = $currentSheet->getCell($col."1")->getValue();
            }
            $subject_field_columns = $this->getSubjectFieldColumns($columns); //获取科目字段的对应的列
            if (!isset($subject_field_columns["subject_no_title"]) || (!isset($subject_field_columns["subject_name_title"]))) {
                return buildMessage("文件格式错误！", 1);
            }
            $subject_datas = $this->getSubjectKVDatas($branch_id);
            $dataList = array();
            for ($rowIndex = 2; $rowIndex <= $allRow; $rowIndex++) {        //循环读取每个单元格的内容。注意行从1开始，列从A开始
                $field_no = trim($currentSheet->getCell($subject_field_columns["subject_no_title"].$rowIndex)->getValue());
                if ($field_no) {
                    if (!$subject_datas[$field_no]) {
                        $data = array();
                        $data["no"] = trim($field_no);
                        $data["name"] = trim($currentSheet->getCell($subject_field_columns["subject_name_title"] . $rowIndex)->getValue());
                        if (isset($subject_field_columns["subject_type_title"])) {
                            $data["type_id"] = getSubjectTypeId(trim($currentSheet->getCell($subject_field_columns["subject_type_title"] . $rowIndex)->getValue()), $branch_id);
                        }
                        $data["querykey"] = firstPinyin($data["name"]);
                        $data["branch_id"] = $branch_id;
                        //$direction = trim($currentSheet->getCell($subject_field_columns["subject_direction_title"] . $rowIndex)->getValue());
                        //$data["direction"] = $direction == "贷" ? DIRECTION_CREDIT:DIRECTION_DEBIT;
                        $dataList[] = $data;
                    }
                }
            }
            unset($subject_datas);
            unset($currentSheet);
            unset($PHPExcel);
            unset($PHPReader);
            if ($dataList) {
                if ($this->addAll($dataList)) {
                    $this->updateSujbectParent($branch_id);
                    $this->mapSubject($branch_id); //标准科目映射
                    return count($dataList);
                } else {
                    return buildMessage($this->getError(), 1);
                }
            } else {
                return buildMessage("资料暂无更新", 1);
            }
        } catch (Exception $e) {
            return buildMessage($e->getMessage(), 1);
        }
    }

    function saveDataFromCsv($filePath, $branch_id) {
        Vendor("Utils.excel");
        $filePath = realpath("./") . $filePath;
        $source_datas = read_csv_lines($filePath, 5);
        $subject_datas = $this->getSubjectKVDatas($branch_id);
        $subject_field_columns = $this->getSubjectFieldColumns($source_datas[0]); //获取科目字段的对应的列
        if (!isset($subject_field_columns["subject_no_title"]) || (!isset($subject_field_columns["subject_name_title"]))) {
            return buildMessage("文件格式错误！", 1);
        }
        $dataList = array();
        for ($rowIndex = 1; $rowIndex <= count($source_datas); $rowIndex++) {        //循环读取每个单元格的内容。注意行从1开始，列从A开始
            $field_no = trim($source_datas[$rowIndex][$subject_field_columns["subject_no_title"]]);
            if ($field_no) {
                if (!$subject_datas[$field_no]) {
                    $data = array();
                    $data["no"] = trim($field_no);
                    $data["name"] = trim($source_datas[$rowIndex][$subject_field_columns["subject_name_title"]]);
                    if (isset($subject_field_columns["subject_type_title"])) {
                       //$data["type_id"] = $this->getType($source_datas[$rowIndex][$subject_field_columns["subject_type_title"]], $branch_id);
                       $data["type_id"] = getSubjectTypeId($source_datas[$rowIndex][$subject_field_columns["subject_type_title"]], $branch_id);
                    }
                    $data["querykey"] = firstPinyin($data["name"]);
                    $data["branch_id"] = $branch_id;
                    $direction = trim($source_datas[$rowIndex][$subject_field_columns["subject_direction_title"]]);
                    $data["direction"] = $direction == "贷" ? DIRECTION_CREDIT:DIRECTION_DEBIT;
                    $dataList[] = $data;
                }
            }
        }
        unset($subject_datas);
        if ($dataList) {
            if ($this->addAll($dataList)) {
                $this->updateSujbectParent($branch_id);
                return count($dataList);
            } else {
                return buildMessage($this->getError(), 1);
            }
        } else {
            return buildMessage("资料暂无更新", 1);
        }
    }

    function getSubjectKVDatas($branch_id) {
        $result = array();
        $list = $this->field("id,no")->where("branch_id=$branch_id")->select();
        foreach ($list as $value) {
            $result[$value["no"]] = $value["id"];
        }
        return $result;
    }


    private function getSubjectFieldColumns($source_datas){
        $subject_field_columns = array();
        $subject_no_titles = SUBJECT_IMPORT_COLUMNS["subject_no_title"];
        $subject_name_titles = SUBJECT_IMPORT_COLUMNS["subject_name_title"];
        $subject_type_titles = SUBJECT_IMPORT_COLUMNS["subject_type_title"];
        $subject_direction_titles = SUBJECT_IMPORT_COLUMNS["subject_direction_title"];
        foreach($source_datas as $key=>$value){
            if (mb_stripos($subject_no_titles, $value) !== false){
                $subject_field_columns["subject_no_title"] = $key;
            }
            if (mb_stripos($subject_name_titles, $value) !== false){
                $subject_field_columns["subject_name_title"] = $key;
            }
            if (mb_stripos($subject_type_titles, $value) !== false){
                $subject_field_columns["subject_type_title"] = $key;
            }
            if (mb_stripos($subject_direction_titles, $value) !== false){
                $subject_field_columns["subject_direction_title"] = $key;
            }
        }
        return $subject_field_columns;
    }

    public function updateSujbectParent($branch_id) {
        $list = $this->query("select id,parent_id,no,'' as parent_no,code,'' as parent_code from vcr_subject where branch_id=$branch_id order by no");
        $pre_subject = null;
        $children = array();
        $values = array();
        $found = false;
        foreach ($list as $key => $value) {
            $found = (stripos($value["no"], $pre_subject["no"]) === 0);
            if ($found) {//判断上个节点，如果上个节点是父节点
                $list[$key]["parent"] = $pre_subject;
                $list[$key]["code"] = $pre_subject["code"] . "_" . Strings::randString(8);
                $list[$key]["level"] = intval($pre_subject["level"]) + 1;
                $values[] = sprintf("(%d,%d,'%s',0,%d)", $value["id"], $pre_subject["id"], $list[$key]["code"],$list[$key]["level"]);
                $children[$pre_subject["id"]] = intval($children[$pre_subject["id"]]) + 1;
            } else {
                if ($pre_subject["parent"]) {
                    $parent_subject = $pre_subject["parent"];
                    while ($parent_subject) {
                        $found = (stripos($value["no"], $parent_subject["no"]) === 0);
                        if ($found) { //是兄弟节点，设置节点的父节点
                            $list[$key]["parent"] = $parent_subject;
                            $list[$key]["code"] = $parent_subject["code"] . "_" . Strings::randString(8);
                            $list[$key]["level"] = intval($parent_subject["level"]) + 1;
                            $values[] = sprintf("(%d,%d,'%s',0,%d)", $value["id"], $parent_subject["id"], $list[$key]["code"],$list[$key]["level"]);
                            $children[$parent_subject["id"]] = intval($children[$parent_subject["id"]]) + 1;
                            break;
                        }
                        $parent_subject = $parent_subject["parent"];
                    }
                }
            }
            if (!$found) {
                $list[$key]["parent_id"] = 0;
                $list[$key]["code"] = Strings::randString(8);
                $list[$key]["level"] = 1;
                $values[] = sprintf("(%d,0,'%s',0,1)", $value["id"], $list[$key]["code"]);
                $children[$value["id"]] = 0;
            }
            $pre_subject = $list[$key];
        }
        $sql = "INSERT INTO vcr_subject(id,parent_id,code,child_count,level) values" . implode(",", $values) .
              " ON DUPLICATE KEY UPDATE parent_id=VALUES(parent_id),code=VALUES(code),child_count=VALUES(child_count),level=VALUES(level)";
        $this->execute($sql);
        $values = null;
        foreach ($children as $key => $value) {
            if ($value > 0){
                $values[] = sprintf("(%d,%d)",$key, $value);
            }            
        }
        $sql = "INSERT INTO vcr_subject(id,child_count) values" . implode(",", $values) .
               " ON DUPLICATE KEY UPDATE child_count=VALUES(child_count)";
        $this->execute($sql);

    }

    /**标准科目自动匹配
     * @param $branch_id
     * @parma $reset  是否全部重置映射
     */
    public function mapSubject($branch_id, $reset = true){
        if($_SESSION[USER_SESSION_KEY]->currBranchId != $branch_id){
            return false;
        }
        $sysSubjectDatas = array();
        $ent_type_id = M("SysBranch")->where("id=$branch_id")->getField("ent_type_id");
        if (M("VcrSysSubject")->where("ent_type_id=$ent_type_id")->count() == 0){ //查找是否存在该类型,没有就用通用科目
            $ent_type_id = 0;
        }
        $sys_list = M("VcrSysSubject a")->join("left join vcr_sys_subject p on p.id=a.parent_id")
            ->field("a.id,a.no,a.name,IFNULL(p.name,'') as parent_name")->where("a.ent_type_id=$ent_type_id")->select();
        foreach ($sys_list as $key=>$value) {
            $name_key = md5(trim($value["parent_name"]).".".trim($value["name"]));
            $sysSubjectDatas[$name_key] = &$sys_list[$key];
        }
        $update_values = array();
        $list = M("VcrSubject a")->join("left join vcr_subject p on p.id=a.parent_id")
            ->field("a.id,a.no,a.name,IFNULL(p.name,'') as parent_name,a.std_subject_id")->where("a.branch_id=$branch_id")->select();
        $mapping_children = [];
        foreach ($list as $key=>$value) {
            if ($reset || empty($value["std_subject_id"])) { //如果是重置
                $name_key = md5(trim($value["parent_name"]) . "." . trim($value["name"]));
                if ($vcr_sys_subject = $sysSubjectDatas[$name_key]) {
                    $update_values[] = sprintf("(%d,%d)", $value["id"], $vcr_sys_subject["id"]);
                }
                //如果父级是银行存款或包含应付应收，则需要根据父级匹配的科目自动匹配下级科目
                if($value['parent_name'] == "银行存款" || $value['parent_name'] == "实收资本" || str_exists($value['parent_name'],"应收") || str_exists($value['parent_name'],"应付")) {
                    $mapping_children[] = $value['id'];
                }
            }
        }
        $sql = "INSERT INTO vcr_subject(id,std_subject_id) values" . implode(",", $update_values) .
            " ON DUPLICATE KEY UPDATE std_subject_id=VALUES(std_subject_id)";
        $this->execute($sql);
        $this->handlerAutoMappingChildren($mapping_children,"");
    }

    public function getPayReceiveKeyNames($branch_id) {
        $condition["p.branch_id"] = $branch_id;
        $condition["p.name"] = array("in", array("应收账款","应付账款"));
        $result = M("VcrSubject p")->field("c.id,c.name")->join("vcr_subject c on c.parent_id=p.id")->where($condition)->limit(0,500)->select();
        return $result;
    }

    public function getBankKeyNames($branch_id) {
        $condition["p.branch_id"] = $branch_id;
        $condition["p.name"] = "银行存款";
        $result = M("VcrSubject p")->field("c.id,c.name")->join("vcr_subject c on c.parent_id=p.id")->where($condition)->select();
        return $result;
    }

    public function getChildSubjectAutoCompleteDatas($branch_id){
        $condition["a.branch_id"] = $branch_id;
        $result = D("VcrSubject a")->join("left join vcr_subject p on p.id=a.parent_id ")
            ->field("a.id,concat(a.no,':',IFNULL(concat(p.name,'-'),''),a.name) as name,a.querykey")
            ->where($condition)->select();
        return $result;
    }


    private function getHasMapSubjectData($branch_id){
        $result = array();
        $sql = "select std_subject_id from vcr_subject where branch_id=$branch_id and std_subject_id>0 order by no";
        $user_subjects = $this->query($sql);
        foreach ($user_subjects as $value){
            $result[$value["std_subject_id"]] = 1;
        }
        return $result;
    }

    public function getUnMapSubjectData($branch_id, $ent_type_id){
        $count = M("VcrSysSubject a")->where(array("a.ent_type_id"=>$ent_type_id, "a.need_map"=>1))->count();
        if ($count == 0){
            $ent_type_id = 0;
        }
        $sql = "select distinct a.id,a.no,concat(a.no,'-',a.name) as text,'open' as state,a.parent_id 
               from vcr_sys_subject a 
               inner join vcr_sys_subject p on p.id=a.parent_id
               left join vcr_subject b on a.id=b.std_subject_id
               where a.ent_type_id=$ent_type_id and a.need_map=1 and ISNULL(b.std_subject_id)
               order by a.no";
        $std_subjects = $this->query($sql);
        foreach ($std_subjects as $key=>$val){
            $parent_ids[$val["parent_id"]] = 1;
        }
        if ($parent_ids) {
            $sql = "select distinct a.id,a.no,concat(a.no,'-',a.name) as text,'open' as state,a.parent_id
                from vcr_sys_subject a  where a.id in (" . implode(",", array_keys($parent_ids)) . ")";
            $parent_subjects = $this->query($sql);
            $std_subjects = array_merge($std_subjects, $parent_subjects);
        }
        $hasMapDataset = $this->getHasMapSubjectData($branch_id);
        foreach ($std_subjects as $key=>$val){
           if ($hasMapDataset[$val["id"]]){
               $std_subjects[$key]["text"] = $std_subjects[$key]["text"]."【已映射】";
           }
        }
        $result  = list_to_tree($std_subjects, 0);
        return $result;
    }

    public function search($branch_id, $text){
        $map["branch_id"] = $branch_id;
        $sql = "select id,no,concat(no,'-',name) as text,'open' as state,parent_id,std_subject_id from vcr_subject 
                where branch_id=$branch_id and (no like '%$text%' or name like '%$text%') order by no";
        $user_subjects = $this->query($sql);
        if ($user_subjects){
            foreach ($user_subjects as $key=>$val){
                $parent_ids[$val["id"]] = 1;
            }
            if ($parent_ids) {
                $sql = "select a.id,no,concat(a.no,'-',a.name) as text,'open' as state,a.parent_id,std_subject_id
                from vcr_subject a where ISNULL(a.std_subject_id) and a.parent_id in (" . implode(",", array_keys($parent_ids)) . ")";
                $child_subjects = $this->query($sql);
                $user_subjects = array_merge($user_subjects, $child_subjects);
            }
        }
        $result = array();
        $hasMapDataset = $this->getHasMapSubjectData($branch_id);
        foreach ($user_subjects as $key=>$val){
            if ($hasMapDataset[$val["std_subject_id"]]){
                $user_subjects[$key]["text"] = $user_subjects[$key]["text"]."【已映射】";
            }
            $result[$val["id"]] = &$user_subjects[$key];
        }
        return list_to_tree(array_values($result), 0);
    }

    public function getMaxNo($branch_id, $parent_id, $serinal_size = 2){
        $sql = "select id,parent_id,no from vcr_subject where branch_id=$branch_id and (parent_id =$parent_id or id=$parent_id) order by no desc limit 2";
        $list = $this->query($sql);
        if ($list){
            $data = $list[0];
            //没有子节点
            if ($data["id"] == $parent_id){
                return $data["no"]. str_pad("1", $serinal_size, "0", STR_PAD_LEFT);
            }else{
                //编号规则可能有分隔符，也可能没有，分隔符无法预知（可能是“-”，“，”，“.”，“_”）
                $field_no_size = strlen($data["no"]);
                if (preg_match("/(\d+)$/is", $data["no"], $match)){
                    $last_val = $match[1];
                    $last_val_size = strlen($last_val);
                    $last_val = str_pad(intval($last_val) + 1, $last_val_size, "0", STR_PAD_LEFT);
                    return substr($data["no"], 0, $field_no_size - $last_val_size). $last_val;
                }else{
                    $last_chr = substr($data["no"], -1, 1); //最后一位
                    return substr($data["no"], 0, $field_no_size - 1).chr(ord($last_chr)+1);
                }
            }
        }
        return "";
    }

    protected function _before_write(&$data) {
        $c["no"] = $data["no"];
        if (($data["id"])){
            if ($data["id"] == $data["parent_id"]){
                E("上级科目不能设置为当前科目");
            }
            $c["id"] = array("neq", $data["id"]);
        }
        $user_session = session(USER_SESSION_KEY);
        $c["branch_id"] = $user_session->currBranchId;
        //不能用$this，否则可能改变保存过程中的状态
        if (M($this->getModelName())->where($c)->count() > 0){
            E("科目编号已经存在");
        }
    }

    //导入科目时自动匹配，如果匹配的是银行存款、应收（%）、应付（%）的话，下级未匹配科目自动匹配为当前匹配的标准科目
    public function handlerAutoMappingChildren($subject_id){
        foreach($subject_id as $v){
            //查找当前科目的父级科目、父级匹配科目，父级匹配科目名称
            $subject = M("VcrSubject a")
                ->join("left join vcr_subject b on a.parent_id = b.id")
                ->join("left join vcr_sys_subject c on b.std_subject_id = c.id")
                ->where("a.id = ".$v)->field("a.id,b.id as parent_id,b.std_subject_id,c.name as std_subject_name")->find();
            if($subject['std_subject_name'] == "银行存款" ||$subject['std_subject_name'] == "实收资本" || str_exists($subject['std_subject_name'],"应收") || str_exists($subject['std_subject_name'],"应付")) {
                //$condition['std_subject_id'] = array("exp", "IS NULL");
                $condition['id'] = $subject['id'];
                M("VcrSubject")->where($condition)->setField("std_subject_id", $subject['std_subject_id']);
            }
        }
    }

    //手动匹配时，如果匹配的是银行存款、应收（%）、应付（%）的话，下级未匹配科目自动匹配为当前匹配的标准科目
    public function handlerMappingChildren($subject_id,$std_subject_id){
        $std_subject_name = M("VcrSysSubject")->where("id = $std_subject_id")->getField("name");
        if($std_subject_name == "银行存款" || str_exists($std_subject_name,"应收") || str_exists($std_subject_name,"应付")) {
            $condition['parent_id'] = $subject_id;
            $condition['std_subject_id'] = array("exp", "IS NULL");
            M("VcrSubject")->where($condition)->setField("std_subject_id", $std_subject_id);
        }
    }

    public function getLikeMappingItems($item,$branch_id){
        $sys_subjects = [];
        $condition1["a.ent_type_id"] = getEnterpriseType($branch_id);
        $condition1["a.level"] = $item["level"];
        $keyword = I("post.sys_name");//关键字搜索
        $condition1["a.name"] = array("like","%$keyword%");
        $type = I("post.sys_subject_type");//科目类别
        if($type){
            $condition1['a.no'] = array("like","$type%");
        }
        //如果未查询到系统科目，则左边去除一个字重复查询
        for($i = 0;$i<mb_strlen($item["name"]) - 1;$i++){
            $name = mb_substr($item["name"],$i,mb_strlen($item["name"])-$i);
            $querykey = mb_substr($item["querykey"],$i,mb_strlen($item["querykey"])-$i);
            $orCondition["a.querykey"] = $querykey;
            $orCondition["a.name"] = array("like", "%".$name."%");
            $orCondition["_logic"] = "OR";
            $condition1["_complex"] = $orCondition;
            $sys_subjects = M("VcrSysSubject a")
                ->join("left join vcr_sys_subject b on a.parent_id=b.id")
                ->field("a.*,b.name as parent_name")
                ->where($condition1)->select();
            if($sys_subjects){
                break;
            }
        }
        //如果没有相关联的系统科目，则显示上级科目所匹配的系统科目
        if(!$sys_subjects && $item['parent_id']){
            $where['a.id'] = $item['parent_id'];
            $where['a.name'] = array("like","%$keyword%");
            $where['a.no'] = array("like","$type%");
            $sys_subjects = M("VcrSubject a")
                ->join("vcr_sys_subject b on a.std_subject_id = b.id")
                ->join("left join vcr_sys_subject c on b.parent_id = c.id")
                ->where($where)->field("b.*,c.name as parent_name")->select();
        }
        return $sys_subjects;
    }

}
