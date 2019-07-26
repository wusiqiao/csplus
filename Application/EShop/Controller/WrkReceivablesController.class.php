<?php


namespace EShop\Controller;
// use Common\Lib\Controller\ComplexDataController;
use Think\Controller;
class WrkReceivablesController extends BaseController{

    public function indexAction(){
        $this->assign("title","收款管理");
        // $count = D("WrkReceivables")
        // ->alias('b')
        // ->join('LEFT JOIN wrk_receivables_notice a ON a.receivables_id = b.id')
        // ->join('LEFT JOIN wrk_agreement c ON c.id = b.contract_id')
        // ->join('LEFT JOIN sys_branch d ON d.id = c.company_id')
        // ->field('a.id as notice_id,a.receivables_id as id,a.pay_amount,a.pay_date,d.name as company_name,c.name as contract_name')
        // ->order("pay_date desc")
        // ->where(['a.branch_id' =>getBrowseBranchId(),'a.status'=>1])
        // ->count();
        $count = D("WrkReceivables")
        ->setDacFilter('b')
        ->join('LEFT JOIN wrk_receivables_notice a ON a.receivables_id = b.id')
        ->join('LEFT JOIN wrk_agreement c ON c.id = b.contract_id')
        ->join('LEFT JOIN sys_branch d ON d.id = c.company_id')
        ->field('a.id as notice_id,a.receivables_id as id,a.pay_amount,a.pay_date,d.name as company_name,c.name as contract_name')
        ->order("pay_date desc")
        ->where(['a.branch_id' =>getBrowseBranchId(),'a.status'=>1])
        ->count();
        $this->assign("count",$count);
        $this->display();
    }

