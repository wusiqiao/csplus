<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/18
 * Time: 10:08
 */

namespace ESAdmin\Model;
use Common\Lib\Model\DataModel;

class VoteActivityModel extends DataModel{
    /**
     * @var 报名是否需要审核--需要审核
     * */
    const REVIEW_10 = 10;
    /**
     * @var 报名是否需要审核--不需要
     * */
    const REVIEW_20 = 20;
    /**
     * @var是否允许重复投票 -- 允许
     * */
    const VOTE_REPEAT_10 = 10;
    /**
     * @var是否允许重复投票 -- 不允许
     * */
    const VOTE_REPEAT_20 = 20;

    /**
     * @var是否暂停活动 -- 不暂停
     * */
    const STATUS_10 = 10;
    /**
     * @var是否暂停活动 -- 暂停
     * */
    const STATUS_20 = 20;

    protected function _after_find(&$result, $options){
        $result['startTime'] = date('Y/m/d', $result['start_time']);
        $result['endTime'] = date('Y/m/d', $result['end_time']);
    }

    public static function findById($id){
        return (new self)->where(['id' => $id])->find();
    }

    /**
     * @param 是否暂停活动 $status
     */
    public static function getProcessingListByStatus($status = self::STATUS_10){
        if($status){
            $where['status'] = $status;
        }

        $where['end_time'] =  ['lt', time() + 1];
        $page = I('get.page', '1');

        return (new self())->where($where)->page($page, 20)->select();
    }

    public static function isDelete($that){
        $time = time();
        if($that['end_time'] < $time){
            return '活动已结束， 禁止删除!';
        }

        if($that['end_time'] >= $time && $that['start_time'] <= $time){
            if($that['access_total'] > 0)
            return '活动正在进行中， 禁止删除!';
        }

        return true;
    }

    public static function isAllow($vote, $voteLog){
        if($vote['status'] == self::STATUS_20){
            return '活动暂停中!';
        }

        if($vote['vote_repeat']  == self::VOTE_REPEAT_20){
            return '您已投票，请勿重复投票';
        }

        $voteCycle = self::diffBetweenTwoDays($voteLog['last_date']);
        if($voteCycle > $vote['vote_cycle']){
            return true;
        }

        $time = time() - $voteLog['vote_time'];
        if($voteCycle == $vote['vote_cycle'] && $time > (60 * 60 * 24)){
            return true;
        }

        if($vote['vote_num'] > $voteLog['count']){
            return true;
        }

        $watiDay = $vote['vote_cycle'] - $voteCycle;
        $time = $watiDay * 24 * 60 * 60;

        return '再等' . self::toTime($time) . '就可以投票了';
    }

    public static function statisticsVote($id){
         return (new self())->where(['id' => $id])->setInc('vote_total');
    }

    public static function toTime($time){
        $string = '';
        $day = intval($time / (60 * 60 * 24));
        if($day > 0){
            $string .= $day  . '天';
        }
        $time = $time - $day * (60 * 60 * 24);
        $hours = intval($time / 3600);
        if($hours > 0){
            $string .= $hours . '小时';
        }
        $time = $time - ($hours * 3600);
        $min = ceil($time / 60);

        $string .= $min . '分后';

        return $string;
    }

    public static function diffBetweenTwoDays($day){
        $second1 = strtotime($day);
        $second2 = time();
        if ($second1 < $second2) {
            $tmp = $second2;
            $second2 = $second1;
            $second1 = $tmp;
        }

        return ($second1 - $second2) / 86400;
    }
    /**
     * 验证是否允许报名 并且统计报名人数
     * */
    public static function addParticipantTotal($id){
        $model = new self();
        $where['id'] = $id;
        $vote = $model->where($where)->find();
        if(empty($vote)){
            return '活动不存在!';
        }

        if($vote['status'] == self::STATUS_20){
            return '活动暂停中!';
        }

        if($vote['end_time'] < time()){
            return '报名已结束!';
        }

        if(! $model->where($where)->setInc('participant_total')){
            return '登记失败!';
        }

        return $vote;
    }
}