<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/21
 * Time: 11:00
 */

namespace EShop\Model;


class WrkInvoicePlanModel extends ComplexDataModel
{
    protected $_createrField = "creater_id"; //创建人字段，如果是客户，可以设置成客户对应的字段
    protected $_leaderField = "leader_id";//负责人字段
    protected $_visiblersField = "visiblers"; //可见人字段
    protected $_collaboratorsField = "collaborators"; //协作人，如果原有此字段，就设置，没有就不需要

    /*
     * 结束开票通知 (内部通知，不通知客户)
     * $plan_id 开票id
     * $detail_id 开票某期计划id
     * */
    public function sendFinishInvoicePlan($plan_id,$detail_id = ""){
        $templateId = getWxTemplateIdByStandardId("OPENTM408909668");
        if(!$templateId){
            return false;
        }
        $openid = [];
        $result = M("WrkInvoicePlan a")
            ->join("wrk_agreement b on a.agreement_id = b.id")
            ->join("sys_branch c on b.company_id = c.id")
            ->where("a.id = $plan_id")
            ->field("b.company_id,c.name as branch_name,a.agreement_id")
            ->find();
        //客户通知人
        $customer = D("WrkAgreement")->getCustomerUserData("WrkInvoicePlan",$result['company_id']);
        foreach ($customer as $k => $v){
            $openid[] = $v['openid'];
        }
        $data['invoice_id'] = $result['name']; //合同名称
        $data['invoice_day'] = $result['branch_name']; // 发起商户
        $data['invoice_sum'] = date("Y-m-d H:i"); //变更内容
        $data['remark'] = "合同结束开票";
        $data['first'] = getWxTemplateCurrencyTip("finish_invoice_notice");
        $data['openid'] = array_unique($openid);
        $data['url'] = WEB_ROOT."/ComAgreement/invoiceDetail/id/".$result['agreement_id'];
        $this->handlerWXSendInvoiceData($data,$templateId);
        //商户通知人
        $notifiers = $this->getModuleNotifiersOpenid("WrkInvoicePlan",$result['company_id']);
        if($notifiers){
            $data['url'] = WEB_ROOT."/WrkInvoicePlan/detail/plan_id/$plan_id";
            /*if($detail_id){
                $data['url'] = $data['url']."/id/".$detail_id;
            }*/
            $data['remark'] = "点击进入开票详情";
            $data['first'] = getWxTemplateCurrencyTip("branch_finish_invoice_notice");
            $data['openid'] = array_column($notifiers,"openid");
            $this->handlerWXSendInvoiceData($data,$templateId);
        }
    }

    public function handlerWXSendInvoiceData($data,$templateId){
        if($data){
            $message = array();
            $body = array();
            $message['template_id'] = $templateId;
            $message['url'] = $data['url'];
            $body['first']['value'] = $data['first'];
            $body['keyword1']['value'] = $data['invoice_id'];
            $body['keyword2']['value'] = $data['invoice_day'];
            $body['keyword3']['value'] = $data['invoice_sum'];
            $body['remark']['value'] = $data['remark'];
            $message['body'] = $body;
            if(is_array($data['openid'])){
                foreach($data['openid'] as $val){
                    if($val != ""){
                        $message['openid'] = $val;
                        send_wx_message($message);
                    }
                }
            }else{
                $message['openid'] = $data['openid'];
                send_wx_message($message);
            }
        }
    }

    public function getModuleNotifiersOpenid($module,$company_id){
        $condition['a.module'] = $module;
        $condition['a.company_id'] = $company_id;
        $condition['a.branch_id'] = getBrowseBranchId();
        $condition['a.permit_value'] = DAC_PERMIT_VALUE_NOTICER;//通知人
        $condition["a.type"] = DAC_SETTING_TYPE_BRANCH;
        $notifiers = M("SysUserModuleSetting a")->join("sys_user b on a.user_id = b.id")->where($condition)->field("b.openid")->select();
        return $notifiers;
    }

    //处理开票计划数据
    public function handlerInvoicePlan($sum,$plan_id){
        //累加开票总金额，更新开票最新时间
        $plan = M("WrkInvoicePlan a")
            ->join("wrk_agreement b on a.agreement_id = b.id")
            ->where("a.id = $plan_id")
            ->field("a.amount_paid,b.agreement_money,a.type")
            ->find();
        $plan_data['latest_invoice_time'] = time();
        $plan_data['amount_paid'] = $plan['amount_paid'] + $sum;
        //如果是无计划开票 并且 已开金额大于等于合同金额 则开票状态结束
        if(!$plan['type'] && $plan_data['amount_paid'] >= $plan['agreement_money']){
            $plan_data['state'] = 1;
            //自动结束开票日志
            $this->addLog("WrkInvoicePlanDetail","autoFinishInv",$plan_id);
        }
        M("WrkInvoicePlan")->where("id = $plan_id")->save($plan_data);
        //如果是有计划开票
        if($plan['type']){
            $condition = [];
            $condition["plan_id"] = $plan_id;
            $condition["state"] = array(array("neq",1),array("neq",100));//状态不等于全部已开和已取消
            $condition['is_invoice'] = 1;//开票开关开启的
            $condition['type'] = 0;
            $detailList = M("WrkInvoicePlanDetail")->where($condition)->order("plan_day asc")->select();
            if(!$detailList){
                $this->ajaxReturn(array("error"=>1,"message"=>"暂无已开启开票的计划！"));
            }
            $this->handlerPlanDetailData($plan_id,$sum,$detailList);
            return $detailList[0]['id'];
        }
        return "";
    }