    public function customerListAction(){
        $this->assign("title","收款列表");
        $this->display();
    }
    public function customerListSearchAction(){
        $page_index = I("page", 1);
        $page_size = 50;
        $show_status = ['未结束','已结束','已结束'];
        $_filter = array();
        $status = I('state');
        if (!empty($status)) {
            switch ($status) {
                case '1':
                    $_filter['a.status'] = 0;
                    break;
                case '2':
                    $_filter['a.status'] = array('in',[1,2]);
                    break;
            }
        }
        //默认取当天数据
        // $begin_date = I('begin_date');
        // if (!empty($begin_date)) {
        //     $begin_date = strtotime($begin_date);
        // }else{
        //     $begin_date = strtotime(date('Y-m-d'));
        // }
        // $end_date = I('end_date');
        // if (!empty($end_date)) {
        //     $end_date = strtotime($end_date) + (60*60)*24 - 1;
        // }else{
        //     $end_date = strtotime(date('Y-m-d')) + (60*60)*24 - 1;
        // }
        // $_filter['b.receivable_date'][] = array('between',[$begin_date,$end_date]);

        // $company_id = I('company_id');
        // if (!empty($company_id)) {
        //     $_filter['c.company_id'] = $company_id;
        // }
        // $leader_id = I('leader_id');
        // if (!empty($leader_id)) {
        //     $_filter['a.leader_id'] = $leader_id;
        // }
        // $customer_leader_id = I('customer_leader_id');
        // if (!empty($customer_leader_id)) {
        //     $_filter['c.customer_leader_id'] = $customer_leader_id;
        // }
        // $contract_id = I('contract_id');
        // if (!empty($contract_id)) {
        //     $_filter['a.contract_id'] = $contract_id;
        // }
        $keyword = I('keyword');
        if (!empty($keyword)) {
            $_filter['c.name'] =  array("like", "%$keyword%");;
        }
        // $parent_id = M('SysBranch')->where(['id'=>getBrowseBranchId()])->getField('parent_id');

        $_filter['c.company_id'] = $_SESSION['wrk_company_id'];
        // $_filter['a.branch_id'] = getBrowseBranchId();
        
        $_order = "c.start_time desc";
        $list = D('WrkReceivables')
        ->alias('a')
        ->join('LEFT JOIN wrk_agreement c ON c.id = a.contract_id')
        ->join('LEFT JOIN sys_branch d ON d.id = c.company_id')
        ->field("a.id,d.name as company_name,c.name as contract_name,c.agreement_money,a.status")
        ->where($_filter)
        ->page($page_index, $page_size)
        ->order($_order)->select();
        foreach ($list as $k => $v) {
            $actual_amount = 0;
            $advance = M("WrkReceivablesAdvance")
            ->field('id,pay_amount')
            ->where(['receivables_id' =>$v['id'] ])
            ->select();
            foreach ($advance as $k1 => $v1) {
                $actual_amount += (float)$v1['pay_amount'];
            }
            $record = M("wrkReceivablesRecord")
            ->field('id,pay_amount')
            ->where(['receivables_id' =>$v['id'] ])
            ->select();
            foreach ($record as $k1 => $v1) {
                $actual_amount += (float)$v1['pay_amount'];
            }

            $list[$k]['actual_amount'] = $actual_amount;
            $list[$k]['unpaid_amount'] = (float)$v['agreement_money'] - (float)$actual_amount;
            // $list[$k]['start_time'] = date('Y-m-d',$v['start_time']);
            $list[$k]['show_status'] = $show_status[$v['status']];
        }
        $result["list"] = $list;
        $this->ajaxReturn($result);
    }
    public function listAction(){
        $this->assign("title","收款列表");
        $this->display();
    }
    public function listSearchAction(){
        $page_index = I("page", 1);
        $page_size = 50;
        $show_status = ['未收','部分已收','已收','逾期'];
        $_order = array();
        // $this->_parseOrder($_order);
        $_filter = array();
        // $this->_parseFilter($_filter);
        // $_filter['b.receivable_date'] = [];
        //0-全部1-逾期2-未付3-已付
        $status = I('state');
        if (!empty($status)) {
            switch ($status) {
                case '1':
                    $_filter['b.receivable_date'][] = array('LT',strtotime(date('Y-m-d')));
                    $_filter['b.status'] = array('in',[0,1]);
                    break;
                case '2':
                    $_filter['b.status'] = array('in',[0,1]);
                    break;
                case '3':
                    $_filter['b.status'] = 2;
                    break;
            }
        }
        //默认取当天数据
        $begin_date = I('begin_date');
        if (!empty($begin_date)) {
            $begin_date = strtotime($begin_date);
        }else{
            $begin_date = strtotime(date('Y-m-d'));
        }
        $end_date = I('end_date');
        if (!empty($end_date)) {
            $end_date = strtotime($end_date) + (60*60)*24 - 1;
        }else{
            $end_date = strtotime(date('Y-m-d')) + (60*60)*24 - 1;
        }
        $_filter['b.receivable_date'][] = array('between',[$begin_date,$end_date]);

        // $company_id = I('company_id');
        // if (!empty($company_id)) {
        //     $_filter['c.company_id'] = $company_id;
        // }
        // $leader_id = I('leader_id');
        // if (!empty($leader_id)) {
        //     $_filter['a.leader_id'] = $leader_id;
        // }
        // $customer_leader_id = I('customer_leader_id');
        // if (!empty($customer_leader_id)) {
        //     $_filter['c.customer_leader_id'] = $customer_leader_id;
        // }
        // $contract_id = I('contract_id');
        // if (!empty($contract_id)) {
        //     $_filter['a.contract_id'] = $contract_id;
        // }
        $keyword = I('keyword');
        if (!empty($keyword)) {
            //$_filter['c.name'] =  array("like", "%$keyword%");;
            $_filter['_string'] = " c.name like '%$keyword%' or d.name like '%$keyword%'";
        }
        $_filter['b.id'] = array('exp','is not null');
        $_filter['a.branch_id'] = getBrowseBranchId();
        // $count = D('WrkReceivables')->setDacFilter("a")
        // ->join('LEFT JOIN wrk_receivables_item b ON b.receivables_id = a.id')
        // ->join('LEFT JOIN wrk_agreement c ON c.id = a.contract_id')
        // ->join('LEFT JOIN sys_branch d ON d.id = c.company_id')
        // ->field("a.id,c.agreement_sn as contract_no,d.name as company_name,c.customer_leader_id,c.name as contract_name,a.leader_id,b.receivables_amount,
        //     b.receivable_date,b.coupon_amount,b.actual_amount,b.offline_amount,b.balance_amount,b.wechat_amount,b.actual_date,b.status")
        // ->where($_filter)
        // ->count();
        $_order = "receivable_date desc";
        $list = D('WrkReceivables')
        ->setDacFilter("a")
        // ->alias('a')
        ->join('LEFT JOIN wrk_receivables_item b ON b.receivables_id = a.id')
        ->join('LEFT JOIN wrk_agreement c ON c.id = a.contract_id')
        ->join('LEFT JOIN sys_branch d ON d.id = c.company_id')
        ->join('LEFT JOIN wrk_receivables_account e ON e.id = b.account_id')
        ->field("a.id,b.id as item_id,d.name as company_name,c.name as contract_name,b.receivables_amount,b.receivable_date,b.status")
        ->where($_filter)
        ->page($page_index, $page_size)
        ->order($_order)->select();
        foreach ($list as $k => $v) {
            $list[$k]['receivable_date'] = date('Y-m-d',$v['receivable_date']);
            $list[$k]['show_status'] = $show_status[$v['status']];
            if (in_array($v['status'],[0,1]) && $v['receivable_date'] < strtotime(date('Y-m-d')) ){
                $list[$k]['status'] = 3;
                $list[$k]['show_status'] = $show_status[3];
            }
        }
        // $result["total"] = $count;
        $result["list"] = $list;
        $this->ajaxReturn($result);
    }
    public function noticeAction(){
        $this->assign("title","付款通知");
        $this->display();
    }
    public function noticeSearchAction(){
        $page_index = I("page", 1);
        $page_size = 50;
        $list = D("WrkReceivables")
        // ->alias('b')
        ->setDacFilter('b')
        ->join('LEFT JOIN wrk_receivables_notice a ON a.receivables_id = b.id')
        ->join('LEFT JOIN wrk_agreement c ON c.id = b.contract_id')
        ->join('LEFT JOIN sys_branch d ON d.id = c.company_id')
        ->field('a.id as notice_id,a.receivables_id as id,a.pay_amount,a.pay_date,d.name as company_name,c.name as contract_name')
        ->page($page_index, $page_size)
        ->order("pay_date desc")
        ->where(['a.branch_id' =>getBrowseBranchId(),'a.status'=>1])
        ->select();

        foreach ($list as $k => $v) {
            $list[$k]['pay_date'] = date('Y-m-d',$v['pay_date']);
        }
        $result["list"] = $list;
        $this->ajaxReturn($result);
    }
    public function detailAction($id,$item_id = null,$notice_id = null){
        if (IS_POST) {
            $show_status = ['未收','部分已收','已收'];
            //收款信息
            $wrkReceivables = M("WrkReceivables")->alias('a')->where(['a.id' => $id])->find();
            $wrkReceivables['detail_count'] = M("WrkReceivablesItem")
            ->where(['receivables_id' => $id])
            ->count();
            $actual_amount = 0;
            $advance = M("WrkReceivablesAdvance")
            ->field('id,pay_amount')
            ->where(['receivables_id' =>$id])
            ->select();
            foreach ($advance as $k => $v) {
                $actual_amount += (float)$v['pay_amount'];
            }
            $record = M("wrkReceivablesRecord")
            ->field('id,pay_amount')
            ->where(['receivables_id' =>$id])
            ->select();
            foreach ($record as $k => $v) {
                $actual_amount += (float)$v['pay_amount'];
            }
            // $unpaid_amount = (float)$paid_amount - (float)$actual_amount;
            $badDept = M("WrkBadDept")->alias('a')
            ->join('LEFT JOIN wrk_receivables_account b ON b.id = a.account_id')
            ->field('a.id,a.bad_dept_date,a.bad_dept_amount,a.attach_group,a.status')
            ->where(['a.receivables_id' =>$id])
            ->find();
            $wrkReceivables['actual_amount'] = $actual_amount;
            // $wrkReceivables['unpaid_amount'] = $unpaid_amount;
            $wrkReceivables['bad_dept_amount'] = $badDept['bad_dept_amount'];
            //合同信息
            $contract_id = $wrkReceivables['contract_id'];
            $condition = [];
            $condition["id"] = $contract_id;
            $wrkAgreement = M("WrkAgreement")->alias('a')
            ->join('LEFT JOIN sys_branch b ON b.id = a.company_id')
            ->join('LEFT JOIN sys_user c ON c.id = a.customer_leader_id')
            ->join('LEFT JOIN com_store d ON d.branch_id = a.branch_id')
            ->field('a.id,a.company_id,b.name as company_name,a.agreement_sn,a.sys_sn,a.name,a.agreement_money,a.start_time,a.finish_time,c.name as customer_leader_id,a.branch_id,d.unline_card_number')
            ->where(['a.id' => $contract_id])
            ->find();
            
            session("wrk_company_id",$wrkAgreement['company_id']);
            session("wrk_company_name",$wrkAgreement['company_name']);
            
            $wrkReceivables['unpaid_amount'] = (float)$wrkAgreement['agreement_money'] - (float)$actual_amount;

            // $data['notice_id'] = $notice_id;
            //本期收款计划
            $item = [];
            $items = M("WrkReceivablesItem")
                ->where(['receivables_id' =>$id])
                ->select();
            $item['period_number'] = '';
            $item['receivables_amount'] = '';
            $item['unpaid_amount'] = '';
            $item['coupon_amount'] = '';
            $item['wechat_amount'] = '';
            $i = 1;

            if (!empty($item_id) && $item_id != '') {
                foreach ($items as $k => $v) {
                    if ($item_id == $v['id']) {
                        $item = $v;
                        $item['show_status'] = $show_status[$v['status']];
                        if (empty($item['actual_date'])) {
                            $item['actual_date'] = '-';
                        } else {
                            $item['actual_date'] = date('Y-m-d',$v['actual_date']);
                        }                    
                        $item['receivable_date'] = date('Y-m-d',$v['receivable_date']);
                        $item['unpaid_amount'] = 
                        (float)$v['receivables_amount'] - (float)$v['actual_amount'];
                        $item['period_number'] ='第'.$i.'期';
                        break;
                    }
                    $i++;
                }
            } else {
                foreach ($items as $k => $v) {
                    if ($v['status'] != 2 && empty($item['period_number'])) {
                        $item = $v;
                        $item['show_status'] = $show_status[$v['status']];
                        if (empty($item['actual_date'])) {
                            $item['actual_date'] = '-';
                        } else {
                            $item['actual_date'] = date('Y-m-d',$v['actual_date']);
                        }                    
                        $item['receivable_date'] = date('Y-m-d',$v['receivable_date']);
                        $item['unpaid_amount'] = 
                        (float)$v['receivables_amount'] - (float)$v['actual_amount'];
                        $item['period_number'] ='第'.$i.'期';
                        break;
                    }
                    $i++;
                }
            }
            
            $result['wrkReceivables'] = $wrkReceivables;
            $result['wrkAgreement'] = $wrkAgreement;
            $result['item'] = $item;

            $this->ajaxReturn($result);
        } else {
            $this->assign("id",$id);
            $instance_permit = D(CONTROLLER_NAME)->getPermitValue($id);
            $this->assign("instance_permit",$instance_permit);
            $this->assign("item_id",$item_id);
            $this->assign("notice_id",$notice_id);
            $this->assign("title","收款详情");
            $this->display();
        }        
    }

