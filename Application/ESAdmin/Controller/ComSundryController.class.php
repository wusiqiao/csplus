<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class  ComSundryController extends DataController {
	//查询借出中、未借出、已归还的物品
   	public function  KeyNameList1Action($status = null,$customer_company = null) {
      $condition =[];
   		if ($status==0) {
   			$condition["a.status"] = 0;
   		}elseif($status==1) {
        $condition["a.status"] = array("in",[1,2]);
      }else{
        $condition["a.status"] = array("in",[0,1,2]);
      }
      if (!empty($customer_company)) {
        $condition["a.customer_company"] = $customer_company;
      }

   		$condition["a.branch_id"] = $this->_user_session->currBranchId;
  		$fields = "a.id as value,a.name as text";
  		$list = D("ComSundry")->setDacFilter("a")->where($condition)->field($fields)->select();
  		$this->ajaxReturn($list);
    }

    protected function _before_add(&$data) {
      $data["no"] = $this->getMaxNoByUserBranch();
      $data["branch"] = D("SysBranch")->where(["id"=>$this->_user_session->currBranchId])->field("id as value,name as text")->find();
      parent::_before_add($data);
    }

    // protected function _before_write($type, &$data) {
    //   $data["order_no"] = $this->getMaxBillNoByUserBranch("borrow_date","no");
    //   parent::_before_write($type, $data);
    // }
}