    //处理有计划所有单期计划数据
    public function handlerPlanDetailData($plan_id,$sum,$detailList){
        foreach($detailList as $k=>$v){
            $mask = $sum - ($v['plan_money'] - $v['true_money']);
            //如果新开票金额减去本期剩余未开金额 大等于0
            if($mask >= 0){
                $current['invoice_day'] = time();
                $current['state'] = 1;
                if($k == count($detailList) - 1){
                    //如果是最后一期计划 则结束
                    $current['true_money'] = $v['true_money'] + $sum;
                    $sum = 0;
                    M("WrkInvoicePlan")->where("id = $plan_id")->setField("state",1);
                    $this->addLog("WrkInvoicePlanDetail","autoFinishInv",$plan_id);
                }else{
                    $current['true_money'] = $v['plan_money'];
                    $sum = $sum - ($v['plan_money'] - $v['true_money']);
                }
                M("WrkInvoicePlanDetail")->where("id = ".$v['id'])->save($current);
            }else{
                //如果新开金额未达到结束计划的条件，则本期部分已开
                $current['true_money'] = $sum + $v['true_money'];
                $current['state'] = 2;
                $current['invoice_day'] = time();
                M("WrkInvoicePlanDetail")->where("id = ".$v['id'])->save($current);
                break;
            }
        }
    }

    public function addLog($func,$operation,$id){
        $user = M("SysUser a")->join("sys_branch b on a.branch_id = b.id")->where("a.id = ".$_SESSION['user_id'])->field("a.name,a.staff_name,b.name as branch_name")->find();
        $data["user_name"] = empty($user['staff_name'])? $user['name'] : $user['staff_name'];
        $data["branch_name"] = $user['branch_name'];
        $data["kind"] = 0;
        $data["func"] = $func;
        $data["operation"] = $operation;
        $data["content"] = $id;
        $data["create_time"] = time();
        $data["ip"] = get_client_ip();
        M("SysLog")->add($data);
    }

    //添加开票记录
    public function addInvoiceRecord($data,$plan_id){
        $list = [];
        foreach($data as $k=>$v){
            $list[$v['name']] = $v['value'];
        }
        $list['branch_id'] = getBrowseBranchId();
        $list['plan_id'] = $plan_id;
        $list['create_time'] = time();
        $list['creater_id'] = $_SESSION['user_id'];
        M("WrkInvoiceRecord")->add($list);
        return $list;
    }

    //新增开票通知
    public function sendWXNewInvoiceMessage($list,$plan_id,$detail_id = ""){
        $templateId = getWxTemplateIdByStandardId("OPENTM411013137");
        if(!$templateId){
            return false;
        }
        $result = M("WrkInvoicePlan a")
            ->join("wrk_agreement b on a.agreement_id = b.id")
            ->join("sys_branch c on b.company_id = c.id")
            ->where("a.id = $plan_id")->field("a.agreement_id,b.company_id")->find();
        $openid= [];
        //客户通知人
        $customer = D("WrkAgreement")->getCustomerUserData(CONTROLLER_NAME,$result['company_id']);
        foreach ($customer as $k => $v){
            $openid[] = $v['openid'];
        }
        $data['openid'] = $openid;
        $data["first"] = getWxTemplateCurrencyTip("new_invoice_notice");
        $data["invoice_id"] = $list['invoice_id'];
        $data["invoice_day"] = $list['invoice_day'];
        $data["invoice_sum"] = $list['invoice_sum'];
        $data['remark'] = "点击查看详情";
        $data['url'] = WEB_ROOT."/ComAgreement/invoiceDetail/id/".$result["agreement_id"];
        $this->handlerWXSendInvoiceData($data,$templateId);
        //商户通知人
        $notifiers = $this->getModuleNotifiersOpenid("WrkInvoicePlan",$result['company_id']);
        if($notifiers){
            $data['url'] = WEB_ROOT."/WrkInvoicePlan/detail/plan_id/$plan_id";
            /*if($detail_id){
                $data['url'] = $data['url']."/id/".$detail_id;
            }*/
            $data["first"] = getWxTemplateCurrencyTip("branch_new_invoice_notice");
            $data['openid'] = array_column($notifiers,"openid");
            $this->handlerWXSendInvoiceData($data,$templateId);
        }
    }

