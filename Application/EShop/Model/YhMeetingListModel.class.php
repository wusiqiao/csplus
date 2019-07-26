<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/07/15
 * Time: 19:58
 */
namespace EShop\Model;


class YhMeetingListModel extends DataModel{


	protected function _after_insert(&$data,$options) {
		$meetingInfo = D('YhMeeting')->where(array('id'=>array('eq',$data['meeting_id'])))->find();
		if ($meetingInfo['is_check'] == 0 && ($meetingInfo['create_id'] != $data['user_id'])){
			$userMobile = M('SysUser')->where(array('id'=>array('eq',$meetingInfo['create_id'])))->getField('mobile');

			$openid = M('SysUser')->where(array('id'=>array('eq',$data['user_id']),'is_follow'=>array('eq',1)))->getField('openid');
			$url = str_replace('shop','shop'.getBrowseBranchId(),SHOP_ROOT).'/YhMeeting/detail/id/'.$data['meeting_id'];
			if ($openid){
				$sendUpLeader = array(
					'template_id'=>getWxTemplateIdByStandardId('OPENTM416800304'),
					'url'=>$url,
					'body'=>array(
						'first'=>array('value'=>'您好！您的报名情况已进行审核。'),
						'keyword1'=>array('value'=>$meetingInfo['title']),
						'keyword2'=>array('value'=>$userMobile),
						'keyword3'=>array('value'=>'报名成功'),
					),
					'openid'=>$openid,
				);
				send_wx_message($sendUpLeader);
			}
		} elseif ($meetingInfo['is_check'] == 1) {
			$userMobile = M('SysUser')->where(array('id'=>array('eq',$data['user_id'])))->getField('mobile');

			$openid = M('SysUser')->where(array('id'=>array('eq',$meetingInfo['create_id']),'is_follow'=>array('eq',1)))->getField('openid');
			$url = '';
			if ($openid){
				$sendUpLeader = array(
					'template_id'=>getWxTemplateIdByStandardId('OPENTM416800304'),
					'url'=>$url,
					'body'=>array(
						'first'=>array('value'=>'您好，有人员报名，请及时进行审核。'),
						'keyword1'=>array('value'=>$meetingInfo['title']),
						'keyword2'=>array('value'=>$userMobile),
						'keyword3'=>array('value'=>'待审核'),
					),
					'openid'=>$openid,
				);
//				send_wx_message($sendUpLeader);
			}
		}

	}

}