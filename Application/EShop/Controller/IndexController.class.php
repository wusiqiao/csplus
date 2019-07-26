<?php

namespace EShop\Controller;

use Think\Controller;
use Think\Log;

class IndexController extends BaseController {
    protected $_MODEL = "SysUser";

    public function indexAction(){
        $condition['branch_id'] = session('branch_id');
        $where['is_valid'] = 0;
        $where['end_date'] = array("elt",date("Y-m-d"));
        $where['_logic'] = "or";
        $condition['_complex'] = $where;
        $branch_role = M("SysBranch")->where("id = ".$condition['branch_id'])->getField("branch_role");
        if($branch_role != ROLE_ID_COMPANY_FREE){
            $count = M("SysBranchAgreement")->where($condition)->count();
            if($count != 0){
                $this->display("user_loss");
                return ;
            }
        }
        $this->_handlerVoucher();
        //添加获取openid
        $wxUser = getCurrentWXUserInfo(false);
//        $branch_id = getBrowseBranchId();
        $adv = getAdvertiseSliderLists();
        $this->getSearchHot();
        $this->getTheTicketList();
        $this->assign('is_login',session('user_id') ? 1 : 0);
        $this->assign('adv_lists',$adv['lists']);
        $this->setWXShareData($wxUser);
        $this->getToolList();
        $this->version = time();
        //如果get中有存在邀请者，绑定两者关系
        $inviter = I("get.inviter");//邀请人
        if($inviter){
            D('DistributionRelation')->bindingWithOpenid($inviter, $wxUser);
            session("inviter", null);
        }
        $this->assign('title',getComStoreData('name'));
        initialContactTool($this, 1);
        $this->display();
    }
    //工具栏显示
    protected function getToolList(){
        $branch_tool = getRoutineSingle('tool_manager');
        if(trim($branch_tool) != '' && $branch_tool != null){
            $this->tool_list = $branch_tool == 'all' ? 'all' : explode(',',$branch_tool);
        }else{
            $this->tool_list = false;
        }
    }
    private function setWXShareData($wxUser){
        $shopData = getComStoreData("all");
        $share_data['title']  = $shopData['name'];
        $share_data['desc']	  = $shopData['share_desc'];
        $share_data['desc'] = str_replace("\r\n","",$share_data['desc']);
        $openid = session('openid');
        $share_data['link']  = WEB_ROOT . "/Index/index/inviter/".$openid;
        //获取服务商头像
        $share_data['imgUrl'] = getDefalutHeadPic();
        $signPackage          = getWeChatInstance()->getJsSign();
        $this->assign('share_data',$share_data);
        $this->assign('signPackage', $signPackage);
        $this->assign('wxUser', $wxUser);

        if (empty($wxUser["subscribe"])){
            $subscribeInfo["shop_qrcode"] = D("ComStore")->getQRcode();
            $subscribeInfo["shop_logo"] = $share_data['imgUrl'];
            $this->assign('subscribeInfo', $subscribeInfo);
        }

    }
    //优惠券处理
    protected function _handlerVoucher(){
        $TicketStockModal = D("SpTicket");
        if ($_SESSION['user_id'] && !handleIsManager()) {
            $is_dont_receive = $TicketStockModal->setUserIsReceiveCommonly();
            if ($is_dont_receive) {
                $redpacket = 'show';
                $this->assign('user_receive', $is_dont_receive);
            }else{
                $redpacket = 'dont';
            }
        }
        $this->assign('redpacket', $redpacket);
    }
    //展示自定义页面
    public function showBannerPageAction($id){
        $branch_id = getBrowseBranchId();
        $show_banner = M('ComBanner')->field('content,title')->where('id = '.$id.' and branch_id = '.$branch_id)->find();
        $this->assign('content',$show_banner['content']);
        $this->assign('title',$show_banner['title']);
        $this->display('banner_page');
    }
    //输出服务热度
    public function getSearchHot(){
        $list = D('ComProduct')->getCategoryInstallHot();
        $this->assign('hot_category_count',count($list));
        $this->assign('hot_categoty',$list);
    }
    /*
     * 处理用户注册   分享首页时使用
     * 根据openid创建用户表信息 用于Post
     * 流程 是否登录 -> 是否注册 -> 注册
     * 未完成 0416
     * */
    public function HandlerUserRegionAction(){
        $openid = session('openid');
        $branch_id = getBrowseBranchId();
        if(session('user_id')){
            return false;
        }
        $is_login = D('SysUser')->isLoginFromOpenid($openid);
        if($is_login){
            return false;
        }else{
            $default_name            = 'C_'.substr(md5(microtime(true)), 0, 6);
            $condition['branch_id']  = $branch_id;
            $condition['head_pic']   = session('head_pic') ? session('head_pic') : getDefalutHeadPic();
            $condition['name']       = session('nickname') ? removeEmoji(session('nickname')) : $default_name;
            $condition['password']   = md5_plus('123456');
            $condition["reg_time"]   = begtime();
            $condition["last_time"]  = begtime();
            $condition["last_ip"]    = get_client_ip(0, 1);
            $condition["user_type"]  = USER_TYPE_CUSTOMER;
            $condition["role_ids"]   = USER_TYPE_CUSTOMER;
            $condition['is_valid']   = 1;
            D('SysUser')->add($condition);
        }
    }
    public function testRefundAction(){
        $unline_id = 171;
        $state     = 1;
        $remark    = '';
        $err_msg = D("ComFinance")->unlinesAudit($unline_id, $state, $remark);
        var_dump($err_msg);
    }
    public function classificationAction(){
        $this->assign('title',getComStoreData('name'));
        $this->getTopCategoryHot();
        $this->display();
    }
    public function checkLoginReceiveAction(){
        //登录领取代金券
        if($_SESSION['user_id']){
            redirect('/Index');
        }else{
            checkLogin();
        }
    }
    public function getTheTicketList(){
        $Micorstore     =   D('SpTicket');
        $field			=	't.reduce_cost,t.least_cost,a.activity_begin_date,a.activity_end_date,a.id as activity_id';
        $ticket_list	=	$Micorstore->getServiceMayReceiveTicket($field);

        foreach ($ticket_list as $key => $value) {
            $is_surplus									=	$Micorstore->getIsTicketStockSurplus($value['activity_id']);
            if(!$is_surplus){
                unset($ticket_list[$key]);
            }
            $ticket_list[$key]['activity_begin_date']	=	date('Y-m-d',$value['activity_begin_date']);
            $ticket_list[$key]['activity_end_date']		=	date('Y-m-d',$value['activity_end_date']);
            $ticket_list[$key]['is_receive']			=	$Micorstore->getUserMayReceiveTicket($value['activity_id']);
        }
        $this->assign("ticket",$ticket_list);
    }
    //代金券领取
    public function ticket_receiveAction(){
        if(IS_POST){
            checkLogin();
            $activity_id	=	I("post.activity_id");
            $Microstore	    =	D("SpTicket");
            //判断登录的用户是不是该商店的经营者
            $is_admin	    =	handleIsManager();
            if($is_admin){
                echo json_encode(array('error'=>1,'msg'=>'领取失败,不能领取自己商店的代金券'));
                die;
            }
            //判断领取条件
            $is_receive	=	$Microstore->getUserMayReceiveTicket($activity_id);
            if($is_receive == 1){
                echo json_encode(array('error'=>1,'msg'=>'领取失败,您已经领取过该代金券'));
                die;
            }elseif($is_receive == 5){
                //判断用户类型是否符合领取条件
                echo json_encode(array('error'=>1,'msg'=>'领取失败,业务员暂时无法领取'));
                die;
            }
            //判断该活动的代金券是否还有剩余
            $is_surplus	=	$Microstore->getIsTicketStockSurplus($activity_id);
            if($is_surplus	==	0){
                echo json_encode(array('error'=>1,'msg'=>'领取失败,该代金券已经领取完了'));
                die;
            }
            //领取代金券的操作 返回领取数量 满 减金额
            $data		=	$Microstore->userReceiveTicket($activity_id);
            if($data){
                echo json_encode(array('error'=>0,'activity'=>$data));
                die;
            }
        }
    }
    //根据各大分类 获取热门类型
    public  function getTopCategoryHot(){
        $list = D('ComProduct')->getTopCategoryHot();
        list($lists, $top) = $list;
        $this->assign('top',$top);
        $this->assign('lists',$lists);
    }

