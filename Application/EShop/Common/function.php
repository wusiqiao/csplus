<?php

function sendsms($mobile, $tpl, $message) {
    return D('SmsLog')->send_sms_message($mobile, $tpl, $message);
}
function getBrowseBranchId(){
    if(defined('M_SHOP_ID')){
        return M_SHOP_ID;
    }

    $branch_id = SHOP_ID;
    if (empty($branch_id)){
        $branch_id =  I("get.bid");
    }
    if (empty($branch_id)){
        redirect('/Hole/ErrorWeb');
        die();
    }

    return $branch_id;

}
function getUserCompanyId(){
    return isset($_SESSION['currBranchId']) && $_SESSION['currBranchId'] > 0 ? $_SESSION['currBranchId'] : null ;
}
//判断该登录用户是否有设置手机号码
function GetUserIsSetMobile($data = ''){
   $mobile =  ($data == '')  ? session('mobile') :
            (is_array($data) ? $data['mobile']   : $data);
   return strlen($mobile) != 11 ? false : true;
}
/**根据模板内容提取唯一模板id
 * @param $content
 * @return string
 */
function getTemplateIdentKey($content){
    return md5(str_replace(array("\n","\r"),"", $content));
}
//返回设置手机号码地址
function GetSetMobileLocation(){
    return '/User/index.html';
}
function getLoginUserMobile(){
    return session('mobile') ? session('mobile') : '';
}

function getLoginInterfacePic(){
    $system_set_pic = getRoutineSingle('login_interface_pic');
//    $default_pic    = IMG_URL.'login_logo.png';
    if(empty($system_set_pic)){
        return false;
    }
    return $system_set_pic;
}
function getRoutineSingle($inc){
    $branch_id = getBrowseBranchId();
    $routine = M('ComStore')->field($inc)->where('branch_id ='.$branch_id)->find();
    return $routine[$inc];
}
function getWxConfigData(){
    $branch_id = getBrowseBranchId();
    $routine = M('WxConfig')->where('branch_id ='.$branch_id)->find();
    return $routine;
}
//首页 - 服务列表默认图片
function getCategoryListDefaultPic(){
    return IMG_URL.'nopic.gif';
}
//获取店铺信息
function getComStoreData($inc){
    if($inc == 'all'){
        $store = M('ComStore')->where('branch_id = '.getBrowseBranchId())->find();
        return $store;
    }else{
        $store = M('ComStore')->field($inc)->where('branch_id = '.getBrowseBranchId())->find();
        return $store[$inc];
    }
}
//全局店铺号码
function getConstStoreTel(){
    if (session('user_type') == USER_TYPE_COMPANY_MANAGER){
        return false;
    }
    if (isset($_SESSION['store_tel']) === true){
        return $_SESSION['store_tel'];
    }
    $store =getComStoreData('all');
    if (isset($store['tel']) || isset($store['mobile']) || strlen($store['tel']) > 0 || strlen($store['mobile']) == 11){
        $store_tel = (isset($store['tel']) && strlen($store['tel']) > 0) ?  $store['tel'] : $store['mobile'];
        session('store_tel',$store_tel);
        return $store_tel;
    }else{
        return false;
    }
}
//时间格式化
function formatRevealTime($time) {
    $only_time = 3 * 60; //三分钟前领取的显示时间为刚刚
    $before_i_time = 60 * 60; //1小时前领取的显示时间为几分钟前
    $before_d_time = 24 * 60 * 60; //一天前的显示时间为几小时前
    $before_date_time = 7 * 24 * 60 * 60; //7天后的显示时间为具体时间
    $on_time = time(); //当前时间
    $past_time = $on_time - $time;
    if ($past_time < $only_time) {
        $view_time = '刚刚';
    } elseif ($past_time >= $only_time && $past_time < $before_i_time) {
        $view_time = floor($past_time / 60) . '分钟前';
    } elseif ($past_time >= $before_i_time && $past_time < $before_d_time) {
        $view_time = floor($past_time / (60 * 60)) . '小时前';
    } elseif ($past_time >= $before_d_time && $past_time < $before_date_time) {
        $view_time = floor($past_time / (24 * 60 * 60)) . '天前';
    } else {
        $view_time = date('m月d日', $time);
    }
    return $view_time;
}

