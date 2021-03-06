<?php

namespace ESAdmin\Controller;


use Common\Lib\Controller\DataController;

class  ComRechargeController extends DataController {

    protected function _before_list(&$list)
    {
        parent::_before_list($list); // TODO: Change the autogenerated stub
        foreach($list as $key => $val){
            $list[$key]['capital_account'] = $val['money_type'] == FIN_CIZ_RECHARGE ? $val['company_name'] : $val['user_name'];
            $list[$key]['actual_money'] = $val['pay_status'] == 1 ? sprintf("%.2f", $val['account'] - $val['third_fee']):'';
            $list[$key]['object_type'] = $val['money_type'] == FIN_CIZ_RECHARGE ? 'c':'u';
            $account_type = $val['money_type'] == FIN_CIZ_RECHARGE ? 1 : 2;
            $object_id = $val['money_type'] == FIN_CIZ_RECHARGE ? $val['company_id'] : $val['user_id'];
            $account_jurisdiction = A('ComCapitalDetails')->getAccountSystemManage($object_id,$account_type,[CAJ_BRANCH_RECHARGE],[CAJ_BRANCH_RECHARGE]);
            $account = A('ComCapitalDetails')->handlerAccountSystem($account_jurisdiction);
            $list[$key]['recharge_leader_view'] = $account['recharge_leader_view'];
            $list[$key]['attach_group'] = empty($list[$key]['attach_group']) ? '' : $list[$key]['attach_group'];
        }
    }
    protected function _before_display_dataview(&$data)
    {

        parent::_before_display_dataview($data); // TODO: Change the autogenerated stub
        if (isset($data['id']) && $data['id'] > 0) {
            $data['capital_account'] = $data['money_type'] == FIN_CIZ_RECHARGE ? $data['company_name'] : $data['user_name'];
            $data['capital_account_id'] = $data['money_type'] == FIN_CIZ_RECHARGE ? 'c:'.$data['company_id'] : 'u:'.$data['user_id'];
            $this->assign('is_leader',$this->getUserHasAccountLeader($data));
        }
        $data['pay_time'] = time();
        $_filter['status'] = 1;
        $receivables_account = D('WrkReceivablesAccount')->setDacFilter("a")->field("a.id,a.name")->where($_filter)->select();
        $this->assign('receivables_account',$receivables_account);
        $data['attach_group'] = empty($data['attach_group']) ? genUniqidKey() : $data['attach_group'];

    }

