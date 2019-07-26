<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class  DistributionIncomeController extends DataController {

    public function indexAction(){
        $id = I("get.id");
        if($id){
            $this->assign("id",$id);
            $this->assign("name",I("get.name"));
        }
        $this->display("index");
    }

    public function listAction(){
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $_order = array();
        $this->_parseOrder($_order);
        $_filter = array();
        $this->_parseFilter($_filter);
        //$count = D(CONTROLLER_NAME)->setDacFilter("a")->where($_filter)->count();
        $result = D(CONTROLLER_NAME)->getIncomeList($_filter,$page_index, $page_size,$_order);
        $result["total"] = $result["count"];
        $result["rows"] = $result["list"];
        $this->responseJSON($result);
    }

    public function unfrozenCommisionAction($id){
        $condition["branch_id"] = getBrowseBranchId();
        $data = M(CONTROLLER_NAME)->find($id);
        if ($data){
            if ($data["branch_id"] == getBrowseBranchId()){
                $saveData["unfrozen_time"] = time();
                $saveData["status"] = 1;
                $saveData["review_user"] = $this->_user_session->userName;
                if (M(CONTROLLER_NAME)->where("id=$id")->save($saveData) != false){
                    //获取邀请人
                    $user =  M('SysUser')->alias('a')
                                ->field('user.id,user.mobile,user.user_money,user.openid')
                                ->join('distribution_relation as relation on relation.openid = a.openid')
                                ->join('sys_user as user on user.id = relation.inviter_id')
                                ->where('a.id='.$data['user_id'])
                                ->find();
                    $saveData["status_view"] = "已解冻";
                    //新增 recharge
                    $payment = M('com_recharge');
                    $payment->user_id = $user['id'];
                    $payment->creator_id = $user['id'];
                    $payment->branch_id =  $data["branch_id"];
                    $payment->pay_status = 1;
                    $payment->order_sn = getOrderNo("DIZ_");
                    $payment->account = $data['commision'];
                    $payment->mobile = $user['mobile'];
                    $payment->ctime = time();
                    $payment->pay_time = time();
                    $payment->pay_name = '个人账户充值(佣金收入)';
                    $payment->money_type = FIN_DIZ_RECHARGE;
                    $payment->source = FIN_PAY_DISTRIBUTION;
                    $payment->add();
                    //新增 finance
                    $finance_table = M('com_finance');
                    $finance_table->fina_type = FIN_DIZ_RECHARGE;
                    $finance_table->fina_cash = $data['commision'];
                    $finance_table->fina_time = time();
                    $finance_table->user_id = $user['id'];
                    $finance_table->branch_id = $data['branch_id'];
                    $finance_table->order_sn = $data['order_sn'];
                    $finance_table->third_fee = 0;
                    $finance_table->platform_fee = 0;
                    $finance_table->title = '个人账户充值(佣金收入)';
                    $finance_table->add();
                    //用户money添加
                    $save['user_money'] = $user['user_money'] + $data['commision'];
                    M('SysUser')->data($save)->where('id='.$user['id'])->save();
                    $this->ajaxReturn(buildResult($saveData));
                }else{
                    $this->ajaxReturn(buildMessage("更新失败",1));
                }
            }
        }else{
            $this->ajaxReturn(buildMessage("找不到对应提现单据",1));
        }
    }
}