    public function getMpQrcodeAction(){
        die(getMPInstance()->getMPQrcodeB("frompc","pages/eshop/eshop", 60));
    }

    //判断客户session中是否有绑定的公司，没有则默认第一家绑定的公司
    public function handlerSessionCompany(){
        $company = D("ComAgreement")->getUserCompany();
        //有公司且（session中没有公司或公司不在可见公司中），默认第一家公司
        if($company && (!$_SESSION['wrk_company_id'] || !in_array($_SESSION['wrk_company_id'],array_column($company,"value")))){
            session("wrk_company_id",$company[0]['value']);
            session("wrk_company_name",$company[0]['text']);
        }elseif(!$company && $_SESSION['wrk_company_id']){
            unset($_SESSION['wrk_company_id']);
            unset($_SESSION['wrk_company_name']);
        }
        $this->assign('company_id',$_SESSION['wrk_company_id']);
        $this->assign('company_name',$_SESSION['wrk_company_name']);
        //获取当前用户可见公司资金的公司，判断是否包含session中的wrk_company_id,不包含则不可见
        $aj = D('ComAccountJurisdiction');
        $aj->getVisiblersCompanys();
        $visual_companys = $aj->getStore('visiblers');
        $this->assign('show_money',in_array($_SESSION['wrk_company_id'],$visual_companys));
    }

