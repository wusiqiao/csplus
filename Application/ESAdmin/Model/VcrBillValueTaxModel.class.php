<?php

namespace ESAdmin\Model;

class VcrBillValueTaxModel extends VcrBillModel {

    /**
     * 自开（销项）票据处理
     * @param type $customer_data 客户资料
     * @param array $condition  过滤基本条件
     */
    public function processOutPutBill($company_data, $accounting_section) {
        $error_message = [];
        $outPutBillDatas = $this->getOutPutBillData($company_data,$accounting_section);
        $branch_id = $company_data["id"];
        $result['success_count'] = 0;
        $result['total_count'] = count($outPutBillDatas);
        $result['error_message'] = [];
        if ($outPutBillDatas) {
            //获取是否有自开票所需科目，如无则返回错误
            $subject = $this->getOutPutBillSubject($branch_id,$company_data);
            if($subject['error_message']){
                $result['error_message'] = $subject['error_message'];
                return $result;
            }
            $main_income_subject = $subject['main_income_subject'];//收入科目
            $tax_subject = $subject['tax_subject'];//税金科目
            $max_bill_no = $this->getMaxBillNoByUserBranch();
            foreach ($outPutBillDatas as $bill) {
                if ($company_data["ent_type_id"] != ENTERPRISE_TYPE_JRBX) { //非金融（除银行业外）特指小贷公司，是应收利息，其他都是应收账款
                    $receivable_subject_name = "应收账款-".$bill["name"];
                    $receivable_subject = getReceivableSubject($branch_id, $bill["name"]); //应收账款
                }else{
                    $receivable_subject_name = "应收利息-".$bill["name"];
                    $receivable_subject = getInterestSubject($branch_id, $bill["name"]); //应收利息
                }
                $error_message["receivable_subject"] = getVoucherSubjectError($receivable_subject, $receivable_subject_name);
                if (empty($receivable_subject)){
                    $result['error_message'] = $error_message['receivable_subject'];
                    return $result;
                }
                $draf_data["branch_id"] = $branch_id;
                $draf_data["accounting_section"] = $bill["accounting_section"];
                $draf_data["bill_date"] = time();
                $draf_data["bill_no"] = $max_bill_no;
                $draf_data["creater"] = $company_data["creator"];
                $draf_data["number"] = D("VcrVoucherDraf")->getMaxNumber($branch_id);
                $last_id = M("VcrVoucherDraf")->add($draf_data);
                if ($last_id) {
                    $result['success_count'] ++ ;
                    $data_list = $this->getOutPutBillDataList($receivable_subject,$bill,$last_id,$error_message,$main_income_subject,$tax_subject);
                    if (!M("VcrVoucherDrafDetail")->addAll($data_list)) {
                        E("添加子表错误");
                    }
                    D("VcrVoucherDraf")->addDetailChangeLog(["id"=>$last_id],$data_list);
                    //更新关系表
                    $this->updateRelation($last_id, explode(",", $bill["id"]));
                    unset($data_list);
                    $max_bill_no = $this->incBillNo($max_bill_no);
                    if ($error_message){
                        $result['error_message'] = array_merge($result['error_message'],array_filter(array_values($error_message)));
                        M("VcrBill")->where(array("id"=>$bill["id"]))->setField("error",  implode(",",array_values($error_message)));
                    }
                }
            }
        }
        /*if (array_filter(array_values($error_message))) {
            return implode("<br>", array_values($error_message));
        }*/
        $result['error_message'] = implode("<br>", array_values($result['error_message']));
        return $result;
    }

    //获取外开-应付账款部分(采购类发票）所需的科目
    public function getProcessPurchaseSubject($branch_id,$company_data){
        $result['tax_subject'] = getTaxSubject($branch_id, $company_data["ent_scale"], false); //应交税金-进项
        $subject_name = $company_data["ent_scale"] == ENTERPRISE_SCALE_SMALL?"应交税金-应交增值税":"应交增值税-进项税额";
        $error_message["tax_subject"] = getVoucherSubjectError($result['tax_subject'], $subject_name);
        if (empty($result['tax_subject'])) {
            $result['error_message'] = $error_message["tax_subject"];
            return $result;
        }
        $result['cash_subject'] = getCashSubject($branch_id);
        $error_message["cash_subject"] = getVoucherSubjectError($result['cash_subject'], "现金");
        if (empty($result['cash_subject'])) {
            $result['error_message'] = $error_message["cash_subject"];
            return $result;
        }
        $result['assets_subject'] = getVoucherSubject($branch_id, "固定资产");
        return $result;
    }

