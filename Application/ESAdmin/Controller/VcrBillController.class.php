<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;
use Think\Think;

class VcrBillController extends DataController {

    const BILL_ORIGIN_BRANCH = '2';
    const BILL_ORIGIN_CUSTOMER = '4';

    protected function _before_list(&$list) {
        foreach ($list as $key=>$value){
            $list[$key]["bill_type"] = FLAG_BILL_NAMES[$value["bill_flag"]];
        }
    }

    private function getPageDataInfo($billFlag){
        $_filter["a.bill_flag"] = $billFlag;
        $_filter["a.branch_id"] = $this->_user_session->currBranchId;
        $this->_parseFilter($_filter);
        $page_no = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $count = M(CONTROLLER_NAME)->alias("a")->where($_filter)->count();
        $result["total"] = $count;
        $result["page_no"] = $page_no;
        $result["page_size"] = $page_size;
        $result["where"] = $_filter;
        return $result;
    }

    //未生成凭证的单据
    public function unCreateListAction(){
        $page_no = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $_filter["a.voucher_id"] = array("exp", "is null");
        $_filter['a.branch_id'] = $this->_user_session->currBranchId;
        $_filter['a.bill_flag'] = array("lt",FLAG_BILL_FARMPRODUCE);
        $this->_parseFilter($_filter);
        $count = M(CONTROLLER_NAME)->alias("a")->where($_filter)->count();
        $list = M(CONTROLLER_NAME)->alias("a")
            ->where($_filter)
            ->order("a.id desc")
            ->page($page_no, $page_size)
            ->select();
        foreach ($list as $key=>$value){
            $list[$key]["bill_type"] = FLAG_BILL_NAMES[$value["bill_flag"]];
        }
        $result["total"] = $count;
        $result["rows"] = $list;
        $this->ajaxReturn($result);
    }

    public function saleListAction(){
        $pageData = $this->getPageDataInfo(FLAG_BILL_TAX_INCOME);
        $pageData['field'] = strlen(I("post.q-accounting_section")) == 7 ? "a.*,sum(total_sum) as total_money":"a.*" ;
        $list = M(CONTROLLER_NAME)->alias("a")
            ->where($pageData["where"])
            ->order("a.id desc")
            ->page($pageData["page_no"], $pageData["page_size"])
            ->select();
        if ($list) {
            $goods_names = D(CONTROLLER_NAME)->getDetailGoodsNames(array_column($list, "id"));
            foreach ($list as $key => $value) {
                $list[$key]["detail"] = $goods_names[$value["id"]];
            }
        }
        $result['total_money'] = M(CONTROLLER_NAME)->alias("a")
            ->where($pageData["where"])->sum("a.total_sum");
        $result['tips'] = "";
        $result["footer"] = [["name"=>"含税合计总额：","total_sum"=>$result['total_money'] ?? 0 ]];
        $result["total"] = $pageData["total"];
        $result["rows"] = $list;
        if(strlen(I("post.q-accounting_section")) == 7){
            $result['tips'] = D(CONTROLLER_NAME)->getFreeAmountTips($this->_user_session->currBranchId,$pageData);
        }
        $this->ajaxReturn($result);
    }

    public function buyListAction(){
        $pageData = $this->getPageDataInfo(FLAG_BILL_TAX_PAY);
        $list = M(CONTROLLER_NAME)->alias("a")
            ->where($pageData["where"])
            ->order("a.id desc")
            ->page($pageData["page_no"], $pageData["page_size"])
            ->select();
        if ($list) {
            $goods_names = D(CONTROLLER_NAME)->getDetailGoodsNames(array_column($list, "id"));
            foreach ($list as $key => $value) {
                $list[$key]["detail"] = $goods_names[$value["id"]];
            }
        }
        $result["total"] = $pageData["total"];
        $result["rows"] = $list;
        $this->ajaxReturn($result);
    }

    public function bankListAction(){
        $pageData = $this->getPageDataInfo(FLAG_BILL_BANK);
        $list = M(CONTROLLER_NAME)->alias("a")
            //->join("left join vcr_bill as b on a.receipt_id = b.id")
            ->where($pageData["where"])
            ->order("a.id desc")
            //->field("a.*,b.memo as receipt_name")
            ->page($pageData["page_no"], $pageData["page_size"])
            ->select();
        if ($list) {
            $goods_names = D(CONTROLLER_NAME)->getDetailGoodsNames(array_column($list, "id"));
            foreach ($list as $key => $value) {
                $list[$key]["goods_name"] = $goods_names[$value["id"]];
                $list[$key]["bank_subject"] = getVoucherSubjectById($value['branch_id'],$value['bank_subject']);
            }
        }
        $result["total"] = $pageData["total"];
        $result["rows"] = $list;
        $this->ajaxReturn($result);
    }

