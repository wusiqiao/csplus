<?php

namespace ESAdmin\Model;

class VcrBillSalaryModel extends VcrBillModel {
    protected $_bill_flag = FLAG_BILL_SALARY;
    
    
    //发放：借：应付工资   10000  贷：其他应收款-社保费 500，其他应收款-公积金100，应交税费-个税1000 库存现金 8400
    //计提：借：管理费用-工资 1万   贷：应付工资 1万
    //支付社保和公积金：借：管理费用-社保费/公积金    其他应收款-社保款/公积金 贷：银行存款
    // “现金已付”-是
    //（1）、在此选择发放部门为管理部门、销售部门、生产部门、研发部门、施工部门现金发放凭证应该为【1】和【2】
    //分录【1】
    //借：管理费用-应发工资（对应管理部门） //销售费用-应发工资（对应销售部门）//生产成本-应发工资（对应生产部门）
                                     //管理费用-研发支出-应发工资（对应研发部门）//工程施工-应发工资（对应施工部门）
    //贷：应付职工薪酬-实发工资
    //其他应付款-社保/公积金/个人所得税
    //分录【2】
    //借：应付职工薪酬-实发工资
    //贷：库存现金
    //
    //“现金已付”—否，则生成以上分录【1】

    //待银行对账单体现发放时，分录为：借：应付职工薪酬-实发工资
    //贷：银行存款—xx银行
    public function processBill($company_data, $accounting_section) {
        $error_message = array();
        $result = [];
        $branch_id = $company_data["id"];
        $billDatas = $this->getBillData($branch_id,$accounting_section);
        $result['success_count'] = 0;
        $result['total_count'] = count($billDatas);
        $result['error_message'] = [];
        if ($billDatas) {
            $max_bill_no = $this->getMaxBillNoByUserBranch();
            $salary_subject = getSalarySubject($branch_id);
            $error_message["salary_subject"] = getVoucherSubjectError($salary_subject, "应付职工薪酬-工资");
            if (empty($salary_subject)){
                $result['error_message'] = $error_message["salary_subject"];
                return $result;
            }
            $insurance_subject = getInsuranceSubject($branch_id);
            $error_message["insurance_subject"] = getVoucherSubjectError($insurance_subject, "应付职工薪酬-医社保");
            if (empty($insurance_subject)){
                $result['error_message'] = $error_message["insurance_subject"];
                return $result;
            }
            $fund_subject = getFundSubject($branch_id);
            $error_message["fund_subject"] = getVoucherSubjectError($fund_subject, "应付职工薪酬-个人公积金");
            if (empty($fund_subject)){
                $result['error_message'] = $error_message["fund_subject"];
                return $result;
            }
            $tax_subject = getPersonTaxSubject($branch_id);
            $error_message["tax_subject"] = getVoucherSubjectError($tax_subject, "应交税金-个人所得税");
            if (empty($tax_subject)){
                $result['error_message'] = $error_message["tax_subject"];
                return $result;
            }
            $cash_subject = getCashSubject($branch_id);
            $error_message["cash_subject"] = getVoucherSubjectError($cash_subject, "现金");
            if (empty($cash_subject)){
                $result['error_message'] = $error_message["cash_subject"];
                return $result;
            }
            foreach ($billDatas as $bill) {
                //$result = $this->getDrafDetailDataList($bill);
                $last_id = 0;
                $details = $this->query("select * from vcr_bill_detail where parent_id=" . $bill["id"]);
                $salary_data = array();
                foreach ($details as $key => $detail) {
                    switch ($detail["goods_name"]) {
                        case "应发工资":
                            $salary_data["salary_payable"] = $detail["amount"];
                            break;
                        case "保险":
                            $salary_data["insurance"] = $detail["amount"];
                            break;
                        case "公积金":
                            $salary_data["fund"] = $detail["amount"];
                            break;
                        case "个人所得税":
                            $salary_data["personal_tax"] = $detail["amount"];
                            break;
                        case "实发工资":
                            $salary_data["salary_net"] = $detail["amount"];
                            break;
                    }
                }
                $startRow = 0;
                //分录【1】
                $fee_subject = getFeeForSalarySubject($branch_id, $bill["fee_department"]);
                $error_message["fee_subject"] = getVoucherSubjectError($fee_subject, FEE_SUBJECT_NAMES[$bill["fee_department"]] ."-工资");
                if (empty($fee_subject)){
                    //return $error_message["fee_subject"];
                    $result['error_message'] = $error_message["fee_subject"];
                    return $result;
                }
                //借： 管理费用-工资 1000
                $data_list[] = array(
                    "row_no"=>$startRow,
                    "subject_name" => $fee_subject["subject"]["name"],
                    "subject_id" => $fee_subject["subject"]["id"],
                    "amount" => $salary_data["salary_payable"],
                    "direction" => DIRECTION_DEBIT,
                    "parent_id" => $last_id,
                    "summary"=>"工资计提",
                    "error"=>$error_message["fee_subject"]
                );
                $startRow++;
                //贷： 应付职工薪酬-工资 8400
                $data_list[] = array(
                    "row_no" => $startRow,
                    "subject_name" => $salary_subject["subject"]["name"],
                    "subject_id" => $salary_subject["subject"]["id"],
                    "amount" => floatval($salary_data["salary_net"]),
                    "direction" => DIRECTION_CREDIT,
                    "parent_id" => $last_id,
                    "summary"=>"",
                    "error"=>$error_message["salary_subject"]
                );
                $startRow++;
                //贷：应付工资-社保费 500
                if ($salary_data["insurance"] > 0) {
                    $data_list[] = array(
                        "row_no" => $startRow,
                        "subject_name" => $insurance_subject["subject"]["name"],
                        "subject_id" => $insurance_subject["subject"]["id"],
                        "amount" => floatval($salary_data["insurance"]),
                        "direction" => DIRECTION_CREDIT,
                        "parent_id" => $last_id,
                        "summary"=>"",
                        "error"=>$error_message["insurance_subject"]
                    );
                    $startRow++;
                }
                //贷：应付工资-公积金100
                if ($salary_data["fund"] > 0) {
                    $data_list[] = array(
                        "row_no" => $startRow,
                        "subject_name" =>  $fund_subject["subject"]["name"],
                        "subject_id" => $fund_subject["subject"]["id"],
                        "amount" => floatval($salary_data["fund"]),
                        "direction" => DIRECTION_CREDIT,
                        "parent_id" => $last_id,
                        "summary"=>"",
                        "error"=>$error_message["fund_subject"]
                    );
                    $startRow++;
                }
                //贷：应交税费-个税1000
                if ($salary_data["personal_tax"] > 0) {
                    $data_list[] = array(
                        "row_no" => $startRow,
                        "subject_name" => $tax_subject["subject"]["name"],
                        "subject_id" => $tax_subject["subject"]["id"],
                        "amount" => floatval($salary_data["personal_tax"]),
                        "direction" => DIRECTION_CREDIT,
                        "parent_id" => $last_id,
                        "summary"=>"",
                        "error"=>$error_message["tax_subject"]
                    );
                    $startRow++;
                }
                //分录【2】
                //借：应付职工薪酬-工资    （这里是实发）
                //贷：库存现金        （这里是实发）
                if ($bill["cashpayed"]){ //现金已付
                    $data_list[] = array(
                        "row_no"=>$startRow,
                        "subject_name" => $salary_subject["subject"]["name"],
                        "subject_id" => $salary_subject["subject"]["id"],
                        "amount" => $salary_data["salary_net"],
                        "direction" => DIRECTION_DEBIT,
                        "parent_id" => $last_id,
                        "summary"=>"",
                        "error"=>$error_message["salary_subject"]
                    );
                    $startRow++;
                    $data_list[] = array(
                        "row_no"=>$startRow,
                        "subject_name" => $cash_subject["subject"]["name"],
                        "subject_id" => $cash_subject["subject"]["id"],
                        "amount" => $salary_data["salary_net"],
                        "direction" => DIRECTION_CREDIT,
                        "parent_id" => $last_id,
                        "summary"=>"",
                        "error"=>$error_message["cash_subject"]
                    );
                }

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
        //return implode("<br>",array_values($error_message));
        $result['error_message'] = implode("<br>", $result['error_message']);
        return $result;
    }
    
    protected function insert_detail($id)
    {
        $datas = array();
        $data["goods_name"] = "应发工资";
        $data["amount"] = I("post.salary_payable");
        $data["parent_id"] = $id;
        $datas[] = $data;
        $data["goods_name"] = "保险";
        $data["amount"] = I("post.insurance");
        $data["parent_id"] = $id;
        $datas[] = $data;
        $data["goods_name"] = "公积金";
        $data["amount"] = I("post.fund");
        $data["parent_id"] = $id;
        $datas[] = $data;
        $data["goods_name"] = "个人所得税";
        $data["amount"] = I("post.personal_tax");
        $data["parent_id"] = $id;
        $datas[] = $data;
        $data["goods_name"] = "实发工资";
        $data["amount"] = I("post.salary_net");
        $data["parent_id"] = $id;
        $datas[] = $data;
        M("VcrBillDetail")->addAll($datas);
        $total_amount = I("post.salary_payable");//应发工资
        $this->execute("update vcr_bill set total_amount=$total_amount,total_sum=$total_amount where id=$id");
    }


    public function import($excel_file, $client_file) {
        Vendor('PHPExcel18.PHPExcel');
        $filePath = realpath("./") . $excel_file;
        //检查文件重复
        $hash = md5_file($filePath);
        if ($hash) {
            if (M("VcrBillAttachment")->where(array("hash" => $hash))->count() > 0) {
                return buildMessage("工资册文件已经导入过，无需重复导入！");
            }
        }
        $inputFileType = \PHPExcel_IOFactory::identify($filePath);
        $PHPReader = \PHPExcel_IOFactory::createReader($inputFileType);
        $PHPReader->setReadDataOnly(true);
        try {
            $fee_departments = I("post.fee_department");
            $PHPExcel = $PHPReader->load($filePath);        //建立excel对象
            $sheetNames = $PHPExcel->getSheetNames();
            if (count($sheetNames) != count($fee_departments)){
                return buildMessage("导入的工资发放部门数量不符，请核对！",1);
            }
            $billDatas = array();
            if (!empty($sheetNames)) {
                foreach ($sheetNames as $k => $v) {
                    if (isset($fee_departments[$k])) {
                        $currentSheet = $PHPExcel->getSheet($k);
                        $rst = $this->saveDepartmentSalary($filePath,$currentSheet,$fee_departments[$k]);
                        if ($rst == -1) {
                            return buildMessage("文件格式暂时无法识别，请再核实工资单！", 1);
                        }elseif ($rst == -2) {
                            return buildMessage("实发工资计算错误，请再核实工资单！", 1);
                        }else{
                            array_push($billDatas, $rst);
                        }
                    }
                }
            }
            $user_session = session(USER_SESSION_KEY);
            if (!empty($billDatas)) {
                $this->startTrans();
                try {
                    $attachData["url"] = $excel_file;
                    $attachData["hash"] = $hash;
                    $attachData["file_name"] = $client_file;
                    $attachData["branch_id"] = $user_session->currBranchId;
                    $attId = M("VcrBillAttachment")->add($attachData);
                    $message["attachment_id"] = $attId;
                    foreach ($billDatas as $k => $billData) {
                        $billData["attachment_id"] = $attId;
                        $last_id = $this->add($billData);
                        if ($last_id && !empty($billData['detail_list'])) {
                            foreach ($billData['detail_list'] as $key => $value) {
                                $billData['detail_list'][$key]["parent_id"] = $last_id;
                            }
                            M("VcrBillDetail")->addAll($billData['detail_list']);
                        }
                    }
                    $this->commit();
                }catch(Exception $ex) {
                    $this->rollback();
                    return buildMessage("导入失败，失败原因：" . $ex->getMessage());
                }
            }

            unset($currentSheet);
            unset($PHPExcel);
            unset($PHPReader);
            $result =  buildMessage("工资册导入成功");
            $result["attachment_id"] = $attId; //返回夹带attachid;
            return $result;
        } catch (Exception $e) {
            return buildMessage($e->getMessage(), 1);
        }
    }

    public function saveDepartmentSalary($filePath,$currentSheet,$fee_department) {
        //**读取excel文件中的指定工作表*/
        $allRow = intval($currentSheet->getHighestRow());        //**取得一共有多少行*/
        $allColumn = $currentSheet->getHighestDataColumn();
        $maxColumnIndex = getExcelColumnIndex($allColumn);
        $payColumnIndex = 0;//应发所在的列
        for ($rowIndex = 1; $rowIndex <= $allRow; $rowIndex++) {
            if ($payColumnIndex > 0){
                break;
            }
            $startCharIndex = ord("A");
            while ($startCharIndex < $maxColumnIndex) {
                $columnChar = getExcelColumnChar($startCharIndex);
                $cell = $currentSheet->getCell($columnChar . $rowIndex)->getValue();
                if ($cell == "应发" || $cell == "应发薪资" || $cell == "应发工资" || $cell == "应付工资"){
                    $payColumnIndex = $startCharIndex;
                    break;
                }
                $startCharIndex++;
            }
        }
        $sumRowIndex = 0; //获取合计所在的行
        for ($rowIndex = 1; $rowIndex <= $allRow; $rowIndex++) {
            if (trim($currentSheet->getCell("A".$rowIndex)->getValue()) == "合计"){
                $sumRowIndex = $rowIndex;
                break;
            }
        }
        if ($sumRowIndex == 0 || $payColumnIndex == 0){
            //unlink($filePath);//删除文件，防止一直上传
            return  -1;
            // return buildMessage("文件格式暂时无法识别，请再核实工资单！", 1);

        }
        //应发工资
        $columnChar = getExcelColumnChar($payColumnIndex) ;
        $salary_payable = floatval(getExcelCellValue($currentSheet,$columnChar .$sumRowIndex));
        //五险
        $insuranceColumnIndexStart = $payColumnIndex + 1;
        $insurance = 0;
        for ($colIndex = 1; $colIndex <= 5; $colIndex++) {
            $columnChar = getExcelColumnChar($insuranceColumnIndexStart) ;
            $insurance+= floatval(getExcelCellValue($currentSheet,$columnChar .$sumRowIndex));
            $insuranceColumnIndexStart++;
        }
        //公积金
        $columnChar = getExcelColumnChar($insuranceColumnIndexStart) ;
        $fund = floatval(getExcelCellValue($currentSheet,$columnChar .$sumRowIndex));
        $insuranceColumnIndexStart++;

        //个人所得税
        $columnChar = getExcelColumnChar($insuranceColumnIndexStart) ;
        $personal_tax = floatval(getExcelCellValue($currentSheet,$columnChar .$sumRowIndex));
        $insuranceColumnIndexStart++;

        //实发工资
        $columnChar = getExcelColumnChar($insuranceColumnIndexStart) ;
        $salary_net = floatval(getExcelCellValue($currentSheet,$columnChar .$sumRowIndex));
        $insuranceColumnIndexStart++;

        $compResult = bccomp($salary_payable,$salary_net +  $insurance + $fund + $personal_tax);
        if ($compResult != 0){
            //unlink($filePath);//删除文件，防止一直上传
            return  -2;
            // return buildMessage("实发工资计算错误，请再核实工资单！", 1);

        }
        $accounting_section = I("post.accounting_section");
        $cashpayed = I("post.cashpayed");
        $bank_subject = I("post.bank_subject");
        $user_session = session(USER_SESSION_KEY);
        $billData["bill_date"] = time();
        $billData["bill_no"] = $this->getMaxBillNoByUserBranch();
        $billData["bill_flag"] = FLAG_BILL_SALARY;
        $billData["branch_id"] = $user_session->currBranchId;
        $billData["name"] = $user_session->currBranchName;
        $billData["user_id"] = $user_session->userId;
        $billData["accounting_section"] = $accounting_section;
        $billData["creator"] = $user_session->userName;
        $billData["fee_department"] = $fee_department;
        $billData["total_amount"] = $salary_net;
        $billData["total_sum"] = $salary_net;
        $billData["cashpayed"] = $cashpayed;
        $billData["bank_subject"] = $bank_subject;

        $detail_list = array();
        $data["goods_name"] = "应发工资";
        $data["amount"] = $salary_payable;
        $data["parent_id"] = 0;
        $detail_list[] = $data;
        $data["goods_name"] = "保险";
        $data["amount"] = $insurance;
        $data["parent_id"] = 0;
        $detail_list[] = $data;
        $data["goods_name"] = "公积金";
        $data["amount"] = $fund;
        $data["parent_id"] = 0;
        $detail_list[] = $data;
        $data["goods_name"] = "个人所得税";
        $data["amount"] = $personal_tax;
        $data["parent_id"] = 0;
        $detail_list[] = $data;
        $data["goods_name"] = "实发工资";
        $data["amount"] = $salary_net;
        $data["parent_id"] = 0;
        $detail_list[] = $data;
        // M("VcrBillDetail")->addAll($detail_list);
        $billData['detail_list'] = $detail_list;
        // }
        return $billData;
    }

    //获取生成凭证的详情项draf_detail
    public function getDrafDetailDataList($bill,$branch_id){
        $result = [];
        $salary_subject = getSalarySubject($branch_id);
        $error_message["salary_subject"] = getVoucherSubjectError($salary_subject, "应付职工薪酬-工资");
        if (empty($salary_subject)){
            $result['error_message'] = $error_message["salary_subject"];
            return $result;
        }
        $insurance_subject = getInsuranceSubject($branch_id);
        $error_message["insurance_subject"] = getVoucherSubjectError($insurance_subject, "应付职工薪酬-医社保");
        if (empty($insurance_subject)){
            $result['error_message'] = $error_message["insurance_subject"];
            return $result;
        }
        $fund_subject = getFundSubject($branch_id);
        $error_message["fund_subject"] = getVoucherSubjectError($fund_subject, "应付职工薪酬-个人公积金");
        if (empty($fund_subject)){
            $result['error_message'] = $error_message["fund_subject"];
            return $result;
        }
        $tax_subject = getPersonTaxSubject($branch_id);
        $error_message["tax_subject"] = getVoucherSubjectError($tax_subject, "应交税金-个人所得税");
        if (empty($tax_subject)){
            $result['error_message'] = $error_message["tax_subject"];
            return $result;
        }
        $cash_subject = getCashSubject($branch_id);
        $error_message["cash_subject"] = getVoucherSubjectError($cash_subject, "现金");
        if (empty($cash_subject)){
            $result['error_message'] = $error_message["cash_subject"];
            return $result;
        }
        $max_bill_no = $this->getMaxBillNoByUserBranch();
        $result['data_list'] = [];
        $last_id = 0;
        $details = $this->query("select * from vcr_bill_detail where parent_id=" . $bill["id"]);
        $salary_data = array();
        foreach ($details as $key => $detail) {
            switch ($detail["goods_name"]) {
                case "应发工资":
                    $salary_data["salary_payable"] = $detail["amount"];
                    break;
                case "保险":
                    $salary_data["insurance"] = $detail["amount"];
                    break;
                case "公积金":
                    $salary_data["fund"] = $detail["amount"];
                    break;
                case "个人所得税":
                    $salary_data["personal_tax"] = $detail["amount"];
                    break;
                case "实发工资":
                    $salary_data["salary_net"] = $detail["amount"];
                    break;
            }
        }
        $startRow = 0;
        //分录【1】
        $fee_subject = getFeeForSalarySubject($branch_id, $bill["fee_department"]);
        $error_message["fee_subject"] = getVoucherSubjectError($fee_subject, FEE_SUBJECT_NAMES[$bill["fee_department"]] . "-工资");
        if (empty($fee_subject)) {
            $result['error_message'] = $error_message["fee_subject"];
            return $result;
        }
        //借： 管理费用-工资 1000
        $data_list[] = array(
            "row_no" => $startRow,
            "subject_name" => $fee_subject["subject"]["name"],
            "subject_id" => $fee_subject["subject"]["id"],
            "amount" => $salary_data["salary_payable"],
            "direction" => DIRECTION_DEBIT,
            "parent_id" => $last_id,
            "summary" => "工资计提",
            "error" => $error_message["fee_subject"]
        );
        $startRow++;
        //贷： 应付职工薪酬-工资 8400
        $data_list[] = array(
            "row_no" => $startRow,
            "subject_name" => $salary_subject["subject"]["name"],
            "subject_id" => $salary_subject["subject"]["id"],
            "amount" => floatval($salary_data["salary_net"]),
            "direction" => DIRECTION_CREDIT,
            "parent_id" => $last_id,
            "summary" => "",
            "error" => $error_message["salary_subject"]
        );
        $startRow++;
        //贷：应付工资-社保费 500
        if ($salary_data["insurance"] > 0) {
            $data_list[] = array(
                "row_no" => $startRow,
                "subject_name" => $insurance_subject["subject"]["name"],
                "subject_id" => $insurance_subject["subject"]["id"],
                "amount" => floatval($salary_data["insurance"]),
                "direction" => DIRECTION_CREDIT,
                "parent_id" => $last_id,
                "summary" => "",
                "error" => $error_message["insurance_subject"]
            );
            $startRow++;
        }
        //贷：应付工资-公积金100
        if ($salary_data["fund"] > 0) {
            $data_list[] = array(
                "row_no" => $startRow,
                "subject_name" => $fund_subject["subject"]["name"],
                "subject_id" => $fund_subject["subject"]["id"],
                "amount" => floatval($salary_data["fund"]),
                "direction" => DIRECTION_CREDIT,
                "parent_id" => $last_id,
                "summary" => "",
                "error" => $error_message["fund_subject"]
            );
            $startRow++;
        }
        //贷：应交税费-个税1000
        if ($salary_data["personal_tax"] > 0) {
            $data_list[] = array(
                "row_no" => $startRow,
                "subject_name" => $tax_subject["subject"]["name"],
                "subject_id" => $tax_subject["subject"]["id"],
                "amount" => floatval($salary_data["personal_tax"]),
                "direction" => DIRECTION_CREDIT,
                "parent_id" => $last_id,
                "summary" => "",
                "error" => $error_message["tax_subject"]
            );
            $startRow++;
        }
        //分录【2】
        //借：应付职工薪酬-工资    （这里是实发）
        //贷：库存现金        （这里是实发）
        if ($bill["cashpayed"]) { //现金已付
            $data_list[] = array(
                "row_no" => $startRow,
                "subject_name" => $salary_subject["subject"]["name"],
                "subject_id" => $salary_subject["subject"]["id"],
                "amount" => $salary_data["salary_net"],
                "direction" => DIRECTION_DEBIT,
                "parent_id" => $last_id,
                "summary" => "",
                "error" => $error_message["salary_subject"]
            );
            $startRow++;
            $data_list[] = array(
                "row_no" => $startRow,
                "subject_name" => $cash_subject["subject"]["name"],
                "subject_id" => $cash_subject["subject"]["id"],
                "amount" => $salary_data["salary_net"],
                "direction" => DIRECTION_CREDIT,
                "parent_id" => $last_id,
                "summary" => "",
                "error" => $error_message["cash_subject"]
            );
        }
        return $result;
    }




    public function getBillData($branch_id,$accounting_section,$source_id = null){
        $id = empty($source_id) ? I("post.id") : $source_id;
        if (empty($id)) {
            $where = sprintf(" where ISNULL(voucher_id) and bill_flag=%d  and branch_id=%d and accounting_section='%s'", FLAG_BILL_SALARY, $branch_id, $accounting_section);
        } else {
            $where = sprintf(" where ISNULL(voucher_id) and bill_flag=%d and id in (%s) and branch_id=%d", FLAG_BILL_SALARY, implode(",", $id), $branch_id);
        }
        $sql = "select * from vcr_bill $where order by name";
        $billDatas = $this->query($sql);
        return $billDatas;
    }

}
