<?php

namespace ESAdmin\Controller;

use Think\Page;

class SysBranchController extends BranchBaseController {

    protected function _before_add(&$data) {
        parent::_before_add($data);
        $data['attach_group'] = genUniqidKey();
        //系统管理员需指定跟踪人员，员工为设置自己
        $user = M("SysUser")->where("id = ".$this->_user_session->userId)->field("is_leader,staff_name,name,id")->find();
        if($user['is_leader']){
            $data['isSelectTracker'] = 1;
        }else{
            $data['isSelectTracker'] = 0;
            $data['tracker_name'] = $user['staff_name'] == "" ? $user['name'] : $user['staff_name'];
            $data['tracker_id'] = $user['id'];
        }
        $data["branch_role"] = 0; //默认意向客户
    }


    protected function _before_display_dataview(&$data) {
        $data["branch_roles"] = json_encode(getBranchRoles());
    }

    public function indexAction(){
        $this->branch_roles = json_encode(getBranchRoles());
        parent::indexAction();
    }
    protected function _parseOrder(&$order) {
        parseQueryOrder($order);
    }
    public function treeAction() {
        $condition["a.type"] =  ORG_BRANCH ; //加上组织机构
        $list = D(CONTROLLER_NAME)->getUserBranchTree($condition, $this->_user_session);
        $this->ajaxReturn($list);
    }

    protected function _parsefilter(&$filter) {
        parent::_parsefilter($filter);
        $filter["a.id"] = array("neq", 1);
        if(!$this->isAdmin){
            $filter["a.tracker_id"] = $this->_user_session->userId;
        }
        $state = I("post.state");
        if($state != ""){
            $filter["a.state"] = $state;
        }
        $search = I("post.search");
        if($search != ""){
            $where['a.name'] = array("like","%".$search."%");
            $where['a.comments'] = array("like","%".$search."%");
            $where['a.querykey'] = array("like","%".$search."%");
            $where['a.linkman'] = array("like","%".$search."%");
            $where['a.contact'] = array("like","%".$search."%");
            $where['a.address'] = array("like","%".$search."%");
            $where['_logic'] = "or";
            $filter["_complex"] = $where;
        }
    }

    protected function _before_write($type, &$data){
        parent::_before_write($type, $data);
        /*if($data['tracker_id'] == ""){
            $this->responseJSON(buildMessage("请选择跟踪业务员！", 1));
        }*/
        $bundles = I("post.bundles");
        if($type == 1){
            $data['create_time'] = time();
        }
        $data["bundles"] = implode(",",$bundles);
    }

    protected function _before_detail(&$data){
        parent::_before_detail($data);
        if($data['leader_id']){
            $data['mobile'] = M("SysUser")->where("id = ".$data['leader_id'])->getField("mobile");
        }
        if($data['tracker_id']){
            $tracker = M("SysUser")->where("id = ".$data['tracker_id'])->field("name,staff_name")->find();
            $data['tracker'] = $tracker['staff_name'] == "" ? $tracker['name']:$tracker['staff_name'];
        }
        $data['track_time'] = date("Y年m月d H:i",$data['create_time']);
        if($data['attach_group'] == ""){
            $data['attach_group'] = genUniqidKey();
        }

    }

