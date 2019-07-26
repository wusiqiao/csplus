<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/28
 * Time: 15:21
 */

namespace EShop\Controller;
use Think\Controller;

class WrkAgreementController extends BaseController{

    public function indexAction(){
        $this->assign("title","合同管理");
        $this->display();
    }

    public function listAction(){
        $postData = I("post.");
        $condition = $this->parseFilter($postData);
        $result = D(CONTROLLER_NAME)
            ->setDacFilter("a")
            ->join("wrk_invoice_plan wip on a.id = wip.agreement_id")
            ->join("left join wrk_receivables wr on a.id = wr.contract_id")
            ->join("left join sys_branch c on a.company_id = c.id")
            ->where($condition)
            //->field("a.*,wip.state as wip_state,wr.status as wr_state,c.name as company_name")
            ->field("a.*,c.name as company_name")
            ->page($postData['page'],20)
            ->order("a.id desc")
            ->distinct(true)
            ->select();
        foreach ($result as $k=>$v){
            $result[$k]['start_time_fmt'] = date("Y-m-d",$v['start_time']);
            $result[$k]['finish_time_fmt'] = $v['finish_time'] == "" ? "无":date("Y-m-d",$v['finish_time']);
            $result[$k]['state_value'] = D(CONTROLLER_NAME)->getStateValue($v['state']);
            /*if($postData['keyword']){
                $result[$k]['company_name'] = str_replace($postData['keyword'],"<p class='search-keyword'>".I("post.keyword")."</p>",$v['company_name']);
            }*/
        }
        $this->ajaxReturn($result);
    }

    //筛选条件
    public function parseFilter($postData){
        $condition = [];
        $condition['a.branch_id'] = getBrowseBranchId();
        if($postData['state'] != ""){
            $condition['a.state'] = $postData['state'];
        }
        if($postData['wip_state'] == 1){
            //开票状态已结束
            $condition['wip.state'] = array("in","1,2");
            $condition['a.invoice_type'] = 1;
        }elseif($postData['wip_state'] != ""){
            $condition['a.invoice_type'] = 1;//合同开票类型为开票
            $condition['wip.state'] = $postData['wip_state'];
            $condition['wip.creater_id'] = array("exp","is not NULL");
        }
        if($postData['wr_state'] == 1){
            //收款状态已结束
            $condition['wr.status'] = array("in","1,2");
        }elseif($postData['wr_state'] !=""){
            $condition['wr.status'] = $postData['wr_state'];
        }
        $condition["_string"] = "a.name like '%".$postData['keyword']."%' or c.name like '%".$postData['keyword']."%'";
        if($postData['origin'] != ""){
            $condition["a.origin"] = $postData['origin'];
        }
        return $condition;
    }

    //合同详情
    public function detailAction(){
        $id = I("get.id");
        if($id){
            $result = D(CONTROLLER_NAME)->getAgreementDetail($id);
            $this->assign("model",$result);
            $this->assign("title","合同详情");
            $this->display();
        }
    }



    //合同详情页面跳转开票收款判断是否有权限
    public function getModulePermitAction(){
        $module = I("post.module");
        $id = I("post.id");
        $allow = $this->_permissionList['menus'][$module]['allow'];
        $detail_permit = $this->_permissionList['menus'][$module]['menu_actions']['detail'];
        if($allow != 1 or $detail_permit != 1){
            $this->ajaxReturn(array("error"=>1));
        }
        $count = D($module)->setDacFilter("a")->where("a.id = $id")->count();
        if($count){
            $this->ajaxReturn(array("error"=>0));
        }else{
            $this->ajaxReturn(array("error"=>1));
        }
    }

}