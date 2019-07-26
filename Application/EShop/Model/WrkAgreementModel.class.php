<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/21
 * Time: 11:00
 */

namespace EShop\Model;


class WrkAgreementModel extends ComplexDataModel
{
    protected $_createrField = "creater_id"; //创建人字段，如果是客户，可以设置成客户对应的字段
    protected $_leaderField = "leader_id";//负责人字段
    protected $_visiblersField = "visiblers"; //可见人字段
    protected $_collaboratorsField = "collaborators"; //协作人，如果原有此字段，就设置，没有就不需要

    //获得今日本周本月区间
    public function getQdrDate($type){
        $date = [];
        if($type == 1){
            //今日
            $date['begin']=mktime(0,0,0,date('m'),date('d'),date('Y'));
            $date['end']=mktime(0,0,0,date('m'),date('d')+1,date('Y'));
        }elseif($type == 2){
            //本周
            $date['begin']=mktime(0,0,0,date('m'),date('d')-date('w')+1,date('Y'));
            $date['end']=mktime(23,59,59,date('m'),date('d')-date('w')+7,date('Y'));
        }elseif($type == 3){
            //本月
            $date['begin']=mktime(0,0,0,date('m'),1,date('Y'));
            $date['end']=mktime(23,59,59,date('m'),date('t'),date('Y'));
        }elseif($type == 4){
            //本年
            $date['begin'] = strtotime(date("Y",time())."-1"."-1"); //本年开始
            $date['end'] = strtotime(date("Y",time())."-12"."-31"); //本年结束
        }
        return $date;
    }

    public function getAgreementDetail($id){
        $result = D("WrkAgreement")
            //->setDacFilter("a")
            ->alias("a")
            ->join("wrk_invoice_plan wip on a.id = wip.agreement_id")
            ->join("left join wrk_receivables wr on a.id = wr.contract_id")
            ->join("left join wrk_prompt wp on a.id = wp.contract_id")
            ->join("left join sys_branch c on a.company_id = c.id")
            ->where("a.id = $id")
            ->field("a.*,wip.state as wip_state,wr.status as wr_state,wr.id as wr_id,
                wip.leader_id as wip_leader_id,wr.leader_id as wr_leader_id,wip.id as wip_id,
                wp.leader_id as wp_leader_id,wip.creater_id as wip_creater_id,c.name as company_name,
                wip.is_sendWX,wr.is_renew")
            ->find();
        $this->getModuleLeaderInfo($result);
        $result['start_time_fmt'] = date("Y-m-d",$result['start_time']);
        $result['finish_time_fmt'] = $result['finish_time'] == "" ? "无":date("Y-m-d",$result['finish_time']);
        $result['product_options'] = json_decode($result['product_options'],true)[0];
        $result['state_value'] = $this->getStateValue($result['state']);
        $result['wip_state_value'] = $result['wip_state'] == 0 ? "未结束":"已结束";
        $result['wr_state_value'] = $result['wr_state'] == 0 ? "未结束":"已结束";
        return $result;
    }

    //获取合同各模块负责人姓名和联系方式
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

    public function getStateValue($state){
        $stateArray = [0=>"未激活",1=>"服务中",2=>"冻结中",3=>"已结束"];
        return $stateArray[$state];
    }

    //获取客户端模块人员
    public function getCustomerUserData($module,$company_id){
        $condition['a.module'] = $module;
        $condition['a.company_id'] = $company_id;
        $condition['a.branch_id'] = getBrowseBranchId();
        $condition['a.permit_value'] = DAC_PERMIT_VALUE_NOTICER;
        $condition['a.type'] = DAC_SETTING_TYPE_CUSTOMER;
        $result = M("SysUserModuleSetting a")
            ->join("sys_user b on a.user_id = b.id")
            ->field("b.id,b.name,b.staff_name,b.openid")
            ->where($condition)->select();
        //如果未设置此模块人员 则返回客户档案绑定的客户管理员
        if(!$result){
            $result = M("SysBranch a")
                ->join("sys_user b on a.customer_leader_id = b.id")
                ->where("a.id = $company_id")->field("b.id,b.staff_name,b.name,b.openid")->select();
        }
        return $result;
    }
}