    public function userAction(){
        $this->assign('versions', time());
        $this->assign('title',getComStoreData('name'));
        //注册前获取openid
        if(session('user_id') > 0){
            $manager = handleIsManager() ? 1 : 0;
            $this->assign('manager',$manager);
            if(!$manager){
                //判断客户session中是否有绑定的公司，没有则默认第一家绑定的公司
                $this->handlerSessionCompany();
            }
            $this->_assign_user_data();
            $this->assign('is_login',1);
            $this->handlerHasCompany();
            $this->display();
        }else{
            $this->assign('name','游客');
            if(empty(session('openid'))){
                $wechat = getWeChatInstance();
                $json = $wechat->getOauthAccessToken();
                if ($json) {
                    $_SESSION['openid'] = $json['openid'];
                    //盘点是否存在openid对应的用户信息
                    $user_data = D('SysUser')->automationLoginFromOpenid($json['openid'],true);
                    if($user_data){
                        setUserSession($user_data);
                        $manager = handleIsManager() ? 1 : 0;
                        $this->assign('manager',$manager);
                        $this->_assign_user_data();
                        $this->assign('is_login',1);
                    }else{
                        $wechat_user = $wechat->getUserInfo($json['openid']);
                        if ($wechat_user) {
                            $_SESSION['head_pic'] = $wechat_user['headimgurl'];
                            $_SESSION['nickname'] = $wechat_user['nickname'];
                        }
                        $_SESSION['head_pic'] = $_SESSION['head_pic']  ? $_SESSION['head_pic'] : getDefalutHeadPic();
                        $this->assign('head_pic',$_SESSION['head_pic']);
                        $this->assign('is_login',0);
                        $this->assign('manager',0);
                    }
                    //判断客户session中是否有绑定的公司，没有则默认第一家绑定的公司
                    $this->handlerSessionCompany();
                    $this->handlerHasCompany();
                    $this->display();
                } else {
                    header("Location:" . getWeChatRedirectUrl(false));
                    die();
                }
            }else{
                //盘点是否存在openid对应的用户信息
                $user_data = D('SysUser')->automationLoginFromOpenid($_SESSION['openid'],true);
                if($user_data){
                    setUserSession($user_data);
                    $manager = handleIsManager() ? 1 : 0;
                    $this->assign('manager',$manager);
                    $this->_assign_user_data();
                    $this->assign('is_login',1);
                }else {
                    $this->assign('head_pic', $_SESSION['head_pic'] ? $_SESSION['head_pic'] : getDefalutHeadPic());
                    $this->assign('is_login', 0);
                    $this->assign('manager', 0);
                }
                //判断客户session中是否有绑定的公司，没有则默认第一家绑定的公司
                $this->handlerSessionCompany();
                $this->handlerHasCompany();
                $this->display();
            }
        }
    }
    public function handlerHasCompany()
    {
        //判断是否有绑定公司
        if ( session('user_type') == USER_TYPE_COMPANY_MANAGER ){
            $condition['a.parent_id'] = $this->user_branch;
            $company_count = D('sysBranch')->setDacFilter('a')->where($condition)->count();
            $this->assign('has_company',$company_count > 0 ? 1 : 0);
            $this->isAdmin = 1;
        } else {
//            var_dump($_SESSION);die;
            $condition['user_branch.user_id'] = session('user_id');
            $condition['branch.type'] = ORG_COMPANY;
//            $condition['user_branch.type'] = 1;
            $company_count =
                D('sysBranch')
                    ->setDacFilter('branch')
                    ->join('sys_user_branch as user_branch on user_branch.branch_id = branch.id')
                    ->where($condition)->count();
            $this->assign('has_company',$company_count > 0 ? 1 : 0);
            $this->isAdmin = 0;
        }
    }
    public function getProductListsAction(){
        $postdata = I("post.");
        $result = D("ComProduct")->getHomePageProductLists($postdata);
        $this->ajaxReturn($result);
    }
    protected function _assign_user_data() {
        $susu = M($this->_MODEL)->where("id=" . $_SESSION['user_id'])->find();
        $susu['head_pic'] = $susu['head_pic'] == '' ? getDefalutHeadPic() : $susu['head_pic'];
        $this->assign('head_pic',$susu['head_pic']);
        $this->assign('name',$susu['name']);
    }

