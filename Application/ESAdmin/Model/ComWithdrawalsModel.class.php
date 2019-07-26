<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class ComWithdrawalsModel extends DataModel {
    protected $_link = array(
        "user" => array(
            "join_name" => "LEFT",
            'class_name' => "SysUser",
            'foreign_key' => 'user_id',
            'mapping_name' => 'user',
            'mapping_fields' => 'name,id',
            "mapping_key" => "id"
        ),
        "company" => array(
            "join_name" => "LEFT",
            'class_name' => "SysBranch",
            'foreign_key' => 'company_id',
            'mapping_name' => 'company',
            'mapping_fields' => 'name,id',
            "mapping_key" => "id"
        ),
        "receivables" => array(
            "join_name" => "LEFT",
            'class_name' => "WrkReceivablesAccount",
            'foreign_key' => 'receivable_id',
            'mapping_name' => 'receivable',
            'mapping_fields' => 'name,id',
            "mapping_key" => "id"
        )
    );
    public function withdrawalAdopt($payment,$user_id)
    {
        $this->startTrans();
        try{
            $withdrawal["status"] = 1;
            $withdrawal["handle_time"] = time();
            $withdrawal["fee"] = I('post.third_fee');
            $withdrawal["receivable_id"] = I('post.origin');
            $withdrawal['operate_id'] = $user_id;
            $withdrawal["attach_group"] = I('post.attach_group');
            $result = D('ComWithdrawals')->where("id=".$payment['id'])->save($withdrawal);
            if ($result){
                //审核成功，产生对账单
                $financein['fina_type'] = $payment['withdrawal_type'];
                $financein['fina_cash'] = $payment['money'];
                $financein['fina_time'] = time();
                $financein['user_id'] = $payment['user_id'];
                $financein['company_id'] = $payment['company_id'];
                $financein['order_sn'] = $payment['order_sn'];
                $financein['platform_fee'] = I('post.third_fee');
                $financein["receivable_id"] = I('post.origin');
                if ($payment['withdrawal_type'] == FIN_CIZ_WITHDRAW){
                    $financein['title'] = '公司资金提现';
                    M("ComFinance")->data($financein)->add();
                    $members = M("SysBranch");
                    $account = $members->field('money_auditing,money')->where('id = '.$payment['company_id'])->find();
                    $company_save_data['money_auditing'] =  sprintf("%.2f", $account['money_auditing'] - $payment['money']);
                    $company_save_data['money'] =  sprintf("%.2f", $account['money'] - $payment['money']);
                    $members->where("id=".$payment['company_id'])->data($company_save_data)->save();
                }elseif($payment['withdrawal_type'] == FIN_UIZ_WITHDRAW){
                    $financein['title'] = '个人资金提现';
                    M("ComFinance")->data($financein)->add();
                    $members = M("SysUser");
                    $account = $members->field('user_money_auditing,user_money')->where('id = '.$payment['user_id'])->find();
                    $user_save_data['user_money_auditing']  = sprintf("%.2f", $account['user_money_auditing'] - $payment['money']);
                    $user_save_data['user_money']  = sprintf("%.2f", $account['user_money'] - $payment['money']);
                    $members->where("id=".$payment['user_id'])->data($user_save_data)->save();
                }
                $receivables = M('wrk_receivables_account')->where('id = '. $financein['receivable_id'])->getField('accumulated_amount');
                $receivables_money = $receivables - $payment['money'];
                M('wrk_receivables_account')->where('id = '. $financein['receivable_id'])->setField("accumulated_amount", $receivables_money);;
                $this->commit();
                $condition["a.id"] = $payment['id'];
                $record = D('ComWithdrawals')->alias("a")
                                ->field("a.*,company.name as company_name,user.name as user_name")
                                ->join('left join sys_user as user on user.id = a.user_id')
                                ->join('left join sys_branch as company on company.id = a.company_id')
                                ->where($condition)->find();
                $record['capital_account'] = $record['money_type'] == FIN_CIZ_WITHDRAW ? $record['company_name'] : $record['user_name'];
                $record['actual_money'] = $record['status'] == 1 ? sprintf("%.2f", $record['money'] - $record['fee']):'';
                $record['object_type'] = $record['money_type'] == FIN_CIZ_WITHDRAW ? 'c':'u';
                return array('code'=>0,'message' => '提现成功','id' =>$payment['id'],'row'=>$record);
            }
        }catch(\Exception $e){
            $this->rollback();
            return array('code' =>1 ,'message' =>"系统出错，请联系管理员".$e->getMessage());
        }
    }
}