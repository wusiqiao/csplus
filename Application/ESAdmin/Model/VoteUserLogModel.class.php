<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/18
 * Time: 10:08
 */

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class VoteUserLogModel extends DataModel{
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
     * 投票
     */
    public static function participant($miniUserId, $participantId, $activity){
        $model = new self();
        $where['mini_user_id'] = $miniUserId;
        $where['activity_id']  = $activity['id'];
        $where['participant_id'] = $participantId;
        $time = time() - (intval($activity['vote_cycle']) * 24 * 60 * 60);
        $where['vote_time'] = ['gt', $time];

        $logs = $model->where($where)->order(['vote_time' => 'ASC'])->select();
        if(!empty($logs)){
            $log = $logs[0];
            $log['last_date'] = date('Y-m-d H:i:s', $log['vote_time']);
            $log['count'] = count($logs);
            $res = VoteActivityModel::isAllow($activity, $log);
            if($res !== true){
                return $res;
            }
        }

        $data['vote_time']  = time();
        $data['branch_id'] = $activity['branch_id'];
        $data['mini_user_id'] = $miniUserId;
        $data['activity_id'] = $activity['id'];
        $data['participant_id'] = $participantId;
        $data['last_date'] = date('Y-m-d H:i:s');
        $model->startTrans();
        if(
            (new VoteActivityModel)->where(['id' => $activity['id']])->setInc('vote_total')
            && (new VoteParticipantModel())->where(['id' => $participantId])->setInc('vote_taotal')
            && $model->add($data)
        ){
            $model->commit();
            return 1;
        }

        $model->rollback();
        return false;
    }

    public static function vote($miniUserId, $participantId, $activity){
        self::participant($miniUserId, $participantId, $activity);
        $model = new self();
        $data['mini_user_id'] = $miniUserId;
        $data['activity_id']  = $activity['id'];
        $time = time() - (intval($activity['vote_cycle']) * 24 * 60 * 60);
        $data['vote_time'] = ['gt', $time];
        $logs = $model->where($data)->order(['vote_time' => 'ASC'])->select();
        if(!empty($logs)){
            $log = $logs[0];
            $log['count'] = count($logs);
            $log['last_date'] = date('Y-m-d H:i:s', $log['vote_time']);

            $res = VoteActivityModel::isAllow($activity, $log);
            if($res !== true){
                return $res;
            }
        }

        $model->startTrans();
        if(
            (new VoteActivityModel)->where(['id' => $activity['id']])->setInc('vote_total')
            && (new VoteParticipantModel())->where(['id' => $participantId])->setInc('vote_taotal')
            && $model->add([
                'mini_user_id' => $miniUserId,
                'activity_id' => $activity['id'],
                'participant_id' => $participantId,
                'vote_time' => time(),
                'last_date' => date('Y-m-d H:i:s'),
                'branch_id' => $activity['branch_id'],
            ])
        ){
            $model->commit();
            return 1;
        }

        $model->rollback();
        return false;
    }
}