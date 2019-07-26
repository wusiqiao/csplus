<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/18
 * Time: 10:08
 */

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

/**
 * 消息组成员
 * Class MsgGroupMemberModel
 * @package ESAdmin\Model
 */
class MsgGroupMemberModel extends DataModel{

    public static function createGroup($userId, $msgGroupId){
        return self::getInstance()
            ->add([
                'msg_group_id' => $msgGroupId,
                'user_id' => $userId,
                'create_time' => time(),
            ]);
    }

    public static function getGroupMember($msgGroupId){
        return self::getInstance()
            ->where(['msg_group_id' => $msgGroupId])
            ->select();
    }

    public static function getInstance(){
        if(! (self::$instance instanceof  self)){
            self::$instance = new self;
        }

        return self::$instance;
    }

    private static $instance = null;
}