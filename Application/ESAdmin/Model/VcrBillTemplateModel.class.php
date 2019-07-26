<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019-04-08
 * Time: 13:38
 */

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;
use Think\Exception;

class VcrBillTemplateModel extends DataModel{
    protected $_auto = array(
        array('update_at', 'time', 3, 'function'), // 对update_time字段在更新的时候写入当前时间戳
    );

    public function checkDataPermission($id, $branch_id = null){
        return   $this->where(['id' => ['in', $id], 'branch_id' => getBrowseBranchId()])->find();
    }
}