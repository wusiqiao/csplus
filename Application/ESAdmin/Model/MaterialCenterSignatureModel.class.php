<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019-04-08
 * Time: 13:38
 */

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class MaterialCenterSignatureModel extends DataModel
{
    /**
     * 是否启用  -- 关闭
     * */
    const IS_ENABLE_10 = 10;
    /**
     * 是否启用  -- 启用
     * */
    const IS_ENABLE_20 = 20;
    /**
     * 获取签名
     * */
    public function getSignature($branchId){
       return html_entity_decode($this->where([
            'branch_id' => $branchId,
            'is_enable' => self::IS_ENABLE_20
        ])->getField('content'));
    }

    public static function swicthSignature($branchId){
        $model = new self();
        $signature = $model->where(['branch_id' => $branchId])->find();
        if(empty($signature)){
            $model->add([
                'branch_id' => $branchId,
                'content' => '',
                'is_enable' => self::IS_ENABLE_20,
                'update_time' => time(),
            ]);

            return self::swicthSignature($branchId);
        }

        $signature['content'] = html_entity_decode($signature['content']);
        return $signature;
    }
}