    //外开-应付账款部分(采购类发票）
    public function processPurchaseBill($company_data, $accounting_section) {
        $error_message = array();
        $branch_id = $company_data["id"];
        $billDatas = $this->getProcessPurchaseBillData($branch_id,$accounting_section);
        $result['success_count'] = 0;
        $result['total_count'] = count($billDatas);
        $result['error_message'] = [];
        if ($billDatas) {
            //获取所需科目
            $subject = $this->getProcessPurchaseSubject($branch_id,$company_data);
            if($subject['error_message']){
                $result['error_message'] = $subject['error_message'];
                return $result;
            }
            $max_bill_no = $this->getMaxBillNoByUserBranch();
            foreach ($billDatas as $bill) {
                //现金已付,否则银行存款
                if ($bill["cashpayed"]) {
                    $payable_subject = $subject['cash_subject'];
                } else {
                    $payable_subject = getPayableSubject($branch_id, $bill["name"]); //应付账款优先，如果找不到，就现金科目
                    $error_message["payable_subject"] = getVoucherSubjectError($payable_subject, "应付账款-".$bill["name"]);
                    if (empty($payable_subject)) {
                        $result['error_message'] = $error_message["payable_subject"];
                        return $result;
                    }
                }
                $details = $this->query("select * from vcr_bill_detail where parent_id in (" . $bill["id"] . ")");
                $total_amount = 0;
                $total_tax_amount = 0;
                $startRow = 1;
                foreach ($details as $key => $detail) {
                    //借：原材料-XX材料
                    //    应交税费-应交增值税（进项税）
                    if ($detail["price"] >= 2000 && $detail["quantity"] <= 5) {//价值>2000的,数量小于5的，算固定资产
                        $debit_subject = $subject['assets_subject'];
                        $debit_subject_name = "固定资产";
                    } else {
                        $debit_subject = getVoucherSubject($branch_id, $detail["goods_name"]);
                        $debit_subject_name = $detail["goods_name"];
                    }
                    $error_message["debit_subject"] = getVoucherSubjectError($debit_subject, $debit_subject_name);
                    if (empty($debit_subject)) {
                        $result['error_message'] = $error_message["debit_subject"];
                        return $result;
                    }
                    $data_list[] = array(
                        "row_no" => $startRow,
                        "subject_name" => $debit_subject["subject"]["name"],
                        "subject_id" => $debit_subject["subject"]["id"],
                        "amount" => $detail["amount"],
                        "summary" => $bill["memo"],
                        "direction" => DIRECTION_DEBIT,
                        "parent_id" => 0,
                        "error"=>$error_message["debit_subject"]
                    );
                    $total_amount += $detail["amount"];
                    $total_tax_amount += $detail["tax_amount"];
                    $startRow++;
                }
                //如果是专票，借进项税
                if ($bill["tax_type"] == TAX_TYPE_VTX) {
                    $data_list[] = array(
                        "row_no" => $startRow,
                        "subject_name" => $subject['tax_subject']["subject"]["name"],
                        "subject_id" => $subject['tax_subject']["subject"]["id"],
                        "amount" => $total_tax_amount,
                        "summary" => "",
                        "direction" => DIRECTION_DEBIT,
                        "parent_id" => 0,
                        "error"=>$error_message["tax_subject"]
                    );
                    $startRow++;
                }
                //贷：应付账款-XX单位 或 现金
                $data_list[] = array(
                    "row_no" => $startRow,
                    "subject_name" => $payable_subject["subject"]["name"],
                    "subject_id" => $payable_subject["subject"]["id"],
                    "amount" => $total_amount + $total_tax_amount,
                    "summary" => "",
                    "direction" => DIRECTION_CREDIT,
                    "parent_id" => 0,
                    "error"=>$error_message["payable_subject"]
                );

                $draf_data["branch_id"] = $branch_id;
                $draf_data["accounting_section"] = $bill["accounting_section"];
                $draf_data["bill_date"] = time();
                $draf_data["bill_no"] = $max_bill_no;
                $draf_data["creater"] = $company_data["creator"];
                $draf_id = M("VcrVoucherDraf")->add($draf_data);
                if ($draf_id){
                    $result['success_count'] ++ ;
                    foreach ($data_list as $key=>$data){
                        $data_list[$key]["parent_id"] = $draf_id;
                    }
                    if (!M("VcrVoucherDrafDetail")->addAll($data_list)) {
                        E("生成草稿子表错误");
                    }
                    D("VcrVoucherDraf")->addDetailChangeLog(["id"=>$draf_id],$data_list);
                    $this->updateRelation($draf_id, array($bill["id"]));
                    $max_bill_no = $this->incBillNo($max_bill_no);
                }else{
                    E("生成草稿错误");
                }
                unset($data_list);
                if ($error_message){
                    $result['error_message'] = array_merge($result['error_message'],array_filter(array_values($error_message)));
                    M("VcrBill")->where(array("id"=>$bill["id"]))->setField("error",  implode(",",array_values($error_message)));
                }
            }
        }
        /*if (array_filter(array_values($error_message))) {
            return implode("<br>", array_values($error_message));
        }*/
        $result['error_message'] = implode("<br>", $result['error_message']);
        return $result;
    }

