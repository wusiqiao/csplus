<?php

namespace ESAdmin\Model;

use Common\Lib\Model\TreeDataModel;
use Org\Util\Strings;

class VcrSysSubjectModel extends TreeDataModel {

    protected $_link = array(
        "VcrSysSubject" => array(
            "join_name" => "LEFT",
            'class_name' => "VcrSysSubject",
            'foreign_key' => 'parent_id',
            'mapping_name' => 'parent',
            'mapping_fields' => 'name',
            "mapping_key" => "id"
        )
    );

    /**
     * @param $filePath
     * @param $ent_type_id 企业类型
     * @return int|mixed
     */
    public function saveDataFromExcel($filePath, $ent_type_id) {
        Vendor('PHPExcel18.PHPExcel');
        $filePath = realpath("./") . $filePath;
        $PHPReader = new \PHPExcel_Reader_Excel2007();        //建立reader对象
        if (!$PHPReader->canRead($filePath)) {
            $PHPReader = new \PHPExcel_Reader_Excel5();
            if (!$PHPReader->canRead($filePath)) {
                return buildMessage("文件读取错误！", 1);
            }
        }
        try {
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
            $subject_datas = $this->getEnterpriseSubjectByType($ent_type_id, "no");
            $dataList = array();
            for ($rowIndex = 2; $rowIndex <= $allRow; $rowIndex++) {        //循环读取每个单元格的内容。注意行从1开始，列从A开始
                $field_no = trim($currentSheet->getCell($subject_field_columns["subject_no_title"].$rowIndex)->getValue());
                if ($field_no) {
                    if (!$subject_datas[$field_no]) {
                        $data = array();
                        $data["no"] = trim($field_no);
                        $data["name"] = trim($currentSheet->getCell($subject_field_columns["subject_name_title"] . $rowIndex)->getValue());
                        if (isset($subject_field_columns["subject_type_title"])) {
                            $data["type_id"] = getSubjectTypeId(trim($currentSheet->getCell($subject_field_columns["subject_type_title"] . $rowIndex)->getValue()));
                        }
                        $data["querykey"] = firstPinyin($data["name"]);
                        $data["branch_id"] = 0;
                        $data["ent_type_id"] = $ent_type_id;
                        $direction = trim($currentSheet->getCell($subject_field_columns["subject_direction_title"] . $rowIndex)->getValue());
                        $data["direction"] = $direction == "贷" ? DIRECTION_CREDIT:DIRECTION_DEBIT;
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
                    $this->updateSujbectParent($ent_type_id);
                    return count($dataList);
                } else {
                    return buildMessage($this->getError(), 1);
                }
            } else {
                return buildMessage("资料暂无更新", 0);
            }
        } catch (Exception $e) {
            return buildMessage($e->getMessage(), 1);
        }
    }


    public function getEnterpriseSubjectByType($ent_type_id, $key_name = "id") {
        $result = array();
        $list = $this->field("id,no,name")->where("ent_type_id=$ent_type_id")->select();
        if (empty($list)){
            $list = $this->field("id,no,name")->where("ent_type_id=0")->select();
        }
        foreach ($list as $value) {
            $result[$value[$key_name]] = $value;
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

    public function updateSujbectParent($ent_type_id) {
        $list = $this->query("select id,parent_id,no,'' as parent_no,code,'' as parent_code from vcr_sys_subject where ent_type_id=$ent_type_id order by no");
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
        $sql = "INSERT INTO vcr_sys_subject(id,parent_id,code,child_count,level) values" . implode(",", $values) .
            " ON DUPLICATE KEY UPDATE parent_id=VALUES(parent_id),code=VALUES(code),child_count=VALUES(child_count),level=VALUES(level)";
        $this->execute($sql);
        $values = null;
        foreach ($children as $key => $value) {
            if ($value > 0){
                $values[] = sprintf("(%d,%d)",$key, $value);
            }
        }
        if ($values) {
            $sql = "INSERT INTO vcr_sys_subject(id,child_count) values" . implode(",", $values) .
                " ON DUPLICATE KEY UPDATE child_count=VALUES(child_count)";
            $this->execute($sql);
        }
    }

    public function getPayReceiveKeyNames($branch_id) {
        $condition["p.branch_id"] = $branch_id;
        $condition["p.name"] = array("in", array("应收账款","应付账款"));
        $result = M("VcrSysSubject p")->field("c.id,c.name")->join("vcr_sys_subject c on c.parent_id=p.id")->where($condition)->limit(0,500)->select();
        return $result;
    }

    public function getBankKeyNames($branch_id) {
        $condition["p.branch_id"] = $branch_id;
        $condition["p.name"] = "银行存款";
        $result = M("VcrSysSubject p")->field("c.id,c.name")->join("vcr_sys_subject c on c.parent_id=p.id")->where($condition)->select();
        return $result;
    }

    public function getChildSubjectAutoCompleteDatas($branch_id){
        $condition["a.branch_id"] = $branch_id;
        $result = D("VcrSysSubject a")->join("left join vcr_sys_subject p on p.id=a.parent_id ")
            ->field("a.id,concat(a.no,':',IFNULL(concat(p.name,'-'),''),a.name) as name,a.querykey")
            ->where($condition)->select();
        return $result;
    }

    public function getMaxNo($parent_id, $ent_type_id, $serinal_size = 2){
        $sql = "select id,parent_id,no from vcr_sys_subject where ent_type_id=$ent_type_id and (parent_id =$parent_id or id=$parent_id) order by no desc limit 2";
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
        $c["ent_type_id"] = $data["ent_type_id"];
        if (($data["id"])){
            $c["id"] = array("neq", $data["id"]);
        }
        //不能用$this，否则可能改变保存过程中的状态
        if (M($this->getModelName())->where($c)->count() > 0){
            E("科目编号已经存在");
        }
    }
}