//全局留言
function getConstStoreLiuyan(){
    if (session('user_type') == USER_TYPE_COMPANY_MANAGER){
        return false;
    }
    if (isset($_SESSION['store_liuyan']) === true){
        return $_SESSION['store_liuyan'];
    }else{
        $branch_id = getBrowseBranchId();
        session('store_liuyan','/liuyan/Me/branch_id/'.$branch_id);
        return '/liuyan/Me/branch_id/'.$branch_id;
    }
}
function getWxPayOptions($inc = 'all'){
    $branch_id = getBrowseBranchId();
    $options = M("WxConfig")->field('token,appid,appsecret,encoding_aeskey,wx_pay_key,wx_mchid')->where('branch_id = '.$branch_id)->find();
    return $inc == 'all' ? $options : $options[$inc];
}
/**
 * 	产品状态
 * @param type $product
 * @return type
 */
function product_state($state){
    switch ($state) {
        case PRODUCT_STATE_AUDITION:
            return '审核中';
            break;
        case PRODUCT_STATE_DONT_GROUNDING:
            return '未上架';
            break;
        case PRODUCT_STATE_ON_GROUNDING:
            return '出售中';
            break;
        case PRODUCT_STATE_DOWN_GEOUNDING:
            return '已下架';
            break;
        case PRODUCT_STATE_AUDIT_FAILURE:
            return '已关闭';
            break;
        default:

            break;
    }
}
//是否开启三级分销
function isToggleDistribution(){
    return (integer)getRoutineSingle('distribution_toggle') == 1 ? true : false;
}
/**
 * 一级会员奖励金
 */
function getMemberBountyOne() {
    $fee = floatval(getRoutineSingle("member_bounty_one")/100);
    return $fee;
}
/**
 * 二级会员奖励金
 */
function getMemberBountyTwo() {
    $fee = floatval(getRoutineSingle("member_bounty_two")/100);
    return $fee;
}
function handleIsManager(){
    return (int)$_SESSION['user_type'] === USER_TYPE_COMPANY_MANAGER;
}
/*
 * author Lynn
 * created Aug 17,2018
 */
function stringToHump($inc)
{
    $str = preg_replace_callback('/([-_]+([a-z]{1}))/i',function($matches){
        return strtoupper($matches[2]);
    },$inc);
    return $str;
}
function getAdvertiseSliderLists($num = 4, $adv_type = 'homepage') {
    //获取广告轮播图 只取四张
    $adv_where['switch'] = 1; //广告开启的!
    $adv_where['view'] = $adv_type; //广告类型
    $adv_where['branch_id'] = getBrowseBranchId();
    $adv_lists = M("ComBanner")->field("pic,url,url_switch,url_type,id")->where($adv_where)->order("orders desc")->limit(0, $num)->select();
    foreach ($adv_lists as $key => $val){
        if($val['url_switch'] == 1 && $val['url_type'] == 0){
            if(strpos($val['url'],'/uploads') === false && strpos($val['url'],'caisuikx') === false && strpos($val['url'],'http') === false){
                $adv_lists[$key]['url'] =  'http://'.$val['url'];

            }
        }elseif($val['url_switch'] == 1 && $val['url_type'] == 1){
            $adv_lists[$key]['url'] =  '/Index/showBannerPage/id/'.$val['id'];
        }
    }
    $adv['count'] = count($adv_lists);
    $adv['lists'] = $adv_lists;
    return $adv;
}
function getDefalutHeadPic(){
    $branch_id = getBrowseBranchId();
    $routine = M('ComStore')->field('default_header_pic')->where('branch_id ='.$branch_id)->find();
    $url = empty($routine['default_header_pic'])? IMG_URL.'head_pic/logo.png' : $routine['default_header_pic'] ;
    return (empty($_SERVER["HTTPS"])?"http://":"https://").$_SERVER["HTTP_HOST"].'/' .$url;
}
//默认的热门地区和类型
function getHotSearchList(){
    $data['region']	=	array(
        '1321' => '福建/厦门',
        '1350' => '福建/泉州',
        '1306' => '福建/福州',
    );
    $data['category']	=	array(
        '6'=>'公司注册',
        '7'=>'公司变更',
        '12'=>'社保代办',
    );
    return $data;
}
function payLog($msg) {
    if (APP_DEBUG) {
//        $logHandler = new \CLogFileHandler("./log/wxpay_" . date('Y-m-d') . '.log');
//        \Log::Init($logHandler, 15);
        \Log::DEBUG($msg);
    }
}

