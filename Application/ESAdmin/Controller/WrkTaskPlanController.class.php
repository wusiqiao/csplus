<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/05/28
 * Time: 17:26
 */

namespace ESAdmin\Controller;


use Common\Lib\Controller\ComplexDataController;
use Think\Exception;

class WrkTaskPlanController extends ComplexDataController {

	protected function _before_write($type, &$data) {
		parent::_before_write($type, $data);
		if (!I("post.is_to_customer")){
			$data["is_to_customer"] = 0;
		}
		if (self::ACTION_ADD === $type) {
			$data['attach_group'] = genUniqidKey();
		}

		$data['collaborators'] = implode(',',$data['collaborators']);
		$data['visiblers'] = implode(',',$data['visiblers']);

	}

	/**
	 * 合同任务页
	 */
	public function taskListAction()
	{
		//合同详情
		$contract_id = I('contract_id');
		$agreementInfo = D('WrkAgreement')->alias('a')
			->field('a.id,a.name,a.branch_id,a.company_id,a.product_options,c.name AS company_name,cu.name AS customer_name')
			->join('INNER JOIN sys_branch AS c ON c.id = a.company_id')
			->join('LEFT JOIN sys_user_module_setting AS cums ON cums.company_id = a.company_id AND cums.module = "WrkTaskPlan" AND cums.branch_id = a.branch_id AND cums.permit_value = 1 AND cums.type = 0')
			->join('LEFT JOIN sys_user AS cu ON cu.id = cums.user_id')
			->where(array('a.id'=>$contract_id))
			->find();
		$agreementInfo['product_options'] = array_filter(json_decode($agreementInfo['product_options'],true));

		$customer_names = M('SysUserModuleSetting')->alias('cums')
			->field('cu.name AS customer_name')
			->join('LEFT JOIN sys_user AS cu ON cu.id = cums.user_id')
			->where('cums.company_id = '.$agreementInfo['company_id'].' AND cums.module = "WrkTaskPlan" AND cums.branch_id = '.$agreementInfo['branch_id'].' AND cums.permit_value = 1 AND cums.type = 0')
			->select();
		$agreementInfo['customer_name'] = '';
		foreach($customer_names as $customer_name){
			$agreementInfo['customer_name'] .= ','.$customer_name['customer_name'];
		}
		$agreementInfo['customer_name'] = trim($agreementInfo['customer_name'],',');
		if (!$agreementInfo['customer_name']){
			$customer_name = M('SysBranch')->alias('a')
				->field('u.name as customer_name')
				->join('LEFT JOIN sys_user as u ON u.id = a.customer_leader_id')
				->where(array('a.id'=>array('eq',$agreementInfo['company_id'])))
				->find();

			$agreementInfo['customer_name'] = $customer_name['customer_name'];
		}

		$this->assign('model',$agreementInfo);

		//合同任务列表
		$taskList = D(CONTROLLER_NAME)->alias('a')
			->field('a.id,a.task_name,a.status,sh.staff_name AS leader_name,a.is_to_customer')
			->join('LEFT JOIN sys_user AS sh ON sh.id = a.leader_id')
			->where(array('contract_id'=>$contract_id))
			->select();

		$this->assign('tasks',$taskList);

		$pv = D("WrkAgreement")->getPermitValue($contract_id);

		$this->assign('pv',$pv);

		$this->display();
	}

	protected function _getDetailData($id) {
		$record = array();

		if ($id){
			$condition["a.id"] = $id;
			$record = D(CONTROLLER_NAME)->setDacFilter("a")
				->relation(true)
				->field("a.*,sh.staff_name as service_man_name")
				->join('LEFT JOIN sys_user as sh ON sh.id = a.leader_id')
				->where($condition)
				->find();
		}
		$this->_before_detail($record);
		return $record;
	}

	public function detailAction()
	{
		$this->_before_detailAction();
		$id = I('id');
		$agreementInfo = D('WrkAgreement')->alias('a')
			->field('a.product_options')
			->join('INNER JOIN wrk_task_plan AS wtp ON wtp.contract_id = a.id')
			->where(array('wtp.id'=>$id))
			->find();
		$agreementInfo['product_options'] = json_decode($agreementInfo['product_options'],true);
		$this->assign('product_options',json_encode(array_filter($agreementInfo['product_options'])));
		parent::detailAction($id);
	}

