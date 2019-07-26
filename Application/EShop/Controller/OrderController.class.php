<?php

namespace EShop\Controller;

use Think\Controller;

Class OrderController extends BaseController {
    protected $_permission_name = 'ComOrder';
    public function IndexAction(){
        $title = "订单管理";
        $this->assign('title', $title);
        $this->_assign_base_data_get_screen();
        $postdata = I('post.');
        if ($postdata) {
            $page_size = I("post.rows");
            $paging = I("post.page");
            $order		=	isset($postdata['order']) ? $postdata['order'] : null;//类型
            $titles		=	isset($postdata['title']) ? $postdata['title'] : null;//类型
            $where['o.user_id']	    =	$_SESSION['user_id'];
            $where['o.branch_id']   =   getBrowseBranchId();
            if(!empty($titles) && $titles != ''){
                //判断是一级类型还是二级类型
                $where['o.product_title'] = array('like','%'.$titles.'%');
            }
            //1 付款中 2服务中 3待验证 4已完成 5已关闭
            if(!empty($order) && $order != ''){
                switch ($order) {
                    case '1':
                        $where['o.order_state']		=	ORDER_STATE_USER_BUY;
                        $where['o.surety_state']	=   ORDER_DONT_PAY;
                        break;
                    case '2':
                        $where['_string']			=	'o.order_state ='.ORDER_STATE_SERVICE.' or o.order_state = '.ORDER_STATE_APPLY_CLOSE;
                        break;
                    case '3':
                        $where['o.order_state']		=	ORDER_STATE_WAITING_CHECK;
                        break;
                    case '4':
                        $where['o.order_state']		=	array('in',array(ORDER_STATE_FINISH,ORDER_STATE_WAITING_JUDGE,ORDER_STATE_HAS_JUDGE));
                        break;
                    case '5':
                        $where['o.order_state']		=	ORDER_STATE_CLOSED;
                        break;
                    case '6':
                        $where['o.order_state']		=	ORDER_STATE_USER_BUY;
                        $where['o.surety_state']	=	ORDER_PAY;
                        break;
                    default:
                        break;
                }
            }
            $count	=	D("ComOrder")
                        ->setDacFilter('o')
                        ->where($where)
                        ->count();
            $order_data	=	D("ComOrder")
                        ->setDacFilter('o')
                        ->field("o.product_title,o.order_state,o.order_sn,o.contacts,o.payment_money,o.id as order_id,if(o.order_state > 6 , o.on_time - 10000000,o.on_time ) as order_time,o.trade_type,o.surety_state,o.real_cash as order_real_cash,o.on_time as order_on_time")
                        ->where($where)
                        ->order('o.on_time desc')
                        ->page($paging, $page_size)
                        ->select();
            $order_list['total_count']	=	$count;
            $order_list['total'] = count($order_data);
//            var_dump($order_data);die;
            foreach ($order_data as $item) {
                $cash_type			=	'成交价格';
                if ($item['price_type'] == 1) {
                    if($item['order_real_cash'] > 0){
                        $cashdiv	= $item['order_real_cash'].'元 ' ;
                    }else{
                        $cashdiv	= '价格面议     ' ;
                    }
                } else {
                    $cashdiv	= $item['order_real_cash'].'元' ;
                }
                if(in_array($item['order_state'], array(ORDER_STATE_FINISH,ORDER_STATE_WAITING_JUDGE,ORDER_STATE_HAS_JUDGE))){
                    $state_color    =   'complete';
                }elseif(in_array($item['order_state'], array(ORDER_STATE_CLOSED))){
                    $state_color    =   'order-close';
                }else{
                    $state_color    =   'wait-pay';
                }
                $order_list['rows'][] = array(
                    'order_id' 		=> $item['order_id'],
                    'on_time' 		=> date('Y.m.d H:i', $item['order_on_time']),
                    'order_title' 	=> $item['product_title'],
                    'contacts'      => $item['contacts'],
                    'surety_state'  => $item['surety_state'],
                    'order_sn' 		=> $item['order_sn'],
                    'trade_type'	=> '直接交易',
                    'state_color'	=> $state_color,
                    'cashdiv'		=> $cashdiv >0 ? $cashdiv : "面议",
                    'cash_type'		=> $cash_type,
                    'order_stateing'=> order_stateing($item),
                    'total_count'   => $count,
                );
            }
            echo json_encode($order_list);
            exit();
        }

        $this->display();
    }
    public function serviceDetailAction(){
        $this->assign('title',getComStoreData('name'));
        $order_id	        =	I("get.id");
        $OrderModal	        =	D("ComOrder");
        $ReportModal        =	D("SysReport");
        $CommentModal       =	D("ComComment");
        $store = getComStoreData('all');
        if ($store['tel'] && strlen($store['tel'] > 0)){
            $store_tel = $store['tel'];
        }else{
            $store_tel = $store['mobile'] ? $store['mobile'] : '';
        }
        if(!$OrderModal->OrderExistence($order_id)){
            $this->error("该订单已被删除或不存在!!",'/Order/index');
        }
        $is_service_owner	=	$OrderModal->getIsOrderOwner($order_id);
        if(!$is_service_owner){
            $this->error("您不具备该服务的操作!!");
        }
        $report_data	            =	$ReportModal->getOrderReportList($order_id);
        $order_data		            =	$OrderModal->getOrderDetailData($order_id);
        $this->HandlerContactShow(['url'=>'/Liuyan/Me/order_id/'.$order_id.'.html','tel'=>$store_tel]);
        $order_data['view_state']	=	order_stateing($order_data);

        if($order_data['trade_type'] == 0 && $order_data['surety_state'] != 1 && $order_data['real_cash'] == 0 && $order_data['order_state'] == ORDER_STATE_USER_BUY){
            $order_data['view_state'] = '付款中';

            //NEW Jan 2,2018 判断是否有提交需求线下转账
        }elseif($order_data['trade_type'] == 0 && $order_data['surety_state'] != 1){
            $condition_str = 'order_sn   = \''.$order_data['order_sn'].'\' and ';
            $condition_str.= 'money_type = '.FIN_ORDER_LINE_PAY.' and ';
            $condition_str.= 'Source     = '.FIN_PAY_OFFLINE  .' and ';
            $condition_str.= 'user_id    = '.$order_data['user_id'] .' and ';
            $condition_str.= '(pay_status = 0 or pay_status = 2)';
            $res = M('ComRecharge')->where($condition_str)->order('ctime desc')->find();
            if($res && $res['pay_status'] == 0){
//                $order_data['view_state']	=	 ($res['pay_status'] == 2) ?
//                    '待付款,线下转账凭据,后台审核失败!':
//                    '已付款等待商家确认!';
                    $this->assign('is_unlink',1);

            } else {
                $voucher_tickets = D("ComOrder")->getProductVoucherDetail(I("get."),'order',0);
                $this->ticket = $voucher_tickets;
            }
        }
//        elseif($order_data['surety_state'] == ORDER_PAY && $order_data['order_state'] == ORDER_STATE_USER_BUY){
//            $order_data['view_state']	=	"已付款,等待开始服务";
//        }
        elseif($order_data['order_state'] == ORDER_STATE_CLOSED && $order_data['refund_state'] == ORDER_REFUND_STATE_COMPLETE){
            $refund = D('ComRefund')->getRefundDetail($order_data['order_id'],true);
            $order_data["view_state"] =   '已退款成功，交易关闭';
            $this->assign('refund',$refund);
        }elseif($order_data['order_state'] == ORDER_STATE_CLOSED && $order_data['refund_state'] == ORDER_REFUND_STATE_NO){
            $order_data["view_state"] = '已关闭';
        }elseif($order_data['surety_state'] == ORDER_PAY && in_array($order_data['order_state'],array(ORDER_STATE_SERVICE,ORDER_STATE_WAITING_CHECK)) && $order_data['refund_state'] > ORDER_REFUND_STATE_NO){
            //获取refund
            $refund = D('ComRefund')->getRefundDetail($order_data['order_id'],true);
            if($order_data['refund_state'] == ORDER_REFUND_STATE_BEGIN){
                $order_data["view_state"] = '已申请退款，处理中...';
            }elseif($order_data['refund_state'] == ORDER_REFUND_STATE_FAIL){
                $order_data["view_state"] = '对方不同意退款申请，处理中...';
            }
            $this->assign('refund',$refund);
        }
        if(in_array($order_data['order_state'],array(ORDER_STATE_WAITING_JUDGE,ORDER_STATE_CLOSED,ORDER_STATE_HAS_JUDGE))){
            $this->is_over = 1;
        }
        if ($order_data['order_state'] == ORDER_STATE_CLOSED) {
            $product = D('ComProduct')->field('state')->where('id = '.$order_data['product_id'])->find();
            if ($product) {
                $this->product_state = $product['state'];
            }
        }
        if($order_data['order_state'] == ORDER_STATE_HAS_JUDGE){
            $comments	=	$CommentModal->getOrderComment($order_id);
            $star		=	returnViewStar($comments['star']);
            $this->assign('comment',$comments['content']);
            $this->assign('star',$star);
        }
        //获取属性
        $this->_attribute_view($order_data['product_attribute']);
        $this->assign('report',$report_data);
        $this->assign('order',$order_data);
        $this->display();
    }
    protected function _attribute_view($data){
        $data = json_decode($data,true);
        $atts = [];
        foreach ($data as $key => $value) {
            $atts['tips'][] = $value['name'];
        }
        $this->assign('atts',$atts);
    }
    public function service_refresh_priceAction(){
        if(IS_POST){
            $order_id	=	I("post.id");
            $OrderModal	=	D("ComOrder");
            $is_service_owner	=	$OrderModal->getIsServiceOwner($order_id);
            if(!$is_service_owner){
                $this->error("您不具备该服务的操作!!");
            }
            //新的价格
            $real_cash	=	$OrderModal->getOrderRealCash($order_id);
            $voucher_tickets	=	$OrderModal->getServiceVoucherAll($order_id);
            if($voucher_tickets['count'] > 0){
                foreach ($voucher_tickets['lists'] as $key => $value) {
                    if($value['show_type'] != 'error') {
                        $voucher[$key] = $value['reduce_cost'];
                    }
                }
                $voucher_tickets['usable_ticket'] = count($voucher);
                $max_voucher['max_voucher_cost']	=	max($voucher);
                $max_voucher_key					=	array_search($max_voucher['max_voucher_cost'], $voucher);
                $max_voucher['max_voucher_key']		=	$max_voucher_key + 1;
                $max_voucher['max_voucher_ticket_id']	=	$voucher_tickets['lists'][$max_voucher_key]['id'];
            }
//            $ticket['max_service_voucher']	=	$max_service_voucher;
            $ticket['voucher_tickets']	=	$voucher_tickets;
            $ticket['max_voucher']	=	$max_voucher;
            echo json_encode(array('error'=>0,'msg'=>'价格已更新!','cash'=>$real_cash,'tickets'=>$ticket));
            exit;
        }

    }
    /**
     * 拒绝退款
     * @date Jan 15,2018
     */
    public function refuseRefundAction(){
        if(IS_POST){
            $postdata                   = I('post.');
            $order_id                   = $postdata['order_id'];
            $condition['o.id']          = $order_id;
            $condition['o.branch_id']     = getBrowseBranchId();
            $order    = M('ComOrder')
                            ->alias('o')
                            ->field('o.*,o.id as order_id,o.product_title,refund.id as refund_id')
                            ->join('com_refund refund on refund.order_id = o.id')
                            ->where($condition)
                            ->find();
            if($order){
                if($order['refund_state'] == ORDER_REFUND_STATE_BEGIN){
                    if(!trimall($postdata['refund_reply'])){
                        $message = array('error'=>1,'message'=>'操作失败,请输入拒绝原因!!');
                        $this->ajaxReturn($message);
                        exit;
                    }
                    //开始拒绝
                    $save_refund['id']   = $order['refund_id'];
                    $save_refund['refund_reply']= '服务商不同意该申请,原因:'.trimall($postdata['refund_reply']);
                    $save_refund['refund_service_reply']= session('user_name').'已拒绝该申请,原因:'.trimall($postdata['refund_reply']);
                    $save_refund['update_time'] = time();
                    $result = M('ComRefund')->save($save_refund);
                    if($result){
                        //修改需求的拒绝状态
                        $save_order['id']       = $order_id;
                        $save_order['refund_state']  = ORDER_REFUND_STATE_FAIL;
                        M('ComOrder')->save($save_order);
                        $report_table = M('SysReport');
                        $report_table->order_id             = $order_id;
                        $report_table->user_id              = $_SESSION['user_id'];
                        $report_table->report_time          = time();
                        $report_table->report_desc          = '服务商不同意退款';
                        $report_table->report_service_desc  = session('user_name').'不同意退款';
                        $report_table->add();
                        //发送消息
                        D('ComOrder')->sendWXOrderRefuseRefund($order);//微信 发送通知客户
                        D('ComComment')->sendSystemMessageFromOrderRefuseRefund($order);//系统消息 发送通知服务商
                        $message = array('error'=>0,'message'=>'操作成功,拒绝退款成功!!');
                        $this->ajaxReturn($message);
                        exit;
                    }else{
                        $message = array('error'=>1,'message'=>'操作失败,请重新操作!!');
                        $this->ajaxReturn($message);
                        exit;
                    }
                }else{
                    $message = ($order['refund_state'] == ORDER_REFUND_STATE_FAIL) ?
                        array('error'=>1,'message'=>'操作失败,您已经拒绝退款了,请勿重新操作!!') :
                        array('error'=>1,'message'=>'操作失败,当前状态不能拒绝退款!!');
                    $this->ajaxReturn($message);
                    exit;
                }
            }else{
                $message = array('error'=>1,'message'=>'操作失败,您没有权限!!');
                $this->ajaxReturn($message);
                exit;
            }


        }
    }
    public function orderCloseAction(){
        if(IS_POST){
            $order_id	=	I("post.order_id");
            $OrderModal	=	D("ComOrder");
            $is_service_owner	=	$OrderModal->getIsOrderOwner($order_id);
            if(!$is_service_owner){
                echo json_encode(array('error'=>1,'msg'=>'您不具备该服务的操作!!'));
                exit;
            }
            //是否已付款
            $is_pay_cash	=	$OrderModal->getOrderIsPayCash($order_id);
            if($is_pay_cash){
                //付款状态切换到 结束订单页面
                echo json_encode(array('error'=>0,'type'=>'jump','url'=>'/Order/enclosure/id/'.$order_id.'/type/1.html'));
                exit;
            }
            $result			=	$OrderModal->setOrderClose($order_id);
            if($result){
                //记录业务进度
                $report_table = D('SysReport');
                $remark = I("post.closeDesc");
                $closes['desc'] = '客户取消订单';
                $closes['title'] = '订单关闭';
                $closes['other'] = 1;
                $closes['remark'] = $remark;
                $report_table->addOrderReport($order_id,$closes);
                //Topics-0017
                D('ComOrder')->sendWXUserRefuseOrder($order_id);
                D('ComComment')->sendSysUserRefuseOrder($order_id);
//                D('ComOrder')->sendWXUserRefuseProductMessage($order_id);
//                D('ComComment')->sendSystemMessageFromUserRefuseProduct($order_id);
                echo json_encode(array('error'=>0,'msg'=>'订单取消成功',));
                exit;
            }else{
                echo json_encode(array('error'=>1,'msg'=>'订单取消失败',));
                exit;
            }
        }
    }
    /*
    *
    */
    public function orderDelAction(){
        $postdata = I('post.');
        //判断是否已付款
        $orderModel = D('ComOrder');
        if ($orderModel->getIsOrderSurety($postdata['order_id'])){
            echo json_encode(array('error'=>1,'msg'=>'该订单已付款,不能删除!!',));
            exit;
        }else{
            //删除订单
            $res = $orderModel->overallDelete($postdata['order_id']);
            echo json_encode($res);
            exit;
        }
    }
    public function servicePayAction(){
        $postdata = I('post.');
        $order_id	=	I("get.id");
        $OrderModal	=	D("ComOrder");
        if(empty($postdata)){
            $this->assign('title',getComStoreData('name'));
            $is_service_owner	=	$OrderModal->getIsOrderOwner($order_id);
            if(!$is_service_owner){
                $this->error("您不具备该服务的操作!!");
                die;
            }
            $order = $OrderModal->getOrderData($order_id);
            if ($order['real_cash'] == 0 || $order['real_cash'] < 0) {
                $this->error("该订单服务价格暂未提供,请联系服务商！", '/Order/serviceDetail/id/'.$order_id.'.html');
                die;
            }
            if ($order['surety_state'] == 1) {
                $this->error("该订单担保金额已支付，请勿重复支付！", '/Order/serviceDetail/id/'.$order_id.'.html');
                die;
            }
            $order_data	        =	$OrderModal->getOrderDetailData($order_id);
            $service_tickets	=	$OrderModal->getServiceVoucherAll($order_id);
            $service_voucher_count = D('SpTicket')->getServiceCreateVoucherCount();
            if($service_voucher_count > 0){
                if($service_tickets['count'] > 0){
                    foreach ($service_tickets['lists'] as $key => $value) {
                        if($value['show_type'] != 'error'){
                            $voucher[$key]	=	$value['reduce_cost'];
                        }
                    }
                    if($voucher){
                        $max_voucher['max_voucher_cost']	=	number_format(max($voucher), 2);
                        $max_voucher_key					=	array_search($max_voucher['max_voucher_cost'], $voucher);
                        $max_voucher['max_voucher_key']		=	$max_voucher_key + 1;
                        $max_voucher['max_voucher_ticket_id']	=	$service_tickets['lists'][$max_voucher_key]['id'];
                        $this->assign('max_voucher',$max_voucher);
                    }
                }
                $service_tickets['usable_ticket'] = count($voucher) ? count($voucher) : 0 ;
                $this->assign('voucher_tickets',$service_tickets);
                $this->assign('show_voucher',true);
            }
            //NEQ JULY 26 ,2018 WxPayOpen
            $wx_config = getWxConfigData();
            $this->wxpay_open = $wx_config['wxpay_open'];
            $this->ofpay_open = getComStoreData('pay_status');
            //NEW Jan 2 ,2018 Assign platform_message
            $cskx_platform_message = get_cskx_platform_message();
            $this->assign('cskx_platform_message', $cskx_platform_message);
            $this->assign('order',$order_data);
            $this->display();
        }else{
            //判断是否有存在临时表里面
            $is_temp	=	$OrderModal->getOrderTemporaryData($postdata['order_id']);
            //获取数据
            $pay_message['payment_money']			=	$postdata['price'];//实际付款金额
            $pay_message['service_voucher_cash']			=	$postdata['voucher']?$postdata['voucher']:0;//优惠红包金额
//            $pay_message['service_voucher_cash']	=	$postdata['service_voucher']?$postdata['service_voucher']:0;//优惠代金券金额
//            $pay_message['service_ticket_id']		=	$postdata['service_ticket_id']?$postdata['service_ticket_id']:'';//优惠红包id
            $pay_message['service_ticket_id']		=	$postdata['voucher_ticket_id']?$postdata['voucher_ticket_id']:'';//优惠代金券id
            //判断是否失效
            if (!empty($pay_message['service_ticket_id'])) {
                $one_ticket = D('SpTicket')->getOneTicket($pay_message['service_ticket_id'],'a.is_over');
                if($one_ticket['is_over'] == 2) {
                    $this->ajaxReturn(array('error'=>"1","message"=>"该优惠卷已被商家停用，如有疑问，请与商家联系","url"=>''));
                    exit;
                }
            }
            if($is_temp){
                $OrderModal->setOrderTemporaryDataSave($pay_message,$is_temp['id']);
            }else{
                $pay_message['order_id']			=	$postdata['order_id'];
                $OrderModal->setOrderTemporaryDataAdd($pay_message);
            }
            $order = $OrderModal->where("id=".$postdata['order_id'])->find();
            if(floatval($order['real_cash']) != floatval($postdata['real_cash'])){
                $this->ajaxReturn(array('error'=>"1","message"=>"价格已改变,请刷新价格重试","url"=>''));
                exit;
            }
            //判断支付类型
            if ($postdata['pay_type'] === 'weixin') {
                //weixin支付 --------44444444444
                $pay_data=[
                    'payment_money'=>$pay_message['payment_money'],
                    'order_sn'=>$order['order_sn'],
                    'orderId'=>$postdata['order_id']
                ];
                echo json_encode(array("error" => "0", "message" => "等待跳转", "url" => "/WeChatPay/orderPay/id/" . $postdata['order_id'],'pay_data'=>$pay_data));
                exit();
            } elseif($postdata['pay_type'] === 'unline'){
                if($postdata['pic'] == '' && empty($postdata['pic']) && trim($postdata['remark']) == '' && stripos($postdata['pic'],'/uploads') === false){
                    $this->ajaxReturn(array('error'=>"1","message"=>"线下转账提交失败,请上传截图","url"=>''));
                    exit;
                }

                $this->ajaxReturn($OrderModal->setProductUserPayingUnlink($order,$postdata));
            }
        }
    }
    public function sellAction(){
        $title = "订单管理";
        $this->assign('title', $title);
        $this->_assign_base_data_get_screen();
        $postdata = I('post.');
        if ($postdata) {
            $page_size = I("post.rows");
            $paging = I("post.page");
            $order		=	$postdata['order'];//类型
            $title      =   $postdata['title'];
            $where['o.branch_id']	=	getBrowseBranchId();
            $where['o.user_id']	    =	array('gt',0);
            //1 待付款 2 服务中 3 待验收 4 已完成 5已关闭
            if(!empty($order) && $order != ''){
                switch ($order) {
                    case '1':
                        $where['o.order_state']		=	ORDER_STATE_USER_BUY;
                        $where['o.surety_state']	=   ORDER_DONT_PAY;
                        break;
                    case '2':
                        $where['_string']			=	'o.order_state ='.ORDER_STATE_SERVICE.' or o.order_state = '.ORDER_STATE_APPLY_CLOSE;
                        break;
                    case '3':
                        $where['o.order_state']		=	ORDER_STATE_WAITING_CHECK;
                        break;
                    case '4':
                        $where['o.order_state']		=	array('in',array(ORDER_STATE_FINISH,ORDER_STATE_WAITING_JUDGE,ORDER_STATE_HAS_JUDGE));
                        break;
                    case '5':
                        $where['o.order_state']		=	ORDER_STATE_CLOSED;
                        break;
                    default:

                        break;
                }
            }
            if(!empty($title) && $title != ''){
                $where['o.product_title'] = array('like',"%".$title."%");
            }
            $count	=	D("ComOrder")
                            ->setDacFilter('o')
                            ->where($where)
                            ->count();
            $order_data	=	D("ComOrder")
                            ->setDacFilter('o')
                            ->field("o.product_title,o.product_category,o.order_state,o.payment_money,o.id as order_id,if(o.order_state > 6 , o.on_time - 10000000,o.on_time ) as order_time,o.order_sn,o.contacts,o.surety_state,o.trade_type,o.on_time AS order_on_time,o.real_cash as order_real_cash")
                            ->where($where)
                            ->order('o.on_time desc')
                            ->page($paging, $page_size)
                            ->select();
            $order_list['total_count']	=	$count;
            $order_list['total'] = count($order_data);
            foreach ($order_data as $item) {
                if ($item['price_type'] == 1) {
                    $price_type = '价格面议';
                    $picediv	= $item['order_real_cash'].'元 ' ;
                } else {
                    $price_type = $item['real_cash'] . '元';
                    $picediv	= $item['order_real_cash'].'元' ;
                }
                if(in_array($item['order_state'], array(ORDER_STATE_FINISH,ORDER_STATE_WAITING_JUDGE,ORDER_STATE_HAS_JUDGE))){
                    $state_color    =   'complete';
                }elseif(in_array($item['order_state'], array(ORDER_STATE_CLOSED))){
                    $state_color    =   'order-close';
                }else{
                    $state_color    =   'wait-pay';
                }
                $order_list['rows'][] = array(
                    'order_id' => $item['order_id'],
                    'on_time' => date('Y.m.d H:i', $item['order_on_time']),
                    'order_title' => $item['product_title'],
                    'price_type' => $price_type,
                    'category' => $item['product_category'],
                    'trade_type'=>'线上交易' ,
                    'contacts' => $item['contacts'],
                    'order_sn' => $item['order_sn'],
                    'picediv'  => $picediv >0 ? $picediv : "面议",
                    'state_color'=>$state_color,
                    'payment_money'=>$item['payment_money'],
                    'surety_state'	=>$item['surety_state'],
                    'order_stateing' => order_stateing($item),
                    'total_count'  => $count
                );
            }
            echo json_encode($order_list);
            exit();
        }
        $this->display();
    }
    public function sellDetailAction(){
        $this->assign('title',getComStoreData('name'));
        $order_id	=	I("get.id");
        $OrderModal	=	D("ComOrder");
        $ReportModal=	D("SysReport");
        $RefundModel=   D('ComRefund');
        $CommentModal=	D("ComComment");
        $is_existence = $OrderModal -> OrderExistence($order_id);
        if(!$is_existence) {
            $this->error("该订单已被删除或不存在!!",'/Order/sell');
        }
        $is_service_owner	=	$OrderModal->getIsProductOwner($order_id);
        if(!$is_service_owner){
            $this->error("您不具备该服务的操作!!");
        }
        $report_data	=	$ReportModal->getOrderReportList($order_id);
        $order_data		=	$OrderModal->getOrderDetailData($order_id);
        $this->HandlerContactShow(['url'=>'/Liuyan/TaReply/order_id/'.$order_id.'.html','tel'=>$order_data['contacts_tel']]);
        $order_data['view_state']	=	order_stateing($order_data);
        if($order_data['surety_state'] == ORDER_DONT_PAY && $order_data['order_state'] == ORDER_STATE_USER_BUY){
            $order_data['view_state']	=	"付款中";
            $condition_str = 'order_sn   = \''.$order_data['order_sn'].'\' and ';
            $condition_str.= 'money_type = '.FIN_ORDER_LINE_PAY.' and ';
            $condition_str.= 'Source     = '.FIN_PAY_OFFLINE.' and ';
            $condition_str.= 'user_id    = '.$order_data['user_id'] .' and ';
            $condition_str.= '(pay_status = 0 or pay_status = 2)';
            $res = M('ComRecharge')->where($condition_str)->order('ctime desc')->find();
            if($res){
//                $order_data['view_state']	=	 ($res['pay_status'] == 2) ?
//                    '待付款,线下转账凭据,后台审核失败!':
//                    '已付款等待商家确认!';
                if($res['pay_status'] == 0){
                    $this->assign('is_unlink',1);
                }
            }
        }elseif($order_data['surety_state'] == ORDER_PAY && $order_data['order_state'] == ORDER_STATE_USER_BUY){
            $order_data['view_state']	=	"客户已付款,请开始服务";
        }elseif($order_data['surety_state'] == ORDER_PAY && in_array($order_data['order_state'],array(ORDER_STATE_SERVICE,ORDER_STATE_WAITING_CHECK)) && $order_data['refund_state'] > ORDER_REFUND_STATE_NO){
            $refund = $RefundModel->getRefundDetail($order_data['order_id'],true);
            if($order_data['refund_state'] == ORDER_REFUND_STATE_BEGIN){
                $order_data["view_state"] = "对方已申请退款，待回复...";
            }elseif($order_data['refund_state'] == ORDER_REFUND_STATE_FAIL){
                $order_data["view_state"] = "不同意退款申请，处理中...";
            }
            $this->assign('refund',$refund);
        }elseif($order_data['order_state'] == ORDER_STATE_CLOSED && $order_data['refund_state'] == ORDER_REFUND_STATE_COMPLETE){
            $refund = $RefundModel->getRefundDetail($order_data['order_id'],true);
            $order_data["view_state"] = '申请退款处理成功，交易关闭';
            $this->assign('refund',$refund);
        }elseif($order_data['order_state'] == ORDER_STATE_CLOSED && $order_data['refund_state'] == ORDER_REFUND_STATE_NO){
//            $refund = $RefundModel->getOrderRefundDetail($order_data['order_id'],true);
            $order_data["view_state"] = '已关闭';
//            $this->assign('refund',$refund);

        }elseif($order_data['order_state'] == ORDER_STATE_HAS_JUDGE){
            $comments	=	$CommentModal->getOrderComment($order_id);
            $star		=	returnViewStar($comments['star']);
            $this->assign('comment',$comments['content']);
            $this->assign('star',$star);
        }
        if(in_array($order_data['order_state'],array(ORDER_STATE_WAITING_JUDGE,ORDER_STATE_CLOSED,ORDER_STATE_HAS_JUDGE))){
            $this->is_over = 1;
        }
        $voucher_tickets = D("ComOrder")->getProductVoucherDetail(I("get."),'order',1);
        $this->ticket = $voucher_tickets;
        $this->_attribute_view($order_data['product_attribute']);
        $this->assign('report',$report_data);
        $this->assign('order',$order_data);
//		var_dump($order_data);die;
        $this->display();
    }
    /*
     *提醒收款
     */
    public function remindUnlineAction(){
        if (IS_POST){
            $order_id =  I('post.order_id');
            $result  =  D('ComOrder')->setremindUnline($order_id);
            $this->ajaxReturn($result);
        }
    }
    protected function HandlerContactShow($data){
        $this->store_liuyan = $data['url'];
        $this->store_tel = $data['tel'];
    }
    /*
     * 完成收款 或 取消收款
     */
    public function operationUnlineAction(){
        if (IS_POST){
            $order_id =  I('post.order_id');
            $type =  I('post.type');
            $remark = $type == 1 ? '商家确认收款' : '商家取消收款';
            $result  =  D('ComOrder')->operationUnline($order_id,$type,$remark);
            $this->ajaxReturn($result);
        }
    }
    /**
     * 提交退款
     * @param post order_id
     * @param post user_cash 退款金额
     * @param post refund_comment 退款原因
     * @param post attach 附件 array
     * @NEW Jan 10,2018
     */
    public function releaseRefundAction(){
        if(IS_POST){
            $postdata = I('post.');
            $postdata['attach'] = I('post.attach',[]);
            $order_id  = $postdata['order_id'];
            //判断是否可以提交退款  在服务中和待验收下可以,并且没有提交过退款
            $order    = D('ComOrder')->getOrderDetailData($order_id);
            if($order['trade_type'] == 1){
                $this->ajaxReturn(array('error'=>1,'message'=>'您的需求是线下结算不能进行退款!!'));
                exit;
            }
            if(in_array($order['order_state'],array(ORDER_STATE_WAITING_CHECK,ORDER_STATE_SERVICE)) && !$order['refund_state']){
                if(!$postdata['user_cash']){
                    $this->ajaxReturn(array('error'=>1,'message'=>'退款金额不能为0!!'));
                    exit;
                }
                if(!trimall($postdata['refund_comment'])){
                    $this->ajaxReturn(array('error'=>1,'message'=>'退款原因不能为空!!'));
                    exit;
                }
                if ($_SESSION['user_id'] != $order['user_id']) {
                    $this->ajaxReturn(array('error'=>1,'message'=>'您没有权限!!'));
                    exit;
                }
                $condition['user_cash']      = $postdata['user_cash'];
                $condition['refund_comment'] = trimall($postdata['refund_comment']);
                $condition['order_id']       = $order_id;
                $condition['branch_id']      = getBrowseBranchId();
                $condition['on_time']        = time();
                $condition['update_time']    = time();
                //检查附件
                if($postdata['attach']){
                    foreach ($postdata['attach'] as $key => $value) {
                        if($key < 5){
                            $attrch_number = $key + 1;
                            $condition['attach_'.$attrch_number] = $value;
                        }
                    }
                }
                //判断服务商是否有获取金额
                if($order['payment_money'] > $postdata['user_cash']){
                    //获取平台佣金比率
                    $diff_cash                  = $order['payment_money'] - $postdata['user_cash'];
                    $condition['service_cash']  = $diff_cash;
                }
                //添加退款信息
                $result = M('ComRefund')->add($condition);
                if($result){
                    //修改需求表的refund_state字段
                    $save['refund_state']   = ORDER_REFUND_STATE_BEGIN;
                    $save['id']       = $order_id;
                    $save['update_time']    = time();
                    M('ComOrder')->save($save);
                    //发送消息
                    D('ComOrder')->sendWXOrderReleaseRefund($order);//微信 发送通知服务商
                    D('ComComment')->sendSystemMessageFromOrderReleaseRefund($order);//系统消息 发送通知服务商
                    $report_table = M('SysReport');
                    $data['order_id']    = $order['order_id'];
                    $data['user_id']     = $_SESSION['user_id'];
                    $data['report_time'] = time();
                    $data['report_desc'] = '提交申请退款,等待对方确认';
                    $data['report_service_desc'] = '客户申请退款,等待确认';
                    $report_table->data($data)->add();
                    $this->ajaxReturn(array('error'=>0,'message'=>'退款申请已提交!!'));
                    exit;
                }else{
                    $this->ajaxReturn(array('error'=>1,'message'=>'操作失败!!'));
                    exit;
                }
            }else{
                if($order['refund_state'] > 0){
                    $this->ajaxReturn(array('error'=>1,'message'=>'您已经提交过退款了!!'));
                }elseif(!in_array($order['task_state'],array(ORDER_STATE_WAITING_CHECK,ORDER_STATE_SERVICE))){
                    $this->ajaxReturn(array('error'=>1,'message'=>'该需求的状态不能提交退款!!'));
                }else{
                    $this->ajaxReturn(array('error'=>1,'message'=>'操作失败!!'));
                }
                exit;
            }
        }
    }
    //同意退款
    public function agreeRefundAction(){
        $postdata= I('post.');
        $order_id = $postdata['order_id'];
        $condition['o.id']       = $order_id;
        $condition['o.branch_id'] =getBrowseBranchId();
        if(D('ComOrder')->getIsProductOwner()){
            $order    = M('ComOrder')
                            ->alias('o')
                            ->field('o.*,o.id as order_id,o.product_title,refund.id')
                            ->join('com_refund refund on refund.order_id = o.id')
                            ->where($condition)
                            ->find();
            if($order){
                if($order['refund_state'] == ORDER_REFUND_STATE_BEGIN){
                    $result = D('ComFinance')->orderRefundConfirm($order);
                    if($result['error'] == 0){
                        //发送消息
                        D('ComOrder')->sendWXOrderCompleteRefund($order,'user');//微信 发送通知
                        D('ComOrder')->sendWXOrderCompleteRefund($order,'service');
                        D('ComComment')->sendSystemMessageFromOrderCompleteRefund($order);//系统消息 发送通知服务商和客户
                        $message = array('error'=>0,'message'=>'操作成功,同意退款成功!!');
                        $this->ajaxReturn($message);
                        exit;
                    }else{
                        $message = array('error'=>1,'message'=>$result['message']);
                        $this->ajaxReturn($message);
                        exit;
                    }
                }else{
                    $message = ($order['refund_state'] == ORDER_REFUND_STATE_COMPLETE) ?
                        array('error'=>1,'message'=>'操作失败,您已经同意退款了,请勿重新操作!!') :
                        array('error'=>1,'message'=>'操作失败,当前状态不能同意退款!!');
                    $this->ajaxReturn($message);
                    exit;
                }
            }
        }else{
            $message = array('error'=>1,'message'=>'操作失败,您没有权限!!');
            $this->ajaxReturn($message);
            exit;
        }
    }
    public function updatePriceAction(){
        if(IS_POST){
            $order_id	=	I("post.order_id");
            $new_price	=	I("post.new_price");
            $OrderModal	=	D("ComOrder");
            //判断是否是自己的服务
            $is_my_product	=	$OrderModal->getIsProductOwner();
            if(!$is_my_product){
                echo json_encode(array('error'=>1,"msg"=>"这不是你的发布的服务,不能修改!"));
                exit;
            }
            //判断是否已经付款
            $is_order_surety=	$OrderModal->getIsOrderSurety($order_id);
            if($is_order_surety){
                echo json_encode(array('error'=>1,"msg"=>"该订单已付款,不能修改!"));
                exit;
            }
            //判断新价格是否为数字
            if(!is_numeric($new_price) or strpos($new_price,'.') === 0){
                echo json_encode(array('error'=>1,"msg"=>"请输入正确格式的价格!"));
                exit;
            }
            //判断是否大于零
            if(floatval($new_price) == floatval(0)){
                echo json_encode(array('error'=>1,"msg"=>"价格不能为零!"));
                exit;
            }
            //判断是否已线下转账
            $is_unline = $OrderModal->handlerIsUnline($order_id);
            if ($is_unline){
                echo json_encode(array('error'=>1,"msg"=>"该订单客户已提交线下转账,不能更改!"));
                exit;
            }
            //开始修改
            $price	=	$OrderModal->setOrderRealCash($order_id,$new_price);
            if($price){
                echo json_encode(array('error'=>0,"msg"=>"修改成功!",'price'=>$price));
                exit;
            }else{
                echo json_encode(array('error'=>1,"msg"=>"修改失败!"));
                exit;
            }
        }
    }
    public function startServiceAction(){
        if(IS_POST){
            $order_id	=	I("post.order_id");
            $OrderModal	=	D("ComOrder");
            //判断是否是自己的服务
            $is_my_product	=	$OrderModal->getIsProductOwner();
            if(!$is_my_product){
                echo json_encode(array('error'=>1,"msg"=>"这不是你的发布的服务,不能开始服务!"));
                exit;
            }
            //判断是否已经付款
            $is_order_surety=	$OrderModal->getIsOrderSurety($order_id);
            if(!$is_order_surety){
                echo json_encode(array('error'=>1,"msg"=>"该订单未付款,不能开始服务!"));
                exit;
            }
            $result	=	$OrderModal->setOrderState($order_id,ORDER_STATE_SERVICE);
            if($result){
                //记录业务进度
                $Report_modal	=	D("SysReport");
                $Comment_modal	=	D("ComComment");
                $report_message['desc'] = '服务商开始服务';
                $report_message['report_service_desc'] = session('user_name').'开始服务';
                $Report_modal->addOrderReport($order_id,'服务商开始服务');
                $OrderModal->sendWXProductServiceNotive($order_id,"start");
                $Comment_modal->sendSystemMessageFromProductServiceNotive($order_id,'start');
                echo json_encode(array('error'=>0,"msg"=>"开始服务成功!",'url'=>"/Order/sellDetail/id/". $order_id.".html"));
                exit;
            }else{
                echo json_encode(array('error'=>1,"msg"=>"操作失败!"));
                exit;
            }
        }
    }
    //订单进度报表
    public function orderProgressAction(){
        if(IS_GET){
            $this->assign('title','报告业务进度');
            $order_id	=	I("get.id");
            $OrderModal	=	D("ComOrder");
            $is_service_owner	=	$OrderModal->getIsProductOwner();
            if(!$is_service_owner){
                $this->error("您不具备该服务的操作!!");
            }
            //获取该活动的事项进度
            $step_data	=	$OrderModal->getOrderStepData($order_id);
            //对事项进度进行处理
            if(!empty($step_data['step_flow']) && $step_data['is_step']){
                $step_arr	=	explode('&,', $step_data['step_flow']);
                $step['count'] = count($step_arr);
                foreach ($step_arr as $key=>$val){
                    $step['content'][] = ['value'=>$val,'text'=>$val];
                }
                $step['content'] = json_encode($step['content']);
                $this->assign('speed',$step);
            }

            $order_data	=	$OrderModal->getOrderDetailData($order_id);
            $order_data['view_state']	=	order_stateing($order_data);
            $time		=	date('Y.m.d',time());
            $reports = M('SysReport') -> where(array('order_id' => $order_id)) -> order('report_time desc') -> select();
            foreach ($reports as $key => $value) {
                $reports[$key]["report_desc"] = empty($value["report_service_desc"])?$value["report_desc"]:$value["report_service_desc"];
            }
            $this->assign('reports', $reports);
            $this->assign('time',$time);
            $this->assign('order',$order_data);
            $this->display();
        }else{
            $postdata	=	I("post.");
            $condition  = [];
            if(isset($postdata['img_file']) && count($postdata['img_file']) > 0){
                foreach($postdata['img_file'] as $key => $val){
                    $base64_image_content = $val;
                    if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
                        $type = $result[2];
                        $new_file = "/uploads/schedule/" . time() . $key . ".{$type}";
                        if (file_put_contents('.'.$new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))) {
                            $condition['pic'.$key] = $new_file;
                        }else{
                            echo json_encode(array("error" => "1", "msg" => "保存失败!"));
                            exit();
                        }

                    }
                }
            }
            if(isset($postdata['enc_file']) && count($postdata['enc_file']) > 0){
                foreach($postdata['enc_file'] as $key => $val){
                    $base64_image_content = $val;
                    $test = strpos($base64_image_content,'data:');
                    if (strpos($base64_image_content,'data:') !== false){
                        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
                            $type = $result[2];
                            $new_file = "/uploads/schedule/" . (time() + 1) . $key . ".{$type}";
                            if (file_put_contents('.'.$new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))) {
                                $condition['enc'.$key] = $new_file;
                            }else{
                                echo json_encode(array("error" => "1", "msg" => "保存失败!"));
                                exit();
                            }

                        }
                    }else{
                        $condition['enc'.$key] = $val;
                    }
                }
            }
            $condition['desc']= '商家上报进度';
            $condition['report_service_desc']= session('user_name').'上报进度';
            $condition['title']= $postdata['title'];
            $condition['topic']= D('ComProduct')->getIsCustomTitle($postdata) ? REPORT_TOPIC_FLOWED_RESC : REPORT_TOPIC_FLOWED ;
            $condition['remark']= $postdata['order_desc'];
            $result = D("SysReport")->addOrderReport($postdata['order_id'],$condition);
            if($result){
                //topics-0010
                D('ComOrder')->sendWXUserReport($postdata['order_id'],1);
                D('ComComment')->sendSysUserReport($postdata['order_id'],1);
                echo json_encode(array('error'=>0,"msg"=>"进度提交成功!"));
                exit;
            }else{
                echo json_encode(array('error'=>1,"msg"=>"进度提交失败!"));
                exit;
            }
        }
    }

    public function promptingOrderAction(){
        if(IS_POST){
            $order_id =  I('post.order_id');
            $result  =  D('ComOrder')->setPromptingOrder($order_id);
            if($result){
                echo json_encode(array('error'=>0));
                exit;
            }else{
                echo json_encode(array('error'=>1));
                exit;
            }
        }
    }
    //查看线下付款
    public function unlineInfoAction(){
        $title = "线下转账详情";
        $this->assign('title',$title);
        $unline_id = I('get.id');
        $result = D('ComOrder')->getUnlineInfo($unline_id);
        if($result){
            $this->assign('unline',$result);
            $this->display();
        }else{
            $this->error('您没有访问权限');
        }
    }
    //用户提交附件
    public function enclosureAction(){
        if (IS_GET){
            $order_id = I('get.id');
            $imp = I('get.type',0,'int');
            if($imp != 3){
                $isOwn = D('ComOrder')->getIsOrderOwner($order_id);
                //判断是否符合规则
                if(!$isOwn){
                    $this->error('您没有访问权限');
                }
            }else{
                $isService = D('ComOrder')->getIsProductOwner();
                //判断是否符合规则
                if(!$isService){
                    $this->error('您没有访问权限');
                }
            }
            switch ($imp){
                case 0 :
                    $title = '上传附件';
                    $desc = '附件说明';
                    $desc1 = '附件说明';
                    break;
                case 1:
                    $title = '申请结束订单';
                    $desc = '申请原因';
                    $desc1 = '申请结束订单说明';
                    break;
                case 2 :
                    $title = '延迟验收';
                    $desc = '申请原因';
                    $desc1 = '申请延迟验收说明';
                    break;
                case 3:
                    $title = '拒绝结束订单';
                    $desc = '拒绝原因';
                    $desc1 = '拒绝结束订单说明';
                    break;
            }
            $this->assign('desc',$desc);
            $this->assign('desc1',$desc1);
            $this->assign('imp',$imp);
            $this->assign('title',$title);
            $this->assign('order_id',$order_id);
            $this->display();
        }else{
            $postdata	=	I("post.");
            //判断状态是否正确
            $state = D('ComOrder')->where('id = '.$postdata['order_id'])->getField('order_state');
            if(!in_array($state,[ORDER_STATE_SERVICE,ORDER_STATE_WAITING_CHECK]) && $postdata['imp'] == 1){
                echo json_encode(array('error'=>1,"msg"=>"提交失败,当前状态不能申请取消订单!"));
                exit;
            }
            if($state != ORDER_STATE_WAITING_CHECK && $postdata['imp'] == 2){
                echo json_encode(array('error'=>1,"msg"=>"提交失败,当前状态不能延迟验收!"));
                exit;
            }
            $is_author = D('ComOrder')->getIsProductOwner();
            if(!$is_author && $postdata['imp'] == 3){
                echo json_encode(array('error'=>1,"msg"=>"提交失败,您没有该权限!"));
                exit;
            }
            //判断状态是否正确
            $state = D('ComOrder')->where('id = '.$postdata['order_id'])->getField('order_state');
            if ($state != ORDER_STATE_APPLY_CLOSE && $postdata['imp'] == 3){
                echo json_encode(array('error'=>1,"msg"=>"提交失败,当前状态不能进行次操作!"));
                exit;
            }
            $condition  = [];
            if(isset($postdata['img_file']) && count($postdata['img_file']) > 0){
                foreach($postdata['img_file'] as $key => $val){
                    $base64_image_content = $val;
                    if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
                        $type = $result[2];
                        $new_file = "/uploads/schedule/" . time() . $key . ".{$type}";
                        if (file_put_contents('.'.$new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))) {
                            $condition['pic'.$key] = $new_file;
                        }else{
                            echo json_encode(array("error" => "1", "msg" => "保存失败!"));
                            exit();
                        }

                    }
                }
            }
            if(isset($postdata['enc_file']) && count($postdata['enc_file']) > 0){
                foreach($postdata['enc_file'] as $key => $val){
                    $base64_image_content = $val;
                    $test = strpos($base64_image_content,'data:');
                    if (strpos($base64_image_content,'data:') !== false){
                        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
                            $type = $result[2];
                            $new_file = "/uploads/schedule/" . (time() + 1) . $key . ".{$type}";
                            if (file_put_contents('.'.$new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))) {
                                $condition['enc'.$key] = $new_file;
                            }else{
                                echo json_encode(array("error" => "1", "msg" => "保存失败!"));
                                exit();
                            }

                        }
                    }else{
                        $condition['enc'.$key] = $val;
                    }
                }
            }
            $condition['remark']= $postdata['order_desc'];
            switch ($postdata['imp']){
                case 0 :
                    $condition['desc'] = '客户上传附件';
                    $mes_title = '上传附件';
                    $res = true;
                    break;
                case 1:
                    $condition['desc'] = '客户申请结束订单';
                    $mes_title = '申请结束订单';
                    $res = D('ComOrder')->setOrderState($postdata['order_id'],ORDER_STATE_APPLY_CLOSE);
                    break;
                case 2 :
                    $condition['desc'] = '客户延迟验收转为服务中';
                    $mes_title = '延迟验收';
                    $order_condition['id']   = $postdata['order_id'];
                    $order_condition['order_state'] = ORDER_STATE_SERVICE;
                    $order_condition['update_time'] = time();
                    $order_condition['delay_inspect']= 1;
                    $res = M('ComOrder')->save($order_condition);
                    break;
                case 3 :
                    //拒绝取消订单,返回至服务中状态
                    $res =  D('ComOrder')->setOrderState($postdata['order_id'],ORDER_STATE_SERVICE);
                    $condition['desc'] = '商户拒绝结束订单';
                    $condition['report_service_desc'] = $_SESSION['user_name'].'拒绝结束订单';
                    $mes_title = '拒绝结束订单';
                    break;
                default:
                    $condition['desc'] = '客户上传附件';
                    $mes_title = '上传附件';
                    $res = true;
                    break;
            }
            //修改订单状态
            if($res){
                $result = D("SysReport")->addOrderReport($postdata['order_id'],$condition);
                if($result){
                    if($postdata['imp'] == 1 ){
                        $timer = 72 * 60 * 60;
                        D('ESAdmin/SysMq')->add_timer($timer,WEB_ROOT.'/ReqQueue/HandleUserApplyClose/id/'.$postdata['order_id'].'/second/1.html');
                        //topics-0011
                        D('ComOrder')->sendWXUserCloseOrder($postdata['order_id']);
                        D('ComComment')->sendSysUserCloseOrder($postdata['order_id']);
                    }elseif($postdata['imp'] == 2){
                        //topics-0014
                        D('ComOrder')->sendWXDelayInspect($postdata['order_id']);
                        D('ComComment')->sendSysDelayInspect($postdata['order_id']);
                    }elseif($postdata['imp'] == 3){
                        //topics-0012
                        D('ComOrder')->sendWXOrderCloseHandler($postdata['order_id'],'refuse');
                        D('ComComment')->sendSysOrderCloseHandler($postdata['order_id'],'refuse');
                    }else{
                        //topics-0010
                        D('ComOrder')->sendWXUserReport($postdata['order_id'],0);
                        D('ComComment')->sendSysUserReport($postdata['order_id'],0);
                    }
                    echo json_encode(array('error'=>0,"msg"=>$mes_title."提交成功!","imp"=>$postdata['imp']));
                    exit;
                }else{
                    echo json_encode(array('error'=>1,"msg"=>$mes_title."提交失败!"));
                    exit;
                }
            }else{
                echo json_encode(array('error'=>1,"msg"=>$mes_title."提交失败!"));
                exit;
            }

        }
    }
    //商家对用户结束订单的操作
    public function closeHandlerAction(){
        $postdata = I('post.');
        $is_author = D('ComOrder')->getIsProductOwner();
        if(!$is_author){
            echo json_encode(array('error'=>1,"msg"=>"提交失败,您没有该权限!"));
            exit;
        }
        //判断状态是否正确
        $state = D('ComOrder')->where('id = '.$postdata['order_id'])->getField('order_state');
        if ($state != ORDER_STATE_APPLY_CLOSE){
            echo json_encode(array('error'=>1,"msg"=>"提交失败,当前状态不能进行次操作!"));
            exit;
        }
        //处理操作 refuse 拒绝取消订单 agree 同意取消订单
        if($postdata['type'] == 'refuse'){
            //拒绝取消订单,返回至服务中状态
            $data['order_state']		=	ORDER_STATE_SERVICE;
            $data['update_time']        =   time();
            $data['finish_time']        =   time();
            $where['id']	            =	$postdata['order_id'];
            $res	=	M('ComOrder')->data($data)->where($where)->save();
           if($res){
               $report['desc'] = '商家拒绝结束订单转为服务中';
               $report['report_service_desc'] = $_SESSION['user_name'].'拒绝结束订单转为服务中';
                //记录流程 - 商家拒绝结束订单转为服务中
               D('SysReport')->addOrderReport($postdata['order_id'],$report);
                //topics-0012
               D('ComOrder')->sendWXOrderCloseHandler($postdata['order_id'],'refuse');
               D('ComComment')->sendSysOrderCloseHandler($postdata['order_id'],'refuse');
               echo json_encode(array('error'=>0,"msg"=>"提交完成!"));
               exit;
           }else{
               echo json_encode(array('error'=>1,"msg"=>"提交失败!"));
               exit;
           }
        }else{
            $OrderModel = D('ComOrder');
            $FinanceModel = D('ComFinance');
            $order_data	                =	$OrderModel->getOrderDetailData($postdata['order_id']);//订单表信息
            $order_data['branch_id']	=	getBrowseBranchId();//服务所属的公司id
            $result		                =	$FinanceModel->orderPayConfirm($order_data,'商家同意结束订单,订单已完成');//
            if($result['code'] == 0) {
                //记录业务进度
                $report_table = D('SysReport');
                $report_message['desc'] = '商家同意结束订单';
                $report_message['report_service_desc'] = $_SESSION['user_name'].'同意结束订单';
                $report_table->addOrderReport($postdata['order_id'], $report_message);
                //通知topics-0016
                D('ComOrder')->sendWXOrderCloseHandler($postdata['order_id'],'agree');
                D('ComComment')->sendSysOrderCloseHandler($postdata['order_id'],'agree');
                echo json_encode(array('error'=>0,"msg"=>"提交完成!"));
                exit;
            }else{
                echo json_encode(array('error'=>1,"msg"=>"提交失败!"));
                exit;
            }
        }
    }
    public function reportInfoAction(){

        $title = "业务进度报告详情";
		$this -> assign('title', $title);
		$report_id = I('get.id');
		$report = M('SysReport') -> where(array('report_id' => $report_id)) -> find();
        $report['view_time'] = date('Y-m-d H:i',$report['report_time']);
        $order  = M('ComOrder') ->alias('co')
                               ->field('co.product_title,co.contacts')
                               ->where('co.id = '.$report['order_id'])->find();
        $report['title'] = $order['product_title'];
        //报告人
        $user = M('SysUser')->field('user_type,name')->where('id = '.$report['user_id'])->find();
        $report['author'] = $user['user_type'] == USER_TYPE_COMPANY_MANAGER ? $user['name'].'|商家' : $order['contacts'].'|用户';
        //解析附件
        if($report['enc0']){
            for ($i = 0;$i<5;$i++){
                if($report['enc'.$i] && trim(strlen($report['enc'.$i])) > 0 && !is_null($report['enc'.$i])){
                    $res = getAskUploadFileImages($report['enc'.$i],'ask-',true);

                    $report['encs'][] = ['pic'=>IMG_URL.'Index_img/ask-'.$res['type'].'.png',
                                         'url'=>$report['enc'.$i]
                                        ];
                }
            }
        }
        //详情标题
        if (strpos($report['report_desc'],'拒绝结束订单') !== false) {
            $report['tip_desc'] = '拒绝原因';
        } else if (strpos($report['report_desc'],'上传附件') !== false) {
            $report['tip_desc'] = '附件说明';
        } else if (strpos($report['report_desc'],'上报进度') !== false) {
            $report['tip_desc'] = '进度说明';
        } else if (strpos($report['report_desc'],'取消订单') !== false) {
            $report['tip_desc'] = '取消原因';
        } else if (strpos($report['report_desc'],'申请结束订单') !== false) {
            $report['tip_desc'] = '申请原因';
        }else {
            $report['tip_desc'] = '详情';
        }
        $report['order_url'] = session('user_type') == USER_TYPE_COMPANY_MANAGER ?
                                    '/Order/sellDetail/id/'.$report['order_id'].'.html' :
                                    '/Order/serviceDetail/id/'.$report['order_id'].'.html';
		$this -> assign('report', $report);
		$this -> display();
    }
    public function overStepAction(){
        if(IS_POST){
            $order_id	=	I("post.order_id");
            $OrderModal	=	D("ComOrder");
            $ReportModal=	D("SysReport");
            //判断是否是自己的服务
            $is_my_product	=	$OrderModal->getIsProductOwner();
            if(!$is_my_product){
                echo json_encode(array('error'=>1,"msg"=>"这不是你的发布的服务,不能完成提交!"));
                exit;
            }
            if ($OrderModal->getOrderState($order_id) != ORDER_STATE_SERVICE){
                echo json_encode(array('error'=>1,"msg"=>"当前状态不是服务中!"));
                exit;
            }
            //修改订单状态
            $OrderModal->setOrderState($order_id,ORDER_STATE_WAITING_CHECK);
            //事项进度添加
            $report_message['desc'] = '服务商完成任务';
            $report_message['title'] = '待客户验收';
            $report_message['report_service_desc'] = $_SESSION['user_name'].'完成任务';
            $ReportModal->addOrderReport($order_id,$report_message);
            //Topics-0013
            $timer = 24 * 60 * 60;
            D('ESAdmin/SysMq')->add_timer($timer,WEB_ROOT.'/ReqQueue/HandlerBranchCompleteOrder/id/'.$order_id.'/second/1.html');
            //Topics-1005
            D("ComOrder")->sendWXCheckFinishOrder($order_id);
            D("ComComment")->sendSysCheckFinishOrder($order_id);
//            D("ComOrder")->sendWXWaitingForCheckMessageProduct($order_id); //订单完成待验收，微信通知
//            D("ComComment")->sendSystemMessageFromServiceCheckFinishProduct($order_id);
            echo json_encode(array('error'=>0,"msg"=>"任务完成!"));
            exit;
        }
    }
    /**
     * 延迟验收
     * @param post task_id
     * @NEW Jan 17,2018
     */
    public function delayInspectAction(){
        $order_id = I("get.order_id");
        if($order_id > 0){
            $order = M('ComOrder')->where('id = '.$order_id)->find();
            if($order['order_state'] == ORDER_STATE_WAITING_CHECK){
                if($order['delay_inspect'] == 1){
                    $this->error("您最多只能延迟一次该需求!", '/Order/serviceDetail/id/'.$order_id.'.html');
                    exit;
                }
                $condition['id']   = $order_id;
                $condition['order_state']= ORDER_STATE_SERVICE;
                $condition['update_time']  = time();
                $condition['delay_inspect']= 1;
                M('ComOrder')->save($condition);
                //发送消息
                D('ComOrder')->sendDelayInspectOrder($order_id);
                $this->success("延迟成功", '/Order/serviceDetail/id/'.$order_id.'.html');
            }else{
                $this->error("操作错误", '/Order/serviceDetail/id/'.$order_id.'.html');
            }
        }else{
            $this->error("操作错误", '/index.php/Order.html');
        }
    }
    //订单评价
    public function writeCommentsAction(){
        if(IS_POST){
            $postdata	=	I("post.");
            $order_id	=	$postdata['order_id'];
            $OrderModel	=	D("ComOrder");
            $is_order	=	$OrderModel->getIsOrderOwner($order_id);
            if(!$is_order){
                echo json_encode(array('error'=>1,'msg'=>'您不能对此订单进行此操作!'));
                exit;
            }
            $data['branch_id']  = getBrowseBranchId();
            $data['obj_id']     = $order_id;
            $data['origin_id']  = $_SESSION['user_id'];
            $data['user_id']    = $_SESSION['user_id'];
            $data['star']       = $postdata['star'];
            $data['content']    = $postdata['comments'];
            $data['comment_time'] = time();
            $data['obj_type']   = COMMENTS_OBJERT_TYPE_ORDER;
            $result = M('ComComment')->data($data)->add();
            //修改服务订单状态
            $OrderModel->setOrderState($order_id,ORDER_STATE_HAS_JUDGE);
            //记录业务进度
//            $report_table = D('SysReport');
//            $data['order_id'] = $order_id;
//            $data['user_id'] = $_SESSION['user_id'];
//            $data['report_time'] = time();
//            $data['report_desc'] = '已评价，交易结束';
//            $data['report_service_desc'] = '对方已评价，交易结束';
//            $result = $report_table->data($data)->add();
            echo json_encode(array("error" => "0", "msg" => "评价成功，感谢您对我们的支持!", "url" => ""));
            exit();
        }
    }
    //订单完成
    public function serviceOverAction(){
        if(IS_POST){
            $order_id =	I("post.order_id");
            $OrderModel	=	D("ComOrder");
            $FinanceModel =	D("ComFinance");
            $is_order	=	$OrderModel->getIsServiceOwner($order_id);
            if($is_order){
                $order_data	                =	$OrderModel->getOrderDetailData($order_id);//订单表信息
                $order_data['branch_id']	=	getBrowseBranchId();//服务所属的公司id
                $result		                =	$FinanceModel->orderPayConfirm($order_data);//
                if($result['code'] == 0){
                    //记录业务进度
                    $report_table = D('SysReport');
                    $report_table->addOrderReport($order_id,['客户确认验收','订单结束']);
                    $timer = 72 * 60 * 60;
                    D('ESAdmin/SysMq')->add_timer($timer,WEB_ROOT.'/ReqQueue/HandleSystemFavourableComment/id/'.$order_id);
                    //通知topics-0015
                    D('ComOrder')->sendWXAcceptanceMessage($order_id);
                    D('ComComment')->sendSysAcceptanceMessage($order_id);
//                    D('ComOrder')->sendWXOrderAcceptanceMessage($order_id);
//                    D('ComComment')->sendSystemMessageFromOrderUserCheckFinish($order_data);
                    echo json_encode(array('error'=>0,'msg'=>'订单已确认完成'));
                    exit;
                }else{
                    echo json_encode(array('error'=>1,'msg'=>'操作失败!'));
                    exit;
                }
            }else {
                echo json_encode(array('error'=>1,'msg'=>'您不能对此订单进行此操作!'));
                exit;
            }
        }
    }
    protected function _assign_base_data_get_screen()
    {
        //类型二级选择
        $cate_list = M('ComCategory')->field("id as value,name as text,id,parent_id")->order("level")->cache(true)->select();
        $cate = $this->list_to_tree_get_screen($cate_list, "id", "parent_id", "children");
        $this->assign('category', json_encode($cate));
    }
    /**
     * 把返回的数据集转换成Tree	--	筛选  用于同行名录等等的筛选
     * @param array $list 要转换的数据集
     * @param string $pid parent标记字段
     * @param string $level level标记字段
     * @return array
     * Date 2016-07-24
     */
    function list_to_tree_get_screen($list, $pk = 'id', $pid = 'pid', $child = '_child', $root = 0) {
        // 创建Tree
        $tree = array();
        if (is_array($list)) {
            // 创建基于主键的数组引用
            $refer = array();
            foreach ($list as $key => $data) {
                $refer[$data[$pk]] = & $list[$key];
            }
            foreach ($list as $key => $data) {
                // 判断是否存在parent
                $parentId = $data[$pid]; //$pid = parent_id
                if ($root == $parentId) {
                    $tree[] = & $list[$key]; //获取全部的一级地区
                    $parent = & $list[$key];
                    $childs = array(
                        'value' => $data['value'],
                        'text' => "不限",
                        'id' => $data['id'],
                        'parent_id' => $data['parent_id'],
                        'children' => array(
                            array(
                                'value' => $data['value'],
                                'text' => "不限",
                                'id' => $data['id'],
                                'parent_id' => $data['parent_id'],
                            )
                        )
                    );
                    $parent[$child][] = $childs;
                } else {
                    if (isset($refer[$parentId])) {
                        $parent = & $refer[$parentId];
                        if (empty($parent[$child]) || (count($parent[$child]) < 1)) {
                            $parent[$child][] = array(
                                'value' => $parent['value'],
                                'text' => "不限",
                                'id' => $parent['id'],
                                'parent_id' => $parent['parent_id'],
                            );
                        }
                        $parent[$child][] = & $list[$key];
                    }
                }
            }
        }
        return $tree;
    }
    protected function handlerPermissionsProcessing()
    {
        $this->_permission_name = 'ComOrder';
        parent::handlerPermissionsProcessing();
        switch (ACTION_NAME){
//            case 'index':
            case 'sell':
                $this->_permission_action_name = 'list';
                break;
//            case 'serviceDetail':
            case 'sellDetail':
                $this->_permission_action_name = 'detail';
                break;
        }
    }
}
