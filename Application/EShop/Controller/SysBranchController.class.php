<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 19-1-8
 * Time: 上午9:16
 */

namespace Eshop\Controller;
use Think\Controller;

class SysBranchController extends Controller
{
    //获取设置管理员验证码
    public function getCodeForBoundLeaderAction(){
        $_SESSION['leaderCode'] = rand(1000,9999);
        $mobile = I("post.mobile","","strip_tags");
        $beg_time = strtotime(date("Y-m-d"));
        /*$user = M("SysUser")->where("mobile = $mobile")->count();
        if(!$user){
            $this->ajaxReturn(array("error"=>1,"message"=>"发送失败，该手机号未绑定账户！"));
        }*/
        $condition['mobile'] = $mobile;
        $condition['type'] = "设置管理员";
        $condition['begtime'] = $beg_time;
        $sms_all = D("sms_log")->where($condition)->count();
        if($sms_all > 5){
            $this->ajaxReturn(array("error"=>1,"message"=>"发送失败，您今天短信接收已超量！"));
        }else{
            $returnstatus = D("SmsLog")->send_sms_message($mobile,SMS_REG_CODE,array("code"=>$_SESSION['leaderCode']));
            if($returnstatus == "Success"){
                D("sms_log")->add($condition);
                $this->ajaxReturn(array("error"=>0,"message"=>"发送成功！","code"=>$_SESSION['leaderCode']));
            }else{
                $this->ajaxReturn(array("error"=>1,"message"=>"发送失败！"));
            }
        }
    }

    public function boundLeaderAction(){
        checkLogin();
        $user_id = session("user_id");
        $leader_id = M("SysBranch")->where("id=".SHOP_ID)->getField("leader_id");
        if (IS_GET){
            if ($leader_id){
                if ($leader_id == $user_id){
                    die("<div style='font-size: 5vw;text-align: center;margin-top: 40vh'>您已经是管理员了</div>");
                }else{
                   die("<div style='font-size: 5vw;text-align: center;margin-top: 40vh'>已经设定过管理人员，请原管理员先取消绑定！</div>");
                }
            }else {
                $this->display("ComPotential:bound_leader");
            }
        }else{
            if (!$leader_id){
                M("SysBranch")->where("id=".SHOP_ID)->setField("leader_id", $user_id);
                $branch_role = M("SysBranch")->where("id=".SHOP_ID)->getField("branch_role");
                $user_data["role_ids"] = $branch_role;
                $user_data["user_type"] = USER_TYPE_COMPANY_MANAGER;
                $user_data["mobile"] = I("post.mobile");
                $user_data["binded_at"] = time();
                $user_data["is_leader"] = 1;
                $user_data["dac_type"] = DAC_SCOPE_BRANCH;
                M("SysUser")->where("id=$user_id")->save($user_data);
                $sysUserRoleModel = M("SysUserRole");
                $role_data = array("user_id" => $user_id, "role_id" => $branch_role);
                $sysUserRoleModel->where("user_id = $user_id")->delete();
                if ($sysUserRoleModel->where($role_data)->count() == 0){
                    M("SysUserRole")->add($role_data);
                }
                $this->ajaxReturn(buildMessage("设置成功，您已经是管理员了"));
            }
        }
    }
}