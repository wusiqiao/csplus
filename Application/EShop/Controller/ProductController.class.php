<?php

namespace EShop\Controller;

use Think\Controller;

class ProductController extends Controller {
    public function getStoreData(){
        $store = M('ComStore')->field('mobile')->where('branch_id = '.getBrowseBranchId())->find();
        return $store;
    }
    public function productDetailAction(){
        if(IS_GET){
            initialContactTool($this, 1, 1, 1);
            $wxUser = getCurrentWXUserInfo(false);
            //如果get中有存在邀请者，绑定两者关系
            $inviter = I("get.inviter");//邀请人
            if($inviter){
                D('DistributionRelation')->bindingWithOpenid($inviter, $wxUser);
            }

            //添加获取openid
            $this->assign('title',getComStoreData('name'));
            //新增传递category_id进来取product_id值
            if(I("get.category_id") > 0){
                $product_id =  M('ComProduct')->where('category_id = '.I("get.category_id").' and branch_id = '.getBrowseBranchId())->getField('id');
            }else{
                $product_id		=	I("get.product_id");
            }

            $ProductModel	=	D("ComProduct");
            $product_data	=	$ProductModel->getProductData($product_id);

            if($product_data['is_step'] == 1){
                $step		   = explode('&,', $product_data['step_flow']);
                $step_count	   = count($step);
                $step_count_ceil = ceil($step_count / 4); //多少组
                $step_count_remainder = $step_count % 4; //最后一组多少步骤
                foreach ($step as $key => $value) {
                    $the_ceil	=  ceil(($key+1) / 4);
                    $remainder	=  $key % 4;
                    $product_data['step_view']['data'][$the_ceil][$remainder]	=	$value;
                    if($step_count_ceil == $the_ceil){
                        $product_data['remainder'][$the_ceil] = $step_count_remainder;
                    }else{
                        $product_data['remainder'][$the_ceil] = 4;
                    }
                }
            }
            $has_commision = I("get.has_commision",0); //分享活动里面有佣金，会传入此参数
            if($_SESSION['user_type'] == USER_TYPE_COMPANY_MANAGER && $_SESSION['branch_id'] == getBrowseBranchId()){
                $this->can_buy	=	0;
            }else{
                $this->can_buy	=	1;
            }
            $this->show_share = $has_commision;
            //获取多选项
            $topics_img = $product_data['product_pic'] ? $product_data['product_pic'] : getCategoryListDefaultPic();
            $topics_list= $ProductModel->getProductTopics($product_id);
            $first_attr = $ProductModel->getFirstAttrs($product_id);
            $this->first_attr = $first_attr;
            $this->is_look = $ProductModel->getOrderAttrsCount($product_id) == 1 ? 1 : 0;
            $this->_handlerScopeVoucher($product_id);
            $this->assign('topics_img',$topics_img);
            $this->assign('topics_list',$topics_list);
            $this->assign('is_login',session('user_id') ? 1 : 0);
            $this->assign('product_data',$product_data);
            $this->assign('store_data',$this->getStoreData());
            //获取分享信息
            $this->setWXShareData($product_data);

            if (empty($wxUser["subscribe"])){
                $subscribeInfo["shop_qrcode"] = D("ComStore")->getQRcode();
                $subscribeInfo["shop_logo"] = getDefalutHeadPic();
                $this->assign('subscribeInfo', $subscribeInfo);
            }
            $this->openid = $wxUser["openid"];
            $this->display();
        }

    }
    //优惠券处理
    protected function _handlerScopeVoucher($product_id){
        checkLogin();
        if ($_SESSION['user_id'] && !handleIsManager()) {
            $is_dont_receive = D("SpTicket")->setUserIsReceiveCommonly($product_id);
            if ($is_dont_receive) {
                $redpacket = 'show';
                $this->assign('user_receive', $is_dont_receive);
            }else{
                $redpacket = 'dont';
            }
        }
        $this->assign('redpacket', $redpacket);
    }
    public function getAttrsCheckedAction(){
        $ProductModel	= D("ComProduct");
        $result         = $ProductModel->getAttrsChecked(I('post.pid'),I('post.tid'));
        $this->ajaxReturn($result);
    }
    public function getAttrsCaseAction(){
        $ProductModel	=	D("ComProduct");
        $result         = $ProductModel->getAttrsCase(I('post.'));
        $this->ajaxReturn($result);
    }
    public function productBuyAction(){
        if(IS_GET){
            $this->assign('title',getComStoreData('name'));
            $product_id	    =	I("get.id");
            $ProductModel	=	D("ComProduct");
            $product_data	=	$ProductModel->getProductData($product_id);
            if(handleIsManager())
            {
                $this->error("您不能购买自己的服务", '/Product/productDetail/product_id/'.$product_id.".html");
            }
            checkLogin();
            //获取属性
            $atts = $ProductModel->getAttribute(I("get."));
            if(!$atts){
                $this->error("服务属性不存在", '/Index/serviceDetail/product_id/'.$product_id.".html");
            }
            $voucher_tickets = D("ComOrder")->getProductVoucherDetail(I("get."));
            $this->ticket = $voucher_tickets;
//            var_dump($atts);die;
            $this->assign('atts',$atts);
            $this->assign('product',$product_data);
            $this->user_name = session("user_name");
            $this->mobile = session("mobile");
            $this->display();
        }else{
            $postdata				=	I("post.");
            $order					=	$postdata;
            $order['on_time']		=	time();
            $order['update_time']	=	time();
            $order['user_id']		=	$_SESSION['user_id'];
            $order['order_state']	=	ORDER_STATE_USER_BUY;
            $order['order_sn']		=	getOrderNo(SERVICE_ORDER_SN);
            $product				=	M("ComProduct")->where("id = ".$postdata['product_id'])->find();
            $order['branch_id']     =   getBrowseBranchId();
            $order['trade_type']	=	0;//担保交易
            $order['product_title']	=	$product['product_title'];//担保交易
            $order['product_category']	=	category($product['category_pid']).'-'.category($product['category_id']);//担保交易
            $attribute              =   D("ComProduct")->getAttribute(['id'=>$postdata['product_id'],'aid'=>$postdata['attribute']]);
            $order['real_cash']	    =	$attribute['real_cash'] > 0 ? $attribute['real_cash'] : 0;
            $order['product_attribute'] = json_encode($attribute['tips']);
            //添加权限条件
            $order['user_branch_id'] = getUserCompanyId();
            $order['creator_id'] = session('user_id');

            $result	=	M('ComOrder')->add($order);
            if($result){
                //topics-0001
                $url	=	'/Order/serviceDetail/id/'.$result.'/tip/1.html';
                D("ComComment")->sendSysNewOrderMessage($result);
                $is_startUp_msg = 0;
                //topics-1001
                if($attribute['real_cash'] > 0){
                    D("ComOrder")->sendWXNewOrderMessage($result,1);
                    $is_startUp_msg = 1;
                    //topics-1006
                    $timer = 24 * 60 * 60;
                    D('ESAdmin/SysMq')->add_timer($timer,WEB_ROOT.'/ReqQueue/HandleOrderPayMessage/id/'.$result);
                    D('SysReport')->addOrderReport($result,['订单生成','待客户付款']);
                }else{
                    D("ComOrder")->sendWXNewOrderMessage($result,0);
                    D('SysReport')->addOrderReport($result,['订单生成','待商户报价']);
                }
//                D("ComOrder")->sendWXNewServiceOrderMessage($result);
//                D("ComComment")->sendSystemMessageFromProductInsertOrder($result);
                echo json_encode(array('error'=>0,'msg'=>'服务购买成功!!','url'=>$url,'order_id'=>$result,'startUp'=>$is_startUp_msg));
                exit;
            }else{
                echo json_encode(array('error'=>1,'msg'=>'服务购买失败!!'));
                exit;
            }
        }
    }
    public function product_edit_stateAction(){
        if(IS_POST){
            $postdata = I('post.');
            $res = D('ComProduct')->product_edit_state($postdata);
            echo json_encode($res);
            exit();
        }
    }
    //服务的删除
    public function product_deleteAction() {
        if (IS_POST) {
            $postdata = I('post.');
            $res = D('ComProduct')->product_delete($postdata);
            echo json_encode($res);
            exit();
        }
    }
    private function setWXShareData($product_data){
        $share_data['title'] = $product_data['product_title'];
        $share_data['desc']	 = $product_data['product_desc'];
        $share_data['desc']	.= ($product_data['price_type'] == 1) ? '价格面议。': '售价:'.$product_data['real_cash'].'元/'.$product_data['unit'].'。';
        $share_data['desc'] = str_replace("\r\n","",$share_data['desc']);
        $openid = session('openid');
        $product_id = $product_data["id"];
        $share_data['link']  = WEB_ROOT . "/Product/productDetail/product_id/$product_id/inviter/$openid";
        //获取服务商头像
        $share_data['imgUrl'] = get_head_pic($product_data['product_pic']);
        $signPackage          = getWeChatInstance()->getJsSign();
        $this->assign('share_data',$share_data);
        $this->assign('signPackage', $signPackage);
    }
}
