<?php

namespace Common\Lib\Model;

use Common\Lib\Model\DataModel;

class BillModel extends DataModel {

    protected $_auto = array(
        array("bill_date", "strtotime", 3, "function")
    );
    protected $_validate = array(
        array('bill_no', '', '{%VERIFY_BILL_EXISTS}', 0, 'unique', 1)
    );
    protected $_children = array("Detail");
    protected $_children_parent_id_key = "parent_id";

    private $_update_state = self::MODEL_INSERT;
    /**
     * 新增存在两种情况，复制状态和新增状态
     * @param type $data
     * @param type $options
     */
    protected function _after_insert($data, $options) {
        $this->_update_state = self::MODEL_INSERT;
        foreach ($this->_children as $child) {
            if ($_POST[$child]) {
                $child_data = json_decode($_POST[$child], true);
                if ($child_data && $child_data["all"]) {
                    $this->setChildParentId($child_data["all"], $data["id"]);
                    $this->insertChild($data, $child_data["all"], $child);
                }
            }
        }
    }

    protected function getChildData($type, $child = "Detail") {
        if ($_POST[$child]) {
            $child_data = json_decode($_POST[$child], true);
            if ($child_data && $child_data[$type]) {
                return $child_data[$type];
            }
        }
        return null;
    }

    private function setChildParentId(&$insert_data, $parent_id) {
        foreach ($insert_data as $key => $value) {
            $insert_data[$key][$this->_children_parent_id_key] = $parent_id;
        }
    }

    protected function _after_update($data, $options) {
        $this->_update_state = self::MODEL_UPDATE;        
        foreach ($this->_children as $child) {
            if ($_POST[$child]) {
                $child_data = json_decode($_POST[$child], true);
                if ($child_data) {
                    foreach ($child_data as $key => $value) {
                        if ($value) {
                            switch ($key) {
                                case "deleted":
                                    $this->deleteChild($value, $child);
                                    break;
                                case "inserted":
                                    $this->setChildParentId($value, $data["id"]);
                                    $this->insertChild($data, $value, $child);
                                    break;
                                case "updated":
                                    $this->updateChild($data, $value, $child);
                                    break;
                                default:
                                    break;
                            }
                        }
                    }
                }
            }
        }
    }

    private function getChildCreateData($list, $child_name = "Detail") {
        $datas = array();
        $child_model = D($this->name . $child_name);
        foreach ($list as $value) {  //做数据验证和自动完成已经去掉非表字段的参数
            $datas[] = $child_model->create($value);
        }
        return $datas;
    }

    protected function insertChild($master_data, $child_datalist, $child_name = "Detail") {
        $child_createDatas = $this->getChildCreateData($child_datalist, $child_name);
        $child_model = D($this->name . $child_name);
        //新增的ID必须清空，否则addAll会出错
        foreach ($child_createDatas as $key => $value) {
            unset($child_createDatas[$key]["id"]);
            $this->_before_write_child($master_data, $child_createDatas[$key], $child_name);
        }
       
        $child_model->addAll($child_createDatas);
        foreach ($child_createDatas as $value) {
            $child_model->_after_insert($value, null);
        }
    }

    /**
     * 保存前(新增或修改）开放设置子Data的值
     * @param type $master_data 主表data
     * @param type $child_data 子表data
     * @param type $child_name  对应的子名称
     * @param type $type  状态，新增或修改
     */
    protected function _before_write_child($master_data, &$child_data, $child_name){        
    }
    
    protected function updateChild($master_data, $child_datalist, $child_name = "Detail") {
        $child_createDatas = $this->getChildCreateData($child_datalist, $child_name);
        $child_model = D($this->name . $child_name);
        foreach ($child_createDatas as $key => $value) {         
            $this->_before_write_child($master_data, $child_createDatas[$key], $child_name);
        }
        $child_model->addAll($child_createDatas, null, true);
        foreach ($child_createDatas as $value) {
            $child_model->_after_update($value, null);
        }
    }

    protected function deleteChild($list, $child = "Detail") {
        $id_array = array();
        foreach ($list as $value) {
            $id_array[] = $value["id"];
        }
        $condition["id"] = array("in", $id_array);
        $child_model = D($this->name . $child);
        $child_model->where($condition)->delete();
    }

    protected function _after_delete($data, $options) {
        foreach ($this->_children as $child) {
            $child_model = D($this->name . $child);
            $child_model->where(array($this->_children_parent_id_key => $data["id"]))->delete();
        }
    }   
    
    public function getUpdateState(){
        return $this->_update_state;
    }

}