    public function customerDetailAction($id,$item_id = null,$notice_id = null){
        if (IS_POST) {
            $show_status = ['未收','部分已收','已收'];
            //收款信息
            $wrkReceivables = M("WrkReceivables")->alias('a')->where(['a.id' => $id])->find();
            $wrkReceivables['detail_count'] = M("WrkReceivablesItem")
            ->where(['receivables_id' => $id])
            ->count();
            $actual_amount = 0;
            $advance = M("WrkReceivablesAdvance")
            ->field('id,pay_amount')
            ->where(['receivables_id' =>$id])
            ->select();
            foreach ($advance as $k => $v) {
                $actual_amount += (float)$v['pay_amount'];
            }
            $record = M("wrkReceivablesRecord")
            ->field('id,pay_amount')
            ->where(['receivables_id' =>$id])
            ->select();
            foreach ($record as $k => $v) {
                $actual_amount += (float)$v['pay_amount'];
            }
            // $unpaid_amount = (float)$paid_amount - (float)$actual_amount;
            $badDept = M("WrkBadDept")->alias('a')
            ->join('LEFT JOIN wrk_receivables_account b ON b.id = a.account_id')
            ->field('a.id,a.bad_dept_date,a.bad_dept_amount,a.attach_group,a.status')
            ->where(['a.receivables_id' =>$id])
            ->find();
            $wrkReceivables['actual_amount'] = $actual_amount;
            // $wrkReceivables['unpaid_amount'] = $unpaid_amount;
            $wrkReceivables['bad_dept_amount'] = $badDept['bad_dept_amount'];
            //合同信息
            $contract_id = $wrkReceivables['contract_id'];
            $condition = [];
            $condition["id"] = $contract_id;
            $wrkAgreement = M("WrkAgreement")->alias('a')
            ->join('LEFT JOIN sys_branch b ON b.id = a.company_id')
            ->join('LEFT JOIN sys_user c ON c.id = a.customer_leader_id')
            ->join('LEFT JOIN com_store d ON d.branch_id = a.branch_id')
            ->field('a.id,a.company_id,b.name as company_name,a.agreement_sn,a.sys_sn,a.name,a.agreement_money,a.start_time,a.finish_time,c.name as customer_leader_id,a.branch_id,d.unline_card_number')
            ->where(['a.id' => $contract_id])
            ->find();
            $wrkReceivables['unpaid_amount'] = (float)$wrkAgreement['agreement_money'] - (float)$actual_amount;

            // $data['notice_id'] = $notice_id;
            //本期收款计划
            $item = [];
            $items = M("WrkReceivablesItem")
                ->where(['receivables_id' =>$id])
                ->select();
            $item['period_number'] = '';
            $i = 1;

            if (!empty($item_id) && $item_id != '') {
                foreach ($items as $k => $v) {
                    if ($item_id == $v['id']) {
                        $item = $v;
                        $item['show_status'] = $show_status[$v['status']];
                        if (empty($item['actual_date'])) {
                            $item['actual_date'] = '-';
                        } else {
                            $item['actual_date'] = date('Y-m-d',$v['actual_date']);
                        }                    
                        $item['receivable_date'] = date('Y-m-d',$v['receivable_date']);
                        $item['unpaid_amount'] = 
                        (float)$v['receivables_amount'] - (float)$v['actual_amount'];
                        $item['period_number'] ='第'.$i.'期';
                        break;
                    }
                    $i++;
                }
            } else {
                foreach ($items as $k => $v) {
                    if ($v['status'] != 2 && empty($item['period_number'])) {
                        $item = $v;
                        $item['show_status'] = $show_status[$v['status']];
                        if (empty($item['actual_date'])) {
                            $item['actual_date'] = '-';
                        } else {
                            $item['actual_date'] = date('Y-m-d',$v['actual_date']);
                        }                    
                        $item['receivable_date'] = date('Y-m-d',$v['receivable_date']);
                        $item['unpaid_amount'] = 
                        (float)$v['receivables_amount'] - (float)$v['actual_amount'];
                        $item['period_number'] ='第'.$i.'期';
                        break;
                    }
                    $i++;
                }
            }
            
            $result['wrkReceivables'] = $wrkReceivables;
            $result['wrkAgreement'] = $wrkAgreement;
            $result['item'] = $item;
            $this->ajaxReturn($result);
        }      
    }

