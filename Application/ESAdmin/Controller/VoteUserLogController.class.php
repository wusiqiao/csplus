<?php
/**
 * @auhor kcg
 * */
namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;
use ESAdmin\Model\VoteUserLogModel;
/**
 *用户投票记录
 * */
class VoteUserLogController extends DataController{
    protected function _before_list(&$list){
        foreach($list as $key => $item){
            $list[$key]['voteTime'] = date('Y-m-d H:i:s', $item['vote_time']);
        }
    }
}