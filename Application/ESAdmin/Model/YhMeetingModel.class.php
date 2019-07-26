<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/07/15
 * Time: 9:53
 */
namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;
use ESAdmin\Controller\MsgGroupMemberController;

class YhMeetingModel extends DataModel{

	protected $_link = array(
		"province" => array(
			"join_name" => "LEFT",
			'class_name' => "SysRegion",
			'foreign_key' => 'province',
			'mapping_name' => 'province',
			'mapping_fields' => 'name',
			"mapping_key" => "id"
		),
		"city" => array(
			"join_name" => "LEFT",
			'class_name' => "SysRegion",
			'foreign_key' => 'city',
			'mapping_name' => 'city',
			'mapping_fields' => 'name',
			"mapping_key" => "id"
		),
		"area" => array(
			"join_name" => "LEFT",
			'class_name' => "SysRegion",
			'foreign_key' => 'area',
			'mapping_name' => 'area',
			'mapping_fields' => 'name',
			"mapping_key" => "id"
		),
	);


	protected $_validate = array(
		array('title','require','活动标题不能为空！'),
		array('content','require','活动详情不能为空！'),
		array('start_time','checkStartTime','活动开始时间要早于活动结束时间！',0,'callback',3),
		array('apply_start_time','checkApplyStartTime','报名开始时间要早于报名结束时间！',0,'callback',3),
//		array('apply_start_time','checkApplyStartEndTime','报名开始时间要早于活动结束时间！',0,'callback',3),
		array('apply_end_time','checkApplyEndEndTime','报名结束时间要早于活动结束时间！',0,'callback',3),
		array('parameter_names','checkArrayNum','参数添加个数不得超过26个',0,'callback',3),
	);

	protected function checkStartTime(){
		$start_time = strtotime(I('start_time'));
		$end_time = strtotime(I('end_time'));
		if ($start_time >= $end_time ){
			return false;
		}
	}

	protected function checkApplyStartTime(){
		$apply_start_time = strtotime(I('apply_start_time'));
		$apply_end_time = strtotime(I('apply_end_time'));
		if ($apply_start_time >= $apply_end_time){
			return false;
		}
	}
//	protected function checkApplyStartEndTime(){
//		$apply_start_time = strtotime(I('apply_start_time'));
//		$end_time = strtotime(I('end_time'));
//		if ($apply_start_time >= $end_time){
//			return false;
//		}
//	}
	protected function checkApplyEndEndTime(){
		$apply_end_time = strtotime(I('apply_end_time'));
		$end_time = strtotime(I('end_time'));
		if ($apply_end_time >= $end_time){
			return false;
		}
	}

	protected function checkArrayNum()
	{
		$parameter_names = I('parameter_names');
		if (count($parameter_names)>26){
			return false;
		}
	}


	protected function _after_insert($data, $options)
	{
		parent::_after_insert($data, $options);
		$MsgGroupMember = new MsgGroupMemberController();
		$result = $MsgGroupMember->createGroupAction($data['title'],true);
		$data['group_id'] = $result['group_id'];
		$this->save($data);
	}


}