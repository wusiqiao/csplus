<?php

namespace ESAdmin\Model;

use Think\Exception;

class VcrBillBankAccountModel extends VcrBillModel
{

    protected $_bill_flag = FLAG_BILL_BANK;

    /*
     * 获取银行对账单单据资料
     * */
    public function getBillData($accounting_section,$branch_id,$id = ""){
        if(!$id){
            $id = I("post.id");
        }
        //未生成凭证=>>ISNULL(a.voucher_id)，单证资料为银行对账单
        if (empty($id)) {
            $where = sprintf("where ISNULL(a.voucher_id) and a.bill_flag = %d and a.branch_id=%d and accounting_section='%s'", FLAG_BILL_BANK, $branch_id, $accounting_section);
        } else {
            $where = sprintf("where ISNULL(a.voucher_id) and a.bill_flag = %d and a.id in (%s) and a.branch_id=%d", FLAG_BILL_BANK, implode(",", $id), $branch_id);
        }
        $sql = "select a.*,b.name as bank_name,item.goods_name,item.amount from vcr_bill a
                inner join vcr_bill_detail item on item.parent_id=a.id 
                left join vcr_subject b on a.bank_subject=b.id and a.branch_id=b.branch_id $where";
        $bill_datas = $this->query($sql);
        return $bill_datas;
    }

    /**银行对账单，如果匹配不到的，就跳过
     * @param $company_data
     * @return bool
     */
    public function processBill($company_data, $accounting_section){
        $branch_id = $company_data["id"];
        $bill_datas = $this->getBillData($accounting_section,$branch_id);
        $max_bill_no = $this->getMaxBillNoByUserBranch();
        $error_messages = [];
        $success_count = 0;
        foreach ($bill_datas as $bill_data) {
            if ($bill_data["source_flag"] == FLAG_SOURCE_PAY) {//付款
                $result = $this->processPayBill($bill_data, $company_data);
            } else { //收款
                $result = $this->processReceiveBill($bill_data, $company_data);
            }
            $error_messages[] = $result["error"];
            if ($result["list"]) {
                $data_list = $result["list"];
                $draf_data["branch_id"] = $branch_id;
                $draf_data["accounting_section"] = $bill_data["accounting_section"];
                $draf_data["bill_date"] = time();
                $draf_data["bill_no"] = $max_bill_no;
                $draf_data["creater"] = $company_data["creator"];
                $draf_data["number"] = D("VcrVoucherDraf")->getMaxNumber($branch_id);
                $draf_id = M("VcrVoucherDraf")->add($draf_data);
                if ($draf_id) {
                    foreach ($data_list as $key => $data) {
                        $data_list[$key]["parent_id"] = $draf_id;
                    }
                    if (!M("VcrVoucherDrafDetail")->addAll($data_list)) {
                        E("添加子表错误");
                    }
                    D("VcrVoucherDraf")->addDetailChangeLog(["id"=>$draf_id],$data_list);
                    $success_count++;
                    $this->updateRelation($draf_id, array($bill_data["id"]));
                }
                $max_bill_no = $this->incBillNo($max_bill_no);
            } else {
                M("VcrBill")->where(array("id" => $bill_data["id"]))->setField("error", $result["error"]);
                //$error_messages[] = $result["error"];
            }
        }
        //return array_values($error_messages);
        $result['error_message'] = implode("<br>", array_filter(array_values($error_messages)));
        $result['success_count'] = $success_count;
        $result['total_count'] = count($bill_datas);
        return $result;
    }

