<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/07/11
 * Time: 15:58
 */
namespace ESAdmin\Controller;


use Common\Lib\Controller\DataController;
use Think\Exception;

class YhMeetingController extends DataController {

	/**
	 * 添加雁会类型
	 */
	public function addTypeAction()
	{
		try{
			$data['name'] = I('value');
			$data['sort'] = I('sort',0);
			$item = D('YhMeetingType')->create($data);
			if (!$item){
				throw new Exception(D('YhMeetingType')->getError());
			}
			$id = D('YhMeetingType')->add($item);
			$this->ajaxReturn(array("error"=>0,"message"=>"添加成功！","id"=>$id));
		} catch (Exception $e) {
			$this->ajaxReturn(array("error"=>1,"message"=>"添加失败！"));
		}
	}

	/**
	 * 编辑雁会类型
	 */
	public function editTypeAction()
	{
		try{
			$data['id'] = I('id');
			$data['name'] = I('value');
			$data['sort'] = I('sort',0);
			$item = D('YhMeetingType')->create($data);
			if (!$item){
				throw new Exception(D('YhMeetingType')->getError());
			}
			$id = D('YhMeetingType')->save($item);
			$this->ajaxReturn(array("error"=>0,"message"=>"修改成功！","id"=>$id));
		} catch (Exception $e) {
			$this->ajaxReturn(array("error"=>1,"message"=>"修改失败！"));
		}
	}


	/**
	 * 雁会类型页面
	 */
	public function typeIndexAction()
	{
		$list = D('YhMeetingType')->field("id as value,name as text")->order('sort ASC')->select();
		$this->assign('typeList',$list);
		$this->display();
	}
	
	/**
	 * 雁会类型列表
	 */
	public function typeListAction()
	{
		$list = D('YhMeetingType')->field("id as value,name as text")->order('sort ASC')->select();
		$this->ajaxReturn($list);
	}

	/**
	 * 删除雁会类型
	 */
	public function deleteTypeAction()
	{
		try{
			$id = I('id');

			$hasMeeting = D('YhMeeting')->where(array('type_id'=>array('eq',$id)))->find();

			if ($hasMeeting){
				throw new Exception('该类型存在相关雁会,不可删除!');
			}

			D('YhMeetingType')->where(array('id'=>array('eq',$id)))->delete();

		    $this->ajaxReturn(buildMessage('删除成功!'));
		} catch (Exception $e) {
		    $this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}
	}

	protected function _before_add(&$data) {
		$data['is_drafts'] = 1;
		$data['is_check'] = 1;
		$data['parameter'] = array(array('parameter_name'=>'姓名','is_require'=>1),array('parameter_name'=>'手机','is_require'=>1));
		parent::_before_add($data);
	}

	protected function _getAddData() {
		$record = array();
		$this->_before_add($record);
		return json_encode($record);
	}

	public function editAction($id)
	{
		$this->assignPermissions();
		$record = $this->_getDetailData($id);
		$record['parameter'] = json_decode($record['parameter'],true);
		$this->assign("model", json_encode($record));
		exit($this->fetch($this->_get_detail_template($record)));
	}


	/**
	 * @param $type
	 * @param $data
	 * 创建/修改活动 组装数据
	 */
	protected function _before_write($type, &$data) {
		parent::_before_write($type, $data);

		$data['start_time'] = strtotime($data['start_time']);
		$data['end_time'] = strtotime($data['end_time']);
		$data['apply_start_time'] = strtotime($data['apply_start_time']);
		$data['apply_end_time'] = strtotime($data['apply_end_time']);

		$parameters = array();
		if (!empty($data['parameters'])){
			foreach ($data['parameters'] as $key=>$value){
				$parameters[] = array('parameter_name'=>$value['parameter_name'],'is_require'=>$value['is_require'] ? $value['is_require'] : 0);
			}
		}
		unset($data['parameter_name']);
		unset($data['parameter_require']);
		$data['parameter'] = json_encode($parameters);
		if ($type == parent::ACTION_ADD){
			$data['create_id'] = $this->userId;
			$data['create_time'] = time();
		}
	}

	public function indexAction()
	{
		$list = D('YhMeetingType')->order('sort ASC')->select();
		$this->assign('typeList',$list);
		$this->display();
	}

