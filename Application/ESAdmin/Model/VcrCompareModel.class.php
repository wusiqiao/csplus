<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class VcrCompareModel extends DataModel {
    protected $tableName        =   'vcr_bill';

    public function compare($excel_file, $branch_id) {
        Vendor('PHPExcel18.PHPExcel');
        $filePath = realpath("./") . $excel_file;
        $PHPReader = \PHPExcel_IOFactory::createReaderForFile($filePath);
        try {
            $PHPExcel = $PHPReader->load($filePath);        //建立excel对象
            $currentSheet = $PHPExcel->getSheet(0);        //**读取excel文件中的指定工作表*/
            $field_columns = $this->getFieldColumns($currentSheet); //获取科目字段的对应的列
            if (!$field_columns) {
                unlink($filePath);//删除文件，防止一直上传
                unset($currentSheet);
                unset($PHPExcel);
                unset($PHPReader);
                return buildMessage("文件格式错误！", 1);
            }
            //比较的来源包括外来增值税发票非费用票据，费用增值税票据两种，第一类在表头，第二类在表身，按金额来匹配（可能不准确，但是可以保证发票数量比对正确）
            $sql_0 = "select a.id,a.total_amount, a.total_tax,a.source_no,a.bill_flag,a.authed,a.is_fee  from vcr_bill a ";
            $where_0 = "a.branch_id=$branch_id and a.tax_type=".TAX_TYPE_VTX." and a.is_fee = 0 and authed=0 and a.bill_flag =".FLAG_BILL_TAX_PAY;
            $sql_1 ="select a.id,b.amount as total_amount, b.tax_amount as total_tax,b.source_no,a.bill_flag,b.authed,a.is_fee  
                        from vcr_bill a inner join vcr_bill_detail b on a.id=b.parent_id ";
            $where_1 = "a.branch_id=$branch_id and b.fee_type=".RATE_VATTAX." and b.authed=0 and a.bill_flag=". FLAG_BILL_TAX_PAY;
            $sql = sprintf("%s where %s union %s where %s order by id desc",$sql_0, $where_0, $sql_1, $where_1);
            $list = $this->query($sql);
            $bucket = array();
            foreach ($list as $key=>$bill){
                $bucket[$bill["total_amount"]][] = &$list[$key];
            }
            $found_count = 0;
            for ($rowIndex = $field_columns["source_no"]["row"] + 1; $rowIndex <= $currentSheet->getHighestRow(); $rowIndex++) {
                $amount = strval(trim($currentSheet->getCell($field_columns["amount"]["col"] . $rowIndex)->getValue()));
                $source_no = strval(trim($currentSheet->getCell($field_columns["source_no"]["col"] . $rowIndex)->getValue()));
                if ($bucket[$amount]){ //有验证，设置验证标志
                    foreach ($bucket[$amount] as $key=>$value){
                        if (empty($bucket[$amount][$key]["authed"])){
                            $bucket[$amount][$key]["authed"] = 1;
                            $bucket[$amount][$key]["source_no"] = $source_no;
                            if ($bucket[$amount][$key]["is_fee"]){
                                $this->execute("update vcr_bill_detail set authed=1,source_no='$source_no' where id=" . $value["id"]);
                            }else {
                                $this->execute("update vcr_bill set authed=1,source_no='$source_no' where id=" . $value["id"]);
                            }
                        }
                    }
                    $found_count++;
                }
            }
            unset($currentSheet);
            unset($PHPExcel);
            unset($PHPReader);
            return buildMessage("匹配认证数量".$found_count."条");
        } catch (Exception $e) {
            return buildMessage($e->getMessage(), 1);
        }
    }

    private function getFieldColumns($currentSheet) {
        $allRow = $currentSheet->getHighestRow();
        $allColumn = $currentSheet->getHighestColumn();
        $columns = array();
        for ($rowIndex = 1; $rowIndex <= $allRow; $rowIndex++) {
            for ($col = "A"; $col <= $allColumn; $col++) {
                $value = $currentSheet->getCell($col . $rowIndex)->getValue();
                if (str_exists($value, "发票号码") !== false) {
                    $columns["source_no"]["row"] = $rowIndex;
                    $columns["source_no"]["col"] = $col;
                }
                if (str_exists($value, "销方名称") !== false) {
                    $columns["name"]["row"] = $rowIndex;
                    $columns["name"]["col"] = $col;
                }
                if (str_exists($value, "金额") !== false) {
                    $columns["amount"]["row"] = $rowIndex;
                    $columns["amount"]["col"] = $col;
                }
                if (str_exists($value, "税额") !== false) {
                    $columns["tax_amount"]["row"] = $rowIndex;
                    $columns["tax_amount"]["col"] = $col;
                }
            }
            if ($columns["source_no"] && $columns["name"] && $columns["amount"]) {
                if ($columns["source_no"]["row"] == $columns["name"]["row"] && $columns["name"]["row"] == $columns["amount"]["row"]) {
                    return $columns;
                }
            }
        }
        return false;
    }

    //获取未认证的自开票资料(包括非费用类增值税发票和费用类中子项目包含的增值税票）
    public function getValueTaxBill($branch_id, $accounting_section, $pageNo, $pageSize){
        $sql_0 = "select a.bill_no,a.id as bill_id,a.id,a.name,a.total_amount, a.total_tax,IFNULL(a.source_no,'') as source_no,a.bill_flag,a.authed  
                        from vcr_bill a ";
        $where_0 = "a.branch_id=$branch_id and a.tax_type=".TAX_TYPE_VTX." and a.is_fee = 0 and a.bill_flag =".FLAG_BILL_TAX_PAY;
        if ($accounting_section){
            $where_0 = $where_0. " and a.accounting_section='$accounting_section'";
        }
        $sql_1 ="select a.bill_no,a.id as bill_id,concat(a.id,'_',b.id) as id,a.name,b.amount as total_amount, b.tax_amount as total_tax,IFNULL(b.source_no,'') as source_no,a.bill_flag,b.authed  
                        from vcr_bill a inner join vcr_bill_detail b on a.id=b.parent_id ";
        $where_1 = "a.branch_id=$branch_id and b.fee_type=".RATE_VATTAX." and  a.bill_flag=". FLAG_BILL_TAX_PAY;
        if ($accounting_section){
            $where_1 = $where_1. " and a.accounting_section='$accounting_section'";
        }
        $sql = sprintf("%s where %s union %s where %s order by id desc limit %d,%d",
                        $sql_0, $where_0, $sql_1, $where_1, ($pageNo-1)*$pageSize, $pageSize
        );
        $result["rows"] = $this->query($sql);
        $sql_count0 = "select count(id) as total,sum(case authed when 1 then 0 else 1 end) as un_authed_total from vcr_bill a where $where_0";
        $sql_count1 = "select count(a.id) as total,sum(case b.authed when 1 then 0 else 1 end) as un_authed_total from vcr_bill a inner join vcr_bill_detail b where $where_1";
        $cache_key = md5($sql_count0.$sql_count1);
        $countInfo = S($cache_key);
        if ($countInfo === false || $pageNo == 1){//第二页开始才缓存
            $count0_data = $this->query($sql_count0);
            $count1_data = $this->query($sql_count1);
            $total = intval($count0_data[0]["total"]) + intval($count1_data[0]["total"]);
            $unAuthedTotal = intval($count0_data[0]["un_authed_total"]) + intval($count1_data[0]["un_authed_total"]);
            S($cache_key, array($total,$unAuthedTotal));
        }
        $result["total"] = $countInfo[0];
        $result["un_authed_total"] = $countInfo[1];
        return $result;
    }

}