    public function salaryListAction(){
        $pageData = $this->getPageDataInfo(FLAG_BILL_SALARY);
        $list = M(CONTROLLER_NAME)->alias("a")
            ->where($pageData["where"])
            ->order("a.id desc")
            ->page($pageData["page_no"], $pageData["page_size"])
            ->select();
        foreach ($list as $key=>$value){
            $list[$key]["fee_department_name"] = FEE_DEPARTMENTS[$value["fee_department"]];
        }
        $result["total"] = $pageData["total"];
        $result["rows"] = $list;
        $this->ajaxReturn($result);
    }
    public function detailAction($id = null) {
        define("__FORM_ACTION__", "update");
        $this->assignPermissions();
        $record = $this->_getDetailData($id);
        foreach (array_keys(FEE_DEPARTMENTS) as $key){
            $fee_departments[] = ["id"=>$key, "name"=>FEE_DEPARTMENTS[$key]];
        }
        $record["fee_departments"] = json_encode($fee_departments);
        $company_data = M("SysBranch")->field("ent_scale,tax_rate")->find($this->_user_session->currBranchId);
        //自开默认税率
        if ($record["bill_flag"] == FLAG_BILL_TAX_INCOME) {
            $record["income_taxrate"] = $company_data["tax_rate"];
        }
        $record["image_url"] = $this->getBillImage($record["image_id"]);
        $record["ent_scale"] = $company_data["ent_scale"];
        $record["items"] = json_encode($this->getItems($record["id"]));
        $this->assign("model", $record);
        $templateFile = $this->_get_detail_template($record);
        exit($this->fetch($templateFile));
    }

    private function getBillImage($image_id){
        $url = "";
        if($image_id) {
            $url =  M("SysDocument")->where("id=$image_id")->getField("path");
        }
        if (empty($url)){
            $url = ""; //default...
        }
        return $url;
    }
    protected function _get_detail_template($record) {
        switch ($record["bill_flag"]){
            case FLAG_BILL_TAX_INCOME:
                return "valuetax_edit_sale";
            case FLAG_BILL_TAX_PAY:
                return "valuetax_edit_buy";
            case FLAG_BILL_BANK:
                return "";
            case FLAG_BILL_SALARY:
                return "";
        }
    }

    private  function getInitialData($bill_flag){
        $model["bill_no"] = $this->getTableMaxBillNoByUserBranch();
        $model["bill_date"] = time();
        $model["bill_flag"] = $bill_flag;;
        $model["tax_type"] = 0;
        $company_data = M("SysBranch")->field("ent_scale,tax_rate")->find($this->_user_session->currBranchId);
        //自开默认税率
        if ($bill_flag == FLAG_BILL_TAX_INCOME) {
            $model["income_taxrate"] = $company_data["tax_rate"];
        }
        $model["ent_scale"] = $company_data["ent_scale"];
        $model["accounting_section"] = date("Y/m");
        $image_id = I("get.image_id", null);
        $model["image_id"] = $image_id;
        $model["image_url"] = M("SysDocument")->where("id=$image_id")->getField("path");
        return $model;
    }
    //自开票新增
    public function addSaleAction(){
        define("__FORM_ACTION__", "add");
        $model = $this->getInitialData(FLAG_BILL_TAX_INCOME);
        $model['tax_id'] = I("get.tax_id");
        if($model['tax_id']){
            $model["accounting_section"] = M("VcrBill")->where("id = ".$model['tax_id'])->getField("accounting_section");
        }
        $this->model = $model;
        $this->assignPermissions();
        $this->display("valuetax_edit_sale");
    }

    public function addBuyAction(){
        define("__FORM_ACTION__", "add");
        $model = $this->getInitialData(FLAG_BILL_TAX_PAY);
        foreach (array_keys(FEE_DEPARTMENTS) as $key){
            $fee_departments[] = ["id"=>$key, "name"=>FEE_DEPARTMENTS[$key]];
        }
        $model["fee_departments"] = json_encode($fee_departments);
        $model["tax_id"] = I("get.tax_id");
        if($model['tax_id']){
            $model["accounting_section"] = M("VcrBill")->where("id = ".$model['tax_id'])->getField("accounting_section");
        }
        $this->model = $model;
        $this->assignPermissions();
        $this->display("valuetax_edit_buy");
    }

