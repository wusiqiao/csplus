<?php
/**
 * Created by PhpStorm.
 * User: wusiqiao
 * Date: 2019/6/9
 * Time: 19:29
 */

namespace EShop\Model;


class WrkTaskItemModel extends DataModel {

    protected $_validate = array(
        array('contract_id','require','合同错误！'),
        array('task_plan_id','require','合同任务错误！'),
        array('branch_id','require','商户错误！'),
        array('company_id','require','客户错误！'),
        array('progress_type_name','require','提报进度不能留空！'),
        array('progress_situation','require','提报内容不能留空！'),
    );

	protected function _after_insert($data,$options) {

		//$获取合同任务信息
		$wrkTaskPlan = D('WrkTaskPlan')->alias('a')
			->field('wa.sys_sn,a.task_name,a.is_to_customer,a.leader_id')
			->join('INNER JOIN wrk_agreement AS wa ON wa.id = a.contract_id')
			->where(array('a.id'=>array('eq',$data['task_plan_id'])))
			->find();

		if ($data['progress_type_name'] == '服务结束'){
			$data['progress_type_name'] .= '待确认';
		}

		D('WrkTaskPlan')->where(array('id'=>array('eq',$data['task_plan_id'])))->setField(array('update_content'=>$data['progress_type_name'],'update_time'=>time()));

		//发送给商户通知人
		$notifier_id = M('SysUserModuleSetting')
			->where(array(
				'branch_id' => array('eq',$data['branch_id']),
				'company_id' => array('eq',$data['company_id']),
				'module' => array('eq','WrkTaskPlan'),
				'permit_value' => array('eq',DAC_PERMIT_VALUE_NOTICER),
				'type' => array('eq',DAC_SETTING_TYPE_BRANCH)
			))
			->getField('user_id');



		if ($data['progress_type_name'] == '沟通反馈'){
			$data['progress_situation'] = getDescribeByTitle($data['branch_id'],'服务反馈通知');
			$data['progress_situation'] = $data['progress_situation'] ? $data['progress_situation'] : '您好，您的服务有了新的服务反馈，请点击详情查看。';
		}
		$itemcount = $this->where(array('task_plan_id'=>array('eq',$data['task_plan_id'])))->count();
		if ($itemcount == 1){
			$data['progress_situation'] = getDescribeByTitle($data['branch_id'],'服务开始通知');
			$data['progress_situation'] = $data['progress_situation'] ? $data['progress_situation'] : '您好！您的服务已正式启动，请点击详情查看。';
			$url = str_replace('shop','shop'.getBrowseBranchId(),SHOP_ROOT).'/WrkTaskPlan/scheduleInfo/id/'.$data['id'];
			send_one_wx_message($wrkTaskPlan['leader_id'],$url,'您好，您有一个新的任务计划需要跟进，请点击详情查看!',$wrkTaskPlan['sys_sn'],$wrkTaskPlan['task_name']);
		}

		if ($notifier_id){
			$url = str_replace('shop','shop'.getBrowseBranchId(),SHOP_ROOT).'/WrkTaskPlan/scheduleInfo/id/'.$data['id'];
			send_one_wx_message($notifier_id,$url,$data['progress_situation'],$wrkTaskPlan['sys_sn'],$wrkTaskPlan['task_name']);
		}

		if ($data['create_type'] == 2){
			$url = str_replace('shop','shop'.getBrowseBranchId(),SHOP_ROOT).'/WrkTaskPlan/scheduleInfo/id/'.$data['id'];
			send_one_wx_message($wrkTaskPlan['leader_id'],$url,$data['progress_situation'],$wrkTaskPlan['sys_sn'],$wrkTaskPlan['task_name']);
		} elseif ($data['create_type'] == 1 && $wrkTaskPlan['is_to_customer'] == 1){
			$cuser_ids = M('SysUserModuleSetting')
				->field('user_id')
				->where(array(
					'branch_id' => array('eq',$data['branch_id']),
					'company_id' => array('eq',$data['company_id']),
					'module' => array('eq','WrkTaskPlan'),
					'permit_value' => array('eq',DAC_PERMIT_VALUE_NOTICER),
					'type' => array('eq',DAC_SETTING_TYPE_CUSTOMER)
				))
				->select();
			if (empty($cuser_ids)){
				$cuser_ids = M('SysBranch')->field('customer_leader_id as user_id')->where(array('id'=>array('eq',$data['company_id'])))->select();
			}

			if ($cuser_ids){
				$url = str_replace('shop','shop'.getBrowseBranchId(),SHOP_ROOT).'/ComWrkTaskPlan/scheduleInfo/id/'.$data['id'];
				foreach($cuser_ids as $cuser){
					send_one_wx_message($cuser['user_id'],$url,$data['progress_situation'],$wrkTaskPlan['sys_sn'],$wrkTaskPlan['task_name']);
				}
			}
		}
	}


	protected function _after_update($data,$options) {
		//$获取合同任务信息
		$wrkTaskPlan = D('WrkTaskPlan')->alias('a')
			->field('wa.sys_sn,a.task_name,a.is_to_customer,a.leader_id')
			->join('INNER JOIN wrk_agreement AS wa ON wa.id = a.contract_id')
			->where(array('a.id'=>array('eq',$data['task_plan_id'])))
			->find();

		D('WrkTaskPlan')->where(array('id'=>array('eq',$data['task_plan_id'])))->setField(array('update_time'=>time()));

		//发送给商户通知人
		$notifier_id = M('SysUserModuleSetting')
			->where(array(
				'branch_id' => array('eq',$data['branch_id']),
				'company_id' => array('eq',$data['company_id']),
				'module' => array('eq','WrkTaskPlan'),
				'permit_value' => array('eq',DAC_PERMIT_VALUE_NOTICER),
				'type' => array('eq',DAC_SETTING_TYPE_BRANCH)
			))
			->getField('user_id');

		$progress_situation = $data['progress_situation'];
		if ($data['sure_time'] > 0){
			$progress_situation = $data['progress_type_name'].'已确认！';
			if($data['progress_type_name'] == '服务结束'){
				D('WrkTaskPlan')->where(array('id'=>array('eq',$data['task_plan_id'])))->setField('status',3);
				D('WrkTaskPlan')->where(array('id'=>array('eq',$data['task_plan_id'])))->setField('o_status',3);
			}
		}
		if ($data['rejected_time'] > 0){
			$progress_situation = $data['progress_type_name'].'已驳回！';
			D('WrkTaskPlan')->where(array('id'=>array('eq',$data['task_plan_id'])))->setField(array('update_content'=>$progress_situation,'update_time'=>time()));
		}

		if ($notifier_id){
			$url = str_replace('shop','shop'.getBrowseBranchId(),SHOP_ROOT).'/WrkTaskPlan/scheduleInfo/id/'.$data['id'];
			send_one_wx_message($notifier_id,$url,$progress_situation,$wrkTaskPlan['sys_sn'],$wrkTaskPlan['task_name']);
		}

		$url = str_replace('shop','shop'.getBrowseBranchId(),SHOP_ROOT).'/WrkTaskPlan/scheduleInfo/id/'.$data['id'];
		send_one_wx_message($wrkTaskPlan['leader_id'],$url,$progress_situation,$wrkTaskPlan['sys_sn'],$wrkTaskPlan['task_name']);
	}



}