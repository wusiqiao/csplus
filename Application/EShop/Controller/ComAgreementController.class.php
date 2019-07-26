<?php
namespace EShop\Controller;
use Think\Controller;
class ComAgreementController extends BaseController {
    protected $stateValue = [
        "agreement"=>[1=>"服务中", 2=>"冻结中", 3=>"已结束"],
        "invoice"=>[0=>"未结束",1=>"已结束",2=>"已结束"],
    ];

    public function indexAction(){
        $this->assign("title","合同管理");
        $this->assignPermissions();
        $this->display();
    }

    public function listAction(){
        $postData = I("post.");
        $condition = [];
        $this->parseFilter($postData,$condition,"agreement");
        $result = D(CONTROLLER_NAME)
            ->alias("a")
            ->join("wrk_invoice_plan wip on a.id = wip.agreement_id")
            ->where($condition)
            ->field("a.*")
            ->page($postData['page'],20)
            ->select();
        foreach ($result as $k=>$v){
            $result[$k]['start_time_fmt'] = date("Y-m-d",$v['start_time']);
            $result[$k]['finish_time_fmt'] = $v['finish_time'] == "" ? "无":date("Y-m-d",$v['finish_time']);
            $result[$k]['state_value'] = $this->stateValue["agreement"][$v['state']];
        }
        $this->ajaxReturn($result);
    }

    public function parseFilter($postData,&$condition,$type){
        $condition['a.company_id'] = session("wrk_company_id");
        $condition['a.branch_id'] = getBrowseBranchId();
        $condition['a.state'] = array("neq",0);
        if($postData['keyword'] != ""){
            $condition['a.name'] = array("like","%".$postData['keyword']."%");
        }
        if($type == "invoice"){
            $condition["wip.is_sendWX"] = 1;//需要商户端设置为开通客户端功能才显示在列表中
            $condition["wip.creater_id"] = array("neq","");//需要商户端设置为开通客户端功能才显示在列表中
            if($postData['state'] == 1){
                $condition['wip.state'] = array("in","1,2");
            }elseif($postData['state'] != ""){
                $condition['wip.state'] = $postData['state'];
            }
        }else{
            if($postData['state'] != ""){
                $condition['a.state'] = $postData['state'];
            }
        }
    }

    public function detailAction($id){
        $result = D("WrkAgreement")->getAgreementDetail($id);
        $this->assign("title","合同详情");
        $this->assign("model",$result);
        $this->display();
    }

    //发票管理
    public function invoiceListAction(){
        if(IS_POST){
            $condition = [];
            $postData = I("post.");
            $this->parseFilter($postData,$condition,"invoice");
            $result = M("WrkAgreement a")
                ->join("wrk_invoice_plan wip on a.id = wip.agreement_id")
                ->field("a.*,wip.state as wip_state,wip.amount_paid")
                ->page($postData['page'],20)
                ->where($condition)
                ->select();
            foreach ($result as $k=>$v){
                $result[$k]['state_value'] = $this->stateValue["invoice"][$v['wip_state']];
                $result[$k]['amount_balance'] = ($v['agreement_money'] - $v['amount_paid']) < 0 ? "0":($v['agreement_money'] - $v['amount_paid']);
            }
            $this->ajaxReturn($result);
        }else{
            $this->assign("title","发票管理");
            $this->display();
        }
    }

    //发票详情
    public function invoiceDetailAction($id){
        $result = M("WrkAgreement a")
            ->join("left join wrk_invoice_plan b on a.id = b.agreement_id")
            ->join("left join sys_branch c on a.company_id = c.id")
            ->where("a.id = $id")
            ->field("a.*,c.name as company_name,b.amount_paid,b.state as wip_state,b.id as wip_id,b.attach_group as wip_attach_group")
            ->find();
        $result['amount_balance'] = ($result['agreement_money']-$result['amount_paid']) < 0? 0 :($result['agreement_money']-$result['amount_paid']);
        if($result['state'] != 0){
            $result['free_amount'] = $result['amount_paid']-$result['agreement_money'];
        }
        $result['state_value'] = $result['wip_state'] == 0 ? "未结束" : "已结束";
        $record = D("WrkInvoicePlan")->getRecordList($result['wip_id']);
        $this->assign("model",$result);
        $this->assign("record",$record);
        $this->assign("record_count",count($record));
        $this->assign("view_header",I("get.view_header") == "" ? 1 : 0);
        $this->assign("title","发票详情");
        $this->display();
    }

