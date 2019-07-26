<?php

/* 票据种类 */
const FLAG_BILL_TAX_INCOME = 1; //自开发票
const FLAG_BILL_TAX_PAY = 2; //费用类外来发票
const FLAG_BILL_SALARY = 3; //工资相关
const FLAG_BILL_BANK = 4; //银行--社保税收
const FLAG_BILL_FARMPRODUCE = 5; //农产品收购

const FLAG_SOURCE_INCOME = 0; //收入
const FLAG_SOURCE_PAY = 1; //支出

const ENTERPRISE_TYPE_LNMY = 1; //农、林、牧、渔业
const ENTERPRISE_TYPE_ZZY = 2; //制造业
const ENTERPRISE_TYPE_JZY = 3; //建筑业
const ENTERPRISE_TYPE_XXJS = 4; //信息传输、软件和信息技术服务业
const ENTERPRISE_TYPE_FDC = 5; //房地产业
const ENTERPRISE_TYPE_PFLS = 6; //批发和零售贸易业
const ENTERPRISE_TYPE_JRBX = 7; //金融（除银行业外）、保险业
const ENTERPRISE_TYPE_DMS = 8; //电力、煤气及水的生产和供应业
const ENTERPRISE_TYPE_CKY = 9; //采矿业
const ENTERPRISE_TYPE_JTCCYZ = 10; //交通运输、仓储和邮政业
const ENTERPRISE_TYPE_ZSCY = 11; //住宿和餐饮业
const ENTERPRISE_TYPE_ZLSW = 12; //租赁和商务服务业
const ENTERPRISE_TYPE_KXYJ = 13; //科学研究和技术服务业
const ENTERPRISE_TYPE_SLHJ = 14; //水利、环境和公共设施管理业
const ENTERPRISE_TYPE_FWXL = 15; //居民服务、修理和其他服务业
const ENTERPRISE_TYPE_JY = 16; //教育
const ENTERPRISE_TYPE_WSSG = 17; //卫生和社会工作
const ENTERPRISE_TYPE_WTYL = 18; //文化、体育和娱乐业
const ENTERPRISE_TYPE_PUBLIC = 19; //公共管理、社会保障和社会组织
const ENTERPRISE_TYPE_ORG = 20; //国际组织

const ENTERPRISE_TYPES = array(
    ENTERPRISE_TYPE_LNMY =>"农、林、牧、渔业",
    ENTERPRISE_TYPE_ZZY=>"制造业",
    ENTERPRISE_TYPE_JZY=>"建筑业",
    ENTERPRISE_TYPE_XXJS=>"信息传输、软件和信息技术服务业",
    ENTERPRISE_TYPE_FDC=>"房地产业",
    ENTERPRISE_TYPE_PFLS=>"批发和零售贸易业",
    ENTERPRISE_TYPE_JRBX=>"金融（除银行业外）、保险业",
    ENTERPRISE_TYPE_DMS=>"电力、煤气及水的生产和供应业",
    ENTERPRISE_TYPE_CKY=>"采矿业",
    ENTERPRISE_TYPE_JTCCYZ=>"交通运输、仓储和邮政业",
    ENTERPRISE_TYPE_ZSCY=>"住宿和餐饮业",
    ENTERPRISE_TYPE_ZLSW=>"租赁和商务服务业"
);

/* 费用部门 */
const FEE_DEPATMENT_MANAGE = "manage";
const FEE_DEPATMENT_SALES = "sales";
const FEE_DEPATMENT_FINANCE = "finance";
const FEE_DEPATMENT_PRODUCTION = "production";
const FEE_DEPATMENT_PROJECT = "project";//施工
const FEE_DEPATMENT_RD = "rd"; //研发
//const FEE_DEPATMENT_BUILDING = "building"; //施工

const FEE_DEPARTMENTS = array(
    FEE_DEPATMENT_SALES => "销售部门",
    FEE_DEPATMENT_MANAGE => "管理部门",
    FEE_DEPATMENT_FINANCE => "财务部门",
    FEE_DEPATMENT_RD=>"研发部门",
    FEE_DEPATMENT_PRODUCTION => "生产部门",
    FEE_DEPATMENT_PROJECT => "施工部门"
);

