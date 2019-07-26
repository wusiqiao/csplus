<?php
        const ADMIN_ACCOUNT = "admin";
        const SYSTEM_ACCOUNT = "system";
        const USER_SESSION_KEY = "_login_user_key_";
        const ACCESS_TOKEN_TAG = "access_token";
        const SELECT_LIMIT = "0,10"; //选择下拉最多显示行
        const INNER_USER_TYPE = -1;
        const NORMAL_USER_TYPE = 0;
//组织类型
        const ORG_BRANCH = 0;
        const ORG_COMPANY = 1;
        const ORG_DEPARTMENT = 2; //部门 
        const ORG_BRANCH_DEPARTMENT = 10;
        const ORG_COMPANY_DEPARTMENT = 11;
        const ORG_UNKNOW_DEPARTMENT = -1;
//菜单Cache Key
        const MENU_LIST_KEY = "_menu_list_key_";
//Session权限部分Key
        const ACCESS_MENUS_KEY = "menus";
        const ACCESS_URL_KEY = "url";
        const ACCESS_SYS_ACTIONS_KEY = "sys_actions";
        const ACCESS_MENU_ACTIONS_KEY = "menu_actions";
//参数Canche Key
        const SYS_PARAM_CACHE_KEY = "sys_params_cache";
//主表固定别名
        const MASTER_TABLE_ALIAS = "A";
//数据权限范围
        const DAC_SCOPE_DEFAULT = 0; //默认 --如果功能指定可见或负责人，则该功能只能看见指定的数据，如果功能没有指定，则所有数据可见
        const DAC_SCOPE_BRANCH = 1; //所属公司或商户全部
        const DAC_SCOPE_DEPARTMENT = 2; //指定部门
        const DAC_SCOPE_USER = 3; //指定人员
//权限值
        const DAC_PERMIT_VALUE_LEADER = 8; //负责人
        const DAC_PERMIT_VALUE_COLLABORATOR = 4;//协作者
        const DAC_PERMIT_VALUE_VISIBLER = 2; //可见人
        const DAC_PERMIT_VALUE_NOTICER = 1; //通知人
//sys_user_module_setting表区分商户端客户端
        const DAC_SETTING_TYPE_BRANCH = 1; //商户端
        const DAC_SETTING_TYPE_CUSTOMER = 0; //客户端
//操作符号
        const OPERATION_EQU = "q";
        const OPERATION_DATE = "qd";
        const OPERATION_LIKE = "ql";
        const OPERATION_BETWEEN = "qr";
        const OPERATION_DATE_BETWEEN = "qdr";
        const OPERATION_YM = "qm";
        const OPERATION_YM_BETWEEN = "qmr";
//    public $upload_config = array(
//    'maxSize'    =>    1048576, //1000K
//    'rootPath'   =>    './Uploads/',
//    'savePath'   =>    '',
//    'saveName'   =>    array('uniqid',''),
//    'exts'       =>    array('jpg', 'gif', 'png', 'jpeg'),
//    'autoSub'    =>    true,
//    'subName'    =>    array('date','Ymd'),
//    );
        const APIKEY_BAIDU_IPSEARCH = '4479477f2a80ff8c9a9bb5fcaaa0a4b6';

if (!defined('MODULE_UPLOAD_PATH')) {
    define('MODULE_UPLOAD_PATH', './' . APP_PATH . BIND_MODULE . '/Upload/');
}

function getImageUploader($savePath = "") {
    return getUploader($savePath, array('jpg', 'gif', 'png', 'jpeg')); //10M
}

function getUploader($savePath = "", $exts = null, $maxSize = 52428800) {
    $upload_config = array(
        'maxSize' => $maxSize,
        'rootPath' => MODULE_UPLOAD_PATH,
        'savePath' => $savePath,
        'saveName' => array('uniqid', ''),
        'exts' => $exts,
        'autoSub' => true,
        'subName' => array('date', 'Ymd'),
    );
    return new \Think\Upload($upload_config);
}

/**
 * 将中文编码成拼音 
 * @param string $utf8Data utf8字符集数据 
 * @param string $sRetFormat 返回格式 [head:首字母|all:全拼音] 
 * @return string 
 */
function firstPinyin($utf8Data, $isUpper = false) {
    static $pinyin;
    if (empty($pinyin)) {
        vendor("pinyin.src.Pinyin");
        vendor("pinyin.src.DictLoaderInterface");
        vendor("pinyin.src.FileDictLoader");
        $pinyin = new \Overtrue\Pinyin\Pinyin();
    }
    $result = $pinyin->abbr($utf8Data);
    //替换非字母和数字的其他字符
    $result = preg_replace('/[\W]/', '', $result);
    return $result;
}

function md5_plus($source, $sult = "") {
    if (empty($sult)) {
        $str = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($str) - 1;
        $sult = $str[rand(0, $max)] . $str[rand(0, $max)];
    }
    $result = $sult . md5($sult . $source);
    return $result;
}

function check_md5_plus($source, $cryptograph) {
    $sult = substr($cryptograph, 0, 2);
    return (md5_plus($source, $sult) === $cryptograph);
}

function mergeString($source, $merge, $delimiter) {
    return empty($source) ? (empty($merge) ? "" : $merge) : (empty($merge) ? $source : $source . $delimiter . $merge);
}

function buildResult($list = null, $empty_text = "") {
    $code = empty($list) ? 1 : 0;
    $msg = empty($list) ? (empty($empty_text)?"数据为空！" : $empty_text) : $list;
    return buildMessage($msg, $code);
}

function buildMessage($msg = "", $code = 0) {
    $result["message"] = $msg;
    $result["code"] = $code;
    return $result;
}

function buildErrorMessage($msg = "") {
    $result["message"] = $msg;
    $result["code"] = 1;
    return $result;
}

function getSysMenuList($name = "") {
    $menuListCache = S(MENU_LIST_KEY);
    if (!$menuListCache) {
        $condition["is_valid"] = 1;
        $condition["is_show"] = 1;
        $fields = "id,name,url,parent_id,is_dac,style,icon,child_count,params,is_system,is_online";
        $list = M("SysMenu")->field($fields)->where($condition)->order("sort")->select();
        foreach ($list as $value) {
            $key = $value["url"];
            if ($value["params"]) {
                $key = $key . "?" . $value["params"];
            }
            $menuListCache[$key] = $value;
        }
        S(MENU_LIST_KEY, $menuListCache);
        unset($list);
    }
    if ($name) {
        return $menuListCache[$name];
    }
    return $menuListCache;
}

//显示全功能菜单，没有权限的allow设置为0
function getUserMenus() {
    $userSession = session(USER_SESSION_KEY);
    $result = array();
    if ($userSession) {
        $permissionList = $userSession->permissionList[ACCESS_MENUS_KEY];
        $sysMenuList = getSysMenuList();
        foreach ($sysMenuList as $menu) {
            $urls = explode("?", $menu["url"]); //url.params
            $key = $urls[0];
            $isNode = empty($menu["parent_id"]) || (intval($menu["child_count"]) > 0);
            if (($permissionList[$key]) || $isNode) {
                if ($userSession->isPlatformUser){ //平台用户或管理员（平台用户受权限控制，管理员只能看is_system=1的功能
                    if ($permissionList[$key]["allow"] || $isNode || ($menu["is_system"] && $userSession->isAdmin)){
                        $menu["allow"] = 1;
                        $result[] = $menu;
                    }
                }else{
                    $menu["allow"] = ($permissionList[$key]["allow"])?1:0;
                    //免费版本的所有功能显示，付费的只有允许的功能才显示
                    if (ROLE_ID_COMPANY_FREE == $userSession->branchRole || ($permissionList[$key]["allow"] || $isNode)) {
                        $result[] = $menu;
                    }
                }
            }
        }
    }
    return list_to_tree($result, 0);
}

function _buildMenuTree($menu_list, $parent_id) {
    $result = array();
    foreach ($menu_list as $key => $value) {
        if ($value["parent_id"] == $parent_id) {
            $children = _buildMenuTree($menu_list, $value["id"]);
            if ($children) {
                $value['children'] = $children;
            } else {
                $value['leaf'] = true;
            }
            $result[] = $value;
        }
    }
    //去掉没有子菜单的主菜单
    foreach ($result as $key => $value) {
        if ($value["leaf"] && empty($value["parent_id"])) {
            unset($result[$key]);
        }
    }
    return $result;
}

