<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/05/22
 * Time: 14:56
 */
namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;
use Think\Exception;

class ComProgressParameterController extends DataController {

	protected function _before_write($type, &$data) {
		parent::_before_write($type, $data);
		$data["extended_parameter"] = json_encode(I("post.extended_parameter"));
	}

	protected function _before_display_dataview(&$data) {
		$data['extended_parameter'] = json_decode($data['extended_parameter'],true);
	}

	protected function _parseOrder(&$order) {
		$order = array('a.id DESC');
	}

	protected function _parsefilter(&$filter) {
		$filter = array('a.branch_id'=>$this->_user_session->currBranchId,'a.is_system'=>I('is_system'));
	}

	/**
	 * 添加系统消息
	 */
	public function indexAction()
	{
		$this->addXt();

		$list = D(CONTROLLER_NAME)->alias('a')
			->field('a.id,a.progress_type_name,a.is_system')
			->where(array('a.branch_id'=>$this->_user_session->currBranchId,'a.is_system'=>1))
			->select();
		$this->assign('templateList',json_encode($list));
		$this->display();
	}

	/**
	 * getInfo
	 */
	public function getInfoAction()
	{
		try{
			$id = I('id');
			if (empty($id)){
				throw new Exception('请选择设置项');
			}
			$data = D(CONTROLLER_NAME)->find($id);
			if ($data){
				$data['extended_parameter'] = json_decode($data['extended_parameter'],true);
			}
			$this->ajaxReturn(buildResult($data));
		} catch (Exception $e){
			$this->ajaxReturn(buildErrorMessage($e->getMessage()));
		}
	}

	/**
	 * 同步系统消息
	 */
	protected function addXt()
	{
		//删除已有的
		$data = array();

		$xxarray = C('WXMBXX.XTXX');

		foreach ($xxarray as $item){
			$info = D(CONTROLLER_NAME)->where(array('branch_id'=>array('eq',$this->_user_session->currBranchId),
				'progress_type_name'=>array('eq',$item['progress_type_name']),
				'is_system'=>array('eq',1)))
				->find();
			if (empty($info)){
				$data[] = array(
					'branch_id'=>$this->_user_session->currBranchId,
					'is_system'=>1,
					'progress_type_name'=>$item['progress_type_name'],
					'progress_situation'=>$item['progress_situation'],
					'extended_parameter'=>json_encode($item['extended_parameter']),
				);
			}
		}
		if (!empty($data)){
			D(CONTROLLER_NAME)->addAll($data);
		}
	}
	
	/**
	 * 同步已有的商户的微信模板消息
	 */
	public function tbwxmbxxAction()
	{
		$allshs = M('SysBranch')->field('id')->where(array('type'=>array('eq',0),'parent_id'=>array('eq',1)))->select();

		foreach($allshs as $sh){
			D(CONTROLLER_NAME)->initWxMbxx($sh['id']);
		}

		echo '同步完成';
	}

}