    //外开-费用部分(费用发票）
    //贷现金或应付账款，如果是通行费或增值税票，且一般纳税人，计算增值税-进项
    //合并规则：找到二级科目，相同二级科目合并，找不到不合并。
    public function processFeeBill($company_data, $accounting_section) {
        $error_message = array();
        $branch_id = $company_data["id"];
        $where = sprintf("where ISNULL(voucher_id) and bill_flag=%d and is_fee=1 and branch_id=%d  and accounting_section='%s'", FLAG_BILL_TAX_PAY, $branch_id, $accounting_section);
        $max_bill_no = $this->getMaxBillNoByUserBranch();
        $sql = "select * from vcr_bill $where order by name";
        $billDatas = $this->query($sql);
        $result['success_count'] = 0;
        $result['total_count'] = count($billDatas);
        $result['error_message'] = [];
        if ($billDatas) {
            $tax_subject = getTaxSubject($branch_id, $company_data["ent_scale"], false); //应交税金-进项
            $subject_name = $company_data["ent_scale"] == ENTERPRISE_SCALE_SMALL?"应交税金-应交增值税":"应交增值税-进项税额";
            $error_message["tax_subject"] = getVoucherSubjectError($tax_subject, $subject_name);
            if (empty($tax_subject)) {
                $result['error_message'] = $error_message["tax_subject"];
                return $result;
            }
            $cash_subject = getCashSubject($branch_id);
            $error_message["cash_subject"] = getVoucherSubjectError($cash_subject, "现金");
            if (empty($cash_subject)) {
                $result['error_message'] = $error_message["cash_subject"];
                return $result;
            }
            //是否合并费用同类科目
            $merge_fee =  M("VcrConfig")->where("branch_id=$branch_id")->getField("vcr_merge_fee");
            foreach ($billDatas as $bill) {
                //现金已付,否则银行存款
                if ($bill["cashpayed"]) {
                    $payable_subject = $cash_subject;
                } else {
                    $payable_subject = getPayableSubject($branch_id, $bill["name"]); //应付账款优先，如果找不到，就现金科目
                    $error_message["payable_subject"] = getVoucherSubjectError($payable_subject, "应付账款-".$bill["name"]);
                    if (empty($payable_subject)) {
                        $result['error_message'] = $error_message["payable_subject"];
                        return $result;
                    }
                }
                $details = $this->query("select * from vcr_bill_detail where parent_id=" . $bill["id"]);
                $startRow = 1;
                $fee_total_amount = 0;
                $vat_total_amount = 0;
                $subjct_arrays = array();
                foreach ($details as $key => $detail) {
                    //一般纳税人，并且发票是专票
                    $is_generalVAT = $company_data["ent_scale"] == ENTERPRISE_SCALE_GENERAL && ($detail["fee_type"] != 0); //其他类费用不计算
                    if ($is_generalVAT) { //一般纳税人增值税发票，税额单独放增值税科目，否则金额+税额放在借方科目
                        $fee_amount = floatval($detail["amount"]);
                        $vat_total_amount += floatval($detail["tax_amount"]);
                    } else {
                        $fee_amount = floatval($detail["amount"]) + floatval($detail["tax_amount"]); //一般纳税人，并且发票是专票，税额单独放增值税科目
                        $vat_total_amount = 0;
                    }
                    $fee_total_amount += $fee_amount;
                    //查找费用二级科目
                    $fee_subject_name = getFeeSubjectNameByKeyword($detail["goods_name"]); //查找对应费用二级科目名称
                    $fee_subject = getFeeSubject($branch_id, $bill["fee_department"], $fee_subject_name); //根据费用部门，查找二级科目
                    $error_message["fee_subject"] = getVoucherSubjectError($fee_subject, $detail["goods_name"]);
                    if (empty($fee_subject)) {
                        $result['error_message'] = $error_message["fee_subject"];
                        return $result;
                    }
                    $debit_row = [
                        "row_no" => $startRow,
                        "subject_name" => $fee_subject["subject"]["name"],
                        "subject_id" => $fee_subject["subject"]["id"],
                        "amount" => $fee_amount,
                        "summary" => $bill["memo"],
                        "direction" => DIRECTION_DEBIT,
                        "parent_id" => 0,
                        "error" => $error_message["fee_subject"]
                    ];
                    //合并规则：找到二级科目，相同二级科目合并，找不到不合并。
                    if ($merge_fee && $fee_subject["match"]){
                        if ($subjct_arrays[$fee_subject_name]) {
                            $subjct_arrays[$fee_subject_name]["amount"] += $fee_amount;
                        } else {
                            $subjct_arrays[$fee_subject_name] = $debit_row;
                            $startRow++;
                        }
                    }else{
                        $subjct_arrays[$fee_subject_name] = $debit_row;
                        $startRow++;
                    }
                }
                $data_list = array_values($subjct_arrays);
                //如果有税，而且是一般纳税人，且是增值税专用发票，借进项税
                //if ($bill["tax_type"] == TAX_TYPE_VTX && $company_data["ent_scale"] == ENTERPRISE_SCALE_GENERAL) {
                if ($vat_total_amount > 0) {
                    $data_list[] = array(
                        "row_no" => $startRow,
                        "subject_name" =>  $tax_subject["subject"]["name"],
                        "subject_id" => $tax_subject["subject"]["id"],
                        "amount" => $vat_total_amount,
                        "summary" => '',
                        "direction" => DIRECTION_DEBIT,
                        "parent_id" => 0,
                        "error"=>$error_message["tax_subject"]
                    );
                    $startRow++;
                }
                //贷： 现金或应付款
                $data_list[] = array(
                    "row_no" => $startRow,
                    "subject_name" => $payable_subject["subject"]["name"],
                    "subject_id" => $payable_subject["subject"]["id"],
                    "amount" => $fee_total_amount + $vat_total_amount,
                    "summary" => '',
                    "direction" => DIRECTION_CREDIT,
                    "parent_id" => 0,
                    "error"=>""
                );
                $draf_data["branch_id"] = $branch_id;
                $draf_data["accounting_section"] = $bill["accounting_section"];
                $draf_data["bill_date"] = time();
                $draf_data["bill_no"] = $max_bill_no;
                $draf_data["creater"] = $company_data["creator"];
                $draf_id = M("VcrVoucherDraf")->add($draf_data);
                if ($draf_id){
                    $result['success_count'] ++;
                    foreach ($data_list as $key=>$data){
                        $data_list[$key]["parent_id"] = $draf_id;
                    }
                    if (!M("VcrVoucherDrafDetail")->addAll($data_list)) {
                        E("生成草稿子表错误");
                    }
                    D("VcrVoucherDraf")->addDetailChangeLog(["id"=>$draf_id],$data_list);
                    $this->updateRelation($draf_id, array($bill["id"]));
                    $max_bill_no = $this->incBillNo($max_bill_no);
                }else{
                    E("生成草稿错误");
                }
                unset($data_list);
                if ($error_message){
                    $result['error_message'] = array_merge($result['error_message'],array_filter(array_values($error_message)));
                    M("VcrBill")->where(array("id"=>$bill["id"]))->setField("error",  implode(",",array_values($error_message)));
                }
            }
        }
        /*if (array_filter(array_values($error_message))) {
            return implode("<br>", array_values($error_message));
        }*/
        $result['error_message'] = implode("<br>", $result['error_message']);
        return $result;
    }

