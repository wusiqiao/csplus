<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/22
 * Time: 17:32 
 */

namespace EShop\Controller;


use Think\Controller;

class MoneyController extends UserLoginController
{
    CONST COMPANY_RECHARGE_CODE = 1;
    CONST USER_RECHARGE_CODE = 2;
    CONST WITHDRAWALS_TYPE_USER = 2;
    CONST WITHDRAWALS_TYPE_COMPANY = 1;

    public function indexAction(){
        $title = "资金";
        $this->assign('title', $title);
        //if(session('user_type') == USER_TYPE_COMPANY_MANAGER) {
            //$this->error('路径出错');
       // } else {
            $this->handlerHasCompany();
            $this->assign('is_manager',0);
            $user = D('SysUser')->where(array('id' => session('user_id')))->find();
            $this->assign('total_cash',sprintf("%.2f", $user['user_money']));
            $user['user_money'] = sprintf("%.2f", floatval($user['user_money']) - floatval($user['user_money_auditing'])); //显示扣除掉审核中余额
            $user['user_money_auditing'] = sprintf("%.2f",$user['user_money_auditing']);
            if($user['deposit'] > 0) {
                $bank_name = M("sys_bank_ico")->where('id = ' . $user['deposit'])->getField('title');
                $this->assign('bank_name', $bank_name);
                $this->assign("is_bank", 1);
            }
            $this->assign('user',$user);
        //}
        $this->display();
    }
    public function company_selectAction()
    {
        $title = "公司资金";
        $this->assign('title',$title);
        $name = I('get.name');
        if (!empty($name)) {
            $condition['branch.name'] = array('LIKE','%'.$name.'%');
        }
        $condition['user_branch.user_id'] = session('user_id');
        $condition['branch.type'] = 1;
        $aj = D('ComAccountJurisdiction');
        $aj->getVisiblersCompanys();
        $visual_companys = $aj->getStore('visiblers');
        if ($visual_companys) {
            $condition['branch.id'] = array('in',$visual_companys);
        } else {
            $condition['branch.id'] = 0;
        }
        $company =
            D('sysBranch')
                ->alias('branch')
                ->field('branch.id as value,branch.name as text,branch.linkman,branch.contact')
                ->join('sys_user_branch as user_branch on user_branch.branch_id = branch.id')
                ->where($condition)->select();
        $this->assign('company',$company);
        $this->assign('is_view', 1);
        $this->assign('view_url', 'Money/company');
        $this->assign('title','公司选择');
        $this->init = I('get.init') ?? 0;
        $this->display('select_company');
    }
    public function getMoneyDetailsAction()
    {
        if (IS_POST) {
            if (I('post.targetType') == 'company') {
                $finance = D("ComFinance")->getCompanyMoneyDetail(I('post.id'),I('post.page'),I('post.search'));
                foreach($finance as $key => $value) {
                    $revealParagrap = formatRevealParagraphTime($value['pay_time']);
                    $finance[$key]['year'] =   $revealParagrap['year'];
                    $finance[$key]['day'] =   $revealParagrap['day'];
                    $finance[$key]['time'] =   $revealParagrap['time'];
                    $finance[$key]['icon'] =   IMG_URL."tool/".$value['icon'].'-icon.png';
                }
                $this->ajaxReturn($finance);
            } else {
                if(session('user_type') == USER_TYPE_COMPANY_MANAGER){
                    //$finance = D("ComFinance")->getStaffMoneyDetail(session('user_id'),I('post.page'),I('post.search'));
                    $finance = D("ComFinance")->getUserMoneyDetail(session('user_id'),I('post.page'),I('post.search'));
                }else{
                    $finance = D("ComFinance")->getUserMoneyDetail(session('user_id'),I('post.page'),I('post.search'));
                }
                foreach($finance as $key => $value) {
                    $revealParagrap = formatRevealParagraphTime($value['pay_time']);
                    $finance[$key]['year'] =   $revealParagrap['year'];
                    $finance[$key]['day'] =   $revealParagrap['day'];
                    $finance[$key]['time'] =   $revealParagrap['time'];
                    $finance[$key]['icon'] =   IMG_URL."tool/".$value['icon'].'-icon.png';
                }
                $this->ajaxReturn($finance);
            }
        }
    }
    public function companyAction()
    {
        $title = "公司资金";
        $this->assign('title', $title);
        if(session('user_type') != USER_TYPE_COMPANY_MANAGER) {
            $aj = D('ComAccountJurisdiction');
            //$aj->setObjectId(I('get.id'));
            $aj->setObjectId($_SESSION['wrk_company_id']);
            $aj->getHasCompanyLeader();
            $this->assign('has_leader',$aj->getStore('has_leader'));
            //if (!isset($_GET['id']) || !($_GET['id'] > 0)) {
            if (!isset($_SESSION['wrk_company_id']) || !($_SESSION['wrk_company_id'] > 0)) {
                $this->error('该公司不存在','/Index/user');
                die;
            }
            //获取可见的公司，判断是否包含session中的wrk_company_id,不包含则不可见
            $aj = D('ComAccountJurisdiction');
            $aj->getVisiblersCompanys();
            $visual_companys = $aj->getStore('visiblers');
            if(!in_array($_SESSION['wrk_company_id'],$visual_companys)){
                $this->assign("jumpUrl",'/Index/user');
                $this->assign("error",'您不是该公司的资金负责人或可见人');
                $this->assign("waitSecond",'3');
                $this->display("Public/error");
                //$this->error('您不是该公司的资金负责人或可见人','/Index/user');
                die;
            }
            //$company = D('SysBranch')->where(array('id' => I('get.id')))->find();
            $company = D('SysBranch')->where(array('id' => $_SESSION['wrk_company_id']))->find();
            if (!$company){
                $this->error('该公司不存在','/Index/user');
                die;
            }
            $this->assign('total_cash',sprintf("%.2f", $company['money']));
            $company['money'] = sprintf("%.2f", floatval($company['money']) - floatval($company['money_auditing'])); //显示扣除掉审核中余额
            $company['money_auditing'] = sprintf("%.2f",$company['money_auditing']);
            $user = D('SysUser')->where(array('id' => session('user_id')))->find();
            $this->assign('company',$company);
            if($user['deposit'] > 0) {
                $bank_name = M("sys_bank_ico")->where('id = ' . $user['deposit'])->getField('title');
                $this->assign('bank_name', $bank_name);
                $this->assign("is_bank", 1);
            }
            $this->assign('user',$user);
        }
        $this->display();
    }
    //银行卡绑定
    public function bank_bindAction()
    {
        if (IS_GET){
            $this->assign('title','银行卡绑定');
            $bank_table = M('sys_bank_ico');
            $banks = $bank_table->cache(true)->select();
            $this->assign('banks', $banks);
            $user = M('sys_user')->alias("user")->join("left join sys_bank_ico bank on bank.id=user.deposit")
                ->field("user.*,bank.title")->where(array('user.id' => $_SESSION['user_id']))->find();
            $this->assign('user', $user);
            $this->display();
        } else {
            $data = I('post.');
            if ($data['code'] == $_SESSION['regcode']) {
                $result = M('SysUser')->data($data)->where(array('id' => $_SESSION['user_id']))->save();
                if ($result){
                    die(json_encode(array("error" => "0", "msg" => "银行卡绑定成功!")));
                } else {
                    die(json_encode(array("error" => "1", "msg" => "银行卡绑定失败!")));
                }
            } else {
                die(json_encode(array("error" => "1", "msg" => "手机验证码错误!")));
            }
        }

    }
    /**
     * 获取资金-付款单业务详情
     */
    public function companyRechargeAction()
    {
        $title = "公司充值";
        $this->assign('title', $title);
        $aj = D('ComAccountJurisdiction');
        if (IS_GET) {
            if (!I('get.code')){
                $this->title ='充值';
                $aj->setObjectId(I('get.id'));
                $aj->getHasCompanyLeader();
                $has_leader = $aj->getStore('has_leader');
                if (!$has_leader){
                    $this->error('访问错误');
                    die;
                }
                $wx_config = getWxConfigData();
                $this->wxpay_open = $wx_config['wxpay_open'];
                $this->ofpay_open = getComStoreData('pay_status');
                $this->assign('company_id',I('get.id'));
                $this->display('middleware_recharge');
            } else {
                if (!I('get.code') || !$_SESSION['RECHARGE_MIDDLEWARE'][I('get.code')]){
                    $this->error('访问错误','/Money/middleware_recharge');
                    die;
                }
                $middleware = $_SESSION['RECHARGE_MIDDLEWARE'][I('get.code')];
                $aj->setObjectId($middleware['company_id']);
                $aj->getHasCompanyLeader();
                $has_leader = $aj->getStore('has_leader');
                if (!$has_leader){
                    $this->error('访问错误');
                    die;
                }
                $account['recharge_money'] = $middleware['money'];
                $account['price_type'] = $middleware['price_type'];
                $account['price_type_view'] = $middleware['price_type'] == 1 ? '线下付款' : '微信支付' ;
                $company = D('SysBranch')->field('money,name')->where(array('id' => $middleware['company_id']))->find();
                $account['account_type'] = self::COMPANY_RECHARGE_CODE;
                $account['account_name'] = $company['name'];
                $account['money'] = $company['money'];
                $this->assign('company_id',$middleware['company_id']);
                $this->assign('account',$account);
                $cskx_platform_message = get_cskx_platform_message();
                $this->assign('cskx_platform_message', $cskx_platform_message);
                $this->display('recharge');
            }
        } else {
            $aj->setObjectId(I('post.company_id'));
            $aj->getHasCompanyLeader();
            $has_leader = $aj->getStore('has_leader');
            if (!$has_leader){
                $this->ajaxReturn(['error'=>1,'message'=>'您不是负责人,无操作权限']);
                die;
            }
            if (!(I('post.money') > 0)) {
                $this->ajaxReturn(['error'=>1,'message'=>'请输入大于0的充值金额']);
            }
            $_SESSION['RECHARGE_MIDDLEWARE'] = [];
            $sha1 = strtoupper(sha1('REG_'.time()));
            $data = [
                'money' => I('post.money'),
                'price_type' => I('post.price_type'),
                'company_id' => I('post.company_id'),
            ];

            $_SESSION['RECHARGE_MIDDLEWARE'][$sha1] = $data;
            $this->ajaxReturn(['error'=>0,'code'=>$sha1]);
        }
    }
    /**
     * 获取资金-付款单业务详情
    */
    public function rechargeAction()
    {
        $title = "充值";
        $this->assign('title', $title);
        if (IS_GET) {
            if (!I('get.code') || !$_SESSION['RECHARGE_MIDDLEWARE'][I('get.code')]){
                $this->error('访问错误','/Money/middleware_recharge');
                die;
            }
            $middleware = $_SESSION['RECHARGE_MIDDLEWARE'][I('get.code')];
            $account['recharge_money'] = $middleware['money'];
            $account['price_type'] = $middleware['price_type'];
            $account['price_type_view'] = $middleware['price_type'] == 1 ? '线下付款' : '微信支付' ;
            $user = D('SysUser')->field('user_money')->where(array('id' => session('user_id')))->find();
            $account['account_type'] = self::USER_RECHARGE_CODE;
            $account['money'] = $user['user_money'];
            $account['account_name'] = '个人账户';
            $this->assign('account',$account);
            $cskx_platform_message = get_cskx_platform_message();
            $this->assign('cskx_platform_message', $cskx_platform_message);
            $this->display();
        } else {
            $money = I('post.money', '', 'strip_tags');
            $price_type = I('post.price_type', '', 'strip_tags');
            $pic = I('post.pic', '', 'strip_tags');
            $account_type = I('post.account_type', '', 'strip_tags');
            $payment = M('com_recharge');
            if($account_type == self::USER_RECHARGE_CODE){
                $orderid = getOrderNo("UIZ_");
                $money_type	=	FIN_UIZ_RECHARGE;
                $user = M('SysUser') ->field('user_money,user_money_auditing')->where('id = '.session('user_id'))->find();
                $customer_id = session('user_id');
                $customer_type = "user";
            }else{
                $orderid = getOrderNo("CIZ_");
                $money_type	=	FIN_CIZ_RECHARGE;
                $payment->company_id = I('post.company_id');
                $company = M('SysBranch') ->field('money,money_auditing')->where('id = '.$payment->company_id)->find();
                $customer_id = $payment->company_id;
                $customer_type = "company";
            }
            //获取信息 0为微信支付 1为线下转账
            if ($price_type == 0) {
                $pay_name = $account_type == self::USER_RECHARGE_CODE? "个人账户充值(微信业务充值)" :  "公司账户充值(微信业务充值)";
                $source = FIN_PAY_WEIXIN;
            } else {
                $pay_name = $account_type == self::USER_RECHARGE_CODE? "个人账户充值(线下转账)" :  "公司账户充值(线下转账)";
                $source = FIN_PAY_OFFLINE;
//                $payment->pic = $pic;
                $payment->attach_group =I('post.attach_group');
            }
            $payment->user_id = $_SESSION['user_id'];
            $payment->creator_id = $_SESSION['user_id'];
            $payment->branch_id =  getBrowseBranchId();
            $payment->pay_status = 0;
            $payment->order_sn = $orderid;
            $payment->account = $money;
            $payment->mobile = $_SESSION['mobile'];
            $payment->ctime = begtime();
            $payment->pay_name = $pay_name;
            $payment->money_type = $money_type;
            $payment->source = $source;
            $payment->add();
            if($price_type != 0){//不等于微信支付则发送通知（微信支付的通知在wechatpaycontroller）
                $send_result = D("ESAdmin/ComRecharge")->sendCapitalMsgToBranch('comrecharge',$customer_id,$customer_type,$money);
            }
            echo json_encode(array("orderid" => $orderid, "type" => $price_type));
        }
    }
    public function transferAction()
    {
        $title = "转账";
        $this->assign('title', $title);
        $users_model   =   D("SysUser");
        $user = $users_model->alias('u')->join('sys_bank_ico b on b.id=u.deposit', 'left')->where(array('u.id' => $_SESSION['user_id']))->find();
        if (IS_GET) {
            $user['real_name'] = str_pad('', mb_strlen($user['real_name'], 'utf-8') - 1, "*", STR_PAD_LEFT) . mb_substr($user['real_name'], (mb_strlen($user['real_name'], 'utf-8')) - 1, mb_strlen($user['real_name'], 'utf-8'), 'utf-8');
            $user['user_money'] = sprintf("%.2f", $user['user_money'] - $user['user_money_auditing']); //账户余额显示扣除掉审核中余额
            $user['total_money'] = sprintf("%.2f",$user['user_money'] + $user['user_money_auditing']);
            $this->assign('user', $user);
            $this->handlerAssignCompany();
            $this->display();
        } else {
            $postdata = I('post.');
            if ($postdata['money'] > $user['user_money']) {
                echo json_encode(array("error" => "1", "msg" => "个人账户余额不足！"));
                exit();
            }
            if (empty($postdata['company_id']) ) {
                echo json_encode(array("error" => "1", "msg" => "请选择公司！"));
                exit();
            }
            $company = D('SysBranch')->field('money,name,money_auditing')->where('id = '. $postdata['company_id'])->find();
            if (!$company) {
                echo json_encode(array("error" => "1", "msg" => "该公司不存在！"));
                exit();
            }
//            var_dump($postdata);die;
            //提现记录
            $data = $postdata;
            $data['user_id'] = $_SESSION['user_id'];
            $data['order_sn'] = getOrderNo("UWC_");
            $data['create_time'] = time();
            $data['deposit'] = $company['name'];
            $data['company_id'] = $postdata['company_id'];
            $data['branch_id'] = getBrowseBranchId();
            $data['real_money'] = $postdata['money'];
            $data['handle_type'] = '个人转账至公司';
            $data['fee'] = 0; //
            $data["cardholder"] = $user['real_name'];
            $data['status'] = 1;
            $data["withdrawal_type"] = FIN_CIZ_WITHDRAW_FLOW_TO_COMPANY; //业务提现
            $withdrawals_table = M('com_withdrawals');
            try {
                $withdrawals_table -> startTrans();
                $last_id = $withdrawals_table->data($data)->add();
                //用户资金减少
                $money_data['user_money'] =  $user['user_money'] - $postdata['money'];
                $users_model->data($money_data)->where(array('id' => $_SESSION['user_id']))->save();
                //公司资金增加
                $company_money_data['money'] = $postdata['money'] + $company['money'];
                D('SysBranch')->data($company_money_data)->where(array('id' => $postdata['company_id']))->save();
                $finance_table = M('ComFinance');
                //存放至Finance
                $financein['fina_type'] = FIN_CIZ_WITHDRAW_FLOW_TO_COMPANY; //从充值类型带过来
                $financein['fina_cash'] = $postdata['money'];
                $financein['fina_time'] = time();
                $financein['user_id'] = $_SESSION['user_id'];;
                $financein['branch_id'] = $data['branch_id'];
                $financein['company_id'] = $data['company_id'];
                $financein['order_sn'] =  $data['order_sn'];
                $financein['third_fee'] = 0;
                $financein['platform_fee'] = 0;
                $financein['platform_fee'] = 0;
                $financein['title'] = '公司账户充值(转账)';
                $finance_table->data($financein)->add();
                $withdrawals_table->commit();
                //发送转账信息
                $send_data["transaction_type"] = '账户转账(成功)';
                $send_data["account"] = $postdata['money'];
                $send_data["pay_time_view"] = date('Y-m-d H:i:s',time());
                $send_data["account_balance"] = $money_data['user_money'];
                $send_data["openid"] = $user['openid'];
                $send_data['url'] =  WEB_ROOT . '/Money.html';
                D('ComFinance')->sendWxTemplate(TCT_USER_INCOME_COMPLETE_NOTICE,$send_data);
                $send_data["transaction_type"] = '账户转账(已到账)';
                $send_data["account"] = $postdata['money'];
                $send_data["pay_time_view"] = date('Y-m-d H:i:s',time());
                $send_data["account_balance"] = $company_money_data['money'];
                $send_data['url'] =  WEB_ROOT . '/Money/company/id/'.$data['company_id'].'.html';
                $jurisdiction =  D('ESAdmin/ComAccountJurisdiction');
                $jurisdiction->setObjectId($postdata['company_id']);
                $jurisdiction->setObjectType(1);
                $jurisdiction->setObjectVarious([CAJ_BRANCH_CUSTOMER_CAPITAL]);
                $jurisdiction->getAccountNoticeSendUsers('company');
                $send_data['openid'] = $jurisdiction->getStore('company_visiblers');
                D('ComFinance')->sendWxTemplate(TCT_COMPANY_INCOME_COMPLETE_NOTICE,$send_data);
                die(json_encode(array("error" => "0", "msg" => "已转账到公司账号", "url" => "/Money/withdrawals_success/id/$last_id.html")));
            } catch (\Exception $e) {
                $withdrawals_table->rollback();
                die(json_encode(array("error" => "1", "msg" => "提交错误，请联系客服人员。", "url" => "")));
            }
        }
    }
    /*
     * api 返回公司总金额
     */
    public function company_cashAction(){
        if (IS_POST){
            $id = I('post.id');
            $company = M('SysBranch')->field('id,name,money,money_auditing')->where('id = '.$id)->find();
            $cash = sprintf("%.2f", $company['money']);
            $this->ajaxReturn(['cash'=>$cash,'text'=>$company['name'],'code'=>0]);
        }
    }
    /**
     * 提现-付款单业务详情
     */
    public function companyWithdrawalsAction()
    {
        $aj = D('ComAccountJurisdiction');
        $aj->setObjectId(I('get.id'));
        $aj->getHasCompanyLeader();
        $has_leader = $aj->getStore('has_leader');
        if (!$has_leader){
            $this->error('访问错误');
            die;
        }
        $title = "提现";
        $this->assign('title', $title);
        $users_model   =   D("SysUser");
        $user = $users_model->alias('u')->join('sys_bank_ico b on b.id=u.deposit', 'left')->where(array('u.id' => $_SESSION['user_id']))->find();
        if (IS_GET) {
            $user['real_name'] = str_pad('', mb_strlen($user['real_name'], 'utf-8') - 1, "*", STR_PAD_LEFT) . mb_substr($user['real_name'], (mb_strlen($user['real_name'], 'utf-8')) - 1, mb_strlen($user['real_name'], 'utf-8'), 'utf-8');
            $user['bank_account'] = str_pad(substr($user['bank_account'], (strlen($user['bank_account']) - 4)), strlen($user['bank_account']), "*", STR_PAD_LEFT);
            $company = D('SysBranch')->field('money,money_auditing,name,id')->where(array('id' => I('get.id')))->find();;
            $account['account_name'] = $company['name'];
            $account['money'] = sprintf("%.2f", $company['money'] - $company['money_auditing']); //账户余额显示扣除掉审核中余额
            $account['total_money'] = $company['money'];
            $account['user_money_auditing'] = $company['money_auditing'];
            $this->assign("withdrawal_type", self::WITHDRAWALS_TYPE_COMPANY);
            $this->assign('has_bank',$user['bank_account'] ? 1 : 0);
            $this->assign('user', $user);
            $this->assign('account', $account);
            $this->assign('company_id', $company['id']);
            $this->display('withdrawals');
        }
    }
    /**
     * 提现-付款单业务详情
     */
    public function withdrawalsAction()
    {
        $title = "提现";
        $this->assign('title', $title);
        $users_model   =   D("SysUser");
        $user = $users_model->alias('u')->join('sys_bank_ico b on b.id=u.deposit', 'left')->where(array('u.id' => $_SESSION['user_id']))->find();

        if (IS_GET) {
            $this->assign('has_bank',$user['bank_account'] ? 1 : 0);
            $user['real_name'] = str_pad('', mb_strlen($user['real_name'], 'utf-8') - 1, "*", STR_PAD_LEFT) . mb_substr($user['real_name'], (mb_strlen($user['real_name'], 'utf-8')) - 1, mb_strlen($user['real_name'], 'utf-8'), 'utf-8');
            $user['bank_account'] = str_pad(substr($user['bank_account'], (strlen($user['bank_account']) - 4)), strlen($user['bank_account']), "*", STR_PAD_LEFT);
            $account['account_name'] = '个人账户';
            $account['money'] = sprintf("%.2f", $user['user_money'] - $user['user_money_auditing']); //账户余额显示扣除掉审核中余额
            $account['total_money'] = sprintf("%.2f",$user['user_money']);
            $account['user_money_auditing'] = $user['user_money_auditing'];
            $this->assign("withdrawal_type", self::WITHDRAWALS_TYPE_USER);
            $this->assign('user', $user);
            $this->assign('account', $account);
            $this->display();
        } else {
            $postdata = I('post.');
            $withdrawal_type = I("post.withdrawal_type"); //1个人提现 2公司提现
            $withdrawal_fee = I("post.fee");
            if (empty($user['deposit']) || empty($user['bank_account'])) {
                echo json_encode(array("error" => "1", "msg" => "基本账户信息未完善，请完善后再操作！", "url" => ""));
                exit();
            }
            if ($postdata['money'] < 100) {
                echo json_encode(array("error" => "1", "msg" => "提现金额不能低于100！", "url" => ""));
                exit();
            }
            if ($withdrawal_type == self::WITHDRAWALS_TYPE_USER){ //个人
                $customer_id = $_SESSION['user_id'];
                $customer_type = "user";
                if ($postdata['money'] > ($user['user_money'] - $user['user_money_auditing'])) {
                    echo json_encode(array("error" => "1", "msg" => "提现金额不能大于可提金额！", "url" => ""));
                    exit();
                }
            }elseif($withdrawal_type == self::WITHDRAWALS_TYPE_COMPANY){
                $customer_id = $postdata['company_id'];
                $customer_type = "company";
                $company = D('SysBranch')->field('money_auditing,money')->where('id = '. $postdata['company_id'])->find();
                if ($postdata['money'] > ($company['money'] - $company['money_auditing'])) {
                    echo json_encode(array("error" => "1", "msg" => "提现金额不能大于可提金额！", "url" => ""));
                    exit();
                }
            }
//          var_dump($postdata);die;
            //提现记录
            $data = $postdata;
            $data['user_id'] = $_SESSION['user_id'];
            $bill_prefix = ($withdrawal_type==self::WITHDRAWALS_TYPE_USER)?"UIW_":"CIW_";
            $data['order_sn'] = getOrderNo($bill_prefix);
            $data['create_time'] = time();
            $data['deposit'] = $user['title'];
            $data['handle_type'] = '线下转账';
            $data['bank_account'] =  $user['bank_account'];
            $data['branch_id'] = getBrowseBranchId();
            $data['real_money'] = $postdata['money'] - $withdrawal_fee;
            $data['fee'] = $withdrawal_fee;
            $data["cardholder"] = $user['real_name'];
            $data['status'] =  0;
            $data["withdrawal_type"] = ($withdrawal_type==self::WITHDRAWALS_TYPE_USER)?FIN_UIZ_WITHDRAW : FIN_CIZ_WITHDRAW; //业务提现
            $data['bank_address'] = $user['bank_address'];
            $withdrawals_table = M('com_withdrawals');
            $withdrawals_table->startTrans();
            try {
                $last_id = $withdrawals_table->data($data)->add();
                if ($withdrawal_type ==self::WITHDRAWALS_TYPE_USER){ //个人
                    $money_data['user_money_auditing'] = $postdata['money'] + $user['user_money_auditing'];
                    $users_model->data($money_data)->where(array('id' => $_SESSION['user_id']))->save();
                }elseif ($withdrawal_type ==self::WITHDRAWALS_TYPE_COMPANY){
                    $company = D('SysBranch')->field('money_auditing')->where('id = '. $postdata['company_id'])->find();
                    $money_data['money_auditing'] = $company['money_auditing'] + $postdata['money'];
                    D('SysBranch')->data($money_data)->where(array('id' => $postdata['company_id']))->save();
                }
                $withdrawals_table->commit();
                $send_result = D("ESAdmin/ComRecharge")->sendCapitalMsgToBranch('ComWithdrawal',$customer_id,$customer_type,$data['real_money']);
                die(json_encode(array("error" => "0", "msg" => "提现到账时间，以银行实际到账时间为准，一般为T+3个工作日内，请注意查询银行账户。", "url" => "/Money/withdrawals_success/id/$last_id.html")));
            } catch (\Exception $e) {
                $withdrawals_table->rollback();
                die(json_encode(array("error" => "1", "msg" => "提交错误，请联系客服人员。", "url" => "")));
            }
        }
    }
    public function handlerAssignCompany()
    {
        //判断是否有绑定公司
        if ( session('user_type') != USER_TYPE_COMPANY_MANAGER ){
            $condition['branch.type']    = ORG_COMPANY;
            $condition['user_branch.user_id'] = session('user_id');
            $company_data =  D('sysBranch')
                                        ->alias('branch')
                                        ->field('name,id')
                                        ->join('sys_user_branch as user_branch on user_branch.branch_id = branch.id')
                                        ->where($condition)->select();
            $this->assign('companys',$company_data);
        }
    }
    public function handlerHasCompany()
    {
        //判断是否有绑定公司
        if ( session('user_type') != USER_TYPE_COMPANY_MANAGER ){
            $condition['user_branch.user_id'] = session('user_id');
            $condition['branch.type'] = 1;
            $company_count =
                            D('sysBranch')
                                ->setDacFilter('branch')
                                ->join('sys_user_branch as user_branch on user_branch.branch_id = branch.id')
                                ->where($condition)->count();
            $this->assign('has_company',$company_count > 0 ? 1 : 0);
        }
    }
    public function withdrawals_successAction()
    {
        $withdrawals_table = M('ComWithdrawals');
        $withdrawals = $withdrawals_table->where(array('id' => I('get.id')))->find();
        if ( $withdrawals['withdrawal_type'] == FIN_CIZ_WITHDRAW_FLOW_TO_COMPANY ){
            $title = "转账申请成功";
            $this->assign('type','transfer');
            $this->assign('title', $title);
        } else {
            $title = "提现申请成功";
            $this->assign('type','withdrawal');
            $this->assign('title', $title);
        }
        $this->assign('withdrawals', $withdrawals);
        $this->display();
    }
    /**
     * 获取资金-付款单业务详情
     * @param type $order_no
     */
    public function getOrderDetailAction($order_no){
        $condition["o.order_sn"] = $order_no;
        $condition["o.branch_id"]= getBrowseBranchId();
        $task_data = M("ComOrder")
                        ->alias("o")
                        ->field("o.product_title as title,o.real_cash,FROM_UNIXTIME(o.on_time) as on_time,o.contacts")
                        ->where($condition)
                        ->find();
        $this->ajaxReturn($task_data);
    }
    //中间件
    public function middleware_rechargeAction()
    {
        if(IS_GET){
            $this->title ='充值';
            $wx_config = getWxConfigData();
            $this->wxpay_open = $wx_config['wxpay_open'];
            $this->ofpay_open = getComStoreData('pay_status');
            //NEW Jan 2 ,2018 Assign platform_message
            $cskx_platform_message = get_cskx_platform_message();
            $this->assign('cskx_platform_message', $cskx_platform_message);
            $this->display();
        } else {
            $_SESSION['RECHARGE_MIDDLEWARE'] = [];
            $sha1 = strtoupper(sha1('REG_'.time()));
            if (!(I('post.money') > 0)) {
                $this->ajaxReturn(['error'=>1,'message'=>'请输入大于0的充值金额']);
            }
            $data = [
                'money' => I('post.money'),
                'price_type' => I('post.price_type')
            ];
            $_SESSION['RECHARGE_MIDDLEWARE'][$sha1] = $data;
            $this->ajaxReturn(['error'=>0,'code'=>$sha1]);
        }
    }
    /**
     * 获取提现-提现详情
     * @param type $order_no
     */
    public function getWithdrawalsDetailAction($order_no){
        $sql = "select fina_cash as amount,FROM_UNIXTIME(fina_time) as pay_time,platform_fee,(fina_cash-platform_fee) as actual_fee,if(fina_type = ".FIN_CIZ_WITHDRAW_FLOW_TO_COMPANY.",CONCAT('已转账'),CONCAT('已提现')) as state from com_finance"
            . " where order_sn='$order_no'"
            . " union "
            . "select (real_money+fee) as amount,FROM_UNIXTIME(create_time) as pay_time,fee as platform_fee,real_money as actual_fee,"
            . "( case when status = 0 then CONCAT('提现审核中') when status = 2 then CONCAT('提现失败') end) as state from com_withdrawals"
            . " where order_sn='$order_no' and status<>1"
            . " order by pay_time desc limit 1";
        $list = M()->query($sql);
        $this->ajaxReturn($list[0]);
    }
    public function appendAction(){
        $attach_group = I("post.attach_group");
        if (empty($attach_group)){
            $attach_group = genUniqidKey();
        }
        if (count($_FILES) > 10){
            $this->ajaxReturn(buildMessage('上传文件个数不能超过10个',1));
        }
        $config = C("Storage");
        $upload = new \Think\Upload($config);
        $file_infos = array();
        $size = 0;
        //$key为文件类型,和前端的一致,格式： word-file-0、excel-file-1...
        foreach ($_FILES as $key=>$file){
            $info = $upload->uploadOne($file);
            if ($info) {
                $file_keys = explode("-", $key);
                $file_info['url'] = $info['url'];
                $file_info["name"] = $file["name"];
                $file_info["type"] = $file_keys[0];
                $size+= $info['size'];
                $file_infos[] = $file_info;
            } else {
                $this->ajaxReturn(buildMessage('上传文件失败',1));
            }
        }
        $content = I("post.content");
        $data["branch_id"] =  getBrowseBranchId();
        $data["group"] =  $attach_group;
        $data["content"] = $content;
        $data["create_time"] = time();
        $data["size"] = $size / 1000;
        $data["creater_id"] = session('user_id');
        $data["images"] = json_encode($file_infos);
        $data['url'] = $info['url'];
        if ($lastId = M("ComAttachment")->add($data)) {
            $data["id"] = $lastId;
            echo json_encode(array('code'=>0,'message'=>$data));
            exit();
        }else{
            echo json_encode(array('code'=>1,'message'=>'上传失败!'));
            exit();
        }
    }
    public function removeAttachAction(){
        if (IS_POST) {
            $condition['id'] = I('post.id');
            M("ComAttachment")->where($condition)->delete();
        }
    }
}