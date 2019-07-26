<?php

namespace ESAdmin\Model;
use Common\Lib\Model\BillModel;
use Common\Lib\Model\DataModel;

class ComOrderModel extends BillModel {
    protected $_MODEL = 'ComOrder';
    protected $_PREFIX= 'com_';
    protected $_TICKET_PREFIX = 'sp_';
//New Content Start
//    protected $_link = array(
//        "ComProduct" => array(
//            "join_name" => "LEFT",
//            'class_name' => "ComProduct",
//            'foreign_key' => 'product_id',
//            'mapping_name' => 'pro',
//            'mapping_fields' => 'product_title,category_id,category_pid',
//            "mapping_key" => "id"
//        )
//    );
//New Content End
    public function getOrderDetailData($order_id){

        $field	= "o.*,o.id as order_id,o.on_time as number_on_time,o.tel as contacts_tel,FROM_UNIXTIME(o.on_time,'%Y.%m.%d') as order_on_time";
        $result	= M($this->_MODEL)
                        ->alias('o')
//                        ->join($this->_PREFIX."product pro on pro.id = o.product_id")
                        ->field($field)
                        ->where("o.id =".$order_id)
                        ->find();
        return $result;
    }
    //获取订单临时数据
    public function setOrderTemporaryDete($order_id){
        M("ComTemporaryData")->where("order_id = ".$order_id)->delete();
    }
    //获取订单临时数据
    public function getOrderTemporaryData($order_id){
        $result	=	M("ComTemporaryData")->where("order_id = ".$order_id)->find();
        return $result;
    }
    //    您好，您的服务产品【服务产品名称】客户已担保付款，请确认开始服务，并报告业务进度。
    //交易流水：【交易流水号】
    //付款方式：【微信支付】
    //付款金额：【付款金额】
    //客户名称：【客户昵称/姓名】
    //支付时间：【担保付款时间】
    //点击详情查看，如有疑问，请致电财穗快线客服0592-5239592
    //"bjlLwcIUuqvFXu3KnlXRBF99kKsDFw2S09dut-cnF7g"//客户担保支付通知
    public function sendWXPayedMessageOrder($order_id) {
//        if(trimall(getWxTemplateId('orderPayed')) == ''){
//            return false;
//        }
//        $OrderModal	=D($this->_MODEL);
//        $order_data	= $OrderModal->getOrderDetailData($order_id);
//        $fin		= M("ComFinance")->where("order_sn = '".$order_data['order_sn']."'")->find();
//        $service	= M("SysUser")->where("id = ".$order_data['service_id'])->find();
//        if ($order_data) {
//            $message = array();
//            $body = array();
//            $message["template_id"] = getWxTemplateId('orderPayed');
//            $message["openid"] = $service['openid'];
//            $message["url"] = SHOP_ROOT . "/index.php/Order/sellDetail/id/$order_id.html";
//            $body["first"]["value"] = "您好，您的服务【" . $order_data["product_title"] . "】客户已付款，请确认开始服务，并报告业务进度。";
//            $body["keyword1"]["value"] = $fin["pay_code"] ? $fin["pay_code"] : $fin['order_sn']; //交易流水号
//            $body["keyword2"]["value"] = $fin["title"];
//            $body["keyword3"]["value"] = $fin["fina_cash"]; //付款金额
//            $body["keyword4"]["value"] = $order_data["contacts"]; //服务用户
//            $body["keyword5"]["value"] = date("Y-m-d H:i:s", $fin["fina_time"]); //付款金额
//            $body["remark"]["value"] = getMessageRemark();
//            $message["body"] = $body;
//            send_wx_message($message); //异步
//        }
    }

