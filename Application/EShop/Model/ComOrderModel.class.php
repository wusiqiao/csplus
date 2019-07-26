<?php

namespace EShop\Model;

use PhpParser\Node\Expr\Cast\Object_;
use Think\Exception;
use Think\Model;

class ComOrderModel extends DataModel {
    protected $_MODEL = 'ComOrder';
    protected $_PREFIX= 'com_';
    protected $_TICKET_PREFIX = 'sp_';


    public function getOrderDetailData($order_id){

        $field	= "o.*,o.id as order_id,o.on_time as number_on_time,FROM_UNIXTIME(if(o.finish_time > 0 ,o.finish_time,o.update_time),'%Y-%m-%d %H:%i:%s') as finish_time,o.branch_id,o.tel as contacts_tel,FROM_UNIXTIME(o.on_time,'%Y-%m-%d %H:%i:%s') as order_on_time";
        $result	= M($this->_MODEL)
                        ->alias('o')
                        ->field($field)
                        ->where("o.id =".$order_id)
                        ->find();
        $sql =    " select payment_money  from com_temporary_data where order_id = $order_id ";
        $result1['pay'] = $this->query($sql);
        $sql1 =    " select service_voucher_cash  from com_temporary_data where order_id = $order_id ";
        $result1['voucher'] = $this->query($sql1);
        $result['pay'] = $result1['pay'][0]['payment_money'];
        $result['voucher'] = $result1['voucher'][0]['service_voucher_cash'];
        return $result;
    }
    //判断这个订单是否存在
    public function OrderExistence($order_id){
        $order_data	=	M($this->_MODEL)->where("id = ".$order_id)->find();
        if($order_data){
            return true;
        }else{
            return false;
        }
    }
    //判断这个订单是不是登录用户的
    public function getIsOrderOwner($order_id){
        $order_data	=	M($this->_MODEL)->where("id = ".$order_id)->find();
        if($order_data['user_id']	==	$_SESSION['user_id']){
            return true;
        }else{
            return false;
        }
    }
    //是否已付款
    public function getOrderIsPayCash($order_id){
        $surety_state	=	M($this->_MODEL)->where("id = ".$order_id)->getField("surety_state");
        return ($surety_state == ORDER_PAY)?true:false;
    }
    //取消订单
    public function setOrderClose($order_id,$obj = 'user'){
        $order	=	$this->getOrderData($order_id);
        $ticket_id	=	D("EShop/SpTicket")->setObjertOrderCloseVoucher($order_id);
        if($ticket_id){
            $where['id']	=	array('in',$ticket_id);
            $data['state']	=	TICKET_CARD_STATE_USED;
            $data['object_id']	=	'';
            $data['use_object']	=	'';
            D("EShop/SpTicket")->data($data)->where($where)->save();
        }
        $data['order_state'] =	ORDER_STATE_CLOSED;
        $data['finish_time'] =  time();
        $data['update_time'] =   time();
        $data['close_comments']	= $obj == 'user' ? '取消订单' : '超过72小时未付款系统自动取消订单';
        $data['close_comments_service'] = $obj == 'user' ? '客户取消订单' : '客户超过72小时未付款系统自动取消订单';
        $where['id'] = $order_id;
        $result	=	M($this->_MODEL)->data($data)->where($where)->save();
        return $result;
    }
    //返回可使用的代金券 = 0 /红包券 = 1
    public function getServiceVoucher($order_id,$type = 0){
        $order	=	M($this->_MODEL)
            ->alias('o')
            ->field("o.branch_id,o.real_cash,o.product_id")
            ->where("id = ".$order_id)->find();
        if($order['real_cash'] > 0){
            $time	=	time();
            $where['ts.ticket_begin_date'] 	= array('lt',$time);
            $where['ts.ticket_end_date'] 	= array('egt',$time);
            $where['t.least_cost'] 			= array('elt',$order['real_cash']);
            $where['_string']               = 'a.is_scope = 0 or (a.is_scope = 1 and FIND_IN_SET('.$order['product_id'].',a.scope))';
            $where['a.branch_id']			= $order['branch_id'];
            $where['a.activity_type']	    = ACTIVITY_TYPE_SERVICE;
            $where['ts.mobile']			    = getLoginUserMobile();
            $where['ts.state']				= TICKET_CARD_STATE_GETED;
            $field		=	"ts.id,ts.ticket_id,a.is_scope,t.least_cost,t.reduce_cost,FROM_UNIXTIME(ts.ticket_begin_date,'%Y.%m.%d') as ticket_begin_date,FROM_UNIXTIME(ts.ticket_end_date,'%Y.%m.%d') as ticket_end_date";
            $tickets	=	M($this->_TICKET_PREFIX."activity a")	->field($field)
                ->join($this->_TICKET_PREFIX.'activity_ticket at on at.activity_id = a.id')
                ->join($this->_TICKET_PREFIX.'ticket t on t.id = at.ticket_id')
                ->join($this->_TICKET_PREFIX.'ticket_stock ts on ts.activity_id = a.id and ts.ticket_id = t.id','left')
                ->where($where)
                ->select();
            foreach ($tickets as $key => $val){
                $tickets[$key]['show_type'] = $val['is_scope'] == 1 ? 'blue' : 'orange';
                $tickets[$key]['show_scope'] = $val['is_scope'] == 1 ? '仅限指定服务使用' : '通用';
            }
            $ticketsList['count']	=	count($tickets);
            $ticketsList['lists']	=	$tickets;
            return 	$ticketsList;
        }else{
            return false;
        }
    }
    //返回领取的代金券 = 0 /红包券 = 1
    public function getServiceVoucherAll($order_id,$type = 0){
        $order	=	M($this->_MODEL)
            ->alias('o')
            ->field("o.branch_id,o.real_cash,o.product_id")
            ->where("id = ".$order_id)->find();
        if($order['real_cash'] > 0){
            $time	=	time();
            $where['ts.ticket_begin_date'] 	= array('lt',$time);
            $where['ts.ticket_end_date'] 	= array('egt',$time);
            $where['a.branch_id']			= $order['branch_id'];
            $where['a.activity_type']	    = ACTIVITY_TYPE_SERVICE;
            $where['ts.mobile']			    = getLoginUserMobile();
            $where['ts.state']				= TICKET_CARD_STATE_GETED;
            $field		=	"ts.id,ts.ticket_id,a.is_over,a.is_scope,a.scope,t.least_cost,t.reduce_cost,FROM_UNIXTIME(ts.ticket_begin_date,'%Y.%m.%d') as ticket_begin_date,FROM_UNIXTIME(ts.ticket_end_date,'%Y.%m.%d') as ticket_end_date";
            $tickets	=	M($this->_TICKET_PREFIX."activity a")	->field($field)
                ->join($this->_TICKET_PREFIX.'activity_ticket at on at.activity_id = a.id')
                ->join($this->_TICKET_PREFIX.'ticket t on t.id = at.ticket_id')
                ->join($this->_TICKET_PREFIX.'ticket_stock ts on ts.activity_id = a.id and ts.ticket_id = t.id','left')
                ->where($where)
                ->select();
            foreach ($tickets as $key => $val){
                $where['_string']               = 'a.is_scope = 0 or (a.is_scope = 1 and FIND_IN_SET('.$order['product_id'].',a.scope))';
                if($val['least_cost'] <= $order['real_cash'] && $val['is_over'] != 2 && ($val['is_scope'] == 0 || ($val['is_scope'] == 1 && in_array($order['product_id'],explode(',',$val['scope']))))){
                    $tickets[$key]['show_type'] = $val['is_scope'] == 1 ? 'blue' : 'orange';
                    $tickets[$key]['show_scope'] = $val['is_scope'] == 1 ? '仅限指定服务使用' : '通用';
                }else {
                    $tickets[$key]['show_type'] = 'error';
                    if($val['is_over'] == 2){
                        $tickets[$key]['show_scope'] = '该优惠券已失效';
                    }elseif($val['least_cost'] > $order['real_cash']){
                        $tickets[$key]['show_scope'] = '不满足满减价格';
                    }elseif($val['is_scope'] == 1 && !in_array($order['product_id'],explode(',',$val['scope']))){
                        $tickets[$key]['show_scope'] = '不在服务范围内';
                    }
                }
            }
            $ticketsList['count']	=	count($tickets);
            $ticketsList['lists']	=	$tickets;
            return 	$ticketsList;
        }else{
            return false;
        }
    }
    public function getProductVoucherDetail( $request, $type = 'product',$users=0)
    {
        if($type == 'product') {
            $order	=	M('ComOrderAttribute')
                ->field("branch_id,real_cash,product_id")
                ->where("id = ".$request['aid']." and product_id = ".$request['id'])->find();
        } else {
            $order  = M('ComOrder')
                ->field("tel,branch_id,real_cash,product_id,user_id")
                ->where("id = ".$request['id'])->find();
            $user = M('SysUser')
                ->field("mobile")
                ->where("id = ".$order['user_id'])->find();
        }
        if($order['real_cash'] > 0){
            $time	=	time();
            $where['ts.ticket_begin_date'] 	= array('lt',$time);
            $where['ts.ticket_end_date'] 	= array('egt',$time);
            $where['a.branch_id']			= $order['branch_id'];
            $where['t.least_cost']          = array('elt',$order['real_cash']);
            $where['a.activity_type']	    = ACTIVITY_TYPE_SERVICE;
            $where['a.is_over']             = array('neq',2);
            if($users==0){
                $where['ts.mobile']			    = getLoginUserMobile();
            }else{
                $where['ts.mobile']			    = $user['mobile']?$user['mobile']:"" ;
            }
            $where['ts.state']				= TICKET_CARD_STATE_GETED;
            $where['_string']               = 'a.is_scope = 0 or (a.is_scope = 1 and FIND_IN_SET('.$order['product_id'].',a.scope))';
            $field		=	"ts.id,ts.ticket_id,a.is_scope,a.scope,t.least_cost,t.reduce_cost,FROM_UNIXTIME(ts.ticket_begin_date,'%Y.%m.%d') as ticket_begin_date,FROM_UNIXTIME(ts.ticket_end_date,'%Y.%m.%d') as ticket_end_date";
            $tickets	=	M($this->_TICKET_PREFIX."activity a")	->field($field)
                ->join($this->_TICKET_PREFIX.'activity_ticket at on at.activity_id = a.id')
                ->join($this->_TICKET_PREFIX.'ticket t on t.id = at.ticket_id')
                ->join($this->_TICKET_PREFIX.'ticket_stock ts on ts.activity_id = a.id and ts.ticket_id = t.id','left')
                ->where($where)
                ->select();
            foreach ($tickets as $key => $val){
                if($val['least_cost'] <= $order['real_cash'] && ($val['is_scope'] == 0 || ($val['is_scope'] == 1 && in_array($order['product_id'],explode(',',$val['scope']))))){
                    $tickets[$key]['show_type'] = $val['is_scope'] == 1 ? 'blue' : 'orange';
                    $tickets[$key]['show_scope'] = $val['is_scope'] == 1 ? '仅限指定服务使用' : '通用';
                }
//                else{
//                    $tickets[$key]['show_type'] = 'error';
//                    if($val['least_cost'] > $order['real_cash']){
//                        $tickets[$key]['show_scope'] = '不满足满减价格';
//                    }elseif($val['is_scope'] == 1 && !in_array($order['product_id'],explode(',',$val['scope']))){
//                        $tickets[$key]['show_scope'] = '不在服务范围内';
//                    }
//                }
            }
            $ticketsList['count']	=	count($tickets);
            $ticketsList['lists']	=	$tickets;
            return 	$ticketsList;
        }else{
            $ticketsList['count']	=	0;
            return $ticketsList;
        }
    }
    public function getOrderData($order_id){
        $order	=	M($this->_MODEL)->where("id = ".$order_id)->find();
        return $order;
    }
    //获取订单临时数据
    public function getOrderTemporaryData($order_id){
        $result	=	M("ComTemporaryData")->where("order_id = ".$order_id)->find();
        return $result;
    }
    //修改订单临时数据
    public function setOrderTemporaryDataAdd($data){
        M("ComTemporaryData")->data($data)->add();
    }
    //添加订单临时数据
    public function setOrderTemporaryDataSave($data,$order_id){
        M("ComTemporaryData")->data($data)->where('id = '.$order_id)->save();
    }
    //修改update_time
    public function setOrderUpdateTime($id){
        $condition['update_time'] = time();
        $condition['id'] = $id;
        $this->save($condition);
    }
    public function getOrderState($id){
        return M('ComOrder')->where('id = '.$id)->getField('order_state');
    }
    //判断是否处于现在转账状态
    public function handlerIsUnline($order_id){
        $order = M($this->_MODEL)
                ->alias('o')
                ->field('cr.pay_status')
                ->join('com_recharge  cr on cr.order_sn = o.order_sn')
                ->where('o.id = '.$order_id.' and cr.money_type = '.FIN_ORDER_LINE_PAY.'  and cr.source = '.FIN_PAY_OFFLINE)
                ->order('cr.id desc')
                ->find();
        return ($order['pay_status'] == 0 && $order && isset($order['pay_status'])) ? true : false;
    }
    //判断这个订单的产品是不是登录用户出售的
    public function getIsProductOwner(){
        if(USER_TYPE_COMPANY_MANAGER ==	$_SESSION['user_type']){
            return true;
        }else{
            return false;
        }
    }
    //判断这个订单是不是已付款
    public function getIsOrderSurety($order_id){
        $order_data	=	M($this->_MODEL)->where("id = ".$order_id)->find();
        if($order_data['surety_state']	==	1){
            return true;
        }else{
            return false;
        }
    }
    //修改订单状态
    public function setOrderState($order_id,$state){
        $data['order_state']		=	$state;
        $data['update_time']        =   time();
        $where['id']	            =	$order_id;
        $result	=	M($this->_MODEL)->data($data)->where($where)->save();
        if($result){
            return true;
        }else{
            return false;
        }
    }
    //修改价格
    public function setOrderRealCash($order_id,$new_price){
        $data['real_cash']	=	$new_price;
        $where['id']	=	$order_id;
        $old_price = M($this->_MODEL)->where($where)->getField('real_cash');
        if(floatval($old_price) == 0){
            $data['update_time']	=	time();
            //定时器topics-1006
            $timer = 24 * 60 * 60;
            D('ESAdmin/SysMq')->add_timer($timer,WEB_ROOT.'/ReqQueue/HandleOrderPayMessage/id/'.$order_id);
        }
        $result	=	M($this->_MODEL)->data($data)->where($where)->save();
        if($result !== false){
            $user_data = M('SysUser') ->field('name') ->where('id = '.$_SESSION['user_id'])->find();
            if(floatval($old_price) > 0){
                $remark['desc'] = '商家修改价格';
                $remark['report_service_desc'] = $user_data['name'].'修改价格';
                $remark['title'] = '改价为￥'.floatval($new_price).'元';
                $remark['topic'] = REPORT_TOPIC_FAKER;
                $this->sendWXOrderUpdatePriceMessage($order_id,1);
                D("ComComment")->sendSysOrderUpdatePriceMessage($order_id,1);
            }else{
                $remark['desc'] = '商家已报价';
                $remark['report_service_desc'] = $user_data['name'].'已报价';
                $remark['title'] = '价格为￥'.floatval($new_price).'元';
                $remark['topic'] = REPORT_TOPIC_FAKER;
                $this->sendWXOrderUpdatePriceMessage($order_id,0);
                D("ComComment")->sendSysOrderUpdatePriceMessage($order_id,0);
            }
            //topics-0002
            //topics-0003
            D('SysReport')->addOrderReport($order_id,$remark);
            return 	$this->getOrderRealCash($order_id);
        }else{
            return $result;
        }
    }
    //整体删除
    public function overallDelete($order_id){
        try{
            $this->startTrans();
            $res = $this->where('id = '.$order_id)->delete();
            if (!$res){
                throw new Exception("订单删除失败!!");
            }
            D('SysReport')->where('order_id = '.$order_id)->delete();
            $this->commit();
            return ['error'=>0,'msg'=>'订单已删除'];
        }catch (Exception $e){
            $this->rollback();
            return ['error'=>1,'msg'=>$e->getMessage()];
        }

    }
    //输出价格
    public function getOrderRealCash($order_id){
        $where['id']	=	$order_id;
        $real_cash	=	M($this->_MODEL)->where($where)->getField('real_cash');
        return $real_cash;
    }
    //取出指定订单中产品的所属服务商id
    public function getTheOrderProductServiceId(){
        $branch_id = getBrowseBranchId();
        $service_ids	=	M('SysUser')->where("branch_id = ".$branch_id)->order('id asc')->getField("id",true);
        return $service_ids;
    }
    /**
     * 处理用户在服务中线下付款
     * @param array order
     * @param str   pic
     * @date NEW Jan 2,2018 Unline Paying Order
     */
    public function setProductUserPayingUnlink($order,$request){
        $url = "/Order/serviceDetail/id/" . $order["id"];
        //判断是否已交纳付款
        if($order['surety_state'] == 1){
            return array('error'=>1,'message'=>'您的服务订单已付款,请勿重复操作!', "url" => $url);
        }
        $user_id = $_SESSION['user_id'];
        //判断是否已提交审核
        $condition_str = 'order_sn   = \''.$order['order_sn'].'\' and ';
        $condition_str.= 'money_type = '.FIN_ORDER_LINE_PAY.' and ';
        $condition_str.= 'Source     = '.FIN_PAY_OFFLINE  .' and ';
        $condition_str.= 'user_id    = '.    $user_id     .' and ';
        $condition_str.= 'pay_status!= 2'.' and branch_id = '.getBrowseBranchId();
        $res = M('ComRecharge')->where($condition_str)->find();
        if($res){
            return array('error'=>1,'message'=>'您的需求订单已提交线下转账凭证,请勿重复操作!', "url" => $url);
        }
        //获取需要支付的价格
        $temporary = $this->getOrderTemporaryData($order["id"]);
        $payment = M($this->_PREFIX.'recharge');
        $payment->user_id   = $user_id;
        $payment->order_sn  = $order['order_sn'];
        $payment->account   = $temporary['payment_money'];
        $payment->ctime     = begtime();
        $payment->pay_name  = "用户托管交易(线下转账)";
        $payment->pay_status= 0;//未支付
        $payment->mobile    = getLoginUserMobile();
        $payment->money_type= FIN_ORDER_LINE_PAY;
        $payment->source    = FIN_PAY_OFFLINE;
        $payment->pic       = $request['pic'];
        $payment->message   = $request['remark'];
        $payment->branch_id = getBrowseBranchId();
        //添加权限信息
        $payment->creator_id = $user_id;
        $payment->user_branch_id = getUserCompanyId();

        $result             = $payment->add();
        if($result){
            $this->setOrderUpdateTime($order["id"]);
            //topics-0005
            D("ComOrder")->sendWXNewUnlineMessage($order["id"]);
            D("ComComment")->sendSysNewUnlineMessage($order["id"]);
            //添加服务进度
            D('SysReport')->addOrderReport($order["id"],['客户线下付款','待商家确认',0,1,['object'=>'unline','object_id'=>$result,'order_id'=>$order["id"]]]);
            return array('error'=>0,'message'=>'线下转账提交成功，感谢您对我们的支持!', "url" => $url);
        }else{
            return array('error'=>1,'message'=>'线下转账提交失败，感谢您对我们的支持!',"url"=>'');
        }
    }
    //获取订单的事项进度
    public function getOrderStepData($order_id){
        $order			=	$this->getOrderData($order_id);
        $ProductModal	=	D($this->_PREFIX."product");
        $product		=	$ProductModal->getProductData($order['product_id']);
        return $product;
    }
    //New Jan 15,2018 催单操作
    public function setPromptingOrder($order_id){
        $order        = M($this->_MODEL)
                                ->alias('o')
                                ->field('o.*,o.id as order_id')
                                ->where('o.id = '.$order_id)->find();
        $theDayStart = strtotime(date('Y-m-d',time()));
        if($order['prompting_time'] < $theDayStart || $order['prompting_time'] == '' || is_null($order['prompting_time'])){
            $prompting_count = (int) $order['prompting_count'];
            $condition['id']       = $order_id;
            $condition['prompting_time'] = time();
            $condition['prompting_count']= $prompting_count + 1;
            $condition["update_time"]    = time();
            M($this->_MODEL)->save($condition);
            //记录服务流程
            D('SysReport')->addOrderReport($order_id,['客户第'.$condition['prompting_count'].'次催进度','',1]);
            //发送催单信息 topics-0009
            $this->sendWXUserPrompting($order);
            D("ComComment")->sendSysUserPrompting($order);
//            $this->getWXUserOrderPrompting($order);
//            D("ComComment")->sendSystemMessageFromUserOrderPrompting($order);
            return true;
        }else{
            return false;
        }
    }
    //New May 9 提醒收款
    public function setremindUnline($order_id){
        $order = M($this->_MODEL)
                 ->alias('o')
                 ->field('o.*,o.id as order_id,cr.id as recharge_id,cr.remind,cr.pay_status')
                 ->join('com_recharge  cr on cr.order_sn = o.order_sn')
                 ->where('o.id = '.$order_id.' and cr.money_type = '.FIN_ORDER_LINE_PAY.'  and cr.source = '.FIN_PAY_OFFLINE)
                 ->order('cr.id desc')
                 ->find();
        if ($order){
            if ($order['pay_status'] == 0){
                $theDayStart = strtotime(date('Y-m-d',time()));
                if ($order['remind'] < $theDayStart || $order['remind'] == '' || is_null($order['remind'])){
                    $condition['id']       = $order['recharge_id'];
                    $condition['remind'] = time();
                    M('ComRecharge')->save($condition);
                    //topics-0006
                    D("ComOrder")->sendWXRemindUnlineMessage($order_id);
                    D("ComComment")->sendSysRemindUnlineMessage($order_id);
                    D('SysReport')->addOrderReport($order_id,['客户提醒收款','待商家确认',0]);
                    return ['error'=>0,'message'=>'您已成功提醒收款!'];
                }else{
                    return ['error'=>1,'message'=>'您今天已提醒过收款!'];
                }
            }else{
                return $order['pay_status'] == 1 ? ['error'=>1,'message'=>'商家已收款无需提醒!'] : ['error'=>1,'message'=>'商家已取消收款!'];
            }
        }else{
            return ['error'=>1,'message'=>'您没有提交线下转账!'];
        }
    }
    //获取订单临时数据
    public function setOrderTemporaryDete($order_id){
        M("ComTemporaryData")->where("order_id = ".$order_id)->delete();
    }
    //完成/取消线下付款
    public function operationUnline($order_id,$type,$remark){
        //获取 unline_id
        $order = M($this->_MODEL)
            ->alias('o')
            ->field('o.*,o.id as order_id,cr.id as recharge_id,cr.remind,cr.pay_status')
            ->join('com_recharge  cr on cr.order_sn = o.order_sn')
            ->where('o.id = '.$order_id.' and cr.money_type = '.FIN_ORDER_LINE_PAY.'  and cr.source = '.FIN_PAY_OFFLINE)
            ->order('cr.id desc')
            ->find();
        if($order['pay_status'] == 0){
            $result = D('ComFinance')->unlinesAudit($order['recharge_id'], $type, $remark);
            if ($result){
                if($type == 1){
                    //topics-0008
                    $this->sendWXCompleteUnlineMessage($order_id);
                    D('ComComment')->sendSysCompleteUnlineMessage($order_id);
                    D("ESAdmin/ComOrder")->addOrderCommision($order_id);//分销函数
                    $this->createAgreementByOrder($order['order_sn']);
                }else{
                    //更新时间
                    $this->setOrderUpdateTime($order_id);
                    //定时器-topics-1006
                    $timer = 24 * 60 * 60;
                    D('ESAdmin/SysMq')->add_timer($timer,WEB_ROOT.'/ReqQueue/HandleOrderPayMessage/id/'.$order_id);
                    //topics-0007
                    $this->sendWXDontUnlineMessage($order_id);
                    D('ComComment')->sendSysDontUnlineMessage($order_id);
                }
                return $type == 1 ? ['error'=>0,'message'=>'确认收款成功!'] : ['error'=>0,'message'=>'取消收款成功!'];
            }else{
                return ['error'=>1,'message'=>'系统出错,请联系管理员'];
            }
        }else{
            return $order['pay_status'] == 1 ? ['error'=>1,'message'=>'您已确认收款,不能执行此操作'] : ['error'=>1,'message'=>'您已取消收款,不能执行此操作'];
        }
    }
    /**
     * 客户延迟验收
     * @param type $order
     * @return type
     * @date Jan 15 ,2018
     */
    public function sendDelayInspectOrder($order_id){
        $order        = M($this->_MODEL)
            ->alias('o')
            ->field('o.*,o.id as order_id')
            ->where('o.id = '.$order_id)->find();
        if($order){
            //发送延迟验收信息
            $this->getWXUserOrderDelayInspect($order);
            D("ComComment")->sendSystemMessageFromUserOrderDelayInspect($order);
            $report_table = M('SysReport');
            $data['order_id']    = $order['order_id'];
            $data['user_id']     = $_SESSION['user_id'];
            $data['report_time'] = time();
            $data['report_desc'] = '延迟验收';
            $data['report_service_desc'] = '客户延迟验收';
            $report_table->data($data)->add();
        }
    }
    /**
     * 跳转链接地址
     * @param type $order_id
     * @param type $user_role
     * @param type $is_author
     * @return type
     * @date Jan 15 ,2018
     */
    private function getOrderInfoUrl($order_id, $is_service = true) {
        if($is_service){
            $url = WEB_ROOT . "/Order/sellDetail/id/$order_id.html";
        }else{
            $url = WEB_ROOT . "/Order/serviceDetail/id/$order_id.html";
        }

        return $url;
    }
    //判断这个订单是不是登录用户的
    public function getIsServiceOwner($order_id){
        $order_data	=	M($this->_MODEL)->where("id = ".$order_id)->find();
        if($order_data['user_id']	==	$_SESSION['user_id']){
            return true;
        }else{
            return false;
        }
    }
    //线下转账详情
    public function getUnlineInfo($unline_id){
        $branch_id = getBrowseBranchId();
        $list =     M('ComRecharge')
                    ->alias('cr')
                    ->field('cr.*,co.product_title as title,FROM_UNIXTIME(co.on_time,"%Y年%m月%d日 %H:%i") as order_on_time,co.id as order_id,co.order_state,co.surety_state,co.contacts,FROM_UNIXTIME(cr.ctime,"%Y年%m月%d日 %H:%i") as created_time')
                    ->join('left join com_order    co  on co.order_sn = cr.order_sn')
                    ->where('cr.id = '.$unline_id)
                    ->find();
        if ($list['branch_id'] == $branch_id && ($list['user_id'] == session('user_id') ||  session('user_type') == USER_TYPE_COMPANY_MANAGER)){
            $list['view_state']  = $list['pay_status'] == 0 ? '审核中' :
                ($list['pay_status'] == 1 ? '审核通过':'审核失败');
            $list['view_order_state']  =  order_stateing($list);
            $list['is_pic'] = !is_null($list['pic']) ? (strlen(trim($list['pic'])) > 0 ? 1 : 0) : 0;
            $list['is_remark'] = !is_null($list['remark']) ? (strlen(trim($list['remark'])) > 0 ? 1 : 0) : 0;
            $list['is_message'] = !is_null($list['message']) ? (strlen(trim($list['message'])) > 0 ? 1 : 0) : 0;
            $list['order_url'] = session('user_type') == USER_TYPE_COMPANY_MANAGER ?
                                            '/Order/sellDetail/id/'.$list['order_id'].'.html':
                                            '/Order/serviceDetail/id/'.$list['order_id'].'.html';
            return $list;
        }else{
            return false;
        }
    }