    public function search_resultAction(){
        $getdata = I("get.");
        $this->assign('title',getComStoreData('name'));
        $hot_search = getHotSearchList();
        $this->assign("hot_category", $hot_search['category']);
        $view_category['name'] = '查看更多';
        $view_category['id'] = '';
        if ($getdata['cate_id']) {
            if (!array_key_exists($getdata['cate_id'], $hot_search['category'])) {
                $view_category['name'] = category($getdata['cate_id']);
                $view_category['id'] = $getdata['cate_id'];
            }
        }
        if ($_SESSION['user_type'] == USER_TYPE_COMPANY_MANAGER) {
            $this->assign('role', 'service');
        }

        $this->assign('view_category', $view_category);
        $this->assign('data', $getdata);
        $this->_assign_base_data_get_screen();
        $this->display();
    }
    public function get_search_listsAction(){
        $postdata = I("post.");
        $postdata = preg_replace('# #','',$postdata);
        $result = D("ComProduct")->getTheRgionProductLists($postdata);
        $this->ajaxReturn($result);
    }
    /*     * ********   用户购买服务商服务 end   ********* */

    protected function _assign_base_data_get_screen($type = false) {
        $branchId = getBrowseBranchId();
        //类型二级选择
        $cate_list = M('ComCategory')
            ->where([
                'branch_id' => $branchId,
                'is_valid' => 1,
            ])
            ->field("id as value,name as text,id,parent_id")
            ->order("level asc,sort desc")
            ->cache(true)
            ->select();
        $cate = list_to_tree_get_screen($cate_list, "id", "parent_id", "children");
        if($type){
            $add = array(
                'value'     => 0,
                'text'      => '不限',
                'id'        => 0,
                'parent_id' => 0
            );
            array_unshift($cate,$add);
        }
        $list = M('ComCategory')
            ->alias('a')
            ->where([
                'a.branch_id' => $branchId,
                'a.is_valid' => 1,
                'a.name' => ['like', '%' . I('get.keyword') . '%']
            ])
            ->join('com_product b ON a.id = b.category_id')
            ->field('b.id')
            ->select();
        if( count($list) == 1){
            redirect('/Product/productDetail/product_id/' . $list[0]['id'].'.html');
        }
        // echo '<pre>';
        // var_dump($cate);die;
        $this->assign('category', json_encode($cate));
    }
    /*
     * 服务商公司评价列表
     * Author: Lynn start June 20th
     */
    public function theCommentsHistoryAction(){
        $start = I('get.start', 0, 'intval');
        $num   = I('get.n', 0, 'intval');
        $product_id	=	I("post.product_id");
        $comments 	= M('ComComment co')
            ->join('com_order o on o.id=co.obj_id')
            ->join('com_product pro on pro.id=o.product_id')
            ->field('co.*,pro.product_title,o.contacts')
            ->where("o.order_state = ".ORDER_STATE_HAS_JUDGE." and pro.id = ".$product_id)
            ->limit($start,$num)
            ->order("co.comment_time desc")
            ->select();
        foreach ($comments as $key => $value) {
            $user	=	M("SysUser")->where("id = ".$value['origin_id'])->find();
            $sayList['lists'][]	=	array(
                'name'          => $user['name'],
                'view_star'		=> returnViewStar($value['star']),
                'head_url'		=> getUserPicHandle($user['head_pic'],'comments'),
                'comment_time'	=> date('Y年m月d日',$value['comment_time']),
                'task_title'	=> $value['product_title'],
                'content'		=> $value['content'],
            );
        }
        $sayList['count']	=	count($comments);
        echo json_encode($sayList);
    }

