<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\ComplexDataController;

class  WrkPromptController extends ComplexDataController {
  public function listAction($is_related = 1) {
    $this->assignPermissions();
    $page_index = I("page/d", 1);
    $page_size = I("rows/d", 1024);
    $_order = array();
    $this->_parseOrder($_order);
    $_filter = array();
    $this->_parseFilter($_filter);

    // 0-"未催款",1-"已催款",2-"已取消",3-"未查看",4-'已查看'
      $status = I('status');
      if ($status != "all") {
        switch ($status) {
          case '2':
            $_filter['b.status'] = 0;
            $_filter['e.status'] = 2;
          break;
          default:
            $_filter['b.status'] = $status;
          break;
        }
      }
      //默认取当天数据
      $begin_date = I('begin_date');
      if (!empty($begin_date)) {
      	$begin_date = strtotime($begin_date);
      }
      // else{
      // 	$begin_date = strtotime(date('Y-m-d'));
      // }
      $end_date = I('end_date');
      if (!empty($end_date)) {
      	$end_date = strtotime($end_date) + (60*60)*24 - 1;
      }
      // else{
      // 	$end_date = strtotime(date('Y-m-d')) + (60*60)*24 - 1;
      // }
      if (!empty($end_date) && !empty($begin_date)) {
  		  $_filter['f.prompt_date'] = array('between',[$begin_date,$end_date]);
      }

  		$company_id = I('company_id');
  		if (!empty($company_id)) {
  			$_filter['c.company_id'] = $company_id;
          }
      	$leader_id = I('leader_id');
  		if (!empty($leader_id)) {
          	$_filter['a.leader_id'] = $leader_id;
          }
      	$customer_leader_id = I('customer_leader_id');
  		if (!empty($customer_leader_id)) {
        	$_filter['c.customer_leader_id'] = $customer_leader_id;
        }
      if ($is_related==1) {
      	$_filter['a.is_related'] = 1;
      } else {
      	$_filter['a.is_related'] = 0;
      }
      $contract_id = I('contract_id');
      if (!empty($contract_id)) {
        $_filter['a.contract_id'] = $contract_id;
      }

      $_filter['b.id'] = array('exp','is not null');
      // $_filter['a.branch_id'] = $this->_user_session->currBranchId;
      $count = D('WrkPrompt')->setDacFilter("a")
      ->join('LEFT JOIN wrk_prompt_item b ON b.prompt_id = a.id')
      ->join('LEFT JOIN wrk_agreement c ON c.id = a.contract_id')
      ->join('LEFT JOIN sys_branch d ON d.id = c.company_id')
      ->join('LEFT JOIN wrk_receivables_item e ON e.id = b.receivables_item_id')
      ->join('LEFT JOIN wrk_prompt_date f ON f.prompt_item_id = b.id')
      ->field("a.id,c.agreement_sn as contract_no,d.name as company_name,c.customer_leader_id,c.name as contract_name,a.leader_id,e.status as receivable_status,a.press_mode,b.prompt_date,b.press_last_date,b.status,b.id as prompt_item_id,a.is_related")
      ->where($_filter)
      ->count();
      $_order = "prompt_date desc";
      $list = D('WrkPrompt')->setDacFilter("a")
      ->join('LEFT JOIN wrk_prompt_item b ON b.prompt_id = a.id')
      ->join('LEFT JOIN wrk_agreement c ON c.id = a.contract_id')
      ->join('LEFT JOIN sys_branch d ON d.id = c.company_id')
      ->join('LEFT JOIN wrk_receivables_item e ON e.id = b.receivables_item_id')
      ->join('LEFT JOIN wrk_prompt_date f ON f.prompt_item_id = b.id')
      ->field("a.id,c.agreement_sn as contract_no,d.name as company_name,c.customer_leader_id,c.company_id,c.name as contract_name,a.leader_id,e.status as receivable_status,a.press_mode,f.prompt_date,b.press_last_date,b.status,b.id as prompt_item_id,a.is_related")
      ->where($_filter)
      ->page($page_index, $page_size)->order($_order)->select();
      foreach ($list as $k => $v) {
    	// 	$tmp = M("SysUser")->field("id,name")->where(['id'=>$list[$k]['customer_leader_id']])->find();
  			// $list[$k]['customer_leader_id'] = $tmp['name'];
        $list[$k]['customer_leader_name'] = $this->getCustomerleader('WrkPrompt',$v['company_id']);
      		$tmp = M("SysUser")->field("id,name")->where(['id'=>$list[$k]['leader_id']])->find();
  			$list[$k]['leader_id'] = $tmp['name'];
        if ($v['receivable_status'] ==2 && $v['status']==0) {
          $list[$k]['status'] = $v['status'] = 2;
        }
      }

      $result["total"] = $count;
      $result["rows"] = $list;
      header('Content-Type:application/json; charset=utf-8');
      exit(json_encode($result));
    }

