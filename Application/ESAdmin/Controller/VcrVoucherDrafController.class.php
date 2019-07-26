<?php

namespace ESAdmin\Controller;
use Common\Lib\Controller\DataController;
use ESAdmin\Model\VcrBillBankAccountModel;
use org\majkel\dbase\Builder;
use  org\majkel\dbase\Record;
use  org\majkel\dbase\Format;
use org\majkel\dbase\Table;
class  VcrVoucherDrafController extends DataController {
    
    
     public function indexAction() {
        $this->assignPermissions();
        $this->bill_flags = json_encode(getBillFlags(true));
        $this->assign("company_name",$this->_user_session->currBranchName);
        $this->display();
    }

    public function getCreateConfigAction(){
        $condition["branch_id"] = $this->_user_session->currBranchId;
        $configs = M("VcrConfig")->where($condition)->find();
        $this->ajaxReturn($configs);
    }

    protected function _before_add(&$data) {
        parent::_before_add($data);
        $data["company_name"] = $this->companyName;
        $data["creater"] = empty($this->_user_session->staffName) ? $this->_user_session->userName : $this->_user_session->staffName;
        $data["image_count"] = 0;
        $data["bill_date"] = time();
        $data["accounting_section"] = date("Y/m");
        $data["detail"]  = array([],[]);
        $data["number"]  = D(CONTROLLER_NAME)->getMaxNumber($this->_user_session->currBranchId);
        //从原单新增，有传入源单编号
        $source_id  = I("get.source");
        $branch_id = $this->_user_session->currBranchId;
        if ($source_id){
            $result = [];
            $attachments = M("VcrBill a")->join("sys_document b on a.image_id=b.id")->where("a.id=$source_id")->field("b.id,b.path,0 as type")->select();
            $bill = M("VcrBill")->where("id = $source_id")->field("bill_flag,is_fee")->find();
            switch ($bill['bill_flag']) {
                //银行对账单
                case FLAG_BILL_BANK:
                    $result = D(CONTROLLER_NAME)->getBillBankDetail($data,$source_id,$branch_id);
                    break;
                //自开发票
                case FLAG_BILL_TAX_INCOME:
                    $result = D(CONTROLLER_NAME)->getOutPutBillDetail($data,$source_id,$branch_id);
                    break;
                //外取票
                case FLAG_BILL_TAX_PAY:
                    if($bill['is_fee'] == 1){
                        //获取外取票费用部分的生成凭证详情
                        $result = D(CONTROLLER_NAME)->getProcessFeeDetail($data,$source_id,$branch_id);
                    }else{
                        //获取外取票非费用部分的生成凭证详情
                        $result = D(CONTROLLER_NAME)->getProcessPurchaseDetail($data,$source_id,$branch_id);
                    }
                    break;
                //工资册
                case FLAG_BILL_SALARY:
                    /*$billData = D("VcrBillSalary")->getBillData($branch_id,$data["accounting_section"],[$source_id]);
                    if($billData[0]){
                        $result = D("VcrBillSalary")->getDrafDetailDataList($billData[0],$branch_id);
                    }
                    $result['list'] = [[],[],[]];*/
                    break;
                default:
                    break;
            }
            $data["detail"] = empty($result['list']) ?  array([],[]) : $result['list'];
            $data["accounting_section"] = empty($result['accounting_section']) ? $data["accounting_section"] : $result['accounting_section'];
            $data['source_id'] = $source_id;
        }
        $this->attachments = json_encode($attachments);
    }
    protected function _before_detail(&$data) {
        parent::_before_detail($data);
        $details = D(CONTROLLER_NAME)->getDetails(array($data["id"]));
        $data["detail"]  = $details[$data["id"]];
        if(!$data['detail']){
            $data["detail"]  = array([],[]);
        }
        //凭证审核选项
        $config = M("VcrConfig")->where("branch_id = ".$data['branch_id'])->getField("vcr_draf_review");
        //需要会计+主管审核
        if($config == VCR_DRAF_REVIEW_SUP){
            $data['show_sup'] = 1;
        }
        $this->attachments = json_encode($this->getAttachments($data));
    }