	/**
	 * 草稿箱
	 */
	public function draftsAction()
	{
		$this->display();
	}

	public function _parsefilter(&$filter){
		$filter['a.is_delete'] = ['eq',0];
		$filter['a.is_drafts'] = ['eq', I('is_drafts',0)];
//		$status = intval(I('status', 0));
//		switch($status){
//			case 1:
//				$filter['a.apply_start_time'] = ['egt', time()];
//				break;
//			case 2:
//				$filter['a.apply_start_time'] = ['lt', time()];
//				$filter['a.apply_end_time'] = ['egt', time()];
//				break;
//			case 3:
//				$filter['_string'] = sprintf('(a.apply_start_time < %d and a.apply_end_time
//				>= %d and a.is_close_apply = 1) or (a.apply_end_time < %d and a.start_time
//				>= %d)',time(),time(),time(),time());
//				break;
//			case 4:
//				$filter['a.start_time'] = ['lt', time()];
//				$filter['a.end_time'] = ['egt', time()];
//				break;
//			case 5:
//				$filter['_string'] = sprintf('(a.start_time < %d and a.end_time
//				>= %d and a.status = 5) or (a.end_time < %d)',time(),time(),time());
//				break;
//			case 6:
//				$filter['a.status'] = ['eq', 6];
//				break;
//			case 7:
//				$filter['a.status'] = ['eq', 7];
//				break;
//		}
		if (I('status')){
			$filter['a.status'] = ['eq', I('status')];
		}
		if (I('type_id')){
			$filter['a.type_id'] = ['eq', I('type_id')];
		}
		if (I('name')){
			$filter['a.title'] = ['like', "%".I('name')."%"];
		}

		$times = intval(I('times', 0));
		switch($times){
			case 1:
				$filter['a.create_time'] = ['gt', strtotime(date('Y-m-d 00:00:00'))];
				break;
			case 2:
				$nowtime = strtotime(date('Y-m-d 00:00:00'));
				$threedaytime = strtotime('-2 day',$nowtime);
				$filter['a.create_time'] = ['gt', $threedaytime];
				break;
			case 3:
				$nowtime = strtotime(date('Y-m-d 00:00:00'));
				$onemonthtime = strtotime('-1 month',$nowtime);
				$filter['a.create_time'] = ['gt', $onemonthtime];
				break;
			case 4:
				if (I('startTime') && I('endTime')){
					$startTime = I('startTime').' 00:00:00';
					$endTime = I('endTime').' 23:59:59';
					$filter['_string'] = sprintf('a.create_time >= %d and a.create_time <= %d',strtotime($startTime),strtotime($endTime));
				}
				break;
		}

	}

	protected function _before_list(&$list) {
		foreach ($list as $k=>$v){
			$list[$k]['create_time'] = date('Y-m-d H:i',$v['create_time']);
			$list[$k]['count'] = M('YhMeetingList')->where(array('meeting_id'=>array('eq',$v['id']),'is_passed'=>array('eq',1)))->count();
			$list[$k]['apply_start_time'] = date('Y-m-d H:i',$v['apply_start_time']);
			$list[$k]['apply_end_time'] = date('Y-m-d H:i',$v['apply_end_time']);
			$list[$k]['start_time'] = date('Y-m-d H:i',$v['start_time']);
			$list[$k]['end_time'] = date('Y-m-d H:i',$v['end_time']);
			$list[$k]['address'] = $v['province_name'].$v['city_name'].$v['area_name'].$v['address'];
		}
	}

	protected function _before_detail(&$data) {
		$userList = M('YhMeetingList')->field('parameter')->where(array('meeting_id'=>array('eq',$data['id']),'is_passed'=>array('eq',1)))->limit(5)->select();

		foreach($userList as $key=>$value){
			$parameter = json_decode($value['parameter'],true);
			$userList[$key]['user_name'] = $parameter[0]['parameter_value'];
		}

		$data['userList'] = $userList;
		$data['num'] = M('YhMeetingList')->alias('a')
			->where(array('a.meeting_id'=>array('eq',$data['id']),'a.is_passed'=>array('eq',1)))
			->count();
		$data['type_name'] = M('YhMeetingType')->where(array('id'=>array('eq',$data['type_id'])))->getField('name');
		parent::_before_detail($data);
	}