	/**
	 * 添加任务
	 */
	public function addTaskAction()
	{
		$this->_before_addAction();
		$contract_id = I('contract_id');
		$agreementInfo = D('WrkAgreement')->field('id as contract_id,product_options,company_id')->where(array('id'=>$contract_id))->find();
		$agreementInfo['product_options'] = json_decode($agreementInfo['product_options'],true);
		$agreementInfo['is_to_customer'] = 1;

		$staff_name = M('SysUser')->where(array('id'=>array('eq',$this->userId)))->getField('staff_name');
		$agreementInfo['service_man_name'] = $staff_name;
		$agreementInfo['leader_id'] = $this->userId;
		$this->assign('model',$agreementInfo);
		$this->assign('product_options',json_encode(array_filter($agreementInfo['product_options'])));
		$this->display('edit');
	}

	protected function _parseOrder(&$order) {
		$order['update_time'] = '';
		parent::_parseOrder($order);
	}

	protected function _parsefilter(&$filter) {
		if (I('status')){
			$filter['a.status'] = array('eq',I('status'));
		}
		$filter['agreement.id'] = array('neq',0);
		if (I('keyword')){

				$filter["_string"] = "a.task_name like '%".I('keyword')."%' or agreement.name like '%".I('keyword')."%' or agreement.sys_sn like '%".I('keyword')."%'";

//			$where['a.task_name']  = array('like', '%'.I('keyword').'%');
//			$where['agreement.name']  = array('like', '%'.I('keyword').'%');
//			$where['agreement.sys_sn']  = array('like', '%'.I('keyword').'%');
//			$where['_logic']  = 'or';
//			$filter['_complex'] = $where;
		}
		parent::_parsefilter($filter);
	}

	protected function _before_list(&$list) {
		foreach ($list as $key=>$value){
			if (!empty($value['update_content'])){
				$list[$key]['update_time'] = date('Y-m-d H:i',$value['update_time']);
			} else {
				$list[$key]['update_time'] = '';
			}
		}
	}

	/**
	 * 合同任务进度
	 */
	public function scheduleAction()
	{
		$id = I('id');
		$agreementInfo = D('WrkAgreement')->alias('a')
			->field('a.id,a.name,a.branch_id,a.company_id,a.product_options,wtp.attach_group,c.name AS company_name,cu.name AS customer_name,wtp.status,wtp.update_content,wtp.id as task_plan_id,wtp.evaluation_num,wtp.evaluation_txt')
			->join('INNER JOIN sys_branch AS c ON c.id = a.company_id')
			->join('INNER JOIN wrk_task_plan AS wtp ON wtp.contract_id = a.id')
			->join('LEFT JOIN sys_user_module_setting AS cums ON cums.company_id = a.company_id AND cums.module = "WrkTaskPlan" AND cums.branch_id = a.branch_id AND cums.permit_value = 1 AND cums.type = 0')
			->join('LEFT JOIN sys_user AS cu ON cu.id = cums.user_id')
			->where(array('wtp.id'=>$id))
			->find();
		$agreementInfo['product_options'] = array_filter(json_decode($agreementInfo['product_options'],true));

		if ($agreementInfo['update_content'] == '服务结束待确认' && $agreementInfo['status'] != 3){
			$agreementInfo['status'] = 4;
		}

		$customer_names = M('SysUserModuleSetting')->alias('cums')
			->field('cu.name AS customer_name')
			->join('LEFT JOIN sys_user AS cu ON cu.id = cums.user_id')
			->where('cums.company_id = '.$agreementInfo['company_id'].' AND cums.module = "WrkTaskPlan" AND cums.branch_id = '.$agreementInfo['branch_id'].' AND cums.permit_value = 1 AND cums.type = 0')
			->select();
		$agreementInfo['customer_name'] = '';
		foreach($customer_names as $customer_name){
			$agreementInfo['customer_name'] .= ','.$customer_name['customer_name'];
		}
		$agreementInfo['customer_name'] = trim($agreementInfo['customer_name'],',');
		if (!$agreementInfo['customer_name']){
			$customer_name = M('SysBranch')->alias('a')
				->field('u.name as customer_name')
				->join('LEFT JOIN sys_user as u ON u.id = a.customer_leader_id')
				->where(array('a.id'=>array('eq',$agreementInfo['company_id'])))
				->find();

			$agreementInfo['customer_name'] = $customer_name['customer_name'];
		}

		$this->assign('model',$agreementInfo);
		$pv = $pv = D(CONTROLLER_NAME)->getPermitValue($id);
		$this->assign('pv',$pv);
		$this->display();
	}

