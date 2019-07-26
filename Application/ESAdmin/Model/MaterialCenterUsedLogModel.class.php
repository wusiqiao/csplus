<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019-04-08
 * Time: 13:38
 */

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

/**
 * 素材使用记录
 * */
class MaterialCenterUsedLogModel extends DataModel
{
    /**
     * 获取可以使用的次数
     * @param  int $version 版本 10免费   20 付费
     * @param int $source  来源  10 抓取  20 后台添加
     * */
    public function isAllow($version, $source = 20, $branchId, $ids){
        $source = intval($source);
        $branchId = intval($branchId);
        $config = MaterialCenterConfigModel::getByAvailableCount($version, $source);
        if(true === $config || $config === 0){
           return true === $config;
        }

        $usedTime = time() - 24 * 60 * 60 * $config['cycle'];
        $usedCount = $this->where(['branch_id' => $branchId, 'source' => $source, 'used_time' => ['EGT', $usedTime]])->count();

        return ($config['count'] - $usedCount) >= count($ids);
    }
}