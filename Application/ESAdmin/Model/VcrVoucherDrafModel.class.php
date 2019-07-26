<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class VcrVoucherDrafModel extends DataModel {

    /**
     * 
     * @param type $options
     * @return boolean
     */
    protected function _before_delete($options) {
        parent::_before_delete($options);
        if ($options["where"]) {
            $datalist = $this->query("select id,reviewed from vcr_voucher_draf where " . $this->parseWhere($options["where"]));
            if ($datalist) {
                if ($datalist[0]["reviewed"] > 0) {
                    $this->error = "已经复核无法删除";
                    E("已经复核无法删除");
                    return false;
                }
                $id_list = array();
                foreach ($datalist as $value) {
                    $id_list[] = $value["id"];
                }
                $id_strings = implode(",", $id_list);
                $sql = sprintf("delete from vcr_voucher_draf_detail where parent_id in (%s)", $id_strings);
                $this->execute($sql);
                $sql = sprintf("update vcr_bill set voucher_id=null where voucher_id in(%s)", $id_strings);
                $this->execute($sql);
            }
        }
        return true;
    }

    private function insertDrafDetail($parent_data){
        $datalist = $this->getDrafDetail($parent_data);
        if ($datalist){
            M("VcrVoucherDrafDetail")->addAll($datalist);
        }
    }

    public function getDrafDetail($parent_data){
        $subject_ids = I("post.subject_id");
        $summarys = I("post.summary");
        $debit_amounts = I("post.debit_amount");
        $credit_amounts = I("post.credit_amount");
        $datalist = array();
        foreach ($subject_ids as $key=>$subject_id){
            $data["parent_id"] = $parent_data["id"];
            $data["subject_id"] = $subject_id;
            $data["direction"] = floatval($credit_amounts[$key])>0?1:0;
            $data["amount"] = floatval($debit_amounts[$key]) + floatval($credit_amounts[$key]);
            $data["summary"] = $summarys[$key];
            $data["row_no"] = $key + 1;
            $datalist[] = $data;
        }
        return $datalist;
    }

    protected function _after_insert($data, $options) {
        parent::_after_insert($data, $options);
        $this->addDetailChangeLog($data);
        $this->insertDrafDetail($data);
        $source_id = I('post.source_id');
        if($source_id){
            M("VcrBill")->where("id = $source_id")->setField("voucher_id",$data['id']);
        }
    }

    protected function _after_update($data, $options) {
        $this->addDetailChangeLog($data);
        M("VcrVoucherDrafDetail")->where(array("parent_id" => $data["id"]))->delete();
        $this->insertDrafDetail($data);
    }

    //获取凭证详情修改的变化并添加修改日志
    public function addDetailChangeLog($data,$new_data = null){
        if($data['id']){
            if($new_data){
                $old_data = [];
            }else{
                $old_data = M("VcrVoucherDrafDetail")->where(array("parent_id" => $data["id"]))->select();
                $new_data = $this->getDrafDetail($data);
            }
            $content = [];
            $validate_field = ["摘要"=>"summary","会计科目"=>"subject_id","金额"=>"amount","借贷方向"=>"direction"];
            foreach($new_data as $k=>$v) {
                foreach ($validate_field as $field) {
                    $content[] = $this->getChangeContent($old_data[$k][$field], $new_data[$k][$field], array_search($field, $validate_field), $new_data[$k]['row_no']);
                }
            }
            if(count($new_data) < count($old_data)){
                for($i = count($new_data);$i<count($old_data);$i++) {
                    foreach ($validate_field as $field) {
                        $content[] = $this->getChangeContent($old_data[$i][$field], $new_data[$i][$field], array_search($field, $validate_field), $old_data[$i]['row_no']);
                    }
                }
            }
            $content = array_values(array_filter($content));
            if($content){
                M("VcrVoucherDraf")->where("id = ".$data['id'])->setField("reviewed",0);
                $this->addVoucherLog($content,$data['id']);
            }
        }
    }

    public function addVoucherLog($content,$voucher_id){
        if($content && $voucher_id){
            $log['content'] = json_encode($content);
            $log['log_time'] = time();
            $user_session = $_SESSION[USER_SESSION_KEY];
            $log['branch_id'] = $user_session->currBranchId;
            $log['creater_id'] = $user_session->userId;
            $log['user_name'] = $user_session->staffName ?? $user_session->userName ;
            $log['voucher_id'] = $voucher_id;
            M("VcrVoucherLog")->add($log);
        }
    }


    public function getChangeContent($str1,$str2,$field,$row){
        $direction = [0=>"借方",1=>"贷方"];
        if($field == "借贷方向"){
            $str1 = $direction[$str1];
            $str2 = $direction[$str2];
        }elseif($field == "会计科目" && $str1 != $str2){
            $str1 = $this->getSubjectFullName($str1);
            $str2 = $this->getSubjectFullName($str2);
        }
        $content = sprintf("第%d行%s由<%s>修改为<%s>",$row,$field,$str1,$str2);
        return $str1 == $str2 ? null : $content;
    }

    public function getSubjectFullName($id){
        if($id){
            $result = D("VcrSubject a")
                ->join("left join vcr_subject b on a.parent_id = b.id")
                ->where("a.id = $id")
                ->field("concat(a.no,':',IFNULL(concat(b.name,'—'),''),a.name) as full_name")
                ->find();
            return $result['full_name'];
        }
        return "";
    }

    /**
     * 生成凭证草稿
     * @param type $accounting_section  会计期间
     * @param type $branch_id  客户编号
     * source_flag：FLAG_SOURCE_PAY=0：外开，FLAG_SOURCE_INCOME=1：自开
     * name: 销售/购买方,外开时为销售方，自开时为购买方
     * bill_flag:发票类型，
     * 包含：FLAG_BILL_VALUETAX = 0：增值税发票；
     *      FLAG_BILL_SALARY = 1; //工资相关；
     *      FLAG_BILL_OTHER = 2; //其他票类，包括交通、汽油、车票等定额发票；
     *      FLAG_BILL_BANK = 3; //银行--社保税收；
     *      FLAG_BILL_FARMPRODUCE = 4; //农产品收购
     * goods_name：商品、服务名称
     * amount：未税金额
     * tax_amount：税额
     * tax_type：0：非专用发票，1专用发票
     * is_fee：是否费用
     * fee_department：
     *  "manage"=管理部门
     *  "sales"=销售部门
     *  "finance"=财务部门
     *  "production"=生产部门
     *  "project"=项目部门
     * accounting_section：会计期间
     */
    public function createDraf($user_session, $accounting_section) {
        $branch_id = $user_session->currBranchId;
        $this->saveConfig($branch_id); //保存配置
        $company_data = D("ComCompany")->alias("a")->field("a.*")->where("a.id=$branch_id")->find();
        if ($company_data) {
            $company_data["creator"] = empty($user_session->staffName) ? $user_session->userName : $user_session->staffName;
            $error_messages = array();
            $total_count = 0;//单据条数
            $success_count = 0;//成功生成条数
            $this->startTrans();
            try {
                //$this->deleteCurrentDraf($branch_id);
                //自开
                $error_messages[] = D("VcrBillValueTax")->processOutPutBill($company_data,$accounting_section);
                //非费用应付账款
                $error_messages[] = D("VcrBillValueTax")->processPurchaseBill($company_data,$accounting_section);
                //费用类
                $error_messages[] = D("VcrBillValueTax")->processFeeBill($company_data,$accounting_section);
                //工资类
                $error_messages[] = D("VcrBillSalary")->processBill($company_data,$accounting_section);
                //银行类
                $error_messages[] = D("VcrBillBankAccount")->processBill($company_data,$accounting_section);
                foreach ($error_messages as $k=>$message){
                    $total_count += $message['total_count'];
                    $success_count += $message['success_count'];
                    $error_tmp = array_filter(explode("<br>",$message['error_message']));
                    foreach ($error_tmp as $v){
                        array_push($error_messages,$v);
                    }
                    unset($error_messages[$k]);
                }
                $this->commit();
            } catch (\Exception $ex) {
                $this->rollback();
                return buildMessage($ex->getMessage(), 1);
            }
            $error_messages = array_unique(array_filter($error_messages));
            if(count($error_messages) > 10){
                $msg = "等共计".count($error_messages)."条错误";
                $error_messages = array_slice($error_messages,0,9);
                array_push($error_messages,$msg);
            }
            if ($error_messages){
                return buildMessage($accounting_section."期间共".$total_count."条，成功生成".$success_count."条，请前往已完成列表查看修正，其中错误：<br>".implode("<br>", $error_messages), 2);
            }else{
                return buildMessage($accounting_section."期间共".$total_count."条，成功生成".$success_count."条，请前往已完成列表查看修正！");
            }
        } else {
            return buildMessage("客户不存在！", 1);
        }
    }

    //保存配置
    private function saveConfig($branch_id){
        $cfgData["vcr_draf_prompt"] = I("post.draf_prompt"); //保存设置;
        if ($cfgData["vcr_draf_prompt"]) {
            $cfgData["vcr_merge_fee"] = I("post.merge_fee");
            $cfgData["vcr_merge_payable"] = I("post.merge_payable");
            $cfgData["vcr_merge_receivable"] = I("post.merge_receivable");
            $VcrConfigModel = M("VcrConfig");
            $condition["branch_id"] = $branch_id;
            if ($VcrConfigModel->where($condition)->count() == 0) {
                $cfgData["branch_id"] = $branch_id;
                $VcrConfigModel->add($cfgData);
            } else {
                $VcrConfigModel->where($condition)->save($cfgData);
            }
        }
    }

    /* 删除未审核的草稿 */

    private function deleteCurrentDraf($branch_id) {
        $bill_flag = I("post.bill_flag", null); //需要处理的单证类型
        $accounting_section = I("post.accounting_section", null);
        $id = I("post.id", null); //多选   ,多选的话，就不会有 accounting_section，反之，选某种类型的话，就不会有ID    
        $sql = "select a.voucher_id,a.bill_id from vcr_voucher_relation a inner join vcr_bill b on a.bill_id=b.id where b.branch_id=$branch_id";
        if (isset($id)) {
            if (is_array($id)) {
                $sql = $sql . sprintf(" and b.id in (%s)", implode(",", $id));
            } else {
                $sql = $sql . " and b.id = $id";
            }
        }else{
            if (isset($bill_flag)) {
                $sql = $sql . " and b.bill_flag=$bill_flag";
            }
            if (isset($accounting_section)) {
                $sql = $sql . " and b.accounting_section='$accounting_section'";
            }
        }
        $columns = array();
        $relations = $this->query($sql);
        if ($relations) {
            foreach ($relations as $relation) {
                $columns["bill_id"][$relation["bill_id"]] = 1;
                $columns["voucher_id"][$relation["voucher_id"]] = 1;
            }
            $sql = sprintf("delete from vcr_voucher_draf  where id in (%s)", implode(",", array_keys($columns["voucher_id"])));
            $this->execute($sql);
            $sql = sprintf("delete from  vcr_voucher_draf_detail where parent_id in (%s)", implode(",", array_keys($columns["voucher_id"])));
            $this->execute($sql);
            $sql = sprintf("update vcr_bill set voucher_id=null where id in (%s)", implode(",", array_keys($columns["bill_id"])));
            $this->execute($sql);
        }
    }

    /**
     * 外开(进项)发票处理
     * @param type $customer_data 客户资料
     * @param array $condition  过滤基本条件
     */
    private function processInPutBill($company_data, $accounting_section, $branch_id) {
        
    }

    public function getDetails($draf_array) {
        $draf_ids = implode(",", $draf_array);
        $result = array();
        $field = "a.*,a.subject_name as _subject_name,b.no as subject_no, IF(ISNULL(bp.name), b.name, concat(bp.name,'—',b.name)) as subject_name,
                concat(b.no,':',IFNULL(concat(bp.name,'—'),''),b.name) as full_name,
                main.accounting_section,main.reviewed,main.reviewer,main.bill_no,(case a.direction when 0 then amount else 0 end) as debit_amount,
                (case a.direction when 1 then amount else 0 end) as credit_amount";

        $list = M("VcrVoucherDrafDetail a")->field($field)
                        ->join("inner join vcr_voucher_draf main on main.id=a.parent_id")
                        ->join("left join vcr_subject b on a.subject_id=b.id")
                        ->join("left join vcr_subject bp on bp.id=b.parent_id")
                        ->where("a.parent_id in($draf_ids)")->order("a.direction,a.row_no")->select();

        foreach ($list as $value){
            $result[$value["parent_id"]][] = $value;
        }
        unset($list);
        return $result;
    }

    //导航显示,排序默认按序号从小到大
    public function getViewData($id ,$direction, $accounting_section, $branch_id) {
        $order = "id";
        if ($id > 0 && $direction != ""){
            if ($direction == "next"){
                $condition["id"] = array("GT", $id);
            }else{
                $condition["id"] = array("LT", $id);
                $order = "id desc";
            }
        }
        $condition["branch_id"] = $branch_id;
        if ($accounting_section) { //非必传参数
            if (strlen($accounting_section) == 4) { //只传入年
                $condition["accounting_section"] = array("like", $accounting_section."%");
            } else {
                $condition["accounting_section"] = $accounting_section;
            }
        }

        $result = $this->where($condition)->order($order)->find();
        if ($result){
            $parent_id = $result["id"];
            $field = "a.*,a.subject_name as _subject_name,b.no as subject_no, IF(ISNULL(bp.name), b.name, concat(bp.name,'—',b.name)) as subject_name,
                concat(b.no,':',IFNULL(concat(bp.name,'—'),''),b.name) as full_name,
                main.accounting_section,main.reviewed,main.reviewer,main.bill_no,(case a.direction when 0 then amount else 0 end) as debit_amount,
                (case a.direction when 1 then amount else 0 end) as credit_amount";
            $detail = M("VcrVoucherDrafDetail a")->field($field)
                ->join("inner join vcr_voucher_draf main on main.id=a.parent_id")
                ->join("left join vcr_subject b on a.subject_id=b.id")
                ->join("left join vcr_subject bp on bp.id=b.parent_id")
                ->where("a.parent_id=$parent_id")->order("a.direction,a.row_no")->select();
            $result["detail"] = $detail;
        }
        return $result;
    }

    public function changeSubject($branch_id, $detail_id, $subject_id) {
        $sql = "select * from vcr_voucher_draf_detail where id=$detail_id";
        $bill_details = $this->query($sql);
        if ($bill_details) {
            $subject_name = $bill_details[0]["subject_name"];
            if ($this->execute("update vcr_voucher_draf_detail set subject_id=$subject_id where id=$detail_id") !== false) {
                return $this->updateSubjectStudy($branch_id, $subject_name, $subject_id);
            }
        }
        return false;
    }


    private function updateSubjectStudy($branch_id, $subject_name, $subject_id){
        $md5Key = md5($subject_name);
        $count_sql = sprintf("select id  from vcr_subject_study where md5key='%s' and branch_id=%d", $md5Key, $branch_id);
        if ($count_data = $this->query($count_sql)) {
            $sql = sprintf("update vcr_subject_study set subject_id=%d where id=%d", $subject_id, $count_data[0]["id"]);
        } else {
            $sql = sprintf("insert into vcr_subject_study(name,md5key,branch_id,subject_id) values ('%s','%s',%d,%d)", $subject_name, $md5Key, $branch_id, $subject_id);
        }
        return $this->execute($sql);
    }

    /** 获取异常凭证科目
     * @param $branch_id
     * @return mixed
     */
    public function getExceptions($branch_id){
        $sql = "select a.subject_name, GROUP_CONCAT(a.id) as details from  vcr_voucher_draf_detail a inner join vcr_voucher_draf b on a.parent_id=b.id 
          where isnull(subject_id) and b.branch_id=$branch_id
          group by a.subject_name";
        return $this->query($sql);
    }

    public function getExceptionCount($branch_id){
        $sql = "select count(id) as count from  vcr_voucher_draf where has_error=1 and branch_id=$branch_id";
        $result = $this->query($sql);
        return $result[0]["count"];
    }

    /**修正凭证科目异常
     * @param $branch_id
     * @param $subject_name
     * @param $subject_id
     * @return bool|false|int
     */
    public function dealException($branch_id, $subject_name, $subject_id){
        $sql = "update vcr_voucher_draf_detail a 
                inner join vcr_voucher_draf b on a.parent_id=b.id
                set a.subject_id=$subject_id 
                where isnull(a.subject_id) and subject_name='$subject_name' and b.branch_id=$branch_id";
        if ($this->execute($sql) !== false){
            return $this->updateSubjectStudy($branch_id, $subject_name, $subject_id);
        }else{
            return false;
        }
    }

    //导出凭证
    public function export($branch_id, $accounting_section, $format){
        switch ($format){
            case EXPORT_FMT_K3:

                break;
            case EXPORT_FMT_YY:
                break;
        }
    }

    public function getMaxNumber($branch_id){
        return D("VcrVoucherDraf")->where("branch_id = $branch_id")->max("number") + 1;
    }

    //手动生成凭证时，类型为银行对账单的单证资料获取详情信息
    public function getBillBankDetail($data,$source_id,$branch_id){
        $result = [];
        $company_data = D("ComCompany")->alias("a")->field("a.*")->where("a.id=".$branch_id)->find();
        $bill_data = D("VcrBillBankAccount")->getBillData($data["accounting_section"],$branch_id,[$source_id]);
        if($bill_data){
            if($bill_data[0]["source_flag"] == FLAG_SOURCE_PAY){
                //处理付款
                $result = D("VcrBillBankAccount")->processPayBill($bill_data[0], $company_data);
            }else{
                //处理收款
                $result = D("VcrBillBankAccount")->processReceiveBill($bill_data[0], $company_data);
            }
            $result['accounting_section'] = $bill_data[0]['accounting_section'];
        }
        foreach ($result['list'] as $k=>$v){
            $detail = D("VcrSubject a")
                ->join("left join vcr_subject b on a.parent_id = b.id")
                ->where("a.id = ".$v['subject_id'])
                ->field("a.*,concat(a.no,':',IFNULL(concat(b.name,'—'),''),a.name) as full_name")
                ->find();
            $result['list'][$k] = array_merge($result['list'][$k],$detail);
            $result['list'][$k]['credit_amount'] = $v['direction'] == FLAG_SOURCE_PAY ? $v['amount'] : 0;
            $result['list'][$k]['debit_amount'] = $v['direction'] == FLAG_SOURCE_INCOME ? $v['amount'] : 0;
        }
        /*if($result['list']){
            $result['list'][0]['summary'] = $bill_data[0]['memo'];
        }*/
        return $result;
    }

    //手动生成凭证时，类型为自开发票的单证资料获取详情信息
    public function getOutPutBillDetail($data,$source_id,$branch_id){
        $result = [];
        $company_data = D("ComCompany")->alias("a")->field("a.*")->where("a.id=".$branch_id)->find();
        $bill_data = D("VcrBillValueTax")->getOutPutBillData($company_data,$data["accounting_section"],[$source_id]);
        if($bill_data){
            //判断是否有自开票所需科目，如无则返回错误
            $result = D("VcrBillValueTax")->getOutPutBillSubject($branch_id,$company_data);
            if($result['error_message']){
                return false;
            }
            $main_income_subject = $result['main_income_subject'];
            $tax_subject = $result['tax_subject'];
            if ($company_data["ent_type_id"] != ENTERPRISE_TYPE_JRBX) { //非金融（除银行业外）特指小贷公司，是应收利息，其他都是应收账款
                $receivable_subject = getReceivableSubject($branch_id, $bill_data[0]["name"]); //应收账款
            }else{
                $receivable_subject = getInterestSubject($branch_id, $bill_data[0]["name"]); //应收利息
            }
            if (empty($receivable_subject)){
                return false;
            }
            $data_list = D("VcrBillValueTax")->getOutPutBillDataList($receivable_subject,$bill_data[0],"",[],$main_income_subject,$tax_subject);
            foreach ($data_list as $k=>$v){
                $detail = D("VcrSubject a")
                    ->join("left join vcr_subject b on a.parent_id = b.id")
                    ->where("a.id = ".$v['subject_id'])
                    ->field("a.*,concat(a.no,':',IFNULL(concat(b.name,'—'),''),a.name) as full_name")
                    ->find();
                $result['list'][$k] = array_merge($data_list[$k],$detail);
                $result['list'][$k]['credit_amount'] = $v['direction'] == FLAG_SOURCE_PAY ? $v['amount'] : 0;
                $result['list'][$k]['debit_amount'] = $v['direction'] == FLAG_SOURCE_INCOME ? $v['amount'] : 0;
            }
        }
        return $result;
    }

    //手动生成凭证时，类型为非费用类的外取票单证资料获取详情信息
    public function getProcessPurchaseDetail($data,$source_id,$branch_id){
        $result = [];
        $error_message = [];
        $company_data = D("ComCompany")->alias("a")->field("a.*")->where("a.id=".$branch_id)->find();
        $billDatas = D("VcrBillValueTax")->getProcessPurchaseBillData($branch_id,$data['accounting_section'],[$source_id]);
        if($billDatas) {
            $subject = D("VcrBillValueTax")->getProcessPurchaseSubject($branch_id,$company_data);
            if($subject['error_message']){
                $result['error_message'] = $subject['error_message'];
                return $result;
            }
            foreach ($billDatas as $bill) {
                //现金已付,否则银行存款
                if ($bill["cashpayed"]) {
                    $payable_subject = $subject['cash_subject'];
                } else {
                    $payable_subject = getPayableSubject($branch_id, $bill["name"]); //应付账款优先，如果找不到，就现金科目
                    $error_message["payable_subject"] = getVoucherSubjectError($payable_subject, "应付账款-" . $bill["name"]);
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
                        "error" => $error_message["debit_subject"]
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
                        "error" => $error_message["tax_subject"]
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
                    "error" => $error_message["payable_subject"]
                );
                foreach ($data_list as $k=>$v){
                    $detail = D("VcrSubject a")
                        ->join("left join vcr_subject b on a.parent_id = b.id")
                        ->where("a.id = ".$v['subject_id'])
                        ->field("a.*,concat(a.no,':',IFNULL(concat(b.name,'—'),''),a.name) as full_name")
                        ->find();
                    $result['list'][$k] = array_merge($data_list[$k],$detail);
                    $result['list'][$k]['credit_amount'] = $v['direction'] == FLAG_SOURCE_PAY ? $v['amount'] : 0;
                    $result['list'][$k]['debit_amount'] = $v['direction'] == FLAG_SOURCE_INCOME ? $v['amount'] : 0;
                }
            }
        }
        return $result;
    }

    //手动生成凭证时，类型为费用类的外取票单证资料获取详情信息
    public function getProcessFeeDetail($data,$source_id,$branch_id){
        $result = [];
        $company_data = D("ComCompany")->where("id = $branch_id")->find();
        $where = sprintf("where ISNULL(voucher_id) and bill_flag=%d and is_fee=1 and branch_id=%d  and id=%d", FLAG_BILL_TAX_PAY, $branch_id, $source_id);
        $sql = "select * from vcr_bill $where order by name";
        $billDatas = $this->query($sql);
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
                $error_message["payable_subject"] = getVoucherSubjectError($payable_subject, "应付账款-" . $bill["name"]);
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
                if ($merge_fee && $fee_subject["match"]) {
                    if ($subjct_arrays[$fee_subject_name]) {
                        $subjct_arrays[$fee_subject_name]["amount"] += $fee_amount;
                    } else {
                        $subjct_arrays[$fee_subject_name] = $debit_row;
                        $startRow++;
                    }
                } else {
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
                    "subject_name" => $tax_subject["subject"]["name"],
                    "subject_id" => $tax_subject["subject"]["id"],
                    "amount" => $vat_total_amount,
                    "summary" => '',
                    "direction" => DIRECTION_DEBIT,
                    "parent_id" => 0,
                    "error" => $error_message["tax_subject"]
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
                "error" => ""
            );
            foreach ($data_list as $k=>$v){
                $detail = D("VcrSubject a")
                    ->join("left join vcr_subject b on a.parent_id = b.id")
                    ->where("a.id = ".$v['subject_id'])
                    ->field("a.*,concat(a.no,':',IFNULL(concat(b.name,'—'),''),a.name) as full_name")
                    ->find();
                $result['list'][$k] = array_merge($data_list[$k],$detail);
                $result['list'][$k]['credit_amount'] = $v['direction'] == FLAG_SOURCE_PAY ? $v['amount'] : 0;
                $result['list'][$k]['debit_amount'] = $v['direction'] == FLAG_SOURCE_INCOME ? $v['amount'] : 0;
            }
        }
        return $result;
    }

    //获取凭证在会计区间内未生成、已生成条数
    public function getVoucherCount($branch_id,$year){
        $result = [];
        $condition['accounting_section'] = array(array("gt","$year"),array("lt",$year+1));
        $condition['branch_id'] = $branch_id;
        //已生成凭证数
        $voucher = M(CONTROLLER_NAME)
            ->where($condition)
            ->field("count(accounting_section) as num,substring(accounting_section,1,7) as accounting_section")
            ->order("accounting_section")->group("accounting_section")->select();
        //单证资料未生成凭证数
        $condition['voucher_id'] = array("exp","IS NULL");
        $condition['bill_flag'] = array("lt",FLAG_BILL_FARMPRODUCE);
        $bill = M("VcrBill")
            ->where($condition)
            ->field("count(accounting_section) as num,substring(accounting_section,1,7) as accounting_section")
            ->order("accounting_section")->group("accounting_section")->select();
        $model = M("VcrVoucherDraf");
        for($i = 1; $i<13; $i++){
            //会计区间
            $result[$i]['accounting_section'] = $i < 10 ?  $year."/0$i" : "$year/$i";
            $key = array_search($result[$i]['accounting_section'],array_column($voucher,"accounting_section"));
            //当前区间已生成凭证数
            $result[$i]['voucher_num'] = $key !== false ? $voucher[$key]['num'] : 0;
            //当前区间未生成凭证数
            $key = array_search($result[$i]['accounting_section'],array_column($bill,"accounting_section"));
            $result[$i]['bill_num'] = $key !== false ? $bill[$key]['num'] : 0;
            $condition = [];
            $condition['accounting_section'] = $result[$i]['accounting_section'];
            $condition['branch_id'] = $branch_id;
            $result[$i]['number'] = $model->where($condition)->field("max(number) as max_number,min(number) as min_number")->find();
        }
        return $result;
    }

    //根据客户设置的凭证审核类型获取凭证当前状态
    //返回state 0未完成 1已完成
    public function getVoucherState($voucher){
        $array = [0=>"待复核",1=>"待审核",2=>"已完成"];
        $config = M("VcrConfig")->where("branch_id = ".$voucher['branch_id'])->getField("vcr_draf_review");
        if($config == VCR_DRAF_REVIEW_ACCOUNTING){
            //不需要主管审核，会计复核状态为1后便是已完成
            $state = $voucher['reviewed'] == 0 ? $array[0] : $array[2];
        }else{
            //主管已审核则审核状态为2
            $state = $array[$voucher['reviewed']];
        }
        return $state;
    }

}
