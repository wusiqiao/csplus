<?php
namespace ESAdmin\Model;
use Common\Lib\Model\DataModel;
/**
 * 第三方平台 配置信息!
 */
class WxPlatformConfigModel extends DataModel{
    const CACHE_CONFIG_KEY = WxPlatformConfigModel::class . '_config';
    const STATUS_10 = 10;
    public static function getConfig(){
        $config = self::findConfig();
        $config = empty($config) ? self::createConfig() : $config;

        return $config;
    }

    public static function updateThat($data = []){
        $time = time();
        $data['update_time'] = $time;
        isset($data['access_token']) ? $data['refresh_token_time'] = $time : '';
        $config = self::getConfig();
        if(empty($config)){
            return self::createConfig($data);
        }else{
            if(!self::getInstance()->where(['id' => $config['id']])->save($data)){
                return false;
            }

            self::clearCache();
            return self::getConfig();
        }
    }

    public static function getInstance(){
        if(!self::$_instance instanceof self){
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    protected static function findConfig(){
        return self::getInstance()->where(['status' => self::STATUS_10 ])->find();
    }

    protected static function createConfig($config = []){
        $data = [
            'name' => 'name',
            'appid' => 'wxfa491efe64baa95f',
            'secret' => 'd9a5163b2c891070d049f6a5cceb4085',
            'token' => 'dgdsngdsgdgdkgkdkg522fsfdsfsfsfsf',
            'key' => 'QuZcNfJPqtnKgRZhuVYRfFvMAKsPpKymuxeTIhyNHFI',
            'status' => self::STATUS_10,
            'verify_ticket' => 'verify_ticket',
            'access_token' => 'access_token',
            'update_time' => 0,
        ];

        foreach($config as $field => $val){
            if(isset($data[$field])){
                $data[$field] = $val;
            }
        }

        if(self::getInstance()->add($data)){
            return self::getConfig();
        }

        return null;
    }

    private static $_instance = null;
}