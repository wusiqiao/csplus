<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/28
 * Time: 15:21
 */

namespace EShop\Controller;
use Think\Controller;

class WrkInvoicePlanController extends BaseController{

    public function indexAction(){
        //获取本周计划条数
        $count = $this->getList(0,1)['count'];
        $this->assign("count",$count);
        $this->assign("title","开票管理");
        $this->display();
    }

    /*
     * $list 是否获取list
     * $type 是否有计划开票
     * */
    public function getList($list,$type){
        $condition = $this->parseFilter($list,$type);
        if($list){
            if($type){
                //$order = ($condition['a.state'] == 1 or $condition['a.state'] == "") ? "b.plan_day desc":"b.plan_day asc";
                $order = "b.plan_day asc";
                //有计划
                $result['list'] = D(CONTROLLER_NAME)
                    ->setDacFilter("a")
                    ->join("wrk_invoice_plan_detail b on a.id = b.plan_id")
                    ->join("wrk_agreement c on a.agreement_id = c.id")
                    ->join("left join sys_branch company on c.company_id = company.id")
                    ->where($condition)
                    ->field("b.*,company.name as company_name,c.name,c.agreement_money")
                    ->page(I("post.page"),20)->distinct(true)->order($order)->select();
            }else{
                //无计划
                $result['list'] = D(CONTROLLER_NAME)
                    ->setDacFilter("a")
                    ->join("wrk_agreement c on a.agreement_id = c.id")
                    ->join("left join sys_branch company on c.company_id = company.id")
                    ->where($condition)->order("c.create_time desc")
                    ->field("a.*,company.name as company_name,c.name,c.agreement_money")
                    ->page(I("post.page"),20)->distinct(true)->select();
            }
            foreach ($result['list'] as $k=>$v){
                $result['list'][$k]['state_value'] = $this->getStateValue($v['state'],$type);
                if($type){
                    $result['list'][$k]['plan_day_fmt'] = date("Y-m-d",$v['plan_day']);
                }
            }
        }else{
            $result['count'] = D(CONTROLLER_NAME)
                ->setDacFilter("a")
                ->join("wrk_invoice_plan_detail b on a.id = b.plan_id")
                ->join("wrk_agreement c on a.agreement_id = c.id")
                ->where($condition)->count("distinct(b.id)");
        }
        return $result;
    }

    public function listAction(){
        $type = I("post.type");//是否是有计划开票
        $result = $this->getList(1,$type);
        $this->ajaxReturn($result);
    }

    //筛选条件
    public function parseFilter($list,$type){
        $postData = I("post.");
        $condition = [];
        $condition['a.branch_id'] = getBrowseBranchId();
        $condition['a.type'] = $type;//1有计划0无计划
        $condition['a.creater_id'] = array("neq",'');//有creater_id代表已创建开票工作（无计划、有计划）
        if($type == 1){
            //有计划开票
            $condition['b.type'] = 0;//开票计划
            $condition['b.is_invoice'] = 1;//开票开关开启
            if($postData['timeType'] == 0 && $postData['timeType'] != ""){
                //自定义时间
                $condition['b.plan_day'] = array(array("egt",$postData['zdyTimeStart']),array("elt",$postData['zdyTimeEnd']));
            }elseif($postData['timeType'] != ""){
                $date = D("WrkAgreement")->getQdrDate($postData['timeType']);
                $condition['b.plan_day'] = array(array("egt",$date['begin']),array("elt",$date['end']));
            }
            if($postData['state'] == 2){
                //逾期状态下 计划状态不为已开或已取消，计划时间小于今天
                $condition['b.state'] = array(array("neq",1),array("neq",100));
                $date = D("WrkAgreement")->getQdrDate(1);
                $condition['b.plan_day'] = array("lt",$date['begin']);
            }elseif($postData['state'] != ""){
                $condition['b.state'] = $postData['state'];
            }
        }else{
            //无计划开票
            if($postData['state'] == 1){
                $condition['a.state'] = array("in","1,2");
            }elseif($postData['state'] != ""){
                $condition['a.state'] = $postData['state'];
            }
            if($postData['timeType'] == 0 && $postData['timeType'] != ""){
                //自定义时间
                $condition['a.latest_invoice_time'] = array(array("egt",$postData['zdyTimeStart']),array("elt",$postData['zdyTimeEnd']));
            }else if($postData['timeType'] != ""){
                $date = D("WrkAgreement")->getQdrDate($postData['timeType']);
                $condition['a.latest_invoice_time'] = array(array("egt",$date['begin']),array("elt",$date['end']));
            }
        }
        if($list && $postData['keyword']){
            $condition["_string"] = "company.name like '%".$postData['keyword']."%' or c.name like '%".$postData['keyword']."%'";
        }
        return $condition;
    }

