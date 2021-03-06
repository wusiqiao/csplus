<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/5
 * Time: 14:43
 */

namespace ESAdmin\Controller;


use Common\Lib\Controller\DataController;

class ComOrderController extends DataController
{
    protected function _before_list(&$list)
    {
        parent::_before_list($list); // TODO: Change the autogenerated stub
        foreach($list as $key=>$val){
            $list[$key]['on_time']           = date('Y年m月d日 H:i',$val['on_time']);
            $list[$key]['category']          = $list['product_category'];
            $list[$key]['real_cash']         = $val['real_cash']     > 0 ? $val['real_cash'].'元'     : '暂未报价';
            $list[$key]['payment_money']     = $val['payment_money'] > 0 ? $val['payment_money'].'元' : '暂未付款';
            $list[$key]['trade']             = '线上支付';
            $list[$key]['discount']          = ($val['service_voucher_cash'] > 0)  ? '代金劵('.$val['service_voucher_cash'].')' : '无优惠';
            $list[$key]['order_state']       = $this->order_stateing($val);
        }
    }
    protected function _before_detail(&$data)
    {
        parent::_before_detail($data); // TODO: Change the autogenerated stub
        $data['real_cash']          = $data['real_cash']     > 0 ? $data['real_cash'].'元'     : '暂未报价';
        $data['payment_money']      = $data['payment_money'] > 0 ? $data['payment_money'].'元' : '暂未付款';
        $data['trade']              = '线上支付';
        $data['discount']           = ($data['service_voucher_cash'] > 0)  ? '代金劵('.$data['service_voucher_cash'].')' : '无优惠';
        $data['category']           = $data['product_category'];
        $data['on_time']           = date('Y年m月d日 H:i',$data['on_time']);
        $data['order_state']       = $this->order_stateing($data);
        $data['attribute']         = json_decode($data['product_attribute'],true);
        if(ACTION_NAME == 'detail'){
            //获取业务进度
            $report_table = M('SysReport');
            $reports = $report_table -> where(array('order_id' => $data['id'])) ->order('report_time desc') ->select();
            $this->assign('reports',$reports);
        }
    }
    protected function order_stateing($order) {
        $result = "其他状态";
        switch (intval($order['order_state'])) {
            case ORDER_STATE_USER_BUY:
                if ($order['surety_state'] == ORDER_PAY) {
                    $result = "待服务";
                } elseif ($order['surety_state'] == ORDER_DONT_PAY ) {
                    $result = "待付款";
                } elseif ($order['surety_state'] == ORDER_DONT_PAY){
                    $result = "待交纳";
                }
                break;
            case ORDER_STATE_SERVICE:
                $result = "服务中";
                break;
            case ORDER_STATE_WAITING_CHECK:
                $result = "待验收";
                break;
            case ORDER_STATE_FINISH:
                $result = "已完成";
                break;
            case ORDER_STATE_WAITING_JUDGE:
                $result = "已完成";
                break;
            case ORDER_STATE_CLOSED:
                $result = "已关闭";
                break;
            case ORDER_STATE_HAS_JUDGE:
                $result = "已完成";
                break;
            default:
                break;
        }
        return $result;
    }

    public function addOrderCommisionAction($order_id){
        D("ComOrder")->unfreezeOrderCommisionFinishWork($order_id);
    }
}