	/**
	 * @param null $id
	 * 活动详情
	 */
	public function detailAction($id = null) {
		$this->assignPermissions();
		$record = $this->_getDetailData($id);
		$this->assign("model", $record);
		$this->display();
	}

	/**
	 * 关闭报名
	 */
	public function doCloseApplyACtion()
	{
		try{
			$id = I('id');
			$info = D('YhMeeting')->where(array('id'=>array('eq',$id),'is_delete'=>array('eq',0)))->find();
			if (empty($info)){
				throw new Exception('该活动不存在！');
			}
			if ($info['create_id'] != $this->userId){
				throw new Exception('该活动不是您发布的！');
			}
			if (time()>$info['apply_end_time']){
				throw new Exception('已过报名时间！');
			}
			if ($info['is_close_apply'] == 1){
				throw new Exception('请勿重复操作！');
			}

			$info['is_close_apply'] = 1;

			D('YhMeeting')->save($info);

		    $this->ajaxReturn(buildMessage());
		} catch (Exception $e) {
		    $this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}
	}

	/**
	 * 开启报名
	 */
	public function doOpenApplyAction()
	{
		try{
			$id = I('id');
			$info = D('YhMeeting')->where(array('id'=>array('eq',$id),'is_delete'=>array('eq',0)))->find();
			if (empty($info)){
				throw new Exception('该活动不存在！');
			}
			if ($info['create_id'] != $this->userId){
				throw new Exception('该活动不是您发布的！');
			}
			if (time()>$info['apply_end_time']){
				throw new Exception('已过报名时间！');
			}
			if ($info['is_close_apply'] == 0){
				throw new Exception('请勿重复操作！');
			}

			$info['is_close_apply'] = 0;

			D('YhMeeting')->save($info);
		    $this->ajaxReturn(buildMessage());
		} catch (Exception $e) {
		    $this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}
	}

	/**
	 * 关闭活动页面
	 */
	public function closeMeetingAction()
	{
		$data['id'] = I('id');
		$this->assign('model',$data);
		$this->display();
	}

	/**
	 * 关闭活动
	 */
	public function doCloseMeetingAction()
	{
		try{
			$id = I('id');
			$reason = I('reason');

			if (empty($reason)){
				throw new Exception('请填写关闭活动的原因！');
			}

			$info = D('YhMeeting')->where(array('id'=>array('eq',$id),'is_delete'=>array('eq',0)))->find();
			if (empty($info)){
				throw new Exception('该活动不存在！');
			}
			if ($info['create_id'] != $this->userId){
				throw new Exception('该活动不是您发布的！');
			}
			if (time()>$info['start_time']){
				throw new Exception('该活动已开始不可关闭！');
			}
			if ($info['status'] == 6){
				throw new Exception('请勿重复操作！');
			}

			$info['status'] = 6;

			D('YhMeeting')->save($info);

			$list = M('YhMeetingList')->field('user_id')->where(array('meeting_id'=>array('eq',$id),'is_passed'=>array('eq',1)))->select();

			$url = str_replace('shop','shop'.$this->_user_session->currBranchId,SHOP_ROOT).'/YhMeeting/detail/id/'.$id;
			foreach ($list as $user){
				$openid = M('SysUser')->where(array('id'=>array('eq',$user['user_id']),'is_follow'=>array('eq',1)))->getField('openid');
				if (empty($openid)){
					continue;
				}
				$sendUpLeader = array(
					'template_id'=>getWxTemplateIdByStandardId('OPENTM412932965'),
					'url'=>$url,
					'body'=>array(
						'first'=>array('value'=>'非常抱歉通知您，本次活动已取消。'),
						'keyword1'=>array('value'=>$info['title']),
						'keyword2'=>array('value'=>$reason),
						'keyword3'=>array('value'=>date('Y-m-d H:i:s')),
					),
					'openid'=>$openid,
				);
				send_wx_message($sendUpLeader);
			}


			$this->ajaxReturn(buildMessage());
		} catch (Exception $e) {
			$this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}
	}

