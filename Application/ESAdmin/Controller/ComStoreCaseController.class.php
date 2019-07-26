<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/5
 * Time: 14:43
 */

namespace ESAdmin\Controller;

use Common\Lib\Controller\BillDetailController;
use Think\Controller;

class ComStoreCaseController extends BillDetailController
{   
    protected $_permission_name = "ComStore"; //功能权限同主功能,主表名称
   
    protected function _parseOrder(&$order) {
        $order = 'a.row_no';
    }
}