function addCurrentBranch(&$list, $user_session, $branch_type) {
    if (!$user_session->isAdmin && ($user_session->currBranchType == $branch_type)) {
        $self["value"] = strval($user_session->currBranchId); //回传必须是字符串型
        $self["text"] = $user_session->currBranchName;
        array_unshift($list, $self);
    }
}

function excel2array($filePath, $columnHighest, $sheet = 0) {
    Vendor('PHPExcel18.PHPExcel');
    if (empty($filePath) || !file_exists($filePath)) {
        return null;
    }
    $PHPReader = new PHPExcel_Reader_Excel2007();        //建立reader对象
    if (!$PHPReader->canRead($filePath)) {
        $PHPReader = new PHPExcel_Reader_Excel5();
        if (!$PHPReader->canRead($filePath)) {
            return null;
        }
    }
    try {
        $PHPExcel = $PHPReader->load($filePath);        //建立excel对象
        $currentSheet = $PHPExcel->getSheet($sheet);        //**读取excel文件中的指定工作表*/
        $allColumn = $currentSheet->getHighestColumn();        //**取得最大的列号*/
        $colHighest = strtoupper($columnHighest);
        if ($allColumn >= $colHighest) {
            $allColumn = $colHighest;
        } else {
            unset($currentSheet);
            unset($PHPExcel);
            unset($PHPReader);
            return null;
        }
        $allRow = $currentSheet->getHighestRow();        //**取得一共有多少行*/
        $data = array();
        for ($rowIndex = 1; $rowIndex <= $allRow; $rowIndex++) {        //循环读取每个单元格的内容。注意行从1开始，列从A开始
            for ($colIndex = 'A'; $colIndex <= $allColumn; $colIndex++) {
                $addr = $colIndex . $rowIndex;
                $cell = $currentSheet->getCell($addr)->getValue();
                if ($cell instanceof PHPExcel_RichText) { //富文本转换字符串
                    $cell = $cell->__toString();
                }
                $data[$rowIndex][$colIndex] = $cell;
            }
        }
        unset($currentSheet);
        unset($PHPExcel);
        unset($PHPReader);
        return $data;
    } catch (Exception $e) {
        return null;
    }
}

function getQueryField($key) {
    $result = array();
    $querys = explode("-", $key, 2);
    if (count($querys) == 2) {
        $result["operate"] = $querys[0];
        $result["field"] = $querys[1];
    }
    return $result;
}

function getCurrentMonth() {
    $date = getdate();
    return $date["mon"];
}

function getCurrentYear() {
    $date = getdate();
    return $date["year"];
}

function getCurrentYMTime() {
    $date = getdate();
    return strtotime($date["year"] . "-" . $date["mon"] . "-01 00:00:00");
}

function strtoymtime($ym_str) {
    $ymonth = preg_replace("/(\d+)([^\d+])(\d+)/i", "$1-$3", $ym_str);
    if ($ymonth != null) {
        return strtotime($ymonth . "-01 00:00:00");
    }
    return false;
}

function parseQueryOrder(&$order) {
    $sort_field = I("post.sort");
    if (!empty($sort_field)) {
        $order = "a." . $sort_field . ' ' . I("post.order");
    } else {
        if (empty($order)) {
            $order = "a.id desc";
        }
    }
    return $order;
}

//获取解析前端传入的条件,"q-"为"AND"过滤条件, "qr-"表示query-range，范围条件 "ql"表示like
function parseQueryParams(&$filter, $request = null) {
    if (!empty($request)) {
        foreach ($request as $key => $value) {
            $_REQUEST[$key] = $value;
            $_POST[$key] = $value;
        }
    }
    foreach ($_REQUEST as $key => $value) {
        $field_operate = getQueryField($key);
        if ($field_operate) {
            $field = $field_operate["field"];
            //因为参数不能有.号，所以用*号代替.
            if (strpos($field, '*') !== FALSE) {
                $field = str_replace('*', '.', $field);
            } else {
                $field = 'a.' . $field;
            }
            $operate = $field_operate["operate"];
            $key_value = I($key);
            if (strtoupper($key_value) === "NULL") { //特殊标示，标示查找为空的记录
                $filter[$field] = array('exp', ' is NULL');
            } else {
                $keyValues = array($field => "post.$key");
                switch ($operate) {
                    case OPERATION_EQU:
                        setAndFilter($keyValues, $filter);
                        break;
                    case OPERATION_LIKE:
                        setLikeFilter($keyValues, $filter);
                        break;
                    case OPERATION_BETWEEN:
                        setBetweenFilter($keyValues, $filter);
                        break;
                    case OPERATION_YM:
                        setAndFilter($keyValues, $filter, "strtoymtime");
                        break;
                    case OPERATION_DATE:
                        setAndFilter($keyValues, $filter, "strtotime");
                        break;
                    case OPERATION_YM_BETWEEN:
                        setBetweenFilter($keyValues, $filter, "strtoymtime");
                        break;
                    case OPERATION_DATE_BETWEEN:
                        setBetweenFilter($keyValues, $filter, "strtotime");
                        break;
                    default:
                        break;
                }
            }
        }
    }
    return $filter;
}

/* 条件过滤函数 */

function setAndFilter($keyValues, &$filter, $callback = null) {
    if (is_array($keyValues)) {
        foreach ($keyValues as $key => $value) {
            $query = I($value, null);
            if (isset($query)) {
                $query_v = $callback ? call_user_func($callback, $query) : $query;
                $filter[$key] = $query_v;
            }
        }
    }
}

function setLikeFilter($keyValues, &$filter, $callback = null) {
    if (is_array($keyValues)) {
        foreach ($keyValues as $key => $value) {
            $query = I($value, null);
            if (isset($query)) {
                $query_v = $callback ? call_user_func($callback, $query) : $query;
                $filter[$key] = array("like", "%$query_v%");
            }
        }
    }
}

function setBetweenFilter($keyValues, &$filter, $callback = null) {
    if (is_array($keyValues)) {
        foreach ($keyValues as $key => $value) {
            if (is_array($value)) { //起始名称和结束名称不同
                $range_start = I($value[0], null);
                $range_end = I($value[1], null);
            } else {
                $range = I($value, null);
                if (is_array($range)) {
                    $range_start = $range[0];
                    $range_end = $range[1];
                }
            }
            $range_start_v = isset($range_start) ? ($callback ? call_user_func($callback, $range_start) : $range_start) : null;
            $range_end_v = isset($range_end) ? ($callback ? call_user_func($callback, $range_end) : $range_end) : null;
            if ("strtotime" === $callback && $range_end_v) { //如果是时间，特殊处理，结束日期要加一天
                $range_end_v = $range_end_v + 24 * 60 * 60;
            }
            if ($range_start_v) {
                if ($range_end_v) {
                    $filter[$key] = array("BETWEEN", array($range_start_v, $range_end_v));
                } else {
                    $filter[$key] = array("EGT", $range_start_v);
                }
            } else {
                if ($range_end_v) {
                    $filter[$key] = array("ELT", $range_end_v);
                }
            }
        }
    }
}

function setInFilter($names, $values, &$filter) {
    
}

function setCenter($objPHPExcel, $cells, $type = 3) {
    if (($type & 1) == 1) {
        $objPHPExcel->getActiveSheet()->getStyle($cells)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
    }
    if (($type & 2) == 2) {
        $objPHPExcel->getActiveSheet()->getStyle($cells)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    }
}

function mergeCells($objPHPExcel, $cells, $type = 1, $alignment = "top") {
    $objPHPExcel->getActiveSheet()->mergeCells($cells);
    if (($type & 1) == 1) {
        $objPHPExcel->getActiveSheet()->getStyle($cells)->getAlignment()->setVertical($alignment);
    }
    if (($type & 2) == 2) {
        $objPHPExcel->getActiveSheet()->getStyle($cells)->getAlignment()->setHorizontal($alignment);
    }
}