    //------------------------------------微信模板 New--------------------------------------
    /*
     * @source topics-0001
     * @title 客户购买标价商品
     */
    public function sendWXNewOrderMessage($order_id,$type = 1){
        $templateId = getWxTemplateId('noticeServiceFlow');
        if(!$templateId){
            return false;
        }
        $data  = $this->HandlerOrderSendData($order_id,'service');
        if($data) {
            $data['first'] = $type == 1 ? '您好!您有一个新订单,请及时联系客户沟通服务细节。' : '您好!您有一个新议价订单,请及时联系客户沟通确认服务价格,客户方可付款。';
            $data["step"] = $type == 1 ? '客户购买标价商品' : '客户购买议价商品';
            $this->HandlerWXSendData($data, $templateId);
        }
    }
    /*
     * @source topics-0002 0003
     * @title 商家上传报价 商家修改价格
     */
    public function sendWXOrderUpdatePriceMessage($order_id,$type = 1){
        $templateId = getWxTemplateId('noticeServiceFlow');
        if(!$templateId){
            return false;
        }
        $data  = $this->HandlerOrderSendData($order_id,'user');
        if($data) {
            $data['first'] = $type == 1 ?
                '您好!您购买的订单已改价,请确认无误后及时付款以便我们尽快为您提供服务。' :
                '您好!您购买的订单已报价,请确认无误后及时付款以便我们尽快为您提供服务。';
            $data["step"] = $type == 1 ? '商家已修改价格' : '商家已上传报价';
            $this->HandlerWXSendData($data, $templateId);
        }
    }
    /*
     * @source topics-0005
     * @title 客户线下付款
     */
    public function sendWXNewUnlineMessage($order_id){
        $templateId = getWxTemplateId('noticeServiceFlow');
        if(!$templateId){
            return false;
        }
        $data  = $this->HandlerOrderSendData($order_id,'service');
        if($data) {
            $data['first'] = '您好!客户已线下付款,请及时确认收款。';
            $data["step"] = '客户线下付款';
            $this->HandlerWXSendData($data, $templateId);
        }
    }
    /*
     * @source topics-0006
     * @title 客户线下付款提醒
     */
    public function sendWXRemindUnlineMessage($order_id){
        $templateId = getWxTemplateId('noticeServiceFlow');
        if(!$templateId){
            return false;
        }
        $data  = $this->HandlerOrderSendData($order_id,'service');
        if($data) {
            $data['first'] = '您好!客户已提醒收款,请及时确认收款。';
            $data["step"] = '客户线下付款提醒';
            $this->HandlerWXSendData($data, $templateId);
        }
    }
    /*
     * @source topics-0007
     * @title 商户未收到款
     */
    public function sendWXDontUnlineMessage($order_id){
        $templateId = getWxTemplateId('noticeServiceFlow');
        if(!$templateId){
            return false;
        }
        $data  = $this->HandlerOrderSendData($order_id,'user');
        if($data){
            $data['first'] = '您好!您的订单款,我们查核后尚未到账,烦请查询及付款成功后重新做付款上报流程。';
            $data["step"] = '商户未收到款';
            $this->HandlerWXSendData($data,$templateId);
        }
    }
    /*
     * @source topics-0008
     * @title 确认收款.开始服务
     */
    public function sendWXCompleteUnlineMessage($order_id){
        $templateId = getWxTemplateId('noticeServiceFlow');
        if(!$templateId){
            return false;
        }
        $data  = $this->HandlerOrderSendData($order_id,'all');
        if($data){
            $service_openid = $data['service_openid'];
            $user_openid = $data['user_openid'];
            $service_url = $data['service_url'];
            $user_url = $data['user_url'];
            //通知用户
            $data['openid'] = $user_openid;
            $data['url'] = $user_url;
            $data['first'] = '您好！您购买的订单已确认收款，服务已开始，请保持电话畅通，方便我们及时跟进服务';
            $data["step"] = '确认收款.开始服务';
            $this->HandlerWXSendData($data,$templateId);
            //通知商户
            $data['openid'] = $service_openid;
            $data['url'] = $service_url;
            $data['first'] = '您好!客户线下付款成功,请及时联系客户开始服务。';
            $this->HandlerWXSendData($data,$templateId);
        }
    }
    /*
     * @source topics-0009
     * @title 客户催进度
     */
    public function sendWXUserPrompting($order){
        $templateId = getWxTemplateId('noticeServiceFlow');
        if(!$templateId){
            return false;
        }
        //获取openid
        if($order){
            $condition['branch_id']  = getBrowseBranchId();
            $condition['user_type']  = USER_TYPE_COMPANY_MANAGER;
            $condition['is_valid'] = 1;
            $openid = M('SysUser')->where($condition)->getField('openid',true);
            $data['openid'] = $openid;
            $data['product_title'] = $order['product_title'];
            $data['order_sn'] = $order['order_sn'];
            $data['url'] = WEB_ROOT . '/Order/sellDetail/id/'.$order['order_id'].'.html';
            $data['category'] = $order['product_category'];
            $data['first'] = '您好!客户已催促进度'.((int) $order['prompting_count'] + 1).'次,为了给客户有更好的服务体验,请及时提报进度。';
            $data["step"] = '客户催进度';
            $this->HandlerWXSendData($data,$templateId);
        }
    }
    /*
     * @source topics-0010
     * @title 客户上传附件
     */
    public function sendWXUserReport($order_id,$type){
        $templateId = getWxTemplateId('noticeServiceFlow');
        if(!$templateId){
            return false;
        }
        $send_type = $type == 0 ? 'service' : 'user';
        $data  = $this->HandlerOrderSendData($order_id,$send_type);
        if($data){
            $data['first'] = $type == 'user' ?
                             '您好!客户已上传资料,请及时查看确认。':
                             '您好!我们已经更新了服务进度,请及时查看确认';
            $data["step"] = $type == 'user' ? '客户上传附件' : '商家更新进度';
            $this->HandlerWXSendData($data,$templateId);
        }
    }
    /*
     * @source topics-0011
     * @title 客户申请结束订单
     */
    public function sendWXUserCloseOrder($order_id){
        $templateId = getWxTemplateId('noticeServiceFlow');
        if(!$templateId){
            return false;
        }
        $data  = $this->HandlerOrderSendData($order_id,'service');
        if($data){
            $data['first'] = '您好!客户申请结束订单,请及时查看原因和处理。';
            $data["step"] = '客户申请结束订单';
            $this->HandlerWXSendData($data,$templateId);
        }
    }
    /*
     * @source topics-0012 topics-0018
     * @title 商家拒绝结束订单
     */
    public function sendWXOrderCloseHandler($order_id,$type){
        $templateId = getWxTemplateId('noticeServiceFlow');
        if(!$templateId){
            return false;
        }
        //拒绝
        if ($type == 'refuse')
        {
            $data  = $this->HandlerOrderSendData($order_id,'user');
            if($data){
                $data['first'] = '您好!我们拒绝您的订单结束申请,请点击查看详情。';
                $data["step"] = '商家拒绝结束订单';
                $this->HandlerWXSendData($data,$templateId);
            }
        }
        //同意
        else
        {
            $data  = $this->HandlerOrderSendData($order_id,'all');
            if($data){
                $service_openid = $data['service_openid'];
                $user_openid = $data['user_openid'];
                $service_url = $data['service_url'];
                $user_url = $data['user_url'];
                //通知用户
                $data['openid'] = $user_openid;
                $data['url'] = $user_url;
                $data['first'] = '您好!我们已确认你的订单结束订单申请,请与客户联系处理结束订单事宜。';
                $data["step"] = '商家同意结束订单';
                $this->HandlerWXSendData($data,$templateId);
                //通知商户
                $data['openid'] = $service_openid;
                $data['url'] = $service_url;
                $data['first'] = '您好!您已同意客户结束订单申请,请与客户联系处理结束订单事宜。';
                $this->HandlerWXSendData($data,$templateId);
            }
        }

    }
    /*
     * @source topics-0013
     * @title 商家完成服务,申请验收
     */
    public function sendWXCheckFinishOrder($order_id){
        $templateId = getWxTemplateId('noticeServiceFlow');
        if(!$templateId){
            return false;
        }
        $data  = $this->HandlerOrderSendData($order_id,'user');
        if($data){
            $data['first'] = '您好!我们已完成服务,请及时验收,如有问题请及时联系我们。';
            $data["step"] = '商家完成服务,申请验收';
            $this->HandlerWXSendData($data,$templateId);
        }
    }
    /*
     * @source topics-0014
     * @title 客户延迟验收
     */
    public function sendWXDelayInspect($order_id){
        $templateId = getWxTemplateId('noticeServiceFlow');
        if(!$templateId){
            return false;
        }
        $data  = $this->HandlerOrderSendData($order_id,'service');
        if($data){
            $data['first'] = '您好!客户需延迟验收订单,请立即查看原因并联系客户了解详情。';
            $data["step"] = '客户延迟验收';
            $this->HandlerWXSendData($data,$templateId);
        }
    }
    /*
     * @source topics-0015
     * @title 客户确认验收,订单结束,待评价
     */
    public function sendWXAcceptanceMessage($order_id){
        $templateId = getWxTemplateId('noticeServiceFlow');
        if(!$templateId){
            return false;
        }
        $data  = $this->HandlerOrderSendData($order_id,'all');
        if($data){
            $service_openid = $data['service_openid'];
            $user_openid = $data['user_openid'];
            $service_url = $data['service_url'];
            $user_url = $data['user_url'];
            //通知用户
            $data['openid'] = $user_openid;
            $data['url'] = $user_url;
            $data['first'] = '您好!感谢您的验收确认,请您对本次服务做出评价,期待再次为您服务。';
            $data["step"] = '客户确认验收,订单结束';
            $this->HandlerWXSendData($data,$templateId);
            //通知商户
            $data['url'] = $service_url;
            $data['openid'] = $service_openid;
            $data['first'] = '您好,您有1笔服务订单客户已确认验收服务已完成。';
            $this->HandlerWXSendData($data,$templateId);
        }
    }
    /*
     * @source topics-0017
     * @title 客户取消订单,订单关闭
     */
    public function sendWXUserRefuseOrder($order_id){
        $templateId = getWxTemplateId('noticeServiceFlow');
        if(!$templateId){
            return false;
        }
        $data  = $this->HandlerOrderSendData($order_id,'service');
        if($data){
            $data['first'] = '您好!客户已取消订单,请查看客户取消原因。';
            $data["step"] = '客户取消订单,订单关闭';
            $this->HandlerWXSendData($data,$templateId);
        }
    }
    /*
     * @source topics-1006
     * @title 商户未收到款
     */
    public function sendWXOrderPayMessage($order_id,$second){
        $templateId = getWxTemplateId('noticeServiceFlow');
        if(!$templateId){
            return false;
        }
        $data  = $this->HandlerOrderSendData($order_id,'user');
        if($data){
            $date = ( 3 - (int) $second ) * 24;
            $data['first'] = '您好!您有订单需及时付款,否则'.$date.'小时后系统将自动关闭。';
            $data["step"] = '订单未付款';
            $this->HandlerWXSendData($data,$templateId);
        }
    }
    /*
     * @source topics-0018
     * @title 客户超时未付款,订单自动关闭
     */
    public function sendWXOrderAutomaticClose($order_id){
        $templateId = getWxTemplateId('noticeServiceFlow');
        if(!$templateId){
            return false;
        }
        $data  = $this->HandlerOrderSendData($order_id,'all');
        if($data){
            $service_openid = $data['service_openid'];
            $user_openid = $data['user_openid'];
            $service_url = $data['service_url'];
            $user_url = $data['user_url'];
            //通知用户
            $data['openid'] = $user_openid;
            $data['url'] = $user_url;
            $data['first'] = '您好!由于付款超时,系统已自动关闭您的订单,请返回商城重新下单。';
            $data["step"] = '客户超时未付款,订单自动关闭';
            $this->HandlerWXSendData($data,$templateId);
            //通知商户
            $data['openid'] = $service_openid;
            $data['url'] = $service_url;
            $data['first'] = '您好!由于客户付款超时,系统已自动关闭客户订单。';
            $this->HandlerWXSendData($data,$templateId);
        }
    }
    /*
     * @source topics-1005
     * @title 商家完成服务,申请验收
     */
    public function sendWXCheckFinishMessage($order_id,$second){
        $templateId = getWxTemplateId('noticeServiceFlow');
        if(!$templateId){
            return false;
        }
        $data  = $this->HandlerOrderSendData($order_id,'user');
        if($data){
            $date = ( 3 - (int) $second ) * 24;
            $data['first'] = '您好!您有1笔服务订单等待验收,剩'.$date.'小时系统将自动确认验收。';
            $data["step"] = '商家完成服务,申请验收';
            $this->HandlerWXSendData($data,$templateId);
        }
    }
    /*
     * @source topics-1007
     * @title 客户超时未验收,订单自动验收,待评价
     */
    public function sendWXSystemAcceptance($order_id){
        $templateId = getWxTemplateId('noticeServiceFlow');
        if(!$templateId){
            return false;
        }
        $data  = $this->HandlerOrderSendData($order_id,'user');
        if($data){
            $data['first'] = '您好!您有1笔服务订单超时未验收,系统已自动确认验收,请对本次服务做出评价,期待再次为您服务。';
            $data["step"] = '客户超时未验收,订单自动验收';
            $this->HandlerWXSendData($data,$templateId);
        }
    }
    /*
 * @title 客户请求通知 - 工具提交通知
 */
    public function sendWXTool($tool){
        $templateId = getWxTemplateId('noticeToolSend');
        if(!$templateId){
            \Think\Log::write("客户请求通知-模板消息没配置！");
            return false;
        }
        $branch_id = getBrowseBranchId();
        $condition["branch_id"] = $branch_id;
        $condition["is_leader"] = 1;
        $openid = M('SysUser')->where($condition)->getField('openid',true);
        if($tool['type'] == 0){
            $data['first'] = '您收到一条核名信息。' ;
            $data['url'] =  WEB_ROOT . '/Work/tool_query_list/type/0';
        }elseif($tool['type'] == 1){
            $data['first'] = '您收到一条商标查询信息。' ;
            $data['url'] =  WEB_ROOT . '/Work/tool_query_list/type/1';
        }elseif($tool['type'] ==2){
            $data['first'] = '您收到一条咨询信息。';
            $data['url'] =  WEB_ROOT . '/Work/tool_query_list/type/2';
        }
        if(!is_null($openid)){
            $data["name"] = $tool['nickname'];
            $data["mobile"] = $tool['mobile'];
            $data["created_at"] = date('Y/m/d H:i:s',time());
            $data['openid'] = $openid;
            $this->HandlerWXSendToolData($data,$templateId);
        }else{
            \Think\Log::write("客户请求通知-找不到管理人员！");
        }
    }
    /*免费咨询发送客户通知
     * type=1 提交咨询发送给客户
     * type=2 商家回复咨询发送给客户
     * type=3 客户留言发送给商家
     * type=4 商家回留言
     * */
    public function sendWXConsult($tool,$type=1){
        $templateId = getWxTemplateIdByStandardId('OPENTM202109783');
        if(!$templateId){
            return false;
        }
        if($type == 1){
            $openid = M('SysUser')->where("id=".$_SESSION['user_id'])->getField('openid',true);
            $data['first'] = '您的咨询信息已提交成功';
            $data["reply"] = "商家将在24小时内与您联系";
            $data['remark'] = "点击进入首页";
            $data['url'] =  WEB_ROOT . '/Index';
        }elseif($type == 2){
            $openid = M('SysUser')->where("id=".$tool['user_id'])->getField('openid',true);
            $data['first'] = '您的咨询信息收到一条新消息';
            $data["reply"] = $tool["reply"];
            $data['remark'] = "点击查看详情";
            $data['url'] =  WEB_ROOT . '/Liuyan.html';
        }elseif($type == 3){
            $condition["branch_id"] = getBrowseBranchId();
            $condition["is_leader"] = 1;
            $openid = M('SysUser')->where($condition)->getField('openid',true);
            $data['first'] = '您收到一条留言信息';
            $data["reply"] = $tool["reply"];
            $data['remark'] = "点击查看详情";
            $data['url'] =  WEB_ROOT . '/Liuyan.html';
        }elseif($type == 4){
            $openid = M('SysUser')->where("id=".$tool['user_id'])->getField('openid',true);
            $data['first'] = '您收到一条留言信息';
            $data["reply"] = $tool["reply"];
            $data['remark'] = "点击查看详情";
            $data['url'] =  WEB_ROOT . '/Liuyan.html';
        }
        if(!is_null($openid)){
            $data['consultant'] = $tool['consultant'];
            $data['openid'] = $openid;
            $this->HandlerWXSendConsultData($data,$templateId);
        }
    }
    protected function HandlerWXSendConsultData($data,$templateId){
        if ($data) {
            $message = array();
            $body = array();
            $message["template_id"] = $templateId;
            $message["url"] = $data['url'];
            $body["first"]["value"]    = $data['first'];
            $body["keyword1"]["value"] = $data['consultant']; //咨询名称
            $body["keyword2"]["value"] = $data["reply"]; //回复信息
            $body["remark"]["value"] = $data["remark"];//strlen(trim($data["remark"])) > 0 ? $data["remark"] : getMessageRemark();
            $message["body"] = $body;
            if(is_array($data['openid'])){
                foreach ($data['openid'] as $val){
                    $message["openid"] = $val;
                    send_wx_message($message);
                }
            }else{
                $message["openid"] = $data['openid'];
                send_wx_message($message);
            }
        }
    }

