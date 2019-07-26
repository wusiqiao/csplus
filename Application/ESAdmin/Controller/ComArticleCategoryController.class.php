<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class  ComArticleCategoryController extends DataController {
    protected function _parseOrder(&$order) {
        $order = "sort";
    }
}