const FEE_SUBJECT_NAMES = array(
    FEE_DEPATMENT_MANAGE => "管理费用",
    FEE_DEPATMENT_SALES => "销售费用",
    FEE_DEPATMENT_FINANCE => "财务费用",
    FEE_DEPATMENT_PRODUCTION => "制造费用",
    FEE_DEPATMENT_PROJECT => "工程施工;工程成本;在建工程"
);

const FLAG_BILL_NAMES = array(
    FLAG_BILL_TAX_INCOME => "自开发票",
    FLAG_BILL_TAX_PAY => "外取票",
    FLAG_BILL_SALARY => "工资册",
    FLAG_BILL_BANK => "银行对账单"
);

const SUBJECT_TYPRS = [
    1=>"流动资产",
    2=>"长期资产",
    3=>"流动负债",
    4=>"长期负债",
    5=>"所有者权益",
    6=>"成本",
    7=>"营业收入",
    8=>"营业成本及税金",
    9=>"期间费用",
    10=>"其他收益",
    11=>"其他损失",
    12=>"所得税",
    13=>"以前年度损益调整"
];

const ENTERPRISE_SCALE_SMALL = 0; //小规模
const ENTERPRISE_SCALE_GENERAL = 1; //一般纳税人

const AMOUNT_FREE_TYPE_MONTH = 0; //免费类型：月
const AMOUNT_FREE_TYPE_SEASON = 1; //免费类型：季

const DIRECTION_DEBIT = 0; //"借"
const DIRECTION_CREDIT = 1; //"贷"
/* 付款类型 */
const PAY_TYPE_PURCHASE = 0; //采购物资
const PAY_TYPE_FEE = 1; //费用
const PAY_TYPE_SALARY = 2; //工资

/* 发票类型 */
const TAX_TYPE_NOMAL = 0; //普通发票
const TAX_TYPE_VTX = 1; //增值税专用发票

/*用户类型->对应用户查看范围*/
const USER_TYPE_SYSTEM_MANAGER = 1;//系统管理员
const USER_TYPE_COMPANY_MANAGER = 2;//公司管理员
const USER_TYPE_COMPANY_SALES = 3;//公司业务员
const USER_TYPE_CUSTOMER = 4;//客户

/*用户预设角色*/
const ROLE_ID_SYSTEM_MANAGER = 1; //公司管理员 权限ID
const ROLE_ID_COMPANY_MANAGER = 2; //公司管理员 权限ID
const ROLE_ID_COMPANY_SALES = 3; //1公司业务员 权限ID
const ROLE_ID_CUSTOMER = 4; //客户 权限ID

const EXPORT_FMT_K3 = "k3";//导出金蝶k3
const EXPORT_FMT_YY = "yongyou";//导出用友

const RATE_ZEROTAX = 0;
const RATE_EXPRESSWAY = 1;
const RATE_ROAD_BRIDGE = 2;
const RATE_VATTAX = 3;

const SUBJECT_IMPORT_COLUMNS = array(
    "subject_no_title"=>"科目编号;科目编码;科目代号;科目代码",
    "subject_name_title"=>"科目名称;名称",
    "subject_type_title"=>"科目类型;科目类别;类型;类别"
);

const BANK_IMPORT_COLUMNS = array(
    "bc_debit"=>"借方;支取;支出;借方金额",
    "bc_credit"=>"贷方;收入;贷方金额",
    "bc_side"=>"对方户名;对方账号名称",
    "bc_summary"=>"摘要",
    "bc_memo"=>"备注;用途",
    "bc_datetime"=>"交易日期;交易时间"
);

const ERROR_REPORT_TO = array(
    "report_mail" =>"akunzeng@qq.com",
    "report_mail_pwd" =>"1234qwer",
    "report_subject" =>"智能凭证-错误消息详情",
    "smtp" =>"smtp.qq.com",
    "smtp_port"=>"587"
);

const HIGHWAY_BRIDGE_TAXRATES = array(
    RATE_EXPRESSWAY => 3,
    RATE_ROAD_BRIDGE =>5
);

/**应收账款科目
 * @param $branch_id
 * @param 二级科目名称
 * @return type
 */
function getReceivableSubject($branch_id, $subject_name) {
    return getVoucherSubject($branch_id, "应收账款", $subject_name); //应收总账科目
}

/**应付账款科目
 * @param $branch_id
 * @param 二级科目名称
 * @return type
 */
