<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\TreeController;
use Common\Lib\Controller\DataController;
class ComCategoryController extends TreeController {
    
    protected function _before_write($type, &$data) {
        parent::_before_write($type, $data);
//        if ($path = $this->uploadOne("icon_file","upload/",array('jpg', 'gif', 'png', 'jpeg'))){
//            $data["icon"] = $path;
//        }
        parent::_before_write($type, $data);
        if (empty($data["parent_id"]) && $data["is_hot"]){
            $this->ajaxReturn(buildMessage("只有小类才能在首页展示", 1));
        }

    }
   
    public function level2Action(){
        $where = array("a.parent_id"=> array("gt", 0));
        $cates = D('ComCategory')->field("a.id as value,a.name as text")->setDacFilter("a")->where($where)->select();
        $this->ajaxReturn($cates);
    }

    protected function _parseOrder(&$_order){
        $_order = array("a.sort");
    }

    /**
     * 排序窗口
     */
    public function showSortFormAction(){
        $this->display("sort_form");
    }

    //排序数据列表
    public function datalistAction(){
        $list = D(CONTROLLER_NAME)->setDacFilter("a")->relation(true)->field("a.*")->where("a.is_hot=1")->order("a.sort")->select();
        $result["total"] = count($list);
        $result["rows"] = $list;
        $this->responseJSON($result);
    }
}