function setPayParams($inputObj) {
    $wx_pay = getWxConfigData();
    $inputObj->SetAppid($wx_pay['appid']); //公众账号ID
    $inputObj->SetMch_id($wx_pay['wx_mchid']); //商户号
    $inputObj->SetAPPKey($wx_pay['wx_pay_key']);
}

/**
 * 设置小程序参数
 */
function miniPayParams($inputObj) {
    $wx_pay = getWxConfigData();
    $inputObj->SetAppid($wx_pay['xcx_appid']); //小程序appid
    $inputObj->SetMch_id($wx_pay['wx_mchid']); //商户号
    $inputObj->SetAPPKey($wx_pay['wx_pay_key']);
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
/**
 * 用户头像链接
 * @param type $url
 * @return type
 */
function get_head_pic($url) {
    if (empty($url)) {
        return WEB_ROOT . "/Public/images/logo.png";
    }
    if (stripos($url, "http") !== false) {
        return $url;
    } else {
        $delimiter = (stripos($url, "/") === 0)?"":"/";
        return WEB_ROOT .$delimiter. $url;
    }
}
function curl_get_contents($url, $timeout = 10) {
    $curlHandle = curl_init();
    curl_setopt($curlHandle, CURLOPT_URL, $url);
    curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curlHandle, CURLOPT_TIMEOUT, $timeout);
    $result = curl_exec($curlHandle);
    curl_close($curlHandle);
    return $result;
}
/**
 * 获取获取文件对应图片 主要用于留言中 显示文件图片
 * @param string 文件的url
 * @param type   默认文件图标前缀
 * @param type   是否输出type
 * @date Jan 5,2018
 */
function getAskUploadFileImages($file,$prefix = 'ask-',$is_type = false){
    if(!$file){
        return '';
    }
    $temp       =   explode('.', $file);
    $defalut_url=   IMG_URL.'Index_img/';
    $defalut_ext=   '.png';
    $extension  =   $temp[count($temp)-1];
    $type       =   '';
    $imgExts    =   explode(',', 'jpg,gif,png,jpeg,JPGE');
    $excelExts  =   explode(',', 'xlsx,xlsm,xltx,xltm,xls,xlsb,xlsm');
    $wordExts   =   explode(',', 'doc,docx,docm,dotx,dotm');
    //如果是图片
    if(in_array($extension, $imgExts)){
        $type       = 'image';
        $typeName   = '[图片]';
    }
    //如果是word
    if(in_array($extension, $wordExts)){
        $type       = 'word';
        $typeName   = '[文档]';
    }
    //如果是excel
    if(in_array($extension, $excelExts)){
        $type       = 'excel';
        $typeName   = '[表格]';
    }
    //如果是text
    if($extension == 'txt'){
        $type       = 'text';
        $typeName   = '[文本]';
    }
    //如果是pdf
    if($extension == 'pdf'){
        $type       = 'pdf';
        $typeName   = '[pdf]';
    }
    //如果都不是的话
    if($type == ''){
        $type       = 'other';
        $typeName   = '[文件]';
    }
    if($is_type){
        return array('type'=>$type,'type_name'=>$typeName);
    }else{
        return $type == 'image' ? $file : $defalut_url.$prefix.$type.$defalut_ext;
    }

}
function timeline($var, $type = 0) {
    if ($var == '' or $var == 0) {
        return "-";
    } elseif ($type == 0) {
        return date("Y-m-d H:i:s", $var);
    } elseif ($type == 1) {
        return date("Y-m-d", $var);
    } elseif ($type == 2) {
        return date("Y", $var);
    } elseif ($type == 3) {
        return date("Y-m", $var);
    }
}