    public function syncFansAction($appid, $appsecret,$branch_id,$token =''){
        if (empty($appid) || empty($appsecret)){
            die("appid 或 appsecret不能为空！");
        }
        Vendor('Wechat.TPWechat', '', '.class.php');
        $options = array(
            'appid' => $appid,
            'appsecret' => $appsecret
        );
        $wechatInstance = new \TpWechat($options);
        $tally = 100;
        if (empty($token)) {
            $count = 0;
            $total = 0;
            $wx_users =  $wechatInstance->getUserList();
            if (count($wx_users["data"]["openid"]) > $tally - 1) {
                $token = genUniqidKey();
            }
        } else {
            $token_data = session($token);
            $wx_users =  $wechatInstance->getUserList($token_data['next_openid']);
            $count = $token_data['count'];
            $total = $token_data['total'];
        }
        if ($wx_users) {
            foreach ($wx_users["data"]["openid"] as $key=>$openid) {
                $total++;
                $sys_user["openid"] = $openid;
                $wx_user = $wechatInstance->getUserInfo($openid);
                $sys_user["nickname"] = $wx_user["nickname"];
                $sys_user["headimgurl"] = $wx_user["headimgurl"];
                $sys_user["subscribe_scene"] = $wx_user["subscribe_scene"];
                if (D(CONTROLLER_NAME)->addFans($branch_id, $sys_user)){
                    $count++;
                }
                if ($total%$tally == 0){
                    session($token,['count'=>$count,'next_openid' =>$openid,'total' => $total]);
                    break;
                }
            }
            if ($total%$tally == 0) {
                $this->responseJSON(['code'=>2,'token'=>$token,'message'=>'已成功搜索到'.$total.'个粉丝,成功同步'.$count.'个,正在持续同步中,请耐心等待...']);
            } else {
                if ($token != ''){
                    unset($_SESSION[$token]);
                }
                $this->responseJSON(buildMessage("同步完成，总共搜索到微信粉丝共".$total."个,成功同步粉丝".$count."个"));
            }
        }else{
            \Think\Log::write($wechatInstance->errMsg);
            $this->responseJSON(buildMessage("同步失败，微信参数错误！",1));
        }


    }

    public function updateValidStateAction($id){
        if ($this->isAdmin || ($this->companyId == 1)){
            $row = M(CONTROLLER_NAME)->field("id,is_valid")->where("id=$id")->find();
            if ($row) {
                M(CONTROLLER_NAME)->where("id=$id")->setField("is_valid", ($row["is_valid"] == 1) ? 0 : 1);
                $row["is_valid"] = 1 - $row["is_valid"];
                $this->responseJSON(buildResult($row));
            }
        }
    }

    public function branchLogAction($branch_id){
        //if ($this->isAdmin || ($this->companyId == 1) && $branch_id){
        if ($branch_id){
            $condition['branch_id'] = $branch_id;
            $condition['agreement_id'] = array("exp","is null");
            $list = M("SysAgreementLog")->where($condition)->order("id desc")->select();
            $this->responseJSON($list);
        }
    }

    private function get_change_bundles($old, $new){
        $bundles_name = L("ENUM_BUNDLES");
        $old_bundles = explode(",", $old);
        foreach ($old_bundles as $bundle){
            $old_bundles_str[] = $bundles_name[$bundle];
        }
        $new_bundles = explode(",", $new);
        foreach ($new_bundles as $bundle){
            $new_bundles_str[] = $bundles_name[$bundle];
        }
        $content = sprintf('服务类型由"%s"修改为"%s"',implode("/",$old_bundles_str), implode("/",$new_bundles_str));
        return $content;
    }

    protected function _before_list(&$list) {
        $bundles_name = L("ENUM_BUNDLES");
        foreach ($list as $key=>$item){
            $bundles_str = array();
            $bundles = explode(",", $item["agree_bundles"]);
            foreach ($bundles as $bundle){
                $bundles_str[] = $bundles_name[$bundle];
            }
            $list[$key]["bundles"] = implode("/", $bundles_str);
            if(mb_strlen($item['name']) > 15){
                $list[$key]['name'] = mb_substr($item['name'],0,15)."...";
            }
            if($item['tracker_id']){
                $tracker = M("SysUser")->where("id = ".$item['tracker_id'])->field("name,staff_name")->find();
                $list[$key]['tracker'] = $tracker['staff_name'] == "" ? $tracker['name']:$tracker['staff_name'];
            }
        }
    }

    public function inviteLeaderAction($branch_id, $mobile){
       if( M('SysBranch')->where(['id' => $branch_id])->getField('branch_role') <= 0){
           exit('请选择商城版本号!');
       }
        $url = sprintf("https://eshop%s.caisuikx.com/SysBranch/boundLeader/mobile/%s", $branch_id, $mobile);
        Vendor("phpqrcode.phpqrcode");
        ob_start();
        $errorCorrectionLevel = 'L';  //容错级别
        $matrixPointSize = 5;      //生成图片大小
        \QRcode::png($url,false,$errorCorrectionLevel,$matrixPointSize,2);
        $imageBase64 = base64_encode(ob_get_contents());
        ob_end_clean();
        echo "<img src='data:image/png;base64,$imageBase64'>";
    }