    //签收发票
    public function confirmInvoiceAction($id){
        $data['id'] = $id;
        $data['confirm_man'] = $_SESSION['user_id'];
        $data['express_state'] = 1;
        $result = M("WrkInvoiceRecord")->save($data);
        if($result){
            $this->ajaxReturn(array("error"=>0,"message"=>"签收成功！","confirm_name"=>$_SESSION['user_name']));
        }else{
            $this->ajaxReturn(array("error"=>1,"message"=>"签收失败！"));
        }
    }

    //客户端公司管理页面
    public function companyAction(){
        $company = D(CONTROLLER_NAME)->getUserCompany();
        $this->assign("company",$company);
        $this->assign("title","公司管理");
        if(count($company) > 1 && !session("wrk_company_id")){
            header("location:/ComAgreement/selectCompany");
        }elseif(count($company) <= 1 && !session("wrk_company_id")){
            session("wrk_company_id",$company[0]['value']);
            session("wrk_company_name",$company[0]['text']);
        }
        $this->assign("company_name",session("wrk_company_name"));
        $this->display();
    }

    public function selectCompanyAction($view_header = 1,$init=0,$name = null){
        $company = D(CONTROLLER_NAME)->getUserCompany($name);
        $this->assign("view_header",$view_header);
        $this->assign("init",$init);
        $this->assign("company",$company);
        $this->display("select_company");
    }

    //客户进入公司管理选择公司后设置在session中
    public function setSessionWrkCompanyAction(){
        $company_id = I("post.company_id");
        $company_name = I("post.company_name");
        if($company_id){
            session("wrk_company_id",$company_id);
            session("wrk_company_name",$company_name);
            $this->ajaxReturn(array("error"=>0,"name"=>$company_name));
        }else{
            $this->ajaxReturn(array("error"=>1));
        }
    }

    //客户端公司设置模块 人员管理界面
    public function userManageAction(){
        if(!$_SESSION["wrk_company_id"]){
            die("您没有权限！");
        }
        $isLeader = D(CONTROLLER_NAME)->getIsLeader($_SESSION["wrk_company_id"]);
        $this->assign("isLeader",$isLeader);
        $this->assign("user_id",$_SESSION['user_id']);
        $this->assign("title","人员管理");
        $this->display();
    }

    //客户端获取员工列表
    public function getStaffListAction(){
        if($_SESSION['wrk_company_id']){
            $result = D(CONTROLLER_NAME)->getStaffList();
            $this->ajaxReturn(array("error"=>0,"result"=>$result));
        }else{
            $this->ajaxReturn(array("error"=>1,"result"=>[]));
        }
    }

    //删除客户端的员工
    public function deleteComStaffAction($id){
        if(!$_SESSION["wrk_company_id"]){
            $this->ajaxReturn(array("message"=>"您没有权限！","error"=>1));
        }
        $leader_id = M("SysBranch")->where("id = ".$_SESSION["wrk_company_id"])->getField("customer_leader_id");
        //被删除员工不为管理员 且 当前用户为管理员
        if($leader_id == $_SESSION['user_id'] && $leader_id != $id){
            $condition["user_id"] = array("in",$id);
            $condition["branch_id"] = $_SESSION["wrk_company_id"];
            $condition["type"] = array("neq",ORG_DEPARTMENT);
            $result = M("SysUserBranch")->where($condition)->delete();
            $condition = [];
            $condition['company_id'] = $_SESSION["wrk_company_id"];
            $condition['branch_id'] = getBrowseBranchId();
            $condition['user_id'] = $id;
            $condition['type'] = DAC_SETTING_TYPE_CUSTOMER;
            M("SysUserModuleSetting")->where($condition)->delete();
            if($result){
                $this->ajaxReturn(array("message"=>"删除成功！","error"=>0));
            }else{
                $this->ajaxReturn(array("message"=>"删除失败！","error"=>1));
            }
        }else{
            $this->ajaxReturn(array("message"=>"您没有权限！","error"=>1));
        }
    }

