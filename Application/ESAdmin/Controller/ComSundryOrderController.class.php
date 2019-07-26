<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class  ComSundryOrderController extends DataController {

    protected function _before_add(&$data) {
        $data["no"] = $this->getMaxBillNoByUserBranch("borrow_date","no");
        $data["status"] = 0;
        $data["borrower"] = $this->_user_session->userId;

        parent::_before_add($data);
    }

    protected function _before_list(&$list)
    {
        parent::_before_list($list);
        foreach($list as $k=>$v){
            $sundry_names = "";
            $items = D("ComSundryItem")->setDacFilter("a")->relation(true)->field("a.*")
            ->where(array("parent_id" => $v["id"]))->select();
            foreach ($items as $k1 => $v1) {
                if (empty($sundry_names)) {
                    $sundry_names = $v1['sundry_name'];
                } else {
                    $sundry_names = $sundry_names.",".$v1['sundry_name'];
                }
                
            }
            $list[$k]['sundry_names']  = $sundry_names;
        }
        // var_dump(expression);
    }

    protected function _before_detail(&$data)
    {
        parent::_before_detail($data);
        if ($data['type']==0) {
        	$data['items'] = D("ComSundryItem")->setDacFilter("a")->relation(true)->field("a.*")
            ->where(array("parent_id" => $data["id"]))->select();
            $condition["user_type"] = 4;
            $condition["branch_id"] = $data['customer_company'];
            $data['customer_list'] = D('SysUser')
            ->field("id as value,name as text")
            ->where($condition)
            ->select();

            $sundry_names = "";
            $items = $data['items'];
            foreach ($items as $k => $v) {
                if (empty($sundry_names)) {
                    $sundry_names = $v['sundry_name'];
                } else {
                    $sundry_names = $sundry_names.",".$v['sundry_name'];
                }      
            }
            $data['sundry_names']  = $sundry_names;
        }else{
        	$data['items'] = D("ComSundryItem")->setDacFilter("a")->relation(true)->field("a.*")
            ->where(array("return_order_id" => $data["id"]))->select();
        }  
    }

    protected function _before_write($type, &$data) {
        $data['borrow_date'] = date_create_from_format("Y/m/d",$data['borrow_date']);
		$data['borrow_date'] = date_format($data['borrow_date'],"Y-m-d");
        $data['borrow_date'] = strtotime($data['borrow_date']);
        $data['expected_return_date'] = date_create_from_format("Y/m/d",$data['expected_return_date']);
		$data['expected_return_date'] = date_format($data['expected_return_date'],"Y-m-d");
        $data['expected_return_date'] = strtotime($data['expected_return_date']);
        if (isset($data['return_date'])) {
        	$data['return_date'] = date_create_from_format("Y/m/d",$data['return_date']);
			$data['return_date'] = date_format($data['return_date'],"Y-m-d");
        	$data['return_date'] = strtotime($data['return_date']);
        }
        if (isset($data['company_cc_recipient'])) {
            $data['company_cc_recipient'] = implode(",",$data['company_cc_recipient']);
        }
        if (isset($data['customer_cc_recipient'])) {
            $data['customer_cc_recipient'] = implode(",",$data['customer_cc_recipient']);
        }
        parent::_before_write($type, $data);
    }  

    //查询客户公司
    public function branchListAction($selected = "", $term = "", $select_all = false) {
        $condition = $this->getChosenSearchCondition($selected, $term);
        $fields = "id as value,name as text";
        $condition["is_valid"] = 1;
        $condition["parent_id"] = $this->_user_session->currBranchId;
        $branch_list = D('SysBranch')

        ->callFilter(true)
        ->field($fields)
        ->where($condition)
        ->order("code")
        ->select();

        $this->ajaxReturn($branch_list);
    }
    //查询客户
    public function customerListAction($selected = "", $term = "", $select_all = false,$branch_id = null) {
        // $condition = $this->getChosenSearchCondition($selected, $term);
        $fields = "a.id as value,a.name as text";
        // $condition["user_type"] = 4;
        $condition["b.branch_id"] = $branch_id;
        // $condition['name'] = array(['exp',' is not NULL'],['exp','<> ""']);
        $branch_list = D('SysUser')
        // ->callFilter(true)
        ->alias('a')
        ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
        ->field($fields)
        ->where($condition)
        ->select();
        // return $branch_list;
        $this->ajaxReturn($branch_list);
    }

    public function userListAction($selected = "", $term = "", $select_all = false,$branch_id = null) {
        // $condition = $this->getChosenSearchCondition($selected, $term);
        $fields = "id as value,name as text";
        $condition["user_type"] = array('in',['2','3']);
        $condition["branch_id"] =  $this->_user_session->currBranchId;
        // $condition['name'] = array(['exp',' is not NULL'],['exp','<> ""']);
        $branch_list = D('SysUser')
        // ->callFilter(true)
        // ->alias('a')
        // ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
        ->field($fields)
        ->where($condition)
        ->select();
        // return $branch_list;
        $this->ajaxReturn($branch_list);
    }    
    //查询客户ajax页面
    // public function customerSelectAction($branch_id = null) {
    //     $this->branch_id = $branch_id;
    //     $this->display('customerSelect');
    // }
    //发起借出申请
    public function applyAction($id){
        $data["status"] = 1;
        $model = M("ComSundryOrder");
        try {
            $model->startTrans();
            $num = $model->where(['id'=>$id])->save($data);
            $model->commit();
        } catch (Think\Exception $ex) {
            $model->rollback();
            $num = false;
        }

        if($num){
            $comSundryOrder = $model->where(['id'=>$id])->find();
            $condition['parent_id'] = $id;
            $item_data = D("ComSundryItem")->setDacFilter("a")->relation(true)->field("a.*")->where($condition)->select();
            $sundry_names ="";
            foreach ($item_data as $k => $v) {
                if (empty($sundry_names)) {
                    $sundry_names = $v['sundry_name'];
                }else{
                    $sundry_names = $sundry_names.",".$v['sundry_name'];
                }
            }
            $record_data = array(
                    "parent_id" => $v['parent_id'],
                    "type" => 1,
                    "sundry_names" => $sundry_names,
                    "branch_id" =>$this->_user_session->currBranchId,
                    "user_id"=>$comSundryOrder['borrower'],
                    "date"=> time(),
                    "created_at"=> time()
                );
            $record_id = D("ComSundryRecord")->add($record_data);
            // $rst = 
            D("ComSundryOrder")->sendBorrowApplyWXMessage($id,$record_id);
            // var_dump($rst);
            $this->ajaxReturn(array('error'=>0,'message'=>'申请成功!!'));
        }else{
            $this->ajaxReturn(array('error'=>1,'message'=>'申请失败!!'));
        }
    }

    //查看借用记录
    public function recordAction($id){
        $data = array();
        $show_status = ["借用中","已归还"];
            if ($id){
                $data = D('ComSundryOrder')->setDacFilter("a")->relation(true)->field("a.*")->where(["a.id" => $id])->find();
                $items = D('ComSundryItem')->setDacFilter("a")->relation(true)->field("a.*")->where(array("parent_id" => $id))->select();
                foreach ($items as $k => $v) {
                    $items[$k]['show_status'] = $show_status[$v['status']];
                }
                $data['items'] = $items;

                $condition['parent_id'] = $id;
                $condition['type'] = array("in",[1,2,3,4,5,9]);
                $rst = D('ComSundryRecord')->setDacFilter("a")->relation(true)->field("a.*")->where($condition)->order('created_at desc')->select();
                $i = 'active';
                foreach ($rst as $k => $v) {
                    $rst[$k]['active'] = $i;
                    $rst[$k]['date'] = date('Y/m/d',$v['date']);
                    $rst[$k]['created_at'] = date('Y/m/d H:i:s',$v['created_at']);
                    $i='';
                }
                $data['records'] = $rst;
            }
        $this->assign('model',$data);
        $this->display('record');
    }

    //发起归还
    public function returnAction($id){
        $post_data = I('post.data');
        $model = D("ComSundryOrder");
        if ($data = $model->create($post_data)) { 
        	unset($data['item_ids']);
        	unset($data['sundry_ids']);
        	$data["branch_id"] = $this->_user_session->currBranchId;
    		$data['return_date'] = date_create_from_format("Y/m/d",$data['return_date']);
			$data['return_date'] = date_format($data['return_date'],"Y-m-d");
    		$data['return_date'] = strtotime($data['return_date']);
            try {
                $model->startTrans();
                $last_id = $model->add($data, array("callback"=>true));
                $model->commit();
            } catch (Think\Exception $ex) {
                $model->rollback();
                $last_id = false;
            }

            if($last_id){
            	if (!empty($post_data['item_ids'])) {
                	$condition['id'] = array("in",$post_data['item_ids']);
                	$item_data['return_order_id'] = $last_id;
                	// $item_data['status'] = 1;
                	M("ComSundryItem")->where($condition)->save($item_data);

                    $comSundryOrder = M("ComSundryOrder")->where(['id'=>$last_id])->find();
                    $item_data = D("ComSundryItem")->setDacFilter("a")->relation(true)->field("a.*")->where(['a.return_order_id'=> $last_id])->select();
                    $sundry_names ="";
                    foreach ($item_data as $k => $v) {
                        if (empty($sundry_names)) {
                            $sundry_names = $v['sundry_name'];
                        }else{
                            $sundry_names = $sundry_names.",".$v['sundry_name'];
                        }
                    }

                    $record_data = array(
                            "parent_id" => $id,
                            "type" => 4,
                            "sundry_names" => $sundry_names,
                            "branch_id" =>$this->_user_session->currBranchId,
                            "user_id"=>$comSundryOrder['returner'],
                            "date"=> time(),
                            "created_at"=> time()
                        );
                    D("ComSundryRecord")->add($record_data);
                    // $rst = 
                    D("ComSundryOrder")->sendReturnWXMessage($last_id,$record_id);
            	// var_dump($rst);
                }
            	$this->ajaxReturn(array('error'=>0,'message'=>'归还成功!!'));
            }else{
            	$this->ajaxReturn(array('error'=>1,'message'=>'归还失败!!'));
            }
    	}else{
    		$this->ajaxReturn(array('error'=>1,'message'=>'归还失败!!'));
    	}
        
    }
    
}