<?php

namespace EShop\Controller;

use Think\Controller;

class CheckController extends Controller {

    public function indexAction() {
        $action = I('post.action', '', 'strip_tags');
//============================>>发送注册时候短信验证码 The Start
        if ($action == "checkphone") {
            $_SESSION['regcode'] = rand(1000, 9999);
            $phone = I('post.phone', '', 'strip_tags');
            $branch_id = getBrowseBranchId();//所属公司Id
            $begtime = strtotime(date("Y-m-d"));

            $user = D("SysUser");
            $total = $user->where("account='$phone' and branch_id = ".$branch_id)->count();
            $smsall = D("sms_log")->where("mobile='$phone' and branch_id = ".$branch_id." and type='注册账户' and begtime='$begtime'")->count();
            if ($smsall > 3) {
                echo json_encode(array("result" => "1", "msg" => "短信已达服务上限，请明天再试！"));
                exit();
            }
            if ($total == 0) {
                $returnstatus = sendsms($phone, SMS_REG_CODE, array("code"=>$_SESSION['regcode']));
                if ($returnstatus == 'Success') {
                    $sms_log = D("sms_log");
                    $sms_log->type = "注册账户";
                    $sms_log->mobile = $phone;
                    $sms_log->begtime = $begtime;
                    $sms_log->branch_id= $branch_id;
                    $sms_log->add();

                    echo json_encode(array("result" => "0", "msg" => "验证码已发送到您手机上", "codes" => $_SESSION['regcode']));
                    exit();
                } else {
                    echo json_encode(array("result" => "1", "msg" => "获取失败，请稍后再试！"));
                    exit();
                }
            } else {
                echo json_encode(array("result" => "1", "msg" => "获取失败，您已经注册过了！"));
                exit();
            }


//============================>>修改手机号码
        }
          elseif ($action == "chagephone") {
              $_SESSION['regcode'] = rand(1000, 9999);
              $phone = I('post.phone', '', 'strip_tags');
              $begtime = strtotime(date("Y-m-d"));
              $smsall = D("sms_log")->where("mobile='$phone' and branch_id = " . getBrowseBranchId() . " and type='验证手机号码' and begtime='$begtime'")->count();
              if ($smsall > 5) {
                  echo json_encode(array("result" => "1", "msg" => "短信已达服务上限，请明天再试！"));
                  exit();
              }
              $user = D("SysUser");
              $branch_id = getBrowseBranchId();//所属公司Id
              $total = $user->where("mobile='$phone' and branch_id = " . $branch_id)->count();
              if ($total < 1) {
                  $returnstatus = sendsms($phone, SMS_CHANGE_MOBILE_CODE, array("code" => $_SESSION['regcode']));
                  if ($returnstatus == 'Success') {
                      $sms_log = D("sms_log");
                      $sms_log->type = "验证手机号码";
                      $sms_log->mobile = $phone;
                      $sms_log->begtime = $begtime;
                      $sms_log->branch_id = getBrowseBranchId();
                      $sms_log->add();
                      echo json_encode(array("result" => "0", "msg" => "验证码已发送到您手机上", "codes" => $_SESSION['regcode']));
                      exit();
                  } else {
                      echo json_encode(array("result" => "1", "msg" => "获取失败，请稍后再试！"));
                      exit();
                  }
              } else {
                  echo json_encode(array("result" => "1", "msg" => "该号码已绑定其他账号！"));
                  exit();
              }
          }
//
//
////============================>>设置手机号码
       elseif ($action == "setphone") {
              $_SESSION['regcode'] = rand(1000, 9999);
              $phone = I('post.phone', '', 'strip_tags');
              $begtime = strtotime(date("Y-m-d"));
              $smsall = D("sms_log")->where("mobile='$phone' and branch_id = ".getBrowseBranchId()." and type='设置手机号码' and begtime='$begtime'")->count();
              if ($smsall > 5) {
                  echo json_encode(array("result" => "1", "msg" => "短信已达服务上限，请明天再试！"));
                  exit();
              }
              $user = D("SysUser");
              $branch_id = getBrowseBranchId();//所属公司Id
              $total = $user->where("mobile='$phone' and branch_id = ".$branch_id)->count();
              if ($total == 0){
                  $returnstatus=sendsms($phone, SMS_CHANGE_MOBILE_CODE, array("code"=>$_SESSION['regcode']));
                  if ($returnstatus == 'Success') {
                      $sms_log = D("sms_log");
                      $sms_log->type = "设置手机号码";
                      $sms_log->mobile = $phone;
                      $sms_log->begtime = $begtime;
                      $sms_log->branch_id = getBrowseBranchId();
                      $sms_log->add();
                      echo json_encode(array("result" => "0", "msg" => "验证码已发送到您手机上", "codes" => $_SESSION['regcode']));
                      exit();
                  } else {
                      echo json_encode(array("result" => "1", "msg" => "获取失败，请稍后再试！"));
                      exit();
                  }
              }else{
                  echo json_encode(array("result" => "1", "msg" => "该号码已绑定其他账号！"));
                  exit();
              }
        }
        ////============================>>修改密码
        elseif ($action == "passwordphone") {
            $_SESSION['passworkcode'] = rand(1000, 9999);
            $phone = I('post.phone', '', 'strip_tags');
            $begtime = strtotime(date("Y-m-d"));
            $smsall = D("sms_log")->where("mobile='$phone' and branch_id = ".getBrowseBranchId()." and type='修改密码' and begtime='$begtime'")->count();
            if ($smsall > 5) {
                echo json_encode(array("result" => "1", "msg" => "短信已达服务上限，请明天再试！"));
                exit();
            }
            $user = D("SysUser");
            $branch_id = getBrowseBranchId();//所属公司Id
            $total = $user->where("mobile='$phone' and branch_id = ".$branch_id)->count();
            if ($total == 1){
                $returnstatus=sendsms($phone, SMS_RESET_PASSWORD_CODE, array("code"=>$_SESSION['passworkcode']));
                if ($returnstatus == 'Success') {
                    $sms_log = D("sms_log");
                    $sms_log->type = "修改密码";
                    $sms_log->mobile = $phone;
                    $sms_log->begtime = $begtime;
                    $sms_log->branch_id = getBrowseBranchId();
                    $sms_log->add();
                    echo json_encode(array("result" => "0", "msg" => "验证码已发送到您手机上", "codes" => $_SESSION['passworkcode']));
                    exit();
                } else {
                    echo json_encode(array("result" => "1", "msg" => "获取失败，请稍后再试！"));
                    exit();
                }
            }else{
                echo json_encode(array("result" => "1", "msg" => "获取失败，该手机号码不存在！"));
                exit();
            }
        }
        ////============================>>找回密码发送验证码
        elseif ($action == "checkforget") {
            $_SESSION['regcode'] = rand(1000, 9999);
            $phone = I('post.phone', '', 'strip_tags');
            $begtime = strtotime(date("Y-m-d"));
            $branch_id = getBrowseBranchId();
            $user = D("SysUser");
            $total = $user->where("mobile='$phone' and branch_id = ".$branch_id)->count();
            $smsall = D("sms_log")->where("mobile='$phone' and type='重置密码' and begtime='$begtime'")->count();
            if ($smsall > 5) {
                echo json_encode(array("result" => "1", "msg" => "短信已达服务上限，请明天再试！"));
                exit();
            }
            if ($total == 1) {
                $returnstatus=sendsms($phone, SMS_RESET_PASSWORD_CODE, array("code"=>$_SESSION['regcode']));
                if ($returnstatus == 'Success') {
                    $sms_log = D("sms_log");
                    $sms_log->type = "重置密码";
                    $sms_log->mobile = $phone;
                    $sms_log->begtime = $begtime;
                    $sms_log->add();
                    echo json_encode(array("result" => "0", "msg" => "验证码已发送到您手机上", "codes" => $_SESSION['regcode']));
                    exit();
                } else {
                    echo json_encode(array("result" => "1", "msg" => "获取失败，请稍后再试！"));
                    exit();
                }
            } else {
                echo json_encode(array("result" => "1", "msg" => "获取失败，该手机号不存在！"));
                exit();
            }
//
        } elseif ($action == "tool_nuclear") {
            $_SESSION['msgcode'] = rand(1000, 9999);
            $phone = I('post.phone', '', 'strip_tags');
            $begtime = strtotime(date("Y-m-d"));
            $branch_id = getBrowseBranchId();
            $user = D("SysUser");
            $smsall = D("sms_log")->where("mobile='$phone' and type='核名查询' and begtime='$begtime'")->count();
            if ($smsall > 5) {
                echo json_encode(array("result" => "1", "msg" => "短信已达服务上限，请明天再试！"));
                exit();
            }
            $returnstatus=sendsms($phone, SMS_RESET_PASSWORD_CODE, array("code"=>$_SESSION['msgcode']));
            if ($returnstatus == 'Success') {
                $sms_log = D("sms_log");
                $sms_log->type = "核名查询";
                $sms_log->mobile = $phone;
                $sms_log->begtime = $begtime;
                $sms_log->add();
                echo json_encode(array("result" => "0", "msg" => "验证码已发送到您手机上", "codes" => $_SESSION['msgcode']));
                exit();
            } else {
                echo json_encode(array("result" => "1", "msg" => "获取失败，请稍后再试！"));
                exit();
            }
//
        }elseif ($action == "tool_trademarks") {
            $_SESSION['msgcode'] = rand(1000, 9999);
            $phone = I('post.phone', '', 'strip_tags');
            $begtime = strtotime(date("Y-m-d"));
            $branch_id = getBrowseBranchId();
            $user = D("SysUser");
            $smsall = D("sms_log")->where("mobile='$phone' and type='商标查询' and begtime='$begtime'")->count();
            if ($smsall > 5) {
                echo json_encode(array("result" => "1", "msg" => "短信已达服务上限，请明天再试！"));
                exit();
            }
            $returnstatus=sendsms($phone, SMS_RESET_PASSWORD_CODE, array("code"=>$_SESSION['msgcode']));
            if ($returnstatus == 'Success') {
                $sms_log = D("sms_log");
                $sms_log->type = "商标查询";
                $sms_log->mobile = $phone;
                $sms_log->begtime = $begtime;
                $sms_log->add();
                echo json_encode(array("result" => "0", "msg" => "验证码已发送到您手机上", "codes" => $_SESSION['msgcode']));
                exit();
            } else {
                echo json_encode(array("result" => "1", "msg" => "获取失败，请稍后再试！"));
                exit();
            }
//
        }elseif ($action == "loginphone") {
            $_SESSION['regcode'] = rand(1000, 9999);
            $phone = I('post.phone', '', 'strip_tags');
            $begtime = strtotime(date("Y-m-d"));
            $branch_id = getBrowseBranchId();
            $user = D("SysUser");
            $smsall = D("sms_log")->where("mobile='$phone' and type='短信登录' and begtime='$begtime'")->count();
            if ($smsall > 5) {
                echo json_encode(array("result" => "1", "msg" => "短信已达服务上限，请明天再试！"));
                exit();
            }
            $returnstatus=sendsms($phone, SMS_RESET_PASSWORD_CODE, array("code"=>$_SESSION['regcode']));
            if ($returnstatus == 'Success') {
                $sms_log = D("sms_log");
                $sms_log->type = "短信登录";
                $sms_log->mobile = $phone;
                $sms_log->begtime = $begtime;
                $sms_log->add();
                echo json_encode(array("result" => "0", "msg" => "验证码已发送到您手机上", "codes" => $_SESSION['regcode']));
                exit();
            } else {
                echo json_encode(array("result" => "1", "msg" => "获取失败，请稍后再试！"));
                exit();
            }
//
        }
        elseif ($action == "checkbank") {
            $_SESSION['regcode'] = rand(1000, 9999);
            $phone = I('post.phone', '', 'strip_tags');
            $begtime = strtotime(date("Y-m-d"));
            $user = D("SysUser");
            $total = $user->where("mobile='$phone'")->count();
            $smsall = D("sms_log")->where("mobile='$phone' and type='绑定银行卡' and begtime='$begtime'")->count();
            if ($smsall > 5) {
                echo json_encode(array("result" => "1", "msg" => "短信已达服务上限，请明天再试！"));
                exit();
            }
            if ($total != 0) {
                $returnstatus=sendsms($phone, SMS_RESET_PASSWORD_CODE, array("code"=>$_SESSION['regcode']));
                if ($returnstatus == 'Success') {
                    $sms_log = D("sms_log");
                    $sms_log->type = "绑定银行卡";
                    $sms_log->mobile = $phone;
                    $sms_log->begtime = $begtime;
                    $sms_log->add();
                    echo json_encode(array("result" => "0", "msg" => "验证码已发送到您手机上", "codes" => $_SESSION['regcode']));
                    exit();
                } else {
                    echo json_encode(array("result" => "1", "msg" => "获取失败，请稍后再试！"));
                    exit();
                }
            } else {
                echo json_encode(array("result" => "1", "msg" => "获取失败，该手机号不存在！"));
                exit();
            }
//
        }
    }

}