    //缴费详情页面
    public function customerAction($id) {
            $this->assign("id",$id);
            $this->assign("title","缴费详情");
            $this->display();    
    }
    //缴费列表页面
    public function paymentListAction($id,$prompt_item_id = null) {
            $condition = ['b.id' =>$prompt_item_id];
            $item = M("WrkReceivablesItem")
            ->alias("a")
            ->join('LEFT JOIN wrk_prompt_item b ON b.receivables_item_id = a.id')
            ->where($condition)
            ->field("a.*")
            ->find();
        $this->assign("item",$item);
        $this->assign("id",$id);
        $this->assign("title","缴费列表");
        $this->display();    
    }
    //立即缴费
    public function customerPayAction($id = null,$pay_amount = null,$prompt_item_id = null) {
        if (IS_POST) {
            $data['receivables_id'] = $id;
            $data['pay_date'] = time();
            $balance_amount = I('balance_amount');
            if (empty($balance_amount)) {
                $balance_amount = 0;
            }
            $pay_amount = I('pay_amount');
            $coupon_amount = I('reduce_cost');
            if (empty($pay_amount)) {
                $pay_amount = 0;
            }
            $data['pay_amount'] = (float)$pay_amount;
            $data['offline_amount'] = (float)$pay_amount - (float)$balance_amount - (float)$coupon_amount;
            $parent_id = M("SysBranch")->where(['id'=>getBrowseBranchId()])->getfield("parent_id");
            if ($parent_id == 0 || $parent_id == 1) {
                $data['branch_id'] = getBrowseBranchId();
            } else {
                $data['branch_id'] = $parent_id;
            }
            if ($pay_amount > 0) {
                $item = D("WrkReceivablesItem")
                ->where(['receivables_id'=>$id,'branch_id'=>$data['branch_id'],'status'=>array('neq',2)])
                ->find();
            }
            $data['order_sn'] = I('order_sn');
            $data['create_time'] = time();
            $data['creater_id'] = $_SESSION["user_id"];
            $data['attach_group'] = I('attach_group');
            $sp_ticket_stock_id = I('sp_ticket_stock_id');
            $data['coupon_amount'] = I('reduce_cost');
            //优惠卷付款
            if (!empty($sp_ticket_stock_id)) {
               D("WrkReceivables")->payByCoupon($id,$sp_ticket_stock_id,$data['branch_id'],$_SESSION["user_id"]);
            }
            //余额付款
            $company_id = I('company_id');
            $sysBranch = M("SysBranch")->where(['id' =>$company_id])->find();
            $sysBranch['balance_amount'] = $sysBranch['money'] - $sysBranch['money_auditing'];
            if ($sysBranch['balance_amount'] >= $balance_amount && $balance_amount > 0) {
                $money = $sysBranch['money'];
                $money = $money - $balance_amount;
                M("SysBranch")->where(['id' =>$company_id])->save(['money'=>$money]);
                if (empty($balance_amount)) {
                    $balance_amount = 0;
                }
                $data['balance_amount'] = $balance_amount;
                $record_data['balance_amount'] = $balance_amount;
                $rst = D("WrkReceivables")->payByBlance($id,$balance_amount,$company_id);
            }
            //生成付款记录
            $pay_amount = (float)$data['coupon_amount'] + (float)$data['balance_amount'];
            if ($pay_amount > 0) {
                $record_data['receivables_id'] = $id;
                $record_data['pay_date'] = time();
                $record_data['pay_amount'] = $pay_amount;
                $record_data['net_amount'] = 0;
                $record_data['coupon_amount'] = $data['coupon_amount'];
                $record_data['order_sn'] = $data['order_sn'];
                $record_data['created_time'] = time();
                $record_data['updated_time'] = time();
                $record_data['branch_id'] = $data['branch_id'];
                $record_id = M("wrkReceivablesRecord")->add($record_data);
            }

            //更新坏账
            D("WrkReceivables")->updateBadDept($id);
            if(!($data['offline_amount'] > 0)){
                $data['status'] = 0;
            }
            $notice_id = M("wrkReceivablesNotice")->add($data);
            if ($data['offline_amount'] > 0) {
                //线下付款日期
                $item = M("WrkReceivablesItem")
                ->where(['receivables_id' =>$id,'status'=>array('neq',2)])
                ->find();
                M('WrkReceivablesItem')
                ->where(['id'=>$item['id']])
                ->save(['actual_date'=>time()]);
                //待确认金额
                $unconfirmed_amount = D("WrkReceivables")->where(['id'=>$id])->getField('unconfirmed_amount');
                $unconfirmed_amount  = (float)$unconfirmed_amount + (float)$data['offline_amount'];
                D("WrkReceivables")->where(['id'=>$id])->save(['unconfirmed_amount' => $unconfirmed_amount]);
            }
            $pay_amount = (float)$data['offline_amount'] + (float)$data['coupon_amount'] + (float)$data['balance_amount'];
            D("WrkReceivables")->sendWXcustomerPayMessage($id,$pay_amount,"线下付款",$notice_id);
            $this->ajaxReturn(array('code'=>0,'message'=>'付款成功'));
        } else {
            //从移动客户端催款通知进入
            // if (!empty($prompt_item_id)) {
            //     $condition = ['b.id' =>$prompt_item_id,'a.actual_date'=>array('exp','is null')];
            //     $item = M("WrkReceivablesItem")
            //     ->alias("a")
            //     ->join('LEFT JOIN wrk_prompt_item b ON b.receivables_item_id = a.id')
            //     ->where($condition)
            //     ->find();
            //     if (empty($item)) {
            //         $this->error('催款通知已失效','/Index/user');
            //     }
            // }
            $rst = M('WrkReceivables')->alias("a")
                ->join('LEFT JOIN wrk_agreement b ON b.id = a.contract_id')
                ->join('LEFT JOIN sys_branch c ON c.id = b.company_id')
                ->join('LEFT JOIN com_store d ON d.branch_id = b.branch_id')
                ->field("a.id,a.contract_id,c.id as company_id,c.name as company_name,b.name as agreement_name,d.unline_payee,d.unline_bank_account,d.unline_card_number,c.money_auditing,c.money,a.attach_group")
                ->where(['a.id' =>$id])
                ->find();
            $rst['balance_amount'] = (float)$rst['money'] - (float)$rst['money_auditing'];
            $rst['pay_amount'] = $pay_amount;
            $rst['order_sn'] = getOrderNo(SERVICE_ORDER_SN);
            if ($rst['balance_amount'] > $rst['pay_amount']) {
                $rst['balance_max']  = $rst['pay_amount'];
            }else{
                $rst['balance_max']  = $rst['balance_amount'];
            }

            $parent_id = M("SysBranch")->where(['id'=>getBrowseBranchId()])->getfield("parent_id");
            $account = M('wrkReceivablesAccount')
                ->where(['is_wx' =>1,'branch_id'=>$parent_id])
                ->find();
            if (!empty($account)) {
                $rst['has_wx']  = 1;
            }else{
                $rst['has_wx']  = 0;
            }

        $mobile = M("SysUser")->where(['id' => $_SESSION['user_id']])->getField('mobile');
        $parent_id = M("SysBranch")->where(['id' => getBrowseBranchId()])->getField('parent_id');
            if ($parent_id == 0 || $parent_id == 1) {
                $branch_id = getBrowseBranchId();
            } else {
                $branch_id = $parent_id;
            }
            $ticketCount = M("SpTicketStock")->alias('a')
            ->join('LEFT JOIN sp_activity b ON b.id = a.activity_id')
            ->join('LEFT JOIN sp_activity_ticket c ON c.ticket_id = a.ticket_id')
            ->join('LEFT JOIN sp_ticket d ON d.id = a.ticket_id')
            ->where([
                'a.ticket_begin_date' => array('lt',time()),
                'a.ticket_end_date' => array('gt',time()),
                'd.least_cost' => array('elt',$pay_amount),
                'b.is_scope' => 0,
                'a.mobile' => $mobile, 
                'a.state' => 1,
                'b.activity_type' => 2,
                'b.branch_id' => $branch_id
            ])->field("a.ticket_end_date,a.id,d.least_cost,d.reduce_cost")
            ->count();
            $rst['pay_status'] = M("ComStore")->where(['branch_id'=>$branch_id
            ])->getfield("pay_status");
            $rst['wxpay_open'] = M("WxConfig")->where(['branch_id'=>$branch_id
            ])->getfield("wxpay_open");

            $this->assign('id',$id);
            $this->assign('ticketCount',$ticketCount);
            $this->assign('model',$rst);
            $this->assign('title','立即缴费');
            $this->display('customerPay');

        }
    }
    public function wechatPayAction($id,$order_sn,$total_fee,$balance_amount = 0,$reduce_cost = 0,
$sp_ticket_stock_id = null) {
        $arr = [];
        //微信统一下单接口调用
        $unifiedorder = $this->unifiedorder($id,$order_sn,$total_fee,$balance_amount,$reduce_cost,$sp_ticket_stock_id);
        if ($unifiedorder) {
            $rst = $unifiedorder['result'];
            $xml = $unifiedorder['xml'];
            $rst = simplexml_load_string($rst,'SimpleXMLElement', LIBXML_NOCDATA);
            $rst = json_decode(json_encode($rst),TRUE);
            if ($rst['return_code'] == 'SUCCESS') {
                $arr['appId'] = $rst['appid'];
                $arr['timeStamp'] = time();
                $arr['nonceStr'] = $this->getNonceStr();;
                $arr['package'] = 'prepay_id='.$rst['prepay_id'];
                $arr['signType'] = 'MD5';
                // $arr['paySign'] = $this->getSign($arr);
                $sign = $this->getSign($arr);
                $arr['paySign'] = $sign['sign'];
                $this->assign('string', $sign['string']);
            }
        }

        $rst = json_encode($rst);
        $this->assign('rst', $rst);
        $this->assign('xml', $xml);
        $this->assign('arr', $arr);
        // $arr = json_encode($arr);
        $this->assign('id', $id);
        $this->assign('orderid', $order_sn);
        $this->assign('price', $total_fee);
        // $this->assign('jsApiParameters', $arr);
        $this->display('wechatPay');
    }