    /** 处理付款，借：XXXX 贷：银行存款
     * @param $bill_data
     * @param $branch_id
     * 如果名称为空，表示对方单位是银行，
     * 否则对方单位为应付账款，表示还货款或者借款给对方
     *    用途：货款、XX费类如果有对方户名，列到应付账款-户名
     *         如果对方户名为空，表示银行，如果收款，只有利息收入为财务费用，付款的话为都是财务费用。
     *    备用金、往来款：其他应收款-户名
     *    实时扣税类：应交税金（根据结算单再细化二级科目）
     *    付款第一原则：借：应付账款-户名
     */
    public function processPayBill($bill_data, $company_data){
        $branch_id = $company_data["id"];
        $goods_name = $bill_data["goods_name"];
        $other_side = trim($bill_data["name"]);//对方账号，可能全是空的，所以不能用来判断是否是银行
        //先找是否有完全符合的科目
        $debit_subject = getVoucherSubject($branch_id, $goods_name);
        $subject_name = $goods_name;
        if (empty($debit_subject)) {
            //取现/提取备用金
            if (has_string($goods_name, array("取现", "备用金"))) {
                $debit_subject = getCashSubject($branch_id);
                $subject_name = "库存现金";
            }
            //短信费/手续费/年费/收费 → 财务费用—手续费（无对方户名/对方户名为银行）
            if (str_endwith($goods_name, array("费", "费用")) || has_string($goods_name, "收费")) {
                if ($other_side && !$this->has_bank_keyword($other_side)) { //有对方户名，先看有没有二级科目
                    $debit_subject = getVoucherSubject($branch_id, "应付账款", $other_side);
                    $subject_name = "应付账款-" . $other_side;
                } else {
                    if (!has_string($goods_name, array("保", "险"))) {
                        $debit_subject = getVoucherSubject($branch_id, "财务费用", array("手续费", "银行手续费"));
                        $subject_name = "财务费用-手续费";
                    } else {
                        $debit_subject = getVoucherSubject($branch_id, "其他应付款", array($other_side, "其他"));
                        $subject_name = "其他应付款-" . $other_side;
                    }
                }
            }
            //**费用/报销 → 其他应付款—对方户名/其他
            if (has_string($goods_name, "报销")) {
                $debit_subject = getVoucherSubject($branch_id, "其他应付款", array($other_side, "其他"));
                $subject_name = "其他应付款-" . $other_side;
            }
            //工资/薪资 → 应付工资/应付职工薪酬—工资-需要合计，外部处理
            //待银行对账单体现发放时，分录为：借：应付职工薪酬-工资          （这里是实发）
            //贷：银行存款—xx银行       （这里是实发）
            if (has_string($bill_data["goods_name"], array("工资", "薪资"))) { //工资
                $debit_subject = getSalarySubject($company_data["id"]);
                $subject_name = "应付职工薪酬—工资";
            }
            //公积金/社保 → 管理费用—公积金/社保 （公司部分）??无法识别，需要匹配银行结算单

            //其他应付款/其他应收款—社保/公积金（个人部分）??无法识别，需要匹配银行结算单

            //财税库行联网划缴税款/TIPS实时扣税 → 税金及附加—应交** ??也是无法识别，需要匹配银行结算单
//        if (str_endwith($goods_name, "税")) {
//            $debit_subject = getVoucherSubject($branch_id, "税金及附加",$goods_name);
//            $subject_name = "税金及附加-".$goods_name;
//        }

            // (收)定金/订金/预付款 → 预付账款-对方户名
            if (has_string($goods_name, array("定金", "订金", "预付款"))) {
                $debit_subject = getVoucherSubject($branch_id, "预付账款", $other_side);
                $subject_name = "预付账款-" . $other_side;
            }
            // 退款/户名不符退回 → 应收账款(货款类)/其他应收款/其他应付款(费用/往来类)—对方户名----需要往前查找对应金额????
            if (has_string($goods_name, array("退款", "户名不符退回"))) {
                $debit_subject = getVoucherSubject($branch_id, "应收账款", $other_side);
                $subject_name = "应收账款-" . $other_side;
            }
            //往来款（同名户转）银行存款—**银行（特殊，需与另一银行账户对应，且只做一遍）
            //if (stripos($goods_name, "往来款")) {
            if (has_string($goods_name, array("往来"))) {
                $debit_subject = getVoucherSubject($branch_id, "银行存款", $other_side);
                if ($debit_subject) {
                    $subject_name = "银行存款-" . $other_side;
                } else {
                    $debit_subject = getVoucherSubject($branch_id, "其他应收款", $other_side);
                    $subject_name = "其他应收款-" . $other_side;
                }
            }

            //(收)借款/还款/贷款 → 发放贷款—对方户名（金融企业），否则是其他应收款（非金融）
            if (has_string($goods_name, array("借款", "还款", "贷款"))) {
                if ($company_data["ent_type_id"] == ENTERPRISE_TYPE_JRBX) {
                    $debit_subject = getVoucherSubject($branch_id, array("发放贷款", "贷款发放"), $other_side);
                    $subject_name = "发放贷款-" . $other_side;
                } else {
                    $debit_subject = getVoucherSubject($branch_id, "其他应收款", $other_side);
                    $subject_name = "其他应收款-" . $other_side;
                }
            }
            //资金占用费/利息 → 应收利息—对方户名（金融企业）
            if ($company_data["ent_type_id"] == ENTERPRISE_TYPE_JRBX && has_string($goods_name, array("资金占用费", "利息"))) {
                $debit_subject = getVoucherSubject($branch_id, "应收利息", $other_side);
                $subject_name = "应收利息-" . $other_side;
            }

            //付货款
            if (has_string($goods_name, "货款")) {
                $debit_subject = getVoucherSubject($branch_id, "应付账款", $other_side);
                $subject_name = "应付账款-" . $other_side;
            }
            //如果都找不到，默认查找应付账款二级科目
            if (empty($debit_subject) && !empty($other_side)) {
                $debit_subject = getVoucherSubject($branch_id, "应付账款", $other_side);
                $subject_name = "应付账款-" . $other_side;
            }
        }
        //找不到或没有匹配的二级科目，还是要报错误
        $error_message = (empty($debit_subject) || (!$debit_subject["match"])) ? "找不到【" . $subject_name . "】对应的科目" : "";
        $data_list = array();
        if ($debit_subject) { //有找到一级或二级科目
            $data_list[] = array(
                "row_no" => 1,
                "subject_name" => $debit_subject["subject"]["name"],
                "subject_id" => $debit_subject["subject"]["id"],
                "amount" => $bill_data["amount"],
                "summary" => $bill_data["memo"],
                "direction" => DIRECTION_DEBIT,
                "parent_id" => 0,
                "error" => $error_message
            );
            $data_list[] = array(
                "row_no" => 2,
                "subject_name" => "",
                "subject_id" => $bill_data["bank_subject"],
                "amount" => $bill_data["amount"],
                "summary" => "",
                "direction" => DIRECTION_CREDIT,
                "parent_id" => 0,
                "error" => ''
            );
        }
        return array("error" => $error_message, "list" => $data_list);
    }