    protected function getCustomerleader($module,$company_id){
        $userData = D("WrkAgreement")->getCustomerUserData($module,$company_id);
        $str = '';
        foreach($userData as $k => $v) {
            if (empty($v['staff_name'])) {
                $str .= $v['name'].';';
            }else{
                $str .= $v['staff_name'].';';
            }
        }
        $str = rtrim($str,";");
        return $str;
    }

    
    protected function _before_write($type, &$data)
    {
        parent::_before_write($type, $data);
        $data['update_time'] = time();
        $data['updater_id'] = $this->_user_session->userId;
        if ($type == 1) {
        	$data['create_time'] = time();
        	$data['creater_id'] = $this->_user_session->userId;
        }
    }

	protected function _before_detail(&$data) {
        $this->assignPermissions();
        $contract_id = $data['contract_id'];
        $condition = [];
        $condition["id"] = $contract_id;
        $wrkAgreement = M("WrkAgreement")->alias('a')
        ->join('LEFT JOIN sys_branch b ON b.id = a.company_id')
        ->join('LEFT JOIN sys_user c ON c.id = a.customer_leader_id')
        ->join('LEFT JOIN wrk_receivables d ON d.contract_id = a.id')
        ->field('a.id,a.company_id,b.name as company_name,a.agreement_sn,a.sys_sn,a.name,a.agreement_money,a.start_time,a.finish_time,c.name as customer_leader_id,a.branch_id,d.id as receivables_id,d.attach_group')
        ->where(['a.id' => $contract_id])
    	->find();
      if ($data['is_related'] == 0 && I('status') == 3) {
        $prompt_item_id = I('prompt_item_id');
        M("WrkPromptItem")->where(['id' => $prompt_item_id])->save(['status'=>4]);
      }

    	if (!empty($wrkAgreement)) {
    		$wrkAgreement['start_time'] = date('Y-m-d',$wrkAgreement['start_time']);
    		$wrkAgreement['finish_time'] = date('Y-m-d',$wrkAgreement['finish_time']);
    	}
      $wrkAgreement['customer_leader_name'] = $this->getCustomerleader('WrkPrompt',$wrkAgreement['company_id']);
      $data["wrkAgreement"] = $wrkAgreement;
      $data['attach_group'] = $wrkAgreement['attach_group'];
      $data["service_man_name"] =  M("SysUser")->where(['id' => $data['leader_id'] ])->getField("name");
      //是否可见人
        if($data['visiblers']){
            $user_id = $this->_user_session->userId;
            $visiblers = explode(",",$data['visiblers']);
            $data['is_visibler'] = in_array($user_id,$visiblers);
        }
   }