    /*
     * @source topics-0019
     * @title 微信支付成功,开始服务
     */
    public function sendWXPayedMessage($order_id){
        $templateId = getWxTemplateId('noticeServiceFlow');
        if(!$templateId){
            return false;
        }
        $data  = $this->HandlerOrderSendData($order_id,'all');
        if($data){
            $service_openid = $data['service_openid'];
            $user_openid = $data['user_openid'];
            $service_url = $data['service_url'];
            $user_url = $data['user_url'];
            //通知用户
            $data['openid'] = $user_openid;
            $data['url'] = $user_url;
            $data['first'] = '您好!您购买的订单已支付成功,服务已开始,请保持电话畅通,方便我们及时跟进服务。';
            $data["step"] = '微信支付成功,开始服务';
            $this->HandlerWXSendData($data,$templateId);
            //通知商户
            $data['openid'] = $service_openid;
            $data['url'] = $service_url;
            $data['first'] = '您好!客户微信支付成功,请及时联系客户开始服务。';
            $this->HandlerWXSendData($data,$templateId);
        }
    }
    protected function HandlerOrderSendData($order_id,$type = 'user'){


        $field = "comorder.order_sn,comorder.id as order_id,comorder.order_state,comorder.product_title,comorder.product_category,user.openid";
        $handler = $this    ->alias("comorder");
        $handler->join("left join sys_user  as  user on user.id=comorder.user_id");
        $handler ->field($field);
        $handler ->where("comorder.id=".$order_id);
        $data = $handler->find();
        if($data){
            $data['category'] =  $data['product_category'] ;
            if($type == 'user'){
                $data['url'] = WEB_ROOT . '/Order/serviceDetail/id/'.$order_id.'.html';
            }elseif($type == 'service'){
                $data['openid'] = array_unique(D('SysUser')->getBranchManager('openid'));
                $data['url'] = WEB_ROOT . '/Order/sellDetail/id/'.$order_id.'.html';
            }elseif($type == 'all'){
                $data['user_openid'] = $data['openid'];
                $data['service_openid'] = array_unique(D('SysUser')->getBranchManager('openid'));
                $data['user_url'] = WEB_ROOT . '/Order/serviceDetail/id/'.$order_id.'.html';
                $data['service_url'] = WEB_ROOT . '/Order/sellDetail/id/'.$order_id.'.html';
            }
            return $data;
        }else{
            return false;
        }
    }
    protected function HandlerWXSendData($data,$templateId){
        if ($data) {
            $message = array();
            $body = array();
            $message["template_id"] = $templateId;
            $message["url"] = $data['url'];
            $body["first"]["value"]    = $data['first'];
            $body["keyword1"]["value"] = $data["order_sn"]; //编号
            $body["keyword2"]["value"] = $data["category"]; //类型
            $body["keyword3"]["value"] = $data["product_title"]; //内容
            $body["keyword4"]["value"] = $data["step"];//进度
            $body["remark"]["value"] = strlen(trim($data["remark"])) > 0 ? $data["remark"] : getMessageRemark();
            $message["body"] = $body;
            if(is_array($data['openid'])){
                foreach ($data['openid'] as $val){
                    $message["openid"] = $val;
                    send_wx_message($message);
                }
            }else{
                $message["openid"] = $data['openid'];
                send_wx_message($message);
            }

        }
    }
    protected function HandlerWXSendToolData($data,$templateId){
        if ($data) {
            $message = array();
            $body = array();
            $message["template_id"] = $templateId;
            $message["url"] = $data['url'];
            $body["first"]["value"]    = $data['first'];
            $body["keyword1"]["value"] = $data["name"]; //姓名
            $body["keyword2"]["value"] = $data["mobile"]; //手机
            $body["keyword3"]["value"] = $data["created_at"]; //提交时间
            $body["remark"]["value"] = strlen(trim($data["remark"])) > 0 ? $data["remark"] : getMessageRemark();
            $message["body"] = $body;
            if(is_array($data['openid'])){
                foreach ($data['openid'] as $val){
                    $message["openid"] = $val;
                    send_wx_message($message,true);
                }
            }else{
                $message["openid"] = $data['openid'];
                send_wx_message($message, true);
            }
        }
    }

