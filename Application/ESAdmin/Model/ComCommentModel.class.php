<?php

namespace ESAdmin\Model;

use Think\Model;

class ComCommentModel extends Model {
//	const DEFAULT_LY_SUFFIX = '如有疑问请致电客服0592-5239592';
	const DEFAULT_URL_HEAD      = SHOP_ROOT;
	const DEFAULT_URL_BLANCH    = '/index.php/Order/sellDetail';
	const DEFAULT_URL_CUSTOMER  = '/index.php/Order/serviceDetail';
	protected $_MODEL           = 'ComComment';

    public function getOrderComment($order_id){
        $where['obj_id']	=	$order_id;
        $where['obj_type']	=	COMMENTS_OBJERT_TYPE_ORDER;
        $comment	=	M($this->_MODEL)->where($where)->find();
        return $comment;
    }
	//发送系统留言提醒
	//obj 对象id集合
	public function sentSystemMessage($obj,$contents){
		if(!$obj || $contents == ''){
			return false;
		}
		$ask['obj_type'] = 'system';
        $ask['read']     = 1;
        $ask['origin_id'] = 0;
        $ask['comment_time'] = time();
        $ask['user_id'] = 0;
        $ask['branch_id'] = getBrowseBranchId();
        $ask['content']  = base64_encode($contents);
        if(is_array($obj)){
        	$obj_arr = array_unique($obj);
        	foreach ($obj_arr as $key => $value) {
	            $ask['obj_id'] = $value;
	            $ask_all[]  =   $ask;
	        }
        }else{
        	$ask['obj_id'] = $obj;
        	$ask_all[]  =   $ask;
        }
        M("SysAsk")->addAll($ask_all);
	}
    //服务 -- 付款 提醒服务商
    public function sendSystemMessageFromUserPayedProduct($order_id){
        $order          = D("ComOrder")->getOrderDetailData($order_id);
        $url     		= self::DEFAULT_URL_HEAD . self::DEFAULT_URL_BLANCH ."/id/$order_id.html";
        $contents 		= '您好，您的订单'.$this->FormatSpan($url,$order['product_title']).'客户已付款，请确认开始服务。';
        $order['service_ids'] = $this->getBranchIds('id');
        $this->sentSystemMessage($order['service_ids'],$contents);
    }
	//格式化需求点击
	private function FormatSpan($url,$content){
		$default = '<span id="system-msg" data-url="'.$url.'" style="color:blue">'.$content.'</span>';
		return $default;
	}
	private function getBranchIds($inc){
        $branch_id = getBrowseBranchId();
        return M('SysUser')->where('branch_id = '.$branch_id.' and user_type = '.USER_TYPE_COMPANY_MANAGER)->getField($inc,true);
    }
    /**
     * 跳转链接地址 -- order
     * @param type $order_id 
     * @param type $user_role 
     * @param type $is_author 
     * @return type
     * @date Jan 15 ,2018
     */
//    private function getOrderInfoUrl($order_id, $user_role = '', $is_author = true) {
//        $url = "";
//        if($is_author){
//	        $url = self::DEFAULT_URL_HEAD . "/index.php/Order/service_sell_detail/id/$order_id.html";
//        }else{
//            $url = ($user_role == USER_CUSTOMER) ?
//            		self::DEFAULT_URL_HEAD . "/user.php/Order/service_detail/id/$order_id.html" :
//            		self::DEFAULT_URL_HEAD . "/index.php/Order/service_buy_detail/id/$order_id.html";
//        }
//
//        return $url;
//    }

}