    protected function _parsefilter(&$filter)
    {
        parent::_parsefilter($filter); // TODO: Change the autogenerated stub
        $filter['a.money_type'] = array('in',[FIN_CIZ_RECHARGE,FIN_UIZ_RECHARGE]);
        if (!is_null($filter['a.capital_account_id'])){
            $capital_account_id = explode(':',$filter['a.capital_account_id']);
            if ($capital_account_id[0] == 'c') {
                $filter['a.company_id'] =  $capital_account_id[1];
                $filter['a.money_type'] = FIN_CIZ_RECHARGE;
            } else {
                $filter['a.user_id'] =  $capital_account_id[1];
                $filter['a.money_type'] = FIN_UIZ_RECHARGE;
            }
            unset($filter['a.capital_account_id']);
        }
        if(I("post.q-pay_status") == 0 && I("post.q-pay_status") != ""){
            $filter['a.source'] = array("neq",FIN_PAY_WEIXIN);
        }
        if(I("post.q-pay_status") == 1){
            unset($filter['a.pay_status']);
            $filter['_string'] = "( a.pay_status = 1 or ( a.source = 0 and a.pay_status in (0,1)) )";
        }
        $aj = D('ComAccountJurisdiction') ;
        $aj-> setObjectVarious([CAJ_BRANCH_RECHARGE]);
        $aj-> setOptions('jurisdiction','visiblers');
        $aj-> getLoginUserJurisdiction();
        $jurisdiction = $aj->getStore('jurisdiction');
        if ($jurisdiction === false) {
            $filter['a.id'] = 0;
        } elseif(is_array($jurisdiction)) {
            if (!empty($jurisdiction['users'][CAJ_BRANCH_RECHARGE]) && !empty($jurisdiction['companys'][CAJ_BRANCH_RECHARGE])){
                $filter['_string'] = empty($filter['_string']) ? "":$filter['_string']." and ";
                $filter['_string'] .= '( a.money_type='.FIN_CIZ_RECHARGE.' and a.company_id in ('.implode(',',$jurisdiction['companys'][CAJ_BRANCH_RECHARGE]).') )';
                $filter['_string'] .= ' or (a.money_type='.FIN_UIZ_RECHARGE.' and a.user_id in ('.implode(',',$jurisdiction['users'][CAJ_BRANCH_RECHARGE]).') )';
            } else {
                if (empty($jurisdiction['users'][CAJ_BRANCH_RECHARGE])) {
                    $filter['a.money_type'] = array(array('neq',FIN_UIZ_RECHARGE),array('in',[FIN_CIZ_RECHARGE,FIN_UIZ_RECHARGE]),'and');
                } else {
                    $where['a.money_type']  = array('eq',FIN_UIZ_RECHARGE);
                    $where['a.user_id']  = array('in',$jurisdiction['users'][CAJ_BRANCH_RECHARGE]);
                    $where['_logic'] = 'and';
                }
                if (empty($jurisdiction['companys'][CAJ_BRANCH_RECHARGE])) {
                    $filter['a.money_type'] = array(array('neq',FIN_CIZ_RECHARGE),array('in',[FIN_CIZ_RECHARGE,FIN_UIZ_RECHARGE]),'and');
                } else {
                    $where['a.money_type']  = array('eq',FIN_CIZ_RECHARGE);
                    $where['a.company_id']  = array('in',$jurisdiction['companys'][CAJ_BRANCH_RECHARGE]);
                    $where['_logic'] = 'and';
                }
            }
            if ($where) {
                $filter['_complex'] = $where;
            }
        }
    }

