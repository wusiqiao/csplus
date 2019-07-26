<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/13
 * Time: 16:37
 */

namespace ESAdmin\Controller;
use Common\Lib\Controller\DataController;

class ComTweetsController extends DataController
{
    //新增，更新后返回数据，一般返回全部，特殊需要处理的就是有大数据字段，没必要返回
    protected function _getLastData($id) {
        return D(CONTROLLER_NAME)->field("body",true)->where("id=$id")->find();
    }
}