function getPayableSubject($branch_id, $subject_name) {
    return getVoucherSubject($branch_id, "应付账款", $subject_name); //应付总账科目
}

/**应收利息科目
 * @param $branch_id
 * @param 二级科目名称
 * @return type
 */
function getInterestSubject($branch_id, $subject_name) {
    return getVoucherSubject($branch_id, "应收利息", $subject_name); //应付总账科目
}

//相识度
function getIsSimilar($source, $dest, $min_compare_length = 3) {
    vendor("ShortTextCompare");
    $str_arr1 = ShortTextCompare::CharToArr($source);
    $str_arr2 = ShortTextCompare::CharToArr($dest);
    $num = ShortTextCompare::S($str_arr1, $str_arr2);
    return $num * 100 > 80;
//    $result = false;
//    $sub_length = mb_strlen($source) - 1; //来源长度
//    while ($sub_length >= $min_compare_length) {
//        $sub_like_name = mb_substr($source, 0, $sub_length);
//        if (mb_stripos($dest, $sub_like_name) !== false) {
//            $result = true;
//            break;
//        }
//        $sub_length--;
//    }
//    return $result;
}

/**
 * 获取凭证对应的科目，传入一级科目名称，二级科目名称（可选），用在生成凭证的时候
 * @param type $branch_id
 * @param type $name
 * @param type $sub_name 可以传入多个
 * @return 如果二级科目没有找到，返回一级科目
 * ex：getVoucherSubject(100,'应收账款','厦门锐速科技有限公司')
 *     getVoucherSubject(100,'管理费用','停车费')
 */
function getVoucherSubject($branch_id, $subject_name, $child_name = null) {
    $subject_data = array();
    if (is_array($subject_name)){
        foreach ($subject_name as $item){
            if ($subject_data = getSubjectByName($branch_id, $item)){
                break;
            }
        }
    }else{
        $subject_data = getSubjectByName($branch_id, $subject_name);
    }
    $subject_child_data = array();
    if ($child_name && $subject_data) {        //查找符合名称的下级科目或对应标准科目
        $parent_id = $subject_data["id"];
        if (is_array($child_name)){
            foreach ($child_name as $item){
                if ($subject_child_data = getSubjectByName($branch_id, $item, $parent_id)){
                    break;
                }
            }
        }else{
            $subject_child_data = getSubjectByName($branch_id, $child_name, $parent_id);
        }
    }
    if (empty($subject_data)){
        return false;
    }else{
        if (empty($child_name)){
            return array("match"=>true, "subject"=>$subject_data);
        }else {
            if ($subject_child_data){
                return array("match"=>true, "subject"=>$subject_child_data);
            }else{
                return array("match"=>false, "subject"=>$subject_data);
            }
        }
    }
}

function getVoucherSubjectError($subject, $subject_name){
    return (empty($subject) || !$subject["match"])?sprintf("找不到【%s】对应的科目", $subject_name):"";
}

/**
 * 获取对应科目，传入一级科目名称，二级科目名称（可选）
 * @param type $branch_id
 * @param type $name
 * @param type $sub_name 可以传入多个
 * @return 如果没有找到返回空
 * ex：getVoucherSubject(100,'应收账款','厦门锐速科技有限公司')
 *     getVoucherSubject(100,'管理费用','停车费')
 */
function getSubject($branch_id, $subject_name, $child_name = null) {
    $voucherSubject = getVoucherSubject($branch_id, $subject_name, $child_name);
    if ($voucherSubject && $voucherSubject["match"]){
        return $voucherSubject["subject"];
    }
    return false;
}

function getSubjectByName($branch_id, $subject_name, $parent_id = null){
    $condition_a["branch_id"] = $branch_id;
    $condition_a["name"] = trim($subject_name);
    if (isset($parent_id)){
        $condition_a["parent_id"] = $parent_id;
    }
    $result = M("VcrSubject")->field("id,name")->where($condition_a)->order("no")->find();
    if (empty($result)){ //找不到再找映射科目
        $condition_b["a.name"] = trim($subject_name);
        $condition_b["b.branch_id"] = $branch_id;
        $condition_b["c.id"] = $branch_id;
        if (isset($parent_id)){
            $condition_b["b.parent_id"] = $parent_id;
        }
        $result = M("VcrSysSubject")->alias("a")
            ->join("inner join vcr_subject b on a.id=b.std_subject_id")
            ->join("inner join sys_branch c on a.ent_type_id=c.ent_type_id")
            ->field("b.id,b.name")
            ->where($condition_b)
            ->order("a.no")
            ->find();
    }
    return $result;
}