function setCellsBorders($objPHPExcel, $cells, $is_all = true) {
    $borderFlag = $is_all ? 'allborders' : 'outline';
    $styleThinBlackBorderline = array(
        'borders' => array(
            $borderFlag => array(
                'style' => \PHPExcel_Style_Border::BORDER_THIN, //设置border样式
                //'style' => PHPExcel_Style_Border::BORDER_THICK, 另一种样式
                'color' => array('argb' => 'FF000000'), //设置border颜色
            ),
        ),
    );
    $objPHPExcel->getActiveSheet()->getStyle($cells)->applyFromArray($styleThinBlackBorderline);
}

function setCellImage($objPHPExcel, $cell, $row, $imageUrl) {
    if (file_exists($imageUrl)) {
        $objPHPExcel->getActiveSheet()->getRowDimension($row)->setRowHeight(100);
        $objDrawing = new \PHPExcel_Worksheet_Drawing();
        $objDrawing->setPath($imageUrl);
        $objDrawing->setOffsetX(5)->setOffsetY(5)->setHeight(120);
        $objDrawing->setCoordinates($cell . $row);
        $objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
        $objDrawing = null;
    } else {
        $objPHPExcel->getActiveSheet()->setCellValue($cell . $row, 'Picture not found!');
    }
}

function getExcelColumnChar($index) {
    if (chr($index) > "Z") {
        return "A" . chr($index - ord("Z") - 1 + ord("A"));
    } else {
        return chr($index);
    }
}

function getExcelColumnIndex($str) {
    $length = strlen($str);
    return ($length == 1)?ord($str):ord("Z") + ord(substr($str,1,1)) - ord("A") + 1;
}

function getExcelCellValue($currentSheet, $cell){
    $cell = $currentSheet->getCell($cell);
    if ($cell->getDataType() == 'f'){
        return $cell->getCalculatedValue();
    }else{
        return $cell->getValue();
    }
}

function qrcode($text = '', $level = 'L', $size = 32, $margin = 2) {
    Vendor('phpqrcode.phpqrcode');
    \QRcode::png($text, false, $level, $size, $margin);
}

function getTextLine($pageW, $rectW, $text) {
    $lineCount = 76; //每行字数
    if ($rectW == 0) {
        $rectW = $pageW;
    }
    $textLen = iconv_strlen($text, 'utf-8');
    return ceil(($pageW / $lineCount * $textLen) / $rectW);
}

/* JSON返回示例 :
  //{
  //    "errNum": 0,
  //    "errMsg": "success",
  //    "retData": {
  //        "ip": "117.89.35.58", //IP地址
  //        "country": "中国", //国家
  //        "province": "江苏", //省份 国外的默认值为none
  //        "city": "南京", //城市  国外的默认值为none
  //        "district": "鼓楼",// 地区 国外的默认值为none
  //        "carrier": "中国电信" //运营商  特殊IP显示为未知
  //    }
  //}
 * */

function getLocationByIP($ip) {
    $ch = curl_init();
    $url = 'http://apis.baidu.com/apistore/iplookupservice/iplookup?ip=' . $ip;
    $header = array(
        'apikey:' . APIKEY_BAIDU_IPSEARCH,
        "content-type: application/x-www-form-urlencoded; charset=UTF-8"
    );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    $result_json = curl_exec($ch);
    if ($result_json) {
        $result = json_decode($result_json, true);
        if (strtolower($result['errMsg']) == 'success') {
            return $result['retData'];
        }
    }
    return $result;
}

/*
 * 经典的概率算法， 
 * $proArr是一个预先设置的数组， 
 * 假设数组为：array('1'=>100,'2'=>200,'3'=>300，'4'=>400)， 
 * 开始是从1,1000 这个概率范围内筛选第一个数是否在他的出现概率范围之内，  
 * 如果不在，则将概率空间，也就是k的值减去刚刚的那个数字的概率空间， 
 * 在本例当中就是减去100，也就是说第二个数是在1，900这个范围内筛选的。 
 * 这样 筛选到最终，总会有一个数满足要求。 
 * 就相当于去一个箱子里摸东西， 
 * 第一个不是，第二个不是，第三个还不是，那最后一个一定是。 
 */

function get_lottery_rand($proArr, $proSum = 0) {
    $result = '';
    //概率数组的总概率精度 
    if ($proSum == 0) {
        $proSum = array_sum($proArr);
    }
    //概率数组循环   
    foreach ($proArr as $key => $proCur) {
        $randNum = mt_rand(1, $proSum);
        if ($randNum <= $proCur) {
            $result = $key;
            break;
        } else {
            $proSum-= $proCur;
        }
    }
    unset($proArr);
    return $result;
}

/* 获取url上的跳转链接，修正thinkphp无法获取urlencode的问题 */

function bulid_url_forward($url, $forward) {
    return __ROOT__ . str_replace(__ROOT__, '', $url) . "?HTTP_REFERER=" . urlencode(__ROOT__ . str_replace(__ROOT__, '', $forward));
}

function get_url_forward() {
    $referer = urldecode(I('HTTP_REFERER'));
    if (empty($referer)) {
        $referer = $_SERVER['HTTP_REFERER'];
    }
    return $referer;
}
function getWxOptions(){
//    $options = S('wx_options_cache');
//    if(!$options){
        $branch_id = getBrowseBranchId();
        $options = M("WxConfig")->field('id,token,appid,appsecret,encoding_aeskey,xcx_appid,xcx_appsecret, is_author, authorizer_refresh_token')->where('branch_id = '.$branch_id)->find();
//        S('wx_options_cache',$options);
//    }
    return $options;
}
function getWeChatInstance($wx_config = null) {
    static $_tpWeChat = null;
    if (empty($_tpWeChat)) {
        Vendor('Wechat.TPWechat', '', '.class.php');
        if (empty($wx_config)){
            $wx_config = getWxOptions();
        }

        $options = array(
            'id' => $wx_config['id'],
            'token' => $wx_config['token'], //填写你设定的key
            'appid' => $wx_config['appid'],
            'appsecret' => $wx_config['appsecret'],
            'encodingaeskey' => $wx_config['encoding_aeskey'], //填写加密用的EncodingAESKey，如接口为明文模式可忽略
            'is_author' => $wx_config['is_author'],
            'authorizer_refresh_token' => $wx_config['authorizer_refresh_token'],
        );
        $_tpWeChat = new \TpWechat($options);
    }

    return $_tpWeChat;
}

//小程序
function getMPInstance() {
    static $_tpWeChat = null;
    if (empty($_tpWeChat)) {
        Vendor('Wechat.TPWechat', '', '.class.php');
        $sysParams = getWxOptions();
        $options = array(
            'appid' => $sysParams['xcx_appid'],
            'appsecret' => $sysParams['xcx_appsecret'],
        );
        $_tpWeChat = new \TpWechat($options);
    }
    return $_tpWeChat;
}

//function getWeChatInstanceEx() {
//    Vendor('Wechat.TpWechat', '', '.class.php');
//    $options = C(array('token','appid','appsecret','encodingaeskey'));
//    return new \TpWechat($options);
//}

function getJsSign($wechat) {
    if (!$wechat) {
        $wechat = getWeChatInstance();
    }
    $wechat->checkAuth();
    $js_ticket = $wechat->getJsTicket();
    if (!$js_ticket) {
        echo "获取js_ticket失败！<br>";
        echo '错误码：' . $wechat->errCode;
        exit;
    }
    $protocol = empty($_SERVER["HTTPS"])?"http://":"https://";
    $url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    return $wechat->getJsSign($url);
}

function createWeChatUser($weObj) {
    $openid = $weObj->getRevFrom();
    $condition['openid'] = $openid;
    $user_data = M('WxUser')->where($condition)->find();
    if (!$user_data) {
        $user_data = $weObj->getUserInfo($openid);
        M('WxUser')->add($user_data);
    }
    return $user_data;
}

function getWeChatUser($openid = '') {
    if (empty($openid)) {
        $openid = checkWeChatOpenId();
    }
    if ($openid) {
        $condition['openid'] = $openid;
        return M('WxUser')->where($condition)->find();
    }
    return null;
}