function getOrderNo($prefix = "") {
    return $prefix . date("YmdHis") . rand(100, 900);
}
function getUserPicHandle($pic,$type = 'default') {
    if($pic	==	'' || empty($pic)){
        if($type == 'default'){
            $pic	=	IMG_URL.'revision/logo-opacity.png';
        }elseif ($type == 'comments') {
            $pic	=	IMG_URL.'voucher/pic-min.png';
        }
    }
    return $pic;
}
function ception_phone($mobile) {
    return substr($mobile, 0, 3) . "***" . substr($mobile, -4);
}
function getMessageRemark($msg = "") {
    return $msg . getWxTemplateId('noticeInformation');
}
function getWxTemplateId($inc){
    $inc = strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $inc));
    $inc = 'wx_'.$inc;
    $branch_id = getBrowseBranchId();
    $template_id  = M('WxConfig')->where('branch_id = '.$branch_id)->getField($inc);
    return (trim($template_id) == '' || !$template_id) ? false : trim($template_id);
}
function category($id){
    $db_table = M('ComCategory');
    $result = $db_table -> where("id = ".$id) -> find();
    return $result['name'];
}
//获得财穗快线平台信息
/*
 * @tel             客服电话
 * @contact         联系人
 * @address         地址
 * @platform_name   公司名称/平台名称
 * @upline_data     上线时间
 * @payee           收款人
 * @bank_account    开户行
 * @card_number     卡号
 * */
function get_cskx_platform_message() {
    $branch_id = getBrowseBranchId();
    $platform = M('ComStore')->field('unline_card_number,unline_payee,unline_bank_account')->where('branch_id ='.$branch_id)->find();
    $cskx_platform_message['payee']['value'] = $platform['unline_payee'];
    $cskx_platform_message['bank_account']['value'] = $platform['unline_bank_account'];
    $cskx_platform_message['card_number']['value']  = $platform['unline_card_number'];
    return $cskx_platform_message;
}
function begtime() {
    return strtotime(date("Y-m-d G:i:s"));
}
function getLastVisitUrl($default) {
    if (empty($_SESSION["LAST_VISIT_URL"])) {
        $last_url = $default;
    } else {
        $last_url = $_SESSION["LAST_VISIT_URL"];
        unset($_SESSION["LAST_VISIT_URL"]);
    }
    return $last_url;
}
/**
 * 获取第三方渠道手续费用比例，默认取参数
 */
function getThirdPartyFeeRate() {
//    $fee = floatval(getParamValue("recharge_fee"));
    $fee = 0.03;
    return $fee;
}
function checkLogin() {
    if (empty($_SESSION['user_id']) || ($_SESSION["branch_id"] != getBrowseBranchId())) {
        $_SESSION["branch_id"] = getBrowseBranchId();
        $wechat_user = getCurrentWXUserInfo(false);
        if(strpos($wechat_user['openid'],'vo_') === 0 && isRemoteHost()){ //本地测试要可以继续
            redirect('Api/firewall');
            die;
        }
        session('openid',$wechat_user['openid']);
        $user_data = D('SysUser')->isLoginFromOpenid($wechat_user['openid'],true);
        if($user_data){
            setUserSession($user_data);
        }else{
            //公网访问并且不是微信浏览器，强制微信登录，本地测试可以通过
//            if (isRemoteHost() && !isWechatBrower()){
//                die(json_encode(array("error" => "1", "msg" => "请在微信上登录")));
//            }
            session('head_pic',  $wechat_user['headimgurl']);
            session('nickname',  $wechat_user['nickname']);
            session('is_follow', $wechat_user['subscribe']);
            session('followed_at', $wechat_user['subscribe_time']);
            $result = D("SysUser")->userRegisterSilence(session('openid'), session('nickname'), session('head_pic'));
            ActionLog($result, "登录");
//            $_SESSION["LAST_VISIT_URL"] = WEB_ROOT . $_SERVER['REQUEST_URI'];
            //redirect(getDefaultUrlByRole($role_id));
//            redirect('/Store/my');
        }
    }else{
        //更新用户角色
        $user_data = D('SysUser')->where('id = '.$_SESSION['user_id'])->find();
        if ($user_data['user_type'] != session('user_type') || $user_data['role_ids'] != session('user_role') || $user_data['updated_at'] != session('updated_at')){
            setUserSession($user_data);
        }
    }
}
function ActionLog($user_id, $content) {
    $Ip = new \Org\Net\IpLocation('UTFWry.dat');
    $location = $Ip -> getlocation(getIP());
    if ($location['country'] == "") {
        $location['country'] = "未知";
    }
    $log = D("SysUserLog");
    $log -> user_id = $user_id;
    $log -> log_ip = getIP();
    $log -> log_address = $location['country'];
    $log -> log_action = $content;
    $log -> log_time = begtime();
    $log -> add();
}
function getIP() {
    global $ip;
    if (getenv("HTTP_CLIENT_IP"))
        $ip = getenv("HTTP_CLIENT_IP");
    else if (getenv("HTTP_X_FORWARDED_FOR"))
        $ip = getenv("HTTP_X_FORWARDED_FOR");
    else if (getenv("REMOTE_ADDR"))
        $ip = getenv("REMOTE_ADDR");
    else
        $ip = "Unknow";
    return $ip;
}
function check_code($code, $id = "") {
    $verify = new \Think\Verify();
    return $verify -> check($code, $id);
}
function isMicroMessengerBrower() {
    return (stripos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false);
}
/**
 * 获取平台信息资料
 * @param type $name
 * @return type
 */