    private function _import($file_key, $cmd){
        set_time_limit(0);
        if (!empty($_FILES)) {
            $uploader = getUploader("temp/", array('xls', 'xlsx'));
            $info = $uploader->uploadOne($_FILES[$file_key]);
            if (!$info) {
                $message = buildMessage($uploader->getError(), 1);
            } else {
                $filePath = ltrim($uploader->rootPath, ".") . $info['savepath'] . $info['savename'];
                $client_file = $_FILES[$file_key]["name"];
                switch ($cmd){
                    case "importInvoice":
                        $message = D("VcrBillValueTax")->importInvoice($filePath, $client_file);
                        break;
                    case "importBankAccount":
                        $message = D("VcrBillBankAccount")->import($filePath, $client_file);
                        break;
                    case "importSalaryTable":
                        $message = D("VcrBillSalary")->import($filePath, $client_file);
                        break;
                    default:
                        E("无效导入命令！");
                }

                unset($uploader);
            }
            if ($message["code"] > 0 ){
                $errmsg = sprintf("用户编号：%s（%s）导入失败!错误原因：%s，文件路径：%s",
                    $this->_user_session->userId,
                    $this->_user_session->currBranchName,
                    $message["message"],
                    WEB_ROOT."/".$filePath);
                D('ESAdmin/SysMq')->add_timer(100, WEB_ROOT . "/ReqQueue/sendMail/message/".base64_encode($errmsg));
            }else{
                $message["file_name"] = $client_file;
            }
            $this->responseJSON($message);
        } else {
            $this->responseJSON(buildMessage("文件不能为空！", 1));
        }
    }
    //导入金穗自开发票
    public function importInvoiceAction($file_key) {
        $this->_import($file_key, "importInvoice");
    }

    //删除自开票文件对应的记录
    public function removeAttachmentAction($attachment_id){
        if (D("VcrBillValueTax")->removeAttachment($this->_user_session->currBranchId, $attachment_id)){
            $this->responseJSON(buildMessage("删除成功！"));
        }else{
            $this->responseJSON(buildMessage("删除错误！", 1));
        }
    }

    public function importBankAccountAction($file_key) {
        $this->_import($file_key, "importBankAccount");
    }

    public function importSalaryTableAction($file_key){
        $this->_import($file_key, "importSalaryTable");
    }

    /**企业规模
     * @return mixed
     */
    protected function getCompanyScale(){
        $result = D("ComCompany")->getCompanyScale($this->_user_session->currBranchId);
        return $result;
    }
    protected function _before_write($type, &$data) {
        //获取当前用户最大单号
        if (self::ACTION_ADD === $type || self::ACTION_COPY === $type) {
            $data["bill_no"] = $this->getTableMaxBillNoByUserBranch();
            if (self::ACTION_ADD == $type) {      //新增状态下，会计区间取上次的
                $_SESSION["accounting_section"] = $data["accounting_section"];
            }
            //新增时设置来源
            if($this->_user_session->userType != USER_TYPE_COMPANY_MANAGER){
                $bill_data['origin'] = self::BILL_ORIGIN_CUSTOMER;
            }else{
                $bill_data['origin'] = self::BILL_ORIGIN_BRANCH;
            }
        }
        //外取票更新时未勾选现金已付时
        if($data['bill_flag'] == FLAG_BILL_TAX_PAY && !$data['cashpayed']){
            $data['cashpayed'] = 0;
        }
        parent::_before_write($type, $data);
    }


    protected function getItems($id){
        $items = M("VcrBillDetail")->where("parent_id=$id")->select();
        return $items;
    }

    //应收应付客户
    public function getPayReceiveKeyNamesAction(){
        $result = D("VcrSubject")->getPayReceiveKeyNames($this->_user_session->currBranchId);
        $this->ajaxReturn($this->assignQueryKey($result));
    }

    public function getBankKeyNamesAction(){
        $result = D("VcrSubject")->getBankKeyNames($this->_user_session->currBranchId);
        $this->ajaxReturn($this->assignQueryKey($result));
    }

    public function getGoodsNamesAction($bill_flag){
        $result = D(CONTROLLER_NAME)->getGoodsNames($this->_user_session->currBranchId, $bill_flag);
        $this->ajaxReturn($this->assignQueryKey($result));
    }

    public function getUnitNamesAction(){
        $result = D(CONTROLLER_NAME)->getUnitNames($this->_user_session->currBranchId);
        $this->ajaxReturn($this->assignQueryKey($result));
    }

