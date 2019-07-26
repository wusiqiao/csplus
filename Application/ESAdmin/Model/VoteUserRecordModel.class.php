<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/18
 * Time: 10:08
 */

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class VoteUserRecordModel extends DataModel{
    const TYPE_10 = 10; //活动记录
    const TYPE_20 = 20; //参与人记录

    protected $_link = [
        "user" => [
            "join_name" => "LEFT",
            'class_name' => "MiniUser",
            'foreign_key' => 'mini_user_id',
            'mapping_name' => 'nick,avatar_url,',
            'mapping_fields' => 'nickname,avatar_url,contact_tel,contact_name',
            "mapping_key" => "id"
        ]
    ];

    /**
     * 活动浏览记录登记
     * */
    public static function switchActivity($miniUserId, $activityId){
        $data = ['mini_user_id' => $miniUserId, 'record_id' => $activityId, 'type' => self::TYPE_10];
        $model = new self();
        if($model->where($data)->find()){
            return 2;
        }

        $data['create_time'] = time();
        $model->startTrans();
        if($model->add($data) && (new VoteActivityModel())->where(['id' => $activityId])->setInc('access_total')){
            $model->rollback();
            return 1;
        }

        $model->commit();
        return 0;
    }

    /**
     * 浏览参与人记录登记
     * */
    public static function switchParticipant($miniUserId, $recordId){
        $data = [ 'mini_user_id' => $miniUserId, 'record_id' => $recordId, 'type' => self::TYPE_20];
        $model = new self();
        if($model->where($data)->find()){
            return 2;
        }

        $data['create_time'] = time();
        if((new VoteParticipantModel)->where(['id' => $recordId])->setInc('scan_total') && $model->add($data)){
            return 1;
        }

        return 0;
    }
}