    function capitalAccountListAction()
    {
        $str = I('q');
        $various = I('get.v');
        $condition_company = [];
        $condition_user = [];
        if (!empty($str)) {
            $where['name']  = array('like', '%'.$str.'%');
            $where['querykey']  = array('like', '%'.$str.'%');
            $where['_logic'] = 'or';
            $condition_company['_complex'] = $where;
            $condition_user['_complex'] = $where;
        }
        $condition_user['branch_id'] = $this->_user_session->currBranchId;
        $condition_user['user_type']= array('neq',USER_TYPE_COMPANY_MANAGER);
        $condition_user['is_valid']=1;
        $condition_company['parent_id'] = $this->_user_session->currBranchId;
        $condition_company['type']=1;
        $condition_company['is_valid']=1;
        if ($various == 'w' || $various == 'r') {
            $aj = D('ComAccountJurisdiction') ;
            $various = $various == 'w' ? CAJ_BRANCH_WITHDRAWAL : CAJ_BRANCH_RECHARGE;
            $aj-> setObjectVarious([$various]);
            $aj-> setOptions('jurisdiction','leader');
            $aj-> getLoginUserJurisdiction();
            $jurisdiction = $aj->getStore('jurisdiction');
            if ($jurisdiction === false) {
                $condition_user['id'] = 0;
                $condition_company['id'] = 0;
            } elseif(is_array($jurisdiction)) {
                if (!empty($jurisdiction['users'][$various]) || !empty($jurisdiction['companys'][$various])){
                    if (empty($jurisdiction['users'][$various])) {
                        $condition_user['id'] = 0;
                    } else {
                        $condition_user['id']  = array('in',$jurisdiction['users'][$various]);
                    }
                    if (empty($jurisdiction['companys'][$various])) {
                        $condition_company['id'] = 0;
                    } else {
                        $condition_company['id']  = array('in',$jurisdiction['companys'][$various]);
                    }
                }
            }
        }
        $company_sql = M('SysBranch')
            ->where($condition_company)
            ->field("CONCAT('c:',id) as id,name,querykey,service_id")->fetchSql(true)->select();
        $user_sql = M('SysUser')
            ->where($condition_user)
            ->field("CONCAT('u:',id) as id,name,querykey,service_id")->fetchSql(true)->select();
        $sql = '( '.$company_sql . ') union ('.$user_sql.')';
        $list = M()->query($sql);
        foreach ($list as $k=>$v){
            if($v['service_id'] != ""){
                $result = M("SysUser")->where("id = ".$v['service_id'])->field("name,id")->find();
                $list[$k]['user_name'] = $result['name'];
                $list[$k]['user_id'] = $result['id'];
            }
        }
        $this->ajaxReturn($list);
    }
    public function rechargeAdoptAction()
    {
        if (IS_GET) {
            $id = I('get.id');
            //权限控制
            $payment = D(CONTROLLER_NAME)->field('company_id,user_id,money_type,account,pay_time')->where('id = '.$id)->find();
            if (!$this->getUserHasAccountLeader($payment)) {
                die("无此功能操作权限");
            }
            if ($payment['money_type'] == FIN_CIZ_RECHARGE){
                $account = M("SysBranch")->where('id = '.$payment['company_id'])->find();
                $balance_money  = sprintf("%.2f", $account['money'] - $account['money_auditing']);
            } else if ($payment['money_type'] == FIN_UIZ_RECHARGE) {
                $account = M("SysUser")->where('id = '.$payment['user_id'])->find();
                $balance_money  = sprintf("%.2f", $account['user_money'] - $account['user_money_auditing']);
            }
            $model['id'] = $id;
            $model['balance_money'] = $balance_money;
            $model['pay_time'] = date('Y-m-d',$payment['pay_time']);
            $model['account'] = sprintf("%.2f",$payment['account']);
            $this->assign('model',$model);
            $this->display();
        } else {
            $id = I('post.id');
            if ($id > 0) {
                $payment = D(CONTROLLER_NAME)->where('id = '.$id)->find();
                if (!$this->getUserHasAccountLeader($payment)) {
                    $this->responseJSON(buildMessage("无此功能操作权限！", 1));
                }
                if ($payment['pay_status'] != 0 && !empty($payment['audit_time'])) {
                    $this->ajaxReturn(buildMessage( "已经审核过，不能再次审核！",1));
                    die;
                }
                if ($payment["source"] != FIN_PAY_OFFLINE){ //线下转账
                    $this->ajaxReturn(buildMessage( "非线下转账充值，无需审核!",1));
                    die;
                }
                if (empty(I('post.origin'))){
                    $this->ajaxReturn(buildMessage( "请选择收款账户!",1));
                    die;
                }

                $err_msg = D(CONTROLLER_NAME)->rechargeAdopt($payment,$this->_user_session->userId);
                $this->ajaxReturn($err_msg);
            } else {
                if (!isset($_POST['account'])){
                    $this->ajaxReturn(array('code' =>1 ,'message' =>"系统出错，请联系管理员"));
                } else {
                    $capital_account_id = explode(':',I('post.capital_account_id'));
                    if ($capital_account_id[0] == 'c') {
                        $payment['company_id'] = $capital_account_id[1];
                        $payment['money_type'] = FIN_CIZ_RECHARGE;
                    } else {
                        $payment['user_id'] = $capital_account_id[1];
                        $payment['money_type'] = FIN_UIZ_RECHARGE;
                    }
                    if (!$this->getUserHasAccountLeader($payment)) {
                        $this->responseJSON(buildMessage("无此功能操作权限！", 1));
                    }
                    $err_msg = D(CONTROLLER_NAME)->rechargeAdopt(null,$this->_user_session->userId);
                    $this->ajaxReturn($err_msg);
                }
            }

        }
    }
    public function rechargeRefuseAction()
    {
        if (IS_GET) {
            $model['id'] = I('get.id');
            $payment = D(CONTROLLER_NAME)->where('id = '.$model['id'] )->find();
            if (!$this->getUserHasAccountLeader($payment)) {
                die("无此功能操作权限");
            }
            $this->assign('model',$payment);
            $this->display();
        } else {
            $id = I('post.id');
            $payment = D(CONTROLLER_NAME)->where('id = '.$id)->find();
            if (!$this->getUserHasAccountLeader($payment)) {
                $this->responseJSON(buildMessage("无此功能操作权限！", 1));
            }
            if ($payment['pay_status'] != 0 && !empty($payment['audit_time'])) {
                $this->responseJSON(buildMessage( "已经审核过，不能再次审核！",1));
                die;
            }
            if ($payment["source"] != FIN_PAY_OFFLINE){ //线下转账
                $this->responseJSON(buildMessage( "非线下转账充值，无需审核!",1));
                die;
            }
            $recharge["pay_status"] = 2;
            $recharge["attach_group"] = I('post.attach_group');
            $recharge["audit_time"] = time();
            $success = D(CONTROLLER_NAME)->where("id=$id")->save($recharge);
            if ($success){
                $condition["a.id"] = $id;
                $record = D(CONTROLLER_NAME)->setDacFilter("a")->relation(true)->field("a.*")->where($condition)->find();
                $record['capital_account'] = $record['money_type'] == FIN_CIZ_RECHARGE ? $record['company_name'] : $record['user_name'];
                $record['actual_money'] = $record['pay_status'] == 1 ? sprintf("%.2f", $record['account'] - $record['third_fee']):'';
                $record['object_type'] = $record['money_type'] == FIN_CIZ_RECHARGE ? 'c':'u';
                $this->responseJSON(array("code"=>0, "message"=>"审核成功！","row"=>$record));
            }else{
                $this->responseJSON(array("code"=>1, "message"=>"审核失败"));
            }
        }
    }
    protected function getUserHasAccountLeader($data)
    {
       $object_id = $data['money_type'] == FIN_CIZ_RECHARGE ? $data['company_id'] : $data['user_id'];
       $jurisdiction =  D('ComAccountJurisdiction');
       $jurisdiction->setObjectType($data['money_type']);
       $jurisdiction->setObjectId($object_id);
       $jurisdiction->setObjectVarious([CAJ_BRANCH_RECHARGE]);
       $jurisdiction->handlerHasAccountLeader();
       return $jurisdiction->getStore('has_leader');
    }
    public function setAttachGroupAction()
    {
        if (IS_POST) {
            $condition['id'] = I('post.id');
            $save['attach_group'] = I('post.attach_group');
            D(CONTROLLER_NAME)->where($condition)->data($save)->save();
        }
    }
    public function rechargeNoticeAction()
    {
        if (IS_POST) {
            $notice = I('post.notice');
            $id = I('post.id');
            $currency_tip = '';
            $branch_currency_tip = '';
            $recharge = D(CONTROLLER_NAME) ->where('id = '.$id)->find();
            $recharge['account'] = sprintf('%.2f',$recharge['account']);
            $recharge['pay_time_view'] = date('Y-m-d H:i:s',$recharge['audit_time']);
            //获取可见人
            if ($recharge['money_type'] == FIN_CIZ_RECHARGE) {
                $account = M('SysBranch') ->field('money,money_auditing')-> where(['id' => $recharge['company_id']]) ->find();
                $recharge['account_balance'] = sprintf('%.2f',$account['money'] - $account['money_auditing']);
                $branch_visiblers = $recharge;
                $branch_visiblers['url'] = str_replace('shop','shop'.$this->_user_session->currBranchId,SHOP_ROOT).'/ComBranchCapital/capitalDetails/id/c:'.$recharge['company_id'].'.html';
                $jurisdiction =  D('ComAccountJurisdiction');
                $jurisdiction->setObjectId($recharge['company_id']);
                $jurisdiction->setObjectType(1);
                $jurisdiction->setObjectVarious([CAJ_BRANCH_CUSTOMER_CAPITAL]);
                $jurisdiction->getAccountNoticeSendUsers('user');
                $recharge['openid'] = $jurisdiction->getStore('user_visiblers');
                $jurisdiction->setObjectVarious([CAJ_BRANCH_RECHARGE]);
                $jurisdiction->getAccountNoticeSendUsers('branch',false);
                $branch_visiblers['openid'] = $jurisdiction->getStore('branch_visiblers');
                if(!$branch_visiblers['openid']){
                    $branch_visiblers['openid'] = D("ComRecharge")->getCapitalLeaderOpenid("comrecharge");
                }
                //$branch_visiblers['account_balance'] = D("EShop/ComFinance")->getWxAccountMoney($recharge['receivable_id']);//获取收款账户的余额
                $recharge['url'] = str_replace('shop','shop'.$this->_user_session->currBranchId,SHOP_ROOT).'/money/company/id/'.$recharge['company_id'].'.html';
            } else {
                $account = M('SysUser') ->field('user_money_auditing,user_money,openid')-> where(['id' => $recharge['user_id']]) ->find();
                $recharge['account_balance'] = sprintf('%.2f',$account['user_money'] - $account['user_money_auditing']);
                $branch_visiblers = $recharge;
                $branch_visiblers['url'] = str_replace('shop','shop'.$this->_user_session->currBranchId,SHOP_ROOT).'/ComBranchCapital/capitalDetails/id/u:'.$recharge['user_id'].'.html';;
                $jurisdiction =  D('ComAccountJurisdiction');
                $jurisdiction->setObjectId($recharge['user_id']);
                $jurisdiction->setObjectType(2);
                $jurisdiction->setObjectVarious([CAJ_BRANCH_RECHARGE]);
                $jurisdiction->getAccountNoticeSendUsers('branch',false);
                $branch_visiblers['openid'] = $jurisdiction->getStore('branch_visiblers');
                if(!$branch_visiblers['openid']){
                    $branch_visiblers['openid'] = D("ComRecharge")->getCapitalLeaderOpenid("ComRecharge");
                }
                //$branch_visiblers['account_balance'] = D("EShop/ComFinance")->getWxAccountMoney($recharge['receivable_id']);//获取收款账户的余额
                $recharge['openid'] = $account['openid'];
                $recharge['url'] = str_replace('shop','shop'.$this->_user_session->currBranchId,SHOP_ROOT).'/money';
            }
            switch ($notice) {
                case 'adopt':
                    $recharge['transaction_type'] = '账户充值(成功)';
                    $currency_tip = TCT_RECHARGE_COMPLETE_NOTICE;
                    $branch_currency_tip = TCT_BRANCH_RECHARGE_COMPLETE_NOTICE;
                    $branch_visiblers['transaction_type'] =  '账户充值成功（线下付款）';
                    break;
                case 'refuse':
                    $recharge['transaction_type'] = '账户充值(失败)';
                    $currency_tip = TCT_RECHARGE_REFUSE_NOTICE;
                    $branch_currency_tip = TCT_BRANCH_RECHARGE_REFUSE_NOTICE;
                    $branch_visiblers['transaction_type'] =  '账户充值失败（线下付款）';
                    $branch_visiblers['account_balance'] = '';
                    break;
                default:
                    break;
            }
            $this->sendWxTemplate($branch_currency_tip,$branch_visiblers);
            $this->sendWxTemplate($currency_tip,$recharge);
        }
        //通知信息 id:id,notice:'refuse'
    }
    protected function sendWxTemplate($currency_tip,$data)
    {
        $template_id = getWxTemplateIdByStandardId('OPENTM415437052');
        $message = array();
        $body = array();
        $remark = '感谢使用, 如有问题请及时联系我们!';
        $message["template_id"] = $template_id;
        $message["url"] = $data['url'];
        if(strstr($currency_tip,'branch')){
            //$message["url"] = '';
            $remark = '';
        }
        $body["first"]["value"]    = getWxTemplateCurrencyTip($currency_tip);
        $body["keyword1"]["value"] = $data["transaction_type"];
        $body["keyword2"]["value"] = $data["account"];
        $body["keyword3"]["value"] = $data["pay_time_view"];
        $body["keyword4"]["value"] = $data["account_balance"];
        $body["remark"]["value"] = $remark;
        $message["body"] = $body;
        if ($data['openid']) {
            if (is_array($data['openid'])) {
                foreach ($data['openid'] as $val){
                    $message["openid"] = $val;
                    send_wx_message($message);
                }
            } else {
                $message["openid"] = $data['openid'];
                send_wx_message($message);
            }
        }
    }
}