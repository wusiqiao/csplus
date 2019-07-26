<?php
require_cache(MODULE_PATH."Common/vcr_common.php");

const DOMAIN_NAME = "caisuikx.com";
const USE_SSL = true;

/*
 * Title  : 获取服务分类的目录
 * Create : March 5 2018
 */
//function getCategory($level = 'all'){
//    $where = $level == 'all' ? '' : 'level = '.$level;
//    $cates = M('ComCategory')->where($where)->select();
//    return $cates;
//}
//level不再起作用，以parent_id来判断
//function getCategory($level = 'all'){
//    $where = $level == 'all' ? '' : array("a.parent_id"=> array("gt", 0));
//    $cates = D('ComCategory')->setDacFilter("a")->where($where)->select();
//    var_dump($cates);die;
//    return $cates;
//}
function getCategoryListDefaultPic(){
    return IMG_URL.'nopic.gif';
}
function getCategory($level = 'all',$parent = ''){
    $branch_id = intval(getBrowseBranchId());
    $where = 'branch_id = '.$branch_id;
    if($level != 'all' && $parent == ''){
        $where .= $level == 1 ? ' and parent_id = 0 ' : ' and parent_id > 0 ';
    }elseif($level != 'all' && $parent != ''){
        $where .= ' and parent_id = '.$parent;
    }
//    $where .= $level == 'all' ? '' : ' and level = '.$level;
//    if($level != 'all' && $parent != ''){
//        $where .= ' and parent_id = '.$parent;
//    }
    $cates = D('ComCategory')->where($where)->select();
    return $cates;
}
function getRegion($level = 'all',$parent = ''){
    $where = '';
    if($level != 'all' && $parent == ''){
        $where .= $level == 1 ? ' parent_id = 0 ' : ' parent_id > 0 ';
    }elseif($level != 'all' && $parent != ''){
        $where .= ' parent_id = '.$parent;
    }
    $regions = D('SysRegion')->where($where)->select();
    return $regions;
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
function getWxConfigData(){
    $branch_id = getBrowseBranchId();
    $routine = M('WxConfig')->where('branch_id ='.$branch_id)->find();
    return $routine;
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

//是否开启三级分销
function isToggleDistribution(){
     return (integer)getRoutineSingle('distribution_toggle') == 1 ? true : false;
}
function getRoutineSingle($inc){
    $branch_id = getBrowseBranchId();
    $routine = M('SysRoutine')->field($inc)->where('branch_id ='.$branch_id)->find();
    return $routine[$inc];
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
    $defalut_url=   '/Application/EShop/Public/images/Index_img/';
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
function getMessageRemark($msg = "") {
    return $msg . getWxTemplateId('noticeInformation');
}
function getBrowseBranchId(){
    $user_session = session(USER_SESSION_KEY);
    if ($user_session->parentBranchId == 1) {
        return $user_session->currBranchId;
    } else {
        return $user_session->parentBranchId;
    }
}

function getWxPayOptions($inc = 'all'){
    $branch_id = getBrowseBranchId();
    $options = M("WxConfig")->field('token,appid,appsecret,encoding_aeskey,wx_pay_key,wx_mchid')->where('branch_id = '.$branch_id)->find();
    return $inc == 'all' ? $options : $options[$inc];
}
function setPayParams($inputObj) {
    $wx_pay = getWxConfigData();
    $inputObj->SetAppid($wx_pay['appid']); //公众账号ID
    $inputObj->SetMch_id($wx_pay['wx_mchid']); //商户号
    $inputObj->SetAPPKey($wx_pay['wx_pay_key']);
}
function getWxTemplateId($inc){
    $inc = strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $inc));
    $inc = 'wx_'.$inc;
    $branch_id = getBrowseBranchId();
    return M('WxConfig')->where('branch_id = '.$branch_id)->getField($inc);
}
/*
 *
 */
function category($id){
    $db_table = M('ComCategory');
    $result = $db_table -> where("id = ".$id) -> find();
    return $result['name'];
}
function int_trans_ch($number) {
    $ch = array(
        '1'=> '一',
        '2'=> '二',
        '3'=> '三',
        '4'=> '四',
        '5'=> '五',
        '6'=> '六',
        '7'=> '七',
        '8'=> '八',
        '9'=> '九',
        '10'=> '十',
        '11'=> '十一',
        '12'=> '十二',
        '13'=> '十三',
        '14'=> '十四',
        '15'=> '十五',
        '16'=> '十六',
        '17'=> '十七',
        '18'=> '十八',
        '19'=> '十九',
        '20'=> '二十',
    );
    return $ch[$number];
}
function get_ticket_code_cuid() {
    $charid = strtoupper(md5(uniqid(mt_rand(), true)));
    return $charid;
}
function mb_strev($string, $encoding = null) {
    if ($encoding === null) {
        $encoding = mb_detect_encoding($string);
    }
    $length = mb_strlen($string, $encoding);
    $reversed = '';
    while ($length-- > 0) {
        $reversed.= mb_substr($string, $length, 1, $encoding);
    }
    return $reversed;
}

/**根据模板内容提取唯一模板id
 * @param $content
 * @return string
 */
function getTemplateIdentKey($content){
    return md5(str_replace(array("\n","\r"),"", $content));
}
function getOrderNo($prefix = "") {
    return $prefix . date("YmdHis") . rand(100, 900);
}

/**商城版本
 * @return array
 */
function getBranchRoles(){
    return array(["id"=>0, "name"=>"（无）"],["id"=>ROLE_ID_COMPANY_MANAGER, "name"=>"付费版本"],["id"=>ROLE_ID_COMPANY_FREE, "name"=>"免费版本"]);
}