<?php

namespace EShop\Controller;

use Think\Controller;

class ComSundryOrderController extends UserLoginController {

    //判断是否为客户
    protected function is_customer(){
        // $_SESSION['user_id']
        $sysUserBranch = D('SysUserBranch')->where(['user_id'=>$_SESSION['user_id']])->find();
        if (!empty($sysUserBranch) && $sysUserBranch['branch_id'] != $this->user_branch) {
            return $sysUserBranch['branch_id'];
        }else{
            return false;
        }
    }

    public function indexAction(){
        $data = array();
        $status =['借用中','已归还','未借用'];

        if ($branch_id = $this->is_customer()) {
            $condition['customer_company'] = $branch_id;
            $this->is_customer = 1;
        } else {
            if (!empty(I("post.customer_company"))) {
                $condition['customer_company'] = I("post.customer_company");
            }
            $condition['branch_id'] = $this->user_branch;
            $this->is_customer = 0;
        }

        $data["sundry"] = D('ComSundry')->where($condition)->select();
        foreach ($data["sundry"] as $k => $v) {
            // $data["sundry"][$k]['show_status'] = $status[$v['status']];
            $data["sundry"][$k]['show_status'] = 
            isset($status[$v['status']])?$status[$v['status']]:"";
        }

        if (!empty(I("post.customer_company"))) {
            //根据选择公司刷新物品列表
            $this->ajaxReturn($data["sundry"]);
        } else {
            $user = D('SysUser')->where(['id'=>$_SESSION['user_id']])->field('id,user_type,name')->find();
            $this->user = $user;
            // $data["user"] =  $user;
            unset($condition['id']);
            $condition['name'] = array(['exp',' is not NULL'],['exp','<> ""']);
            $condition['user_type'] = array('in',['2','3']);
            $data['userList'] = json_encode(D('SysUser')->field('id as value,name as text')->where($condition)->select());

            unset($condition['user_type']);
            unset($condition['name']);
            $condition['no'] = array(['exp',' is not NULL'],['exp','<> ""']);
            $condition['type'] = 0;
            $data['orderList'] = json_encode(D('ComSundryOrder')->field('id as value,no as text')->where($condition)->select());

            $data['no'] = D('ComSundryOrder')->getMaxBillNo($this->user_branch,"borrow_date","no");
            $this->title = '客户借还';
            $this->assign('model',$data);
            $this->display('index');

        }

    }

    public function select_companyAction($init = 0,$name = null){
        $condition = [];
        if (!empty($name)) {
            $condition['name'] = array('LIKE','%'.$name.'%');
        }
        $condition['parent_id'] = $this->user_branch;
        // $tmp['id'] = $this->user_branch;
        // $tmp['_logic'] = 'or';
        // $condition['_complex'] = $tmp;

        $this->company = D('sysBranch')
        ->field('id as value,name as text')
        ->where($condition)->select();
        $this->init = $init;
        $this->display('select_company');
    }

    public function select_company1Action($init = 0,$name = null){
        $condition = [];
        if (!empty($name)) {
            $condition['name'] = array('LIKE','%'.$name.'%');
        }
        $tmp['parent_id'] = $this->user_branch;
        $tmp['id'] = $this->user_branch;
        $tmp['_logic'] = 'or';
        $condition['_complex'] = $tmp;

        $this->company = D('sysBranch')
        ->field('id as value,name as text')
        ->where($condition)->select();
        $this->init = $init;
        $this->display('select_company');
    }

    public function select_lenderAction($branch_id = null){
        $condition['b.branch_id'] = $branch_id;
        $condition['a.name'] = array(['exp',' is not NULL'],['exp','<> ""']);
        $customerList = D('sysUser')
            ->alias('a')
            ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
            ->field('a.id as value,a.name as text')
            ->where($condition)->select();
        $this->ajaxReturn($customerList);
    }