    /** 处理收款，借：银行存款 贷：XXXX
     * @param $bill_data
     * @param $branch_id
     * 如果名称为空，表示对方单位是银行，
     * 否则对方单位为应收账款，表示收到货款或者收到借款还款
     * 用途货款、XX费类如果有对方户名，列到应收账款-户名
     * 往来款：其他应收款-户名
     * 收入第一原则都是贷：应收账款-户名
     * 特例关键字：往来款（其他应收款-户名）、贷或借款（短期借款）、利息收入（对方户名空）
     */
    public function processReceiveBill($bill_data, $company_data){
        $branch_id = $company_data["id"];
        $goods_name = trim($bill_data["goods_name"]);
        $other_side = trim($bill_data["name"]);//对方账号，可能全是空的，所以不能用来判断是否是银行
        //投资款 → 实收资本—对方户名
        if (has_string($goods_name, array("投资款"))) {
            $credit_subject = getVoucherSubject($branch_id, "实收资本", $other_side);
            $subject_name = "实收资本-" . $other_side;
        }
        //存现/还备用金→ 库存现金
        if (has_string($goods_name, array("存现", "备用金"))) {
            $credit_subject = getCashSubject($branch_id);
            $subject_name = "库存现金";
        }
        // (收)定金/订金/预付款 → 预收账款—对方户名
        if (has_string($goods_name, array("定金", "订金", "预付款"))) {
            $credit_subject = getVoucherSubject($branch_id, "预收账款", $other_side);
            $subject_name = "预收账款-" . $other_side;
        }
        // (收)退款/户名不符退回→ 应付账款(货款类)/其他应付款/其他应收款(费用/往来类)—对方户名-----需要往前查找对应金额????
        if (has_string($goods_name, array("退款", "户名不符退回"))) {
            $credit_subject = getVoucherSubject($branch_id, "应付账款", $other_side);
            $subject_name = "应付账款-" . $other_side;
        }
        //往来款（同名户转）银行存款—**银行（特殊，需与另一银行账户对应，且只做一遍）
        //if (stripos($goods_name, "往来款") !== false) {
        if (has_string($goods_name, array("往来"))) {
            $credit_subject = getVoucherSubject($branch_id, "银行存款", $other_side);
            if ($credit_subject) {
                $subject_name = "银行存款-" . $other_side;
            } else {
                $credit_subject = getVoucherSubject($branch_id, "其他应收款", $other_side);
                $subject_name = "其他应收款-" . $other_side;
            }
        }
        //(收)借款/还款/贷款 → 发放贷款—对方户名（金融企业）如果对方账户为空或摘要+对方账户有银行关键字，就任务是借款，否则是其他应收款（非金融）
        if (has_string($goods_name, array("借款", "还款", "贷款"))) {
            if ($company_data["ent_type_id"] == ENTERPRISE_TYPE_JRBX) {
                $credit_subject = getVoucherSubject($branch_id, array("发放贷款", "贷款发放"), $other_side);
                $subject_name = "发放贷款-" . $other_side;
            } else {
                if (empty($other_side) || has_string($other_side . $goods_name, array("银行", "分行", "支行"))) { //对方账号名称是空，可能是银行或者有银行关键字
                    $credit_subject = getVoucherSubject($branch_id, array("短期借款", "长期借款"));
                    $subject_name = "短期借款";
                } else {
                    $credit_subject = getVoucherSubject($branch_id, "其他应收款", $other_side);
                    $subject_name = "其他应收款-" . $other_side;
                }
            }
        }
        //资金占用费 → 应收利息—对方户名（金融企业）
        if ($company_data["ent_type_id"] == ENTERPRISE_TYPE_JRBX && has_string($goods_name, "资金占用费")) {
            $credit_subject = getVoucherSubject($branch_id, "应收利息", $other_side);
            $subject_name = "应收利息-" . $other_side;
        }
        //利息如果是金融行业，且没有银行关键字，为应收利息，否则为财务费用利息收入
        if (has_string($goods_name, array("利息", "结息"))) {
            if ($company_data["ent_type_id"] == ENTERPRISE_TYPE_JRBX && !has_bank_keyword($goods_name . "," . $other_side)) {
                $credit_subject = getVoucherSubject($branch_id, "应收利息", $other_side);
                $subject_name = "应收利息-" . $other_side;
            } else {
                $credit_subject = getVoucherSubject($branch_id, "财务费用", array("利息收入"));
                $subject_name = "利息收入";
            }
        }
        //政府补助 → 其他收益/营业外收入
        if (stripos($goods_name, "政府补助") !== false) {
            $credit_subject = getVoucherSubject($branch_id, array("其他收益", "营业外收入"));
            $subject_name = "其他收益/营业外收入" . $goods_name;
        }
        //收货款
        if (has_string($goods_name, "货款")) {
            $credit_subject = getVoucherSubject($branch_id, "应收账款", $other_side);
            $subject_name = "应收账款-" . $other_side;
        }
        //如果都找不到，默认查找应收二级科目
        if (empty($credit_subject) && !empty($other_side)) {
            $credit_subject = getVoucherSubject($branch_id, "应收账款", $other_side);
            $subject_name = "应收账款-" . $other_side;
        }
        $error_message = (empty($credit_subject) || (!$credit_subject["match"])) ? "找不到【" . $subject_name . "】对应的科目" : "";
        $data_list = array();
        if ($credit_subject) {
            $data_list[] = array(
                "row_no" => 1,
                "subject_name" => "", //银行
                "subject_id" => $bill_data["bank_subject"],
                "amount" => $bill_data["amount"],
                "summary" => $bill_data["memo"],
                "direction" => DIRECTION_DEBIT,
                "parent_id" => 0,
                "error" => ''
            );
            $data_list[] = array(
                "row_no" => 2,
                "subject_name" => $credit_subject["subject"]["name"],
                "subject_id" => $credit_subject["subject"]["id"],
                "amount" => $bill_data["amount"],
                "summary" => "",
                "direction" => DIRECTION_CREDIT,
                "parent_id" => 0,
                "error" => $error_message
            );
        }
        return array("error" => $error_message, "list" => $data_list);
    }