    protected function _before_write($type, &$data) {
         parent::_before_write($type, $data);
        $subject_ids = I("post.subject_id");
        $subject_names = I("post.subject_name");
        foreach ($subject_ids as $k=>$subject){
            if (empty($subject) || empty($subject_names[$k])){
                $this->responseJSON(buildMessage("科目无效",1));
                break;
            }
        }
        $debit_amounts = I("post.debit_amount");
        $credit_amounts = I("post.credit_amount");
        if (count($subject_ids) != count($credit_amounts) || count($subject_ids) != count($debit_amounts)){
            $this->responseJSON(buildMessage("数据错误",1));
        }
        $total_debit_amounts = array_sum($debit_amounts);
        $total_credit_amounts = array_sum($credit_amounts);
        if (bccomp($total_debit_amounts, $total_credit_amounts, 2) != 0){
            $this->responseJSON(buildMessage("借贷不平，无法保存",1));
        }
        if ($total_debit_amounts == 0 && $total_credit_amounts == 0){
            $this->responseJSON(buildMessage("借贷都为零，无法保存",1));
        }
        $images = I("post.images");
        /*foreach ($images as $key=>$image){
            $data["attachment_$key"] = $image;
        }*/
        for($i = 0;$i<4;$i++){
            $data["attachment_$i"] = $images[$i];
        }
        $data["bill_no"] = $this->getTableMaxBillNoByUserBranch();
        $data["bill_date"] = strtotime(date("Y-m-d"));
        $data["has_error"] = 0; //修改后，自动改成无错误
    }

    //审核
    public function reviewedAction($id){
         if (is_array($id)){
             $data["reviewed"]  = I("post.reviewed");
             $data["has_error"] = 0;
             $name = $this->getCurrentUserName();
             if($data["reviewed"] == 2){
                 //主管审核
                 $data["review_sup_name"] = $name;
                 $log_content = ["主管审核"];
             }else{
                 //会计审核
                 $data["reviewer"] = $name;
                 $log_content = ["会计审核"];
             }
             if  (M(CONTROLLER_NAME)->where(array("id"=>array("in", $id)))->save($data) !== false){
                 $condition['parent_id'] = array("in",$id);
                 M("VcrVoucherDrafDetail")->where($condition)->setField("error","");
                 foreach ($id as $v){
                     D(CONTROLLER_NAME)->addVoucherLog($log_content,$v);
                 }
                 $this->responseJSON(array("code"=>0,"message"=>"操作成功","name"=>$name));
             }else{
                 $this->responseJSON(buildMessage("操作失败", 1));
             }
         }else{
             $this->responseJSON(buildMessage("操作失败，请先选择要复核或取消复核的草稿", 1));
         }
    }
    
    public function changeSubjectAction($detail_id, $subject_id){  
        $branch_id = $this->_user_session->currBranchId;
        if (D(CONTROLLER_NAME)->changeSubject($branch_id,$detail_id, $subject_id) !== false){
            $this->responseJSON(buildMessage("修改成功"));
        }else{
            $this->responseJSON(buildMessage("修改科目失败",1));
        }
       
    }

    public function _parseFilter(&$_filter){
         $reviewed = I("post.reviewed");
         if($reviewed != ""){
             //获取客户配置，根据是否需要主管审核判断已完成条件
             $config = M("VcrConfig")->where("branch_id = ".$this->_user_session->currBranchId)->getField("vcr_draf_review");
             if($reviewed == 1){
                 if($config == VCR_DRAF_REVIEW_SUP){
                     //需要会计+主管审核
                     $_filter["a.reviewed"] = 2;
                 }else{
                     $_filter["a.reviewed"] = array(1,2,"or");
                 }
             }elseif($reviewed == 0){
                 if($config == VCR_DRAF_REVIEW_SUP){
                     //需要会计+主管审核
                     $_filter["a.reviewed"] = array("neq",2);
                 }else{
                     $_filter["a.reviewed"] = array("neq",1);
                 }
             }
         }
         parent::_parseFilter($_filter);
    }

