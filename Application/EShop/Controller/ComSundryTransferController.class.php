<?php

namespace EShop\Controller;

use Think\Controller;

class ComSundryTransferController extends UserLoginController {

    public function indexAction(){
        $data = array();
        $condition['a.branch_id'] = $this->user_branch;
        $tmp['a.status'] = 0;
        $tmp['customer_company'] = $this->user_branch;
        $tmp['_logic'] = 'or';
        $condition['_complex'] = $tmp;
        $data["sundry"] = D('ComSundry')
            ->alias('a')
            ->join('LEFT JOIN sys_user b ON b.id = a.user_id')
            ->field('b.name as user_name,a.id,a.name,a.position,a.no')
            ->where($condition)->select();
        $condition = [];
        $condition['id'] = $_SESSION['user_id'];
        $data["user"] = D('SysUser')->where($condition)->field("name")->find();
        
        $condition = [];
        $condition['branch_id'] = $this->user_branch;
        $condition['name'] = array(['exp',' is not NULL'],['exp','<> ""']);
        $condition['user_type'] = array('in',['2','3']);
        $data['userList'] = json_encode(D('SysUser')->field('id as value,name as text')->where($condition)->select());
        
        $condition = [];
        $condition['branch_id'] = $this->user_branch;
        $condition['no'] = array(['exp',' is not NULL'],['exp','<> ""']);
        $data['transferList'] = json_encode(D('ComSundryTransfer')->field('id as value,no as text')->where($condition)->select());

        $data['no'] = D('ComSundryTransfer')->getMaxBillNo($this->user_branch,"created_at","no");
        $this->title = '内部借用';
        $this->assign('model',$data);
        $this->display('index');
    }

    public function getRecordAction($id){
        $data = array();
            if ($id){
                $condition['id'] = $id;
                $comSundryTransfer = D('ComSundryTransfer')->where($condition)->find();
                $this->sundryTransfer = $comSundryTransfer;

                unset($condition);
                $condition['a.parent_id'] = $id;
                $condition['a.type'] = array("in",[7,8]);
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
        $this->assign('model',$data);
        $this->display('record');
    }

    public function addAction(){
        $data = array();
        $post_data = I("post.");
        $model = D('ComSundryTransfer');
        // $data['customer_cc_recipient'] = implode(",",$post_data['customer_cc_recipient']);
        // $data['type'] = 0;
        // $data['remarks'] = $post_data['remarks'];
        // $data['borrow_date'] = strtotime($post_data['borrow_date']);
        // $data['no'] = uniqid();
        $data['company_cc_recipient'] = implode(",",$post_data['company_cc_recipient']);
        $data['sundry_ids'] = implode(",",$post_data['sundry_ids']);
        $data['customer_leader'] = $post_data['customer_leader'];
        $data['status'] = 7;
        $data['branch_id'] = $this->user_branch;
        $data['lender'] = $_SESSION['user_id'];
        $data['borrower'] = $post_data['borrower'];
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

        // $post_data['sundry_ids'];
        // var_dump($data);

        if($last_id){
            // $model = D("ComSundryItem");
            // $sundry_ids = $post_data['sundry_ids'];
            // $sundry_datas = array();
            // foreach ($sundry_ids as $key => $value) {
            //     $sundry_datas[] = array(
            //         "parent_id" => $last_id,
            //         "sundry_id" => $sundry_ids[$key],
            //         "branch_id" => $this->user_branch,
            //         "created_at"=> time(),
            //         "updated_at"=> time()
            //     );
            // }
            // try {
            //     $model->startTrans();
            //     $model->addAll($sundry_datas, null, true);
            //     $model->commit();
            // } catch (Think\Exception $ex) {
            //     $model->rollback();
            //     $last_id = false;
            // }

            $record_data = array(
                    "parent_id" => $last_id,
                    "type" => 7,
                    "sundry_names" => $post_data['sundry_names'],
                    "branch_id" =>$this->user_branch,
                    "user_id"=>$data['lender'],
                    "date"=> time(),
                    "created_at"=> time()
                );
            try {
                $model->startTrans();
                $record_id = D("ComSundryRecord")->add($record_data);
                $model->commit();
            // D("ComSundryOrder")->sendTransferApplyWXMessage($id,$record_id);
            } catch (Think\Exception $ex) {
                $model->rollback();
                $last_id = false;
            }

            $this->ajaxReturn(array('error'=>0,'msg'=>'申请成功!!'));
        }else{
            $this->ajaxReturn(array('error'=>1,'msg'=>'操作失败!!'));
        }
    }

    public function transferSignAction($record_id = null){
        $condition['id'] = $record_id;
        $record = D('ComSundryRecord')->where($condition)->find();
        if (IS_POST) {
            $post_data = I('post.data');
            $model = M("ComSundryTransfer");
            try {
                $model->startTrans();
                $condition['id'] = $record['parent_id'];
                $num = $model->where($condition)->save(["status" => 8]);
                $model->commit();
            } catch (Think\Exception $ex) {
                $model->rollback();
                $num = false;
            }

            if($num){
                $condition['id'] = $record['parent_id'];
                $comSundryTransfer = $model->where($condition)->find();
                $sundry_ids = explode(",", $comSundryTransfer["sundry_ids"]);
                $condition['id'] =  array("in",$sundry_ids);
                D('ComSundry')->where($condition)->save(["user_id"=>$comSundryTransfer['borrower']]);

                $record_data = array(
                    "parent_id" =>$record['parent_id'],
                    "type" => 8,
                    "sundry_names" =>$record['sundry_names'],
                    "branch_id" =>$this->user_branch ,
                    "user_id"=>$comSundryTransfer['borrower'],
                    "date"=> time(),
                    "created_at"=> time()
                );
                $record_id = D("ComSundryRecord")->add($record_data);
                // D("ComSundryOrder")->sendBorrowApplyWXMessage($id,$record_id);
                $this->ajaxReturn(array('error'=>0,'msg'=>'签收成功!!'));
            }else{
                $this->ajaxReturn(array('error'=>1,'msg'=>'操作失败!!'));
            }

        }
    }
}