    private function assignQueryKey(&$result){
        foreach ($result as $key => $value) {
            $result[$key]["querykey"] = firstPinyin($value["name"]);
        }
        return $result;
    }
    public function getAutoCompleteDatasAction($include = 1, $bill_flag = null) {
        $result = array();
        $include = intval($include);
        //应收应付
        if (($include & 1) == 1){
            $result["names"] = D("VcrSubject")->getPayReceiveKeyNames($this->_user_session->currBranchId);
        }
        //银行存款
        if (($include & 2) == 2){
            $result["bank_names"] = D("VcrSubject")->getBankKeyNames($this->_user_session->currBranchId);
        }
        //服务、商品名称
        if (($include & 4) == 4){
            $result["goods_names"] = D(CONTROLLER_NAME)->getGoodsNames($this->_user_session->currBranchId, $bill_flag);
        }
        //计量单位
        if (($include & 8) == 8){
            $result["unit_names"] = D(CONTROLLER_NAME)->getUnitNames($this->_user_session->currBranchId);
        }
        foreach ($result as $key => $value) {
            foreach ($result[$key] as $itemkey=>$item){
                $result[$key][$itemkey]["querykey"] = firstPinyin($item["name"]);
            }

        }
        $this->responseJSON(buildResult($result));
    }

    public function getFeeAutoCompleteDatasAction(){
        $result =  getAllFeeKeys();
        $this->responseJSON(buildResult($result));
    }

    public function downloadSalaryAction()
    {
        $filename = MODULE_PATH . "/Template/salary.xlsx";
        \Org\Net\Http::download($filename, "工资册模板");
    }

    public function getImageTreeAction(){
        //$condition["target_branch_id"] = 102;//$this->_user_session->currBranchId;
        $condition["target_branch"] = $this->_user_session->currBranchId;
        $condition["is_valid"] = 1;
        $orCondition["format"] = array("like", "image%");
        $orCondition["type"] = 0;
        $orCondition["_logic"] = "OR";
        $condition["_complex"] = $orCondition;
        $root = array("id"=>-1, "text"=>$this->_user_session->currBranchName, "parent_id"=>0,"path"=>"");
        $list = M("SysDocument")
            ->field("id,parent_id,name as text,path")
            ->where($condition)
            ->order("parent_id")
            ->select();
        if ($list){
            foreach ($list as $key=>$value){
                if ($value["parent_id"] == 0) {
                    $list[$key]["parent_id"] = -1;
                }
            }
            array_unshift($list, $root);
        }else{
            $list = array($root);
        }
        $tree = list_to_tree($list);
        $this->ajaxReturn($tree);
    }

    public function setImageAction($image_id, $model_id){
        $model = D(CONTROLLER_NAME);
        if (!$model->checkDataPermission($model_id)) {
            $this->responseJSON(buildMessage("保存失败：您没有权限更新此记录！", 1));
        }
        $model->where("id=$model_id")->setField("image_id", $image_id);
        $data = $model->where("id=$model_id")->find();
        $this->ajaxReturn(buildResult($data));
    }

    public function searchBillAction(){
        $query = I("q");
        $_filter = [];
        if ($query) {
            $_filter['a.bill_no'] = array("like", "$query%");
        }
        $_filter['a.bill_flag'] = array("lt",FLAG_BILL_FARMPRODUCE);
        //$_filter['a.voucher_id'] = array("exp","IS NULL");
        $list = D(CONTROLLER_NAME)->setDacFilter("a")->field("a.bill_no as name,a.id")->where($_filter)->select();
        $this->ajaxReturn($list);
    }

    //获取已导入过的文件
    public function getImportedFileAction($bill_flag){
        //$pageData = $this->getPageDataInfo(FLAG_BILL_BANK);
        $pageData = $this->getPageDataInfo($bill_flag);
        $pageData["where"]["b.branch_id"] = $this->_user_session->currBranchId;
        $accounting = I('cookie.mon_' . $bill_flag, I('cookie.year_' . $bill_flag));
        $pageData['where']['b.accounting_section'] = ['like', '%' . $accounting . '%'];
        $list = M(CONTROLLER_NAME)->alias("a")
            ->where($pageData["where"])
            ->join("vcr_bill_attachment b on a.attachment_id = b.id")
            ->field("distinct a.attachment_id,b.id,b.file_name as name")
            ->select();

        $this->ajaxReturn($list);
    }

