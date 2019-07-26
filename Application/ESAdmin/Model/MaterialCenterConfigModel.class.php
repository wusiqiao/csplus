<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019-04-08
 * Time: 13:38
 */

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class MaterialCenterConfigModel extends DataModel
{
    /**
     * 版本 -- 免费
     * */
    const VERSION_10 = 10;
    /**
     * 版本 -- 付费
     * */
    const VERSION_20 = 20;

    /**
     * 来源 -- 爬虫抓取
     * */
    const SOURCE_10 = 10;
    /**
     * 来源 -- 后台上传
     * */
    const SOURCE_20 = 20;

    /**
     * 使用限制 -- 关闭(无限制)
     * */
    const STINT_10 = 10;
    /**
     * 使用限制 -- 开启(有限制)
     * */
    const STINT_20 = 20;

    /**
     * 限制类型 -- 天
     * */
    const CYCLE_10 = 10;
    /**
     * 限制类型 -- 周
     * */
    const CYCLE_20 = 20;
    /**
     * 限制类型 -- 月
     * */
    const CYCLE_30 = 30;

    /**
     * 获取所有配置信息
     * */
    public function getConfig()
    {
        $data = $this->select();
        if(empty($data)){
           if( $this->initConfig()){
               return $this->getConfig();
           }
        }

        $config = [];
        foreach($data as $value){
            if($value['version'] == self::VERSION_10 && $value['source'] == self::SOURCE_10){
                $config['free']['he'] = $value;
            }

            if($value['version'] == self::VERSION_10 && $value['source'] == self::SOURCE_20){
                $config['free']['me'] = $value;
            }

            if($value['version'] == self::VERSION_20 && $value['source'] == self::SOURCE_10){
                $config['pay']['he'] = $value;
            }

            if($value['version'] == self::VERSION_20 && $value['source'] == self::SOURCE_20){
                $config['pay']['me'] = $value;
            }
        }

        return $config;
    }
    private function initConfig(){
        $time = time();
        $data[] = [ 'version' => self::VERSION_10, 'source'  => self::SOURCE_10, 'update_time' => $time];
        $data[] = [ 'version' => self::VERSION_10, 'source'  => self::SOURCE_20, 'update_time' => $time];
        $data[] = [ 'version' => self::VERSION_20, 'source'  => self::SOURCE_10, 'update_time' => $time];
        $data[] = [ 'version' => self::VERSION_20, 'source'  => self::SOURCE_20, 'update_time' => $time];

        return $this->addAll($data);
    }
    /**
     * 获取可用次数
     * @param  int $version 版本
     * @param  int $source 文章来源 类型
     *
     * @return int | true;
     * */
    public static function getByAvailableCount($version = self::VERSION_10, $source = self::SOURCE_10){
        $config =  M('MaterialCenterConfig')->where([
            'version' => $version,
            'source'  => $source,
            'status'  => self::STINT_20,
        ])->find();

        if(empty($config)){
            return 0;
        }

        if($config['stint'] == self::STINT_10){
            return true;
        }

        $cycle = 1;
        switch(intval($config['cycle'])){
            case self::CYCLE_10 :
                $cycle = 1;
                break;
            case self::CYCLE_20 :
                $cycle = 7;
                break;
            case self::CYCLE_30:
                $cycle = 30;
                break;
        }

        return [
            'count' => $config['number'],
            'cycle' => $cycle,
        ];
    }
}