    //客户端员工详情页面
    public function comStaffDetailAction($id){
        $condition['id'] = $id;
        $result = M("SysUser a")
            ->join("sys_user_branch b on a.id = b.user_id")
            ->join("sys_branch c on b.branch_id = c.id")
            ->where("a.id = $id and b.branch_id = ".$_SESSION['wrk_company_id'])
            ->field("a.id,a.name,a.staff_name,a.head_pic,a.mobile,c.customer_leader_id")->find();
        if(IS_POST){
            $this->ajaxReturn($result);
        }else{
            //人员类型 1为管理员 0 为员工
            $result['staff_type'] = ($id == $result['customer_leader_id']) ? 1 : 0;
            //当前session用户为管理员且 当前人员详情不是管理员 时才显示按钮
            if($_SESSION['user_id'] == $result['customer_leader_id']){
                $result["showEditBtn"] = 1;//编辑按钮
                if(!$result['staff_type']){
                    $result["showBtn"] = 1;
                }
            }
            $this->assign("title","人员管理");
            $this->assign("model",$result);
            $this->display();
        }
    }

    //客户端管理员设置用户为管理员
    public function replaceCustomerLeaderAction($id){
        $leader_id = M("SysBranch")->where("id = ".$_SESSION["wrk_company_id"])->getField("customer_leader_id");
        if($_SESSION['user_id'] == $leader_id && $id != $leader_id){
            $result = M("SysBranch")->where("id = ".$_SESSION['wrk_company_id'])->setField("customer_leader_id",$id);
            if($result){
                $this->ajaxReturn(array("error"=>0,"message"=>"操作成功！"));
            }else{
                $this->ajaxReturn(array("error"=>1,"message"=>"操作失败！"));
            }
        }else{
            $this->ajaxReturn(array("error"=>1,"message"=>"您没有权限！"));
        }
    }

    public function saveStaffEditAction($id,$staff_name){
        $result = M("SysUser")->where("id = $id")->update(["staff_name"=>$staff_name]);
        if($result !== false){
            $this->ajaxReturn(array("error"=>0,"message"=>"修改成功！"));
        }else{
            $this->ajaxReturn(array("error"=>1,"message"=>"修改失败！"));
        }
    }

    //客户端添加员工
    public function addUserAction(){
        if(IS_GET){
            $this->assign("title","添加公司人员");
            $this->display();
        }else{
            $isLeader = D(CONTROLLER_NAME)->getIsLeader($_SESSION['wrk_company_id']);
            if(!$isLeader){
                $this->ajaxReturn(array("error"=>1,"message"=>"您没有权限！"));
            }
            $ids = I("post.ids");
            $data = [];
            foreach ($ids as $k=>$id){
                $data[] = ["user_id"=>$id,"branch_id"=>$_SESSION['wrk_company_id'],"type"=>ORG_COMPANY];
                M("SysUser")->where("id = $id")->setField("staff_name",I("post.staff_name"));
            }
            $result = M("SysUserBranch")->addAll($data);
            if($result){
                $this->ajaxReturn(array("error"=>0,"message"=>"添加成功！"));
            }else{
                $this->ajaxReturn(array("error"=>1,"message"=>"添加失败！"));
            }
        }
    }

    //客户端搜索用户用于添加员工
    public function queryUserAction(){
        if(!I("post.mobile")){
            $this->ajaxReturn([]);
        }
        $condition['a.mobile'] = I("post.mobile");
        $condition['a.branch_id'] = getBrowseBranchId();
        $condition['a.user_type'] = array(USER_TYPE_CUSTOMER,USER_TYPE_PROSPECTIVE,"or");
        $condition['a.role_ids'] = ROLE_ID_CUSTOMER;
        $result = M("SysUser a")
            ->where($condition)->field("a.id,a.name,a.head_pic")->select();
        foreach ($result as $k=>$v){
            $condition = [];
            $condition['user_id'] = $v['id'];
            $condition['branch_id'] = $_SESSION['wrk_company_id'];
            $condition['type'] = ORG_COMPANY;
            $is_add = M("SysUserBranch")->where($condition)->find();
            $result[$k]['is_add'] = empty($is_add) ? 0 : 1;
        }
        $this->ajaxReturn($result);
    }

    //客户端开票信息
    public function invoiceInfoAction(){
        $result = M("SysBranch")->where("id = ".$_SESSION['wrk_company_id'])
            ->field("id,invoice_title,taxpayer_identification,reg_address,telephone,bank,bank_account,customer_leader_id")->find();
        if(IS_GET){
            $this->assign("isLeader",$_SESSION['user_id'] == $result['customer_leader_id'] ? 1 : 0);
            $this->assign("title","开票资料");
            $this->assign("model",$result);
            $this->display();
        }else{
            if($_SESSION['user_id'] != $result['customer_leader_id']){
                $this->ajaxReturn(array("error"=>1,"message"=>"您没有权限！"));
            }
            $postData = I("post.data");
            $data = [];
            foreach($postData as $k=>$v){
                $data[$v['name']] = $v['value'];
            }
            $result = M("SysBranch")->where("id = ".$_SESSION['wrk_company_id'])->save($data);
            if($result !== false){
                $this->ajaxReturn(array("error"=>0,"message"=>"保存成功！"));
            }else{
                $this->ajaxReturn(array("error"=>1,"message"=>"保存失败！"));
            }
        }
    }