    public function bankTotalAction($year){
        $year = intval($year);
        $end  = $year + 1;
        $branchId = $this->_user_session->currBranchId;
        $sql = "SELECT bill_flag, count(substring(accounting_section,1,7)) AS num, accounting_section FROM vcr_bill WHERE branch_id = {$branchId}  AND accounting_section > '{$year}'  AND accounting_section < '{$end}' GROUP  BY accounting_section, bill_flag ORDER BY accounting_section;";
        $list = M()->query($sql);

        $data = [];
        foreach($list as $item){
            $data[$item['bill_flag']][] = $item;
        }
        sort($data);

        return $this->ajaxReturn($data);
    }

    //银行回单
    public function receiptListAction(){
        $pageData = $this->getPageDataInfo(FLAG_BILL_RECEIPT);
        if(I("get.type") == 1){
            $accounting_section = M("VcrBill")->where("id = ".I("get.id"))->getField("accounting_section");
            $pageData["where"]['a.accounting_section'] = $accounting_section;
            //$pageData["where"]['b.receipt_id'] = array("exp","IS NULL");
            $pageData["total"] = M(CONTROLLER_NAME)->alias("a")
                ->join("left join vcr_bill as b on a.id = b.receipt_id")
                ->where($pageData['where'])->count();
        }
        $list = M(CONTROLLER_NAME)->alias("a")
            ->join("left join vcr_bill as b on a.id = b.receipt_id")
            ->where($pageData["where"])
            ->order("a.id desc")
            ->field("a.*,b.id as is_link_bank")
            ->page($pageData["page_no"], $pageData["page_size"])
            ->select();
        $result["total"] = $pageData["total"];
        $result["rows"] = $list;
        $this->ajaxReturn($result);
    }

    //单证资料会计区间总条数,$bill_flag单证资料类型
    public function billTotalAction($year,$bill_flag,$source_type = null){
        $result = [];
        $condition['accounting_section'] = array(array("gt","$year"),array("lt",$year+1));
        $condition['branch_id'] = $this->_user_session->currBranchId;
        $condition['bill_flag'] = $bill_flag;
        if($source_type){
            $condition['source_flag'] = $source_type == FLAG_BILL_TAX_INCOME ? FLAG_SOURCE_INCOME : FLAG_SOURCE_PAY;
        }
        $bill_count = M("VcrBill")
            ->where($condition)
            ->field("count(accounting_section) as num,substring(accounting_section,1,7) as accounting_section")
            ->order("accounting_section")->group("accounting_section")->select();
        for($i = 1; $i<13; $i++){
            //会计区间
            $result[$i]['accounting_section'] = $i < 10 ?  $year."/0$i" : "$year/$i";
            $key = array_search($result[$i]['accounting_section'],array_column($bill_count,"accounting_section"));
            $result[$i]['num'] = $key !== false ? $bill_count[$key]['num'] : 0;
        }
        $this->ajaxReturn($result);
    }

    //银行回单新增、编辑
    public function editReceiptAction(){
        if(IS_POST){
            $model = M("VcrBill");
            try{
                $model->startTrans();
                //处理文件上传并添加到客户文件单证资料文件夹中
                $image_id = $this->addBillImage(FLAG_BILL_RECEIPT);
                //单证资料处理
                if(I("post.id")){
                    M("VcrBill")->where("receipt_id = ".I("post.id"))->setField("image_id",$image_id);
                }
                $result = $this->addBillReceipt($model,$image_id,FLAG_BILL_RECEIPT);
                $model->commit();
                if($result){
                    $this->ajaxReturn(buildMessage("保存成功！"));
                }else{
                    $this->ajaxReturn(buildMessage("保存失败，请稍后重试！",1));
                }
            }catch(\Think\Exception $ex){
                $model->rollback();
                $this->responseJSON(buildMessage("保存失败：" . $ex->getMessage(), 1));
            }
        }else{
            $id = I('get.id');
            if($id > 0){
                $data = M("VcrBill a")
                    ->join("left join sys_document b on a.image_id = b.id")
                    ->where("a.id = $id")
                    ->field("a.*,concat(b.name,'.',b.ext) as filename , b.path")
                    ->find();
                $this->assign("model",$data);
                //查看关联回单时删除关联按钮
                if(I("get.type") == 1){
                    $this->assign("show_delete_link",1);
                }
            }
            $this->display("add_receipt");
        }
    }