    public function getRecordAction($id){
        $data = array();
            if ($id){
                $condition['id'] = $id;
                $comSundryOrder = D('ComSundryOrder')->where($condition)->find();
                $this->sundryOrder = $comSundryOrder;

                unset($condition);
                $condition['a.parent_id'] = $id;
                $condition['a.type'] = array("in",[1,2,3,4,5,9]);
                $rst = D('ComSundryRecord')
                ->alias('a')
                ->join('LEFT JOIN sys_user b ON b.id = a.user_id')
                ->field('b.name as user_name,a.id,a.type,a.sundry_names,a.date,a.created_at')
                ->where($condition)->order('a.created_at desc')->select();
                $i = 'avtive';
                foreach ($rst as $k => $v) {
                    $rst[$k]['active'] = $i;
                    $rst[$k]['date'] = date('Y/m/d',$v['date']);
                    $rst[$k]['created_at'] = date('Y/m/d H:i:s',$v['created_at']);
                    $i='';
                }
                $data['records'] = $rst;
            }
            if ($branch_id = $this->is_customer()) {
                $this->is_customer = 1;
            } else {
                $this->is_customer = 0;
            }
        $this->assign('model',$data);
        $this->display('record');
    }

    public function addAction(){
        $data = array();
        $post_data = I("post.");
        $model = D('ComSundryOrder');
        $data['customer_cc_recipient'] = implode(",",$post_data['customer_cc_recipient']);
        $data['company_cc_recipient'] = implode(",",$post_data['company_cc_recipient']);
        $data['customer_leader'] = $post_data['customer_leader'];
        $data['customer_company'] = $post_data['customer_company'];
        $data['no'] = $post_data['no'];
        $data['type'] = 0;
        $data['status'] = 1;
        $data['remarks'] = $post_data['remarks'];
        $data['branch_id'] = $this->user_branch;
        $data['lender'] = $post_data['lender'];
        $data['borrower'] = $_SESSION['user_id'];
        $data['borrow_date'] = strtotime($post_data['borrow_date']);
        $data['expected_return_date'] = strtotime($post_data['expected_return_date']);
        $data['created_at'] = time();
        $data['updated_at'] = time();
        // var_dump($post_data);
        try {
            $model->startTrans();
            $last_id = $model->add($data, array("callback"=>true));
            $model->commit();
        } catch (Think\Exception $ex) {
            $model->rollback();
            $last_id = false;
        }

        // $post_data['sundry_ids'];
        // var_dump($data);

        if($last_id){
            $model = D("ComSundryItem");
            $sundry_ids = $post_data['sundry_ids'];
            $sundry_datas = array();
            foreach ($sundry_ids as $key => $value) {
                $sundry_datas[] = array(
                    "parent_id" => $last_id,
                    "sundry_id" => $sundry_ids[$key],
                    "branch_id" => $this->user_branch,
                    "created_at"=> time(),
                    "updated_at"=> time()
                );
            }
            try {
                $model->startTrans();
                $model->addAll($sundry_datas, null, true);
                $model->commit();
            } catch (Think\Exception $ex) {
                $model->rollback();
                $last_id = false;
            }

            $model = D("ComSundryRecord");
            $record_data = array(
                "parent_id" => $last_id,
                "type" => 1,
                "sundry_names" => $post_data['sundry_names'],
                "branch_id" => $this->user_branch,
                "user_id"=>$data['borrower'],
                "remarks"=>$data['remarks'],
                "date"=> time(),
                "created_at"=> time()
            );
            try {
                $model->startTrans();
                $record_id = D("ComSundryRecord")->add($record_data);
                $model->commit();
                // $rst = 
                D("ComSundryOrder")->sendBorrowApplyWXMessage($last_id,$record_id);
                // var_dump($rst);
            } catch (Think\Exception $ex) {
                $model->rollback();
                $last_id = false;
            }
            $this->ajaxReturn(array('error'=>0,'msg'=>'申请成功!!'));
        }else{
            $this->ajaxReturn(array('error'=>1,'msg'=>'操作失败!!'));
        }
    }