    //有计划、无计划首页
    public function toListAction($type){
        if($type){
            $this->title = "有计划开票";
            $this->type = $type;
        }else{
            $this->title = "无计划开票";
            $this->type = $type;
        }
        $this->display("invoiceList");
    }


    //开票详情页面
    public function detailAction(){
        $plan_id = I("get.plan_id");
        $id = I("get.id");
        if($id){
            $this->assign("id",$id);
            $this->assign("type",1);
        }else{
            $this->assign("type",0);
        }
        $instance_permit = D(CONTROLLER_NAME)->getPermitValue($plan_id);
        $this->assign("instance_permit",$instance_permit);
        $this->assign("view_header",I("get.view_header") == "" ? 1 : 0);
        $this->assign("plan_id",$plan_id);
        $this->assign("title","开票详情");
        $this->display();

    }

    //获取详情信息
    public function getDetailAction(){
        $plan_id = I("post.plan_id");
        $id = I("post.id");
        if($plan_id){
            $result = D("WrkInvoicePlan")
                //->setDacFilter("a")
                ->alias("a")
                ->join("left join wrk_agreement wag on a.agreement_id = wag.id")
                ->join("left join sys_branch company on wag.company_id = company.id")
                ->field("a.*,company.name as company_name,wag.name as wag_name,wag.agreement_money")
                ->where("a.id = $plan_id")
                ->find();
            $result['wag_amount_balance'] = ($result['agreement_money'] - $result['amount_paid']) >= 0 ? ($result['agreement_money'] - $result['amount_paid']) : 0;
            $result['free_amount'] = $result['amount_paid'] - $result['agreement_money'];
            if($id){
                $tmp = M("WrkInvoicePlanDetail")->where("plan_id = $plan_id and type = 0")->field("id,plan_day,state,plan_money,true_money")->order("id asc")->select();
                $result['detail_count'] = count($tmp);//计划总期数
                $index = array_search($id,array_column($tmp,"id"));
                $result['current_count'] = $index + 1;//当前期数
                $result['plan_day_fmt'] = date("Y-m-d",$tmp[$index]['plan_day']);
                $result['current_state'] = $tmp[$index]['state'];//当期状态
                $result['current_state_value'] = $this->getStateValue($tmp[$index]['state'],1);
                $result['plan_money'] = $tmp[$index]['plan_money'];
                $result['plan_money_balance'] = ($tmp[$index]['plan_money'] - $tmp[$index]['true_money']) >= 0 ? ($tmp[$index]['plan_money'] - $tmp[$index]['true_money']):0;
            }else{
                $result['plan_money_balance'] = $result['wag_amount_balance'];
            }
            $this->ajaxReturn($result);
        }
    }

    public function getStateValue($state,$type){
        $stateArray = [
            0=>[0=>"未结束",1=>"已结束",2=>"已结束"],
            1=>[0=>"未开",1=>"全部已开",2=>"部分已开",100=>"已取消"]
        ];
        return $stateArray[$type][$state];
    }

    //结束开票
    public function finishInvoiceAction($plan_id){
        $model = M("WrkInvoicePlan");
        try{
            $model->startTrans();
            $instance_permit = D(CONTROLLER_NAME)->getPermitValue($plan_id);
            if($instance_permit < 4){
                $this->ajaxReturn(array("error"=>1,"message"=>"您没有权限！"));
            }
            $model->where("id = $plan_id")->setField("state",2);
            //if(I("post.id")){
                //取消有计划中未开的计划
                $condition['plan_id'] = $plan_id;
                $condition['state'] = array(array("neq",1),array("neq",2));//不等于已开、部分已开
                $condition['type'] = 0;
                M("WrkInvoicePlanDetail")->where($condition)->setField("state",100);
            //}
            D(CONTROLLER_NAME)->addLog("WrkInvoicePlanDetail",ACTION_NAME,$plan_id);
            $model->commit();
            D("WrkInvoicePlan")->sendFinishInvoicePlan($plan_id,I("post.id"));
            $this->ajaxReturn(array("error"=>0,"message"=>"结束开票成功！"));
        }catch(\Think\Exception $e){
            $model->rollback();
            $this->ajaxReturn(array("error"=>1,"message"=>"结束开票失败！"));
        }

    }