    //处理文件上传并添加到客户文件单证资料文件夹中
    public function addBillImage($bill_flag){
        $files = reset($_FILES);
        $image_id = I("post.image_id");//图片文件id
        if($image_id){
            $image_id = M("SysDocument")->where("id = $image_id")->getField("id");
        }
        if(empty($files['name']) && empty($image_id)){
            $image_id = 0;
            $this->ajaxReturn(buildMessage("请上传文件",1));
        }elseif(!empty($files['name'])){
            $upload = new UploadController();
            $info = $upload->documentUpload();
            if ($info) {
                $data['path'] = $info['file']['url'];
                $data['size'] = $info['file']['size'];
                $data['format'] = $info['file']['type'];
                $data['ext'] = $info['file']['ext'];
            } else {
                $this->ajaxReturn(buildMessage('上传文件失败',1));
            }
            $data['name'] = I("post.file_name");
            //创建单证资料文件夹，已有则直接返回文件夹id
            $data['parent_id'] = D(CONTROLLER_NAME)->createBillFolder($this->_user_session->currBranchId,$bill_flag);
            $data['updated_at'] = time();
            $data['created_at'] = time();
            $data['branch_id'] = getBrowseBranchId();
            $data['type'] = 1;
            $data['user_id'] = $this->_user_session->userId;
            $data['target_branch'] = $this->_user_session->currBranchId;
            $data['share'] = 0;
            $image_id = M("SysDocument")->add($data);
        }elseif (!empty($image_id) && empty($files['name'])){
            $folder_id = D(CONTROLLER_NAME)->createBillFolder($this->_user_session->currBranchId,$bill_flag);
            M("SysDocument")->where("id = $image_id")->save(["parent_id"=>$folder_id]);
        }
        if(!$image_id){
            $this->ajaxReturn(buildMessage("文件处理失败，请稍后重试！",1));
        }
        return $image_id;
    }

    //处理增加银行回单
    public function addBillReceipt($model,$image_id,$bill_flag){
        $bill_data = I("post.");
        $bill_data['accounting_section'] = I("post.accounting-section-year")."/".I("post.accounting-section-month");
        $bill_data['bill_flag'] = $bill_flag;
        $bill_data['bill_date'] = time();
        $bill_data['source_date'] = strtotime($bill_data['source_date']);
        $bill_data['branch_id'] = $this->_user_session->currBranchId;
        $bill_data['user_id'] = $this->_user_session->userId;
        $bill_data['creator'] = empty($this->_user_session->staffName) ? $this->_user_session->userName : $this->_user_session->staffName;
        $bill_data['image_id'] = $image_id;
        $bill_data['total_amount'] = $bill_data['total_sum'];
        if($bill_data['id']){
            $result = $model->save($bill_data);
        }else{
            //新增时设置来源
            if($this->_user_session->userType != USER_TYPE_COMPANY_MANAGER){
                $bill_data['origin'] = self::BILL_ORIGIN_CUSTOMER;
            }else{
                $bill_data['origin'] = self::BILL_ORIGIN_BRANCH;
            }
            $result = $model->add($bill_data);
        }
        return $result;
    }

    //删除银行回单
    public function deleteReceiptAction($id){
        if(!$id){
            $this->ajaxReturn(buildMessage("请选择删除项",1));
        }
        $model = D(CONTROLLER_NAME);
        if (!$model->checkDataPermission($id)) {
            $this->responseJSON(buildMessage("删除失败：您没有权限删除此记录！", 1));
        }
        try{
            $model->startTrans();
            $image_id = $model->where("id = $id")->getField("image_id");
            if($image_id){
                M("SysDocument")->where("id = $image_id")->save(["deleted_at"=>time(),"is_valid"=>0]);
            }
            $model->where("receipt_id = $id")->save(array("receipt_id"=>null,"image_id"=>null));
            $result = $model->where("id = $id")->delete();
        }catch(\Think\Exception $ex){
            $result = false;
            $this->ajaxReturn(buildMessage("删除失败",1));
        }
        if($result){
            $this->addLog($id);
            $model->commit();
            $this->ajaxReturn(buildMessage("删除成功"));
        }else{
            $this->ajaxReturn(buildMessage("删除失败",1));
        }
    }