//获取子科目
function getChilrenSubjects($branch_id, $subject_name) {
    $condition["p.branch_id"] = $branch_id;
    if (is_array($subject_name)) {
        $condition["p.name"] = array("in", $subject_name);
    } else {
        $condition["p.name"] = trim($subject_name);
    }
    $result = M("VcrSubject p")->field("c.*,p.name as parent_name")->join("vcr_subject c on c.parent_id=p.id")->where($condition)->select();
    return $result;
}

//从历史记录里面查找
function getSubjectIdByGoodsName($branch_id, $goods_name, $row_no) {
    $condition["branch_id"] = $branch_id;
    $condition["goods_name"] = $goods_name;
    $condition["row_no"] = $row_no;
    $data = M("VcrSubjectStudy")->field("subject_id")->where($condition)->find();
    if ($data) {
        return $data["subject_id"];
    }
    return null;
}


//应交税金科目
/**
 * 
 * @param type $branch_id
 * @param type $ent_scale 企业规模
 * @param type $is_sales 是否销项税
 * @return type
 */
function getTaxSubject($branch_id, $ent_scale, $is_sales = true) {
    if ($ent_scale == ENTERPRISE_SCALE_SMALL) { //小规模
        $tax_subject = getVoucherSubject($branch_id, "应交税金", "应交增值税");
    } else { //一般纳税人
        $tax_subject = $is_sales ? getVoucherSubject($branch_id, "应交增值税", array("销项税额","销项税金")) : getVoucherSubject($branch_id, "应交增值税", array("进项税额","进项税金"));
    }
    return $tax_subject;
}

function getCashSubject($branch_id) {
    return getVoucherSubject($branch_id, array("现金","库存现金"));
}

//根据部门获取费用类工资科目
function getFeeForSalarySubject($branch_id, $fee_department) {
    return getFeeSubject($branch_id, $fee_department, array("工资","员工工资"));
}

//根据部门获取费用科目;$fee_subject_name为二级费用科目名称
function getFeeSubject($branch_id, $fee_department, $fee_subject_name) {
    switch ($fee_department) {
        case FEE_DEPATMENT_PROJECT:
            $subject = getVoucherSubject($branch_id, explode(";",FEE_SUBJECT_NAMES[$fee_department]), $fee_subject_name);
            break;
        default:
            $subject = getVoucherSubject($branch_id, FEE_SUBJECT_NAMES[$fee_department], $fee_subject_name);
            break;
    }
    return $subject;
}

//应付职工薪酬-工资
function getSalarySubject($branch_id){
    return getVoucherSubject($branch_id, "应付职工薪酬", "工资");
}
//应付工资-"个人社保","社保"
function getInsuranceSubject($branch_id){
    return getVoucherSubject($branch_id, "应付职工薪酬", array("医社保","社保","医(社)保"));
}

//应付工资-"个人公积金","公积金"
function getFundSubject($branch_id){
    return getVoucherSubject($branch_id, "应付职工薪酬", array("个人公积金","公积金","住房公积金"));
}

//应交税金-个人所得税
function getPersonTaxSubject($branch_id){
    return getVoucherSubject($branch_id, array("应交税金","应交税费"), "个人所得税");
}


/**
 * 获取费用二级科目类型：包括办公费、业务招待费、水电费、差旅费等
 * 再根据费用部门，查找对应的科目,找不到就返回原值
 * @staticvar type $keyParams
 * @param type $keyword
 * @return string
 */
function getFeeSubjectNameByKeyword($keyword) {
    static $keyParams;
    if (empty($keyParams)) {
        $feekeyword_datas = M("VcrFeeKeyword")->cache(true)->select();
        foreach ($feekeyword_datas as $value) {
            $keyword_list = explode(";", $value["keywords"]);
            foreach ($keyword_list as $key) {
                $keyParams[$key] = $value["subject_name"];
            }
        }
    }
    $result = $keyParams[$keyword];
    if (empty($result)) {
        $result = $keyword;
    }
    return $result;
}

/**
 * 获取费用参数自动查询
 * 再根据费用部门，查找对应的科目
 * @staticvar type $keyParams
 * @param type $keyword
 * @return string
 */