    public function listAction(){
        $_filter = array();
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $this->_parseFilter($_filter);
        $count = D(CONTROLLER_NAME)->setDacFilter("a")->where($_filter)->count();
        $draf_list = D(CONTROLLER_NAME)->setDacFilter("a")->where($_filter)->field("a.*")->order("a.id desc")->page($page_index, $page_size)->select();
        if ($draf_list){
            $draf_array = array_column($draf_list, "id");
            $draf_bill_detail = D(CONTROLLER_NAME)->getDetails($draf_array);
            foreach ($draf_list as $key=>$value){
                $details = $draf_bill_detail[$value["id"]];
                $draf_list[$key]["summary"] =  implode("；",array_column($details, "summary"));
                $subjectText = "";
                $debit_amountText = "";
                $credit_amountText = "";
                $error = "";
                foreach ($details as $dk=>$detail){
                    if ($detail["error"]){
                        $error = $error .  $detail["error"] ."\n";
                    }
                    if ($detail["direction"] == 0){
                        $subjectText =  $subjectText. "<p>借：" . $detail["full_name"] ."</p>";
                    }else{
                        $subjectText =  $subjectText. "<p>贷：" . $detail["full_name"] ."</p>";
                    }
                    $debit_amountText =  $debit_amountText. "<p>" . number_format($detail["debit_amount"],2) ."</p>";
                    $credit_amountText =  $credit_amountText. "<p>" .number_format($detail["credit_amount"],2)."</p>";
                }
                $draf_list[$key]["subject_name"] = $subjectText;
                $draf_list[$key]["debit_amount"] = $debit_amountText;
                $draf_list[$key]["credit_amount"] = $credit_amountText;
                $draf_list[$key]["error"] = $error;
                $draf_list[$key]["state"] = D(CONTROLLER_NAME)->getVoucherState($draf_list[$key]);
                $bill_flag = M("VcrBill")->where("voucher_id = ".$value['id'])->getField("bill_flag");
                $draf_list[$key]["bill_type"] = FLAG_BILL_NAMES[$bill_flag];
            }
            $result["total"] = $count;
            $result["rows"] = &$draf_list;
            $this->getFinishCount($result,$_filter);
        }else{
            $result["total"] = 0;
            $result["rows"] = array();
            $this->getFinishCount($result,$_filter);
        }
        $this->responseJSON($result);
    }

    public function getFinishCount(&$result,$_filter){
        $config = M("VcrConfig")->where("branch_id = ".$this->_user_session->currBranchId)->getField("vcr_draf_review");
        if($config == VCR_DRAF_REVIEW_SUP){
            //需要会计+主管审核
            $_filter["a.reviewed"] = 2;
        }else{
            $_filter["a.reviewed"] = array(1,2,"or");
        }
        $model = D(CONTROLLER_NAME);
        $result['reviewed_count'] =  $model->setDacFilter("a")->where($_filter)->count();
        unset($_filter['a.reviewed']);
        $total_count = $model->setDacFilter("a")->where($_filter)->count();
        $result['unReviewed_count'] = $total_count - $result['reviewed_count'];
    }

    //查询草稿来源单
    public function getSourceAction($draf_id){
        $result["source"] = D("VcrBillDetail a")->field("a.*,b.name,d.url")
            ->join("vcr_bill b on a.parent_id=b.id")
            ->join("vcr_voucher_relation c on c.bill_id=b.id")
            ->join("left join vcr_bill_attachment d on d.id=b.image_id")
            ->where("c.voucher_id=$draf_id and b.branch_id=".$this->_user_session->currBranchId)->select();
        return $this->responseJSON(buildResult($result));
    }