function checkWeChatOpenId() {
    $code = I("code"); //先判断网页授权方式
    if ($code) {
        $we = getWeChatInstance();
        if ($we->getUserBaseByCode($code)) {
            $openid = $we->getRevFrom();
            ;
        }
    } else {
        $openid = I("openid");
    }
    return ($openid) ? $openid : false;
}

function getWeChatRedirectUrl($url = null, $base = true) {
    $scope = $base ? 'snsapi_base' : 'snsapi_userinfo';
    $protocol = empty($_SERVER["HTTPS"])?"http://":"https://";
    if (empty($url)) {
        $url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    } else {
        if (stripos($url, 'http') === false) {
            $url = $protocol . $_SERVER['HTTP_HOST'] . $url;
        }
    }
    return getWeChatInstance()->getOauthRedirect($url, 1, $scope);
}
//区域名称 区域id
function region($id) {
    $db_table = M('sysRegion');
    $result = $db_table -> where("id = $id ") -> find();
    return $result['name'];
}
function getCompanyInfo() {
    $company_info = S("GLOBAL_COMPANY_INFO");
    if (empty($company_info)) {
        $company_info = M("SysBranch")->where("type=" . ORG_COMPANY)->find();
        S("GLOBAL_COMPANY_INFO", $company_info);
    }
    return $company_info;
}

/* 产生树形结构 */

function build_tree_old($list, $parent_id = 0, $only_checked = false) {
    $result = array();
    foreach ($list as $item) {
        if ($parent_id == intval($item["parent_id"])) {
            $children = build_tree($list, intval($item["id"]), $only_checked);
            if ($children) {
                $item['children'] = $children;
                $result[] = $item;
            } else {
                if (!$only_checked || ($only_checked && $item["checked"])) {
                    $item['state'] = "opened";
                    $result[] = $item;
                }
            }
        }
    }
    return $result;
}


function list_to_tree($list,  $root = 0, $pk = 'id', $pid = 'parent_id', $child = 'children') {
    $tree = array();
    if (is_array($list)) {
        $refer = array();
        foreach ($list as $key => $data) {
            $refer[$data[$pk]] = & $list[$key];
        }
        foreach ($list as $key => $data) {
            $parentId = $data[$pid];
            if ($root == $parentId) {
                $tree[] = & $list[$key];
            } else {
                if (isset($refer[$parentId])) {
                    $parent = & $refer[$parentId];
                    $parent[$child][] = & $list[$key];
                }
            }
        }
    }
    return $tree;
}

/* 产生列表，按层级排序 */
function build_tree_list($list, $id_field = 'value') {
    $result = array();
    foreach ($list as $item) {
        $item["prefix"] = "";
        $result[$item[$id_field]] = $item;
    }
    foreach ($result as $key => $item) {
        $parent_id = $item["parent_id"];
        $parent_prefix = $result[$parent_id]["prefix"];
        if ($result[$parent_id]) {
            $result[$key]["prefix"] = $parent_prefix . '　';
            $result[$key]["text"] = $parent_prefix . '　└─' . $item["text"];
        }
    }
    return array_values($result);
}
//取出字符串的空格
function trimall($str)//删除空格
{
    $qian=array(" ","　","\t","\n","\r");$hou=array("","","","","");
    return str_replace($qian,$hou,$str);
}
function send_wx_message($data, $sync = false, $delay = 0) {
    return $sync ? D("ESAdmin/SysMq")->send_wx_message($data, $sync, $delay) : send_wx_message_direct($data);
}

/**
 * @param $userId	需要发送的用户id
 * @param $url		微信消息点击详情的链接
 * @param $first	第一行提示语
 * @param $keyword1	服务编号
 * @param $keyword2	服务项目
 * @param string $remark	备注
 * @return array	errcode:1 出错了
 */
function send_one_wx_message($userId,$url,$first,$keyword1,$keyword2,$remark=''){
	$openid = M('SysUser')->where(array('id'=>array('eq',$userId),'is_follow'=>array('eq',1)))->getField('openid');
	if (empty($openid)){
		return array('errcode'=>1,'message'=>'用户没关注公众号不能发送微信消息');
	}
	$sendUpLeader = array(
		'template_id'=>getWxTemplateIdByStandardId('OPENTM405775254'),
		'url'=>$url,
		'body'=>array(
			'first'=>array('value'=>$first),
			'keyword1'=>array('value'=>$keyword1),
			'keyword2'=>array('value'=>$keyword2),
			'remark'=>array('value'=>$remark),
		),
		'openid'=>$openid,
	);
	send_wx_message($sendUpLeader);
}

//作用于群发模板消息
function send_wx_group_message($data, $sync = true, $delay = 0) {
    return D("ESAdmin/SysMq")->send_wx_group_message($data, $sync, $delay);
}
function isRemoteServer() {
    $a = stripos($_SERVER["SERVER_NAME"], "localhost");
    $b = stripos($_SERVER["SERVER_NAME"], "127.0.0.1");
    $c = stripos($_SERVER["SERVER_NAME"], "192.168");
    return ($a === false && $b === false && $c === false);
}
function send_wx_message_direct($data) {
    $wechat = getWeChatInstance();
    $result["errcode"] = 0;
    if ($wechat->isRemoteHost()) {
        $message["touser"] = $data["openid"];
        $message["template_id"] = $data["template_id"];
        $message["url"] = $data["url"];
        $message["topcolor"] = "#FF0000";
        $message["data"] = $data["body"];
        if ($data['miniprogram']){
            $message["miniprogram"] = $data["miniprogram"];
        }

        if (!$wechat->sendTemplateMessage($message)) {
            $result["errcode"] = $wechat->errCode;
            $result["errmsg"] = $wechat->errMsg;
            \Think\Log::write("send_wx_message error!template_id:" . $message["template_id"] . "message:" . $wechat->errMsg.'|code:'.$wechat->errCode);
        }
    }
    return $result;
}
function getGlobalReturnCode($inc){
    $result = array(
        '0'=>'ok',
        '40001' => '公众号配置错误',//获取 access_token 时 AppSecret 错误，或者 access_token 无效。请开发者认真比对 AppSecret 的正确性，或查看是否正在为恰当的公众号调用接口
        '40002' => '不合法的凭证类型',
        '40003' => '不合法的OpenID',
        '40013' => '不合法的AppID',
        '40014' => '不合法的access_token',
        '40035' => '不合法的参数',
        '40037' => '模板ID无效',
        '40039' => '不合法的 URL 长度',
        '40132' => '微信号不合法',
        '40155' => '请勿添加其他公众号的主页链接',
        '41001' => '缺少access_token参数',
        '41002' => '缺少appid参数',
        '41003' => '缺少refresh_token参数',
        '41004' => '缺少secret参数',
        '41008' => '缺少oauth code',
        '41009' => '缺少openid',
        '42001' => 'access_token 超时',
        '43001' => '需要 GET 请求',
        '43002' => '需要 POST 请求',
        '43003' => '需要 HTTPS 请求',
        '43004' => '需要接收者关注',
        '43005' => '需要好友关系',
        '43019' => '需要将接收者从黑名单中移除',
        '45002' => '消息内容超过限制',
        '45003' => '标题字段超过限制',
        '45004' => '描述字段超过限制',
        '45005' => '链接字段超过限制',
        '45009' => '接口调用超过限制',
        '45011' => 'API 调用太频繁，请稍候再试',
        '46004' => '不存在的用户',
        '48001' => 'api 功能未授权',
        '48002' => '粉丝拒收消息',
        '48004' => 'api 接口被封禁',
        '50005' => '用户未关注公众号',
    );
    return $result[$inc];
}
function get_device_type() {
    //全部变成小写字母
    $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    $type = 'other';
    //分别进行判断
    if (strpos($agent, 'iphone') || strpos($agent, 'ipad')) {
        $type = 'ios';
    }

    if (strpos($agent, 'android')) {
        $type = 'android';
    }
    return $type;
}