    public function borrowAction($record_id = null){
        $condition['id'] = $record_id;
        $record = D('ComSundryRecord')->where($condition)->find();
        if (IS_GET) {
        	$data = array();
            if (!empty($record)) {
            	str_replace(',','、',$record['sundry_names']);
            	$condition['id'] = $record['parent_id'];
                $order = D('ComSundryOrder')->where($condition)->find();
                $condition['id'] = $order['borrower'];
                $borrower = D('SysUser')->where($condition)->find();
                $order['borrower'] = $borrower['name'];
                $order['borrow_date'] = date('Y/m/d',$order['borrow_date']);

                $data = $order;
                $data['record'] = $record;
            }
        	$this->assign('model',$data);
            $this->display('borrow');
        } else {
        	$post_data = I('post.');
	        $data["status"] = 2;
   		 	$data['pick_up_date'] = strtotime($post_data['pick_up_date']);
            // $data['remarks'] = strtotime($post_data['remarks']);
	        $model = M("ComSundryOrder");
	        try {
	            $model->startTrans();
	            $condition['id'] = $record['parent_id'];
	            $num = $model->where($condition)->save($data);
	            $model->commit();
	        } catch (Think\Exception $ex) {
	            $model->rollback();
	            $num = false;
	        }

	        if($num){
	        	$condition['id'] = $record['parent_id'];
	            $comSundryOrder = $model->where($condition)->find();
	            $record_data = array(
                    "parent_id" =>$condition['id'],
                    "type" => 2,
                    "sundry_names" =>$record['sundry_names'],
                    "branch_id" =>$this->user_branch ,
                    "user_id"=>$comSundryOrder['lender'],
                    "date"=> strtotime($post_data['pick_up_date']),
                    "remarks"=> $post_data['remarks'],
                    "created_at"=> time()
                );
	            $record_id = D("ComSundryRecord")->add($record_data);
	            // D("ComSundryOrder")->sendBorrowApplyWXMessage($id,$record_id);
	            $this->ajaxReturn(array('error'=>0,'msg'=>'同意申请!!'));
	        }else{
	            $this->ajaxReturn(array('error'=>1,'msg'=>'操作失败!!'));
	        }
        }
    }

    //拒绝借用
    public function refuseAction($record_id = null){
        $condition['id'] = $record_id;
        $record = D('ComSundryRecord')->where($condition)->find();
        if (IS_POST) {
            $post_data = I('post.');
            // $data["status"] = 9;
            // $data['pick_up_date'] = strtotime($post_data['pick_up_date']);
            // $data['remarks'] = strtotime($post_data['remarks']);
            $model = M("ComSundryOrder");
            try {
                $model->startTrans();
                $condition['id'] = $record['parent_id'];
                $num = $model->where($condition)->save(["status" => 9]);
                $model->commit();
            } catch (Think\Exception $ex) {
                $model->rollback();
                $num = false;
            }

            if($num){
                $condition['id'] = $record['parent_id'];
                $comSundryOrder = $model->where($condition)->find();
                $record_data = array(
                    "parent_id" =>$condition['id'],
                    "type" => 9,
                    // "sundry_names" =>$record['sundry_names'],
                    "branch_id" =>$this->user_branch ,
                    "user_id"=>$comSundryOrder['lender'],
                    "date"=> time(),
                    "remarks"=> $post_data['remarks'],
                    "created_at"=> time()
                );
                $record_id = D("ComSundryRecord")->add($record_data);
                // D("ComSundryOrder")->sendBorrowApplyWXMessage($id,$record_id);
                $this->ajaxReturn(array('error'=>0,'msg'=>'同意申请!!'));
            }else{
                $this->ajaxReturn(array('error'=>1,'msg'=>'操作失败!!'));
            }
        }
    }