	/**
	 * 结束活动
	 */
	public function doEndMeetingAction()
	{
		try{
			$id = I('id');
			$info = D('YhMeeting')->where(array('id'=>array('eq',$id),'is_delete'=>array('eq',0)))->find();
			if (empty($info)){
				throw new Exception('该活动不存在！');
			}
			if ($info['create_id'] != $this->userId){
				throw new Exception('该活动不是您发布的！');
			}
			if (time()<$info['start_time']){
				throw new Exception('该阶段不可结束活动！');
			}
			if ($info['status'] == 5){
				throw new Exception('请勿重复操作！');
			}

			$info['status'] = 5;

			D('YhMeeting')->save($info);

			$this->ajaxReturn(buildMessage());
		} catch (Exception $e) {
			$this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}
	}
	
	/**
	 * 活动归档
	 */
	public function doArchivingAction()
	{
		try{
			$id = I('id');
			$info = D('YhMeeting')->where(array('id'=>array('eq',$id),'is_delete'=>array('eq',0)))->find();
			if (empty($info)){
				throw new Exception('该活动不存在！');
			}
			if ($info['create_id'] != $this->userId){
				throw new Exception('该活动不是您发布的！');
			}
			if (!($info['status'] == 5 || $info['status'] == 6)){
				throw new Exception('该阶段不可归档活动！');
			}
			if ($info['status'] == 7){
				throw new Exception('请勿重复操作！');
			}

			$info['o_status'] = $info['status'];

			$info['status'] = 7;

			D('YhMeeting')->save($info);

			$this->ajaxReturn(buildMessage());
		} catch (Exception $e) {
			$this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}
	}

	/**
	 * 活动反归档
	 */
	public function doDisArchivingAction()
	{
		try{
			$id = I('id');
			$info = D('YhMeeting')->where(array('id'=>array('eq',$id),'is_delete'=>array('eq',0)))->find();
			if (empty($info)){
				throw new Exception('该活动不存在！');
			}
			if ($info['create_id'] != $this->userId){
				throw new Exception('该活动不是您发布的！');
			}
			if ($info['status'] != 7){
				throw new Exception('请勿重复操作！');
			}

			$info['status'] = $info['o_status'];

			D('YhMeeting')->save($info);

			$this->ajaxReturn(buildMessage($info));
		} catch (Exception $e) {
			$this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}
	}

	/**
	 * 删除活动
	 */
	public function deleteAction()
	{
		try{
			$id = I('id');
			$info = D('YhMeeting')->where(array('id'=>array('eq',$id),'is_delete'=>array('eq',0)))->find();
			if (empty($info)){
				throw new Exception('该活动不存在！');
			}
			if ($info['create_id'] != $this->userId){
				throw new Exception('该活动不是您发布的！');
			}
//			if ($info['status'] != 7){
//				throw new Exception('请归档后再进行删除操作！');
//			}
			if ($info['is_delete'] == 1){
				throw new Exception('请勿重复操作！');
			}

			$info['is_delete'] = 1;

			D('YhMeeting')->save($info);

			$this->ajaxReturn(buildMessage());
		} catch (Exception $e) {
			$this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}
	}
	
	/**
	 * 通过审核
	 */
	public function approvedAction()
	{
		try{
			$id = I('id');

			$info = D('YhMeetingList')->where(array('id'=>array('eq',$id)))->find();
			if (empty($info)){
				throw new Exception('该报名申请无效！');
			}

			$meetingInfo = D('YhMeeting')->where(array('id'=>array('eq',$info['meeting_id']),'is_delete'=>array('eq',0)))->find();
			if (empty($meetingInfo)){
				throw new Exception('该活动不存在！');
			}
			if ($meetingInfo['create_id'] != $this->userId){
				throw new Exception('该活动不是您发布的！');
			}
			if (time()>$meetingInfo['start_time']){
				throw new Exception('该活动已开始不可进行审核！');
			}

			if ($info['is_passed'] != 0){
				throw new Exception('请勿重复审核！');
			}

			$info['is_passed'] = 1;
			$info['update_time'] = time();

			M('YhMeetingList')->save($info);

			$userMobile = M('SysUser')->where(array('id'=>array('eq',$this->userId)))->getField('mobile');

			$openid = M('SysUser')->where(array('id'=>array('eq',$info['user_id']),'is_follow'=>array('eq',1)))->getField('openid');
			$url = str_replace('shop','shop'.$this->_user_session->currBranchId,SHOP_ROOT).'/YhMeeting/detail/id/'.$info['meeting_id'];
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


			$this->ajaxReturn(buildMessage());
		} catch (Exception $e) {
			$this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}
	}

