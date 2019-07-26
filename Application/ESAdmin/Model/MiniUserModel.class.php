<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019-04-08
 * Time: 13:38
 */

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class MiniUserModel extends DataModel{
    public static function findByToken($token){
        return (new  self())->where(['token' => $token])->find();
    }

    public static function findByOpenid($openid){
        return (new  self())->where(['openid' => $openid])->find();
    }
    /**
     * @param  array $info 微信小程序获取的数据
     * @param  string $appid 微信小程序的AppID
     * @param  int $branchId
     * @param  string 签名方式  支持PHP 所有的函数
     * */
    public static function switchThat($info, $appid, $branchId = 0, $signType = 'safe'){
        if(self::findByOpenid($info['openId'])){
            $token = self::updateThat($info['openId'], $info['nickName'], $info['avatarUrl']);
        }else{
            $token = self::createThat($info, $appid, $branchId);
        }

        if(!$token){
            return false;
        }

        return self::encodeToken($signType, $token);
    }

    public static function decodeToken($signType,$token){
        switch($signType){
            case 'base64' :
                return base64_decode($token);
            case 'safe' :
                return  substr($token,0, 32);
        }

        return $token;
    }

    public static function encodeToken($signType, $token){
        switch($signType){
            case 'base64' :
                return base64_encode($token);
            case 'safe' :
                return $token . substr($token,0, rand(3, 15));
        }

        return $token;
    }

    public static function updateThat($openid, $nickname, $avatarUrl){
        $save['nickname'] = $nickname;
        $save['avatar_url'] = $avatarUrl;
        $save['token'] = self::getToken($openid);
        $save['update_time'] = time();

        if((new self())->where(['openid' => $openid])->save($save)){
            return $save['token'];
        }

        return false;
    }

    public static function createThat($info, $appid, $branchId){
        $save['appid'] = $appid;
        $save['openid'] = $info['openId'];
        $save['token'] = self::getToken($info['openId']);
        $save['nickname'] = $info['nickName'];
        $save['avatar_url'] = $info['avatarUrl'];
        $save['gender'] = $info['gender'];
        $save['contact_name'] = $save['contact_tel'] = '未获得';
        $save['branch_id'] = $branchId;
        $save['update_time']  = $save['create_time'] = time();

        if(!(new self())->add($save)){
            return false;
        }

        return $save['token'];
    }

    private static function getToken($openid){
        return md5($openid . uniqid());
    }

    protected function _before_write(&$data){
        if(isset($data['nickname'])){
            $data['nickname'] = base64_encode($data['nickname']);
        }

        $data['update_time'] = time();
    }

    protected function _after_find(&$result, $options){
       $result['nickname'] = base64_decode($result['nickname']);
    }
}