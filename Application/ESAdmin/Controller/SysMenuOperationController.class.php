<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class SysMenuOperationController extends DataController {

    public function detailAction($id = 0) {
        $this->display("edit");
    }

    public function updateAction() {
        if (IS_POST) {
            if (D(CONTROLLER_NAME)->updateRelation(I("post.data"))){
                $this->ajaxReturn(buildMessage("更新成功！"));
            }else{
                $this->ajaxReturn(buildMessage("更新失败！", 1));
            }
        }
    }

    public function listAction() {
        $menu_tree = D(CONTROLLER_NAME)->bulid_menu_operation_tree();
        $this->ajaxReturn($menu_tree);
    }

}
