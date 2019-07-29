<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class  ComCapitalDetailsController extends DataController {

    protected $_various = [CAJ_BRANCH_RECHARGE,CAJ_BRANCH_WITHDRAWAL,CAJ_BRANCH_CUSTOMER_CAPITAL];

    public function listAction() {
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $limit ='limit '. ($page_index - 1) * $page_size . ',' . $page_size;
        $where_user['a.branch_id'] = getBrowseBranchId();
        $where_company['a.parent_id'] = getBrowseBranchId();
        $where_company['a.type'] = ORG_COMPANY;
        $count_user = ['count' => 0,'total' =>0];
        $count_company = ['count' => 0,'total' =>0];
        if (!empty(I('post.capital_account_id'))) {
            $capital_account = explode(':',I('post.capital_account_id'));
            if ($capital_account[0] === 'c') {
                $where_user['a.id'] = 0;
                $where_company['a.id'] = $capital_account[1];
                if(I("post.status") == 2){
                    $users = M("SysUserBranch")->where("type in (1,2) and branch_id = ".$capital_account[1])->getField("user_id",true);
                    $where_user['a.id'] = array("in",$users);
                }
            } else {
                $where_company['a.id'] = 0;
                $where_user['a.id'] = $capital_account[1];
            }
        }
        //负责人处理
        if (!empty(I('post.recharge_leader_id')) || !empty(I('post.withdrawal_leader_id'))) {
            $aj = D('ComAccountJurisdiction');
            if(!empty($capital_account)) {
                $aj->setObjectType($capital_account[0] == 'c' ? 1 : 2);
                $aj->setObjectId($capital_account[1]);
            }
            if (!empty(I('post.recharge_leader_id'))){
                $aj->setObjectVarious([CAJ_BRANCH_RECHARGE]);
                $recharge_data = $aj->getVariousBelongAccount(['leader_id'=>I('post.recharge_leader_id')]);
            }
            if (!empty(I('post.withdrawal_leader_id'))) {
                $aj->setObjectVarious([CAJ_BRANCH_WITHDRAWAL]);
                $withdrawal_data = $aj->getVariousBelongAccount(['leader_id'=>I('post.withdrawal_leader_id')]);
            }
            $template_company = [];
            $template_user = [];
            if ($recharge_data) {
                foreach ($recharge_data as $key=>$value){
                    if ($value['object_type'] == 1) {
                        $template_company[] = $value['company_id'];
                    } else {
                        $template_user[] = $value['user_id'];
                    }
                }
            }
            if ($withdrawal_data) {
                foreach ($withdrawal_data as $key=>$value){
                    if ($value['object_type'] == 1) {
                        $template_company[] = $value['company_id'];
                    } else {
                        $template_user[] = $value['user_id'];
                    }
                }
            }
            if ($template_user) {
                if (isset($where_company['a.id'])) {
                    $where_user['a.id'] = array(['eq',$where_user['a.id']],['in',$template_user],'and');
                } else {
                    $where_user['a.id'] = ['in',$template_user];
                }
            } else {
                $where_user['a.id'] = 0;
            }
            if ($template_company) {
                if (isset($where_company['a.id'])) {
                    $where_company['a.id'] = array(['eq',$where_company['a.id']],['in',$template_company],'and');
                } else {
                    $where_company['a.id'] = ['in',$template_company];
                }
            } else {
                $where_company['a.id'] = 0;
            }
        }

        if (I('post.status') != 1) {
            $field_user = '(a.user_money) as actual_money,CONCAT("u",a.id) as id,a.name as capital_account,"u" as source,a.id as object_id';
            $sql_user = M('SysUser')->alias('a')->field($field_user)->where($where_user)->fetchSql(true)->select();
            $count_user = M('SysUser')->alias('a')->field('count(*) as count,sum(user_money) as total') ->where($where_user) ->find();
        }
        if (I('post.status') != 2) {
            $field_company = '(a.money) as actual_money,CONCAT("c",a.id) as id,a.name as capital_account,"c" as source,a.id as object_id';
            $sql_company = M('SysBranch')->alias('a') ->field($field_company)->where($where_company)->fetchSql(true)->select();
            $count_company = M('SysBranch')->alias('a') ->field('count(*) as count,sum(money) as total') ->where($where_company) ->find();
        }
        $finance_model = M("ComFinance");
        $where = [];
        //$where['fina_type'] = array("in",[FIN_PROMPT_BALANCE_PAY,FIN_RECEIVABLES_CONFIRMED]);
        $where['fina_type'] = array("in",[FIN_PROMPT_BALANCE_PAY]);
        $where['branch_id'] = getBrowseBranchId();
        $branch_total_pay = 0;
        $total_pay_list = $finance_model->where($where)->group("order_sn")->field("fina_cash")->select();
        //$branch_total_pay = $finance_model->where($where)->sum("fina_cash");
        foreach($total_pay_list as $t){
            $branch_total_pay += $t['fina_cash'];
        }
        if (I('post.status') == 1) {
            $sql = $sql_company.' order by actual_money desc '.$limit;
        } else if (I('post.status') == 2){
            $branch_total_pay = "hide";
            $sql = $sql_user.' order by actual_money desc '.$limit;
        } else {
            $sql = '('.$sql_user.') union ('.$sql_company.') order by actual_money desc '.$limit;
        }
        //用户列表
        $list = M()->query($sql);
        foreach ($list as $key => $value) {
            $list[$key]['actual_money'] = sprintf("%.2f",$value['actual_money']);
            $account_type = $value['source'] == 'c' ? 1 : 2;
            $account_jurisdiction = $this->getAccountSystemManage($value['object_id'],$account_type,[CAJ_BRANCH_RECHARGE,CAJ_BRANCH_WITHDRAWAL],[CAJ_BRANCH_RECHARGE,CAJ_BRANCH_WITHDRAWAL]);
            $account = $this->handlerAccountSystem($account_jurisdiction);
            $list[$key]['recharge_leader_view'] = $account['recharge_leader_view'];
            $list[$key]['withdrawal_leader_view'] = $account['withdrawal_leader_view'];
            //付款总额
            if($account_type == 1){
                $where['company_id'] = $value['object_id'];
                $total_pay_list = $finance_model->where($where)->group("order_sn")->field("fina_cash")->select();
                //$total_pay = $finance_model->where($where)->sum("fina_cash");
                $total_pay = 0;
                foreach($total_pay_list as $t){
                    $total_pay += $t['fina_cash'];
                }
            }else{
                $total_pay = "";
            }
            $list[$key]['total_pay'] = empty($total_pay) ? 0:$total_pay;
        }
        $result["total"] = $count_company['count'] + $count_user['count'];
        $result["rows"] = $list;
        $result["footer"] = [
            ['capital_account' => '资金账户总余额','actual_money'=> sprintf("%.2f",$count_company['total'] + $count_user['total']),"total_pay"=>empty($branch_total_pay)?0:$branch_total_pay]
        ];
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($result));
    }
    public function detailAction($id = null) {
        $this->assignPermissions();
        $id = I('get.id');
        $branch = M('SysBranch')->field('leader_id')->where('id = '.getBrowseBranchId())->find();
        if ($branch['leader_id'] == $this->_user_session->userId){
            $this->assign('has_branch_leader',1);
        } else {
            $this->assign('has_branch_leader',0);
        }
        if (strpos($id,'c') !== false) {
            $id = str_replace("c","",$id);
            $record = M('SysBranch')->where('id = '.$id)->find();
            $record['capital_type'] = 'company';
            $hasLeader['object_type'] = 1;
            $account_system = $this->getAccountSystemManage($id,1,$this->_various,[CAJ_BRANCH_CUSTOMER_CAPITAL]);
        } else {
            $id = str_replace("u","",$id);
            $record = M('SysUser')->where('id = '.$id)->find();
            $record['capital_type'] = 'user';
            $record['bank_account'] = $record['bank_account'] ? '尾号'.substr($record['bank_account'],-4) : '';
            $hasLeader['object_type'] = 2;
            $account_system = $this->getAccountSystemManage($id,2,$this->_various,[CAJ_BRANCH_CUSTOMER_CAPITAL]);
        }
        $record['idt'] = I('get.id');
        $hasLeader['object_id'] = $id;
        $has_leaders = $this->getUserHasAccountLeader($hasLeader,[CAJ_BRANCH_RECHARGE,CAJ_BRANCH_WITHDRAWAL]);
        $this->assign('has_leaders',$has_leaders);
        $record['attach_group'] = empty($record['attach_group']) ? genUniqidKey() : $record['attach_group'];
        $record['id'] = $id;
        $this->assign("model", $record);
        $this->assign('account_belong',$this->handlerAccountSystem($account_system));
        //var_dump($record);
        exit($this->fetch($this->_get_detail_template($record)));
    }

    // post capital_type - 资金类型 / id - 资金id
    // post mold - 明细类型 1 收入 2 支出 all 全部
    // post income - 收入类型 1 充值 2 佣金 3 转账 4 退款 all 全部
    // post pay - 支出类型 1 付款 2 提现 3 转账 all 全部
    public function accountDetailsListAction()
    {
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $limit ='limit '. ($page_index - 1) * $page_size . ',' . $page_size;
        $branch_id = getBrowseBranchId();
        $is_company = I('get.capital_type') == 'company' ? true : false;
        $id = I('get.id');
        if ($is_company) {
            //添加充值 提现 转账信息
            //转账至公司账户
            $where1 = "fina.branch_id = ".$branch_id ."  and "." fina.fina_type in (".FIN_CIZ_WITHDRAW_FLOW_TO_COMPANY.") and fina.company_id = ".$id;
            //公司提现
            $where2 = "fina.branch_id = ".$branch_id ."  and "." fina.withdrawal_type in (".FIN_CIZ_WITHDRAW.")"." and fina.company_id = ".$id;
            //公司充值
            $where3 = "fina.branch_id = ".$branch_id ."  and "." fina.money_type in (".FIN_CIZ_RECHARGE.")"." and fina.company_id = ".$id;
            //缴费余额付款
            //$where4 = "fina.branch_id = ".$branch_id ."  and "." fina.fina_type in (".FIN_PROMPT_BALANCE_PAY.",".FIN_RECEIVABLES_CONFIRMED.") and fina.company_id = ".$id;
            $where4 = "fina.branch_id = ".$branch_id ."  and "." fina.fina_type in (".FIN_PROMPT_BALANCE_PAY.") and fina.company_id = ".$id;
            //退款
            $where5 = "fina.branch_id = ".$branch_id ."  and "." fina.fina_type in (".FIN_USER_REFUND.") and fina.company_id = ".$id;
            $sql = $this->getComAccountListSql($where1,$where2,$where3,$where4,$where5);
            $alldata =  M()->query($sql);
            if (isset($_POST['mold'])) {
                if($_POST['mold'] == 1){
                    switch ($_POST['income']) {
                        case '3':
                            $where2 = "fina.id = 0";$where3 = "fina.id = 0";$where4 = "fina.fina_id = 0";$where5 = "fina.fina_id = 0";
                            break;
                        case '4':
                            $where1 = "fina.fina_id = 0";$where2 = "fina.id = 0";$where3 = "fina.id = 0";$where4 = "fina.fina_id = 0";
                            break;
                        case '5':
                            $where1 = "fina.fina_id = 0";$where2 = "fina.id = 0";$where4 = "fina.fina_id = 0";$where5 = "fina.fina_id = 0";
                            break;
                        default:
                            $where2 = "fina.id = 0";$where4 = "fina.fina_id = 0";
                            break;
                    }
                }elseif($_POST['mold'] == 2){
                    switch ($_POST['pay']) {
                        case '1':
                            $where1 = "fina.fina_id = 0";$where2 = "fina.id = 0";$where3 = "fina.id = 0";$where5 = "fina.fina_id = 0";
                            break;
                        case '2':
                            $where1 = "fina.fina_id = 0";$where3 = "fina.id = 0";$where4 = "fina.fina_id = 0";$where5 = "fina.fina_id = 0";
                            break;
                        default:
                            $where1 = "fina.fina_id = 0";$where3 = "fina.id = 0";$where5 = "fina.fina_id = 0";
                            break;
                    }
                }
            }
            $sql = $this->getComAccountListSql($where1,$where2,$where3,$where4,$where5);
            $list = M()->query($sql.$limit);
            //获取当前的公司账户的余额
            //$account_data = M('SysBranch')->field('money,money_auditing') -> where('id = '.$id) ->find();
        } else {
            //用户退款
            $where1 = "fina.branch_id = ".$branch_id ." and fina.user_id = ".$id." and "." fina.fina_type = '". FIN_USER_REFUND ."'";
            //客户线下付款 客户订单现金付款    (不展示)
            //$where2 = "fina.branch_id = ".$branch_id ."  and fina.fina_type in (".FIN_ORDER_LINE_PAY.",".FIN_ORDER_PAY.")"." and o.user_id = ".$id;
            //转账至公司
            $where3 = "fina.branch_id = ".$branch_id ."  and fina.fina_type in (".FIN_CIZ_WITHDRAW_FLOW_TO_COMPANY.")"." and fina.user_id = ".$id;
            //用户提现
            $where4 = "fina.branch_id = ".$branch_id ."  and fina.withdrawal_type in (".FIN_UIZ_WITHDRAW.")"." and fina.user_id = ".$id;
            //个人充值 佣金
            $where5 = "fina.branch_id = ".$branch_id ."  and fina.money_type in (".FIN_UIZ_RECHARGE.",".FIN_DIZ_RECHARGE.")"." and fina.user_id = ".$id;
            $sql = $this->getUserAccountListSql($where1,$where3,$where4,$where5);
            $alldata =  M()->query($sql);
            if (isset($_POST['mold'])) {
                if($_POST['mold'] == 1){
                    switch ($_POST['income']) {
                        case '1' ://充值
                            $where1 = "fina.fina_id = 0";$where2 = "fina.fina_id = 0";$where3 = "fina.fina_id = 0";$where4 = "fina.id = 0";
                            $where5 = "fina.branch_id = ".$branch_id ."  and fina.money_type in (".FIN_UIZ_RECHARGE.")"." and fina.user_id = ".$id;
                            break;
                        case '2'://佣金
                            $where1 = "fina.fina_id = 0";$where2 = "fina.fina_id = 0";$where3 = "fina.fina_id = 0";$where4 = "fina.id = 0";
                            $where5 = "fina.branch_id = ".$branch_id ."  and fina.money_type in (".FIN_DIZ_RECHARGE.")"." and fina.user_id = ".$id;
                            break;
                        default:
                            $where2 = "fina.fina_id = 0";$where3 = "fina.fina_id = 0";$where4 = "fina.id = 0";
                            break;
                    }
                }elseif($_POST['mold'] == 2){
                    switch ($_POST['pay']) {
                        case '1' ://付款
                            $where1 = "fina.fina_id = 0";$where3 = "fina.fina_id = 0";$where4 = "fina.id = 0";$where5 = "fina.id = 0";
                            break;
                        case '2'://提现
                            $where1 = "fina.fina_id = 0";$where2 = "fina.fina_id = 0";$where3 = "fina.fina_id = 0";$where5 = "fina.id = 0";
                            break;
                        case '3':
                            $where1 = "fina.fina_id = 0";$where2 = "fina.fina_id = 0";$where4 = "fina.id = 0";$where5 = "fina.id = 0";
                            break;
                        default:
                            $where1 = "fina.fina_id = 0";$where5 = "fina.id = 0";
                            break;
                    }
                }
            }
            $sql = $this->getUserAccountListSql($where1,$where3,$where4,$where5);
            $list = M()->query($sql.$limit);
            //$account_data = M('SysUser')->field('user_money as money,user_money_auditing as money_auditing') -> where('id = '.$id) ->find();
        }
        $alldata_income = 0;
        $alldata_pay = 0;
        foreach($alldata as $key=>$value) {
            if ($value['state'] == 1 && $value['polarity'] == '+'){
                $alldata_income = sprintf("%.2f",$alldata_income + $value['income_money']);
            } else if ($value['state'] == 1 && $value['polarity'] == '-'){
                $alldata_pay = sprintf("%.2f",$alldata_pay + $value['pay_money']);
            }
            $alldata[$key]['actual_money'] = sprintf("%.2f",$alldata_income-$alldata_pay);
        }
        $alldata_ids = array_column($alldata,"id");
        foreach ($list as $key =>$value) {
            $index = array_search($value['id'],$alldata_ids);
            $list[$key]['actual_money'] = $alldata[$index]['actual_money'];
            //$list[$key]['actual_money'] = $alldata[($page_index - 1) * $page_size + $key]['actual_money'];
            //$list[$key]['actual_money'] = $alldata[($page_index) * $page_size - $key]['actual_money'];
            $list[$key]['state_view'] = $this->getCapitalDetailStateView($value);
            $list[$key]['operation'] = $this->getCapitalDetailOperation($value);
            if(($value['source'] != '' && $value['source'] != null && $value['source'] == 0 && $value['state'] == 0)){
                $list[$key]['state_view'] = '充值失败';
            }
            $list[$key]['income_money'] = $value['polarity'] == '+' ? sprintf("%.2f",$value['income_money']) : '';
            $list[$key]['pay_money'] = $value['polarity'] == '-' ? sprintf("%.2f",$value['pay_money']) : '';
            $list[$key]['created_time'] = date('Y/m/d H:i:s',$value['created_time']);
        }
        $result["total"] = count(M()->query($sql));
        $result["rows"] = $list;
        $result["footer"] = [
            ['created_time' => '合计','income_money'=> $alldata_income,'pay_money'=>$alldata_pay,'actual_money'=>'hide']
        ];
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($result));
    }
    protected function getCapitalDetailStateView($capital)
    {
        $capital_entity_library = [
            '提现' => ['提现中','提现成功','提现失败'],
            '充值' => ['充值中','充值成功','充值失败'],
            '转账' => ['转账中','已转账','转账失败'],
            '佣金' => ['未解冻','已解冻','解冻失败'],
            '退款' => ['退款中','已退款','退款失败'],
            '付款' => ['付款中','已付','付款失败']
        ];
        return $capital['polarity'] === '+' ?
               $capital_entity_library[$capital['income_type']][$capital['state']] :
               $capital_entity_library[$capital['pay_type']][$capital['state']];
    }
    protected function getCapitalDetailOperation($capital)
    {
        if ($capital['polarity'] === '+' && $capital['income_type'] ==='充值' ){
            return 'ComRecharge';
        } elseif ($capital['polarity'] === '-' && $capital['pay_type'] ==='提现' ) {
            return 'ComWithdrawals';
        } else {
            return 'ComFinance';
        }
    }
    public function getAccountSystemDataAction() {

        $capital_account_id = explode(':',I('post.id'));
        $object_id = $capital_account_id[1];
        $object_type = $capital_account_id[0] == 'u' ? 2 : 1;
        $account_manager = $this->getAccountSystemManage($object_id,$object_type,$this->_various);
        $this->ajaxReturn($this->handlerAccountSystem($account_manager));
    }
    public function saveAccountJurisdictionAction()
    {
        if (IS_POST) {
            $account_jurisdiction = D('ComAccountJurisdiction');
            $postdata = I('post.');
            $account_jurisdiction->setOptions('object_id',$postdata['id']);
            $account_jurisdiction->setObjectVarious([CAJ_BRANCH_RECHARGE,CAJ_BRANCH_WITHDRAWAL]);
            if (!empty($postdata['recharge_leader_id']) && !empty($postdata['recharge_visiblers_inputs'])) {
                if (in_array($postdata['recharge_leader_id'],$postdata['recharge_visiblers_inputs'])){
                    unset($postdata['recharge_visiblers_inputs'][array_search($postdata['recharge_leader_id'],$postdata['recharge_visiblers_inputs'])]);
                }
            }
            if (!empty($postdata['withdrawal_leader_id']) && !empty($postdata['withdrawal_visiblers_inputs'])) {
                if (in_array($postdata['withdrawal_leader_id'],$postdata['withdrawal_visiblers_inputs'])){
                    unset($postdata['withdrawal_visiblers_inputs'][array_search($postdata['withdrawal_leader_id'],$postdata['withdrawal_visiblers_inputs'])]);
                }
            }
            if ($postdata['capital_type'] == 'company') {
                if (empty(M('SysBranch') ->where('id = '.$postdata['id'])->getField('attach_group'))) {
                    $save['attach_group'] = I('post.attach_group');
                    M('SysBranch') ->where('id = '.$postdata['id']) ->data($save) ->save();
                }
                $account_jurisdiction->setOptions('object_type',1);
            } else {
                if (empty(M('SysUser') ->where('id = '.$postdata['id'])->getField('attach_group'))) {
                    $save['attach_group'] = I('post.attach_group');
                    M('SysUser') ->where('id = '.$postdata['id']) ->data($save) ->save();
                }
                $account_jurisdiction->setOptions('object_type',2);
            }
            $recharge['leader_id'] = empty($postdata['recharge_leader_id']) ? null : $postdata['recharge_leader_id'];
            $recharge['visiblers'] = empty($postdata['recharge_visiblers_inputs']) ? null : implode(',',$postdata['recharge_visiblers_inputs']);
            $account_jurisdiction->setOptions(CAJ_BRANCH_RECHARGE,$recharge);
            $withdrawal['leader_id'] = empty($postdata['withdrawal_leader_id']) ? null : $postdata['withdrawal_leader_id'];
            $withdrawal['visiblers'] = empty($postdata['withdrawal_visiblers_inputs']) ? null : implode(',',$postdata['withdrawal_visiblers_inputs']);
            $account_jurisdiction->setOptions(CAJ_BRANCH_WITHDRAWAL,$withdrawal);
            $result = $account_jurisdiction->saveAccountJurisdiction();
            if ($result) {
                $this->ajaxReturn(buildMessage('保存成功!',0));
            } else {
                $this->ajaxReturn(buildMessage('保存失败!',1));
            }
        }
    }
    public function setAttachGroupAction()
    {
        if (IS_POST) {
            $table = I('post.operation');
            if ($table == 'ComFinance') {
                $condition['fina.id'] = I('post.id');
            } else {
                $condition['id'] = I('post.id');
            }
            $save['attach_group'] = I('post.attach_group');
            D($table)->where($condition)->data($save)->save();
        }
    }
    public function handlerAccountSystem($account_manager)
    {
        $condition_id = [];
        $users = [];
        $recharge_visiblers_all = '';
        $withdrawal_visiblers_all = '';
        $customer_capital_visiblers_all = '';
        if (!empty($account_manager['withdrawal_leader_id'])) {
            $condition_id[] = $account_manager['withdrawal_leader_id'];
        }
        if (!empty($account_manager['withdrawal_visiblers'])) {
            $withdrawal_visiblers = explode(',',$account_manager['withdrawal_visiblers']);
            foreach ($withdrawal_visiblers as $key =>$val) {
                $condition_id[] = $val;
            }
        }
        if (!empty($account_manager['recharge_leader_id'])) {
            $condition_id[] = $account_manager['recharge_leader_id'];
        }
        if (!empty($account_manager['recharge_visiblers'])) {
            $recharge_visiblers = explode(',',$account_manager['recharge_visiblers']);
            foreach ($recharge_visiblers as $key =>$val) {
                $condition_id[] = $val;
            }
        }
        if (!empty($account_manager['customer_capital_leader_id'])) {
            $condition_id[] = $account_manager['customer_capital_leader_id'];
        }
        if (!empty($account_manager['customer_capital_visiblers'])) {
            $customer_capital_visiblers = explode(',',$account_manager['customer_capital_visiblers']);
            foreach ($customer_capital_visiblers as $key =>$val) {
                $condition_id[] = $val;
            }
        }
        if ($condition_id) {
            $condition['id'] = array('in',$condition_id);
            $result = M('SysUser')->field('id,name')->where($condition)->select();
        } else {
            $result = [];
        }
        foreach ($result as $key =>$val) {
            $users[$val['id']] = $val;
        }
        if ($account_manager['recharge_visiblers'] !== null) {
            foreach ($recharge_visiblers as $key =>$val) {
                $recharge_visiblers_all[] = $users[$val];
            }
        }
        if ($account_manager['withdrawal_visiblers'] !== null) {
            foreach ($withdrawal_visiblers as $key =>$val) {
                $withdrawal_visiblers_all[] = $users[$val];
            }
        }
        if ($account_manager['customer_capital_visiblers'] !== null) {
            foreach ($customer_capital_visiblers as $key =>$val) {
                $customer_capital_visiblers_all[] = $users[$val];
            }
        }
        $account_manager['recharge_leader_view'] = !empty($account_manager['recharge_leader_id']) ? $users[$account_manager['recharge_leader_id']]['name'] : '';
        $account_manager['recharge_visiblers_view'] = $recharge_visiblers_all;
        $account_manager['withdrawal_leader_view'] = !empty($account_manager['withdrawal_leader_id']) ? $users[$account_manager['withdrawal_leader_id']]['name'] : '';
        $account_manager['withdrawal_visiblers_view'] = $withdrawal_visiblers_all;
        $account_manager['customer_capital_leader_view'] = !empty($account_manager['customer_capital_leader_id']) ? $users[$account_manager['customer_capital_leader_id']]['name'] : '';
        $account_manager['customer_capital_visiblers_view'] = $customer_capital_visiblers_all;
        return $account_manager;
    }
    public function getAccountSystemManage($id,$type,$various,$field = []){
        $account_jurisdiction = D('ComAccountJurisdiction');
        $account_jurisdiction->resetJurisdiction();
        $account_jurisdiction->setObjectVarious($various);
        $account_jurisdiction->setObjectId($id);
        $account_jurisdiction->setObjectType($type);
        $account_jurisdiction->getAccountSystemManage();
        $account_jurisdiction->handlerAccountCapitalJurisdiction($field);
        $capital = $account_jurisdiction->getStore('capital');
        return $capital;
    }
    protected function getUserHasAccountLeader($data,$various)
    {
        $jurisdiction =  D('ComAccountJurisdiction');
        $jurisdiction->setObjectType($data['object_type']);
        $jurisdiction->setObjectId($data['object_id']);
        $jurisdiction->setObjectVarious($various);
        $jurisdiction->handlerHasAccountLeader(false);
        return $jurisdiction->getStore('has_leader');
    }

    public function getComAccountListSql($where1,$where2,$where3,$where4,$where5){
        $list_sql1 = D('ComFinance')
            ->alias('fina')
            ->field("fina.fina_id as id,fina.fina_time as created_time,fina.fina_cash as income_money,'' as pay_money,'收入' as detail_type,(case fina.fina_type when ".FIN_CIZ_WITHDRAW." then '提现' when ".FIN_CIZ_WITHDRAW_FLOW_TO_COMPANY." then '转账' when ".FIN_USER_REFUND." then '退款' else '充值' end)  as income_type,'' as pay_type,'1' as state,fina.fina_type as money_type,'+' as polarity,fina.attach_group,'' as source")
            ->where($where1)
            ->fetchSql(true)->select();
        $list_sql2 = D('ComWithdrawals')
            ->alias('fina')
            ->field("fina.id,fina.create_time as created_time,'' as income_money,fina.money as pay_money,'支出' as detail_type,'' as income_type,'提现' as pay_type,fina.status as state,fina.withdrawal_type as money_type,'-' as polarity,fina.attach_group,'' as source")
            ->where($where2)
            ->fetchSql(true)->select();
        $list_sql3 = D('ComRecharge')
            ->alias('fina')
            ->field("fina.id,fina.ctime as created_time,fina.account as income_money,'' as pay_money,'收入' as detail_type,'充值' as income_type,'' as pay_type,fina.pay_status as state,fina.money_type as money_type,'+' as polarity,fina.attach_group,fina.source")
            ->where($where3)
            ->fetchSql(true)->select();
        $list_sql4 = D('ComFinance')
            ->alias('fina')
            ->field("fina.fina_id as id,fina.fina_time as created_time,'' as income_money,fina.fina_cash as pay_money,'支出' as detail_type,''  as income_type,'付款' as pay_type,'1' as state,fina.fina_type as money_type,'-' as polarity,fina.attach_group,'' as source")
            ->where($where4)
            ->group("fina.order_sn")
            ->fetchSql(true)->select();
        //退款
        $list_sql5 = D('ComFinance')
            ->alias('fina')
            ->field("fina.fina_id as id,fina.fina_time as created_time,fina.fina_cash as income_money,'' as pay_money,'收入' as detail_type,(case fina.fina_type when ".FIN_CIZ_WITHDRAW." then '提现' when ".FIN_CIZ_WITHDRAW_FLOW_TO_COMPANY." then '转账' when ".FIN_USER_REFUND." then '退款' else '充值' end)  as income_type,'' as pay_type,'1' as state,fina.fina_type as money_type,'+' as polarity,fina.attach_group,'' as source")
            ->where($where5)
            ->fetchSql(true)->select();
        $sql = '( '.$list_sql1 . ') union ('.$list_sql2.') union ('.$list_sql3.') union ('.$list_sql4.') union ('.$list_sql5.')  order by created_time asc,id asc ';
        return $sql;
    }

    public function getUserAccountListSql($where1,$where3,$where4,$where5){
        $list_sql1 = D('ComOrder')
            ->setDacFilter('o')
            ->field("fina.fina_id as id,fina.fina_time as created_time,fina.fina_cash as income_money,'' as pay_money,'收入' as detail_type,'退款' as income_type,'' as pay_type,'1' as state,fina.fina_type as money_type,'+' as polarity,fina.attach_group,'' as source")
            ->join('inner join com_finance as fina on fina.order_sn = o.order_sn')
            ->where($where1)
            ->fetchSql(true)->select();
        /*$list_sql2 = D('ComOrder')
            ->setDacFilter('o')
            ->field("fina.fina_id as id,fina.fina_time as created_time,'' as income_money,fina.fina_cash as pay_money,'支出' as detail_type,'' as income_type,'付款' as pay_type,'1' as state,fina.fina_type as money_type,'-' as polarity,fina.attach_group")
            ->join('inner join com_finance as fina on fina.order_sn = o.order_sn')
            ->where($where2)
            ->fetchSql(true)->select();*/
        $list_sql3 = D('ComFinance')
            ->alias('fina')
            ->field("fina.fina_id as id,fina_time as created_time,'' as income_money,fina.fina_cash as pay_money,'支出' as detail_type,'' as income_type,'转账' as pay_type,'1' as state,fina.fina_type as money_type,'-' as polarity,fina.attach_group,'' as source")
            ->where($where3)
            ->fetchSql(true)->select();
        $list_sql4 = D('ComWithdrawals')
            ->alias('fina')
            ->field("fina.id,fina.create_time as created_time,'' as income_money,fina.money as pay_money,'支出' as detail_type,'' as income_type,'提现' as pay_type,fina.status as state,fina.withdrawal_type as money_type,'-' as polarity,fina.attach_group,'' as source")
            ->where($where4)
            ->fetchSql(true)->select();
        $list_sql5 = D('ComRecharge')
            ->alias('fina')
            ->field("fina.id,fina.ctime as created_time,fina.account as income_money,'' as pay_money,'收入' as detail_type,( case fina.money_type  when ".FIN_DIZ_RECHARGE." then '佣金' else '充值' end ) as income_type,'' as pay_type,fina.pay_status as state,fina.money_type,'+' as polarity,fina.attach_group,fina.source")
            ->where($where5)
            ->fetchSql(true)->select();
//            $sql = '( '.$list_sql1 . ') union ('.$list_sql2.') union ('.$list_sql3.') union ('.$list_sql4.')order by pay_time desc '.$limit;
        $sql = '( '.$list_sql1 . ')  union ('.$list_sql3.') union ('.$list_sql4.') union ('.$list_sql5.')   order by created_time asc ';
        return $sql;
    }
}