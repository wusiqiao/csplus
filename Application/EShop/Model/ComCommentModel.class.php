<?php

namespace EShop\Model;

use Think\Model;

class ComCommentModel extends DataModel {
//	const DEFAULT_LY_SUFFIX = '如有疑问请致电客服0592-5239592';
    private $Assemble = [];
	const DEFAULT_URL_HEAD      = WEB_ROOT;
	const DEFAULT_URL_BLANCH    = '/Order/sellDetail';
	const DEFAULT_URL_CUSTOMER  = '/Order/serviceDetail';
    protected $_EShop_model     = 'EShop';
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
        $ask['branch_id'] = $this->Assemble['branch_id'] ? $this->Assemble['branch_id'] : getBrowseBranchId();
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
	//--------------------------------------New start--------------------------------//
    /*
     * @source topics-0001
     * @title 客户购买标价商品
     */
    public  function sendSysNewOrderMessage($order_id){
        $this->handlerOrderId($order_id);
        $res = $this->getDefaultOrderData();
        if(!$res){
            return false;
        }
        $this->setBranchIds();
        $url = $this->getJumpUrl('branch');
        $contents 		= $this->Assemble['real_cash'] > 0 ?
            '您好！您有一个新订单'.$this->FormatSpan($url,$this->Assemble['product_title']).'，请及时联系客户沟通服务细节。':
            '您好！您有一个新议价订单'.$this->FormatSpan($url,$this->Assemble['product_title']).'，请及时联系客户确定服务价格，客户方可付款。';
        $this->sentSystemMessage($this->Assemble['service_ids'],$contents);
    }
    /*
     * @source topics-0002 0003
     * @title 商家上传报价 商家修改价格
     */
    public function sendSysOrderUpdatePriceMessage($order_id,$type = 1){
        $this->handlerOrderId($order_id);
        $res = $this->getDefaultOrderData();
        if(!$res){
            return false;
        }
        $url = $this->getJumpUrl('user');
        $contents 		= $type == 1 ?
            '您好！您购买的订单'.$this->FormatSpan($url,$this->Assemble['product_title']).'已改价，请确认无误后及时付款以便我们尽快为您提供服务。':
            '您好！您购买的订单'.$this->FormatSpan($url,$this->Assemble['product_title']).'已报价，请确认无误后及时付款以便我们尽快为您提供服务。';
        $this->sentSystemMessage($this->Assemble['user_id'],$contents);
    }
    /*
     * @source topics-0005
     * @title 客户线下付款
     */
    public function sendSysNewUnlineMessage($order_id){
        $this->handlerOrderId($order_id);
        $res = $this->getDefaultOrderData();
        if(!$res){
            return false;
        }
        $this->setBranchIds();
        $url = $this->getJumpUrl('branch');
        $contents = '您好！您出售的订单'.$this->FormatSpan($url,$this->Assemble['product_title']).'客户已线下付款，请及时确认收款。';
        $this->sentSystemMessage($this->Assemble['service_ids'],$contents);
    }
    /*
     * @source topics-0006
     * @title 客户线下付款提醒
     */
    public function sendSysRemindUnlineMessage($order_id){
        $this->handlerOrderId($order_id);
        $res = $this->getDefaultOrderData();
        if(!$res){
            return false;
        }
        $this->setBranchIds();
        $url = $this->getJumpUrl('branch');
        $contents = '您好！您的'.$this->FormatSpan($url,$this->Assemble['product_title']).'订单，客户已提醒收款,请及时确认收款。';
        $this->sentSystemMessage($this->Assemble['service_ids'],$contents);
    }
    /*
     * @source topics-0007
     * @title 商户未收到款
     */
    public function sendSysDontUnlineMessage($order_id){
        $this->handlerOrderId($order_id);
        $res = $this->getDefaultOrderData();
        if(!$res){
            return false;
        }
        $url = $this->getJumpUrl('user');
        $contents = '您好！您的'.$this->FormatSpan($url,$this->Assemble['product_title']).'订单款，我们查核后尚未到账,烦请查询及付款成功后重新做付款上报流程。';
        $this->sentSystemMessage($this->Assemble['user_id'],$contents);
    }
    /*
     * @source topics-0008
     * @title 确认收款.开始服务
     */
    public function sendSysCompleteUnlineMessage($order_id){
        $this->handlerOrderId($order_id);
        $res = $this->getDefaultOrderData();
        if(!$res){
            return false;
        }
        $this->setBranchIds();
        $user_url = $this->getJumpUrl('user');
        $branch_url = $this->getJumpUrl('branch');
        $user_contents = '您好！您购买的'.$this->FormatSpan($user_url,$this->Assemble['product_title']).'订单已确认收款，服务已开始,请保持电话畅通,方便我们及时跟进服务。';
        $branch_contents = '您好！您出售的订单'.$this->FormatSpan($branch_url,$this->Assemble['product_title']).',客户线下付款成功，请及时联系客户开始服务。';
        $this->sentSystemMessage($this->Assemble['user_id'],$user_contents);
        $this->sentSystemMessage($this->Assemble['service_ids'],$branch_contents);
    }
    /*
     * @source topics-0009
     * @title 客户催进度
     */
    public function sendSysUserPrompting($order){
        $this->handlerAssemble($order);
        $url = $this->getJumpUrl('branch');
        $this->setBranchIds();
        $prompting_count = (int) $this->Assemble['prompting_count'] + 1;
        $contents = '您好！您出售的订单'.$this->FormatSpan($url,$this->Assemble['product_title']).'，客户已经催促'.$prompting_count.'次,为了让客户有更好的服务体验.请及时提报进度。';
        $this->sentSystemMessage($this->Assemble['service_ids'],$contents);
    }
    /*
     * @source topics-0010
     * @title 客户上传附件
     */
    public function sendSysUserReport($order_id,$type){
        $this->handlerOrderId($order_id);
        $res = $this->getDefaultOrderData();
        if(!$res){
            return false;
        }
        $this->setBranchIds();
        $url = $type == 0 ? $this->getJumpUrl('branch') : $this->getJumpUrl('user');
        $contents = $type == 0 ?
                    '您好！您出售的订单'.$this->FormatSpan($url,$this->Assemble['product_title']).'，客户已上传资料,请及时查看确认。':
                    '您好！您购买的订单'.$this->FormatSpan($url,$this->Assemble['product_title']).'，我们已经更新了服务进度,请点击查看详情。';
        $obj = $type == 0 ? $this->Assemble['service_ids'] : $this->Assemble['user_id'];
        $this->sentSystemMessage($obj,$contents);
    }
    /*
     * @source topics-0011
     * @title 客户申请结束订单
     */
    public function sendSysUserCloseOrder($order_id){
        $this->handlerOrderId($order_id);
        $res = $this->getDefaultOrderData();
        if(!$res){
            return false;
        }
        $this->setBranchIds();
        $url = $this->getJumpUrl('branch');
        $contents = '您好！您出售的订单'.$this->FormatSpan($url,$this->Assemble['product_title']).'，客户申请结束订单,请及时查看原因和处理。';
        $this->sentSystemMessage($this->Assemble['service_ids'],$contents);
    }
    /*
     * @source topics-0012 topics-0016
     * @title 商家拒绝结束订单 商家同意结束订单
     */
    public function sendSysOrderCloseHandler($order_id,$type){
        $this->handlerOrderId($order_id);
        $res = $this->getDefaultOrderData();
        if(!$res){
            return false;
        }
        if($type == 'refuse'){
            $url = $this->getJumpUrl('user') ;
            $contents = '您好！我们拒绝您购买的订单'.$this->FormatSpan($url,$this->Assemble['product_title']).',订单结束申请，请点击查看详情。';
            $this->sentSystemMessage($this->Assemble['user_id'],$contents);
        }elseif($type == 'agree'){
            $this->setBranchIds();
            $user_url = $this->getJumpUrl('user') ;
            $branch_url = $this->getJumpUrl('branch') ;
            $user_contents = '您好！我们已同意您购买的订单'.$this->FormatSpan($user_url,$this->Assemble['product_title']).',订单结束申请，我们将联系您处理结束订单事务。';
            $branch_contents = '您好！您已同意客户订单'.$this->FormatSpan($branch_url,$this->Assemble['product_title']).',订单结束申请，请于客户联系处理结束订单事务。';
            $this->sentSystemMessage($this->Assemble['user_id'],$user_contents);
            $this->sentSystemMessage($this->Assemble['service_ids'],$branch_contents);
        }elseif($type == 'sys'){
            $this->setBranchIds();
            $user_url = $this->getJumpUrl('user') ;
            $branch_url = $this->getJumpUrl('branch') ;
            $user_contents = '您好！系统已接受您的'.$this->FormatSpan($user_url,$this->Assemble['product_title']).',订单结束申请，我们将联系您处理结束订单事务。';
            $branch_contents = '您好！客户的结束订单'.$this->FormatSpan($branch_url,$this->Assemble['product_title']).'申请您超时未处理，系统已自动结束订单，请与客户联系处理结束订单事宜。';
            $this->sentSystemMessage($this->Assemble['user_id'],$user_contents);
            $this->sentSystemMessage($this->Assemble['service_ids'],$branch_contents);
        }
    }
    /*
     * @source topics-0012
     * @title 商家完成服务,申请验收
     */
    public function sendSysCheckFinishOrder($order_id){
        $this->handlerOrderId($order_id);
        $res = $this->getDefaultOrderData();
        if(!$res){
            return false;
        }
        $url = $this->getJumpUrl('user');
        $contents = '您好！您购买的订单'.$this->FormatSpan($url,$this->Assemble['product_title']).'我们已完成服务，请及时验收,如有问题请及时联系我们。';
        $this->sentSystemMessage($this->Assemble['user_id'],$contents);
    }
    /*
     * @source topics-0014
     * @title 客户延迟验收
     */
    public function sendSysDelayInspect($order_id){
        $this->handlerOrderId($order_id);
        $res = $this->getDefaultOrderData();
        if(!$res){
            return false;
        }
        $this->setBranchIds();
        $url = $this->getJumpUrl('branch');
        $contents = '您好！您出售的订单'.$this->FormatSpan($url,$this->Assemble['product_title']).'客户需延迟验收订单，请立即查看原因并联系客户了解情况。';
        $this->sentSystemMessage($this->Assemble['service_ids'],$contents);
    }
    /*
     * @source topics-0015
     * @title 客户确认验收,订单结束,待评价
     */
    public function sendSysAcceptanceMessage($order_id){
        $this->handlerOrderId($order_id);
        $res = $this->getDefaultOrderData();
        if(!$res){
            return false;
        }
        $this->setBranchIds();
        $user_url = $this->getJumpUrl('user') ;
        $branch_url = $this->getJumpUrl('branch') ;
        $user_contents = '您好！感谢您对订单'.$this->FormatSpan($user_url,$this->Assemble['product_title']).'的验收确认,请您对本次服务做出评价,期待再次为您服务。';
        $branch_contents = '您好！您有1笔服务订单"'.$this->FormatSpan($branch_url,$this->Assemble['product_title']).',客户已确认验收，服务已完成。';
        $this->sentSystemMessage($this->Assemble['user_id'],$user_contents);
        $this->sentSystemMessage($this->Assemble['service_ids'],$branch_contents);
    }
    /*
     * @source topics-0017
     * @title 客户取消订单,订单关闭
     */
    public function sendSysUserRefuseOrder($order_id){
        $this->handlerOrderId($order_id);
        $res = $this->getDefaultOrderData();
        if(!$res){
            return false;
        }
        $this->setBranchIds();
        $url = $this->getJumpUrl('branch');
        $contents = '您好！您出售的订单'.$this->FormatSpan($url,$this->Assemble['product_title']).'客户已取消订单，请查看客户取消原因。';
        $this->sentSystemMessage($this->Assemble['service_ids'],$contents);
    }
    /*
     * @source topics-1006
     * @title 商户未收到款
     */
    public function sendSysOrderPayMessage($order_id,$second){
        $this->handlerOrderId($order_id);
        $res = $this->getDefaultOrderData();
        if(!$res){
            return false;
        }
        $url = $this->getJumpUrl('user');
        $contents = '您好！您有订单'.$this->FormatSpan($url,$this->Assemble['product_title']).'需要及时付款,否则'.( 24 * ( 3 - $second ) ).'小时后系统将自动关闭。';
        $this->sentSystemMessage($this->Assemble['user_id'],$contents);
    }
    /*
     * @source topics-0018
     * @title 客户超时未付款,订单自动关闭
     */
    public function sendSysOrderAutomaticClose($order_id){
        $this->handlerOrderId($order_id);
        $res = $this->getDefaultOrderData();
        if(!$res){
            return false;
        }
        $this->setBranchIds();
        $user_url = $this->getJumpUrl('user') ;
        $branch_url = $this->getJumpUrl('branch') ;
        $user_contents = '您好！由于付款超时，系统已自动关闭您的'.$this->FormatSpan($user_url,'订单').'，请返回商城重新下单。';
        $branch_contents = '您好！由于客户付款超时,系统已自动关闭客户'.$this->FormatSpan($branch_url,'订单').'。';
        $this->sentSystemMessage($this->Assemble['user_id'],$user_contents);
        $this->sentSystemMessage($this->Assemble['service_ids'],$branch_contents);
    }
    /*
     * @source topics-1005
     * @title 商家完成服务,申请验收
     */
    public function sendSysCheckFinishMessage($order_id,$second){
        $this->handlerOrderId($order_id);
        $res = $this->getDefaultOrderData();
        if(!$res){
            return false;
        }
        $url = $this->getJumpUrl('user');
        $contents = '您好！您有1笔服务订单'.$this->FormatSpan($url,$this->Assemble['product_title']).'等待验收，剩'.( 24 * ( 3 - $second ) ).'小时系统将自动确认验收。';
        $this->sentSystemMessage($this->Assemble['user_id'],$contents);
    }
    /*
     * @source topics-1007
     * @title 客户超时未验收,订单自动验收,待评价
     */
    public function sendSysSystemAcceptance($order_id){
        $this->handlerOrderId($order_id);
        $res = $this->getDefaultOrderData();
        if(!$res){
            return false;
        }
        $this->setBranchIds();
        $user_url = $this->getJumpUrl('user') ;
        $branch_url = $this->getJumpUrl('branch') ;
        $user_contents = '您好！您有1笔服务'.$this->FormatSpan($user_url,'订单').'超时未验收，系统已自动确认验收，请对本次服务做出评价，期待再次为您服务。';
        $branch_contents = '您好！您有1笔服务'.$this->FormatSpan($branch_url,'订单').'客户已确认验收，服务已完成。';
        $this->sentSystemMessage($this->Assemble['user_id'],$user_contents);
        $this->sentSystemMessage($this->Assemble['service_ids'],$branch_contents);
    }
    /*
     * @source topics-0019
     * @title 微信支付成功,开始服务
     */
    public function sendSysPayedMessage($order_id){
        $this->handlerOrderId($order_id);
        $res = $this->getDefaultOrderData();
        if(!$res){
            return false;
        }
        $this->setBranchIds();
        $user_url = $this->getJumpUrl('user') ;
        $branch_url = $this->getJumpUrl('branch') ;
        $user_contents = '您好！您购买的'.$this->FormatSpan($user_url,'订单').'已支付成功，服务已开始，请保持电话畅通，方便我们及时跟进服务。';
        $branch_contents = '您好！您出售的'.$this->FormatSpan($branch_url,'订单').'您好！客户微信支付成功，请及时联系客户开始服务。';
        $this->sentSystemMessage($this->Assemble['user_id'],$user_contents);
        $this->sentSystemMessage($this->Assemble['service_ids'],$branch_contents);
    }
    /*
 * @source topics-0014
 * @title 客户延迟验收
 */
    public function sendSysTool($condition){
        $branch_id = getBrowseBranchId();
        $where["branch_id"] = $branch_id;
        $where["is_leader"] = 1;
        $user_id = M('SysUser')->where($where)->getField('id');
        $url = self::DEFAULT_URL_HEAD . "/Tool/tool_detail/id/".$condition['id'].".html";
        if($condition['type'] !=2){
            $msg = $condition['type'] == 1 ? '商标查询信息' : '核名信息';
        }else{
            $msg = '咨询信息';
        }
        $contents = '您好！您收到一条新的'.$this->FormatSpan($url,$msg);
        $this->sentSystemMessage($user_id,$contents);
    }
    //--------------------------------------New end--------------------------------//
    //获取管理人Id
    private function setBranchIds(){
        $this->Assemble['service_ids'] = D('SysUser')->getBranchManager('id');
    }
	//格式化需求点击
	private function FormatSpan($url,$content){
		$default = '<span id="system-msg" data-url="'.$url.'" style="color:blue">'.$content.'</span>';
		return $default;
	}
	private function handlerOrderId($order_id){
        $this->handlerAssemble(['order_id'=>$order_id]);
    }
    private function getJumpUrl($type){
        return $type == 'user' ?
            self::DEFAULT_URL_HEAD.self::DEFAULT_URL_CUSTOMER."/id/".$this->Assemble['order_id'].".html":
            self::DEFAULT_URL_HEAD.self::DEFAULT_URL_BLANCH  ."/id/".$this->Assemble['order_id'].".html";
    }
	private function getDefaultOrderData(){
        $assemble = $this->Assemble;
        if($assemble['order_id'] > 0){
           $res =  D($this->_EShop_model."/ComOrder")->getOrderDetailData($assemble['order_id']);
           if ($res){
               $this->handlerAssemble($res);
               return true;
           }else{
               return false;
           }
        }else{
            return false;
        }
    }
    private function handlerAssemble(array $array){
        if (is_null($array)){
            $this->Assemble = [];
        }
        $this->Assemble = $array;
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
