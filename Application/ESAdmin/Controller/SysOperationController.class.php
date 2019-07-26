<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class SysOperationController extends DataController {

    protected function _before_getKeyNameList(&$condition) {
        $menu_id = I("get.menu_id");
        if (empty($menu_id)){
            if ($condition["_string"]){
                $condition["_string"].= " and (ISNULL(a.menu_id) or (a.menu_id = 0))";
            }else{
                $condition["_string"] = " (ISNULL(a.menu_id) or (a.menu_id = 0))";
            }
        }else{
            if ($condition["_string"]){
                $condition["_string"].= " and (ISNULL(a.menu_id) or (a.menu_id = 0) or (a.menu_id=$menu_id))";
            }else{
                $condition["_string"] = " (ISNULL(a.menu_id) or (a.menu_id = 0) or (a.menu_id=$menu_id))";
            }
        }
    }

}