  //新增催款任务弹窗
  public function addPromptAction($contract_id,$is_related = 0) {
    $this->assignPermissions();
    $wrkAgreement = M('wrk_agreement')->alias('a')
    ->join('LEFT JOIN sys_branch b ON b.id = a.company_id')
    ->join('LEFT JOIN sys_user c ON c.id = a.customer_leader_id')
    ->join('LEFT JOIN wrk_receivables d ON d.contract_id = a.id')
    ->field('a.id,a.company_id as company_id,b.name as company_name,a.agreement_sn,a.sys_sn,a.name,a.agreement_money,a.start_time,a.finish_time,c.name as customer_leader_id,a.branch_id,d.id as receivables_id,d.attach_group')
      ->where(['a.id' => $contract_id])
    ->find();
    if (!empty($wrkAgreement)) {
      $wrkAgreement['start_time'] = date('Y-m-d',$wrkAgreement['start_time']);
      $wrkAgreement['finish_time'] = date('Y-m-d',$wrkAgreement['finish_time']);
    }
    
    $sysUser = M("SysUser")->where(['id'=>$this->_user_session->userId])->find();
    if (!empty($sysUser['staff_name'])) {
        $data["service_man_name"] = $sysUser['staff_name'];
    } else {
        $data["service_man_name"] = $sysUser['name'];
    }
    $data["leader_id"] = $this->_user_session->userId;
    $wrkAgreement['customer_leader_name'] = $this->getCustomerleader('WrkPrompt',$wrkAgreement['company_id']);
    $data["wrkAgreement"] = $wrkAgreement;
    $data['is_related'] = $is_related;
    $data['attach_group'] = $wrkAgreement['attach_group'];
    $this->assign("model", $data);
    $this->display('edit');
  }

  //保存催款任务
  public function savePromptAction() {
    $post_data = I('post.');
    $data = [];
    foreach ($post_data['data'] as $k => $v) {
      if ($v['name']=='visiblers' || $v['name']=='collaborators') {
        $data[$v['name']][] = $v['value'];
      } else {
        $data[$v['name']] = $v['value'];
      } 
    }

    $item = $post_data['item'];

    $leader_id = $data['leader_id'];
    $visiblers = $data['visiblers'];
    $collaborators = $data['collaborators'];

    $data['visiblers'] = implode(",", $data['visiblers']);
    $data['collaborators'] = implode(",", $data['collaborators']);

    $this->_before_write(1, $data);
    $last_id = M('WrkPrompt')->add($data);
    D('WrkReceivables')
    ->updateAccesData('WrkPrompt',$last_id,$leader_id,$this->_user_session->currBranchId,$visiblers,$collaborators);
    if ($post_data['is_related'] == 1) {
      $date = $post_data['date'];
      $item_arr = [];
      foreach ($item as $k => $v) {
        $item_arr['prompt_id'] = $last_id;
        $item_arr['status'] = 0;
        $item_arr['branch_id'] = $this->_user_session->currBranchId;
        $item_arr['create_time'] = time();
        $item_arr['is_checked'] = $item[$k]['is_checked'];
        $item_arr['receivables_item_id'] = $item[$k]['id'];
        $prompt_item_id = M('wrkPromptItem')->add($item_arr);
        foreach ($date as $k1 => $v1) {
            $date_arr['prompt_item_id'] = $prompt_item_id;
            $date_arr['prompt_date'] = $v1[$k]['prompt_date'];
            $date_arr['title'] = $v1[$k]['title'];
            $date_arr['is_checked'] = $v1[$k]['is_checked'];
            M('wrkPromptDate')->add($date_arr);
        }
      }
      D("WrkPrompt")->sendMessageByTimer($last_id,null);
    }else{
      foreach ($item as $k => $v) {
        $item[$k]['prompt_id'] = $last_id;
        $item[$k]['status'] = 3;
        $item[$k]['branch_id'] = $this->_user_session->currBranchId;
        $item[$k]['create_time'] = time();
        $prompt_item_id = M('wrkPromptItem')->add($item[$k]);
        $date['prompt_item_id'] = $prompt_item_id;
        $date['prompt_date'] = $item[$k]['prompt_date'];
        M('wrkPromptDate')->add($date);
      }
    }
    $this->ajaxReturn(array('code'=>0,'message'=>'新增催款计划成功'));
  }

  public function getItemAction($id = null) {
      $item = M("WrkPromptItem")->alias('a')
      ->join('LEFT JOIN wrk_prompt_date b ON b.prompt_item_id = a.id')
      ->field('a.id,a.attach_group,b.prompt_date')
      ->where(['a.prompt_id' =>$id])
    ->select();
    $this->ajaxReturn($item);
  }

