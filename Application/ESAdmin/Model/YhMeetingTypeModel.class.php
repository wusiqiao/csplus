<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/07/11
 * Time: 16:09
 */
namespace ESAdmin\Model;



use Common\Lib\Model\DataModel;
use Think\Model;

class YhMeetingTypeModel extends DataModel{

	protected $_validate = array(
		array('name','require','类型名不能为空!'),
		array('name','','类型名已经存在!',0,'unique',1),
		array('name','checkUpdateName','类型名已经存在',0,'callback',2),
	);

	public function checkUpdateName(){
		$condition['id'] = array('neq',I('id'));
		$condition['name'] = array('eq',I('value'));

		$isHave = $this->where($condition)->find();
		if ($isHave){
			return false;
		}
	}
}