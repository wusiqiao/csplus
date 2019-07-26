<?php

namespace ESAdmin\Controller;


use Think\Exception;

class  ComFansController extends UserParseController {
    public function listAction() {
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $_order = array();
        $this->_parseOrder($_order);
        $_filter = array();
        if (!empty(I('groups'))) {
            $groups = I('groups');
            $group_conditon = [];
            foreach ($groups as $k => $v) {
                if ($v == '0') {
                    unset($groups[$k]);
                    $group_conditon[] = "a.group_id is null";
                    $group_conditon[] = "a.group_id =''";
                }
            }
            if (!empty($groups)) {
                $group_conditon[] = " a.group_id in (".implode(",", $groups).")";
            }
            $_filter["_string"] = "(".implode(" or ",$group_conditon).")";
        }
        
        $this->_parseFilter($_filter);
        $temp[] = $_filter["a.id"];
        $_filter["a.id"] = $temp;
        if (!empty(I('tags'))) {
            $this->handlerTagsSearch(I('tags'),$_filter);
        }

        $user_ids = [];
        $user_company = I("user_company");
        if (!empty($user_company)) {
            $sysUserBranch = D('SysUserBranch')->where(['branch_id' =>$user_company])->select();
            if (!empty($sysUserBranch)) {
                foreach ($sysUserBranch as $k => $v) {
                    array_push($user_ids, $v['user_id']);
                }
                $_filter["a.id"][] = array('in',$user_ids);
            }else{
                 $_filter["a.id"] = 0;
            }
        }

        $user_ids = [];
        $is_bind = I("is_bind");
        if (!empty($is_bind)) {
            $sysUserBranch = M('SysUserBranch')->alias('a')
            ->join('LEFT JOIN sys_branch b ON b.id = a.branch_id')->where(['b.type' =>['neq',2]])->field('a.user_id,a.branch_id')->select();

            if ($is_bind==1) {
                if (!empty($sysUserBranch)) {
                    foreach ($sysUserBranch as $k => $v) {
                        array_push($user_ids, $v['user_id']);
                    }
                    $_filter["a.id"][] = array('in',$user_ids);
                }else{
                     $_filter["a.id"] = 0;
                }
            } else {
                if (!empty($sysUserBranch)) {
                    foreach ($sysUserBranch as $k => $v) {
                        array_push($user_ids, $v['user_id']);
                    }
                    $_filter["a.id"][] = array('not in',$user_ids);
                }
            }   
        }

        $count = D(CONTROLLER_NAME)->setDacFilter("a")->where($_filter)->count();
        $_order = "followed_at desc";
        $list = D(CONTROLLER_NAME)->setDacFilter("a")->relation(true)->field("a.*")->where($_filter)->page($page_index, $page_size)->order($_order)->select();
        $this->_before_list($list);
        $result["total"] = $count;
        $result["rows"] = $list;
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($result));
    }
    public function testAction()
    {
        if (IS_POST) {

        }else{
            $str = I('str');
            $condition = [];
            if (!empty($str)) {
                $where['name']  = array('like', '%'.$str.'%');
                $where['querykey']  = array('like', '%'.$str.'%');
                $where['_logic'] = 'or';
                $condition['_complex'] = $where;
            }
            $condition['parent_id']=$this->_user_session->currBranchId;
            $company_data = json_encode(M('SysBranch')
                ->where($condition)
                ->field("id,name,linkman,contact,querykey")->select());
            $this->company_data = $company_data;
            $this->display('test');
        }
    }

    public function companyListAction()
    {
            $str = I('q');
            $condition = [];
            if (!empty($str)) {
                $where['name']  = array('like', '%'.$str.'%');
                $where['querykey']  = array('like', '%'.$str.'%');
                $where['_logic'] = 'or';
                $condition['_complex'] = $where;
            }
            $condition['parent_id'] = $this->_user_session->currBranchId;
            $condition['type']=1;
            $condition['is_valid']=1;
            $company_data = M('SysBranch')
                ->where($condition)
                ->field("id,name,querykey")->select();
            return $this->ajaxReturn($company_data);
    }

    public function bindNoticeAction()
    {
        if (IS_POST) {
            //获取openid且发送模板消息
            // $users = D(CONTROLLER_NAME)->getUsersData();
            $condition['id'] = array('in',I('post.id'));
            $condition['is_follow'] = 1;
            $condition['_string'] = 'mobile is null or mobile=""';
            $users = M('SysUser')->where($condition)->field('id,name,openid')->select();
            $result = $this->handlerSendTemplateMessageForNotice($users);
            $not_send_count = count(I('post.id'))-count($users);
            $result['message'] = "已经成功发送".$result['num1']."用户，未成功发送".($result['num2']+$not_send_count)."用户。";
            $this->ajaxReturn($result);
        }else{
            $this->display('bind_notice');
        }
    }
    public function handlerSendTemplateMessageForNotice($users,$mobile="",$type="")
    {
       $template_id =  M('WxConfig')->where('branch_id = '.$this->_user_session->currBranchId)->getField('wx_notice_bind_company');
        $num1 = 0;
        $num2 = 0;
       if ($template_id) {
           $message = array();
           $message["template_id"] = $template_id;
           //$type = I("get.type");
           if($type == "addStaff"){
               $message["url"] = str_replace('shop','shop'.$this->_user_session->currBranchId,SHOP_ROOT).'/Organization/bound_staff/mobile/'.$mobile;
               foreach($users as $key => $value) {
                    $body = array();
                    $body["first"]["value"]    = '绑定手机号，即可使用更多服务功能';
                    $body["keyword1"]["value"] = $value['name']; //编号
                    $body["keyword2"]["value"] = '绑定手机号'; //类型
                    $body["remark"]["value"] = '点击进入页面快速绑定手机号';
                    $message["body"] = $body;
                    $message["openid"] = $value['openid'];
                    $rst = send_wx_message($message);
                    if ($rst['errcode']==0) {
                        $num1++;
                    }else{
                        $num2++;
                    }
               }
           }else{
               $message["url"] = str_replace('shop','shop'.$this->_user_session->currBranchId,SHOP_ROOT).'/User/index.html';
               foreach($users as $key => $value) {
                    $body = array();
                    $body["first"]["value"]    = '绑定手机号，即可使用更多服务功能';
                    $body["keyword1"]["value"] = $value['name']; //编号
                    $body["keyword2"]["value"] = '绑定手机号'; //类型
                    $body["remark"]["value"] = '点击进入页面快速绑定手机号';
                    $message["body"] = $body;
                    $message["openid"] = $value['openid'];
                    $rst = send_wx_message($message);
                    if ($rst['errcode']==0) {
                        $num1++;
                    }else{
                        $num2++;
                    }
                }
           }
           return ['code'=>0,'message'=>'已经成功发送'.$num1.'用户，未成功发送'.$num2.'用户。',"num1"=>$num1,"num2"=>$num2];
           // return ['code'=>0,'message'=>'已发送绑定通知'];
       } else {
           //获取template_id
           $wechat = getWeChatInstance();
           $result["errcode"] = 0;
           if ($wechat->isRemoteHost()) {
               $template_id =  $this->addTemplateMessage($wechat, 'OPENTM207372650', '绑定通知');
               if (!$template_id) {
                   $result["errcode"] = $wechat->errCode;
                   $result["errmsg"] = $wechat->errMsg;
                   \Think\Log::write("send_wx_message error!= message:" . $wechat->errMsg.'|code:'.$wechat->errCode);
                   return ['code'=>1,'message'=>'获取不到绑定通知模板,请到公众号内查看模板消息是否开启'];
               }else{
                   M('WxConfig')->where('branch_id = '.$this->_user_session->currBranchId)->setField('wx_notice_bind_company',$template_id);
                   return $this->handlerSendTemplateMessageForNotice($users);
               }
           }
       }
    }
    //不能直接调用接口，需要判断微信后台模板是否没有再添加，否则会出现重复
    private function addTemplateMessage($wechat, $tpl_id_short, $title){
        $list = $wechat->getTemplateMessageList();//先获取微信后台模板
        $template_id = "";
        foreach ($list["template_list"] as $key=>$tpl){
            if ($tpl["title"] == trim($title)){
                $template_id = $tpl["template_id"];
                break;
            }
        }
        if (empty($template_id)){
            $template_id = $wechat->addTemplateMessage($tpl_id_short);
        }
        return $template_id;
    }
    protected function _before_display_dataview(&$data) {
        parent::_before_display_dataview($data);
        $temp = getRegion('1');
        array_unshift($temp,['id'=>null,'name' => '请选择省份']);
        $this->assign('province_lists',$temp);
        $this->assign('city_lists',$data['province'] ? getRegion('2',$data['province']) : [['id'=>null,'name' => '请选择城市']]);
        $this->assign('district_lists',$data['city'] ? getRegion('3',$data['city']) : [['id'=>null,'name' => '请选择县级市']]);
        //获取公司列表
        $this->handlerBranchLists($data);
//        var_dump($data);die;
    }
    public function assignPermissions($controller = CONTROLLER_NAME)
    {
        parent::assignPermissions($controller); // TODO: Change the autogenerated stub
        //获取标签和分组
        $groups = D(CONTROLLER_NAME)->getBranchTarget('group');
        $tags = D(CONTROLLER_NAME)->getBranchTarget('tag');
        foreach($groups as $k=>$v){
            $groups[$k]['user_count'] = M('SysUser')
                ->alias('a')
            ->where(['a.group_id'=>$v['id'],'a.is_follow'=>1,'_string' => 'a.mobile is null or a.mobile =""','a.branch_id' => $this->_user_session->currBranchId])
            ->count();
        }
        $tmp = array('id'=>0,'value' => '未分组','branch_id' => $this->_user_session->currBranchId);
        $tmp['user_count'] = M('SysUser')
            ->alias('a')
            ->where(['a.branch_id' => $this->_user_session->currBranchId,'a.is_follow'=>1,
            '_string' => '(a.group_id is null or a.group_id ="") and (a.mobile is null or a.mobile ="")'])
        ->count();
        array_push($groups,$tmp);
        foreach($tags as $k=>$v){
            $tags[$k]['user_count'] = M('SysUser')
            ->alias('b')
            ->join('LEFT JOIN sys_user_relation_tag a ON a.user_id = b.id')
            ->where(['a.tag'=>$v['id'],'b.is_follow'=>1,'_string' => 'b.mobile is null or b.mobile =""','a.branch_id' => $this->_user_session->currBranchId])->count();
        }
        $this->groups = $groups;
        $this->tags = $tags;
    }
    protected function _before_list(&$list)
    {
        parent::_before_list($list); // TODO: Change the autogenerated stub
        $user_type = [USER_TYPE_COMPANY_MANAGER=>'员工',USER_TYPE_CUSTOMER=>'成交客户',USER_TYPE_PROSPECTIVE=>'意向客户'];
        foreach($list as $key => $val) {
            $list[$key]['tags_value'] = D(CONTROLLER_NAME)->getGroupNames($val);
            $list[$key]['company_names'] = D(CONTROLLER_NAME)->getCompanyNames($val);
            $list[$key]['company_ids'] = D(CONTROLLER_NAME)->getCompanyIds($val);
            $list[$key]['service_man_value'] = D(CONTROLLER_NAME)->getServiceMan($val);
            $list[$key]['tag_ids'] = D(CONTROLLER_NAME)->getTagIds($val);
            if (isset($user_type[$val['user_type']])) {
                $list[$key]['user_type_value'] = $user_type[$val['user_type']];
            }else{
                $list[$key]['user_type_value'] = ""; 
            }
        }
    }

    protected function _before_detail(&$data) {
        $data['tags_value'] = D(CONTROLLER_NAME)->getGroupNames($data);
        $data['company_names'] = D(CONTROLLER_NAME)->getCompanyNames($data);
        $data['company_ids'] = D(CONTROLLER_NAME)->getCompanyIds($data);
        $data['service_man_value'] = D(CONTROLLER_NAME)->getServiceMan($data);
        $data['tag_ids'] = D(CONTROLLER_NAME)->getTagIds($data);
        // $list[$key]['company_names'] = D(CONTROLLER_NAME)->getCompanyNames($data);

        $user_type = [USER_TYPE_COMPANY_MANAGER=>'员工',USER_TYPE_CUSTOMER=>'成交客户',USER_TYPE_PROSPECTIVE=>'意向客户'];
        if (isset($user_type[$data['user_type']])) {
            $data['user_type_value'] = $user_type[$data['user_type']];
        }else{
            $data['user_type_value'] = ""; 
        }
        parent::_before_detail($data);
    }
    public function showTipAction()
    {
        $this->display('show_tip');
    }

}