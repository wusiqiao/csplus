<?php

namespace Common\Lib\Controller;

use Common\Lib\Controller\DataController;

class BillDetailController extends DataController {

    public function _initialize() {
        if (!$this->_permission_name){
            $this->_permission_name = str_replace("Detail", "", CONTROLLER_NAME);
        }
        parent::_initialize();
    }

}
