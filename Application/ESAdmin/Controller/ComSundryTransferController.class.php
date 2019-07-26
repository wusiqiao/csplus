<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class  ComSundryTransferController extends DataController {
    protected function _before_write($type, &$data) {
        parent::_before_write($type, $data);
        $data['sundry_ids'] = implode(",",$data['sundry_ids']);
        $data['company_cc_recipient'] = implode(",",$data['company_cc_recipient']);
    }

    protected function _before_detail(&$data)
    {
        parent::_before_detail($data);
        $condition['id'] = array('in',$data['sundry_ids']);
        $data['items'] = M("ComSundry")->where($condition)->select();
    }

    protected function _before_add(&$data) {
        $data["no"] = $this->getMaxBillNoByUserBranch("created_at","no");
        parent::_before_add($data);
    }

    //发起借出申请
    public function applyAction($id){
        $data["status"] = 1;
        $model = M("ComSundryTransfer");
        try {
            $model->startTrans();
            $num = $model->where(['id'=>$id])->save($data);
            $model->commit();
        } catch (Think\Exception $ex) {
            $model->rollback();
            $num = false;
        }

        if($num){
            $comSundryTransfer = $model->where(['id'=>$id])->find();
            $last_id = $comSundryTransfer['id'];
            $condition['id'] = array("in",$comSundryTransfer['sundry_ids']);
            $ComSundry = D("ComSundry")->where($condition)->select();
            $sundry_names ="";
            foreach ($ComSundry as $k => $v) {
                if (empty($sundry_names)) {
                    $sundry_names = $v['name'];
                }else{
                    $sundry_names = $sundry_names.",".$v['name'];
                }
            }
            $record_data = array(
                    "parent_id" => $last_id,
                    "type" => 7,
                    "sundry_names" => $sundry_names,
                    "branch_id" =>$this->_user_session->currBranchId,
                    "user_id"=>$comSundryTransfer['lender'],
                    "date"=> time(),
                    "created_at"=> time()
                );
            $record_id = D("ComSundryRecord")->add($record_data);
            // $rst = 
            D("ComSundryOrder")->sendTransferApplyWXMessage($id,$record_id);
// var_dump($rst);
            $this->ajaxReturn(array('error'=>0,'message'=>'申请成功!!'));
        }else{
            $this->ajaxReturn(array('error'=>1,'message'=>'申请失败!!'));
        }
    }

    //查看借用记录
    public function recordAction($id){
        $data = array();
            if ($id){
                // $data = D('ComSundryOrder')->setDacFilter("a")->relation(true)->field("a.*")->where(["a.id" => $id])->find();
                $condition['parent_id'] = $id;
                $condition['type'] = array("in",[7,8]);
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
}