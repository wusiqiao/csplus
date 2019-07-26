<?php

//初始化日志

class SmsSetmealPayCallBack extends WxPayNotify {
    //查询订单
    public function Queryorder($transaction_id) {
        $input = new \WxPayOrderQuery();
        setPayParams($input);
        $input->SetTransaction_id($transaction_id);
        $result = \WxPayApi::orderQuery($input);
        $out_trade_no = $result['out_trade_no'];

        $order_info = D("SmsBuyrecord")->where("order_sn='$out_trade_no'")->find();
        //判断是否支付
        if ($order_info['pay_status'] == 1 && !empty($order_info['pay_time'])) {
            return true;
        }
        $user_id = $order_info['service_id'];
        //改变订单状态
        $result = M('SmsBuyrecord')->where(array('order_sn' => $out_trade_no))->data(array('pay_time' => time(), 'pay_status' => 1))->save();
        //改变短信条数
        $company_info = M('Company')->where(array('user_id' => $user_id))->field('company_id,sms_nums,sms_nums_total')->find();
        $sms_nums = empty($company_info['sms_nums']) ? 0 : $company_info['sms_nums'];
        $sms_nums_total = empty($company_info['sms_nums_total']) ? 0 : $company_info['sms_nums_total'];
        $company_data = array(
            'sms_nums' => $sms_nums + $order_info['sms_nums'],
            'sms_nums_total' => $sms_nums_total + $order_info['sms_nums'],
        );
        $company_res = M('Company')->where(array('user_id' => $user_id))->data($company_data)->save();
//		Log::DEBUG("query:". json_encode($result));
        if (array_key_exists("return_code", $result) && array_key_exists("result_code", $result) && $result["return_code"] == "SUCCESS" && $result["result_code"] == "SUCCESS") {
            return true;
        }
        return false;
    }

    //重写回调处理函数
    public function NotifyProcess($data, &$msg) {
//		Log::DEBUG("call back:smsmeal" . json_encode($data));
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