function getAllFeeKeys() {
    $result = S("VcrFeeKeyword");
    if (empty($result)){
        $keys_list = M("VcrFeeKeyword")->cache(true)->select();
        foreach ($keys_list as $keys) {
            $key_list = explode(";", $keys["keywords"]);
            foreach ($key_list as $value) {
                $result[] = array("name"=>$value,"querykey"=>firstPinyin($value));
            }
        }
        S("VcrFeeKeyword", $result);
    }
    return $result;
}

function getModuleNameByFlag($bill_flag){
    switch ($bill_flag){
        case FLAG_BILL_TAX_PAY:
        case FLAG_BILL_TAX_INCOME:
            return "VcrBillValueTax";
        case FLAG_BILL_SALARY:
            return "VcrBillSalary";
        case FLAG_BILL_BANK:
            return "VcrBillBankAccount";
        defualt:
            return "BillOther";
    }
}

function getModuleTitleByFlag($bill_flag, $source_flag = null){
    switch($bill_flag){
        case FLAG_BILL_TAX_PAY:
            return "外取票";
        case FLAG_BILL_TAX_INCOME:
            return "自开票";
        case FLAG_BILL_SALARY:
            return "工资相关";
        case FLAG_BILL_BANK:
            return "银行类";
    }
}

/**字符串是否包含数组内任一字符串,传入；分开的字符串或数组
 * @param $search array or string
 * @param $text  string
 */
function str_exists($text, $search)
{
    if (is_string($search)) {
        $search = explode(";", $search);
    }else{
        $search = strval($search);
    }
    $text = strval($text);
    return str_replace($search, "", $text) != $text;
}

//获取导出格式文件模板信息
function getFormatTemplateInfo($format){
    switch ($format){
        case EXPORT_FMT_K3:
            return array("file"=>"k3.dbf", "ext"=>"dbf");
        case EXPORT_FMT_YY:
            return array("file"=>"yongyou.xls", "ext"=>"xls");
        defualt:
            return array("file"=>"k3.dbf", "ext"=>"dbf");
    }
}

//excel内如果是带格式的日期，读取回来会是float
function getFormateExcelDate($date_value){
    if (is_numeric($date_value)){
        $d = 25569;
        $t = 24 * 60 * 60;
        return  gmdate('Y-m-d H:i:s', ($date_value - $d) * $t);
    }else{
        if (strtotime($date_value)){
            return $date_value;
        }else{
            return "";
        }
    }
}


/**$needle字符串是否包含$haystack数组内字符串任意一个
 * @param $needle 查找的字符串
 * @param  $haystack 包含的字符串(数组）
 */
function has_string($needle,  $haystack){
    $result = false;
    if (is_string($haystack)){
        $result = stripos($needle, $haystack) !== false;
    }else{
        foreach ($haystack as $value){
            if (stripos($needle, $value) !== false){
                $result = true;
                break;
            }
        }
    }
    return $result;
}

/**查找字符串是否在尾部
 * ex: str_end_width("iamakunzeng","zeng")
 * @param $text  完整字符串
 * @param $search 需要查找的字符串（数组）
 */
function str_endwith($text, $search){
    $result = false;
    $text_len = mb_strlen($text);
    $array_search = is_string($search)?array($search):$search;
    foreach ($array_search as $value){
        $search_len = mb_strlen($value);
        $last_str = mb_substr($text, $text_len - $search_len, $search_len);
        if (strcmp($last_str, $value) === 0){
            $result = true;
            break;
        }
    }
    return $result;
}

/**获取科目类型编号
 * @param $type_name
 * @return int
 */
function getSubjectTypeId($type_name){
    static $flip;
    if (empty($flip)) {
        $flip = array_flip(SUBJECT_TYPRS);
    }
    return intval($flip[$type_name]);
}

function getEnterpriseType($branch_id){
   return M("SysBranch")->where("id=$branch_id")->getField("ent_type_id");
}

function getBillFlags($includeAll = false){
    if ($includeAll){
        $result[] = array("id"=>0, "name"=>"全部");
    }
    foreach (array_keys(FLAG_BILL_NAMES) as $key){
        $result[] = ["id"=>$key, "name"=>FLAG_BILL_NAMES[$key]];
    }
    return $result;
}