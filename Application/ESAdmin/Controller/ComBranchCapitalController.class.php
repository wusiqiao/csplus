<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;
use Think\Log;

class  ComBranchCapitalController extends DataController {

    public function listAction(){
        $branch_id = getBrowseBranchId();
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $limit ='limit '. ($page_index - 1) * $page_size . ',' . $page_size;
        $sql = $this->getListSql();
        $list = M()->query($sql['sql'].$limit);
        $list_total = count(M()->query($sql['sql']));
        $alldata =  M()->query($sql['all_sql']);
        //获取当前的公司账户的余额
        $account_data = M('SysBranch')->field('money,money_auditing') -> where('id = '.$branch_id) ->find();
        $alldata_income = 0;
        $alldata_pay = 0;
        $this->setAllDataActualMoney($alldata,$account_data,$alldata_income,$alldata_pay);
        $allDataIds = array_column($alldata,"id");
        foreach ($list as $key =>$value) {
            //$list[$key]['actual_money'] = $alldata[($page_index - 1) * $page_size + $key]['actual_money'];
            $list[$key]['actual_money'] = $alldata[array_search($value['id'],$allDataIds)]['actual_money'];
            $list[$key]['state_view'] = $this->getCapitalDetailStateView($value);
            $list[$key]['operation'] = $this->getCapitalDetailOperation($value);
            $list[$key]['income_money'] = $value['polarity'] == '+' ? sprintf("%.2f",$value['income_money']) : '';
            $list[$key]['pay_money'] = $value['polarity'] == '-' ? sprintf("%.2f",$value['pay_money']) : '';
            $list[$key]['created_time'] = date('Y/m/d H:i:s',$value['created_time']);
        }
        $result['footer'] = [["created_time"=>"资金账户总额：","company_name"=>"￥".$account_data['money'],
            "detail_type"=>"资金账户收入总额：","income_money"=>$alldata_income,
            "pay_money"=>"资金账户退款总额：","actual_money"=>$alldata_pay]];
        $result['total'] = $list_total;
        $result['rows'] = $list;
        $this->ajaxReturn($result);
    }

    //计算每一项的余额
    public function setAllDataActualMoney(&$alldata,$account_data,&$alldata_income,&$alldata_pay){
        foreach($alldata as $key=>$value) {
            if ($value['state'] == 1 && $value['polarity'] == '+'){
                $alldata_income = sprintf("%.2f",$alldata_income + $value['income_money']);
            } else if ($value['state'] == 1 && $value['polarity'] == '-'){
                $alldata_pay = sprintf("%.2f",$alldata_pay + $value['pay_money']);
            }
            $alldata[$key]['actual_money'] = sprintf("%.2f",$alldata_income-$alldata_pay);
            /*if ($key == 0) {
                $alldata[$key]['actual_money'] = sprintf("%.2f",$account_data['money']);
                if ($value['state'] == 1 && $value['polarity'] == '+'){
                    $alldata_income = sprintf("%.2f",$alldata_income + $value['income_money']);
                } else if ($value['state'] == 1 && $value['polarity'] == '-'){
                    $alldata_pay = sprintf("%.2f",$alldata_pay + $value['pay_money']);
                }
            } else {
                if ($value['state'] == 1 && $value['polarity'] == '+') {
                    $alldata_income = sprintf("%.2f",$alldata_income + $value['income_money']);
                } else if ($value['state'] == 1 && $value['polarity'] == '-'){
                    $alldata_pay = sprintf("%.2f",$alldata_pay + $value['pay_money']);
                }
                if ($alldata[$key - 1]['state'] == 1 && $alldata[$key - 1]['polarity'] == '+') {
                    $alldata[$key]['actual_money'] = sprintf("%.2f",$alldata[$key - 1]['actual_money'] - $alldata[$key - 1]['income_money']);
                } elseif ($alldata[$key - 1]['state'] == 1 && $alldata[$key - 1]['polarity'] == '-') {
                    $alldata[$key]['actual_money'] = sprintf("%.2f",$alldata[$key - 1]['actual_money'] + $alldata[$key - 1]['pay_money']);
                } else {
                    $alldata[$key]['actual_money'] = $alldata[$key - 1]['actual_money'];
                }
            }*/
        }
        if($alldata[count($alldata)-1]['actual_money']-1 != $account_data['money']){
            \Think\Log::write("商户资金账户余额不等于累积金额".$alldata[count($alldata)-1]['actual_money']."-".$account_data['money']);
        }
    }