	public function reportingAction()
	{
		$task_plan_id = I('task_plan_id');
		$agreementInfo = D('WrkAgreement')->alias('a')
			->field('a.id as contract_id,a.branch_id,a.company_id,wtp.attach_group,wtp.id as task_plan_id,wtp.task_name')
			->join('INNER JOIN sys_branch AS c ON c.id = a.company_id')
			->join('INNER JOIN wrk_task_plan AS wtp ON wtp.contract_id = a.id')
			->join('LEFT JOIN sys_user_module_setting AS cums ON cums.company_id = a.company_id AND cums.module = "WrkTaskPlan" AND cums.branch_id = a.branch_id AND cums.permit_value = 1 AND cums.type = 0')
			->join('LEFT JOIN sys_user AS cu ON cu.id = cums.user_id')
			->where(array('wtp.id'=>$task_plan_id))
			->find();

		$this->assign('model',$agreementInfo);


		$list = D('ComProgressParameter')->where(array('branch_id'=>array('eq',$this->companyId),'is_system'=>array('eq',2)))->select();


		foreach ($list as $key=>$value){
			$list[$key]['extended_parameter'] = json_decode($value['extended_parameter'],true);
		}

		$this->assign('optList',json_encode($list));

		$this->display();
	}

	/**
	 * 新增提报进度
	 */
	public function addScheduleAction()
	{
		try{
			$data = I('post.');

			$pv = D(CONTROLLER_NAME)->getPermitValue($data['task_plan_id']);
			if (!($pv == DAC_PERMIT_VALUE_LEADER || $pv == DAC_PERMIT_VALUE_COLLABORATOR)){
				throw new Exception('您不是此任务的负责人或协作人，没有权限新增！');
			}


			//合同信息
			if (empty($data['contract_id'])){
				throw new Exception('请选择合同');
			}
			$wrkAgreement = D('WrkAgreement')->field('name,sys_sn,is_task_plan')->where(array('id'=>array('eq',$data['contract_id'])))->find();
			if (empty($wrkAgreement)){
				throw new Exception('该合同不存在');
			}
			if ($wrkAgreement['is_task_plan'] == 2){
				throw new Exception('该合同冻结中');
			}
			if ($wrkAgreement['is_task_plan'] == 3){
				throw new Exception('该合同已结束');
			}

			//任务信息
			if (empty($data['task_plan_id'])){
				throw new Exception('请选择任务');
			}
			$wrkTaskPlan = D('WrkTaskPlan')->alias('a')
				->field('a.task_name,a.status,a.is_to_customer,cu.id as cuser_id')
				->join('LEFT JOIN sys_user_module_setting AS cums ON cums.branch_id = a.branch_id AND cums.company_id = a.company_id AND cums.module = "WrkTaskPlan"  AND cums.permit_value = 1 AND cums.type = 0')
				->join('LEFT JOIN sys_user AS cu ON cu.id = cums.user_id')
				->where(array('a.id'=>array('eq',$data['task_plan_id'])))
				->find();
			if (empty($wrkTaskPlan)){
				throw new Exception('该任务不存在');
			}
			if ($wrkTaskPlan['status'] == 2){
				throw new Exception('该任务冻结中');
			}
			if ($wrkTaskPlan['status'] == 3){
				throw new Exception('该任务已结束');
			}


			$data['create_id'] = $this->userId;
			$data['create_type'] = 1;
			$data['create_time'] = time();
			$data['receiver_id'] = $wrkTaskPlan['cuser_id'];
			$data['extended_parameter'] = array();
			foreach ($data['field'] as $key=>$value){
				if ($value && $data['value'][$key]){
					$data['extended_parameter'][] = array('field'=>$data['field'][$key],'value'=>$data['value'][$key]);
				} elseif ((empty($value) && $data['value'][$key]) || $value && empty($data['value'][$key])){
					throw new Exception('请完善拓展参数');
				}

			}

			$data['extended_parameter'] = json_encode($data['extended_parameter']);
			$data['images'] = $_POST['images'];
			$data['attachment'] = $_POST['attachment'];

			$item = D('WrkTaskItem')->create($data);
			if (!$item){
				throw new Exception(D('WrkTaskItem')->getError());
			}

			$results = D('WrkTaskItem')->add($item,array("callback"=>true));


		    $this->ajaxReturn(buildMessage($results));
		} catch (Exception $e) {
		    $this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}

	}