function isMobile() {
    // 如果有HTTP_X_WAP_PROFILE则一定是移动设备
    if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) {
        return true;
    }
    // 如果via信息含有wap则一定是移动设备,部分服务商会屏蔽该信息
    if (isset($_SERVER['HTTP_VIA'])) {
        // 找不到为flase,否则为true
        return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
    }
    // 脑残法，判断手机发送的客户端标志,兼容性有待提高
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $clientkeywords = array('nokia',
            'sony',
            'ericsson',
            'mot',
            'samsung',
            'htc',
            'sgh',
            'lg',
            'sharp',
            'sie-',
            'philips',
            'panasonic',
            'alcatel',
            'lenovo',
            'iphone',
            'ipod',
            'blackberry',
            'meizu',
            'android',
            'netfront',
            'symbian',
            'ucweb',
            'windowsce',
            'palm',
            'operamini',
            'operamobi',
            'openwave',
            'nexusone',
            'cldc',
            'midp',
            'wap',
            'mobile'
        );
        // 从HTTP_USER_AGENT中查找手机浏览器的关键字
        if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
            return true;
        }
    }
    // 协议法，因为有可能不准确，放到最后判断
    if (isset($_SERVER['HTTP_ACCEPT'])) {
        // 如果只支持wml并且不支持html那一定是移动设备
        // 如果支持wml和html但是wml在html之前则是移动设备
        if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))) {
            return true;
        }
    }
    return false;
}

function isWechatBrower() {
    return (stripos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false);
}

function isRemoteHost() {
    $a = stripos($_SERVER["SERVER_NAME"], "localhost");
    $b = stripos($_SERVER["SERVER_NAME"], "127.0.0.1");
    $c = stripos($_SERVER["SERVER_NAME"], "192.168");
    return ($a === false && $b === false && $c === false);
}

/**
 * 递归获取目录下的文件信息列表
 * @staticvar array $dir_files
 * @param type $root_dir
 * @return type
  file_time:文件创建时间
  file_name：文件名
  file_url：下载链接
  file_dir：文件所在目录
  file_size：文件大小;
 */
function get_dir_files($root_dir) {
    $sub_dirs = scandir($root_dir);
    static $dir_files = array();
    foreach ($sub_dirs as $sub_dir) {
        if (stripos($sub_dir, ".") !== 0) {
            $sub_dir_path = $root_dir . "/" . $sub_dir;
            if (is_dir($sub_dir_path)) {
                $this->get_dir_files($sub_dir_path);
            } else {
                $file_data = array();
                $file_size = $this->bytesToSize(filesize($sub_dir_path));
                $file_url = str_replace(realpath("./"), "", $sub_dir_path);
                $file_data["file_time"] = $dt = date("Y-m-d H:i:s", filectime($sub_dir_path));
                $file_data["file_name"] = $sub_dir;
                $file_data["file_url"] = $file_url;
                $file_data["file_dir"] = $root_dir;
                $file_data["file_size"] = $file_size;
                $dir_files[] = $file_data;
            }
        }
    }
    return $dir_files;
}

/**
 * 字节显示
 * @param type $bytes
 * @return string
 */
function bytesToSize($bytes) {
    $sizes = ['Bytes', 'KB', 'MB'];
    if ($bytes == 0)
        return 'n/a';
    $i = intval(floor(log($bytes) / log(1024)));
    return round($bytes / pow(1024, $i), 2) . ' ' . $sizes[$i];
}

/**
 * 数据库压缩备份
 * @param type $backup_dir
 * @param type $error 
 * 错误信息
 * @return type
 * 成功返回true,否则false,查看error
 */
function sqldump_backup($backup_dir, &$error) {
    $cfg_dbuser = C("DB_USER");
    $cfg_dbname = C("DB_NAME");
    $cfg_dbpwd = C("DB_PWD");
    $filename = $cfg_dbname . "_" . date("Y-m-d_H-i-s") . ".sql.gz";
    $path = realpath("./") . "/$backup_dir";
    if (!is_dir($path)) {
        mkdir($path);
    }
    $tmpFile = $path . "/" . $filename;
    $cmd = "mysqldump -u" . $cfg_dbuser . " -p" . $cfg_dbpwd . " --default-character-set=utf8 " . $cfg_dbname . " | gzip > " . $tmpFile;
    exec($cmd, $error, $ret);
    return ($ret == 0);
}

/**
 *  @desc 根据两点间的经纬度计算距离 
 *  @param float $latitude 纬度值 
 *  @param float $longitude 经度值 
 */
function getDistance($latitude1, $longitude1, $latitude2, $longitude2) {
    $earth_radius = 6371000;   //approximate radius of earth in meters  
    $dLat = deg2rad($latitude2 - $latitude1);
    $dLon = deg2rad($longitude2 - $longitude1);
    /*
      Using the
      Haversine formula
      http://en.wikipedia.org/wiki/Haversine_formula
      http://www.codecodex.com/wiki/Calculate_Distance_Between_Two_Points_on_a_Globe
      验证：百度地图  http://developer.baidu.com/map/jsdemo.htm#a6_1
      calculate the distance
     */
    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * asin(sqrt($a));
    $d = $earth_radius * $c;
    return round($d);   //四舍五入  
}

/**
 * 地址转换经纬度坐标
 * @param type $address
 * @return type
 */
function address2latlag($address, $city_name) {
    $url = 'http://api.map.baidu.com/geocoder/v2/?city=' . $city_name . '&address=' . $address . '&output=json&ak=rtEVdPv2ZV9YgkKf3m5HTwW8zX3e5vr6';
    if ($result = file_get_contents($url)) {
        $res = json_decode($result, true);
        if ($res["status"] == "0") {
            return $res["result"]["location"];
        }
    }
    return null;
}
/**
 * 
 * @param type $url
 * @param type $cookie
 * @param type $post_data 如果不为空，表示post
 * @return boolean
 */
function http_request($url, $cookie = '', $post_data = '') {
    $curl = curl_init();
    if (stripos($url, "https://") !== FALSE) {
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
    }
    if ($cookie) {
        curl_setopt($curl, CURLOPT_COOKIE, $cookie); //存储cookies
    }
    curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.57 Safari/536.11");
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    if ($post_data) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
    }
    $content = curl_exec($curl);
    $status = curl_getinfo($curl);
    curl_close($curl);
    if (intval($status["http_code"]) == 200) {
        return $content;
    } else {
        return false;
    }
}

function upload_attachments() {
    $attachments = array();
    $base64_images_content = I("post.image_files");
    if ($base64_images_content) {
        $index = 1;
        foreach ($base64_images_content as $image) {
            if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $image, $result)) {
                $result = upload_attachment($image);
                if ($result)
                    $attachments[] = $result;
            } else {
                break;
            }
        }
    }
    return $attachments;
}


function upload_attachment($base64_image_data) {
    if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_data, $result)) {
        $extension = $result[2];
        $descript = $result[1];
        $path =  MODULE_UPLOAD_PATH."/BillImages/" . date("Ymd");
        mkdir($path, 0777, true);
        $file = md5($base64_image_data) . ".$extension"; //文件名用md5（$base64_image_data）保证同文件覆盖
        $new_file = $path . "/" . $file;
        if (is_file($new_file)){
            return buildMessage("文件已经上传过", 2);
        }
        if (file_put_contents($new_file, base64_decode(str_replace($descript, '', $base64_image_data)))){
            return buildMessage(str_replace("./", "/", $new_file));
        }
    }
    return buildMessage("上传失败", 1);
}

function add_timer($delay, $url, $client = null) {
    $result = false;
    if (empty($url)) {
        \Think\Log::write("url is empty!");
        return false;
    }
    $is_new_client = ($client == null);
    if ($is_new_client) {
        $client = new \swoole_client(SWOOLE_SOCK_TCP);
        //连接到服务器
        if (!$client->connect("127.0.0.1", 9557, 0.5)) {
            \Think\Log::write("connect failed. error:".$client->errCode);
            return false;
        }
    }
    //插入消息到记录
    $key = md5($url . $delay);
    $package["id"] = $key;
    $package["url"] = $url;
    $package["event_time"] = time() + $delay;
    $json_data = json_encode($package);
    if (!$client->send($json_data . "\r\n")) {
        \Think\Log::write("client send fail, package:$json_data");
        $result = false;
    }
    //关闭连接
    if ($is_new_client) {
        $client->close();
    }
    return $result;
}

