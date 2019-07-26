<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/05/29
 * Time: 11:57
 */
namespace ESAdmin\Model;


use Common\Lib\Model\ComplexDataModel;

class WrkTaskPlanModel extends ComplexDataModel {

	protected $_createrField = "creater_id"; //创建人字段，如果是客户，可以设置成客户对应的字段
	protected $_leaderField = "leader_id";//负责人字段
	protected $_visiblersField = "visiblers"; //可见人字段
	protected $_collaboratorsField = "collaborators"; //协作人，如果原有此字段，就设置，没有就不需要

	protected $_link = array(
		"WrkAgreement" => array(
			"join_name" => "INNER",
			'class_name' => "WrkAgreement",
			'foreign_key' => 'contract_id',
			'mapping_name' => 'agreement',
			'mapping_fields' => 'name,sys_sn',
			"mapping_key" => "id"
		),
		"Company" => array(
			"join_name" => "LEFT",
			'class_name' => "SysBranch",
			'foreign_key' => 'company_id',
			'mapping_name' => 'company',
			'mapping_fields' => 'name',
			"mapping_key" => "id"
		),
		"SysUser" => array(
			"join_name" => "LEFT",
			'class_name' => "SysUser",
			'foreign_key' => 'leader_id',
			'mapping_name' => 'leader',
			'mapping_fields' => 'name',
			"mapping_key" => "id"
		)
	);

	protected $_validate = array(
		array('contract_id','require','合同必选！'),
		array('task_name','require','任务名不能为空！'),
		array('task_name','checkTaskName','合同任务名重复',0,'callback',1),
		array('task_name','checkUpdateTaskName','合同任务名重复',0,'callback',2),
	);

	public function checkTaskName(){
		$condition['contract_id'] = array('eq',I('contract_id'));
		$condition['task_name'] = array('eq',I('task_name'));

		$isHave = $this->where($condition)->find();
		if ($isHave){
			return false;
		}
	}

	public function checkUpdateTaskName(){
		$condition['id'] = array('neq',I('id'));
		$condition['contract_id'] = array('eq',I('contract_id'));
		$condition['task_name'] = array('eq',I('task_name'));

		$isHave = $this->where($condition)->find();
		if ($isHave){
			return false;
		}
	}


	/**
	 * @param $data
	 * @param $options
	 * 新增成功后给客户发送开始任务的进度
	 */
	protected function _after_insert($data,$options) {
		parent::_after_insert($data, $options);
		$item['contract_id'] = $data['contract_id'];
		$item['task_plan_id'] = $data['id'];
		$item['branch_id'] = $data['branch_id'];
		$item['company_id'] = $data['company_id'];
		$item['create_id'] = $data['creater_id'];
		$item['create_time'] = time();
		$item['create_type'] = 1;
		$item['progress_type_name'] = '服务已启动';
		$item['progress_situation'] = '您好！您的服务已正式启动，请点击详情查看。';

		$item['receiver_id'] = M('SysUserModuleSetting')
			->where(array(
				'branch_id' => array('eq',$item['branch_id']),
				'company_id' => array('eq',$item['company_id']),
				'module' => array('eq','WrkTaskPlan'),
				'permit_value' => array('eq',DAC_PERMIT_VALUE_NOTICER),
				'type' => array('eq',DAC_SETTING_TYPE_CUSTOMER)
			))
			->getField('user_id');
		$item['is_sure'] = 0;

		$itemDate = D('WrkTaskItem')->create($item);
		if ($itemDate){
			D('WrkTaskItem')->add($itemDate,array("callback"=>true));
		}

	}



}