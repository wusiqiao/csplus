<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class  WrkReceivablesAccountController extends DataController {
    protected function _before_write($type, &$data)
    {
        parent::_before_write($type, $data);
        $data['update_time'] = time();
        $data['update_id'] = $this->_user_session->userId;
        if ($type == 1) {
        	$data['create_time'] = time();
        	$data['creater_id'] = $this->_user_session->userId;
        }
    }

    public function recordAction(){
        $page_index = I("page", 1);
        $page_size = I("rows",1024);
    	$condition = [];
    	$account_id = I('account_id');

    	$begin_date = I('begin_date');
    	if (empty($begin_date)) {
    		$begin_date = strtotime('1970-01-01');
    	}else{
    		$begin_date = strtotime($begin_date);
    	}
    	$end_date = I('end_date');
    	if (empty($end_date)) {
    		$end_date = strtotime(date('Y-m-t'));
    	}else{
    		$end_date = strtotime("$end_date +1 day");
    	}
    	$condition['a.fina_time']  = array('between',[$begin_date,$end_date]);
        // $condition['a.branch_id']  = $this->_user_session->currBranchId;
    	if (!empty($account_id) && $account_id!='other'){
    		$condition['a.receivable_id'] = $account_id;
        // $condition['a.branch_id']  = ;
        }
        // $branch_ids = M('SysBranch')->where(['parent_id'=>$this->_user_session->currBranchId])->getField('id',true);
        // array_push($branch_ids, $this->_user_session->currBranchId);
        $condition['c.branch_id'] = $this->_user_session->currBranchId;
    	// 
        // 充值 1 2 12
        // 提现  -2 -1

        $condition['a.fina_type'] = array('in',[-1,-2,1,2,12]);
        // var_dump($condition);
        $list = M('ComFinance')->alias('a')
        ->join('LEFT JOIN sys_branch b ON b.id = a.company_id')
        ->join('LEFT JOIN wrk_receivables_account c ON c.id = a.receivable_id')
        ->join('LEFT JOIN wrk_receivables_record d ON d.order_sn = a.order_sn')
        ->join('LEFT JOIN wrk_receivables e ON e.id = d.receivables_id')
        ->join('LEFT JOIN wrk_agreement f ON f.id = e.contract_id')
        ->field('f.agreement_sn as contract_no,f.name as contract_name,e.leader_id,a.fina_id as id,a.fina_time,a.fina_type,a.fina_cash,a.third_fee,c.name as account_name,b.name as company_name,f.customer_leader_id,a.user_id,a.company_id,a.platform_fee')
        ->where($condition)->page($page_index,$page_size)
        ->order("fina_time desc")
        ->select();
        $count = M('ComFinance')->alias('a')
            ->join('LEFT JOIN sys_branch b ON b.id = a.company_id')
            ->join('LEFT JOIN wrk_receivables_account c ON c.id = a.receivable_id')
            ->join('LEFT JOIN wrk_receivables_record d ON d.order_sn = a.order_sn')
            ->join('LEFT JOIN wrk_receivables e ON e.id = d.receivables_id')
            ->join('LEFT JOIN wrk_agreement f ON f.id = e.contract_id')
            ->where($condition)
            ->count();
        foreach ($list as $k => $v) {
            if ($v['fina_type'] > 0) {
                // $list[$k]['detail_type'] = "收入";
                // if (in_array($v['fina_type'],[1,2,12])){
                //     $list[$k]['income_type'] = '充值';
                // }
                $list[$k]['income_amount'] = $v['fina_cash'];
                $list[$k]['recharge_poundage'] = $v['third_fee'];
                $list[$k]['actual_in'] = $v['fina_cash'] - $v['third_fee'];
                if ($v['fina_type'] == 12) {
                    $list[$k]['leader'] = M("SysUser")->where(['id'=>$v['leader_id']])->getField("name");
                }else{
                    $jurisdiction =  D('ComAccountJurisdiction');
                    $jurisdiction->setObjectId($v['company_id']);
                    if ($v['fina_type'] == 2) {
                        $jurisdiction->setObjectType(2);
                    } else {
                        $jurisdiction->setObjectType(1);
                    }
                    $jurisdiction->setObjectVarious([CAJ_BRANCH_RECHARGE]);
                    $list[$k]['leader'] = M("SysUser")->where(['id'=>$jurisdiction->getAccountLeaderId() ])->getField("name");
                }
                // $list[$k]['customer_name'] = M("SysUser")->where(['id'=>$v['customer_leader_id']])->getField("name");
            } else {
                // $list[$k]['detail_type'] = "支出";
                // if (in_array($v['fina_type'],[-1,-2])) {
                //    $list[$k]['pay_type'] = '提现';
                // }
                $jurisdiction =  D('ComAccountJurisdiction');
                $jurisdiction->setObjectId($v['company_id']);
                if ($v['fina_type'] == -2) {
                    $jurisdiction->setObjectType(-2);
                } else {
                    $jurisdiction->setObjectType(-1);
                }
                $jurisdiction->setObjectVarious([CAJ_BRANCH_WITHDRAWAL]);
                $list[$k]['leader'] = M("SysUser")->where(['id'=>$jurisdiction->getAccountLeaderId() ])->getField("name");
                $list[$k]['pay_amount'] = $v['fina_cash'];
                $list[$k]['cash_poundage'] = $v['third_fee'] + $v['platform_fee'];
                $list[$k]['actual_out'] = $v['fina_cash'] - $v['third_fee'] - $v['platform_fee'];
                // $list[$k]['leader'] = M("SysUser")->where(['id'=>$v['leader_id']])->getField("name");
            }
            $list[$k]['customer_name'] = M("SysUser")->where(['id'=>$v['user_id']])->getField("name");
        }
        $result['rows'] = $list;
        $result['total'] = $count;
    	$this->ajaxReturn($result);
    }
    //出款
    public function paymentAction($id){
        if (IS_POST) {
            $finance_data = [];
            $finance_data['fina_type'] = FIN_PAYMENT;
            $finance_data['fina_cash'] = I('amount');
            $finance_data['attach_group'] = I('attach_group');
            $finance_data['fina_time'] = time();
            $finance_data['user_id'] = $this->_user_session->userId;
            $finance_data['branch_id'] = $this->_user_session->currBranchId;
            $finance_data['company_id'] = $this->_user_session->currBranchId;
            $finance_data['order_sn'] = getOrderNo("CIZ_");
            $finance_data['third_fee'] = 0;
            $finance_data['receivable_id'] = I('id');
            $finance_data['title'] = '收款账户入款';
            M("ComFinance")->add($finance_data);
            $accumulated_amount = M("wrkReceivablesAccount")->where(['id'=>$id])->getField('accumulated_amount');
            $accumulated_amount = $accumulated_amount - $finance_data['fina_cash'];
            M("wrkReceivablesAccount")->where(['id'=>$id])->save(['accumulated_amount'=>$accumulated_amount]);
            $this->ajaxReturn(array('code'=>0,'message'=>'收款账户出款成功'));
        } else {
            $rst['id'] = $id;
            $rst['account_name'] = M('WrkReceivablesAccount')
            ->where(['id'=>$id])->getField("name");
            $rst['attach_group'] = genUniqidKey();
            $this->assign('model',$rst);
            $this->display('payment');
        }
    }
    //入款
    public function incomeAction($id){
        if (IS_POST) {
            $finance_data = [];
            $finance_data['fina_type'] = FIN_INCOME;
            $finance_data['fina_cash'] = I('amount');
            $finance_data['attach_group'] = I('attach_group');
            $finance_data['fina_time'] = time();
            $finance_data['user_id'] = $this->_user_session->userId;
            $finance_data['branch_id'] = $this->_user_session->currBranchId;
            $finance_data['company_id'] = $this->_user_session->currBranchId;
            $finance_data['order_sn'] = getOrderNo("CIZ_");
            $finance_data['third_fee'] = 0;
            $finance_data['receivable_id'] = I('id');
            $finance_data['title'] = '收款账户入款';
            M("ComFinance")->add($finance_data);
            $accumulated_amount = M("wrkReceivablesAccount")->where(['id'=>$id])->getField('accumulated_amount');
            $accumulated_amount = $finance_data['fina_cash'] + $accumulated_amount;
            M("wrkReceivablesAccount")->where(['id'=>$id])->save(['accumulated_amount'=>$accumulated_amount]);
            $this->ajaxReturn(array('code'=>0,'message'=>'收款账户入款成功'));
        } else {
            $rst['id'] = $id;
            $rst['account_name'] = M('WrkReceivablesAccount')->where(['id'=>$id])->getField("name");
            $rst['attach_group'] = genUniqidKey();
            $this->assign('model',$rst);
            $this->display('income');
        }
    }
    //合同列表
    public function ContractListAction($id){
        $list = M('WrkReceivablesRecord')->alias('a')
        ->join('LEFT JOIN wrk_receivables b ON b.id = a.receivables_id')
        ->join('LEFT JOIN wrk_agreement c ON c.id = b.contract_id')
        ->join('LEFT JOIN sys_branch d ON d.id = c.company_id')
        ->field('c.id,c.name as contract_name,d.name as company_name,c.customer_leader_id,b.leader_id,a.receivables_id')
        ->where(['a.account_id'=>$id,'c.id'=>array('exp','is not null')])
        ->group("c.id")
        ->select();

        foreach ($list as $k => $v) {
            $tmp = M("SysUser")->field("id,name")->where(['id'=>$list[$k]['customer_leader_id']])->find();
            $list[$k]['customer_leader'] = $tmp['name'];
            $tmp = M("SysUser")->field("id,name")->where(['id'=>$list[$k]['leader_id']])->find();
            $list[$k]['leader'] = $tmp['name'];
            
            $badDept = M('WrkBadDept')->where(['receivables_id'=>$id])->find();
             $list[$k]['end_status'] = 1;
            if (!empty($badDept)) {
                $receivablesItem = M('WrkReceivablesItem')->where(['receivables_id'=>$id])->select();
                foreach ($receivablesItem as $k => $v) {
                    if ($v['status'] != 2) {
                        $list[$k]['end_status'] = 0;
                    }
                }
            }

        }
        $this->ajaxReturn($list);
    }
    //详情页明细
    public function financeAction($id){
        $page_index = I("page", 1);
        $page_size = I("rows",1024);
        $condition = [];
        $account_id = $id;
        $model = M('ComFinance');
        $condition['a.fina_type'][] = array('in',[-1,-2,-7,1,2,11,12]);
        $condition['a.receivable_id'] = $account_id;
        //收款账户下的所有记录
        $all_data = $model->alias('a')
            ->field('a.fina_id as id,a.fina_time,a.fina_type,a.fina_cash,a.third_fee,a.platform_fee')
            ->where($condition)->order("a.fina_time asc")->select();
        $actual_money = 0;//实际余额
        foreach($all_data as $k=>$v){
            $all_data[$k]['actual_money'] = $v['fina_type'] > 0 ? $actual_money += ($v['fina_cash']-$v['third_fee']-$v['platform_fee']):$actual_money -= ($v['fina_cash']-$v['third_fee']-$v['platform_fee']);
        }
        //根据筛选条件显示在列表中的记录
        $begin_date = I('begin_date');
        if (empty($begin_date)) {
            $begin_date = strtotime('1970-01-01');
        }else{
            $begin_date = strtotime($begin_date);
        }
        $end_date = I('end_date');
        if (empty($end_date)) {
            $end_date = strtotime(date('Y-m-t'));
        }else{
            $end_date = strtotime("$end_date +1 day");
        }
        $detail_type = I('detail_type');
        if ($detail_type == 1) {//收入
            $condition['a.fina_type'][] = array('in',[1,2,11,12]);
        }elseif ($detail_type == 2) {//支出
            $condition['a.fina_type'][] = array('in',[-1,-2,-7]);
        }
        $income_type = I('income_type');
        $fina_type = [];
        if ($income_type == 1) {
            array_push($fina_type, 1);
            array_push($fina_type, 2);
            array_push($fina_type, 12);
            // $condition['a.fina_type'][] = array('in',[1,2,12]);
        }elseif ($income_type == 2) {
            array_push($fina_type, 11);
            // $condition['a.fina_type'][] = array('in',[11]);
        }
        $pay_type = I('pay_type');
        if ($pay_type == 1) {
            array_push($fina_type, -1);
            array_push($fina_type, -2);

            // $condition['a.fina_type'][] = array('in',[-1,-2]);
        }elseif ($pay_type == 2) {
            array_push($fina_type, -7);
            // $condition['a.fina_type'][] = array('in',[-7]);
        }
        if (!empty($fina_type)) {
            $condition['a.fina_type'][] = array('in',$fina_type);
        }
        if(I("post.company_id")){
            $condition['a.company_id'] = I("post.company_id");
        }
        $condition['a.fina_time']  = array('between',[$begin_date,$end_date]);
        // $condition['a.branch_id'] = $this->_user_session->currBranchId;
        
        $account = M('WrkReceivablesAccount')->where(['id'=>$account_id,'branch_id'=>$this->_user_session->currBranchId])->find();

        $list = $model->alias('a')
            ->join('LEFT JOIN sys_branch b ON b.id = a.company_id')
            ->join('LEFT JOIN wrk_receivables_account c ON c.id = a.receivable_id')
            ->join('LEFT JOIN sys_user d ON d.id = a.user_id')
            ->join('LEFT JOIN wrk_receivables_record e ON e.order_sn = a.order_sn')
            ->join('LEFT JOIN wrk_receivables f ON f.id = e.receivables_id')
            ->field('a.company_id,b.name as company_name, d.name as customer_name,a.fina_id as id,a.fina_time,a.fina_type,a.fina_cash,a.third_fee,a.order_sn,c.name as account_name,f.leader_id,a.platform_fee')
            ->where($condition)->page($page_index,$page_size)
            ->order("a.fina_time asc")
            ->select();
        $count = $model->alias('a')
            ->join('LEFT JOIN sys_branch b ON b.id = a.company_id')
            ->join('LEFT JOIN wrk_receivables_account c ON c.id = a.receivable_id')
            ->join('LEFT JOIN sys_user d ON d.id = a.user_id')
            ->join('LEFT JOIN wrk_receivables_record e ON e.order_sn = a.order_sn')
            ->join('LEFT JOIN wrk_receivables f ON f.id = e.receivables_id')
            ->field('a.company_id,b.name as company_name, d.name as customer_name,a.fina_id as id,a.fina_time,a.fina_type,a.fina_cash,a.third_fee,a.order_sn,c.name as account_name,f.leader_id,a.platform_fee')
            ->where($condition)->count();
        $balance = $account['initial_balance'];
        $all_data_ids = array_column($all_data,"id");
        foreach ($list as $k => $v) {
            if ($v['fina_type'] > 0) {
                $list[$k]['detail_type'] = "收入";
                if (in_array($v['fina_type'],[1,2,12])){
                    $list[$k]['income_type'] = '直接充值';
                    $list[$k]['attach_group'] = M('ComRecharge')->where(['order_sn'=>$v['order_sn']])->getField("attach_group");

                    if ($v['fina_type'] == 12) {
                        $list[$k]['income_type'] = '充值付款';
                        $list[$k]['leader'] = M("SysUser")->where(['id'=>$v['leader_id']])->getField("name");
                    }else{
                        $jurisdiction =  D('ComAccountJurisdiction');
                        $jurisdiction->setObjectId($v['company_id']);
                        if ($v['fina_type'] == 2) {
                            $jurisdiction->setObjectType(2);
                        } else {
                            $jurisdiction->setObjectType(1);
                        }
                        $jurisdiction->setObjectVarious([CAJ_BRANCH_RECHARGE]);
                        $list[$k]['leader'] = M("SysUser")->where(['id'=>$jurisdiction->getAccountLeaderId() ])->getField("name");
                    }

                }elseif (in_array($v['fina_type'],[11])) {
                    $list[$k]['income_type'] = '入款';
                    $list[$k]['attach_group'] = M('ComFinance')->where(['order_sn'=>$v['order_sn']])->getField("attach_group");
                    $list[$k]['leader'] =  $list[$k]['customer_name'];

                }
                $list[$k]['income_amount'] = $v['fina_cash'];
                $list[$k]['recharge_poundage'] = $v['third_fee'];
                $list[$k]['actual_in'] = sprintf("%.2f",$v['fina_cash'] - $v['third_fee']-$v['platform_fee']);
                $balance = $balance + $list[$k]['actual_in'];
                $list[$k]['balance'] = $balance;
            } else {
                $list[$k]['detail_type'] = "支出";
                if (in_array($v['fina_type'],[-1,-2])) {
                    $list[$k]['pay_type'] = '提现';
                    $list[$k]['attach_group'] = M('ComWithdrawals')->where(['order_sn'=>$v['order_sn']])->getField("attach_group");

                    $jurisdiction =  D('ComAccountJurisdiction');
                    $jurisdiction->setObjectId($v['company_id']);
                    if ($v['fina_type'] == -2) {
                        $jurisdiction->setObjectType(-2);
                    } else {
                        $jurisdiction->setObjectType(-1);
                    }
                    $jurisdiction->setObjectVarious([CAJ_BRANCH_WITHDRAWAL]);
                    $list[$k]['leader'] = M("SysUser")->where(['id'=>$jurisdiction->getAccountLeaderId() ])->getField("name");


                }elseif (in_array($v['fina_type'],[-7])) {
                    $list[$k]['pay_type'] = '出款';
                    $list[$k]['attach_group'] = M('ComFinance')->where(['order_sn'=>$v['order_sn']])->getField("attach_group");
                    $list[$k]['leader'] =  $list[$k]['customer_name'];

                }
                $list[$k]['pay_amount'] = $v['fina_cash'];
                //$list[$k]['cash_poundage'] = $v['third_fee'];
                $list[$k]['cash_poundage'] = $v['platform_fee'];
                $list[$k]['actual_out'] = sprintf("%.2f",$v['fina_cash'] - $v['third_fee']-$v['platform_fee']);
                $balance = $balance - $list[$k]['actual_out'];
                $list[$k]['balance'] = $balance;
            }
            $list[$k]['balance'] = sprintf("%.2f",$all_data[array_search($v['id'],$all_data_ids)]['actual_money']);
        }
        $rst = [];
        /*foreach ($list as $k => $v) {
            array_push($rst,$v);
        }*/
        $rst['rows'] = $list;
        $rst['total'] = $count;
        $this->ajaxReturn($rst);
    }

    //删除到款记录
    public function deleteRecordAction($id){
        $record = M('WrkReceivablesRecord')->where(['receivables_id'=>$id])->order("id desc")->find();
        $items = M('WrkReceivablesItem')
        ->alias('a')
        ->join('LEFT JOIN wrk_receivables_item_record b ON b.item_id = a.id')
        ->where(['b.record_id'=>$record['id']])
        ->order("a.id desc")
        ->select();

        $pay_amount = $record['pay_amount'];

        foreach ($items as $k => $v) {
            if ($pay_amount > 0) {
                $balance =  $pay_amount - $v['actual_amount'];
                if ($balance >= 0) {
                    $actual_amount = 0;
                    $pay_amount = $balance;
                }else{
                    $actual_amount = $v['actual_amount'] - $pay_amount;
                    $pay_amount = 0;
                }
                M('WrkReceivablesItem')->where(['id'=>$v['id']])->save(['actual_amount'=>$actual_amount]);
            }else{
                break;
            }
        }
        M('WrkReceivablesRecord')->where(['id'=>$record['id']])->delete();
        M('WrkReceivablesItemRecord')->where(['record_id'=>$record['id']])->delete();
        M('ComFinance')->where(['order_sn'=>$record['order_sn']])->delete();
        M('ComRecharge')->where(['order_sn'=>$record['order_sn']])->delete();
        $receivables = M("WrkReceivables")->where(['id' =>$id])->find();
        $sysBranch = M("SysBranch")->where(['id' =>$receivables['company_id']])->find();
        $sysBranch['balance_amount'] = $sysBranch['money'] - $sysBranch['money_auditing'];
        D('WrkReceivables')->sendWXDeleteRecordMessage($id,$pay_amount,$balance_amount);
        $this->ajaxReturn(array('code' => 0, 'message' => '操作成功'));
    }

    //退款列表
    public function refundListAction($id){
        // 公司
        // 收款客户负责人
        // 合同名称
        // 收款商户负责人
        // 发生日期
        // 支出金额
        // 备注附件
        $list = M('WrkRefund')->alias('a')
        ->join('LEFT JOIN wrk_receivables b ON b.id = a.receivables_id')
        ->join('LEFT JOIN wrk_agreement c ON c.id = b.contract_id')
        ->join('LEFT JOIN sys_branch d ON d.id = c.company_id')
        ->field('a.id,c.name as contract_name,d.name as company_name,a.refund_amount,a.refund_date,c.customer_leader_id,b.leader_id,a.attach_group')
        ->where(['a.account_id'=>$id])
        ->group("id")
        ->select();
        
        foreach ($list as $k => $v) {
            $tmp = M("SysUser")->field("id,name")->where(['id'=>$list[$k]['customer_leader_id']])->find();
            $list[$k]['customer_leader'] = $tmp['name'];
            $tmp = M("SysUser")->field("id,name")->where(['id'=>$list[$k]['leader_id']])->find();
            $list[$k]['leader'] = $tmp['name'];
        }
        $this->ajaxReturn($list);
    }
    //付款列表
    public function payListAction($id){
        // 公司
        // 收款客户负责人
        // 合同名称
        // 收款商户负责人
        // 发生日期
        // 支出金额
        // 备注附件
        $list = M('wrkReceivablesRecord')->alias('a')
        ->join('LEFT JOIN wrk_receivables b ON b.id = a.receivables_id')
        ->join('LEFT JOIN wrk_agreement c ON c.id = b.contract_id')
        ->join('LEFT JOIN sys_branch d ON d.id = c.company_id')
        ->field('a.id,c.name as contract_name,d.name as company_name,a.pay_amount,a.pay_date,c.customer_leader_id,b.leader_id,b.attach_group')
        ->where(['a.account_id'=>$id])
        ->group("id")
        ->select();
        
        foreach ($list as $k => $v) {
            $tmp = M("SysUser")->field("id,name")->where(['id'=>$list[$k]['customer_leader_id']])->find();
            $list[$k]['customer_leader'] = $tmp['name'];
            $tmp = M("SysUser")->field("id,name")->where(['id'=>$list[$k]['leader_id']])->find();
            $list[$k]['leader'] = $tmp['name'];
        }
        $this->ajaxReturn($list);
    }

    public function activateAction($id,$status=1){
    	if ($status == 1) {
    		$status = 0;
    	} else {
    		$status = 1;
    	}
    	
		M('WrkReceivablesAccount')
        ->where(['id'=>$id])
    	->save(['status'=>$status,'is_wx'=>0]);
    	$this->ajaxReturn(array('code' => 0, 'message' => '操作成功'));
    }

    public function setWXAccountAction($id){
        M('WrkReceivablesAccount')
        ->where(['status'=>1,'branch_id'=>$this->_user_session->currBranchId])
        ->save(['is_wx'=>0]);
        M('WrkReceivablesAccount')
        ->where(['id'=>$id,'status'=>1,'branch_id'=>$this->_user_session->currBranchId])
        ->save(['is_wx'=>1]);
        $this->ajaxReturn(array('code' => 0, 'message' => '操作成功'));
    }

    protected function _before_list(&$list)
    {
        parent::_before_list($list);
        $begin_date = I('begin_date');
        $end_date = I('end_date');
    	if (empty($begin_date)) {
    		$begin_date = strtotime('Y-m-01');
    	}else{
    		$begin_date = strtotime($begin_date);
    	}
    	$end_date = I('end_date');
    	if (empty($end_date)) {
    		$end_date = strtotime(date('Y-m-t'));
    	}else{
    		$end_date = strtotime("$end_date +1 day");
    	}
    	$pay_date  = array('between',[$begin_date,$end_date]);

        $in_amount = 0;
        $out_amount = 0;
        foreach($list as $k => $v) {
			$condition = [];
			$condition['a.receivable_id'] = $v['id'];
			$condition['a.fina_time'] = $pay_date;
            $condition['a.fina_type'] = array('in',[-1,-2,1,2,12]);
            $finance = M('ComFinance')->alias('a')
            ->join('LEFT JOIN wrk_receivables_account c ON c.id = a.receivable_id')
            ->field('a.fina_id as id,a.fina_time,a.fina_type,a.fina_cash,a.third_fee,c.name as account_name,a.platform_fee')
            ->where($condition)
            ->select();
            $condition['a.fina_type'] = array('in',[-1,-2,-7,1,2,11,12]);
            $list[$k]['record_count'] = M('ComFinance')->alias('a')
            ->where($condition)
            ->count();

            $list[$k]['in_amount'] = 0;
            $list[$k]['out_amount'] = 0;
            foreach ($finance as $k1 => $v1) {
                if ($v1['fina_type'] > 0) {
                    $list[$k]['in_amount'] = $list[$k]['in_amount'] + ($v1['fina_cash'] - $v1['third_fee'] - $v1['platform_fee']);
                } else {
                    $list[$k]['out_amount'] = $list[$k]['out_amount'] + ($v1['fina_cash'] - $v1['third_fee'] - $v1['platform_fee']);
                }
            }
            $in_amount += $list[$k]['in_amount'];
            $out_amount += $list[$k]['out_amount'];
        }
        array_push($list,[
        	'id'=>'other',
        	'name'=>'总账',
			'in_amount'=>$in_amount,
            'out_amount'=>$out_amount
        ]);
    }

    protected function _before_detail(&$data) {
        parent::_before_detail($data);
    }    

}