    //微信支付统一下单接口生成订单
    protected function unifiedorder($id,$order_sn,$total_fee,$balance_amount = 0,$reduce_cost = 0,
$sp_ticket_stock_id = null) {
        $branch_id = M("SysBranch")->where(['id'=>getBrowseBranchId()])->getfield("parent_id");
        $branch_id = getBrowseBranchId();
        $wxConfig = M("WxConfig")->field('token,appid,appsecret,encoding_aeskey,wx_pay_key,wx_mchid')->where('branch_id = '.$branch_id)->find();
        $arr = [];
        $arr['appid'] = $wxConfig['appid'];
        $arr['attach'] = '托管交易';
        $arr['body'] = '消费';
        $arr['mch_id'] = $wxConfig['wx_mchid'];
        $arr['openid'] = $_SESSION['openid'];
        // $arr['openid'] = 'opkAT5vaEue_6mEeqii2HDMZ1xZo';
        $arr['total_fee'] = $total_fee*100;
        $arr['spbill_create_ip'] = get_client_ip();
        $arr['nonce_str'] = $this->getNonceStr();
        $arr['out_trade_no'] = $order_sn;
        $arr['trade_type'] = 'JSAPI';
        $url = ADMIN_HOST."ReqQueue/WeChatPay/order_sn/".$order_sn."/receivables_id/".$id."/pay_amount/".$total_fee."/balance_amount/".$balance_amount."/user_id/".$_SESSION['user_id']."/branch_id/".$branch_id;
        if (!empty($sp_ticket_stock_id)) {
            $url = $url."/reduce_cost/".$reduce_cost."/sp_ticket_stock_id/".$sp_ticket_stock_id;
        }
        $arr['notify_url'] = $url;
        $sign = $this->getSign($arr);
        $arr['sign'] = $sign['sign'];
        $rst['string'] = $sign['string'];
        $xml = $this->toXml($arr);
        // var_dump($xml);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,"https://api.mch.weixin.qq.com/pay/unifiedorder");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $rst['xml'] = $xml;
        $rst['result'] = curl_exec($ch);
        curl_close($ch);
        return $rst;
    }
    //生成随机数
    protected function getNonceStr() {
        $code = "";
        for ($i=0; $i > 10; $i++) {
            $code .= mt_rand(1000);        //获取随机数
        }
        $nonceStrTemp = md5($code);
        $nonce_str = mb_substr($nonceStrTemp, 4,36);      //MD5加密后截取32位字符
        return $nonce_str;
    }
    //生成签名
    protected function getSign($arr) {
        $branch_id = M("SysBranch")->where(['id'=>getBrowseBranchId()])->getfield("parent_id");
        $branch_id = getBrowseBranchId();
        $wxConfig = M("WxConfig")->field('token,appid,appsecret,encoding_aeskey,wx_pay_key,wx_mchid')->where('branch_id = '.$branch_id)->find();

        // $app_key = null;
        ksort($arr);
        $sign = "";
        foreach ($arr as $k => $v)
        {
            if($k != "sign" && $v != "" && !is_array($v)){
                $sign.= $k."=".$v."&";
            }
        }
        $sign = trim($sign,"&");
        $sign = $sign . "&key=".$wxConfig['wx_pay_key'];
        $rst['string'] = $sign;
        $sign = md5($sign);
        $sign = strtoupper($sign);
        $rst['sign'] = $sign;
        return $rst;
    }
    //转为xml
    protected function toXml($arr) {
        $xml = "<xml>";
        foreach ($arr as $k=>$v)
        {
            // if (is_numeric($val)){
                $xml.="<".$k.">".$v."</".$k.">";
            // }else{
            //     $xml.="<".$k."><![CDATA[".$v."]]></".$k.">";
            // }
        }
        $xml.="</xml>";
        return $xml;
    }
    //优惠卷列表
    public function ticketListAction($least_cost) {
        if (IS_POST) {
            $mobile = M("SysUser")->where(['id' => $_SESSION['user_id']])->getField('mobile');
            $parent_id = M("SysBranch")->where(['id' => getBrowseBranchId()])->getField('parent_id');
                if ($parent_id == 0 || $parent_id == 1) {
                    $branch_id = getBrowseBranchId();
                } else {
                    $branch_id = $parent_id;
                }
            $list = M("SpTicketStock")->alias('a')
            ->join('LEFT JOIN sp_activity b ON b.id = a.activity_id')
            ->join('LEFT JOIN sp_activity_ticket c ON c.ticket_id = a.ticket_id')
            ->join('LEFT JOIN sp_ticket d ON d.id = a.ticket_id')
            ->where([
                'a.ticket_begin_date' => array('lt',time()),
                'a.ticket_end_date' => array('gt',time()),
                'd.least_cost' => array('elt',$least_cost),
                'b.is_scope' => 0,
                'a.mobile' => $mobile, 
                'a.state' => 1,
                'b.activity_type' => 2,
                'b.branch_id' => $branch_id
            ])->field("a.ticket_end_date,a.id,d.least_cost,d.reduce_cost")
            // ->fetchSql(true)
            ->select();
            foreach ($list as $k => $v) {
                $list[$k]['ticket_end_date'] = date("Y-m-d",$v['ticket_end_date']) ;
            }
            $this->ajaxReturn($list);
        } else {
            $this->assign('least_cost',$least_cost);
            $this->display('ticketList');
        }

        


    }

