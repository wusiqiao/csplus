<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class  ComCapitalParameterController extends DataController {
    protected $_parameter_arrays = [
        TCT_RECHARGE_COMPLETE_NOTICE,
        TCT_BRANCH_RECHARGE_COMPLETE_NOTICE,
        TCT_RECHARGE_REFUSE_NOTICE,
        TCT_BRANCH_RECHARGE_REFUSE_NOTICE,
        TCT_WITHDRAWAL_COMPLETE_NOTICE,
        TCT_BRANCH_WITHDRAWAL_COMPLETE_NOTICE,
        TCT_WITHDRAWAL_REFUSE_NOTICE,
        TCT_BRANCH_WITHDRAWAL_REFUSE_NOTICE,
        TCT_USER_INCOME_COMPLETE_NOTICE,
        TCT_COMPANY_INCOME_COMPLETE_NOTICE,
        TCT_DISTRIBUTION_COMPLETE_NOTICE
    ];
    protected $_leader_parameter_arrays =[
        'withdrawal_leader_id' => ['module' => 'ComWithdrawal','key' => 'withdrawal'],
        'recharge_leader_id' => ['module' => 'ComRecharge','key' => 'rechagre']
    ];
    protected $_leaders = ['ComWithdrawal','ComRecharge'];
    public function indexAction() {
        define("__FORM_ACTION__", "update");
        $_filter['branch_id'] = getBrowseBranchId();
        $_filter = ['various' =>['in',$this->_parameter_arrays]];
        $parameter = D(CONTROLLER_NAME)->setDacFilter("a")->field("a.*")->where($_filter)->select();
        $model = [];
        if ($parameter) {
            foreach ($parameter as $key => $value) {
                $model[$value['various']] = $value['message'];
            }
        }
        //负责人
        $where_leader['sms.module']         = array('in',$this->_leaders);
        $where_leader['sms.branch_id']      = getBrowseBranchId();
        $where_leader['sms.permit_value']   = 8;
        $where_leader['sms.type']   = DAC_SETTING_TYPE_BRANCH;
        $result= M("SysUserModuleSetting")->field('sms.module,user.name,user.id,user.staff_name')->alias('sms')->join('sys_user as user on user.id = sms.user_id')->where($where_leader)->select();
        if ($result) {
            foreach($result as $key => $value){
                $model[substr(strtolower($value['module']),3).'_leader'] = empty($value['staff_name']) ? $value['name'] :$value['staff_name'];
                $model[substr(strtolower($value['module']),3).'_leader_id'] = $value['id'];
            }
        }
        $this->assign("is_leader",$this->_user_session->is_leader);
        $this->assign('model',$model);
        $this->assignPermissions(); //权限设置
        $this->display();
    }
    public function updateAction() {
        if (IS_POST) {
            $_user_session = session(USER_SESSION_KEY);
            $add = [];
            $post_data = I('post.');
            $condition['branch_id'] = getBrowseBranchId();
            $various = D(CONTROLLER_NAME)->where($condition)->getField('various',true);
            //对以前的负责人进行删除
            $where_leader['module'] = array('in',$this->_leaders);
            $where_leader['branch_id'] = getBrowseBranchId();
            $where_leader['permit_value'] = 8;
            $where_leader['type'] = DAC_SETTING_TYPE_BRANCH;
            M("SysUserModuleSetting")->where($where_leader)->delete();
            //
            foreach($post_data as $key => $value) {
                $leader_parameter = array_keys($this->_leader_parameter_arrays);
                if (!in_array($key,$leader_parameter)) {
                    $message = strval($value);
                    if (!empty($various) && in_array($key,$various)) {
                        $condition['various'] = $key;
                        $save['message'] = $message;
                        $save['updated_at'] = time();
                        D(CONTROLLER_NAME)->where($condition)->data($save)->save();
                    } else {
                        $add[] = [
                            'branch_id' =>$condition['branch_id'],
                            'message' =>$message,
                            'various' => $key,
                            'user_id' => $_user_session->userId,
                            'creator_id' =>  $_user_session->userId,
                            'created_at' => time(),
                            'updated_at' => time()
                        ];
                    }
                } else {
                    if (in_array($key,$leader_parameter) && $this->_leader_parameter_arrays[$key]) {
                        $append = [
                            'module' => $this->_leader_parameter_arrays[$key]['module'],
                            'branch_id' => getBrowseBranchId(),
                            'company_id' => 0,
                            'user_id' => $value,
                            'permit_value' => 8,
                            'type'=>DAC_SETTING_TYPE_BRANCH
                        ];
                        M("SysUserModuleSetting")->add($append);
                    }
                }
            }
            if ($add) {
                D(CONTROLLER_NAME)->addAll($add);
            }
            $this->ajaxReturn(buildMessage('保存成功',0));
        }
    }
}