<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class  ComCompanyParameterController extends DataController {
    public function indexAction() {
        define("__FORM_ACTION__", "update");
        $result = A('ComCapitalDetails')->getAccountSystemManage($this->_user_session->currBranchId,1,[CAJ_BRANCH_CUSTOMER_CAPITAL],['other']);
        $account_belong = A('ComCapitalDetails')->handlerAccountSystem($result);
        if(!$account_belong['customer_capital_leader_id']){
            $leader = M("SysBranch a")
                ->join("sys_user b on a.customer_leader_id = b.id")
                ->where("a.id = ".$this->_user_session->currBranchId)->field("b.id,b.name,b.staff_name")->find();
            $account_belong['customer_capital_leader_id'] = $leader['id'];
            $account_belong['customer_capital_leader_view'] = empty($leader['staff_name']) ? $leader['name']:$leader['staff_name'];
        }
        $this->assign('account_belong',$account_belong);
        $result = M('SysBranch')->where('id = '.$this->_user_session->currBranchId.' and customer_leader_id = '.$this->_user_session->userId)->find();
        $this->assign('has_leader',$result ? true : false);
        $this->assignPermissions(); //权限设置
        $this->display();
    }
    public function company_belongAction()
    {
        $str = I('q');
        $type = I('get.type');
        $condition = [];
        if (!empty($str)) {
            $where['user.name']  = array('like', '%'.$str.'%');
            $where['user.querykey']  = array('like', '%'.$str.'%');
            $where['user.mobile'] = array('like', $str . '%');
            $where['_logic'] = 'or';
            $condition['_complex'] = $where;
        }
        $condition['user_branch.type'] = 1;
        $condition['user.branch_id'] = $this->_user_session->parentBranchId;
        $condition['user_branch.branch_id'] = $this->_user_session->currBranchId;
        //$condition['id'] = array('neq',$this->_user_session->userId);
        if ($type != 1) {
            $customer_data = M('SysUser')
                ->alias('user')
                ->join('inner join sys_user_branch as user_branch on user_branch.user_id = user.id')
                ->where($condition)
                ->field("user.id,user.name,user.querykey,user.mobile")->select();
        } else {
            $customer_data = M('SysUser')
                ->alias('user')
                ->join('inner join sys_user_branch as user_branch on user_branch.user_id = user.id')
                ->where($condition)
                ->field("user.id as value,user.name as text")->select();
        }
        $this->ajaxReturn($customer_data);
    }
    public function updateAction() {
        if (IS_POST) {
            $hasLeader = M('SysBranch')->where('id = '.$this->_user_session->currBranchId.' and customer_leader_id = '.$this->_user_session->userId)->find();
            if ($hasLeader) {
                $account_jurisdiction = D('ComAccountJurisdiction');
                $postdata = I('post.');
                $account_jurisdiction->setOptions('object_id',$this->_user_session->currBranchId);
                $account_jurisdiction->setObjectVarious([CAJ_BRANCH_CUSTOMER_CAPITAL]);
                if (!empty($postdata['customer_capital_leader_id']) && !empty($postdata['customer_capital_visiblers'])) {
                    if (in_array($postdata['customer_capital_leader_id'],$postdata['customer_capital_visiblers'])){
                        unset($postdata['customer_capital_visiblers'][array_search($postdata['customer_capital_leader_id'],$postdata['customer_capital_visiblers'])]);
                    }
                }
                $account_jurisdiction->setOptions('object_type',1);
                $save['leader_id'] = empty($postdata['customer_capital_leader_id']) ? null : $postdata['customer_capital_leader_id'];
                $save['visiblers'] = empty($postdata['customer_capital_visiblers']) ? null : implode(',',$postdata['customer_capital_visiblers']);
                $account_jurisdiction->setOptions(CAJ_BRANCH_CUSTOMER_CAPITAL,$save);
                $result = $account_jurisdiction->saveAccountJurisdiction();
                if ($result) {
                    $this->ajaxReturn(buildMessage('保存成功!',0));
                } else {
                    $this->ajaxReturn(buildMessage('保存失败!',1));
                }
            } else {
                $this->ajaxReturn(buildMessage('您不是该公司的负责人,不能修改!',1));
            }

        }
    }
    protected function getUserHasAccountLeader()
    {
        $jurisdiction =  D('ComAccountJurisdiction');
        $jurisdiction->setObjectType(1);
        $jurisdiction->setObjectId($this->_user_session->currBranchId);
        $jurisdiction->setObjectVarious([CAJ_BRANCH_CUSTOMER_CAPITAL]);
        $jurisdiction->handlerHasCompanyLeader();
        return $jurisdiction->getStore('has_leader');
    }
}