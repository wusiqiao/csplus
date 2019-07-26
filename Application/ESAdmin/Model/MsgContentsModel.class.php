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
 * 用户消息
 * Class MsgGroupModel
 * @package ESAdmin\Model
 */
class MsgContentsModel extends DataModel{
    const MSG_TYPE_10 = 10;
    const MSG_TYPE_20 = 20;
    const IS_READ_10 = 10;
    const IS_READ_20 = 20;
    /**
     * 创建消息
     * @param $contents
     * @param $contents_type
     * @param $msg_group_id
     * @param $send_user_id
     * @param $accept_user_id
     * @param int $msg_type
     */
    public static function createMsg($data){
        if(self::getInstance()->add($data)){
            return true;
        }

        return false;
    }
    /**
     * @param $msgGroupId
     */
    public static function getHistoryMsg($msgGroupId){
        return self::getInstance()
            ->where(['msg_group_id' => $msgGroupId])
            ->field('')
            ->page(I('get.page'), 10)
            ->order(['create_time' => 'DESC'])
            ->select();
    }

    public static function readMsg($acceptUserId){
        return self::getInstance()
            ->where(['accept_user_id' => $acceptUserId])
            ->save(['is_read' => self::IS_READ_20]);
    }

    public static function getInstance(){
        if(!(self::$instance instanceof self)){
            self::$instance = new self;
        }

        return self::$instance;
    }

    private static $instance = null;
}