    public function resetLeaderAction($branch_id){
        //if ($this->isAdmin || ($this->companyId == 1) && $branch_id){
        if ($branch_id){
            M(CONTROLLER_NAME)->where("id=$branch_id")->setField("leader_id", ['exp', 'NULL']);
            M("SysUser")->where("branch_id=$branch_id")->setField("is_leader", 0);
            $this->responseJSON(buildMessage("重置成功"));
        }else{
            $this->responseJSON(buildMessage("重置失败",1));
        }
    }

    public function transferTrackerAction(){
        if(IS_POST){
            $id = I("post.id");
            $tracker_id = I("post.tracker_id");
            $tracker_name = I("post.tracker_name");
            if($id == "" or $tracker_id == ""){
                $this->ajaxReturn(array("error"=>1,"message"=>"请选择您要交接的业务员！"));
            }
            $old_tracker = M("SysBranch a")->join("sys_user b on a.tracker_id = b.id")->where("a.id = $id")->field("a.tracker_id,b.staff_name,b.name")
                ->find();
            if($old_tracker['tracker_id'] == $tracker_id){
                $this->ajaxReturn(array("error"=>1,"message"=>"请选择新的业务员！"));
            }
            $result = M("SysBranch")->where("id = $id")->setField("tracker_id",$tracker_id);
            if($result !== false){
                $name = $old_tracker['staff_name'] == ""? $old_tracker['name']:$old_tracker['staff_name'];
                $content = "跟踪业务员由 $name 转为 $tracker_name";
                $log = D("SysBranch")->addBranchLog($id,$content);
                $is_leader = M("SysUser")->where("id = ".$this->_user_session->userId)->getField("is_leader");
                if(!$is_leader){
                    $this->ajaxReturn(array("error"=>0,"message"=>"移交成功！","close"=>1));
                }else{
                    $this->ajaxReturn(array("error"=>0,"message"=>"移交成功！","close"=>0,"log"=>$log));
                }
            }else{
                $this->ajaxReturn(array("error"=>1,"message"=>"移交失败！"));
            }
        }else{
            $id = I("get.id");
            $this->id = $id;
            $this->display();
        }
    }

    public function changeBranchRoleAction($id, $new_branch_role){
        if ($this->isPlatformUser || $id && $new_branch_role) {
            if (D(CONTROLLER_NAME)->changeBranchRole($id, $new_branch_role)){
                $this->responseJSON(buildMessage("版本切换成功"));
            }else{
                $this->responseJSON(buildMessage("版本切换失败，请联系管理员",1));
            }
        }
    }

    public function resetBranchRoleAction($id){
        if ($this->isPlatformUser || $id) {
            if (D(CONTROLLER_NAME)->resetBranchRole($id)){
                $this->responseJSON(buildMessage("版本重置成功"));
            }else{
                $this->responseJSON(buildMessage("版本重置失败，请联系管理员",1));
            }
        }
    }

//    //重置code
//    public function resetCodeAction(){
//        M("SysBranch")->execute("update sys_branch set code=null");
//        $this->resetCodeInternal(0,"");
//    }
//
//    private function resetCodeInternal($id, $code){
//        M("SysBranch")->where("id=$id")->setField("code", $code);
//        $list = M("SysBranch")->field("id,code")->where("parent_id=$id")->order("id")->select();
//        foreach ($list as $value) {
//            $child_code = $code . "_" . \Org\Util\Strings::randString(6);
//            $this->resetCodeInternal($value["id"], $child_code);
//        }
//    }

	/**
	 * 微信认证文件导入到根目录下
	 */
	public function importWeChatFileAction($file_key){
		set_time_limit(0);
		if (!empty($_FILES)) {
			$upload_config = array(
				'maxSize' => 52428800,
				'rootPath' => "./",
				'savePath' => "uploads/wechatfile/",
				'saveName' => "",
				'replace' => true,
				'exts' => "txt",
				'autoSub' => false
			);
			$uploader =  new \Think\Upload($upload_config);
			$info = $uploader->uploadOne($_FILES[$file_key]);
			if (!$info) {
				$message = buildMessage($uploader->getError(), 1);
			} else {
				$message = buildMessage('上传成功',0);
				$message["path_name"] = ltrim($uploader->rootPath, ".") . $info['savepath'] .$info['savename'];
				unset($uploader);
			}
			$this->responseJSON($message);
		} else {
			$this->responseJSON(buildMessage("文件不能为空！", 1));
		}
	}

}