function getPlatformValue($name) {
//    $paramDatas = S("PLATFORM_INFO");
//    if (!$paramDatas) {
//        $list = M("PlatformInfo")->where("1=1")->select();
//        foreach ($list as $value) {
//            $paramDatas[$value["name"]] = $value["value"];
//        }
//        S("PLATFORM_INFO", $paramDatas);
//    }
    $paramDatas['tel'] = 15559080811;
    return $paramDatas[$name];
}
function order_stateing($order) {
    $result = "其他状态";
    switch (intval($order['order_state'])) {
        case ORDER_STATE_USER_BUY:
            if ($order['surety_state'] == ORDER_PAY) {
                $result = "待服务";
            } elseif ($order['surety_state'] == ORDER_DONT_PAY ) {
                $condition_str = 'order_sn   = \''.$order['order_sn'].'\' and ';
                $condition_str.= 'money_type = '.FIN_ORDER_LINE_PAY.' and ';
                $condition_str.= 'Source     = '.FIN_PAY_OFFLINE  .' and ';
                $condition_str.= 'pay_status = 0';
                $res = M('ComRecharge')->where($condition_str)->order('ctime desc')->find();
                if($res){
                    $result = "付款中";
                }else{
                    $result = "付款中";
                }

            } elseif ($order['surety_state'] == ORDER_DONT_PAY){
                $result = "付款中";
            }
            break;
        case ORDER_STATE_SERVICE:
            $result = "服务中";
            break;
        case ORDER_STATE_WAITING_CHECK:
            $result = "待验收";
            break;
        case ORDER_STATE_APPLY_CLOSE:
            $result = "服务中";
            break;
        case ORDER_STATE_WAITING_JUDGE:
            $result = "已完成";
            break;
        case ORDER_STATE_CLOSED:
            $result = "已关闭";
            break;
        case ORDER_STATE_HAS_JUDGE:
            $result = "已完成";
            break;
        default:
            break;
    }
    return $result;
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

//获取商户Token码
function generateBranchToken(){
    $branch_id = getBrowseBranchId();
    return strtoupper(substr(md5($branch_id),0,8));
}
/**获取微信用户信息，如果非微信环境，返回虚拟信息
 * @param bool $only_base
 * @return array
 */
function getCurrentWXUserInfo($only_base = true)
{
    $wx_user_info = session(SK('wx_user_info_'));
    if (empty($wx_user_info)) {
        if (!isWechatBrower()) {
            $virtual_openid = cookie(SK("virtual_openid_"));
            if (empty($virtual_openid)) {
                $virtual_openid = "vo_" . md5(session_id());
                cookie(SK("virtual_openid_"), $virtual_openid);
            }
            session('openid', $virtual_openid);
            $wx_user_info = array("openid" => $virtual_openid, "subscribe"=>1); //非微信浏览器或本地测试，返回一个虚拟的session_id.
        }else{
            $start = time();
            //\Think\Log::write("开始：".$start);
            $wechatInstance = getWeChatInstance();
            $accessTokenData = $wechatInstance->getOauthAccessToken();
            if (!$accessTokenData) {
                header("Location:" . getWeChatRedirectUrl("", false));
                die();
            } else {
                $wx_user_info = $wechatInstance->getUserInfo($accessTokenData['openid']);
                if ($wx_user_info["subscribe"] == 0){ //未关注，获取不到用户昵称，必须再用授权方式获取一次
                    $wx_user_info = $wechatInstance->getOauthUserinfo($accessTokenData["access_token"], $accessTokenData['openid']);
                    $wx_user_info["subscribe"] = 0; //取回来没有此字段，必须再加上
                }
                session('openid', $accessTokenData['openid']);
               // \Think\Log::write('userinfo->'.var_export($wx_user_info, true));
            }
            //\Think\Log::write('结束：'. (time()-$start));
        }
        //如果已经关注，就保存在session，未关注的话，
        session(SK('wx_user_info_'), $wx_user_info);
    }
    //关注或取消关注是，设置关注标子
    $subscribe = S($wx_user_info["openid"]);
    if ($subscribe){
        $wx_user_info["subscribe"] = 1;
        S($wx_user_info["openid"], null);
        session(SK('wx_user_info_'), $wx_user_info);
    }
    return $wx_user_info;
}

/**设置session基本信息
 * @param $user_data
 */
function setUserSession($user_data){
    session("user_id", $user_data['id']);
    session("user_name", $user_data['name']);
    session('head_pic', $user_data['head_pic']);
    session('user_role', $user_data['role_ids']);
    session('user_type', $user_data['user_type']);
    session('mobile', $user_data['mobile']);
    session('branch_id', $user_data['branch_id']);
    session('updated_at', $user_data['updated_at']);
    setUserDataAccess($user_data['id']);
    setUserCurrBranchId($user_data['id']);
    setUserRoleAccess($user_data['id']);
}

function setUserDataAccess($user_id){
    $condition['user_id'] = $user_id;
    $target_id_branchs = M('sysUserDataAccess') ->field('target_id,type')-> where($condition) ->select();
    $target_value = [
        '0' => '_branchs',
        '1' => '_users'
    ];
    $target = [];
    if ($target_id_branchs) {
        foreach($target_id_branchs as $key => $value) {
            $target[$target_value[$value['type']]][] = $value['target_id'];
        }
    }
    session('userDataAccess', $target);
}
function setUserCurrBranchId($user_id)
{
    $condition['user_id'] = $user_id;
    $result = M("SysUserBranch")->where($condition)->find();
    if ($result) {
        $branch_data_list = D('SysUser')->getUserBranchs($user_id, $result['branch_id']);
        if ($branch_data_list) {
            foreach ($branch_data_list as $branch_data){
                if ($branch_data["id"] == $result['branch_id']){
                    session('currBranchId',intval($branch_data["id"]));
                    session('currBranchName',intval($branch_data["name"]));
                }
                $userBranchs[] = array("id"=>$branch_data["id"], "name"=>$branch_data["name"]);
            }
            session('branchList',$userBranchs);
        }
    }
}
function setUserRoleAccess($user_id)
{
    $_SESSION['permissionList'] = D('SysUser')->setUserRoleAccess($user_id);
}
//不同店铺的KEY
function SK($key){
    $branch_id = getBrowseBranchId();
    return $key . $branch_id;
}

function initialContactTool($controller, $show_share = 0, $show_tel = 1, $show_msg =1){
    $store_tel = getConstStoreTel();
    if ($store_tel && $show_tel){
        $controller->store_tel = $store_tel;
    }
    $store_liuyan = getConstStoreLiuyan();
    if ($store_tel && $show_msg){
        $controller->store_liuyan = $store_liuyan;
    }
    $controller->show_share = $show_share;
}