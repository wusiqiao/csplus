<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/5
 * Time: 14:43
 */

namespace ESAdmin\Controller;


use Common\Lib\Controller\DataController;

class ComTransferCaseController extends DataController
{
    protected function _before_list(&$list)
    {
        parent::_before_list($list); // TODO: Change the autogenerated stub
        foreach ($list as $key=>$val){
            $list[$key]['ctime'] = date('Y年m月d日 H:i',$val['ctime']);
            $order =  M('ComOrder')->field('order_state')->where('order_sn = \''.$val['order_sn'].'\'')->find();
            $list[$key]['pay_status'] = $order['order_state'] == ORDER_STATE_CLOSED ? 3 : $val['pay_status'];
        }
    }

    public function approvalAction($id){
//      $this->assign("model", $data);
        $_filter['status'] = 1;
        $receivables_account = D('WrkReceivablesAccount')->setDacFilter("a")->field("a.id,a.name")->where($_filter)->select();
        $this->assign('receivables_account',$receivables_account);
        $list =     M('ComRecharge') ->alias('cr')
                                     ->field('cr.*,pro.product_title as title,co.order_state,co.surety_state,co.contacts,FROM_UNIXTIME(cr.ctime,"%Y年%m月%d日 %H:%i") as created_time')
                                     ->join('left join com_order    co  on co.order_sn = cr.order_sn')
                                     ->join('left join com_product  pro on pro.id      = co.product_id')
                                     ->where('cr.id = '.$id)
                                     ->find();
        $list['view_state']  = $list['pay_status'] == 0 ? '审核中' :
                               ($list['pay_status'] == 1 ? '审核通过':'审核失败');
        $list['view_state']  = $list['order_state'] == ORDER_STATE_CLOSED ? '订单已关闭' : $list['view_state'];
        $list['attach_group'] = empty($list['attach_group']) ? genUniqidKey() : $list['attach_group'];
        $this->assign('model',$list);
        $this->display();
    }
    public function updateApprovalAction(){
        $postdata       =       I('post.');
        //判断该条记录是否已审核
        $recharge =   M('ComRecharge')->field('pay_status,order_sn')->where('id = '.$postdata['id'])->find();
        if($recharge['pay_status'] > 0){
            $this->ajaxReturn(array('error'=>1,'message'=>'该条记录已审核,不能再次审核!!'));
        }else{
            //判断订单当前状态
            $order = M('ComOrder')->field('order_state,id')->where('order_sn = \''.$recharge['order_sn'].'\'')->find();
            if($order['order_state'] != ORDER_STATE_USER_BUY){
                $this->ajaxReturn(array('error'=>1,'message'=>'该订单当前状态,审核失效!!'));
            }
            if($postdata['type'] == 2 && trim($postdata['remark']) == ''){
                $this->ajaxReturn(array('error'=>1,'message'=>'请填写审核失败的原因!!'));
            }
            if ($postdata['type'] == 1 && empty($postdata['origin'])){
                $this->ajaxReturn(array('error'=>1,'message'=>'请选择收款账号!!'));
            }
            $attach['origin'] = $postdata['origin'];
            $attach['attach_group'] = $postdata['attach_group'];
            $result = D("ComFinance")->unlinesAudit($postdata['id'], $postdata['type'], $postdata['remark'],$attach);
            if($result){
                D("ComOrder")->addOrderCommision($order['id']);
                $this->ajaxReturn(array('error'=>0,'message'=>'审核成功!!'));
            }else{
                $this->ajaxReturn(array('error'=>1,'message'=>'审核失败!!'));
            }
        }
    }
}