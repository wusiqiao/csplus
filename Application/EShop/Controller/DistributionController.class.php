<?php

namespace EShop\Controller;

use Think\Controller;

class DistributionController extends UserLoginController {
    public function indexAction(){
        $this->assign("my_head_pic", session("head_pic"));
        $this->title = '分销管理';
        $this->products = D("ESAdmin/DistributionSetting")->getProductHasCommision(getBrowseBranchId());
        $this->display();
    }

    public function myIncomeAction(){
        $model = D("SysUser")->getMyProfit();
        $this->assign("model", $model);
        $this->title = '我的收益';
        $this->display("my_income");
    }

    //提现
    public function withdrawAction(){
        $withdrawal_amount = I("post.withdrawal_amount");
        if ($withdrawal_amount <= 0){
            $this->ajaxReturn(buildMessage("提现失败，金额小于零", 1));
        }
        if (D("SysUser")->getCommisionCanWithdraw() < $withdrawal_amount){
            $this->ajaxReturn(buildMessage("提现失败，金额不能超过可提现金额", 1));
        }
        $data["user_id"] = session("user_id");
        $data["branch_id"] = getBrowseBranchId();
        $data["money"] = $withdrawal_amount;
        $data["create_time"] = time();
        $data["status"] = 0;
        if (M("DistributionWithdrawal")->add($data)){
            $this->ajaxReturn(buildMessage("提现申请成功，请等待商家确认！"));
        }else{
            $this->ajaxReturn(buildMessage("提现失败，请联系商家客服咨询", 1));
        }
    }

    public function myTeamAction(){
        $member_profits = D("SysUser")->getTeamMember();
        $this->assign('member_profits',$member_profits);
        $this->title = '我的下级';
        $this->display("my_team");
    }

    /**下级成员贡献列表
     * @param $openid
     */
    public function getTeamMemberCommisionIncomeDetailAction($member_id){
        $list = D("SysUser")->getTeamMemberCommisionIncomeDetail($member_id);
        $this->ajaxReturn(buildResult($list));
    }

    public function share_qrcode(){
       $model   =  D("Home/Users")->getUserQrcodeData();
       $this->assign('model',$model);
       $this->display();
    }

}