    public function createDrafAction(){
        $accounting_section = I("accounting_section");
        if (empty($accounting_section)){
            $this->responseJSON(buildMessage("请选择会计期间",1));
        }
        $result = D(CONTROLLER_NAME)->createDraf($this->_user_session, $accounting_section);
        $this->responseJSON($result);
    }

    public function exceptionsAction(){
         $list = D(CONTROLLER_NAME)->getExceptions($this->_user_session->currBranchId);
         $this->assign("exceptions", $list);
         $this->display();
    }

    //处理异常凭证科目
    public function dealExceptionAction($old_name, $subject_id){
        $result = D(CONTROLLER_NAME)->dealException($this->_user_session->currBranchId, $old_name, $subject_id);
        $msg = ($result === false)?buildMessage("更新失败", 1):buildMessage("更新成功");
        $this->responseJSON($msg);
    }

    //异常数量
    public function getExceptionCountAction(){
        $exception_count = D(CONTROLLER_NAME)->getExceptionCount($this->_user_session->currBranchId);
        echo strval($exception_count);
    }

    public function exportViewAction(){
        $this->display("export");
    }

    //导出前检查
    public function exportCheckAction(){
        //$this->responseJSON(buildMessage("检查正常"));
        $accounting_section = I("post.accounting_section");
        $condition["branch_id"] = $this->_user_session->currBranchId;
        $condition["accounting_section"] = $accounting_section;
        //已完成凭证才可导出，获取客户配置，增加已完成条件
        $config = M("VcrConfig")->where("branch_id = ".$condition["branch_id"])->getField("vcr_draf_review");
        if($config == VCR_DRAF_REVIEW_SUP){
            //需要会计+主管审核
            $condition["reviewed"] = 2;
        }else{
            $condition["reviewed"] = array(1,2,"or");
        }
        if (M("VcrVoucherDraf")->where($condition)->count() == 0){
            $this->responseJSON(buildMessage($accounting_section."当月没有已完成凭证资料，请先确认", 1));
        }
        $exception_count = D(CONTROLLER_NAME)->getExceptionCount($this->_user_session->currBranchId);
        if ($exception_count > 0){
            $this->responseJSON(buildMessage("当前凭证存在异常科目，请先修正", 1));
        }else{
            $this->responseJSON(buildMessage("检查正常"));
        }
    }
    //导出实现
    public function exportAction(){
        $accounting_section = I("get.accounting_section"); //get参数不能有“/”符号，所以先转成“-”
        $voucher_no = I("get.voucher_no",1); //凭证号-k3
        $serial_no = I("get.serial_no",1);//顺序号--k3
        //$format = I("get.exprot_format");
        $format = I("get.state");
        $tmplateFile = getFormatTemplateInfo($format);
        $condition["a.branch_id"] = $this->_user_session->currBranchId;
        $condition["a.accounting_section"] = $accounting_section;
        //已完成凭证才可导出，获取客户配置，增加已完成条件
        $config = M("VcrConfig")->where("branch_id = ".$condition["a.branch_id"])->getField("vcr_draf_review");
        if($config == VCR_DRAF_REVIEW_SUP){
            //需要会计+主管审核
            $condition["a.reviewed"] = 2;
        }else{
            $condition["a.reviewed"] = array(1,2,"or");
        }
        $list = M("VcrVoucherDraf a")
            ->join("vcr_voucher_draf_detail b on b.parent_id=a.id")
            ->join("vcr_subject c on c.id=b.subject_id")
            ->field("a.id as bill_id, a.accounting_section,a.bill_date,a.bill_no,a.creater,b.*,c.no as subject_no,c.name as subject_name")
            ->where($condition)->order("a.id,b.row_no")->select();
        $func = $format."_export";
        if (method_exists($this, $func)) {
            call_user_func_array(array($this, $func), array($tmplateFile, $list, $voucher_no, $serial_no));
        }
        unset($list);
    }