//utf-8无法json_encode转换
function try_convert_to_utf8($data)
{
    if (is_string($data)) {
        return iconv("UTF-8", "UTF-8//TRANSLIT", $data);
    } elseif (is_array($data)) {
        $ret = [];
        foreach ($data as $i => $d) $ret[ $i ] = try_convert_to_utf8($d);

        return $ret;
    } elseif (is_object($data)) {
        foreach ($data as $i => $d) $data->$i = try_convert_to_utf8($d);

        return $data;
    } else {
        return $data;
    }
}

function getWxTemplateIdByStandardId($standard_id){
    $branch_id = getBrowseBranchId();
    $condition["standard_id"] = $standard_id;
    $condition["branch_id"] = $branch_id;
    $template_id  = M('WxTemplateConfig')->where($condition)->getField("template_id");
    if (empty($template_id)){
        $template_id = getWeChatInstance()->addTemplateMessage($standard_id);
        if ($template_id){
            $condition["template_id"] = $template_id;
            M('WxTemplateConfig')->add($condition);
        }
    }
    return (trim($template_id) == '' || !$template_id) ? false : trim($template_id);
}
//主方法
//beginStr 开始时间 endStr 结束时间 data 传入数据
//single 单次 daily 每日 weekly 每周 monthly 每月 quarterly 每季度 yearly 每年
//传入数据说明 :
// daily  -    inventory=[sn] 编号;    prohibit=[]; [6,7] 6 周六 7 周日 禁止项 如果没有禁止项的话 传入[]
// weekly -    inventory=[definition,sn];arrays definition: d:定义日期(1-7)  编号
// monthly -   inventory=[definition,sn];arrays definition: d:定义日期(1-31) 编号 如果定义日期超过当月最大日期时 默认为最后一天
// quarterly - inventory=[definition,sn];arrays definition: m:季度的第几个月(1-3) d:定义日期(1-31) 编号 如果定义日期超过当月最大日期时 默认为最后一天
// yearly -    inventory=[definition,sn];arrays definition: m:月份 d:定义日期(1-31) 编号 如果定义日期超过当月最大日期时 默认为最后一天
// 传出数据说明 :
// sn   -  周期编号
// planned_at - 计划时间(时间戳)
// planned_str - 计划时间(字符串)
// serial - 序号 从1开始
// section_begin_time - 周期开始日期(时间戳)
// section_begin_str - 周期开始日期(字符串)
// section_end_time - 周期结束日期(时间戳)
// section_end_str - 周期结束日期(字符串)
//数据模拟
// $beginStr = '2018/2/8 11:11:11';
// $endStr   = '2018/4/11 11:11:11';
// $data = [
//    'inventory' => [
//        ['sn' =>1,'definition'=>['m'=>1,'d'=>1]],
//        ['sn' =>2,'definition'=>['m'=>1,'d'=>2]],
//        ['sn' =>3,'definition'=>['m'=>2,'d'=>2]],
//        ['sn' =>4,'definition'=>['m'=>3,'d'=>5]]
//    ],
//    'cycle' => 'daily'
// ];
function handlerTimeDistribution($beginStr,$endStr,$data) {
    $beginStr = date('Y/m/d  H:i:s',strtotime($beginStr));
    $endStr = date('Y/m/d  H:i:s',strtotime($endStr));
    $beginDate = handlerRegionDateTime('Y/m/d H:i:s',$beginStr);
    $endDate = handlerRegionDateTime('Y/m/d H:i:s',$endStr);
    $inventory = [];
    $weekly = [
        '1'=>'Monday',
        '2'=>'Tuesday',
        '3'=>'Wednesday',
        '4'=>'Thursday',
        '5'=>'Friday',
        '6'=>'Saturday',
        '7'=>'Sunday',
    ];
    switch ($data['cycle']) {
        case 'single':
            return $data['inventory'];
            break;
        case 'daily':
            $SectionBeginDate = handlerRegionDateTime('Y/m/d',date('Y/m/d',strtotime($beginStr)));
            $SectionBeginDate->setTime(00,00,00);
            $serial = 0;
            $prohibit = [];
            if (!is_null($data['prohibit'])) {
                if (!is_array($data['prohibit'])) {
                    $data['prohibit'] = explode(',',$data['prohibit']);
                }
                foreach ($data['prohibit'] as $key=>$value) {
                    $prohibit[] = $weekly[$value];
                }
            }
            if ($beginDate->getTimestamp() != $SectionBeginDate->getTimestamp()) {
                $SectionBeginDate->modify('+1 day');
            }
            for ( ; $SectionBeginDate->getTimestamp() < $endDate->getTimestamp() ; $SectionBeginDate->modify('+1 day') ) {
                $SectionEndDate = clone $SectionBeginDate;
                $SectionEndDate->modify('+1 day -1 second');
                if (!in_array($SectionBeginDate->format('l'),$prohibit)){
                    foreach ($data['inventory'] as $key => $val) {
                        $serial ++;
                        $inventory[] = [
                            'sn' => $val['sn'],
                            'planned_at' => $SectionBeginDate->getTimestamp(),
                            'planned_str' => $SectionBeginDate->format('Y/m/d H:i:s'),
                            'serial' => $serial,
                            'section_begin_time' => $SectionBeginDate->getTimestamp(),
                            'section_end_time' => $SectionEndDate->getTimestamp(),
                            'section_begin_str' => $SectionBeginDate->format('Y/m/d H:i:s'),
                            'section_end_str' => $SectionEndDate->format('Y/m/d H:i:s'),
                        ];
                    }
                }
            }
            return $inventory;
            break;
        case 'weekly':
            $SectionBeginDate = clone $beginDate;
            $SectionBeginDate->modify('Monday');
            $serial = 0;
            for ( ; $SectionBeginDate->getTimestamp() < $endDate->getTimestamp() ; $SectionBeginDate->modify('Monday +1 week') ) {
                $SectionDate = clone $SectionBeginDate;
                $SectionEndDate = clone $SectionBeginDate;
                $SectionEndDate->modify('Monday +1 week -1 second');
                foreach ($data['inventory'] as $key => $val) {
                    if ($SectionDate->modify($weekly[$val['definition']['d']])->getTimestamp() >= $beginDate->getTimestamp() && $SectionDate->modify($weekly[$val['definition']['d']])->getTimestamp() <= $endDate->getTimestamp()) {
                        $serial ++;
                        $inventory[] = [
                            'sn' => $val['sn'],
                            'planned_at' => $SectionDate->modify($weekly[$val['definition']['d']])->getTimestamp(),
                            'planned_str' => $SectionDate->modify($weekly[$val['definition']['d']])->format('Y/m/d H:i:s'),
                            'serial' => $serial,
                            'section_begin_time' => $SectionBeginDate->getTimestamp(),
                            'section_end_time' => $SectionEndDate->getTimestamp(),
                            'section_begin_str' => $SectionBeginDate->format('Y/m/d H:i:s'),
                            'section_end_str' => $SectionEndDate->format('Y/m/d H:i:s'),
                        ];
                    }
                }
            }
            return $inventory;
            break;
        case 'monthly':
            //$SectionBeginDate  -  每月1日 $SectionEndDate  - 每月最后一天 $SectionDate  - 每个子任务计划时间
            $SectionBeginDate = handlerRegionDateTime('Y/m/d',handlerRegionDateTime('Y/m/d H:i:s',$beginStr)->format('Y/m/1'));
            $SectionBeginDate->setTime(00,00,00);
            $serial = 0;
            for ( ; $SectionBeginDate->getTimestamp() < $endDate->getTimestamp() ; $SectionBeginDate->modify('+1 month') ) {
                $SectionEndDate = clone $SectionBeginDate;
                $SectionEndDate->modify('last day of this month');
                $SectionEndDate->setTime(00,00,00);
                $SectionEndDate->modify('+1 day -1 second');
                foreach ($data['inventory'] as $key => $val) {
                    $SectionDate = handlerRegionDateTime('Y/m/d',$SectionBeginDate->format('Y/m/'.$val['definition']['d']));
                    $SectionDate->setTime(00,00,00);
                    if ($SectionDate->getTimestamp() > $SectionEndDate->getTimestamp()) {
                        $SectionDate = clone $SectionEndDate;
                        $SectionDate->modify('-1 day +1 second');
                    }
                    if ($SectionDate->getTimestamp() >= $beginDate->getTimestamp() && $SectionDate->getTimestamp() <= $endDate->getTimestamp()) {
                        $serial ++;
                        $inventory[] = [
                            'sn' => $val['sn'],
                            'planned_at' => $SectionDate->getTimestamp(),
                            'planned_str' => $SectionDate->format('Y/m/d H:i:s'),
                            'serial' => $serial,
                            'section_begin_time' => $SectionBeginDate->getTimestamp(),
                            'section_end_time' => $SectionEndDate->getTimestamp(),
                            'section_begin_str' => $SectionBeginDate->format('Y/m/d H:i:s'),
                            'section_end_str' => $SectionEndDate->format('Y/m/d H:i:s'),
                        ];
                    }
                }
            }
            return $inventory;
            break;
        case 'quarterly':
            $quarter = ceil($beginDate->format('n')/3);
            $SectionBeginDate = handlerRegionDateTime('Y/m/d',$beginDate->format('Y').'/'.(($quarter - 1) *3 +1).'/01');
            $SectionBeginDate->setTime(00,00,00);
            $serial = 0;
            for ( ; $SectionBeginDate->getTimestamp() < $endDate->getTimestamp() ; $SectionBeginDate->modify('+3 month') ) {
                $SectionEndDate = clone $SectionBeginDate;
                $SectionEndDate->modify('+3 month -1 second');
                $firstMonth = $SectionBeginDate->format('m');
                foreach ($data['inventory'] as $key => $val) {
                    $inventory_template = (int)$firstMonth + $val['definition']['m'] - 1;
                    $SectionDate = handlerRegionDateTime('Y/m/d',$SectionBeginDate->format('Y/'.$inventory_template.'/'.$val['definition']['d']));
                    $SectionDate->setTime(00,00,00);
                    if ($SectionDate->format('m') != $inventory_template) {
                        $SectionDate->modify('-1 month last day of this month');
                    }
                    if ($SectionDate->getTimestamp() >= $beginDate->getTimestamp() && $SectionDate->getTimestamp() <= $endDate->getTimestamp()) {
                        $serial ++;
                        $inventory[] = [
                            'sn' => $val['sn'],
                            'planned_at' => $SectionDate->getTimestamp(),
                            'planned_str' => $SectionDate->format('Y/m/d H:i:s'),
                            'serial' => $serial,
                            'section_begin_time' => $SectionBeginDate->getTimestamp(),
                            'section_end_time' => $SectionEndDate->getTimestamp(),
                            'section_begin_str' => $SectionBeginDate->format('Y/m/d H:i:s'),
                            'section_end_str' => $SectionEndDate->format('Y/m/d H:i:s'),
                        ];
                    }
                }

            }
            return $inventory;
            break;
        case 'yearly':
            $SectionBeginDate = handlerRegionDateTime('Y/m/d',$beginDate->format('Y').'/01/01');
            $SectionBeginDate->setTime(00,00,00);
            $serial = 0;
            for ( ; $SectionBeginDate->getTimestamp() < $endDate->getTimestamp() ; $SectionBeginDate->modify('+1 year') ) {
                $SectionEndDate = clone($SectionBeginDate);
                $SectionEndDate->modify('+1 year -1 day');
                foreach ($data['inventory'] as $key => $val) {
                    $SectionDate = handlerRegionDateTime('Y/m/d',$SectionBeginDate->format('Y/'.$val['definition']['m'].'/'.$val['definition']['d']));
                    $SectionDate->setTime(00,00,00);
                    if ($SectionDate->format('m') != $val['definition']['m']) {
                        $SectionDate->modify('-1 month last day of this month');
                    }
                    if ($SectionDate->getTimestamp() >= $beginDate->getTimestamp() && $SectionDate->getTimestamp() <= $endDate->getTimestamp()) {
                        $serial++;
                        $inventory[] = [
                            'sn' => $val['sn'],
                            'planned_at' => $SectionDate->getTimestamp(),
                            'planned_str' => $SectionDate->format('Y/m/d H:i:s'),
                            'serial' => $serial,
                            'section_begin_time' => $SectionBeginDate->getTimestamp(),
                            'section_end_time' => $SectionEndDate->getTimestamp(),
                            'section_begin_str' => $SectionBeginDate->format('Y/m/d H:i:s'),
                            'section_end_str' => $SectionEndDate->format('Y/m/d H:i:s'),
                        ];
                    }
                }
            }
            return $inventory;
            break;
        default:
            return false;
            break;
    }
}
//时间格式化 - 以年为单位的时间端
//输出 日期(11:30  11-16 11-17) 时间(今天 昨天 周五 周六) 年份(2018)
function formatRevealParagraphTime($time) {
    $thisTime = handlerRegionDateTime('Y/m/d H:i:s',date('Y/m/d  H:i:s',$time));
    $currentTime = handlerRegionDateTime('Y/m/d H:i:s',date('Y/m/d  H:i:s',time()));
    if ($thisTime->format('Y/m/d') === $currentTime->format('Y/m/d')){
        return [
            'time'=>$thisTime->format('H:i'),
            'day' =>'今天',
            'year'=>$thisTime->format('Y')
        ];
    } else {
        $currentTime->modify('-1 day');
        $currentTime->setTime(0,0,0);
        if ($thisTime->getTimestamp() > $currentTime->getTimestamp()){
            return [
                'time'=>$thisTime->format('m-d'),
                'day' =>'昨天',
                'year'=>$thisTime->format('Y')
            ];
        } else {
            $weekly = [
                '0' => '周日',
                '1' => '周一',
                '2' => '周二',
                '3' => '周三',
                '4' => '周四',
                '5' => '周五',
                '6' => '周六'
            ];
            return [
                'time'=>$thisTime->format('m-d'),
                'day' =>$weekly[$thisTime->format('w')],
                'year'=>$thisTime->format('Y')
            ];
        }
    }
}
//时间处理初始化
function handlerRegionDateTime ($strTimeDefault,$strTime)
{
    $date = new \DateTime();
    return $date->createFromFormat($strTimeDefault,$strTime,new \DateTimeZone('Asia/Shanghai'));
}

