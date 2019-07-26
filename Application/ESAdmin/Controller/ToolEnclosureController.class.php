<?php

namespace ESAdmin\Controller;
use Common\Lib\Controller\DataController;

class ToolEnclosureController extends DataController{

    protected function _parseOrder(&$order)
    {
        $order =  'a.sort desc,a.created_at desc';
        return $order;
    }
}