    //到款确认
    public function payConfirmAction($id,$notice_id = null) {
        if (IS_POST) {
            $receivables = M("WrkReceivables")->where(['id' =>$id])->find();
            $agreement = M("WrkAgreement")->where(['id' =>$receivables['contract_id']])->find();
            if($agreement['company_id']){
                $customer_leader_id = M("SysBranch")->where("id = ".$agreement['company_id'])->getField("customer_leader_id");
            }
            $data['receivables_id'] = $id;
            $data['account_id'] = I('account_id');
            $data['pay_date'] = strtotime(I('pay_date'));
            $data['pay_amount'] = I('pay_amount');
            $data['offline_amount'] = I('net_amount');
            $data['poundage'] = I('poundage');
            $data['net_amount'] = I('net_amount');
            $data['order_sn'] = getOrderNo("CIZ_");
            $data['created_time'] = time();
            $data['updated_time'] = time();
            $data['attach_group'] = $receivables['attach_group'];
            $data['branch_id'] = getBrowseBranchId();
            $record_id = M("wrkReceivablesRecord")->add($data);

            $this->createLog($id,"到款确认".$data['pay_amount']."元,手续费".$data['poundage']."元");
            $this->pay($record_id);
            $unconfirmed_amount = D("WrkReceivables")->where(['id'=>$id])->getField('unconfirmed_amount');
            if ($unconfirmed_amount > 0) {
                $unconfirmed_amount  = (float)$unconfirmed_amount - (float)$data['pay_amount'];
                if ($unconfirmed_amount < 0) {
                    $unconfirmed_amount = 0;
                }
                D("WrkReceivables")->where(['id'=>$id])->save(['unconfirmed_amount' => $unconfirmed_amount]);
            }
            // D("WrkReceivables")->payByTimer($id,null);

            $sysBranch = M("SysBranch")->where(['id' =>$receivables['company_id']])->find();
            $sysBranch['balance_amount'] = $sysBranch['money'] - $sysBranch['money_auditing'];
            D("WrkReceivables")->sendWXConfirmMessage($id,$data['pay_amount'],$sysBranch['balance_amount']);

            $accumulated_amount = M("wrkReceivablesAccount")->where(['id'=>$data['account_id']])->getField('accumulated_amount');
            $accumulated_amount = $data['net_amount'] + $accumulated_amount;
            M("wrkReceivablesAccount")->where(['id'=>$data['account_id']])->save(['accumulated_amount'=>$accumulated_amount]);
            $notice_id = I('notice_id');
            if (!empty($notice_id)) {
                M("WrkReceivablesNotice")->where(['id' =>$notice_id])->save(['status'=>0]);
            }

            $recharge_data['company_id'] = I('company_id');
            $recharge_data['user_id'] = empty($customer_leader_id) ? 0 : $customer_leader_id;
            $recharge_data['money_type'] = FIN_CIZ_RECHARGE;
            $recharge_data['order_sn'] = $data['order_sn'];
            $recharge_data['pay_name'] = '到款确认充值';
            $recharge_data['third_fee'] = $data['poundage'];
            $recharge_data['account'] = $data['pay_amount'];
            $recharge_data['creator_id'] = $_SESSION["user_id"];
            $recharge_data['branch_id'] = getBrowseBranchId();
            $recharge_data['ctime'] = time();
            $recharge_data['source'] = FIN_RECEIVABLES_CONFIRMED;
            $recharge_data['pay_status'] = 1;
            $recharge_data['audit_time'] = time();
            $recharge_data['receivable_id'] =$data['account_id'];
            M("comRecharge")->add($recharge_data);
            $finance_data['fina_type'] = $recharge_data['source'];
            $finance_data['fina_cash'] = $data['pay_amount'];
            //$finance_data['fina_time'] = $data['pay_date'];
            $finance_data['fina_time'] = time();
            //$finance_data['user_id'] = $_SESSION['user_id'];
            $finance_data['branch_id'] = $recharge_data['branch_id'];
            $finance_data['company_id'] = I('company_id');
            $finance_data['order_sn'] = $recharge_data['order_sn'];
            $finance_data['third_fee'] = $data['poundage'];
            $finance_data['receivable_id'] = $data['account_id'];
            $finance_data['title'] = '到款确认充值';
            M("ComFinance")->add($finance_data);
            //到款确认后会在资金账户多一条充值记录，所以需要多一条余额消费记录抵消
            $finance_data_pay['fina_type'] = FIN_PROMPT_BALANCE_PAY;//缴费付款
            $finance_data_pay['fina_cash'] = $data['pay_amount'];
            //$finance_data_pay['fina_time'] = $record_data['pay_date'];
            $finance_data_pay['fina_time'] = time();
            $finance_data_pay['branch_id'] = $recharge_data['branch_id'];
            $finance_data_pay['company_id'] = $recharge_data['company_id'];
            $finance_data_pay['order_sn'] = $data['order_sn'];
            $finance_data_pay['third_fee'] = 0;
            $finance_data_pay['receivable_id'] = $data['account_id'];
            $finance_data_pay['title'] = '客户余额消费';
            //$finance_data_pay['user_id'] = $_SESSION['user_id'];
            M("ComFinance")->add($finance_data_pay);
            $branch_id = getBrowseBranchId();
            $branch_money = M("SysBranch")->where("id = $branch_id")->getField('money');
            M("SysBranch")->where("id = $branch_id")->setField('money',$branch_money+$data['pay_amount']);
            $this->ajaxReturn(array('code'=>0,'message'=>'到款确认成功'));
        } else {
            $receivables = M('WrkReceivables')->alias("a")
                ->join('LEFT JOIN wrk_agreement b ON b.id = a.contract_id')
                ->join('LEFT JOIN sys_branch c ON c.id = b.company_id')
                ->field("c.id,c.name as company_name,a.attach_group")
                ->where(['a.id' =>$id])
                ->find();
            $notice = M("wrkReceivablesNotice")
            ->where(['id' =>$notice_id,'status'=>1])
            ->find();

            $rst['id'] = $id;
            $rst['company_name'] = $receivables['company_name'];
            $rst['pay_date'] = date("Y-m-d");
            $rst['company_id'] = $receivables['id'];

            $item = M("WrkReceivablesItem")
                ->where(['receivables_id' =>$id])
                ->select();
            $unpaid_amount = 0;
            $rst['period_number'] = '';
            foreach ($item as $k => $v) {
                $unpaid_amount = (float)$unpaid_amount + (float)$v['receivables_amount'] - (float)$v['actual_amount'];
                if ($v['status'] != 2 && empty($rst['period_number'])) {
                    $rst['period_number'] ='第'.((int)$k+1).'期';
                }
            }
            $rst['unpaid_amount'] = $unpaid_amount;

            if (!empty($notice)) {
                $rst['notice_id'] = $notice['id'];
                $rst['pay_amount'] = $notice['offline_amount'];
                $rst['balance_amount'] = $notice['balance_amount'];
                $rst['poundage'] = 0;
                $rst['net_amount'] = $notice['offline_amount'];
            }
            $rst['attach_group'] = $receivables['attach_group'];
            $accountList = M("WrkReceivablesAccount")
            ->field('id as value,name as text')
            ->where(["branch_id"=>getBrowseBranchId()])
            ->select();
            $rst['accountList'] = json_encode($accountList);
            $this->assign('model',$rst);
            $this->display('payConfirm');
        }
    }
    //生成日志
    public function createLog($content = "",$operation = ACTION_NAME,$kind = 0) {
        $sysUser = M("SysUser")->where(['id'=>$_SESSION["user_id"]])->find();
        $branch_name = M("SysBranch")->where(['id'=>getBrowseBranchId() ])->getField('name');
        if (!empty($sysUser['staff_name'])) {
            $data["user_name"] = $sysUser['staff_name'];
        } else {
            $data["user_name"] = $sysUser['name'];
        }
        $data["branch_name"] = $branch_name;
        $data["kind"] = $kind;
        $data["func"] = CONTROLLER_NAME;
        $data["operation"] = $operation;
        $data["content"] = $content;
        $data["create_time"] = time();
        $data["ip"] = get_client_ip();
        M("SysLog")->add($data);
    }
    //坏账处理
    public function badDeptAction($id = null) {
        if (IS_POST) {
            $data['receivables_id'] = $id;
            $data['bad_dept_amount'] = I('bad_dept_amount');
            $data['bad_dept_date'] = time();
            $data['create_time'] = time();
            $data['attach_group'] = M("WrkReceivables")->where(['id' =>$id])->getField('attach_group');
            $data['branch_id'] = getBrowseBranchId();
            M("WrkReceivables")->where(['id' =>$id])->save(['status'=>2]);
            M("WrkBadDept")->add($data);
            $this->createLog($id,"add_badDept");
            D("WrkReceivables")->sendWXBadDeptMessage($id,$data['bad_dept_amount'],'');
            $this->ajaxReturn(array('code'=>0,'message'=>'坏账处理成功'));
        }
    }
    //到款确认后修改收款计划的已收金额
    protected function pay($record_id){
        $record = M("wrkReceivablesRecord")->where(['id' =>$record_id])->find();
        $receivables_id = $record['receivables_id'];
        $date = $record['pay_date'];
        $sum = $record['pay_amount'];
        $item = M("WrkReceivablesItem")
            ->where([
                'receivables_id' =>$receivables_id,
                'status'=>array('in',[0,1,3])
            ])
        ->order("id asc")->select();
        $item_arr = [];
        foreach ($item as $k => $v) {
            $unpay_amount = (float)$v['receivables_amount'] - (float)$v['actual_amount'];
            if ($sum >= $unpay_amount) {
                //已收金额与未收金额之和
                $item_arr['actual_amount'] = $v['receivables_amount'];
                //线下金额增加
                $item_arr['offline_amount'] = (float)$v['offline_amount'] + (float)$unpay_amount;
                $item_arr['actual_date'] = $date;
                $item_arr['status'] = 2;
                $item_arr['confirm_flag'] = 0;
                M('WrkReceivablesItem')->where(['id'=>$v['id']])->save($item_arr);
                M('wrk_receivables_item_record')->add([
                    'record_id'=>$record_id,
                    'item_id'=>$v['id']
                ]);
                $sum = (float)$sum - (float)$unpay_amount;
                if ($sum == 0) {break;}
            } else {
                $item_arr['actual_amount'] = (float)$v['actual_amount'] + (float)$sum;
                //线下金额增加
                $item_arr['offline_amount'] = (float)$v['offline_amount'] + (float)$sum;
                $item_arr['actual_date'] = time();
                $item_arr['status'] = 1;
                M('WrkReceivablesItem')->where(['id'=>$v['id']])->save($item_arr);
                M('wrk_receivables_item_record')->add([
                    'record_id'=>$record_id,
                    'item_id'=>$v['id']
                ]);
                break;
            }
        }
        $num = M("WrkReceivablesItem")->where([
                'receivables_id' =>$receivables_id,
                'status'=>array('neq',2)
            ])->count();

        if ($num == 0) {
            M("WrkReceivables")->where(['id' =>$receivables_id])->save(['status'=>1]);
        }
    }
    