    protected function insert_detail($id){
        $goods_names = I("post.goods_name");
        $amounts = I("post.amount");
        if (!is_array($goods_names) || !is_array($amounts)) {
            $this->responseJSON(buildMessage("数据格式错误", 1));
        }
        $total_amount = 0;
        foreach ($goods_names as $key => $value) {
            if (empty($value) || !isset($amounts[$key])) {
                $this->responseJSON(buildMessage("数据格式错误", 1));
                break;
            }
            $datas[] = array(
                "goods_name" => $value,
                "amount" => $amounts[$key],
                "parent_id" => $id
            );
            $total_amount = $total_amount + floatval($amounts[$key]);
        };
        M("VcrBillDetail")->addAll($datas);
        $this->execute("update vcr_bill set total_amount=$total_amount,total_sum=$total_amount where id=$id");
    }

    /**导入银行对账单，工资部分合并成1条
     * @param $excel_file
     * @return mixed
     */
    public function import($excel_file, $client_file, $branchId = null){
        Vendor('PHPExcel18.PHPExcel');
        $filePath = realpath("./") . $excel_file;
//        检查文件重复
        $hash = md5_file($filePath);
        if(!$branchId){
            $branchId = session(USER_SESSION_KEY)->currBranchId;
        }

        if ($hash) {
            if (M("VcrBillAttachment")->where(["hash" => $hash, 'branch_id' => $branchId])->count() > 0) {
                return buildMessage("文件已经导入过，无需重复导入！");
            }
        }

        $template = D('VcrBillTemplates')->where(['id' => I("post.template_id")])->find();
        if (empty($template)) {
            return buildMessage("解析模板不存在！", 1);
        }

        $PHPReader = \PHPExcel_IOFactory::createReaderForFile($filePath);
        $PHPReader->setReadDataOnly(true);
        $PHPExcel = $PHPReader->load($filePath);        //建立excel对象
        $currentSheet = $PHPExcel->getSheet(0);        //**读取excel文件中的指定工作表*/
        $totalRows = $currentSheet->getHighestRow();
        if ($template['start'] >= $totalRows) {
            return buildMessage("没有数据，当前模板起始行" . $template['start'], 1);
        }

        try {
            $total_salary_amount = 0;
            $bank_subject = I("post.bank_subject");
            $billData = array();
            $billDataList = array();
            $accounting_sections = array();
            $max_bill_no = $this->getMaxBillNoByUserBranch();
            for ($rowIndex = $template['start']; $rowIndex <= $totalRows; $rowIndex++) {        //循环读取每个单元格的内容。注意行从1开始，列从A开始
                //出
                $debit_amount = str_replace(",", "", trim($currentSheet->getCell($template['disbursement'] . $rowIndex)->getValue()));
                //入
                $credit_amount = str_replace(",", "", trim($currentSheet->getCell($template["income"] . $rowIndex)->getValue()));

                //入 出 判定读取
                if ($template['disbursement'] != $template["income"]) {
                    //不是数字
                    if (!is_numeric($debit_amount) && !is_numeric($credit_amount)) {
                        continue;
                        // return buildMessage("请检查模板，或者文件第{$template['disbursement']}{{$rowIndex},支出的值不是数字", 1);
                    }
                    //入不是数字 可能是借贷标记，
                    if (!is_numeric($credit_amount)) {
                        //当借贷标记值没有时跳过
                        if (empty($template['mark'])) {
                            continue;
                            //return buildMessage("请检查模板, {$template['income']}{$rowIndex}行，不是数字，可能是借贷标记，请配置收入的值", 1);
                        } else {
                            if ($credit_amount == $template['mark']) {
                                $credit_amount = $debit_amount;
                                $debit_amount = 0;
                            } else {
                                $credit_amount = 0;
                            }
                        }
                    }
                } else {
                    if ($debit_amount > 0) {
                        $credit_amount = $debit_amount;
                        $debit_amount = 0;
                    } else {
                        $credit_amount = 0;
                    }
                }

                $debit_amount = abs($debit_amount);
                $credit_amount = abs($credit_amount);
                //对方账户
                $other_side = trim($currentSheet->getCell($template["account"] . $rowIndex)->getValue());
                //交易时间
                $date_time = trim($currentSheet->getCell($template["deal_time"] . $rowIndex)->getValue());
                if(!$date_time){
                    continue;
                }
                if(is_float($date_time + 0)){
                    $date_time = date('Y-m-d H:i:s', \PHPExcel_Shared_Date::ExcelToPHP($date_time));
                }
                $summary = "";
                $memo = "";
                //摘要读取
                if ($template["summary"]) {
                    $summary = trim($currentSheet->getCell($template["summary"] . $rowIndex)->getValue());
                }
                //备注读取
                if ($template["remarks"]) {
                    $memo = trim($currentSheet->getCell($template["remarks"] . $rowIndex)->getValue());
                }

                $goods_name = mergeString($summary, $memo, ",");
                $total_amount = floatval($debit_amount) + floatval($credit_amount);
                if (has_string($goods_name, array("工资", "薪资"))) {
                    $total_salary_amount = $total_salary_amount + $total_amount;
                    continue;
                }
                $user_session = session(USER_SESSION_KEY);
                $billData["bill_date"] = time();
                $billData["bill_no"] = $max_bill_no;
                $billData["bill_flag"] = FLAG_BILL_BANK;
                $billData["branch_id"] = $user_session->currBranchId;
                $billData["name"] = $other_side;
                $billData["user_id"] = $user_session->userId;
                $billData["source_date"] = strtotime($date_time);
                $billData["accounting_section"] = date("Y/m", $billData["source_date"]);
                $billData["creator"] = $user_session->userName;
                $billData["bank_subject"] = $bank_subject;
                $billData["source_flag"] = (empty($debit_amount) || (0 == floatval($debit_amount))) ? FLAG_SOURCE_INCOME : FLAG_SOURCE_PAY; //$debit_amount借方金额为零，表示收款，否则付款
                $billData["memo"] = mergeString($summary, $memo, ",");
                $billData["total_sum"] = $total_amount;
                $billData["total_amount"] = $total_amount;
                $detail["goods_name"] = $goods_name;
                $detail["amount"] = $total_amount;
                $detail["parent_id"] = 0;
                $billData["items"] = $detail;
                array_unshift($billDataList, $billData);
                //保存月
                $accounting_section_key = sprintf("'%s'", $billData["accounting_section"]);
                if (!in_array($accounting_section_key, $accounting_sections)) {
                    $accounting_sections[] = $accounting_section_key;
                }
                $max_bill_no = $this->incBillNo($max_bill_no);
            }
            $sql = sprintf("select id from vcr_bill where branch_id=%d and bank_subject=%d and bill_flag=%d and accounting_section in (%s)",
                $billData["branch_id"], $bank_subject, FLAG_BILL_BANK, implode(',', $accounting_sections));
            $exists_datas = $this->query($sql);
            if ($exists_datas) {
                E("本月银行对账已经存在，如需修改，请直接修改单证资料！");
            }
            //最后加入工资合计
            if ($total_salary_amount > 0) {
                $max_bill_no = $this->incBillNo($max_bill_no);
                $billData["bill_no"] = $max_bill_no;
                $billData["name"] = "公司员工";
                $billData["bank_subject"] = $bank_subject;
                $billData["source_flag"] = FLAG_SOURCE_PAY; //$debit_amount借方金额为零，表示收款，否则付款
                $billData["memo"] = "工资合计";
                $billData["total_sum"] = $total_salary_amount;
                $billData["total_amount"] = $total_salary_amount;
                $detail["goods_name"] = "工资";
                $detail["amount"] = $total_salary_amount;
                $detail["parent_id"] = 0;
                $billData["items"] = $detail;
                array_push($billDataList, $billData);
            }
            $total = count($billDataList);
            $this->startTrans();
            try {
                $attachData['bill_flag'] = FLAG_BILL_BANK;
                $attachData['accounting_section'] = implode(',',  $accounting_sections);
                $attachData["url"] = $excel_file;
                $attachData["hash"] = $hash;
                $attachData["file_name"] = $client_file;
                $attachData["branch_id"] = $user_session->currBranchId;
                $attId = M("VcrBillAttachment")->add($attachData);
                $message["attachment_id"] = $attId;
                foreach ($billDataList as $billData) {
                    $billData["attachment_id"] = $attId;
                    $lastId = M("VcrBill")->add($billData);
                    if ($lastId) {
                        $items = $billData["items"];
                        $items["parent_id"] = $lastId;
                        if (M("VcrBillDetail")->add($items) === false) {
                            E("保存子表数据失败！");
                        }
                    } else {
                        E("保存对账单失败！");
                    }
                }
                $this->commit();
            } catch (Exception $ex) {
                $this->rollback();
                return buildMessage("导入失败，失败原因：" . $ex->getMessage());
            }
            unset($currentSheet);
            unset($PHPExcel);
            unset($PHPReader);
            $result = buildMessage(sprintf("导入成功，导入%d条记录（工资部分已经合并为一条）", $total));
            $result["attachment_id"] = $attId; //返回夹带attachid;
            return $result;
        } catch (Exception $e) {
            return buildMessage($e->getMessage(), 1);
        }
    }

