<?php
//初始化日志

class MiniProgramPayNotifyCallBack extends WxPayNotify {
//查询订单
    public function Queryorder($transaction_id) {
        $input = new \WxPayOrderQuery();
        miniPayParams($input);
        $input->SetTransaction_id($transaction_id);
        $result = \WxPayApi::orderQuery($input);
        $out_trade_no = $result['out_trade_no'];
//        \Think\Log::record($result);
        $order = D("ComOrder")->where("order_sn='$out_trade_no'")->find();
        if ($order['surety_state'] == 1) { //付完款就直接结束
            return true;
        }
        //设置需求已付款
        M('ComOrder')->data(array('surety_state' => 1))->where(array('order_sn' => $out_trade_no))->save();
        $result = D("ComFinance")->orderPay($order, true, $transaction_id);
        return ($result["code"] == 0);
    }

//重写回调处理函数
    public function NotifyProcess($data, &$msg) {
//        Log::DEBUG("call back:online" . json_encode($data));
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
