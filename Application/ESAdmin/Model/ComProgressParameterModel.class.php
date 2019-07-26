<?php
/**
 * Created by PhpStorm.
 * User: wusiqiao
 * Date: 2019/05/22
 * Time: 14:49
 */

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class ComProgressParameterModel extends DataModel {

	protected $_validate = array(
		array('branch_id','require','商户ID不能为空！'),
		array('progress_type_name','require','进度类型不能为空！'),
		array('progress_situation','require','进度详情不能为空！'),
	);


	public function initWxMbxx($branch_id){
		//删除已有的
		$data = array();

		$xxarray = C('WXMBXX.YSXX');

		foreach ($xxarray as $item){
			$info = $this->where(array('branch_id'=>array('eq',$branch_id),
				'progress_type_name'=>array('eq',$item['progress_type_name']),
				'is_system'=>array('eq',2)))
				->find();
			if (empty($info)){
				$data[] = array(
					'branch_id'=>$branch_id,
					'is_system'=>2,
					'progress_type_name'=>$item['progress_type_name'],
					'progress_situation'=>$item['progress_situation'],
					'extended_parameter'=>json_encode($item['extended_parameter']),
				);
			}
		}
		if (!empty($data)){
			$this->addAll($data);
		}
	}

}