	/**
	 * 提报进度详情
	 */
	public function scheduleInfoAction()
	{
		$id = I('id');
		if (empty($id)){
			throw new Exception('请选择提报内容');
		}

		$info = D('WrkTaskItem')->alias('a')
			->field('sh.name as branch_name,wtp.task_name,wtp.attach_group,a.*,khu.name as receiver_name')
			->join('INNER JOIN sys_branch AS sh ON sh.id = a.branch_id')
			->join('INNER JOIN wrk_task_plan AS wtp ON wtp.id = a.task_plan_id')
			->join('LEFT JOIN sys_user AS khu ON khu.id = a.receiver_id')
			->where(array('a.id'=>array('eq',$id)))
			->find();

		$this->assign('images',json_encode($info['images']));
		$this->assign('rejected_images',json_encode($info['rejected_images']));

		$info['extended_parameter'] = json_decode($info['extended_parameter'],true);
		$info['images'] = json_decode($info['images'],true);
		$info['attachment'] = json_decode($info['attachment'],true);
		$info['rejected_images'] = json_decode($info['rejected_images'],true);
		$info['rejected_attachment'] = json_decode($info['rejected_attachment'],true);

		$this->assign('model',$info);



		$this->display('detail');
	}

	/**
	 * 提报进度列表
	 */
	public function scheduleListAction()
	{
		try{
			$page_index = I("page/d", 1);
			$page_size = I("rows/d", 1024);
			$task_plan_id = I('task_plan_id');


			if (empty($task_plan_id)){
				throw new Exception('请选择合同任务');
			}

			$condition['a.task_plan_id'] = array('eq',$task_plan_id);

			$count = D('WrkTaskItem')->setDacFilter("a")->where($condition)->count();

			$list = D('WrkTaskItem')->setDacFilter("a")
				->field("a.id,a.progress_type_name,a.progress_situation,creater.name as create_name,receiver.name as receiver_name,a.is_sure,a.create_type,a.create_time,a.sure_time,a.rejected_time")
				->join('LEFT JOIN sys_user AS creater ON creater.id = a.create_id')
				->join('LEFT JOIN sys_user AS receiver ON receiver.id = a.receiver_id')
				->where($condition)
				->order('a.id DESC')
				->page($page_index, $page_size)
				->select();

			foreach ($list as $key=>$value){
				$list[$key]['create_time'] = $value['create_time'] ? date('Y/m/d',$value['create_time']) : '';
				$list[$key]['sure_time'] = $value['sure_time'] ? date('Y/m/d',$value['sure_time']) : '';
				$list[$key]['rejected_time'] = $value['rejected_time'] ? date('Y/m/d',$value['rejected_time']) : '';
			}

			if (empty($list)){
				throw new Exception('没有更多了');
			}

		    $this->ajaxReturn(buildMessage($list));
		} catch (Exception $e) {
		    $this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}

	}