    //获取收款计划
    public function getItemAction($id,$notice_id = null,$type = 1) {
        if (IS_POST) {
            $condition = ['receivables_id' =>$id];
            // if ($type == 1) {
            //     $condition['actual_amount'] = array('gt',0);
            // }
            $item = M("WrkReceivablesItem")
            ->field('id,receivable_date,receivables_amount,actual_date,actual_amount,coupon_amount,offline_amount,balance_amount,wechat_amount,status,begin_date,end_date,attach_group,press_last_date')
            ->where($condition)
            ->select();
            $unconfirmed_amount = D("WrkReceivables")->where(['id'=>$id])->getField('unconfirmed_amount');
            $notice_sum = (float)M("wrkReceivablesNotice")->where(['id' =>$notice_id,'status'=>1])->getField("offline_amount");
            if (empty($notice_sum)) {
                $notice_sum = 0;
            }
            $status = ["未收","部分已收","已收","逾期"];
            $costmer_status = ["未付","部分已付","已付","逾期"];

            foreach ($item as $k => $v) {
                $item[$k]['show_receivables_date'] = !empty($v['receivable_date'])?date('Y-m-d',$v['receivable_date']):'-';
                $item[$k]['show_actual_date'] = !empty($v['actual_date'])?date('Y-m-d',$v['actual_date']):'-';
                $item[$k]['show_begin_date'] = !empty($v['begin_date'])?date('Y-m-d',$v['begin_date']):'-';
                $item[$k]['show_end_date'] = !empty($v['end_date'])?date('Y-m-d',$v['end_date']):'-';
                $item[$k]['show_press_last_date'] = !empty($v['press_last_date'])?date('Y-m-d',$v['press_last_date']):'-';
                //查询跟收款计划相关的账户和记录
                $account = M("wrkReceivablesAccount")
                ->alias('a')
                ->join('LEFT JOIN wrk_receivables_record b ON b.account_id = a.id')
                ->join('LEFT JOIN wrk_receivables_item_record c ON c.record_id = b.id')
                ->field('a.id,a.name')
                ->where(['c.item_id' =>$v['id']])
                ->select();
                $tmp = [];
                foreach ($account as $k1 => $v1) {
                    array_push($tmp,$v1['name']);
                }
                $tmp = array_unique($tmp);
                $tmp = implode(",",$tmp);
                if($tmp == '') {
                    $item[$k]['account_name'] = '-';
                }else{
                    $item[$k]['account_name'] = $tmp;
                    
                }
                $item[$k]['unpaid_amount'] = $v['receivables_amount'] - $v['actual_amount'];

                $promptItem = M("WrkPromptItem")
                ->where([
                    'receivables_item_id' =>$v['id'],
                    'press_last_date'=>array('exp','is not null')
                ])->select();
                if (!empty($promptItem)) {
                    $item[$k]['prompt_flag'] = 1; 
                }else{
                    $item[$k]['prompt_flag'] = 0;
                }

                if ($notice_sum > 0) {
                    if ($notice_sum > ($v['receivables_amount'] - $v['actual_amount'])) {
                        $notice_sum = $notice_sum - ($v['receivables_amount'] - $v['actual_amount']);
                    }else{
                        $notice_sum = 0;
                    }
                    $item[$k]['notice_flag'] = 1; 
                }else{
                    $item[$k]['notice_flag'] = 0;
                }

                if ($unconfirmed_amount > 0) {
                    if ($unconfirmed_amount > ($v['receivables_amount'] - $v['actual_amount'])) {
                        $item[$k]['unconfirmed_amount'] = $v['receivables_amount'] - $v['actual_amount'];
                        $unconfirmed_amount = $unconfirmed_amount - ($v['receivables_amount'] - $v['actual_amount']);
                    }else{
                        $item[$k]['unconfirmed_amount'] = $unconfirmed_amount; 
                        $unconfirmed_amount = 0;
                    }
                    $item[$k]['confirm_flag'] = 1; 
                }else{
                    $item[$k]['unconfirmed_amount'] = '-';
                    $item[$k]['confirm_flag'] = 0;
                }

                if (in_array($v['status'],[0,1])){
                    if ($v['receivable_date'] < strtotime(date('Y-m-d'))) {
                        $item[$k]['status'] = 3;
                    }
                }

                $item[$k]['show_status'] = $status[$item[$k]['status']];
                $item[$k]['show_costmer_status'] = $costmer_status[$item[$k]['status']];      
                $item[$k]['prompt_item_id'] = M("WrkPromptItem")->where(['receivables_item_id' =>$v['id']])->getField('id');
            }
            $this->ajaxReturn($item);
        } else {
            $this->assign("id",$id);
            $this->assign("title","收款列表");
            $this->display('itemList');
        }
    }