    public function workSettingAction(){
        if(IS_GET){
            $isLeader = D(CONTROLLER_NAME)->getIsLeader($_SESSION['wrk_company_id']);
            $this->assign("isLeader",$isLeader);
            $this->assign("title","工作设置");
            $this->display();
        }
    }

    //获取模块设置人员
    public function getWorkUserAction(){
        $result = D(CONTROLLER_NAME)->getWorkSettingUsers();
        $this->ajaxReturn($result);
    }

    //客户端工作人员设置恢复默认即将设置的人员清空
    public function resetWorkSettingAction(){
        $customer_leader_id = M("SysBranch")->where("id = ".$_SESSION['wrk_company_id'])->getField("customer_leader_id");
        //$isLeader = D(CONTROLLER_NAME)->getIsLeader($_SESSION['wrk_company_id']);
        if($_SESSION['user_id'] != $customer_leader_id){
            $this->ajaxReturn(array("error"=>1,"message"=>"您没有权限！"));
        }
        $condition['branch_id'] = getBrowseBranchId();
        $condition['company_id'] = $_SESSION['wrk_company_id'];
        //需要区分客户端和商户端设置的人员
        $condition['type'] = DAC_SETTING_TYPE_CUSTOMER;
        $result = M("SysUserModuleSetting")->where($condition)->delete();
        $condition['obj_type'] = 1;
        $condition['object_various'] = "customercapital";
        $result1 = M("ComAccountJurisdiction")->where($condition)->delete();
        if($result !== false && $result1 !== false){
            $customer_leader = M("SysUser")->where("id = $customer_leader_id")->field("name,staff_name")->find();
            $name = empty($customer_leader['staff_name']) ? $customer_leader['name'] : $customer_leader['staff_name'];
            $this->ajaxReturn(array("error"=>0,"message"=>"操作成功！","name"=>$name));
        }else{
            $this->ajaxReturn(array("error"=>1,"message"=>"操作失败！"));
        }
    }

    //设置模块人员
    public function setModuleUserAction(){
        if(IS_GET){
            $isLeader = D(CONTROLLER_NAME)->getIsLeader($_SESSION['wrk_company_id']);
            $this->assign("isLeader",$isLeader);
            $this->assign("module",I("get.module"));
            $this->assign("permit_value",I("get.pv"));
            $this->display();
        }else{
            $isLeader = D(CONTROLLER_NAME)->getIsLeader($_SESSION['wrk_company_id']);
            if(!$isLeader){
                $this->ajaxReturn(array("error"=>1,"message"=>"您没有权限！"));
            }
            if(I("post.module") == "customercapital"){
                //资金负责人可见人设置
                $result = D(CONTROLLER_NAME)->handlerCapitalUser();
            }else{
                //其余模块通知人设置
                $result = D(CONTROLLER_NAME)->handlerModuleUser();
            }
            if($result){
                $this->ajaxReturn(array("error"=>0,"message"=>"操作成功！"));
            }else{
                $this->ajaxReturn(array("error"=>1,"message"=>"操作失败！"));
            }
        }
    }

    //获取所有员工，根据模块判断是否被设置为通知人
    public function getSelectedModuleUserAction(){
        $module = I("post.module");
        $staffList = D(CONTROLLER_NAME)->getStaffList();//所有员工
        $selected = D(CONTROLLER_NAME)->getWorkSettingUsers($module);//已设置员工
        foreach ($staffList as $k=>$v){
            if($module == "customercapital"){
                $pv = I("post.permit_value");
                $tmp = array_column($pv == DAC_PERMIT_VALUE_LEADER ?$selected['leader']:$selected['visiblers'],"id");//已选择的人员id数组
            }else{
                $tmp = array_column($selected[$module],"user_id");//已选择的人员id数组
            }
            if(array_search($v['id'],$tmp) !== false && $tmp != null){
                $staffList[$k]['checked'] = 1;
            }else{
                $staffList[$k]['checked'] = 0;
            }
        }
        $this->ajaxReturn($staffList);
    }

}