	/**
	 * 沟通反馈
	 */
	public function feedbackAction()
	{

		$task_plan_id = I('task_plan_id');
		$agreementInfo = D('WrkAgreement')->alias('a')
			->field('a.id as contract_id,a.branch_id,a.company_id,wtp.attach_group,wtp.id as task_plan_id')
			->join('INNER JOIN sys_branch AS c ON c.id = a.company_id')
			->join('INNER JOIN wrk_task_plan AS wtp ON wtp.contract_id = a.id')
			->join('LEFT JOIN sys_user_module_setting AS cums ON cums.company_id = a.company_id AND cums.module = "WrkTaskPlan" AND cums.branch_id = a.branch_id AND cums.permit_value = 1 AND cums.type = 0')
			->join('LEFT JOIN sys_user AS cu ON cu.id = cums.user_id')
			->where(array('wtp.id'=>$task_plan_id))
			->find();

		$this->assign('model',$agreementInfo);

		$this->display();
	}

	/**
	 * 改变任务状态
	 */
	public function statusAction()
	{
		try{
			$task_plan_id = I('task_plan_id');

			$pv = D(CONTROLLER_NAME)->getPermitValue($task_plan_id);
			if (!($pv == DAC_PERMIT_VALUE_LEADER || $pv == DAC_PERMIT_VALUE_COLLABORATOR)){
				throw new Exception('您不是此任务的负责人或协作人，没有该操作权限！');
			}

			$status = I('status');
			$taskPlan = D(CONTROLLER_NAME)->find($task_plan_id);

			if (empty($taskPlan)){
				throw new Exception('任务不存在');
			}

			if ($status != 3){
				D(CONTROLLER_NAME)->where(array('id'=>array('eq',$task_plan_id)))->setField('status',$status);
				D(CONTROLLER_NAME)->where(array('id'=>array('eq',$task_plan_id)))->setField('o_status',$status);
			}

			$item['contract_id'] = $taskPlan['contract_id'];
			$item['task_plan_id'] = $taskPlan['id'];
			$item['branch_id'] = $taskPlan['branch_id'];
			$item['company_id'] = $taskPlan['company_id'];
			$item['create_id'] = $this->userId;
			$item['create_time'] = time();
			$item['create_type'] = 1;
			$item['is_sure'] = 0;
			$item['receiver_id'] = M('SysUserModuleSetting')
				->where(array(
					'branch_id' => array('eq',$item['branch_id']),
					'company_id' => array('eq',$item['company_id']),
					'module' => array('eq','WrkTaskPlan'),
					'permit_value' => array('eq',DAC_PERMIT_VALUE_NOTICER),
					'type' => array('eq',DAC_SETTING_TYPE_CUSTOMER)
				))
				->getField('user_id');

			switch ($status){
				case 1:
					$item['progress_type_name'] = '服务解冻';
					$item['progress_situation'] = getDescribeByTitle($taskPlan['branch_id'],'服务解冻通知');
					$item['progress_situation'] = $item['progress_situation'] ? $item['progress_situation'] : '您好！您的服务已解冻，将继续为您服务。';
					break;
				case 2:
					$item['progress_type_name'] = '服务冻结';
					$item['progress_situation'] = getDescribeByTitle($taskPlan['branch_id'],'服务冻结通知');
					$item['progress_situation'] = $item['progress_situation'] ? $item['progress_situation'] : '您好！您的服务已被冻结，请联系您的服务会计人员。';
					break;
				case 3:
					$item['progress_type_name'] = '服务结束';
					$item['progress_situation'] = getDescribeByTitle($taskPlan['branch_id'],'服务结束通知');
					$item['progress_situation'] = $item['progress_situation'] ? $item['progress_situation'] : '您好！您的服务已结束，期待下次为您服务。';
					$item['is_sure'] = 1;
					break;
			}

			$itemDate = D('WrkTaskItem')->create($item);
			if ($itemDate){
				D('WrkTaskItem')->add($itemDate,array("callback"=>true));
			}

		    $this->ajaxReturn(buildMessage());
		} catch (Exception $e) {
		    $this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}
	}

	public function getPermitValueAction()
	{
		try{
			$id = I('id');
			$pv = D(CONTROLLER_NAME)->getPermitValue($id);
			if (!$pv){
				throw new Exception('您没有该数据的查看权限！');
			}
		    $this->ajaxReturn(buildMessage());
		} catch (Exception $e) {
		    $this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}
	}
}