	/**
	 * 批量通过审核
	 */
	public function batchApprovedAction()
	{
		try{
			$meeting_id = I('meeting_id');
			$meetingInfo = D('YhMeeting')->where(array('id'=>array('eq',$meeting_id),'is_delete'=>array('eq',0)))->find();
			if (empty($meetingInfo)){
				throw new Exception('该活动不存在！');
			}
			if ($meetingInfo['create_id'] != $this->userId){
				throw new Exception('该活动不是您发布的！');
			}
			if (time()>$meetingInfo['start_time']){
				throw new Exception('该活动已开始不可进行审核！');
			}

			$idarr = I('ids');
			$userMobile = M('SysUser')->where(array('id'=>array('eq',$this->userId)))->getField('mobile');
			$url = str_replace('shop','shop'.$this->_user_session->currBranchId,SHOP_ROOT).'/YhMeeting/detail/id/'.$meeting_id;
			foreach ($idarr as $id){
				$info = D('YhMeetingList')->where(array('id'=>array('eq',$id)))->find();
				if (empty($info)){
					continue;
				}

				if ($info['is_passed'] != 0){
					continue;
				}

				$info['is_passed'] = 1;
				$info['update_time'] = time();
				M('YhMeetingList')->save($info);
				$openid = M('SysUser')->where(array('id'=>array('eq',$info['user_id']),'is_follow'=>array('eq',1)))->getField('openid');
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
			}

			$this->ajaxReturn(buildMessage());
		} catch (Exception $e) {
			$this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}
	}
	
	
	/**
	 * 拒绝通过
	 */
	public function disApprovedAction()
	{
		try{
			$id = I('id');

			$info = D('YhMeetingList')->where(array('id'=>array('eq',$id)))->find();
			if (empty($info)){
				throw new Exception('该报名申请无效！');
			}

			$meetingInfo = D('YhMeeting')->where(array('id'=>array('eq',$info['meeting_id']),'is_delete'=>array('eq',0)))->find();
			if (empty($meetingInfo)){
				throw new Exception('该活动不存在！');
			}
			if ($meetingInfo['create_id'] != $this->userId){
				throw new Exception('该活动不是您发布的！');
			}
			if (time()>$meetingInfo['start_time']){
				throw new Exception('该活动已开始不可进行审核！');
			}

			if ($info['is_passed'] != 0){
				throw new Exception('请勿重复审核！');
			}

			$info['is_passed'] = 2;
			$info['update_time'] = time();
			M('YhMeetingList')->save($info);

			$userMobile = M('SysUser')->where(array('id'=>array('eq',$this->userId)))->getField('mobile');

			$openid = M('SysUser')->where(array('id'=>array('eq',$info['user_id']),'is_follow'=>array('eq',1)))->getField('openid');
			$url = str_replace('shop','shop'.$this->_user_session->currBranchId,SHOP_ROOT).'/YhMeeting/detail/id/'.$info['meeting_id'];
			if ($openid){
				$sendUpLeader = array(
					'template_id'=>getWxTemplateIdByStandardId('OPENTM416800304'),
					'url'=>$url,
					'body'=>array(
						'first'=>array('value'=>'您好！您的报名情况已进行审核。'),
						'keyword1'=>array('value'=>$meetingInfo['title']),
						'keyword2'=>array('value'=>$userMobile),
						'keyword3'=>array('value'=>'报名失败'),
					),
					'openid'=>$openid,
				);
				send_wx_message($sendUpLeader);
			}

			$this->ajaxReturn(buildMessage());
		} catch (Exception $e) {
			$this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}
	}

