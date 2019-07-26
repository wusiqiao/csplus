<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/05/29
 * Time: 11:57
 */
namespace EShop\Model;


class WrkTaskPlanModel extends ComplexDataModel {

	protected $_createrField = "creater_id"; //创建人字段，如果是客户，可以设置成客户对应的字段
	protected $_leaderField = "leader_id";//负责人字段
	protected $_visiblersField = "visiblers"; //可见人字段
	protected $_collaboratorsField = "collaborators"; //协作人，如果原有此字段，就设置，没有就不需要

	/**
	 * @param $data
	 * @param $options
	 * 新增成功后给客户发送开始任务的进度
	 */
	protected function _after_insert($data,$options) {
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