    //对账单关联回单
    public function linkBillAction(){
        if(IS_GET){
            $this->assign("bill_id",I("get.id"));
            $accounting_section = M("VcrBill")->where("id = ".I("get.id"))->getField("accounting_section");
            $this->assign("accounting_section",$accounting_section);
            $this->display("bank_statement");
        }else{
            $bill_id = I("post.bill_id");
            $receipt_id = I("post.receipt_id");
            $tax_id = I("post.tax_id");
            $type = I("post.type");
            $save_data = [];
            if($type == 'receipt'){
                $count = M("VcrBill")->where("receipt_id = $receipt_id")->count();
                $error_msg = "该银行回单已被关联！";
                $save_data["receipt_id"] = $receipt_id;
            }else{
                $count = M("VcrBill")->where("tax_id = $tax_id")->count();
                $error_msg = "该发票已被关联！";
                $save_data["tax_id"] = $tax_id;
            }
            if($count){
                $this->ajaxReturn(array("error"=>1,"message"=>"关联失败，$error_msg"));
            }
            $save_data["image_id"] = I("post.image_id");
            $result = M("VcrBill")->where("id = $bill_id")->save($save_data);
            if($result){
                $this->ajaxReturn(array("error"=>0,"message"=>"关联成功！"));
            }else{
                $this->ajaxReturn(array("error"=>1,"message"=>"关联失败！"));
            }
        }
    }

    //关联回单时展示会计区间的数量
    public function receiptTotalAction($year){
        $result = [];
        $condition['a.accounting_section'] = array(array("gt","$year"),array("lt",$year+1));
        $condition['a.branch_id'] = $this->_user_session->currBranchId;
        $condition['a.bill_flag'] = FLAG_BILL_RECEIPT;
        $condition['b.receipt_id'] = array("exp","IS NULL");
        $bill_count = M("VcrBill a")
            ->join("left join vcr_bill b on a.id = b.receipt_id")
            ->where($condition)
            ->field("count(a.accounting_section) as num,substring(a.accounting_section,1,7) as accounting_section")
            ->order("a.accounting_section")->group("a.accounting_section")->select();
        for($i = 1; $i<13; $i++){
            //会计区间
            $result[$i]['accounting_section'] = $i < 10 ?  $year."/0$i" : "$year/$i";
            $key = array_search($result[$i]['accounting_section'],array_column($bill_count,"accounting_section"));
            $result[$i]['num'] = $key !== false ? $bill_count[$key]['num'] : 0;
        }
        $this->ajaxReturn($result);
    }

    //删除银行回单的关联
    public function deleteLinkAction(){
        $receipt_id = I("post.receipt_id");//银行回单
        $tax_id = I("post.tax_id");//发票
        $type = I("post.type");
        if(!$receipt_id && !$tax_id){
            $this->ajaxReturn(buildMessage("操作失败！",1));
        }
        $save_data = [];
        $save_data['image_id'] = "";
        $condition['branch_id'] = $this->_user_session->currBranchId;
        if($type == "receipt"){
            $condition['bill_flag'] = FLAG_BILL_BANK;//对账单
            $condition['receipt_id'] = $receipt_id;
            $save_data['receipt_id'] = "";
        }else{
            $condition['bill_flag'] = array(FLAG_BILL_TAX_PAY,FLAG_BILL_TAX_INCOME,"or");
            $condition['tax_id'] = $tax_id;
            $save_data['tax_id'] = "";
        }
        $result = M("VcrBill")->where($condition)->save($save_data);
        if($result){
            $this->ajaxReturn(buildMessage("操作成功！"));
        }else{
            $this->ajaxReturn(buildMessage("操作失败！",1));
        }
    }

    //发票新增、编辑
    public function editTaxAction(){
        if(IS_POST){
            $model = M("VcrBill");
            try{
                $model->startTrans();
                //根据source_flag判断收入或支出，收入为自开票，支出为外取票
                $bill_flag = I("post.source_flag") == FLAG_SOURCE_INCOME ? FLAG_BILL_TAX_INCOME : FLAG_BILL_TAX_PAY;
                //处理文件上传并添加到客户文件单证资料文件夹中
                $image_id = $this->addBillImage($bill_flag);
                if(I("post.id")){
                    $model->where("tax_id = ".I("post.id"))->setField("image_id",$image_id);
                }
                //单证资料处理
                $result = $this->addBillReceipt($model,$image_id,FLAG_BILL_TAX);
                $model->commit();
                if($result){
                    $this->ajaxReturn(buildMessage("保存成功！"));
                }else{
                    $this->ajaxReturn(buildMessage("保存失败，请稍后重试！",1));
                }
            }catch(\Think\Exception $ex){
                $model->rollback();
                $this->responseJSON(buildMessage("保存失败：" . $ex->getMessage(), 1));
            }
        }else{
            $id = I('get.id');
            if($id > 0){
                $data = M("VcrBill a")
                    ->join("left join sys_document b on a.image_id = b.id")
                    ->where("a.id = $id")
                    ->field("a.*,concat(b.name,'.',b.ext) as filename , b.path")
                    ->find();
                $this->assign("model",$data);
                //查看关联回单时删除关联按钮
                if(I("get.type") == 1){
                    $this->assign("show_delete_link",1);
                }
            }
            $this->display("add_tax");
        }
    }