	/**
	 * @param $id
	 * 批量拒绝
	 */
	public function batchDisApprovedAction()
	{
		try{
			$meeting_id = I('meeting_id');
			$meetingInfo = D('YhMeeting')->where(array('id'=>array('eq',$meeting_id),'is_delete'=>array('eq',0)))->find();
			if (empty($meetingInfo)){
				throw new Exception('该活动不存在！');
			}
			if ($meetingInfo['create_id'] != $this->userId){
				throw new Exception('该活动不是您发布的！');
			}
			if (time()>$meetingInfo['start_time']){
				throw new Exception('该活动已开始不可进行审核！');
			}

			$idarr = I('ids');
			$userMobile = M('SysUser')->where(array('id'=>array('eq',$this->userId)))->getField('mobile');
			$url = str_replace('shop','shop'.$this->_user_session->currBranchId,SHOP_ROOT).'/YhMeeting/detail/id/'.$meeting_id;
			foreach ($idarr as $id){
				$info = D('YhMeetingList')->where(array('id'=>array('eq',$id)))->find();
				if (empty($info)){
					continue;
				}

				if ($info['is_passed'] != 0){
					continue;
				}

				$info['is_passed'] = 2;
				$info['update_time'] = time();
				M('YhMeetingList')->save($info);
				$openid = M('SysUser')->where(array('id'=>array('eq',$info['user_id']),'is_follow'=>array('eq',1)))->getField('openid');
				if ($openid){
					$sendUpLeader = array(
						'template_id'=>getWxTemplateIdByStandardId('OPENTM416800304'),
						'url'=>$url,
						'body'=>array(
							'first'=>array('value'=>'您好！您的报名情况已进行审核。'),
							'keyword1'=>array('value'=>$meetingInfo['title']),
							'keyword2'=>array('value'=>$userMobile),
							'keyword3'=>array('value'=>'报名失败'),
						),
						'openid'=>$openid,
					);
					send_wx_message($sendUpLeader);
				}
			}

			$this->ajaxReturn(buildMessage());
		} catch (Exception $e) {
			$this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}
	}


	public function qrcodeAction($id){
		//TODO::得替换url
		$data['url'] = str_replace('shop','shop'.$this->_user_session->currBranchId,SHOP_ROOT).'/YhMeeting/detail/id/'.$id;
		$this->assign('model', $data);
		$this->display();
	}

	/**
	 * 导出雁会名单
	 */
	public function exportMeetingAction(){

		$id = I('id');
		$meeting = M('YhMeeting')->field('id,title,parameter,is_check')->where(array('id'=>array('eq',$id)))->find();
		if (empty($meeting)){
			exit;
		}
		$file_name = $meeting['title']."名单.xlsx";

		$meeting['parameter'] = json_decode($meeting['parameter'],true);

		$columnArray = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');

		vendor('PHPExcel18.PHPExcel');
		$objPHPExcel = new \PHPExcel();
		$objPHPExcel->setActiveSheetIndex(0);
		foreach($meeting['parameter'] as $k=>$v){
			$objPHPExcel->getActiveSheet()->getColumnDimension($columnArray[$k])->setAutoSize(true);
			$objPHPExcel->getActiveSheet()->setCellValue($columnArray[$k]."1",$v['name']);
		}
		$objPHPExcel->getActiveSheet()->getColumnDimension($columnArray[count($meeting['parameter'])])->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->setCellValue($columnArray[count($meeting['parameter'])]."1","审核状态");

		$list = M("YhMeetingList")->where(array('meeting_id'=>array('eq',$meeting['id'])))->field("id,parameter,is_passed")->select();
		$startRow = 2;
		foreach ($list as $value){
			$value['parameter'] = json_decode($value['parameter'],true);
			foreach($value['parameter'] as $key=>$item){
				$objPHPExcel->getActiveSheet()->setCellValue($columnArray[$key].$startRow,$item["parameter_value"]);
			}

			$status = '待审核';
			if ($meeting['is_check'] == 0){
				$status = '免审核';
			} elseif ($value['is_passed'] == 1){
				$status = '已通过';
			} elseif ($value['is_passed'] == 2){
				$status = '已拒绝';
			}

			$objPHPExcel->getActiveSheet()->setCellValue($columnArray[count($meeting['parameter'])].$startRow,$status);
			$startRow++;
		}
		$userBrowser = $_SERVER['HTTP_USER_AGENT'];
		if (preg_match('/MSIE/i', $userBrowser)) {
			$file_name = urlencode($file_name);
		}
		$file_name = iconv('UTF-8', 'GBK//IGNORE', $file_name);
		$this->setExcelHeader($file_name);
		$objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
		$objWriter->save('php://output');
		unset($objWriter);
		unset($objPHPExcel);
	}
	