    //发票作废
    public function handlerCancelInvoiceData($id){
        $result = M("WrkInvoicePlan a")
            ->join("wrk_invoice_record b on a.id = b.plan_id")
            ->join("wrk_agreement c on a.agreement_id = c.id")
            ->join("sys_branch d on c.company_id = d.id")
            ->where("b.id = $id")
            ->field("a.agreement_id,a.amount_paid,c.agreement_money,a.type,b.invoice_sum,a.id,b.invoice_id,b.invoice_day,
            c.customer_leader_id,d.customer_leader_id as branch_customer_leader,c.company_id")
            ->find();
        $tmp['amount_paid'] = ($result['amount_paid'] - $result['invoice_sum']) < 0 ? 0 : ($result['amount_paid'] - $result['invoice_sum']);
        //已开票金额小于合同金额，则开票状态变为未结束
        if($tmp['amount_paid'] < $result['agreement_money']){
            $tmp['state'] = 0;//未结束
        }
        M("WrkInvoicePlan")->where("id = ".$result['id'])->save($tmp);
        $cancelMoney = $result['invoice_sum'];//作废金额
        //如果是有计划开票
        if($result['type'] == 1){
            //获取状态为已开或部分已开的每期计划，相应减去作废金额
            $plan_detail = M("WrkInvoicePlanDetail")->where("plan_id = ".$result['id']." and state in (1,2)")->order("plan_day desc")->select();
            foreach($plan_detail as $k=>$v){
                $mask = $result['invoice_sum'] - $v['true_money'];
                //如果作废发票金额大等于本期已开金额，则本期变为未开,否则变为部分已开
                if($mask >= 0){
                    $result['invoice_sum'] = $result['invoice_sum'] - $v['true_money'];
                    $v['state'] = 0;
                    $v['true_money'] = 0;
                    M("WrkInvoicePlanDetail")->save($v);
                }else{
                    $v['true_money'] = $v['true_money'] - $result['invoice_sum'];
                    if($v['true_money'] < $v['plan_money']){
                        $v['state'] = 2;
                    }
                    M("WrkInvoicePlanDetail")->save($v);
                    break;
                }
            }
            //将结束开票变为已取消的计划重新激活
            M("WrkInvoicePlanDetail")->where("plan_id = ".$result['id']." and state = 100 ")->setField("state",0);
            $this->sendWXCancelInvoiceMessage($result,$cancelMoney,$plan_detail[0]['id']);
        }else{
            $this->sendWXCancelInvoiceMessage($result,$cancelMoney);
        }
        $this->addLog("WrkInvoicePlanDetail",ACTION_NAME,$result['id']);
    }

    //发票作废通知
    public function sendWXCancelInvoiceMessage($result,$cancelMoney,$detail_id = ""){
        $templateId = getWxTemplateIdByStandardId("OPENTM411013137");
        if(!$templateId){
            return false;
        }
        $openid = [];
        //客户通知人
        $customer = D("WrkAgreement")->getCustomerUserData("WrkInvoicePlan",$result['company_id']);
        foreach ($customer as $k => $v){
            $openid[] = $v['openid'];
        }
        $data["openid"] = $openid;
        $data['remark'] = "点击查看详情";
        $data["invoice_id"] = $result['invoice_id'];
        $data["invoice_day"] = date("Y/m/d",$result['invoice_day']);
        $data["invoice_sum"] = $cancelMoney;//作废金额
        $data["first"] = getWxTemplateCurrencyTip("cancel_invoice_notice");
        if($data['openid'] != ""){
            $data['url'] = WEB_ROOT."/ComAgreement/invoiceDetail/id/".$result['agreement_id'];
            $this->handlerWXSendInvoiceData($data,$templateId);
        }
        //商户通知人
        $notifiers = $this->getModuleNotifiersOpenid("WrkInvoicePlan",$result['company_id']);
        if($notifiers){
            $data['url'] = WEB_ROOT."/WrkInvoicePlan/detail/plan_id/".$result['id'];
            /*if($detail_id){
                $data['url'] = $data['url']."/id/".$detail_id;
            }*/
            $data["first"] = getWxTemplateCurrencyTip("branch_cancel_invoice_notice");
            $data['openid'] = array_column($notifiers,"openid");
            $this->handlerWXSendInvoiceData($data,$templateId);
        }
    }

    public function getRecordList($plan_id){
        $condition['plan_id'] = $plan_id;
        $list = M("WrkInvoiceRecord")->where($condition)->order("invoice_day desc")->select();
        foreach($list as $k=>$v){
            $list[$k]['invoice_day_fmt'] = date("Y-m-d",$v['invoice_day']);
            if($v['confirm_man']){
                $list[$k]['confirm_man_name'] = M("SysUser")->where("id = ".$v['confirm_man'])->getField("name");
            }else{
                $list[$k]['confirm_man_name'] = "未签收";
            }
            $list[$k]['invoice_type_value'] = $v['invoice_type'] == 0 ? "普通发票":"专用发票";
        }
        return $list;
    }
}