    public function returnAction($record_id = null){
        $condition['id'] = $record_id;
        $record = D('ComSundryRecord')->where($condition)->find();
        if (IS_GET) {
            $data = array();
            if (!empty($record)) {
                $status =['借用中','已归还','未借用'];
                $condition['id'] = $record['parent_id'];
                $order = D('ComSundryOrder')->where($condition)->find();
                $data = $order;
                $data['record'] = $record;

                unset($condition['id']);
                $condition['a.parent_id'] = $record['parent_id'];
                $items = D('ComSundryItem')
                ->alias('a')
                ->join('com_sundry b ON b.id = a.sundry_id')
                ->field('a.id,b.name,a.status')
                ->where($condition)->select();
                foreach ($items as $k => $v) {
                    $items[$k]['show_status'] = $status[$v['status']];
                }
                $data['items'] = $items;
            }
            $this->assign('model',$data);
            $this->display('return');
        } else {
            $post_data = I('post.');
            $model = D("ComSundryOrder");
            $condition['id'] = $record['parent_id'];
            $comSundryOrder = $model->where($condition)->find();
            if ($data = $model->create($post_data)) { 
                unset($data['item_ids']);
                $data["type"] = 1;
                $data["status"] = 4;
                $data["no"] = $comSundryOrder["no"];
                $data["lender"] = $comSundryOrder["lender"];
                $data["returner"] = $comSundryOrder["borrower"];
                $data['borrow_date'] = $comSundryOrder['borrow_date'];
                $data['expected_return_date'] = $comSundryOrder['expected_return_date'];
                $data["branch_id"] = $this->user_branch;
                $data['return_date'] = strtotime($data['return_date']);
                $data["created_at"] = time();
                $data["updated_at"] = time();
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
                        M("ComSundryItem")->where($condition)->save(['return_order_id' => $last_id]);

                        $comSundryOrder = M("ComSundryOrder")->where(['id'=>$last_id])->find();
                        $item_data = D('ComSundryItem')
                            ->alias('a')
                            ->join('com_sundry b ON b.id = a.sundry_id')
                            ->field('a.id,b.name,a.status')
                            ->where(['a.return_order_id'=> $last_id])->select();
                        $sundry_names ="";
                        foreach ($item_data as $k => $v) {
                            if (empty($sundry_names)) {
                                $sundry_names = $v['name'];
                            }else{
                                $sundry_names = $sundry_names.",".$v['name'];
                            }
                        }

                        $record_data = array(
                                "parent_id" => $record['parent_id'],
                                "type" => 4,
                                "sundry_names" => $sundry_names,
                                "branch_id" =>$this->user_branch,
                                "user_id"=>$comSundryOrder['returner'],
                                "date"=> time(),
                                "created_at"=> time()
                            );
                        D("ComSundryRecord")->add($record_data);
                        // D("ComSundryOrder")->sendReturnWXMessage($last_id,$record_id);
                    }
                    $this->ajaxReturn(array('error'=>0,'msg'=>'归还成功!!'));
                }else{
                    $this->ajaxReturn(array('error'=>1,'msg'=>'归还失败!!'));
                }
            }
        }
    }

    public function signAction($record_id = null){
        $condition['id'] = $record_id;
        $record = D('ComSundryRecord')->where($condition)->find();

        if (IS_GET) {
        	$data = array();
            if (!empty($record)) {
                $status =['借用中','已归还','未借用'];
            	$condition['id'] = $record['parent_id'];
                $order = D('ComSundryOrder')->where($condition)->find();
                $data = $order;
                $data['record'] = $record;

                unset($condition['id']);
                $condition['a.parent_id'] = $record['parent_id'];

                $items = D('ComSundryItem')
				->alias('a')
                ->join('com_sundry b ON b.id = a.sundry_id')
                ->field('a.id,b.name,a.status')
                ->where($condition)->select();
                foreach ($items as $k => $v) {
                    $items[$k]['show_status'] = $status[$v['status']];
                }
                $data['items'] = $items;
            }
        	$this->assign('model',$data);
            $this->display('sign');
        } else {
        	$post_data = I('post.');
        	$data["status"] = 3;
            $condition['id'] = $record['parent_id'];
        	
	        $model = M("ComSundryOrder");
	        try {
	            $model->startTrans();
	            $num = $model->where($condition)->save($data);
	            $model->commit();
	        } catch (Think\Exception $ex) {
	            $model->rollback();
	            $num = false;
	        }
            
            if($num){    
                $comSundryOrder = $model->where($condition)->find();

                if (!empty($post_data['item_ids'])) {
                    // 
                    $condition = [];
                    $condition['id'] = array("in",$post_data['item_ids']);
                    D("ComSundryItem")->where($condition)->save(['status'=>0]);
                    
                    $condition = [];
                    $condition['a.id'] = array("in",$post_data['item_ids']);
                    $item_data = D("ComSundryItem")
                    ->alias('a')
                	->join('com_sundry b ON b.id = a.sundry_id')
                	->field('a.id,b.name,a.status,a.sundry_id')->where($condition)->select();

                    $sundry_names ="";
                    $sundry_ids = array();
                    foreach ($item_data as $k => $v) {
                        if (empty($sundry_names)) {
                            $sundry_names = $v['name'];
                        }else{
                            $sundry_names = $sundry_names.",".$v['sundry_name'];
                        }
                        array_push($sundry_ids, $v['sundry_id']);
                    }
                    // var_dump($sundry_ids);
                    $condition = [];
                    $condition['id'] = array("in",$sundry_ids);
                    D("ComSundry")->where($condition)->save(['status'=>0,'user_id'=>$comSundryOrder["borrower"],'last_borrow_date'=>time()]);
	            }
	            $record_data = array(
                    "parent_id" =>$record['parent_id'],
                    "type" => $data["status"],
                    "sundry_names" =>$record['sundry_names'],
                    "branch_id" =>$this->user_branch ,
                    "user_id"=>$comSundryOrder['returner'],
                    "date"=> time(),
                    "created_at"=> time()
                );
	            $record_id = D("ComSundryRecord")->add($record_data);
	            // D("ComSundryOrder")->sendBorrowApplyWXMessage($id,$record_id);
	            // $this->ajaxReturn(array('error'=>0,'msg'=>'签收成功!!'));
                $this->ajaxReturn(array('error'=>0,'msg'=>'签收成功!!'));
	        }else{
	            $this->ajaxReturn(array('error'=>1,'msg'=>'操作失败!!'));
	        }

        }
    }

    public function returnSignAction($record_id = null){
        $condition['id'] = $record_id;
        $record = D('ComSundryRecord')->where($condition)->find();
        if (IS_GET) {
            $data = array();
            if (!empty($record)) {
                $status =['借用中','已归还','未借用'];
                $condition['id'] = $record['parent_id'];
                $order = D('ComSundryOrder')->where($condition)->find();
                $data = $order;
                $data['record'] = $record;

                unset($condition['id']);
                $condition['a.parent_id'] = $record['parent_id'];

                $items = D('ComSundryItem')
                ->alias('a')
                ->join('com_sundry b ON b.id = a.sundry_id')
                ->field('a.id,b.name,a.status')
                ->where($condition)->select();
                foreach ($items as $k => $v) {
                    $items[$k]['show_status'] = $status[$v['status']];
                }
                $data['items'] = $items;
            }
            $this->assign('model',$data);
            $this->display('returnSign');
        } else {
            $post_data = I('post.');
            //决定是否结束借用单
            $flag = 1;
            if (!empty($post_data['item_ids'])) {
                $condition = [];
                $condition['id'] = $record['parent_id'];
                $model = M("ComSundryOrder");
                $comSundryOrder = $model->where($condition)->find();

                $condition = [];
                $condition['id'] = array("in",$post_data['item_ids']);
                D("ComSundryItem")->where($condition)->save(['status'=>1]);
                
                $condition = [];
                $condition['a.id'] = array("in",$post_data['item_ids']);
                $item_data = D("ComSundryItem")
                ->alias('a')
                ->join('com_sundry b ON b.id = a.sundry_id')
                ->field('a.id,b.name,a.status,a.sundry_id')->where($condition)->select();

                $sundry_names ="";
                $sundry_ids = array();
                foreach ($item_data as $k => $v) {
                    if (empty($sundry_names)) {
                        $sundry_names = $v['name'];
                    }else{
                        $sundry_names = $sundry_names.",".$v['sundry_name'];
                    }
                    if ($v['status']!=1) {
                        $flag = 0;
                    }
                    array_push($sundry_ids, $v['sundry_id']);
                }
                // var_dump($sundry_ids);
                $condition = [];
                $condition['id'] = array("in",$sundry_ids);
                D("ComSundry")->where($condition)->save(['status'=>1,'user_id'=>$comSundryOrder["lender"],'last_borrow_date'=>time()]);
            }
            $record_data = array(
                "parent_id" =>$record['parent_id'],
                "type" => $data["status"],
                "sundry_names" =>$record['sundry_names'],
                "branch_id" =>$this->user_branch ,
                "user_id"=>$comSundryOrder['borrower'],
                "date"=> time(),
                "created_at"=> time()
            );
            $record_id = D("ComSundryRecord")->add($record_data);

            if($flag == 1){
                $condition = [];
                $condition['id'] = $record['parent_id'];
                try {
                    $model->startTrans();
                    $num = $model
                    ->where($condition)
                    ->save(["status" => 5]);
                    $model->commit();
                } catch (Think\Exception $ex) {
                    $model->rollback();
                    $num = false;
                }
            }          
            // D("ComSundryOrder")->sendBorrowApplyWXMessage($id,$record_id);
            $this->ajaxReturn(array('error'=>0,'msg'=>'签收成功!!'));
        }
    }

}