  public function getRelatedItemAction($id = null,$receivables_id = null) {
    //辅组初始化数组
    $receivablesItem = M("wrkReceivablesItem")
      ->where(['receivables_id' =>$receivables_id])
    ->select();

    $temp = [];
    foreach ($receivablesItem as $k => $v) {
      $temp[$v['id']]['prompt_date']  = '';
      $temp[$v['id']]['is_checked']  = '';
    }
    //获取催款计划，重新组合
    $_order = "id asc,prompt_date_id asc";
    $item = M("WrkPromptItem")->alias('a')
      ->join('LEFT JOIN wrk_prompt_date b ON b.prompt_item_id = a.id')
      ->field('a.id,a.receivables_item_id,b.is_checked,b.id as prompt_date_id,b.prompt_date,b.title')
      ->where(['a.prompt_id' =>$id])
      ->order($_order)
      ->select();
      $tmp = null;
      $rst = [];
      $date = [];
      $is_checked = [];
      $prompt_item_ids = [];
      $flag = 0;
      foreach ($item as $k => $v) {
        if ($v['id'] != $tmp) {
          $i=0;
          $tmp = $v['id'];
          $prompt_item_ids[] = $v['id'];
        }
        // $is_related[$v['id']] = $v['is_checked'];
        if (empty($date[$i])) {
          $date[$i] = $temp;
        }
        if (!empty($v['prompt_date_id'])) {
          $flag = 1;
        }
        $date[$i][$v['receivables_item_id']]['prompt_date'] = $v['prompt_date'];
        $date[$i][$v['receivables_item_id']]['is_checked'] = $v['is_checked'];
        $date[$i][$v['receivables_item_id']]['title'] = $v['title'];
        // $is_checked[$i][$v['id']] = $v['is_checked'];
        $i++;
      }
      if ($flag==0) {
        $date = [];
      }
      //格式化
      foreach ($date as $k => $v) {
        $date[$k] = array_values($date[$k]);
      }
      $rst['date'] = $date;
      $rst['prompt_item_ids'] = $prompt_item_ids;
      // $rst['is_related'] = array_values($is_related);
    $this->ajaxReturn($rst);
  }

  //编辑收款任务
  public function editPromptAction($id) {
    $post_data = I('post.');
    $data = [];
    foreach ($post_data['data'] as $k => $v) {
        if ($v['name']=='visiblers' || $v['name']=='collaborators') {
            $data[$v['name']][] = $v['value'];
        } else {
            $data[$v['name']] = $v['value'];
        }   
    }
    $item = $post_data['item'];

    $leader_id = $data['leader_id'];
    $visiblers = $data['visiblers'];
    $collaborators = $data['collaborators'];

    $data['visiblers'] = implode(",", $data['visiblers']);
    $data['collaborators'] = implode(",", $data['collaborators']);

    $this->_before_write(1, $data);
    $condition['id'] = $id;
    $condition['branch_id'] = $this->_user_session->currBranchId;
    M('WrkPrompt')->where($condition)->save($data);
    D('WrkReceivables')
    ->updateAccesData('WrkPrompt',$id,$leader_id,$this->_user_session->currBranchId,$visiblers,$collaborators);
    $ids = [];
    $wrkPromptItem = M("wrkPromptItem")->alias('a')
    ->where(['prompt_id' =>$id])
    ->select();

    foreach ($wrkPromptItem as $k => $v) {
      array_push($ids,$v['id']);
    }

    if ($post_data['is_related']==1) {
      $date = $post_data['date'];

      foreach ($item as $k => $v) {
        $item[$k]['prompt_id'] = $id;
        $item[$k]['receivables_item_id'] = $v['id'];
        $item[$k]['attach_group'] = genUniqidKey();
        if (!empty($v['prompt_item_id'])) {
          $ids = array_diff($ids,[$v['prompt_item_id']]);
          M('wrkPromptItem')->where([
              'id'=>$v['id'],
              'branch_id'=>$this->_user_session->currBranchId
          ])->save($item[$k]);
          M('wrkPromptDate')->where(['prompt_item_id'=>$v['prompt_item_id']])->delete();
          foreach ($date as $k1 => $v1) {
            $date_arr['prompt_item_id'] = $v['prompt_item_id'];
            $date_arr['prompt_date'] = $v1[$k]['prompt_date'];
            $date_arr['title'] = $v1[$k]['title'];
            $date_arr['is_checked'] = $v1[$k]['is_checked'];
            M('wrkPromptDate')->add($date_arr);
          }
        } else {
          $item[$k]['status'] = 0;
          $item[$k]['branch_id'] = $this->_user_session->currBranchId;
          $item[$k]['create_time'] = time();
          unset($item[$k]['id']);
          $prompt_item_id = M('wrkPromptItem')->add($item[$k]);
          M('wrkPromptDate')->where(['prompt_item_id'=>$v['prompt_item_id']])->delete();
          foreach ($date as $k1 => $v1) {
            $date_arr['prompt_item_id'] = $prompt_item_id;
            $date_arr['prompt_date'] = $v1[$k]['prompt_date'];
            $date_arr['title'] = $v1[$k]['title'];
            $date_arr['is_checked'] = $v1[$k]['is_checked'];
            M('wrkPromptDate')->add($date_arr);
          }
        }
      }
      D("WrkPrompt")->sendMessageByTimer($id,null);
    }else{
      foreach ($item as $k => $v) {
        $item[$k]['prompt_id'] = $id;
        if (!empty($v['id'])) {
          $ids = array_diff($ids,[$v['id']]);
          M('wrkPromptItem')->where([
              'id'=>$v['id'],
              'branch_id'=>$this->_user_session->currBranchId
          ])->save($item[$k]);
          $date['prompt_date'] = $item[$k]['prompt_date'];
          M('wrkPromptDate')->where(['prompt_item_id'=>$v['id']])
          ->save($date);
        } else {
          $item[$k]['status'] = 3;
          $item[$k]['branch_id'] = $this->_user_session->currBranchId;
          $item[$k]['create_time'] = time();
          $prompt_item_id = M('wrkPromptItem')->add($item[$k]);
          $date['prompt_item_id'] = $prompt_item_id;
          $date['prompt_date'] = $item[$k]['prompt_date'];
          M('wrkPromptDate')->add($date);
        }
      }    
    }

    $condition = [];
    if (!empty($ids)) {
        $condition['id'] = array('in',$ids);
    }
    M('wrkPromptItem')->where($condition)->delete();
    $this->ajaxReturn(array('code'=>0,'message'=>'编辑催款计划成功'));
  }

