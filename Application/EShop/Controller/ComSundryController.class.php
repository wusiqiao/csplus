<?php

namespace EShop\Controller;

use Think\Controller;

class ComSundryController extends UserLoginController {
	public function indexAction() {
        $status = ['借用中','已归还','未借用'];
    	$data = array();
        if (!empty(I("post.customer_company"))) {
            $condition['a.customer_company'] = I("post.customer_company");
        }
    	$condition['a.branch_id'] = $this->user_branch;
        $data = D('ComSundry')
            ->alias('a')
            ->join('LEFT JOIN sys_user b ON b.id = a.user_id')
            ->field('b.name as user_name,a.id,a.status,a.name,a.no,a.position')
            ->where($condition)
            ->select();
        foreach ($data as $k => $v) {
            $data[$k]['status'] = 
            isset($status[$v['status']])?$status[$v['status']]:"";
            $data[$k]['user_name'] = 
            isset($data[$k]['user_name'])?$data[$k]['user_name']:"";
        }

        if (!empty(I("post.customer_company"))) {
            $this->ajaxReturn($data);
        } else {
            $condition = [];
            $condition['branch_id'] = $this->user_branch;
            $condition['name'] = array(['exp',' is not NULL'],['exp','<> ""']);
            $this->userList = json_encode(D('SysUser')->field('id as value,name as text')->where($condition)->select());
            $this->title = '物品管理';
            $this->assign('model',$data);
            $this->display('index');
        }
        
    }

    public function editAction($id = null) {
        if (IS_GET) {
            $status = ['借用中','已归还','未借用'];
            $data = array();
            $condition['a.branch_id'] = $this->user_branch;
            $condition['a.id'] = $id ;
            $data = D('ComSundry')
                ->alias('a')
                ->join('LEFT JOIN sys_user b ON b.id = a.user_id')
                ->field('b.name as user_name,a.id,a.status,a.name,a.no,a.position,a.last_borrow_date,a.last_return_date')
                ->where($condition)->find();
            $data['show_status'] = $status[$data['status']];
            $data['last_borrow_date'] = isset($data['last_borrow_date'])?date('Y-m-d',$data['last_borrow_date']):null;
            $data['last_return_date'] = isset($data['last_return_date'])?date('Y-m-d',$data['last_return_date']):null;
            if (empty($id)) {
                $data['no'] = D('ComSundry')->getMaxNo($this->user_branch);
            }    
            $this->assign('model',$data);
            $this->display('edit');
        }else{
            $post_data = I('post.');
            $model = D('ComSundry');
            $data = array();
            $id = isset($post_data['id'])?$post_data['id']:null;
            if (!empty($id)) {
                $data['no'] = $post_data['no'];
                $data['name'] = $post_data['name'];
                $data['position'] = $post_data['position'];
                $data['updated_at'] = time();
                try {
                    $condition['id'] = $id;
                    $model->startTrans();
                    $last_id = $model->where( $condition)->save($data);
                    $model->commit();
                } catch (Think\Exception $ex) {
                    $model->rollback();
                    $last_id = false;
                }
                if($last_id){
                    $this->ajaxReturn(array('error'=>0,'msg'=>'更新成功!!'));
                }else{
                    $this->ajaxReturn(array('error'=>1,'msg'=>'更新失败!!'));
                }
            } else {
                $data['no'] = $post_data['no'];
                $data['name'] = $post_data['name'];
                $data['position'] = $post_data['position'];
                $data['status'] = $post_data['status'];
                $data['customer_company'] = $post_data['customer_company'];
                $data['branch_id'] =  $this->user_branch;
                $data['created_at'] = time();
                $data['updated_at'] = time();
                try {
                    $model->startTrans();
                    $last_id = $model->add($data, array("callback"=>true));
                    $model->commit();
                } catch (Think\Exception $ex) {
                    $model->rollback();
                    $last_id = false;
                }
                if($data['status']==0){
                    $data = array();
                    $post_data = I("post.");
                    $model = D('ComSundryOrder');
                    $data['customer_company'] = $post_data['customer_company'];
                    $data['no'] = $model->getMaxBillNo($this->user_branch,"borrow_date","no");
                    $data['type'] = 0;
                    $data['status'] = 3;
                    $data['remarks'] = $post_data['remarks'];
                    $data['branch_id'] = $this->user_branch;
                    $data['lender'] = $post_data['lender'];
                    $data['borrower'] = $post_data['user_id'];
                    $data['borrow_date'] = time();
                    $data['expected_return_date'] = time();
                    $data['created_at'] = time();
                    $data['updated_at'] = time();
                    try {
                        $model->startTrans();
                        $order_id = $model->add($data, array("callback"=>true));
                        $model->commit();
                    } catch (Think\Exception $ex) {
                        $model->rollback();
                        $order_id = false;
                    }
                    if($order_id){
                        $model = D("ComSundryItem");
                        $sundry_data = array(
                            "parent_id" => $order_id,
                            "sundry_id" => $last_id,
                            "branch_id" => $this->user_branch,
                            "status"=> 0,
                            "created_at"=> time(),
                            "updated_at"=> time()
                        );

                        try {
                            $model->startTrans();
                            $model->add($sundry_data);
                            $model->commit();
                        } catch (Think\Exception $ex) {
                            $model->rollback();
                            // $last_id = false;
                        }

                        $record_data = array(
                            "parent_id" => $order_id,
                            "type" => 3,
                            "sundry_names" => $post_data['name'],
                            "branch_id" => $this->user_branch,
                            "user_id"=>$data['lender'],
                            // "remarks"=>$data['remarks'],
                            "date"=> time(),
                            "created_at"=> time()
                        );
                        try {
                            $model->startTrans();
                            $record_id = D("ComSundryRecord")->add($record_data);
                            $model->commit();
                        } catch (Think\Exception $ex) {
                            $model->rollback();
                            $last_id = false;
                        }
                    }
                }
                if($last_id){
                    $this->ajaxReturn(array('error'=>0,'msg'=>'新建成功!!'));
                }else{
                    $this->ajaxReturn(array('error'=>1,'msg'=>'新建失败!!'));
                }
            }
        }
    }
}