    //上传发票列表
    public function taxListAction(){
        $pageData = $this->getPageDataInfo(FLAG_BILL_TAX);
        $bill_id = I("get.id");
        $type = I("get.type");
        if($bill_id == 0 && $type == FLAG_BILL_TAX_INCOME){//自开票
            $pageData["where"]['a.source_flag'] = FLAG_SOURCE_INCOME;
        }elseif($bill_id == 0 && $type == FLAG_BILL_TAX_PAY){//外取票
            $pageData["where"]['a.source_flag'] = FLAG_SOURCE_PAY;
        } elseif(I("get.id")){
            //自开票为收入，外取票为支出
            $bill_flag = M("VcrBill")->where("id = " . I("get.id"))->getField("bill_flag");
            $pageData["where"]['a.source_flag'] = $bill_flag == FLAG_BILL_TAX_INCOME ? FLAG_SOURCE_INCOME : FLAG_SOURCE_PAY;
            $pageData["total"] = M(CONTROLLER_NAME)->alias("a")
                //->join("left join vcr_bill as b on a.id = b.tax_id")
                ->where($pageData['where'])->count();
        }
        $list = M(CONTROLLER_NAME)->alias("a")
            ->join("left join vcr_bill as b on a.id = b.tax_id")
            ->where($pageData["where"])
            ->order("a.id desc")
            ->field("a.*,b.id as is_link_bill")
            ->page($pageData["page_no"], $pageData["page_size"])
            ->select();
        $result["total"] = $pageData["total"];
        $result["rows"] = $list;
        $this->ajaxReturn($result);
    }

    //删除上传发票
    public function deleteTaxAction($id){
        if(!$id){
            $this->ajaxReturn(buildMessage("请选择删除项",1));
        }
        $model = D(CONTROLLER_NAME);
        if (!$model->checkDataPermission($id)) {
            $this->responseJSON(buildMessage("删除失败：您没有权限删除此记录！", 1));
        }
        try{
            $model->startTrans();
            $image_id = $model->where("id = $id")->getField("image_id");
            if($image_id){
                M("SysDocument")->where("id = $image_id")->save(["deleted_at"=>time(),"is_valid"=>0]);
            }
            $model->where("tax_id = $id")->save(array("tax_id"=>null,"image_id"=>null));
            $result = $model->where("id = $id")->delete();
        }catch(\Think\Exception $ex){
            $result = false;
            $this->ajaxReturn(buildMessage("删除失败",1));
        }
        if($result){
            $this->addLog($id);
            $model->commit();
            $this->ajaxReturn(buildMessage("删除成功"));
        }else{
            $this->ajaxReturn(buildMessage("删除失败",1));
        }
    }

    //关联发票选择发票
    public function selectTaxAction(){
        $this->assign("bill_id",I("get.id"));
        $this->assign("type",I("get.type"));
        $this->display("select_tax");
    }

    public function taxCountAction($year,$source_type = null){
        $result = [];
        $condition['a.bill_flag'] = FLAG_BILL_TAX;
        $condition['a.branch_id'] = $this->_user_session->currBranchId;
        $condition['a.accounting_section'] = array(array("gt","$year"),array("lt",$year+1));
        if($source_type){
            $condition['a.source_flag'] = $source_type == FLAG_BILL_TAX_INCOME ? FLAG_SOURCE_INCOME : FLAG_SOURCE_PAY;
        }
        $tax_count = M("VcrBill a")
            ->join("left join vcr_bill b on a.id = b.tax_id")
            ->where($condition)
            ->field("count(a.accounting_section) as num,substring(a.accounting_section,1,7) as accounting_section,count(b.tax_id) as selected_count")
            ->order("a.accounting_section")->group("a.accounting_section")->select();
        for($i = 1; $i<13; $i++){
            //会计区间
            $result[$i]['accounting_section'] = $i < 10 ?  $year."/0$i" : "$year/$i";
            $key = array_search($result[$i]['accounting_section'],array_column($tax_count,"accounting_section"));
            $result[$i]['num'] = $key !== false ? $tax_count[$key]['num'] : 0;
            $result[$i]['unselected_count'] = $key !== false ? $result[$i]['num'] - $tax_count[$key]['selected_count'] : 0;
        }
        $this->ajaxReturn($result);
    }

}
