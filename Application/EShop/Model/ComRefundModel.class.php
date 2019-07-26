<?php

namespace EShop\Model;

use Think\Model;

class ComRefundModel extends Model {
    protected $_MODEL = 'ComRefund';
    protected $_PREFIX= 'com_';
    protected $_SYS_PREFIX = 'sys_';
    protected $_ORDER = 'ComOrder';

    public function getOrderRefundDetail($refund_id){
        if($refund_id > 0){
            $refund = $this->getRefundDetail($refund_id,true);
            $condition['o.id'] 	= $refund['order_id'];
            $data =	M($this->_ORDER)
                    ->alias('o')
                    ->field('o.product_title as title,o.contacts,o.order_sn,fina.fina_cash,o.refund_state')
                    ->join($this->_PREFIX.'finance as fina on fina.order_sn = o.order_sn')
                    ->where($condition)
                    ->find();
            $data['order_url']   = '/admin.php/Demand/order_info/id/'.$refund['order_id'].'.html';
            $data['on_time']  	  = date('Y-m-d H:i:s',$refund['on_time']);
            $data['update_time']  = date('Y-m-d H:i:s',$refund['update_time']);
            $data['show_state']	  = ($data['refund_state'] == ORDER_REFUND_STATE_BEGIN) ?
                '等待服务商回复' :
                (($data['refund_state'] == ORDER_REFUND_STATE_COMPLETE)?
                    '服务商同意退款' : '服务商拒绝退款');
            return array($refund,$data);
        }

    }
    public function getRefundDetail($refund_id,$type = false){
        $refund = M($this->_MODEL)->where('order_id = '.$refund_id)->find();
        if($type){
            for ($i=1; $i < 6; $i++) {
                if($refund['attach_'.$i]){
                    $refund['attach'][] = $refund['attach_'.$i];
                }else{
                    break;
                }
            }
        }
        return $refund;
    }
}
