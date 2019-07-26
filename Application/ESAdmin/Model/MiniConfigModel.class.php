<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019-04-08
 * Time: 13:38
 */

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class MiniConfigModel extends DataModel{
    const CACHE_KEY = 'MiniConfig_1';
    public function getConfig(){
        if (!$config = $this->where(['id' => 1])->find()){
            $data = [
                'id' => 1,
                'name' => '请输入小程序名称',
                'appid' => '请输入小程序appid',
                'secret' => '请输入小程序secret',
                'mch_id' => ' ',
                'key' => ' ',
                'update_time' => time()
            ];

            M('MiniConfig')->add($data);
            return $this->getConfig();
        }

        return $config;
    }

    public static function getMiniConfig(){
        $config = S(self::CACHE_KEY);
        if(empty($config)){
            $config = M('MiniConfig')->where(['id' => 1])->find();
            S(self::CACHE_KEY, $config);
        }

        return $config;
    }

    protected function _before_write(&$data){
        if(isset($data['id']) && $data['id'] == 1){
            S(self::CACHE_KEY, null);
        }
    }
}