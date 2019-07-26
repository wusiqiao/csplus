<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/18
 * Time: 10:08
 */

namespace ESAdmin\Model;
use Common\Lib\Model\DataModel;

class VoteParticipantModel extends DataModel{
    protected $_auto = [
        ['create_date', 'date', 3, 'function' ]
    ];
    /**
     * @var是否审核通过 -- 未审核
     * */
    const STATUS_10 = 10;
    /**
     * @var是否审核通过 -- 审核通过
     * */
    const STATUS_20 = 20;

    public function checkDataPermission($id){
        return true;
    }
    public static function findById($id){
        return (new self)->where(['id' => $id])->find();
    }

    /**
     * 获取排名
     * @param $activity_id
     * @param $vote_taotal
     */
    public static function getRanking($activity_id, $vote_taotal){
         $count=  (new self())
            ->where([
                'activity_id' => $activity_id,
                'status' => self::STATUS_20,
                'vote_taotal' => ['gt', $vote_taotal]
            ])->count();

        return 1 + $count;
    }

    /**
     * @param 活动ID $activityId
     * @param 是否审核通过 $status
     */
    public static function getList($activityId, $status = self::STATUS_20){
        $where['activity_id'] = $activityId;
        if($status){
            $where['status'] = $status;
        }

        return (new self())->where($where)->page(I('get.page', 1), 20)->select();
    }
    /**
     * 获取排名列表
     * */
    public static function getRankingList($activityId,  $status = self::STATUS_20){
        $where['activity_id'] = $activityId;
        if($status){
            $where['status'] = $status;
        }

        return (new self())->where($where)->page(I('get.page', 1), 20)->order(['vote_taotal' => 'DESC'])->select();
    }

    public static function Approved($id){
        return (new self)->where(['id' => $id])->save([
            'status' => self::STATUS_20,
            'update_time' => time(),
        ]);
    }
}