    private function k3_export($tmplateFile, $list, $voucher_no, $serialNo){
        Vendor("dbase.autoloader");
        $accounting_section = I("get.accounting_section");
        $accounting_section = str_replace(array("/","-"),"", $accounting_section);
        $filePath = dirname(dirname(__FILE__)) ."/Template/".$tmplateFile["file"]; //模板文件
        $new_filePath = dirname(dirname(__FILE__))."/Upload/export/k3_". $this->_user_session->currBranchId .$accounting_section.".".$tmplateFile["ext"]; //模板文件
        $dbf = Builder::fromFile($filePath)
            ->setFormatType(Format::DBASE3)
            ->build($new_filePath);
        $last_billId = $list[0]["bill_id"];
        $row_no = -1;
        foreach ($list as $value){
            if ($last_billId != $value["bill_id"]){
                $serialNo++;
                $voucher_no++;
                $row_no = 0;
            }else{
                $row_no++;
            }
            $record = new Record();
            $record->FDATE = date("Y-m-d", $value["bill_date"]);
            $accounting_sections = explode("/", $value["accounting_section"]);
            $record->FPERIOD = intval($accounting_sections[1]);
            $record->FGROUP = iconv("utf-8","gbk","记");
            $record->FNUM = $voucher_no;
            $record->FENTRYID = $row_no;
            $record->FEXP = iconv("utf-8","gbk",$value["summary"]);
            $record->FACCTID = $value["subject_no"];
            $record->FCYID = "RMB"; //币别代码
            $record->FEXCHRATE = 1; //汇率
            $direction = empty($value["direction"])?"D":"C";
            $record->FDC = $direction; //借方贷方
            $record->FFCYAMT =$value["amount"];//原币金额
            $record->FQTY = $value["quantity"];
            $record->FPRICE = intval($value["price"]);
            $record->FDEBIT = ($direction=="D")?$value["amount"]:0;//借方金额
            $record->FCREDIT = ($direction=="C")?$value["amount"]:0;//贷方金额
            $record->FPREPARE = iconv("utf-8","gbk",$value["creator"]);;//制单人FPREPARE
            $record->FPOSTED = false; //凭证是否已过帐
            $record->FDELETED = false; //凭证删除标志
            $record->FSERIALNO = $serialNo; //凭证序列号
            $last_billId =  $value["bill_id"];
            $dbf->insert($record);
        }
        header("Content-type:application/octet-stream");
        //$filename = basename($new_filePath);
        header("Content-Disposition:attachment;filename = ".$accounting_section.".dbf");
        header("Accept-ranges:bytes");
        header("Accept-length:".filesize($new_filePath));
        readfile($new_filePath);
    }

