<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/07/15
 * Time: 14:41
 */
namespace EShop\Controller;


use Think\Exception;

class YhMeetingController extends BaseController{

	/**
	 * 活动列表
	 */
	public function indexAction()
	{
		$this->assign('title','第一层列表页面');

		$where['a.is_delete'] = array('eq',0);
		$where['a.is_drafts'] = array('eq',0);

		$data = array();

		$where['a.status'] = 1;
		$data['status_one'] = M('YhMeeting')->alias('a')
			->field('a.id,a.title,a.image,a.start_time,a.end_time,a.address,count(yml.id) as num')
			->join('INNER JOIN yh_meeting_list as yml ON yml.meeting_id = a.id AND yml.is_passed = 1')
			->where($where)
			->group('a.id')
			->order('id DESC')
			->limit(2)
			->select();
		$where['a.status'] = 2;
		$data['status_two'] = M('YhMeeting')->alias('a')
			->field('a.id,a.title,a.image,a.start_time,a.end_time,a.address,count(yml.id) as num')
			->join('INNER JOIN yh_meeting_list as yml ON yml.meeting_id = a.id AND yml.is_passed = 1')
			->where($where)
			->group('a.id')
			->order('id DESC')
			->limit(2)
			->select();
		$where['a.status'] = 3;
		$data['status_three'] = M('YhMeeting')->alias('a')
			->field('a.id,a.title,a.image,a.start_time,a.end_time,a.address,count(yml.id) as num')
			->join('INNER JOIN yh_meeting_list as yml ON yml.meeting_id = a.id AND yml.is_passed = 1')
			->where($where)
			->group('a.id')
			->order('id DESC')
			->limit(2)
			->select();
		$where['a.status'] = 4;
		$data['status_four'] = M('YhMeeting')->alias('a')
			->field('a.id,a.title,a.image,a.start_time,a.end_time,a.address,count(yml.id) as num')
			->join('INNER JOIN yh_meeting_list as yml ON yml.meeting_id = a.id AND yml.is_passed = 1')
			->where($where)
			->group('a.id')
			->order('id DESC')
			->limit(2)
			->select();
		$where['a.status'] = 5;
		$data['status_five'] = M('YhMeeting')->alias('a')
			->field('a.id,a.title,a.image,a.start_time,a.end_time,a.address,count(yml.id) as num')
			->join('INNER JOIN yh_meeting_list as yml ON yml.meeting_id = a.id AND yml.is_passed = 1')
			->where($where)
			->group('a.id')
			->order('a.id DESC')
			->limit(2)
			->select();

		$this->assign('model',$data);


		$typeList = M('YhMeetingType')->select();

		$this->assign('typeList',$typeList);

		$this->display();
	}

	/**
	 * 各个状态下的列表
	 */
	public function statusIndexAction()
	{
		$this->assign('title','第二层列表页面');
		$p = I('p',1);
		$where['a.is_delete'] = array('eq',0);
		$where['a.is_drafts'] = array('eq',0);
		$where['a.status'] = array('eq',I('status'));
		if (I('type_id')){
			$where['a.type_id'] = array('eq',I('type_id'));
		}

		if (I('keywork')){
			$where['a.title'] = array('like','%'.I('keywork').'%');
		}

		$list = M('YhMeeting')->alias('a')
			->field('a.id,a.title,a.image,a.start_time,a.end_time,a.address,count(yml.id) as num')
			->join('INNER JOIN yh_meeting_list as yml ON yml.meeting_id = a.id AND yml.is_passed = 1')
			->where($where)
			->group('a.id')
			->order('a.id DESC')
			->page($p,10)
			->select();

		$typeList = M('YhMeetingType')->select();

		$this->assign('typeList',$typeList);

		if (IS_AJAX){
			$this->ajaxReturn($list);
		} else {
			$this->assign('model',$list);
			$this->display();
		}

	}

	/**
	 * 获取协会活动详细信息
	 */
	public function detailAction()
	{
		$this->assign('title','活动详情');
		$id = I('id');

		$info = M('YhMeeting')->alias('a')
			->field('a.id,a.title,a.image,a.start_time,a.end_time,a.address,a.content,count(yml.id) as num')
			->join('INNER JOIN yh_meeting_list as yml ON yml.meeting_id = a.id AND yml.is_passed = 1')
			->where(array('a.id'=>array('eq',$id)))
			->find();
		$info['is_join'] = 0;
		$info['join_parameter'] = array();
		$is_join = M('YhMeetingList')->where(array('meeting_id'=>array('eq',$id),'user_id'=>array('eq',$this->userId),'is_passed'=>array('eq',1)))->find();
		if ($is_join){
			$info['is_join'] = 1;
			$info['join_parameter'] = json_decode($is_join['parameter'],true);
		}
		pr($info);

		$this->assign('model',$info);
		$this->display();
	}

	/**
	 * 报名活动
	 * 获取报名协会活动需要填写的参数
	 */
	public function applyViewAction()
	{
		$this->assign('title','报名活动');
		$id = I('id');
		$info = D('YhMeeting')
			->field('id,parameter')
			->where(array('id'=>array('eq',$id)))
			->find();
		$info['parameter'] = json_decode($info['parameter'],true);
		$this->assign('model',$info);
		$this->display();
	}

	/**
	 * 申请报名活动
	 */
	public function doApplyAction()
	{
		try{
			$post = I('post.');
			$id = I('id');
			$info = D('YhMeeting')->where(array('id'=>array('eq',$id),'is_delete'=>array('eq',0)))->find();

			if (empty($info)){
				throw new Exception('该活动不存在！');
			}

//			if ($info['is_open'] == 0){
//				throw new Exception('该活动没开放报名');
//			}

			if ($info['is_close_apply'] == 1){
				throw new Exception('该活动已关闭报名！');
			}

			if (time()>$info['apply_end_time']){
				throw new Exception('已过报名时间！');
			}

			$is_has = M('YhMeetingList')->where(array('user_id'=>array('eq',$this->userId),'meeting_id'=>array('eq',$id)))->find();
			if ($is_has){
//				throw new Exception('该活动以报名！');
			}

			$data['meeting_id'] = $id;
			$data['user_id'] = $this->userId;
			$parameters = array();
			if ($post['names']){
				foreach ($post['names'] as $key=>$name){
					$parameters[] = array('parameter_name'=>$name,'parameter_value'=>$post['values'][$key]);
				}
			}
			$data['parameter'] = json_encode($parameters);
			$data['apply_time'] = time();

			$data['is_passed'] = 0;
			if ($info['is_check'] == 0){
				$data['is_passed'] = 1;
				$data['update_time'] = time();
				// TODO:客户接受审核成功结果，url到活动详情页面;
			} else {
				// TODO:商户接受审核结果，url到活动详情页面;
			}

			D('YhMeetingList')->add($data,array('callback'=>true));

		    $this->ajaxReturn(buildMessage());
		} catch (Exception $e) {
		    $this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}
	}

	public function myIndexAction()
	{
		$this->display();
	}
	public function liveViewAction()
	{
		$this->display();
	}
	public function pictureViewAction()
	{
		$this->display();
	}
	public function applyInfoAction()
	{
		$this->display();
	}
}