    //订单创建合同
    public function createAgreementByOrder($order_sn){
        $order = M("ComOrder")->where("order_sn = '$order_sn'")->find();
        if($order){
            $is_auto_agreement = M("ComStore")->where("branch_id = ".$order['branch_id'])->getField("is_auto_agreement");
            if($is_auto_agreement) {
                $data['origin'] = 0;//商城
                $data['customer_leader_id'] = $order['user_id'];//订单购买人
                $data['order_sn'] = $order_sn;
                $data['company_id'] = $order['branch_id'];
                $data['name'] = $order_sn . " " . $order['product_title'];
                $data['agreement_money'] = $order['real_cash'];
                //服务明细
                $product_attributes = json_decode($order['product_attribute'], true);
                $product_category = explode("-", $order['product_category']);
                $tmp = [];
                for ($i = 0; $i < 3; $i++) {
                    if ($product_attributes[$i]['parent_name']) {
                        $tmp['attributes' . ($i + 1)] = $product_attributes[$i]['parent_name'] . "：" . $product_attributes[$i]['name'];
                    } else {
                        $tmp['attributes' . ($i + 1)] = "";
                    }
                }
                $option[0] = array("id" => 1, 'type1' => $product_category[0],
                    'type2' => $product_category[1],
                    'attributes1' => $tmp['attributes1'],
                    'attributes2' => $tmp['attributes2'],
                    'attributes3' => $tmp['attributes3']);
                array_push($option, $order['order_desc']);
                $data['product_options'] = json_encode($option);
                $branch_info = M("SysBranch")->where("id = " . $order['branch_id'])->find();
                $data['creater_id'] = $branch_info['leader_id'];
                $data['leader_id'] = $branch_info['leader_id'];
                //$data['visiblers'] = $branch_info['leader_id'];
                $data['create_time'] = time();
                $data['start_time'] = $order['on_time'];
                //$data['comments'] = "订单自动生成合同";
                $data['sys_sn'] = "A" . $order['branch_id'] . $order['user_id'] . time();
                $data['branch_id'] = $order['branch_id'];
                $data['attach_group'] = genUniqidKey();
                $data['invoice_type'] = 1;
                $result = M("WrkAgreement")->add($data);
                if ($result !== false) {
                   $this->addInvoicePlan($data, $result);
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

    public function addInvoicePlan($data,$id=""){
        $tmp['branch_id'] = $data['branch_id'];
        $tmp['type'] = 0;
        $tmp['leader_id'] = M("SysBranch")->where("id = ".$data['branch_id'])->getField("leader_id");
        $tmp['agreement_id'] = $data['id'];
        $tmp['create_time'] = time();
        $tmp['attach_group'] = genUniqidKey();
        if($id != ""){
            $tmp['agreement_id'] = $id;
        }
        if($data['invoice_type'] != 0){
            $tmp["creater_id"] = $data['creater_id'];
        }
        $tmp["is_sendWX"] = 1;
        M("WrkInvoicePlan")->add($tmp);
    }
}