	/**
	 * 名单列表
	 */
	public function applyListAction()
	{
		$meeting_id = I('id');

		$meeting = M('YhMeeting')->find($meeting_id);

		$userList = M('YhMeetingList')->where(array('meeting_id'=>array('eq',$meeting_id)))->select();

		foreach($userList as $key=>$value){
			if ($meeting['is_check'] == 0){
				$userList[$key]['status'] = 3;
				$userList[$key]['status_name'] = '免审核';
			} elseif ($value['is_passed'] == 1){
				$userList[$key]['status'] = 1;
				$userList[$key]['status_name'] = '已通过';
			} elseif ($value['is_passed'] == 2){
				$userList[$key]['status'] = 2;
				$userList[$key]['status_name'] = '已拒绝';
			} else {
				$userList[$key]['status'] = 0;
				$userList[$key]['status_name'] = '待审核';
			}
			$parameter = json_decode($value['parameter'],true);
			$userList[$key]['user_name'] = $parameter[0]['parameter_value'];
			$userList[$key]['user_mobile'] = $parameter[1]['parameter_value'];
			$userList[$key]['apply_time'] = $value['apply_time'] ? date('Y-m-d H:i',$value['apply_time']) : '';
			$userList[$key]['update_time'] = $value['update_time'] ? date('Y-m-d H:i',$value['update_time']) : '';
		}

		if (IS_GET){
			$this->assign('model',$meeting);
			$this->assign('userList',$userList);
			$this->display();
		} else {
			$this->ajaxReturn($userList);
		}


	}

	/**
	 * 名单列表
	 */
	public function getApplyListAction()
	{
		$meeting_id = I('id');

		$meeting = M('YhMeeting')->find($meeting_id);

		$userList = M('YhMeetingList')->where(array('meeting_id'=>array('eq',$meeting_id)))->select();

		foreach($userList as $key=>$value){
			if ($meeting['is_check'] == 0){
				$userList[$key]['status'] = 3;
				$userList[$key]['status_name'] = '免审核';
			} elseif ($value['is_passed'] == 1){
				$userList[$key]['status'] = 1;
				$userList[$key]['status_name'] = '已通过';
			} elseif ($value['is_passed'] == 2){
				$userList[$key]['status'] = 2;
				$userList[$key]['status_name'] = '已拒绝';
			} else {
				$userList[$key]['status'] = 0;
				$userList[$key]['status_name'] = '待审核';
			}
			$parameter = json_decode($value['parameter'],true);
			$userList[$key]['user_name'] = $parameter[0]['parameter_value'];
			$userList[$key]['user_mobile'] = $parameter[1]['parameter_value'];
			$userList[$key]['apply_time'] = $value['apply_time'] ? date('Y-m-d H:i',$value['apply_time']) : '';
			$userList[$key]['update_time'] = $value['update_time'] ? date('Y-m-d H:i',$value['update_time']) : '';
		}

		$this->ajaxReturn($userList);
	}

	/**
	 * 报名信息
	 */
	public function userInfoAction()
	{
		$id = I('id');
		$info = M('YhMeetingList')->find($id);
		$info['parameter'] = json_decode($info['parameter'],true);
		$this->assign('model',$info);
		$this->display();
	}
	
	/**
	 * 添加活动直播记录
	 */
	public function addLiveAction()
	{
		try{

			$data['meeting_id'] = I('meeting_id');
			$data['user_id'] = $this->userId;
			$data['type'] = I('type',1);
			$data['create_time'] = time();
			if ($_FILES){
				$config = C("Storage");
				$upload = new \Think\Upload($config);

				$lists = $upload->upload();
				pr($lists);die;
				if ($lists){
					foreach($lists as $val){

					}
				}
			}

			$data['content'] = I('content');




		    $this->ajaxReturn(buildMessage());
		} catch (Exception $e) {
		    $this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}
	}
}