  public function sendMessageAction($id = null,$prompt_item_id = null,$period_number = null,$begin_date = null,$end_date = null,$amount = null,$is_related = null,$date = null) {
    if (IS_POST) {
        $prompt_id = $id;
        $amount = I('amount');
        $date = I('date');
        $begin_date = I('begin_date');
        $end_date = I('end_date');
        $period_number = I('period_number');
        $remark = I('remark');
        // if ($is_related==1) {
        //   $status = 1;
        // } else {
        //   $status = 4;
        // }
        $status = 1;
        M('wrkPromptItem')->where([
          'id'=>$prompt_item_id,
          'branch_id'=>$this->_user_session->currBranchId
        ])->save(['press_last_date'=>time(),'status'=>$status]);
        $rst = D("WrkPrompt")->sendWXPromptMessage($prompt_id,$amount,$prompt_item_id,$date);

        $receivables = M("WrkReceivables")->alias('a')
        ->join('LEFT JOIN wrk_prompt b ON b.contract_id = a.contract_id')
        ->where(['b.id' => $prompt_id])->field("a.id")->find();

        M("WrkReceivables")->where(['id'=>$receivables['id']])->save(['new_message'=>1]);
        $this->ajaxReturn(array('code'=>0,'message'=>'发送催款通知成功','rst'=>$rst));

    } else {
        $rst['id'] = $id;
        $rst['amount'] = $amount;

        if ( $begin_date=='null' ) {
          $rst['begin_date'] = '';
        }else{
          $rst['begin_date'] = $begin_date;
        }
        if ($end_date =='null') {
          $rst['end_date'] = '';
        }else{
          $rst['end_date'] = $end_date;
        }
        $rst['date'] = $date;
        $rst['period_number'] = '第'.$period_number.'期';
        $rst['prompt_item_id'] = $prompt_item_id;
        $this->assign('model',$rst);
        $this->display('sendMessage');
    }
  }

  // public function sendMessageByTimerAction($id,$prompt_date_id){
  //   $rst = D("WrkPrompt")->sendMessageByTimer($id,$prompt_date_id);
  // }
}