    //筛选条件
    public function parseFilter($postData){
        $condition = [];
        //$condition['a.branch_id'] = getBrowseBranchId();
        $condition['a.branch_id'] = 34;
        if($postData['state'] != ""){
            $condition['a.state'] = $postData['state'];
        }
        if($postData['wip_state'] == 1){
            //开票状态已结束
            $condition['wip.state'] = array("in","1,2");
            $condition['_string'] = "wip.creater_id is not NULL";
        }elseif($postData['wip_state'] != ""){
            $condition['wip.state'] = $postData['wip_state'];
            $condition['_string'] = "wip.creater_id is not NULL";
        }
        if($postData['wr_state'] == 1){
            //收款状态已结束
            $condition['wr.status'] = array("in","1,2");
        }elseif($postData['wr_state'] !=""){
            $condition['wr.status'] = $postData['wr_state'];
        }
        $condition["a.name"] = array("like","%".$postData['keyword']."%");
        if($postData['origin'] != ""){
            $condition["a.origin"] = $postData['origin'];
        }
        return $condition;
    }

    //获取收款各模块负责人姓名和联系方式
    public function getModuleLeaderInfo(&$data){
        $module = array("","wip_","wr_","wp_");
        foreach ($module as $k=>$v){
            if($data[$v.'leader_id']){
                $leader = M("SysUser")->where("id = ".$data[$v.'leader_id'])->field("staff_name,name,mobile")->find();
                $data[$v.'leader_name'] = $leader['staff_name'] == "" ? $leader['name'] : $leader['staff_name'];
                $data[$v.'leader_mobile'] = $leader['mobile'];
            }
        }
    }


}