    protected function insert_detail($id, $data) {
        //外来的发票或费用才重新计算税
        if ($data["bill_flag"] == FLAG_BILL_TAX_PAY) {
            $road_brige_taxs = array();
            $user_session = session(USER_SESSION_KEY);
            //一般纳税人过桥过路费可以抵扣
            if ($user_session->entScale == ENTERPRISE_SCALE_GENERAL) {
                $road_brige_taxs = array(
                    RATE_EXPRESSWAY =>HIGHWAY_BRIDGE_TAXRATES[RATE_EXPRESSWAY],
                    RATE_ROAD_BRIDGE =>HIGHWAY_BRIDGE_TAXRATES[RATE_ROAD_BRIDGE]
                );
            }
            $fee_types = array();
            /*处理费用类型 高速公路通行费可抵扣进项税额=高速公路通行费发票上注明的金额÷（1+3%）×3%
              一级、二级公路通行费可抵扣进项税额=一级、二级公路通行费发票上注明的金额÷（1+5%）×5%
            */
            foreach ($_POST as $k=>$v){
                if (stripos($k, "fee_type_") === 0){
                    $fee_types[] = $v;
                }
            }
        }
        $goods_names = I("post.goods_name");
        $quantitys = I("post.quantity");
        $prices = I("post.price");
        $units = I("post.unit");
        $amounts = I("post.amount");
        $tax_rates = I("post.tax_rate");
        $tax_amounts = I("post.tax_amount");
        $total_amounts = I("post.total_amount"); //以价税合计为准
        if (!is_array($goods_names) || !is_array($quantitys) || !is_array($prices) || !is_array($amounts) || !is_array($tax_amounts)) {
            $this->error = "数据格式错误";
            E($this->error);
        }
        $total_amount = 0;
        $total_tax = 0;
        foreach ($goods_names as $key => $value) {
            if (empty($value) || !isset($amounts[$key])){
               $this->error = "明细数据不能为空";
               E($this->error);
               break;
            }
            //外来的发票或费用才重新计算税
            if (($data["bill_flag"] == FLAG_BILL_TAX_PAY) && $data["is_fee"]) {
                //是一般纳税人的话，$road_brige_taxs有赋值
                $fee_type = $fee_types[$key];
                if ($fee_type == RATE_EXPRESSWAY || $fee_type == RATE_ROAD_BRIDGE) {
                    if ($road_brige_taxs) {
                        $tax_rates[$key] = $road_brige_taxs[$fee_type]; //税率
                        $tax_amounts[$key] = $total_amounts[$key] / (1 + $tax_rates[$key] / 100) * $tax_rates[$key] / 100; //税额
                        $amounts[$key] = $total_amounts[$key] - $tax_amounts[$key];
                    }
                } else {
                    if ($fee_type == RATE_ZEROTAX) {
                        $tax_rates[$key] = 0; //税率
                        $tax_amounts[$key] = 0; //税额
                        $amounts[$key] = $total_amounts[$key] - $tax_amounts[$key];
                    }
                }
            }else{
                //自开票必须有税率
               if ($data["bill_flag"] == FLAG_BILL_TAX_INCOME) {
                   if ($tax_rates[$key] == 0 || $tax_amounts[$key] == 0) {
                       $this->error = "自开票税率/税额不能为0";
                       E($this->error);
                       break;
                   }
               }
            }
            $datas[] = array(
                "goods_name" => $value, 
                "quantity" => $quantitys[$key],
                "price" => $prices[$key], 
                "unit"=>$units[$key],
                "amount" => $amounts[$key], 
                "tax_rate" => $tax_rates[$key], 
                "tax_amount" => $tax_amounts[$key],
                "fee_type"=>($data["bill_flag"] == FLAG_BILL_TAX_PAY)?$fee_types[$key]:0,
                "parent_id" => $id);
            $total_amount = $total_amount + floatval($amounts[$key]);
            $total_tax = $total_tax + floatval($tax_amounts[$key]);
        }
        $total_sum = $total_amount + $total_tax;
        M("VcrBillDetail")->addAll($datas);
        $this->execute("update vcr_bill set total_amount=$total_amount,total_tax=$total_tax,total_sum=$total_sum where id=$id");
    }
    private function getCellVal($currentSheet, $col, $rowIndex){
        if(empty($col)){
            return false;
        }

        return trim($currentSheet->getCell($col . $rowIndex)->getValue());
    }
    //导入自开发票
    public function importInvoice($excel_file, $client_file){
        $user_session = session(USER_SESSION_KEY);
        $filePath = realpath("./") . $excel_file;
        //检查文件重复
        $hash = md5_file($filePath);
    //        if ($hash && M("VcrBillAttachment")->where(array("hash" => $hash))->count() > 0) {
//            return buildMessage("文件已经导入过，无需重复导入！");
//        }

        $templates = D('VcrBillTemplates')->where(['id' => I('template_id')])->find();
        if(empty($templates)){
            return buildMessage("模板丢失!, 请确认模板是否存在!", 1);
        }

        Vendor('PHPExcel18.PHPExcel');
        $PHPReader = \PHPExcel_IOFactory::createReaderForFile($filePath);
        try {
            $PHPExcel = $PHPReader->load($filePath);        //建立excel对象
            $currentSheet = $PHPExcel->getSheet(0);        //**读取excel文件中的指定工作表*/
            $field_columns = $this->getFieldColumns($templates); //获取科目字段的对应的列
            if (!$field_columns) {
                //unlink($filePath);//删除文件，防止一直上传
                return buildMessage("文件格式暂时无法识别！", 1);
            }

            $billDataList = array();
            $import_count = 0;
            $rowIndex = $field_columns["invo_no"]["row"];
            $accounting_section = [];
            while($rowIndex <= $currentSheet->getHighestRow()) {        //循环读取每个单元格的内容。注意行从1开始，列从A开始
                $invo_no = $this->getCellVal($currentSheet, $field_columns["invo_no"]["col"], $rowIndex);
                $buyer = $this->getCellVal($currentSheet, $field_columns["buyer"]["col"], $rowIndex);
                $invo_date = getFormateExcelDate($this->getCellVal($currentSheet, $field_columns["invo_date"]["col"], $rowIndex));
                //发票号码已存在，跳过
                $condition["branch_id"] = $user_session->currBranchId;
                $condition["source_no"] = $invo_no;
                $condition["bill_flag"] = FLAG_BILL_TAX_INCOME;
                if (!$invo_no || !$buyer || $this->where($condition)->count() > 0){
                    $rowIndex++; //跳下一行
                    continue;
                }

                //发票号码非空，且是数字和字母组成
                if ($invo_no && preg_match("/[a-zA-Z0-9-]+/i", $invo_no) && $buyer) {
                    $billData["bill_date"] = time();
                    $billData["bill_no"] = $this->getMaxBillNoByUserBranch();
                    $billData["bill_flag"] = FLAG_BILL_TAX_INCOME;
                    $billData["branch_id"] = $user_session->currBranchId;
                    $billData["name"] = $buyer;
                    $billData["user_id"] = $user_session->userId;
                    $billData["accounting_section"] = date("Y/m", strtotime($invo_date));
                    if(!in_array($billData["accounting_section"], $accounting_section)){
                        array_push($accounting_section, $billData["accounting_section"]);
                    }

                    $billData["creator"] = $user_session->userName;
                    $billData["source_no"] = $invo_no;
                    $billData["source_date"] = strtotime($invo_date);
                    $billData["source_flag"] = FLAG_SOURCE_INCOME;
                    //$billData["tax_type"] = 0;//自开的不需要关心发票类型
                    //子表部分
                    $total_amount = 0;
                    $total_tax = 0;
                    $import_count++;
                    $details = array();
                    $last_invo = $invo_no;
                    while (($invo_no == $last_invo || $last_invo == "") && ($rowIndex <= $currentSheet->getHighestRow())) {
                        $goods_name = $this->getCellVal($currentSheet, $field_columns["goods_name"]["col"], $rowIndex);
                        $unit = $this->getCellVal($currentSheet, $field_columns["unit"]["col"],  $rowIndex);
                        $quantity = str_replace(",", "",  $this->getCellVal($currentSheet, $field_columns["quantity"]["col"], $rowIndex));
                        $price = str_replace(",", "", $this->getCellVal($currentSheet, $field_columns["price"]["col"], $rowIndex));
                        $amount = str_replace(",", "", $this->getCellVal($currentSheet, $field_columns["amount"]["col"], $rowIndex));
                        $tax_rate = str_replace(",", "", $this->getCellVal($currentSheet, $field_columns["tax_rate"]["col"], $rowIndex));
                        $tax_amount = str_replace(",", "", $this->getCellVal($currentSheet, $field_columns["tax_amount"]["col"], $rowIndex));
                        $detail["parent_id"] = 0;
                        $detail["goods_name"] = $goods_name;
                        $detail["unit"] = $unit;
                        $detail["quantity"] = $quantity;
                        $detail["price"] = $price;
                        $detail["amount"] = $amount;
                        $detail["tax_rate"] = floatval(str_replace("%", "", $tax_rate));
                        $detail["tax_amount"] = $tax_amount;
                        $total_amount = $total_amount + floatval($amount);
                        $total_tax = $total_tax + floatval($tax_amount);
                        $details[] = $detail;
                        $last_invo = $this->getCellVal($currentSheet, $field_columns["invo_no"]["col"], $rowIndex + 1);
                        //如果是一张发票多个商品，有个小计项，税率列肯定是空，表示这张发票结束。如果是只有一个商品，没有小计项
                        if ($this->getCellVal($currentSheet, $field_columns["tax_rate"]["col"], $rowIndex + 1) == "") {
                            break;
                        }
                        //如果是只有一个商品，没有小计项，RowIndex必须不动
                        if ($last_invo != "" && $invo_no != $last_invo){
                            break;
                        }
                        $rowIndex++;
                    }
                    $billData["items"] = $details;
                    $billData["total_amount"] = $total_amount;
                    $billData["total_tax"] = $total_tax;
                    $billData["total_sum"] = $total_amount + $total_tax;
                    array_unshift($billDataList, $billData);
                }
                $rowIndex++; //直接跳下一行
            }
            if ($import_count > 0) {
                $this->startTrans();
                try {
                    $attachData['bill_flag'] = FLAG_BILL_TAX_INCOME;
                    $attachData['accounting_section'] = implode(',', $accounting_section);
                    $attachData["url"] = $excel_file;
                    $attachData["hash"] = $hash;
                    $attachData["file_name"] = $client_file;
                    $attachData["branch_id"] =  $user_session->currBranchId;
                    $attId = M("VcrBillAttachment")->add($attachData);
                    $message["attachment_id"] = $attId;
                    foreach ($billDataList as $billData) {
                        $billData["attachment_id"] = $attId;
                        $lastId = M("VcrBill")->add($billData);
                        if ($lastId) {
                            $items = $billData["items"];
                            foreach ($items as $key => $item) {
                                $items[$key]["parent_id"] = $lastId;
                            }
                            if (M("VcrBillDetail")->addAll($items) === false) {
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
            }
            unset($currentSheet);
            unset($PHPExcel);
            unset($PHPReader);
            $msg = ($import_count == 0)?"没有新数据导入!":"导入". $import_count ."条数据!";
            $result =  buildMessage($msg);
            $result["attachment_id"] = $attId; //返回夹带attachid;
            return $result;
        } catch (Exception $e) {
            return buildMessage($e->getMessage(), 1);
        }
    }

    private function getFieldColumns($templates) {
        $rowIndex = $templates['start'];
        $columns = [];
        if($templates['invo_no']){
            $columns["invo_no"]["row"] = $rowIndex;
            $columns["invo_no"]["col"] = $templates['invo_no'];
        }
        if ($templates['buyer']) {
            $columns["buyer"]["row"] = $rowIndex;
            $columns["buyer"]["col"] = $templates['buyer'];
        }
        if ($templates['invo_date']) {
            $columns["invo_date"]["row"] = $rowIndex;
            $columns["invo_date"]["col"] = $templates['invo_date'];
        }
        if ($templates['goods_name']) {
            $columns["goods_name"]["row"] = $rowIndex;
            $columns["goods_name"]["col"] = $templates['goods_name'];
        }
        if ($templates['unit']) {
            $columns["unit"]["row"] = $rowIndex;
            $columns["unit"]["col"] = $templates['unit'];
        }
        if ($templates['quantity']) {
            $columns["quantity"]["row"] = $rowIndex;
            $columns["quantity"]["col"] = $templates['quantity'];
        }
        if ($templates['price']) {
            $columns["price"]["row"] = $rowIndex;
            $columns["price"]["col"] = $templates['price'];
        }
        if ($templates['amount']) {
            $columns["amount"]["row"] = $rowIndex;
            $columns["amount"]["col"] = $templates['amount'];
        }
        if ($templates['tax_rate']) {
            $columns["tax_rate"]["row"] = $rowIndex;
            $columns["tax_rate"]["col"] = $templates['tax_rate'];
        }
        if ($templates['tax_amount']) {
            $columns["tax_amount"]["row"] = $rowIndex;
            $columns["tax_amount"]["col"] = $templates['tax_amount'];
        }

        if ($columns["invo_no"] && $columns["buyer"] && $columns["goods_name"]) {
            if ($columns["invo_no"]["row"] == $columns["buyer"]["row"] && $columns["goods_name"]["row"] == $columns["buyer"]["row"]) {
                return $columns;
            }
        }

        return false;
    }

    public function removeAttachment($branch_id, $attachment_id){
        $condition["branch_id"] = $branch_id;
        $condition["id"] = $attachment_id;
        $this->startTrans();
        try{
            if (M("VcrBillAttachment")->where($condition)->delete()) {
                $bill_ids = M("VcrBill")->where("attachment_id=$attachment_id")->getField("id", true);
                //凭证id
                $draf_ids = M("VcrBill")->where("attachment_id=$attachment_id")->getField("voucher_id", true);
                $draf_ids = array_unique(array_filter($draf_ids));
                if($draf_ids){
                    //凭证详情借贷id
                    $draf_detail_ids = M("VcrVoucherDrafDetail")->where(["parent_id"=>array("in",$draf_ids)])->getField("id",true);
                    M("VcrVoucherDraf")->where(array("id"=>array("in", $draf_ids)))->delete();
                    if($draf_detail_ids){
                        M("VcrVoucherDrafDetail")->where(array("parent_id"=>array("in", $draf_detail_ids)))->delete();
                    }
                }
                M("VcrBill")->where("attachment_id=$attachment_id")->delete();
                M("VcrBillDetail")->where(array("parent_id"=>array("in", $bill_ids)))->delete();
            }
            $this->commit();
        }catch (\Exception $ex){
            $this->rollback();
            return false;
        }
        return true;
    }

    public function getOutPutBillData($company_data,$accounting_section,$id = ""){
        $branch_id = $company_data["id"];
        if(!$id){
            $id = I("post.id"); //如果传入id,表示多选，否则是全部
        }
        if (empty($id)) {
            $where = sprintf("where ISNULL(voucher_id) and bill_flag=%d and branch_id=%d and accounting_section='%s'", FLAG_BILL_TAX_INCOME, $branch_id, $accounting_section);
        } else {
            $where = sprintf("where ISNULL(voucher_id) and bill_flag=%d and id in (%s) and branch_id=%d", FLAG_BILL_TAX_INCOME,  implode(",", $id), $branch_id);
        }
        //是否合并应收
        $merge_receivable = M("VcrConfig")->where("branch_id=$branch_id")->getField("vcr_merge_receivable");
        if ($merge_receivable){
            $sql = "select sum(total_amount) as total_amount,sum(total_tax) as total_tax,sum(total_sum) as total_sum,name,accounting_section, GROUP_CONCAT(id) as id,memo from vcr_bill $where group by name,accounting_section";
        }else{
            $sql = "select * from vcr_bill $where";
        }
        $outPutBillDatas = $this->query($sql);
        return $outPutBillDatas;
    }

    //自开发票获取借贷详情
    public function getOutPutBillDataList($receivable_subject,$bill,$last_id,$error_message,$main_income_subject,$tax_subject){
        $data_list = [];
        $total_amount = $bill["total_amount"];
        $total_tax_amount = $bill["total_tax"];
        //借：应收账款
        $data_list[] = array(
            "row_no"=>1,
            "subject_name" => $receivable_subject["subject"]["name"],
            "subject_id" => $receivable_subject["subject"]["id"],
            "amount" => ($total_amount  + $total_tax_amount),
            "summary" => $bill['memo'],
            "direction" => DIRECTION_DEBIT,
            "parent_id" => $last_id,
            "error"=>$error_message["receivable_subject"]
        );
        //贷：主营业务收入
        $data_list[] = array(
            "row_no"=>2,
            "subject_name" => $main_income_subject["subject"]["name"],
            "subject_id" => $main_income_subject["subject"]["id"],
            "amount" => $total_amount,
            "summary" => "",
            "direction" => DIRECTION_CREDIT,
            "parent_id" => $last_id,
            "error"=>$error_message["main_income_subject"]
        );
        //贷：应交税金
        $data_list[] = array(
            "row_no"=>3,
            "subject_name" => $tax_subject["subject"]["name"],
            "subject_id" => $tax_subject["subject"]["id"],
            "amount" => $total_tax_amount,
            "summary" => "",
            "direction" => DIRECTION_CREDIT,
            "parent_id" => $last_id,
            "error"=>$error_message["tax_subject"]
        );
        return $data_list;
    }

    //获取是否有自开票所需科目，如无则返回错误
    public function getOutPutBillSubject($branch_id,$company_data){
        $result = [];
        $tax_subject = getTaxSubject($branch_id, $company_data["ent_scale"]); //应交税金
        $subject_name = $company_data["ent_scale"] == ENTERPRISE_SCALE_SMALL?"应交税金-应交增值税":"应交增值税-销项税额";
        $error_message["tax_subject"] = getVoucherSubjectError($tax_subject, $subject_name);
        if (empty($tax_subject)) {
            $result['error_message'] = $error_message['tax_subject'];
            return $result;
        }
        switch ($company_data["ent_type_id"]) {
            case ENTERPRISE_TYPE_JRBX:
                $income_subject_name = "利息收入";
                $main_income_subject = getVoucherSubject($branch_id, $income_subject_name);
                break;
            case ENTERPRISE_TYPE_CKY:
            default:
                $income_subject_name = "主营业务收入";
                $main_income_subject = getVoucherSubject($branch_id, $income_subject_name);
                break;
        }
        $error_message["main_income_subject"] = getVoucherSubjectError($main_income_subject, $income_subject_name);
        if (empty($main_income_subject)){
            $result['error_message'] = $error_message['main_income_subject'];
            return $result;
        }
        $result['main_income_subject'] = $main_income_subject;
        $result['tax_subject'] = $tax_subject;
        return $result;
    }

    //获取外开-应付账款部分(采购类发票）单证数据（非费用）
    public function getProcessPurchaseBillData($branch_id, $accounting_section,$id = ""){
        if($id == ""){
            $id = I("post.id");
        }
        if (empty($id)) {
            $where = sprintf("where ISNULL(voucher_id) and bill_flag=%d and is_fee=0 and branch_id=%d and accounting_section='%s'", FLAG_BILL_TAX_PAY, $branch_id, $accounting_section);
        } else {
            $where = sprintf("where ISNULL(voucher_id) and bill_flag=%d and is_fee=0 and id in (%s) and branch_id=%d", FLAG_BILL_TAX_PAY, implode(",", $id), $branch_id);
        }
        //是否合并应付
        $merge_payable = M("VcrConfig")->where("branch_id=$branch_id")->getField("vcr_merge_payable");
        if ($merge_payable){
            $sql = "select sum(total_amount) as total_amount,sum(total_tax) as total_tax,sum(total_sum) as total_sum,name,accounting_section,memo,tax_type,cashpayed, GROUP_CONCAT(id) as id from vcr_bill $where group by name,accounting_section";
        }else{
            $sql = "select * from vcr_bill $where";
        }
        $billDatas = $this->query($sql);
        return $billDatas;
    }
}
