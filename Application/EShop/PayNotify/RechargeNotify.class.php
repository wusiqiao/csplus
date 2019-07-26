<?php

//初始化日志

class RechargeNotifyCallBack extends WxPayNotify {

//查询订单
    public function Queryorder($transaction_id) {
        //查询交易单号
        $input = new \WxPayOrderQuery();
        setPayParams($input);
        $input->SetTransaction_id($transaction_id);
        $result = \WxPayApi::orderQuery($input);
        $out_trade_no = $result['out_trade_no'];
        //充值记录是否存在
        $payment = D("ComRecharge")->where("order_sn='$out_trade_no'")->find();
        if ($payment['pay_status'] == 1 && !empty($payment['pay_time'])) {
            return true;
        }
        $finance_table = D('ComFinance');
        $finance_table->rechargePay($payment, $transaction_id);

        return true;        
    }

//重写回调处理函数
    public function NotifyProcess($data, &$msg) {
        //Log::DEBUG("call back:woaini" . json_encode($data));
        $notfiyOutput = array();

        if (!array_key_exists("transaction_id", $data)) {
            $msg = "输入参数不正确";
            return false;
        }
//查询订单，判断订单真实性
        if (!$this->Queryorder($data["transaction_id"])) {
            $msg = "订单查询失败";
            return false;
        }
        return true;
    }

}
