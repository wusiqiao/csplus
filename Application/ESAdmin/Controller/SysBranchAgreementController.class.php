<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class SysBranchAgreementController extends DataController
{
    public function indexAction(){
        $branch_id = I("get.branch_id");
        if($branch_id){
            $branch_name = I("get.branch_name");
            $this->assign("branch_id",$branch_id);
            $this->assign("branch_name",$branch_name);
        }

        parent::indexAction();
    }

    protected function _before_add(&$data)
    {
        parent::_before_add($data);
        $data["token"] = substr(md5(uniqid()), 0, 24);
        $data["agreement_no"] = D(CONTROLLER_NAME)->getMaxAgreementNo();
        $data["start_date"] = date("Y-m-d");
        $data["end_date"] = date("Y-m-d", strtotime("+12 months -1days"));
        $data["bundles"]["gzh"] = "selected";
        $token = substr(md5(uniqid()), 8, 24);
        $this->wc = array("token" => $token);
        $this->edit_style = array("input" => "", "btn" => "display:none");
        //是否传入branch_id
        $branch_id = I("branch_id");
        if ($branch_id){
            $data['branch_id'] = $branch_id;
            $branchData = M("SysBranch")->where("id = $branch_id")->find();
            //获取已设置过的微信配置
            $wxConfig = M("WxConfig")->where(["branch_id"=>$branch_id])->field("appid,appsecret,encoding_aeskey,token")->find();
            if($wxConfig){
                $this->wc = $wxConfig;
            }
            if ($branchData){
                if (ROLE_ID_COMPANY_FREE == $branchData["branch_role"]){
                    $data["months"] = 120;
                    $data["end_date"] = date("Y-m-d", strtotime("+120 months -1days"));
                }
            }

            $this->branch = $branchData;
        }
    }

    protected function _parsefilter(&$filter)
    {
        parent::_parsefilter($filter);
        $role_ids = M("SysUser")->where("id = ".$this->_user_session->userId)->getField("role_ids");
        if(!$this->isAdmin){
            //$filter["a.branch_id"] = array("neq",$this->_user_session->currBranchId);
                $filter["branch.tracker_id"] = $this->_user_session->userId;
            //}
        }
        $date = I("post.date");
        if ($date != "") {
            $start = date("Y-m-d");
            $end = date("Y-m-d", strtotime("+$date month"));
            $filter["_string"] = 'STR_TO_DATE(a.end_date,"%Y-%m-%d") BETWEEN STR_TO_DATE("' . $start . '","%Y-%m-%d") AND STR_TO_DATE("' . $end . '","%Y-%m-%d")';
        }
        $search = I("post.search");
        if ($search != "") {
            $where['branch.name'] = array("like", "%" . $search . "%");
            $where['a.comments'] = array("like", "%" . $search . "%");
            $where['branch.querykey'] = array("like", "%" . $search . "%");
            $where['branch.linkman'] = array("like", "%" . $search . "%");
            $where['branch.contact'] = array("like", "%" . $search . "%");
            $where['branch.address'] = array("like", "%" . $search . "%");
            $where['a.agreement_no'] = array("like", "%" . $search . "%");
            $where['_logic'] = "or";
            $filter["_complex"] = $where;
        }
        $bundles = I("post.bundles");
        if ($bundles != "") {
            $tmp = explode(",", $bundles);
            foreach ($tmp as $bundle) {
                if ($filter['_string'] != "") {
                    $filter['_string'] = $filter['_string'] . " and FIND_IN_SET('$bundle', a.bundles) ";
                } else {
                    $filter['_string'] = "FIND_IN_SET('$bundle', a.bundles) ";
                }
            }
        }
        $state = I("post.state");
        if($state != ""){
            $now = date("Y-m-d");
            switch ($state){
                case '2':
                    $filter['a.end_date'] = array("elt",$now);
                    break;
                default:
                    $filter['a.is_valid'] = $state;
                    $filter['a.end_date'] = array("gt",$now);
                    break;
            }
        }
        $branch_id = I("get.branch_id");
        if($branch_id){
            $filter['a.branch_id'] = $branch_id;
        }
    }

    protected function _before_write($type, &$data)
    {
        parent::_before_write($type, $data);
        if ($agree = M("SysBranchAgreement")->where(array("agreement_no" => $data["agreement_no"]))->find()) {
            if ($data["branch_id"] != $agree["branch_id"]) {
                $this->responseJSON(buildMessage("合同单号重复！", 1));
            }
        }
        if (strtotime($data["end_date"]) < time()) {
            $this->responseJSON(buildMessage("到期时间不能小于今天！", 1));
        }
        $condition['branch_id'] = $data['branch_id'];
        if($data['id']){
            $condition['id'] = array("neq",$data['id']);
        }
        $new_bundles = explode(",",$data['bundles']);
        $other_agreement = M("SysBranchAgreement")->where($condition)->field("bundles")->select();
        foreach ($other_agreement as $k=>$v){
            if(array_intersect($new_bundles,explode(",",$v['bundles']))){
                $this->ajaxReturn(buildMessage("当前客户已含有该服务",1));
            }
        }
    }

    protected function _before_detail(&$data)
    {
        parent::_before_detail($data);
        $branch = M("SysBranch")->where("id = ".$data['branch_id'])->find();
        if($branch){
            $bundles = explode(",", $data["bundles"]);
            foreach ($bundles as $bundle) {
                $bundle_checks[$bundle] = "selected";
            }
            $data["bundles"] = $bundle_checks;
            $this->edit_style = array("input" => "width:30%;", "btn" => "");
        }else{
            $data["agreement_no"] = D(CONTROLLER_NAME)->getMaxAgreementNo();
            $data["start_date"] = date("Y-m-d");
            $data["end_date"] = date("Y-m-d", strtotime("+12 months"));
            $this->edit_style = array("input" => "", "btn" => "display:none");
        }
        if($branch['leader_id']){
            $leader = M("SysUser")->where("id = ".$branch['leader_id'])->field("name,mobile")->find();
            $branch['leader_name'] = $leader['name'];
            $branch['mobile'] = $leader['mobile'];
        }
        $this->branch = $branch;
        $condition = array("branch_id" => $data["branch_id"]);
        $wcConfig = M("WxConfig")->where($condition)->find();
        $wcConfig["url"] = "https://eshop.caisuikx.com/WeChat/index/bid/" . $data["branch_id"];
        $this->wc = $wcConfig;
    }

    public function agreementLogAction($agreement_id)
    {
        //if ($this->isAdmin || ($this->companyId == 1) && $branch_id) {
        if ($agreement_id) {
            $list = M("SysAgreementLog")->where("agreement_id=$agreement_id")->order("id desc")->select();
            $this->responseJSON($list);
        }
    }

    private function get_change_bundles($old, $new)
    {
        $bundles_name = L("ENUM_BUNDLES");
        $old_bundles = explode(",", $old);
        foreach ($old_bundles as $bundle) {
            $old_bundles_str[] = $bundles_name[$bundle];
        }
        $new_bundles = explode(",", $new);
        foreach ($new_bundles as $bundle) {
            $new_bundles_str[] = $bundles_name[$bundle];
        }
        $content = sprintf('服务类型由"%s"修改为"%s"', implode("/", $old_bundles_str), implode("/", $new_bundles_str));
        return $content;
    }

    protected function _before_list(&$list)
    {

        $bundles_name = array("gzh"=>"公众号","xcx"=>"小程序","pc"=>"PC商城","dyy"=>"代运营");
        foreach ($list as $key => $item) {
            $bundles_str = array();
            $bundles = explode(",", $item["bundles"]);
            foreach ($bundles as $bundle) {
                $bundles_str[] = $bundles_name[$bundle];
            }
            $list[$key]["bundles"] = implode("/", $bundles_str);
            if (mb_strlen($item['branch_name']) > 15) {
                $list[$key]['branch_name'] = mb_substr($item['branch_name'], 0, 15) . "...";
            }
        }
    }

    public function addAgreementAction(){
        $datas = I("post.data");
        $data = [];
        foreach ($datas as $k=>$v){
            $data[$v['name']] = $v['value'];
        }
        $data["bundles"] = I('post.bundles');
        $this->_before_write(1,$data);
        $this->updateBranchInfo($data);
        $this->updateAgreement($data);
        $this->updateWxConfig($data);
        $this->ajaxReturn(buildMessage("操作成功",0));
    }

    //添加修改配置
    private function updateWxConfig($data){
        $wxConfigModel = M("WxConfig");
        $cfg_data = $data;
        unset($cfg_data["id"]); //create会把post.id也填进去，此ID为主表sysbranch的id,需要去除
        $condition["branch_id"] = $data['branch_id'];
        if ($wxConfigModel->where($condition)->count() == 0) {
            $cfg_data["branch_id"] = $data['branch_id'];
            $wxConfigModel->add($cfg_data);
        }else{
            $wxConfigModel->where($condition)->save($cfg_data);
        }
    }

    private function updateBranchInfo(&$data){
        $update_data['address'] = $data['address'];
        $update_data['linkman'] = $data['linkman'];
        $update_data['contact'] = $data['contact'];
        if($data['branch_id'] != ""){
            M("SysBranch")->where("id = ".$data['branch_id'])->save($update_data);
        }else{
            $update_data['name'] = $data['name'];
            $update_data['querykey'] = firstPinyin($data['name']);
            $update_data['parent_id'] = $this->_user_session->currBranchId;
            $update_data['branch_id'] = $update_data['parent_id'];
            $update_data['type'] = 0;
            $update_data['create_time'] = time();
            $data['branch_id'] = M("SysBranch")->add($update_data);
        }
    }

    //添加修改合同
    private function updateAgreement($data){
        $agreementModel = M("SysBranchAgreement");
        $agreement_data = $data;
        //unset($agreement_data["id"]); //create会把post.id也填进去，此ID为主表sysbranch的id,需要去除
        if ($agreement_data['id'] != "") {
            $agreementModel->where("id = ".$agreement_data['id'])->save($agreement_data);
            $this->addAgreementLog($agreement_data['id'],$data['branch_id'],"修改合同","update");
        }else{
            $agreement_data["branch_id"] = $data['branch_id'];
            $agreement_data["attach_group"] = md5(uniqid(mt_rand(), true));
            $result = $agreementModel->add($agreement_data);
            if ($result) {
                M("SysBranch")->where("id = ".$data['branch_id'])->setField("state",1);//变为成交客户
                $this->addAgreementLog($result,$data['branch_id'],"创建合同","create");
            }
        }
    }

    public function addAgreementLog($agreement_id,$branch_id,$content,$type){
        $log["agreement_id"] = $agreement_id;
        $log["branch_id"] = $branch_id;
        $log["log_time"] = date("Y-m-d H:i:s");
        $user_session = session(USER_SESSION_KEY);
        if($type == "update"){
            $log["updater_id"] = $user_session->userId;
        }else{
            $log["creater_id"] = $user_session->userId;
        }
        $log["user_name"] = $user_session->userName;
        $log["content"] = $content;
        M("SysAgreementLog")->add($log);
        return $log;
    }

    public function updateAgreementValidStateAction(){
        $id = I("post.id");
        $row = M(CONTROLLER_NAME)->field("id,is_valid")->where("id=$id")->find();
        if ($row) {
            M(CONTROLLER_NAME)->where("id=$id")->setField("is_valid", ($row["is_valid"] == 1) ? 0 : 1);
            $row["is_valid"] = 1 - $row["is_valid"];
            $this->responseJSON(buildResult($row));
        }
    }

    public function updateAgreementAction($branch_id, $datas,$agreement_id){
        //if ($this->isAdmin || ($this->companyId == 1) && $branch_id && $datas){
        if ($agreement_id && $datas){
            if($datas[0]['field'] == "bundles"){
                $new_bundles = explode(",",$datas[0]['value']);
                $other_agreement = M("SysBranchAgreement")->where("branch_id = $branch_id and id != $agreement_id")->field("bundles")->select();
                foreach ($other_agreement as $k=>$v){
                    if(array_intersect($new_bundles,explode(",",$v['bundles']))){
                        $this->ajaxReturn(buildMessage("当前客户已含有该服务",1));
                    }
                }
            }
            $model  =  M("SysBranchAgreement");
            $c["id"] = $agreement_id;
            $old_data = $model->where($c)->find();
            foreach ($old_data as $field=>$value){
                $old_fields[$field] = $value;
            }
            foreach ($datas as $data){
                $field = $data["field"];
                $update_data[$field] = $data["value"];
                $method = sprintf("get_change_%s", $field);
                if (method_exists($this, $method)) {
                    $content = call_user_func_array(array($this, $method), array($old_fields[$field], $data["value"]));
                }else{
                    $content = sprintf('%s由"%s"修改为"%s"',$data["name"],$old_fields[$field], $data["value"]);
                }
                $contents[] = $content;
            }
            $result = $model->where($c)->save($update_data);
            if ($result !== false) {
                $log = $this->addAgreementLog($agreement_id,$branch_id,implode("；",$contents),"update");
                $this->responseJSON(buildResult($log));
            }else{
                $this->responseJSON(buildMessage("设置失败", 1));
            }

        }
    }

}