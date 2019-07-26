<?php

namespace Common\Lib\Controller;


class ComplexDataController extends DataController {

    public function assignPermissions($controller = CONTROLLER_NAME) {
        parent::assignPermissions($controller);
        if ("detail" == ACTION_NAME){ //触发查看
            $id = I("get.id");
            if ($id) {
                $pv = D(CONTROLLER_NAME)->getPermitValue($id);
                if ($pv < DAC_PERMIT_VALUE_COLLABORATOR){ //可见或通知人，没有update权限
                    $this->permissions["update"] = 0;
                }
                $this->instance_permit = $pv; //返回权限值
            }
        }
    }

    public function listAction() {
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $_order = array();
        $this->_parseOrder($_order);
        $_filter = array();
        $this->_parseFilter($_filter);
        //$count = D(CONTROLLER_NAME)->setDacFilter("a")->where($_filter)->count();
        if ($this->hasRelationCondition($_filter)) { //条件中是否有关联表的查询字段，关联字段查询格式为 q-b*xxx(q:查询模式，b管理部的别名,xxx关联表字段
            $count = D(CONTROLLER_NAME)->setDacFilter("a")->relation(true)->where($_filter)->count();
        }else{
            $count = D(CONTROLLER_NAME)->setDacFilter("a")->where($_filter)->count();
        }
        $list = D(CONTROLLER_NAME)->setDacFilter("a")->relation(true)->field("DISTINCT a.*")->where($_filter)->page($page_index, $page_size)->order($_order)->select();
        $this->_before_list($list);
        $result["total"] = $count;
        $result["rows"] = $list;
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($result));
    }

    /**删除前检查
     * @param $id_list 删除的id列表
     */
    protected function _before_delete($id_list) {
        foreach ($id_list as $item){
            $pv = D(CONTROLLER_NAME)->getPermitValue($item);
            if ($pv != DAC_PERMIT_VALUE_LEADER){ //负责人才可以删除
                $this->responseJSON(buildMessage("您不是此记录的负责人，没有权限删除！", 1));
                break;
            }
        }
    }
}