function genUniqidKey(){
    return md5(uniqid(mt_rand(), true));
}
function getWxTemplateCurrencyTip($various)
{
    //设置tip默认值
    $currency = [
        TCT_RECHARGE_COMPLETE_NOTICE =>'您好，您的充值金额已入账，请查看确认',
        TCT_RECHARGE_REFUSE_NOTICE =>'您好，您的充值操作失败，请确认付款是否成功。',
        TCT_WITHDRAWAL_COMPLETE_NOTICE =>'您好，您的提现已转入你的银行卡，请查收',
        TCT_WITHDRAWAL_REFUSE_NOTICE =>'您好，您的提现失败，具体原因请联系商家',
        TCT_BRANCH_RECHARGE_COMPLETE_NOTICE =>'客户充值成功通知',
        TCT_BRANCH_RECHARGE_REFUSE_NOTICE =>'客户充值失败通知',
        TCT_BRANCH_WITHDRAWAL_COMPLETE_NOTICE =>'客户提现成功通知',
        TCT_BRANCH_WITHDRAWAL_REFUSE_NOTICE =>'客户提现失败通知',
        TCT_USER_INCOME_COMPLETE_NOTICE =>'您好，您有一笔转账支出，操作已成功，请查看确认。',
        TCT_COMPANY_INCOME_COMPLETE_NOTICE =>'您好，您有一笔转账收入，请查看确认。',
        TCT_DISTRIBUTION_COMPLETE_NOTICE =>'您好，您有一笔佣金收入已入账，请查看确认。',

        TCT_AGREEMENT_UPDATE_MONEY_NOTICE =>"您好，您的合同金额已变更，详情请查看确认",
        TCT_BRANCH_AGREEMENT_UPDATE_MONEY_NOTICE =>"客户合同金额已变更，点击查看详情",
        TCT_INVOICE_NOTICE =>"您好，您的发票已开，请确认后签收，谢谢您的配合",
        TCT_BRANCH_INVOICE_NOTICE =>"发票签收通知，点击查看详情",
        TCT_CANCEL_INVOICE_NOTICE =>"您好，以下编号发票已作废，请知悉",
        TCT_BRANCH_CANCEL_INVOICE_NOTICE =>"发票作废通知，点击查看详情",
        TCT_CANCEL_APPLY_NOTICE =>"您好，您的发票申请已取消，具体原因我们会与您联系",
        TCT_BRANCH_CANCEL_APPLY_NOTICE =>"取消客户申请开票通知，点击查看详情",
        TCT_FINISH_INVOICE_NOTICE =>"此合同结束开票，请知悉",
        TCT_BRANCH_FINISH_INVOICE_NOTICE =>"客户合同开票结束通知，点击查看详情",

        TCT_OVERDUE_FREEZE_ASSIGNMENT_DAY=>"3天",
        TCT_OFFLINE_PAYMENT_ARTIFICIAL_NOTICE=>"您好，您的服务费用已收，谢谢您对我们工作的支持",
        TCT_BRANCH_OFFLINE_PAYMENT_ARTIFICIAL_NOTICE=>"客户线下付款成功通知，点击查看详情",
        TCT_BALANCE_PAYMENT_AUTOMATIC_NOTICE=>"您好，您的服务费已从资金账户支付，谢谢您对我们工作的支持",
        TCT_BRANCH_BALANCE_PAYMENT_AUTOMATIC_NOTICE=>"客户线上付款成功通知，点击查看详情",
        TCT_REFUND_AUTOMATIC_NOTICE=>"您好，您的资金账户收到服务费退款，请查看确认",
        TCT_BRANCH_REFUND_AUTOMATIC_NOTICE=>"客户退款通知，点击查看详情",
        TCT_BAD_DEBT_AUTOMATIC_NOTICE=>"客户合同未付金额已做坏账处理，特此通知",
        TCT_BRANCH_BAD_DEBT_AUTOMATIC_NOTICE=>"客户坏账通知，点击查看详情",
        TCT_FREEZE_AUTOMATIC_NOTICE=>"您的服务费已逾期，如未及时续费，系统将在3天后冻结服务工作",
        TCT_BRANCH_FREEZE_AUTOMATIC_NOTICE=>"客户逾期通知，点击查看详情",
        TCT_AUTOMATIC_RENEWAL_NOTICE=>"您好，您的服务费即将到期，系统将在续费日自动扣款续费，请保证资金账户余额充足，谢谢您对我们工作的支持",
        TCT_BRANCH_AUTOMATIC_RENEWAL_NOTICE=>"客户续费通知，点击查看详情",
        TCT_MANUAL_RENEWAL_NOTICE=>"您好，您的服务费即将到期，请及时续费，谢谢您对我们工作的支持",
        TCT_BRANCH_MANUAL_RENEWAL_NOTICE=>"客户续费通知，点击查看详情",
        TCT_DELETE_RECEIPT_RECORD_NOTICE=>"您好，您的合同有一笔付款记录由于我们的失误，现已将收款确认记录删除，详情请查看确认",
        TCT_BRANCH_DELETE_RECEIPT_RECORD_NOTICE=>"客户付款记录删除通知，点击查看详情"
    ];
    $tctCache = S('_wx_template_currency_tip');
    if(empty($tctCache[$various])){
        $condition['various'] = $various;
        $condition['branch_id'] = getBrowseBranchId();
        $TCT = M('wx_template_currency_tip') ->where($condition)->find();
        $tctCache[$various] = $TCT;
        S('_wx_template_currency_tip',$tctCache);
    } else {
        $TCT = $tctCache[$various];
    }
    return empty($TCT['message']) ? $currency[$various] : $TCT['message'];
}

