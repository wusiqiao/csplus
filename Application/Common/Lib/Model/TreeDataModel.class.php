<?php

namespace Common\Lib\Model;

use Common\Lib\Model\DataModel;
use Org\Util\Strings;

class TreeDataModel extends DataModel {

    private $_dataBeforeUpdate; //更新前数据
    private $_deleteList;

    public function __construct($name = '', $tablePrefix = '', $connection = '') {
        parent::__construct($name, $tablePrefix, $connection);
        $this->_link[$this->name] = $this->getRelationConfig($this->getTableName());
    }

    private function getRelationConfig($table_name) {
        return
                array(
                    "join_name" => "LEFT",
                    'class_name' => $table_name,
                    'foreign_key' => 'parent_id',
                    'mapping_name' => 'parent',
                    'mapping_fields' => 'id,name,code',
                    'mapping_key' => 'id'
        );
    }

    protected function _before_insert(&$data, $options) {
        $parentId = $data["parent_id"];
        $code = empty($data["code"]) ? Strings::randString(8) : substr($data["code"], 0, 8);
        if (empty($parentId)) {
            $data["code"] = $code;
        } else {
            //$parent_code = $this->callFilter(false)->getFieldById($parentId, "code");
            $parent_code = M($this->trueTableName)->getFieldById($parentId, "code");
            $data["code"] = sprintf("%s_%s", $parent_code, $code);
        }
        $data["child_count"] = 0;
        return true;
    }

    protected function _after_insert($data, $options) {
        if ($data["parent_id"]) {
            $sql = sprintf("update %s set child_count=child_count+1 where id=%d;", $this->trueTableName, $data["parent_id"]);
            $this->execute($sql);
            //$this->where(array("id" => $data["parent_id"]))->callFilter(false)->setInc("child_count");
        }
    }

    protected function _before_update(&$data, $options) {
        $this->_dataBeforeUpdate = $this->callFilter(false)->field("code,parent_id")->getById(I("post.id"));
        return true;
    }

    protected function _after_update($data, $options) {
        $parentId_old = $this->_dataBeforeUpdate["parent_id"];
        $code_old = $this->_dataBeforeUpdate["code"];
        $parentId = $data["parent_id"];
        if ($parentId_old != $parentId) {
            $id = I("post.id");
            if (empty($parentId_old) && !empty($parentId)) {
                $parent_code = $this->callFilter(false)->getFieldById($parentId, "code"); //新parent_id对应的code
                $sql = sprintf("update %s set child_count=child_count+1 where id=%d;", $this->trueTableName, $parentId);
                $sql.= sprintf("update %s set code=concat('%s_', code) where id=%d or code like '%s_%%'", $this->trueTableName, $parent_code, $id, $code_old);
                $this->execute($sql);
            }
            if (!empty($parentId_old)) {
                if (!empty($parentId)) {
                    $parent_code = $this->callFilter(false)->getFieldById($parentId, "code");
                    $new_code = sprintf("%s_%s", $parent_code, Strings::randString(4));
                    $sql = sprintf("update %s set child_count=child_count+1 where id=%d;", $this->trueTableName, $parentId);
                } else {
                    $new_code = Strings::randString(8);
                    $sql = "";
                }
                $sql.= sprintf("update %s set child_count=child_count-1 where id=%d;", $this->trueTableName, $parentId_old);
                $sql.= sprintf("update %s set code='%s' where id=%d;", $this->trueTableName, $new_code, $id);
                $sql.= sprintf("update %s set code=replace(code, '%s_', '%s_') where code like '%s_%%'", $this->trueTableName, $code_old, $new_code, $code_old);
                $this->execute($sql);
            }
        }
    }

    protected function _before_delete($options) {
        $this->_deleteList = $this->callFilter(false)->field("parent_id,count(parent_id) as count")->where($options["where"])->group("parent_id")->select();
    }

    protected function _after_delete($data, $options) {
        if ($this->_deleteList) {
            foreach ($this->_deleteList as $value) {
                $sql = sprintf("update %s set child_count=child_count-%d where id=%d;", $this->trueTableName, $value["count"], $value["parent_id"]);
            }
            $this->execute($sql);
        }
    }

}