    //原有导入功能
    public function importBack($excel_file, $client_file){
        Vendor('PHPExcel18.PHPExcel');
        $filePath = realpath("./") . $excel_file;
        //检查文件重复
        $hash = md5_file($filePath);
        if ($hash) {
            if (M("VcrBillAttachment")->where(array("hash" => $hash))->count() > 0) {
                return buildMessage("文件已经导入过，无需重复导入！");
            }
        }
        $PHPReader = \PHPExcel_IOFactory::createReaderForFile($filePath);
        $PHPReader->setReadDataOnly(true);
        try {
            $PHPExcel = $PHPReader->load($filePath);        //建立excel对象
            $currentSheet = $PHPExcel->getSheet(0);        //**读取excel文件中的指定工作表*/
            $field_columns = $this->getFieldColumns($currentSheet); //获取科目字段的对应的列
            if (!$field_columns) {
                //unlink($filePath);//删除文件，防止一直上传
                unset($currentSheet);
                unset($PHPExcel);
                unset($PHPReader);
                return buildMessage("文件格式暂时无法识别！", 1);
            }
            $total_salary_amount = 0;
            $bank_subject = I("post.bank_subject");
            $billData = array();
            $billDataList = array();
            $accounting_sections = array();
            $max_bill_no = $this->getMaxBillNoByUserBranch();
            for ($rowIndex = $field_columns["debit_amount"]["row"] + 1; $rowIndex <= $currentSheet->getHighestRow(); $rowIndex++) {        //循环读取每个单元格的内容。注意行从1开始，列从A开始
                $debit_amount = str_replace(",", "", trim($currentSheet->getCell($field_columns["debit_amount"]["col"] . $rowIndex)->getValue()));
                $credit_amount = str_replace(",", "", trim($currentSheet->getCell($field_columns["credit_amount"]["col"] . $rowIndex)->getValue()));
                $date_time = trim($currentSheet->getCell($field_columns["datetime"]["col"] . $rowIndex)->getValue());
                $summary = "";
                $memo = "";
                if ($field_columns["summary"]) {
                    $summary = trim($currentSheet->getCell($field_columns["summary"]["col"] . $rowIndex)->getValue());
                }
                if ($field_columns["memo"]) {
                    $memo = trim($currentSheet->getCell($field_columns["memo"]["col"] . $rowIndex)->getValue());
                }
                $other_side = trim($currentSheet->getCell($field_columns["other_side"]["col"] . $rowIndex)->getValue());
                if ("" == $date_time) {
                    break;
                }
                $goods_name = mergeString($summary, $memo, ",");
                $total_amount = floatval($debit_amount) + floatval($credit_amount);
                if (has_string($goods_name, array("工资", "薪资"))) {
                    $total_salary_amount = $total_salary_amount + $total_amount;
                    continue;
                }
                $user_session = session(USER_SESSION_KEY);
                $billData["bill_date"] = time();
                $billData["bill_no"] = $max_bill_no;
                $billData["bill_flag"] = FLAG_BILL_BANK;
                $billData["branch_id"] = $user_session->currBranchId;
                $billData["name"] = $other_side;
                $billData["user_id"] = $user_session->userId;
                $billData["source_date"] = strtotime($date_time);
                $billData["accounting_section"] = date("Y/m", $billData["source_date"]);
                $billData["creator"] = $user_session->userName;
                $billData["bank_subject"] = $bank_subject;
                $billData["source_flag"] = (empty($debit_amount) || (0 == floatval($debit_amount))) ? FLAG_SOURCE_INCOME : FLAG_SOURCE_PAY; //$debit_amount借方金额为零，表示收款，否则付款
                $billData["memo"] = mergeString($summary, $memo, ",");
                $billData["total_sum"] = $total_amount;
                $billData["total_amount"] = $total_amount;
                $detail["goods_name"] = $goods_name;
                $detail["amount"] = $total_amount;
                $detail["parent_id"] = 0;
                $billData["items"] = $detail;
                array_unshift($billDataList, $billData);
                //保存月
                $accounting_section_key = sprintf("'%s'", $billData["accounting_section"]);
                if (!in_array($accounting_section_key, $accounting_sections)) {
                    $accounting_sections[] = $accounting_section_key;
                }
                $max_bill_no = $this->incBillNo($max_bill_no);
            }
            $sql = sprintf("select id from vcr_bill where branch_id=%d and bank_subject=%d and bill_flag=%d and accounting_section in (%s)",
                $billData["branch_id"], $bank_subject, FLAG_BILL_BANK, implode(',', $accounting_sections));
            $exists_datas = $this->query($sql);
            if ($exists_datas) {
                E("本月银行对账已经存在，如需修改，请直接修改单证资料！");
            }
            //最后加入工资合计
            if ($total_salary_amount > 0) {
                $max_bill_no = $this->incBillNo($max_bill_no);
                $billData["bill_no"] = $max_bill_no;
                $billData["name"] = "公司员工";
                $billData["bank_subject"] = $bank_subject;
                $billData["source_flag"] = FLAG_SOURCE_PAY; //$debit_amount借方金额为零，表示收款，否则付款
                $billData["memo"] = "工资合计";
                $billData["total_sum"] = $total_salary_amount;
                $billData["total_amount"] = $total_salary_amount;
                $detail["goods_name"] = "工资";
                $detail["amount"] = $total_salary_amount;
                $detail["parent_id"] = 0;
                $billData["items"] = $detail;
                array_push($billDataList, $billData);
            }
            $total = count($billDataList);
            $this->startTrans();
            try {
                $attachData["url"] = $excel_file;
                $attachData["hash"] = $hash;
                $attachData["file_name"] = $client_file;
                $attachData["branch_id"] = $user_session->currBranchId;
                $attId = M("VcrBillAttachment")->add($attachData);
                $message["attachment_id"] = $attId;
                foreach ($billDataList as $billData) {
                    $billData["attachment_id"] = $attId;
                    $lastId = M("VcrBill")->add($billData);
                    if ($lastId) {
                        $items = $billData["items"];
                        $items["parent_id"] = $lastId;
                        if (M("VcrBillDetail")->add($items) === false) {
                            E("保存子表数据失败！");
                        }
                    } else {
                        E("保存对账单失败！");
                    }
                }
                $this->commit();
            } catch (Exception $ex) {
                $this->rollback();
                return buildMessage("导入失败，失败原因：" . $ex->getMessage());
            }
            unset($currentSheet);
            unset($PHPExcel);
            unset($PHPReader);
            $result = buildMessage(sprintf("导入成功，导入%d条记录（工资部分已经合并为一条）", $total));
            $result["attachment_id"] = $attId; //返回夹带attachid;
            return $result;
        } catch (Exception $e) {
            return buildMessage($e->getMessage(), 1);
        }
    }