    //新增开票
    public function invoiceAction($plan_id){
        if(IS_POST){
            $model = M("WrkInvoicePlan");
            try{
                $model->startTrans();
                $instance_permit = D(CONTROLLER_NAME)->getPermitValue($plan_id);
                if($instance_permit < 4){
                    $this->ajaxReturn(array("error"=>1,"message"=>"您没有权限！"));
                }
                $state = M("WrkInvoicePlan")->where("id = $plan_id")->getField("state");
                if($state != 0){
                    $this->ajaxReturn(array("error"=>1,"message"=>"操作失败，开票计划已结束！"));
                }
                $data = I("post.data");
                $list = D(CONTROLLER_NAME)->addInvoiceRecord($data,$plan_id);
                $detail_id = D(CONTROLLER_NAME)->handlerInvoicePlan($list['invoice_sum'],$plan_id);
                D(CONTROLLER_NAME)->sendWXNewInvoiceMessage($list,$plan_id,$detail_id);
                D(CONTROLLER_NAME)->addLog("WrkInvoicePlanDetail",ACTION_NAME,$plan_id);
                $model->commit();
                $this->ajaxReturn(array("error"=>0,"message"=>"开票成功！"));
            }catch(\Think\Exception $e){
                $model->rollback();
                $this->ajaxReturn(array("error"=>1,"message"=>"开票失败！"));
            }
        }else{
            $plan = M("WrkInvoicePlan")->where("id = $plan_id")->field("id,attach_group")->find();
            $this->assign("plan",$plan);
            $this->assign("invoice_day_fmt",date("Y-m-d",time()));
            $this->assign("invoice_day",time());
            $this->display();
        }
    }

    //作废发票
    public function cancelInvoiceAction(){
        $model = M("WrkInvoicePlan");
        try{
            $model->startTrans();
            $id = I("post.id");
            if($id){
                $plan_id = I("post.plan_id");
                $instance_permit = D(CONTROLLER_NAME)->getPermitValue($plan_id);
                if($instance_permit < 4){
                    $this->ajaxReturn(array("error"=>1,"message"=>"您没有权限！"));
                }
                $result = M("WrkInvoicePlan a ")
                    ->join("wrk_invoice_record b on a.id = b.plan_id")
                    ->where("b.id = $id")->field("a.state,b.state as record_state")->find();
                //开票计划手动结束或该发票已经作废
                if($result['state'] == 2 or $result['record_state'] == 0){
                    $this->ajaxReturn(array("error"=>1,"message"=>"操作失败，无法作废该发票！"));
                }
                //作废发票
                M("WrkInvoiceRecord")->where("id = $id")->setField("state",0);
                D(CONTROLLER_NAME)->handlerCancelInvoiceData($id);
                $model->commit();
                $this->ajaxReturn(array("error"=>0,"message"=>"操作成功！"));
            }
        }catch(\Think\Exception $e){
            $model->rollback();
            $this->ajaxReturn(array("error"=>1,"message"=>"操作失败！"));
        }
    }

    //开票记录
    public function invoiceRecordAction($plan_id){
        $condition['plan_id'] = $plan_id;
        $plan = M("WrkInvoicePlan")->where("id = $plan_id")->field("state,attach_group,id")->find();
        $list = D(CONTROLLER_NAME)->getRecordList($plan_id);
        $instance_permit = D(CONTROLLER_NAME)->getPermitValue($plan_id);
        $this->assign("instance_permit",$instance_permit);
        $this->assign("list",$list);
        $this->assign("count",count($list));
        $this->assign("plan",$plan);
        $this->display();
    }


}