    /**订单佣金
     * @param $order_id
     */
    public function addOrderCommision($order_id){
        $condition["source_id"] = $order_id;
        $condition["source_type"] = COMMISION_FROM_ORDER;
        if (M("DistributionIncome")->where($condition)->count() > 0){ //添加检查
            return false;
        }
        $order_list = $this->query("select product_id,real_cash,payment_money,order_sn,user_id,branch_id from com_order where id=$order_id");
        if ($order_list){
            $order = $order_list[0];
            $branch_id = $order["branch_id"];
            $user_id = $order["user_id"];
            $commision_settings = D("ESAdmin/DistributionSetting")->getSettings($branch_id);
            if ($commision_settings){
                $commision = 0;
                if ($commision_settings["product_settings"]){ //按服务设定
                    $product_setting = $commision_settings["product_settings"][$order["product_id"]];
                    if ($product_setting){
                        if (intval($product_setting["commision_type"]) == COMMISION_TYPE_RATE){
                            $commision = $order["real_cash"] * $product_setting["commision_rate"]  / 100; //订单内服务金额*比例
                        }else{
                            $commision = $product_setting["commision_amount"]; //固定金额
                        }
                    }
                }else{
                    if (intval($commision_settings["commision_type"]) == COMMISION_TYPE_RATE){
                        $commision = $order["real_cash"] * $commision_settings["commision_rate"] / 100; //订单内服务金额*比例
                    }else{
                        $commision = $commision_settings["commision_amount"]; //固定金额
                    }
                }
                if ($commision > 0){
                    $data["user_id"] = $user_id;
                    $data["update_time"] = time();
                    if (intval($commision_settings["frozen_type"]) == COMMISION_FROZEN_TYPE_STARTWORK && $commision_settings["frozen_days"] == 0){ //如果是开始服务后0天
                        $data["status"] =  1; //马上生效
                    }else {
                        $data["status"] = 0; //确认中
                    }
                    $data["source_type"] = COMMISION_FROM_ORDER;
                    $data["source_id"] = $order_id;
                    $data["branch_id"] = $branch_id;
                    $data["commision"] = $commision;
                    $data["frozen_type"] = $commision_settings["frozen_type"];//记录当时解冻类型
                    $data["frozen_days"] = $commision_settings["frozen_days"];//记录当时解冻日期
                    $data["memo"] = "购买服务，单号：".$order["order_sn"];
                    $last_id =  M("DistributionIncome")->add($data);
                    if (intval($commision_settings["frozen_type"]) == COMMISION_FROZEN_TYPE_STARTWORK && $commision_settings["frozen_days"] > 0){ //如果是开始服务
                        D('ESAdmin/SysMq')->add_timer(intval($commision_settings["frozen_days"]) * 24 * 60 * 60, ADMIN_HOST."/ReqQueue/unfreezeCommision/id/$last_id");
                    }
                }
            }
        }
    }

    //服务完成后解冻
    function unfreezeOrderCommisionFinishWork($order_id){
        $condition["source_id"] = $order_id;
        $condition["source_type"] = COMMISION_FROM_ORDER;
        $commision_data = M("DistributionIncome")->where($condition)->find();
        if ($commision_data && $commision_data["frozen_type"] == COMMISION_FROZEN_TYPE_FINISHWORK){
            if ($commision_data["frozen_days"]){
                D('ESAdmin/SysMq')->add_timer(intval($commision_data["frozen_days"]) * 24 * 60 * 60, ADMIN_HOST."/ReqQueue/unfreezeCommision/id/".$commision_data["id"]);
            }else{
                $data["status"] = 1;
                $data["unfrozen_time"] = time();
                M("DistributionIncome")->where("id=".$commision_data["id"])->save($data);
            }
        }
    }

    //订单创建合同
    public function createAgreementByOrder($order_sn){
        $order = M("ComOrder")->where("order_sn = '$order_sn'")->find();
        if($order){
            $is_auto_agreement = M("ComStore")->where("id = ".$order['branch_id'])->getField("is_auto_agreement");
            if($is_auto_agreement){
                $data['origin'] = 0;//商城
                $data['customer_leader_id'] = $order['user_id'];//订单购买人
                $data['order_sn'] = $order_sn;
                $data['company_id'] = $order['branch_id'];
                $data['name'] = $order_sn . " " . $order['product_title'];
                $data['agreement_money'] = $order['real_cash'];
                //服务明细
                $product_attributes = json_decode($order['product_attribute'],true);
                $product_category = explode("-",$order['product_category']);
                $tmp = [];
                for($i =0;$i<3;$i++){
                    if($product_attributes[$i]['parent_name']){
                        $tmp['attributes'.($i+1)] = $product_attributes[$i]['parent_name']."：".$product_attributes[$i]['name'];
                    }else{
                        $tmp['attributes'.($i+1)] = "";
                    }
                }
                $option[0] = array("id"=>1,'type1'=>$product_category[0],
                    'type2'=>$product_category[1],
                    'attributes1'=>$tmp['attributes1'],
                    'attributes2'=>$tmp['attributes2'],
                    'attributes3'=>$tmp['attributes3']);
                array_push($option,$order['order_desc']);
                $data['product_options'] = json_encode($option);
                $branch_info = M("SysBranch")->where("id = ".$order['branch_id'])->find();
                $data['creater_id'] = $branch_info['leader_id'];
                $data['leader_id'] = $branch_info['leader_id'];
                $data['visiblers'] = $branch_info['leader_id'];
                $data['create_time'] = time();
                $data['start_time'] = $order['on_time'];
                //$data['comments'] = "订单自动生成合同";
                $data['sys_sn'] = "A".$order['branch_id'].$order['user_id'].time();
                $data['branch_id'] = $order['branch_id'];
                $data['attach_group'] = genUniqidKey();
                $result = M("WrkAgreement")->add($data);
                if($result !== false){
                    D("WrkAgreement")->addInvoicePlan($data,$result);
                    $data["user_name"] = "系统";
                    $data["branch_name"] = $branch_info['name'];
                    $data["kind"] = 0;
                    $data["func"] = "WrkAgreement";
                    $data["operation"] = "orderCreateAgm";
                    $data["content"] = $result;
                    $data["create_time"] = time();
                    $data["ip"] = get_client_ip();
                    M("SysLog")->add($data);
                }
                return $result;
            }
        }
        return false;
    }

}