    private function getFieldColumns($currentSheet){
        $allRow = $currentSheet->getHighestRow();
        $allColumn = getExcelColumnIndex($currentSheet->getHighestColumn());
        $columns = array();
        for ($rowIndex = 1; $rowIndex <= $allRow; $rowIndex++) {
            for ($ascii = ord("A"); $ascii <= $allColumn; $ascii++) {
                $col = getExcelColumnChar($ascii);
                $value = $currentSheet->getCell($col . $rowIndex)->getValue();
                //交易日期（时间）
                if (str_exists($value, BANK_IMPORT_COLUMNS["bc_datetime"])) {
                    $columns["datetime"]["row"] = $rowIndex;
                    $columns["datetime"]["col"] = $col;
                }
                //借方
                if (str_exists($value, BANK_IMPORT_COLUMNS["bc_debit"])) {
                    $columns["debit_amount"]["row"] = $rowIndex;
                    $columns["debit_amount"]["col"] = $col;
                }
                //贷方
                if (str_exists($value, BANK_IMPORT_COLUMNS["bc_credit"])) {
                    $columns["credit_amount"]["row"] = $rowIndex;
                    $columns["credit_amount"]["col"] = $col;
                }
                //摘要
                if (str_exists($value, BANK_IMPORT_COLUMNS["bc_summary"])) {
                    $columns["summary"]["row"] = $rowIndex;
                    $columns["summary"]["col"] = $col;
                }
                //备注
                if (str_exists($value, BANK_IMPORT_COLUMNS["bc_memo"])) {
                    $columns["memo"]["row"] = $rowIndex;
                    $columns["memo"]["col"] = $col;
                }
                //对方户名
                if (str_exists($value, BANK_IMPORT_COLUMNS["bc_side"])) {
                    $columns["other_side"]["row"] = $rowIndex;
                    $columns["other_side"]["col"] = $col;
                }
            }
            //先判断是否有备注或摘要字段，然后判断是否存在其他4个字段，再判断是否在同一行。
            $hasMemo = (isset($columns["summary"]) || isset($columns["memo"]));
            $findAll = isset($columns["datetime"]) && isset($columns["debit_amount"]) && isset($columns["credit_amount"]) && $hasMemo && isset($columns["other_side"]);
            if ($findAll) {
                $row_index = $columns["datetime"]["row"];
                foreach ($columns as $column) {
                    if ($row_index != $column["row"]) {
                        return false;
                    }
                }
                return $columns;
            }
        }
        return false;
    }

    private function has_bank_keyword($text){
        return has_string($text, array("银行", "分行", "支行", "商行"));
    }

}
