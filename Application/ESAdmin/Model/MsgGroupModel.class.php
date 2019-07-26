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
 * 用户消息分组名称
 * Class MsgGroupModel
 * @package ESAdmin\Model
 */
class MsgGroupModel extends DataModel{
    const TYPE_10 = 10; //私聊组
    const TYPE_20 = 20;
    public static function getInstance(){
        if(! (self::$instance instanceof  self)){
            self::$instance = new self;
        }

        return self::$instance;
    }

    private static $instance = null;
}