    private function yongyou_export($tmplateFile, $list){
        Vendor('PHPExcel18.PHPExcel.IOFactory');
        $filePath = dirname(dirname(__FILE__)) ."/Template/".$tmplateFile['file']; //模板文件
        $objPHPExcel = \PHPExcel_IOFactory::load($filePath);
        $objPHPExcel->setActiveSheetIndex(0);
        $startRow = 2;
        $serialNo = 1;
        $last_billId = $list[0]["bill_no"];
        foreach ($list as $value){
            $bill_date = date("Y-m-d", $value["bill_date"]);
            $objPHPExcel->getActiveSheet()->setCellValue("A".$startRow, $bill_date);//日期
            $objPHPExcel->getActiveSheet()->setCellValue("B".$startRow, "记"); //凭证字
            $objPHPExcel->getActiveSheet()->setCellValue("C".$startRow, $value["bill_no"]." "); //凭证号
            $objPHPExcel->getActiveSheet()->setCellValue("E".$startRow, $value["summary"]);//摘要
            $objPHPExcel->getActiveSheet()->setCellValue("F".$startRow, $value["subject_no"]);//科目代码
            $direction = empty($value["direction"])?"D":"C";
            $objPHPExcel->getActiveSheet()->setCellValue("G".$startRow, ($direction=="D")?$value["amount"]:0);//借方金额
            $objPHPExcel->getActiveSheet()->setCellValue("H".$startRow, ($direction=="C")?$value["amount"]:0);//贷方金额
            $objPHPExcel->getActiveSheet()->setCellValue("AW".$startRow, "");//套账号？？
            $accounting_sections = explode("/", $value["accounting_section"]);
            $objPHPExcel->getActiveSheet()->setCellValue("AY".$startRow, $accounting_sections[0]);//会计年
            $objPHPExcel->getActiveSheet()->setCellValue("AZ".$startRow, $accounting_sections[1]);//会计月
            $objPHPExcel->getActiveSheet()->setCellValue("BB".$startRow, $value["bill_no"]." "); //凭证号?和C什么区别？？？
            $objPHPExcel->getActiveSheet()->setCellValue("BK".$startRow, $value["subject_name"]);//科目名称
            $objPHPExcel->getActiveSheet()->setCellValue("BG".$startRow, $serialNo);//序号
            if ($last_billId == $value["bill_no"]){
                $serialNo++;
            }else{
                $serialNo = 1;
            }
            $last_billId =  $value["bill_no"];
            $startRow++;
        }
        $accounting_section = I("get.accounting_section");
        $accounting_section = str_replace(array("/","-"),"", $accounting_section);
        $file_name = $this->_user_session->currBranchName."-".$accounting_section .".".$tmplateFile["ext"];
        $userBrowser = $_SERVER['HTTP_USER_AGENT'];
        if (preg_match('/MSIE/i', $userBrowser)) {
            $file_name = urlencode($file_name);
        }
        $file_name = iconv('UTF-8', 'GBK//IGNORE', $file_name);
        $this->setExcelHeader($file_name);
        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $objWriter->save('php://output');
        unset($objWriter);
        unset($objPHPExcel);
    }

    public function moveRecordAction($direction, $currentId = "", $accounting_section){
         if($currentId == ""){
             die("");
         }
        $data = D(CONTROLLER_NAME)->getViewData($currentId, $direction, $accounting_section, $this->_user_session->currBranchId);
        if (empty($data)){
            die("");
        }
        $this->model = $data;
        $this->attachments = json_encode($this->getAttachments($data));
        $this->display("view");
    }

    private function getAttachments($data){
        $attachment_ids = array();
        for($i=0; $i<4; $i++){
            if ($data["attachment_$i"]){
                $attachment_ids[] = $data["attachment_$i"];
            }
        }
        //源单附件
        $source_attachment_id = M("VcrBill a")->where("voucher_id=" . $data["id"])->getField("image_id");
        if ($source_attachment_id){
            $attachment_ids[] = $source_attachment_id;
        }
        $attachments = [];
        if ($attachment_ids) {
            $fields = $source_attachment_id?"id,path,case id when $source_attachment_id then 0 else 1 end as type":"id,path,1 as type";
            $attachments = M("SysDocument")->where(array("id" => array("in", $attachment_ids)))->field($fields)->select();
        }
        return $attachments;
    }

    //获取当前用户的姓名
    public function getCurrentUserName(){
        return empty($this->_user_session->staffName) ? $this->_user_session->userName : $this->_user_session->staffName;
    }

    //获取凭证修改详细日志
    public function getVoucherLogAction(){
         $condition['voucher_id'] = I("post.id");
         $condition['branch_id'] = $this->_user_session->currBranchId;
         $result = M("VcrVoucherLog")->where($condition)->select();
         foreach ($result as $k=>$v){
             $result[$k]['log_time_fmt'] = date("Y-m-d H:i:s",$v['log_time']);
         }
         $this->ajaxReturn($result);
    }

    //获取凭证在会计区间内未生成、已生成条数
    public function getVoucherCountAction(){
         $year = I("post.year");
         if(!$year){
             $this->ajaxReturn([]);
         }
         $result = D(CONTROLLER_NAME)->getVoucherCount($this->_user_session->currBranchId,$year);
         $this->ajaxReturn($result);
    }

}