    public function getListSql(){
        $branch_id = getBrowseBranchId();
        //客户余额消费（收入）
        $where[2] = "fina.branch_id = ".$branch_id ."  and "." fina.fina_type in (".FIN_PROMPT_BALANCE_PAY.",".FIN_RECEIVABLES_CONFIRMED.")";
        //$where[2] = "fina.branch_id = ".$branch_id ."  and "." fina.fina_type in (".FIN_PROMPT_BALANCE_PAY.")";
        //支出 退款
        $where[4] = "fina.branch_id = ".$branch_id ."  and "." fina.fina_type in (".FIN_USER_REFUND.") and fina.company_id IS NOT NULL";
        $order = "order by created_time asc,id asc ";
        $finance_model = D("ComFinance");
        $all_sql2 = $finance_model->alias('fina')
            ->field("fina.fina_id as id,fina.fina_time as created_time,fina.fina_cash as income_money,'' as pay_money,'收入' as detail_type,'客户余额消费' as income_type,'' as pay_type,'1' as state,fina.fina_type as money_type,'+' as polarity,fina.attach_group")
            ->where($where[2])->group("fina.order_sn")->fetchSql(true)->select();
        $all_sql4 = $finance_model->alias('fina')
            ->field("fina.fina_id as id,fina.fina_time as created_time,'' as income_money,fina.fina_cash as pay_money,'支出' as detail_type,'' as income_type,(case fina.fina_type when ".FIN_CIZ_WITHDRAW." then '提现' when ".FIN_USER_REFUND." then '退款' else '其它' end)  as pay_type,'1' as state,fina.fina_type as money_type,'-' as polarity,fina.attach_group")
            ->where($where[4])->fetchSql(true)->select();
        $allDataSql = '('.$all_sql2.') union ('.$all_sql4.') '.$order;
        if(I("post.time") || (I("post.fina_time_start") && I("post.fina_time_end"))){
            $result = D("WrkInvoicePlan")->getQdrDate(I("post.time"));
            if(empty($result)){
                $result['begin'] = strtotime(I("post.fina_time_start"));
                $result['end'] = strtotime(I("post.fina_time_end")) + 60*60*24 -1 ;
            }
            $time = " and fina.fina_time between ".$result['begin']." and ".$result['end'];
            $where[2] .= $time;
            $where[4] .= $time;
            //$order = "order by created_time asc ";
        }
        if(I("post.company_id")){
            $company = " and company_id = ".I("post.company_id");
            $where[2] .= $company;
            $where[4] .= $company;
        }
        if(I("post.mold") ==1){//收入
            $where[4] = "fina.fina_id = 0";
        }elseif(I("post.mold") ==2){//支出（退款）
            $where[2] = "fina.fina_id = 0";
        }
        $list_sql2 = $finance_model
            ->alias('fina')
            ->join("left join sys_branch b on fina.company_id = b.id")
            ->field("b.name as company_name,fina.fina_id as id,fina.fina_time as created_time,fina.fina_cash as income_money,'' as pay_money,'收款' as detail_type,'客户余额消费' as income_type,'' as pay_type,'1' as state,fina.fina_type as money_type,'+' as polarity,fina.attach_group")
            ->where($where[2])
            ->group("fina.order_sn")
            ->fetchSql(true)->select();
        $list_sql4 = $finance_model
            ->alias('fina')
            ->join("left join sys_branch b on fina.company_id = b.id")
            ->field("b.name as company_name,fina.fina_id as id,fina.fina_time as created_time,'' as income_money,fina.fina_cash as pay_money,'退款' as detail_type,'' as income_type,(case fina.fina_type when ".FIN_CIZ_WITHDRAW." then '提现' when ".FIN_USER_REFUND." then '退款' else '其它' end)  as pay_type,'1' as state,fina.fina_type as money_type,'-' as polarity,fina.attach_group")
            ->where($where[4])
            ->fetchSql(true)->select();
        $sql = '('.$list_sql2.') union ('.$list_sql4.') '.$order;
        $result['sql'] = $sql;
        $result['all_sql'] = $allDataSql;
        return $result;
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

    public function getBranches(){
        //$condition["a.id"] = 66;
        $condition["a.type"] = ORG_BRANCH;
        $condition['a.parent_id'] = 1;
        $condition['a.is_valid'] = 1;
        $condition['a.branch_role'] = array("neq",ROLE_ID_COMPANY_FREE);
        $branches = M("SysBranch")->alias("a")->where($condition)->field("id,name,money,money_auditing")->select();
        return $branches;
    }

    //初始化商户资金账户余额
    public function initialCapitalAction(){
        $model = M("ComFinance");
        $branch_model = M("SysBranch");
        $branches = $this->getBranches();
        foreach($branches as $k=>$branch){
            $where = [];
            $where['fina.fina_type'] = array("in",[FIN_PROMPT_BALANCE_PAY,FIN_RECEIVABLES_CONFIRMED]);//收入
            //$where['fina.fina_type'] = array("in",[FIN_PROMPT_BALANCE_PAY]);//收入
            $where['fina.branch_id'] = $branch['id'];
            $list = $model->alias("fina")->where($where)->group("fina.order_sn")->field("fina.fina_cash")->select();
            $total_income = 0;
            foreach ($list as $v) {
                $total_income += $v['fina_cash'];
            }
            //$total_income =  sprintf('%.2f',$model->alias("fina")->where($where)->sum("fina.fina_cash"));
            $where['fina.fina_type'] = FIN_USER_REFUND;//支出
            $where['fina.company_id'] = array("exp","IS NOT NULL");//支出
            $total_pay = sprintf('%.2f', $model->alias("fina")->where($where)->sum("fina.fina_cash"));
            $money = sprintf('%.2f',$total_income - $total_pay);
            $condition['a.id'] = $branch['id'];
            $branch_model->alias("a")->where($condition)->setField("money",$money);
            var_dump("b".$branch['id']."->".$money);
        }
    }

    //修正客户公司资金账户支出记录以及余额
    public function addComPayRecordAction(){
        $model = M();
        $finance_model = M("ComFinance");
        $branch_model = M("SysBranch");
        $recharge_model = M("ComRecharge");
        $branches = $this->getBranches();
        foreach($branches as $branch){
            $branch_id = $branch['id'];
            $condition["a.branch_id"] = $branch_id;
            $condition["a.type"] = ORG_COMPANY;
            $condition['a.is_valid'] = 1;
            //$condition['a.id'] = 134;
            $company = $branch_model->alias("a")->where($condition)->getField("id",true);
            foreach ($company as $k=>$v){
                $sql = sprintf("select a.* from com_finance a where a.order_sn 
            not in (select order_sn from com_finance where company_id = %d and fina_type = %d)
            and a.branch_id = %d and a.company_id = %d and a.fina_type = %d",$v,FIN_PROMPT_BALANCE_PAY,$branch_id,$v,FIN_RECEIVABLES_CONFIRMED);
                $result = $model->query($sql);
                foreach ($result as $key=>$value){
                    $where['user_id'] = $value['user_id'];
                    $where['order_sn'] = $value['order_sn'];
                    $where['money_type'] = 1;
                    $where['pay_status'] = 1;
                    $time = $recharge_model->where($where)->getField("ctime");
                    $value['fina_type'] = FIN_PROMPT_BALANCE_PAY;
                    $value['title'] = "客户余额消费";
                    if(!empty($time)){
                        $value['fina_time'] = $time;
                        $finance_model->where("fina_id = ".$value['fina_id'])->setField("fina_time",$time);
                    }
                    unset($value['fina_id']);
                    unset($value['third_fee']);
                    unset($value['platform_fee']);
                    //$value['fina_time'] = empty($time)? $value['fina_time'] : $time;
                    var_dump("b$branch_id"."->c$v->"."pay-".$value['fina_cash']);
                    $finance_model->add($value);
                }
                //客户资金账户余额
                $money = sprintf("%.2f",$branch_model->where("id = $v")->getField("money"));
                $condition1['branch_id'] = $branch_id;
                $condition1['company_id'] = $v;
                $condition1['fina_type'] = array("in",[FIN_CIZ_WITHDRAW_FLOW_TO_COMPANY,FIN_RECEIVABLES_CONFIRMED,FIN_CIZ_RECHARGE,FIN_USER_REFUND]);
                $total_income = $finance_model->where($condition1)->sum("fina_cash");//总收入
                $condition1['fina_type'] = array("in",[FIN_PROMPT_BALANCE_PAY,FIN_CIZ_WITHDRAW]);
                $total_pay = $finance_model->where($condition1)->sum("fina_cash");//总支出
                $condition1['fina_type'] = FIN_USER_REFUND;
                $condition1['fina_time'] = array("lt",strtotime("2019/7/9"));//上正式服时间
                $refund_sum = $finance_model->where($condition1)->sum("fina_cash");//总退款
                if(!empty($refund_sum)){
                    var_dump("b$branch_id"."->c$v->"."refund-".$refund_sum);
                }
                //如果总收入-总支出-退款金额=现余额，则表示该退款发生在退款未增加余额的时候，增加该笔退款余额
                if((sprintf("%.2f",$total_income - $total_pay - $refund_sum) == $money) && !empty($refund_sum)){
                    $branch_model->where("id = $v")->setField("money",sprintf("%.2f",$money+$refund_sum));
                    var_dump("c".$v."增加余额".$refund_sum);
                }
            }
        }
    }

    public function addUserPayRecordAction(){

    }
}