    public function shopAskAction() {
        $title = "给他留言";
        $this->assign('title', $title);
        checkLogin();
        if (handleIsManager()) {
            $this->error("您不能给自己的微店留言", '/Index');
        }
        $branch_id = getBrowseBranchId();
        redirect('/Liuyan/Me/branch_id/' . $branch_id);
    }
    function returnViewStar($var) {
        if(!($var > 0)){
            $var = 0;
        }
        $startMax	=	5;
        $hollowCount=	$startMax - $var;
        $html		=	'';
        for ($i=0; $i < $var; $i++) {
            $html	.=	'<span class="star-y"></span>';
        }
        for ($i=0; $i < $hollowCount; $i++) {
            $html	.=	'<span class="star-n"></span>';
        }
        return $html;
    }

    public function wechatAction(){
        A("WX")->indexAction();
    }
    /***share start**/
    public function shareQrcodeAction(){
        $model 		=	D("SysUser")->getUserQrcodeData("shareRegister");
        $user_data	=	M("SysUser")->field('mobile,name')->where("id = ".$_SESSION['user_id'])->find();
        $view_name  =   $user_data['name'];
        $model['title'] = '财穗快线，财税服务行业的58同城！';
        $model['desc']  = $view_name.'平台推广期间（10月1日-10月31日），免费开店、免费推广、还送现金好礼！';
        $this->assign('model',$model);
        $this->display();
    }
    public function shareRegisterAction(){
        //注册前获取openid
        $wechat = getWeChatInstance();
        $json = $wechat->getOauthAccessToken();
        if ($json) {
            $_SESSION['openid'] = $json['openid'];
            $user_id	=  I("get.id");
            if (session('user_id')) {
                $this->redirect('Index/shareSuccess/id/'.$user_id);
            }
            $model 		=	D("SysUser")->getWxShareData($user_id);
            $this->assign('model',$model);
            $this->display();
        } else {
            header("Location:" . getWeChatRedirectUrl());
            die();
        }
    }
    /*
*邀请有奖扫码页面
*/
    public function shareSuccessAction(){
        $user_id	=  I("get.id");
        $model 		=	D("SysUser")->getWxShareData($user_id);
        $this->assign('model',$model);
        $this->display();
    }
    /***share end**/

    public function addOrderCommisionTestAction($order){
        D("ESAdmin/ComOrder")->addOrderCommision($order);
    }
}