function  removeEmoji($text) {
    $clean_text = "";

    $regexDingbats = '/[\x{10000}-\x{10FFFF}]/u';
    $clean_text = preg_replace($regexDingbats, '', $text);
    // Match Emoticons
    $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
    $clean_text = preg_replace($regexEmoticons, '', $clean_text);

    // Match Miscellaneous Symbols and Pictographs
    $regexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
    $clean_text = preg_replace($regexSymbols, '', $clean_text);

    // Match Transport And Map Symbols
    $regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';
    $clean_text = preg_replace($regexTransport, '', $clean_text);

    // Match Miscellaneous Symbols
    $regexMisc = '/[\x{2600}-\x{26FF}]/u';
    $clean_text = preg_replace($regexMisc, '', $clean_text);

    // Match Dingbats
    $regexDingbats = '/[\x{2700}-\x{27BF}]/u';
    $clean_text = preg_replace($regexDingbats, '', $clean_text);

    // Match Flags
    $regexDingbats = '/[\x{1F1E6}-\x{1F1FF}]/u';
    $clean_text = preg_replace($regexDingbats, '', $clean_text);

    // Others
    $regexDingbats = '/[\x{1F910}-\x{1F95E}]/u';
    $clean_text = preg_replace($regexDingbats, '', $clean_text);

    $regexDingbats = '/[\x{1F980}-\x{1F991}]/u';
    $clean_text = preg_replace($regexDingbats, '', $clean_text);

    $regexDingbats = '/[\x{1F9C0}]/u';
    $clean_text = preg_replace($regexDingbats, '', $clean_text);

    $regexDingbats = '/[\x{1F9F9}]/u';
    $clean_text = preg_replace($regexDingbats, '', $clean_text);

    return $clean_text;
}

/**获取树形数据的非叶子节点
 * @param $list
 */
function getTreeNodes($list, $fld_parent = "parent_id"){
    $result = array();
    $list_ref = array();
    foreach ($list as $key=>$value){
        $list_ref[$value["id"]] = &$list[$key];
    }
    foreach ($list as $key=>$value){
        $parent = $value[$fld_parent];
        if ($parent) {
            $result[$parent] = $list_ref[$parent];
        }else{
            $result[$value["id"]] = $value;
        }
    }
    return $result;
}

/**
 * 二维数组根据某个字段排序
 * @param array $array 要排序的数组
 * @param string $keys   要排序的键字段
 * @param string $sort  排序类型  SORT_ASC     SORT_DESC
 * @return array 排序后的数组
 */
function arraySort($array, $keys, $sort = SORT_DESC) {
	$keysValue = [];
	foreach ($array as $k => $v) {
		$keysValue[$k] = $v[$keys];
	}
	array_multisort($keysValue, $sort, $array);
	return $array;
}
if(!function_exists('pr'))
{
	/**
	 * 测试用打印函数
	 * @param array $arr
	 */
	function pr($arr)
	{
		if ($arr) {
			echo '<pre>';
			print_r($arr);
			echo '</pre>';
		} else {
			echo 'pr数组为空！';
		}
	}

}

if(!function_exists('vd'))
{
	/**
	 * 测试用打印函数
	 * @param array $arr
	 */
	function vd($arr)
	{
		if ($arr) {
			echo '<pre>';
			var_dump($arr);
			echo '</pre>';
		} else {
			echo 'vd数组为空！';
		}
	}

}

if (!function_exists('getFileType'))
{
	/**
	 * 获取文件的后缀对应的图标
	 */
	function getFileType($fileUrl){
		$endstr = end(explode('.',$fileUrl));
		$type = 'unknown';
		if ($endstr == 'doc' || $endstr == 'docx'){
			$type = 'word';
		} elseif ($endstr == 'xls' || $endstr == 'xlsx'){
			$type = 'excel';
		} elseif ($endstr == 'txt'){
			$type = 'txt';
		}

		return $type;
	}
}

if (!function_exists('getDescribeByTitle'))
{
	/**
	 * 获取文件的后缀对应的图标
	 */
	function getDescribeByTitle($branchId,$title){

		$describe = M('ComProgressParameter')
			->where(array('branch_id'=>array('eq',$branchId),'progress_type_name'=>array('eq',$title),'is_system'=>1))
			->getField('progress_situation');

		return $describe ? $describe : '';
	}
}