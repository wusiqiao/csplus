<?php


namespace EShop\Controller;
use Think\Controller;
// use Common\Lib\Controller\ComplexDataController;
class WrkPromptController extends BaseController{

    public function indexAction(){
        $this->assign("title","催款管理");
        $this->display();
    }

        public function listAction(){
        $this->assign("title","催款列表");
        $this->display();
    }
    public function listSearchAction(){
        $page_index = I("page", 1);
        $page_size = 50;
        $show_status = ['未催款','已催款','已取消'];
        // 0-"未催款",1-"已催款",2-"已取消"
        $status = I('state');

        switch ($status) {
            case '1':
            $_filter['b.status'] = 0;
            break;
            case '2':
            $_filter['b.status'] = 1;
            break;
            case '3':
            $_filter['b.status'] = 0;
            $_filter['e.status'] = 2;
            break;
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
      $_filter['e.receivable_date'] = array('between',[$begin_date,$end_date]);

      //   $company_id = I('company_id');
      //   if (!empty($company_id)) {
      //       $_filter['c.company_id'] = $company_id;
      //     }
      //   $leader_id = I('leader_id');
      //   if (!empty($leader_id)) {
      //       $_filter['a.leader_id'] = $leader_id;
      //     }
      //   $customer_leader_id = I('customer_leader_id');
      //   if (!empty($customer_leader_id)) {
      //       $_filter['c.customer_leader_id'] = $customer_leader_id;
      //   }
      // if ($is_related==1) {
      //   $_filter['a.is_related'] = 1;
      // } else {
      //   $_filter['a.is_related'] = 0;
      // }
      // $contract_id = I('contract_id');
      // if (!empty($contract_id)) {
      //   $_filter['a.contract_id'] = $contract_id;
      // }

        $_filter['b.id'] = array('exp','is not null');
        $_order = "receivable_date desc";
        $list = D('WrkPrompt')->setDacFilter("a")
        // ->alias("a")
        ->join('LEFT JOIN wrk_prompt_item b ON b.prompt_id = a.id')
        ->join('LEFT JOIN wrk_agreement c ON c.id = a.contract_id')
        ->join('LEFT JOIN sys_branch d ON d.id = c.company_id')
        ->join('LEFT JOIN wrk_receivables_item e ON e.id = b.receivables_item_id')
        // ->join('LEFT JOIN wrk_prompt_date f ON f.prompt_item_id = b.id')
        // f.prompt_date,
        ->field("a.id,b.id as item_id,d.name as company_name,c.name as contract_name,e.status as receivable_status,b.press_last_date,b.status,b.id as prompt_item_id,e.receivable_date,e.receivables_amount")
        ->where($_filter)
        ->page($page_index, $page_size)->order($_order)->select();

        foreach ($list as $k => $v) {


            $list[$k]['receivable_date'] = date('Y-m-d',$v['receivable_date']);
            $list[$k]['show_status'] = $show_status[$v['status']];
            if ($v['receivable_status'] ==2 && $v['status']==0) {
              $list[$k]['status'] = $v['status'] = 2;
              $list[$k]['show_status'] = $show_status[2];
            }
        }
        // $result["total"] = $count;
        $result["list"] = $list;
        $this->ajaxReturn($result);
    }

    public function detailAction($id,$item_id,$notice_id = null){
        if (IS_POST) {
            $show_status = ['未催款','已催款','已取消'];
            $wrkPrompt = M("WrkPrompt")->alias('a')->where(['a.id' => $id])->find();
            //收款信息
            $wrkReceivables = M("WrkReceivables")->alias('a')->where(['a.contract_id' => $wrkPrompt['contract_id']])->find();
            $wrkReceivables['detail_count'] = M("WrkReceivablesItem")
            ->where(['receivables_id' => $id])
            ->count();
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
            $wrkReceivables['actual_amount'] = $actual_amount;
            // $wrkReceivables['unpaid_amount'] = $unpaid_amount;
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
            $item = M("WrkPromptItem")
                ->where(['id' =>$item_id])
                ->find();
            $items = M("WrkReceivablesItem")
                ->where(['receivables_id' =>$wrkReceivables['id']])
                ->select();
            $item['period_number'] = '';
            $i = 1;

            foreach ($items as $k => $v) {
                if ($v['id'] == $item['receivables_item_id'] ) {
                    // $item = $v;
                    $item['show_status'] = $show_status[$item['status']];
                    if ($v['status'] ==2 && $item['status']==0) {
                      $item['show_status'] = $show_status[2];
                    }
                    $dates = M("WrkPromptDate")
                    ->where(['prompt_item_id' =>$item['id'],'is_checked'=>1])
                    ->select();
                    foreach ($dates as $k1 => $v1) {
                        $dates[$k1]['prompt_date'] =  date('Y-m-d H:i:s',$v1['prompt_date']);
                    }
                    $item['dates'] = $dates;
                    $item['receivable_date'] = date('Y-m-d',$v['receivable_date']);
                     $item['receivables_amount'] = $v['receivables_amount'];
                    $item['unpaid_amount'] = 
                    (float)$v['receivables_amount'] - (float)$v['actual_amount'];
                    $item['period_number'] ='第'.$i.'期';
                    break;
                }
                $i++;
            }
            $result['wrkReceivables'] = $wrkReceivables;
            $result['wrkAgreement'] = $wrkAgreement;
            $result['item'] = $item;
            $this->ajaxReturn($result);
        } else {
            $instance_permit = D(CONTROLLER_NAME)->getPermitValue($id);
            $this->assign("id",$id);
            $this->assign("item_id",$item_id);
            $this->assign("notice_id",$notice_id);
            $this->assign("title","收款详情");
            $this->display();
        }        
    }

  public function sendMessageAction($id = null,$prompt_item_id = null) {
    if (IS_POST) {
        $amount = I('unpaid_amount');
        $status = 1;
        M('wrkPromptItem')->where([
          'id'=> I('prompt_item_id'),
          'branch_id'=>getBrowseBranchId()
        ])->save(['press_last_date'=>time(),'status'=>$status]);
        $rst = D("WrkPrompt")->sendWXPromptMessage($id,$amount,$prompt_item_id,I('date'));

        $receivables = M("WrkReceivables")->alias('a')
        ->join('LEFT JOIN wrk_prompt b ON b.contract_id = a.contract_id')
        ->where(['b.id' => $id])->field("a.id")->find();

        M("WrkReceivables")->where(['id'=>$receivables['id']])->save(['new_message'=>1]);
        $this->ajaxReturn(array('code'=>0,'message'=>'发送催款通知成功','rst'=>$rst));

    } else {
        $rst['id'] = $id;
        $rst['receivables_amount'] = I('receivables_amount');
        $rst['unpaid_amount'] = I('unpaid_amount');
        $rst['prompt_item_id'] = $prompt_item_id;
        $this->assign('model',$rst);
        $this->display('sendMessage');
    }
  }

}