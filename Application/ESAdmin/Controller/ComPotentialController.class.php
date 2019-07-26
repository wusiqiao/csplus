<?php

namespace ESAdmin\Controller;


class  ComPotentialController extends UserParseController {
    public function listAction() {
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $_order = array();
        $this->_parseOrder($_order);
        $_filter = array();
        $_filter['a.user_type'] = array('neq',USER_TYPE_BUSINESS);
        // if (!empty(I('groups'))) {
        //     $groups = I('groups');
        //     $group_conditon = [];
        //     foreach ($groups as $k => $v) {
        //         if ($v == '0') {
        //             unset($groups[$k]);
        //             $group_conditon[] = "a.group_id is null";
        //             $group_conditon[] = "a.group_id =''";
        //         }
        //     }
        //     if (!empty($groups)) {
        //         $group_conditon[] = " a.group_id in (".implode(",", $groups).")";
        //     }
        //     $_filter["_string"] = "(".implode(" or ",$group_conditon).")";
        // }
        // $user_ids = [];
        // $user_company = I("user_company");
        // if (!empty($user_company)) {
        //     $sysUserBranch = D('SysUserBranch')->where(['branch_id' =>$user_company])->select();
        //     if (!empty($sysUserBranch)) {
        //         foreach ($sysUserBranch as $k => $v) {
        //             array_push($user_ids, $v['user_id']);
        //         }
        //         $_filter["a.id"][] = array('in',$user_ids);
        //     }else{
        //          $_filter["a.id"] = 0;
        //     }
        // }

        // $this->_parseFilter($_filter);
        // $temp[] = $_filter["a.id"];
        // $_filter["a.id"] = $temp;
        $tags = I('tags');
        if (!empty($tags) && $tags[0][0] != 'all') {
            $this->handlerTagsSearch($tags,$_filter);
        }
        
        $is_follow = I("is_follow");
        if(!empty($is_follow)){
            if ($is_follow == 1) {
                $_filter['a.is_follow'] = 1;
            } else {
                $_filter['a.is_follow'] = 0;
            }
        }

        //用户类型筛选
        $user_type = I("user_type");
        $company_bind = null;
        if(!empty($user_type)){
            //是否员工
            if ($user_type == 1) {
                $_filter['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
            }else{
                $_filter['a.user_type'] = array(array('neq',USER_TYPE_COMPANY_MANAGER),array('neq',USER_TYPE_BUSINESS));
                //是否客户，客户有绑定公司
                if($user_type == 2){
                    $company_bind = 1;
                }else{
                    $company_bind = 2;
                }
            } 
        }

        $mobile_bind = I("mobile_bind");
        if(!empty($mobile_bind)){
            if ($mobile_bind == 1) {
                $_filter['a.mobile'] = array(array('neq',''),array('exp','is not null'),'and');
            } else {
                $_filter['a.mobile'] = array(array('eq',''),array('exp','is null'),'or');
            }
        }
        $user_ids = [];
        // $company_bind = I("company_bind");
        if (!empty($company_bind)) {
            $sysUserBranch = M('SysUserBranch')->alias('a')
            ->join('LEFT JOIN sys_branch b ON b.id = a.branch_id')
            ->where(['a.type' =>['neq',2],'b.parent_id'=>$this->_user_session->currBranchId])
            ->field('a.user_id,a.branch_id')
            ->select();
            if (!empty($sysUserBranch)) {
                foreach ($sysUserBranch as $k => $v) {
                    array_push($user_ids, $v['user_id']);
                }
                if ($company_bind == 1) {
                    $_filter["a.id"][] = array('in',$user_ids);
                } else {
                    $_filter["a.id"][] = array('not in',$user_ids);
                }
            }else{
                if ($company_bind == 1) {
                    $_filter["a.id"] = 0;
                }
            }
        }

        $search = I('SerachComPotential');
        if (!empty($search)) {
            $where['a.name']  = array('like', '%'.$search.'%');
            $where['a.comments']  = array('like', '%'.$search.'%');
            $where['a.mobile']  = array('like', '%'.$search.'%');
            //公司搜索
            $user_ids = [];
            $sysUserBranch = M("SysUserBranch")->alias("a")
            ->join('LEFT JOIN sys_branch b ON b.id = a.branch_id')
            ->where(['a.type'=>1,'b.name'=>array('like', '%'.$search.'%'),'a.user_id'=>array("neq",0)])
            ->field("a.user_id,a.branch_id,b.name")
            ->select();
            foreach ($sysUserBranch as $k => $v) {
                array_push($user_ids, $v['user_id']);
            }
            if (!empty($user_ids)) {
                $where["a.id"]  = array('in',$user_ids);
            }
            $where['_logic']  = 'or';
            $_filter['_complex'] = $where;
        }

        $subscribe_scene = I('serach_subscribe_scene');
        if (!empty($subscribe_scene)) {
            if ($subscribe_scene != "ADD_SCENE_OTHERS") {
                $_filter['a.subscribe_scene'] = $subscribe_scene;
            }else{
                $_filter['a.subscribe_scene'] = array('in',
                    [
                    'ADD_SCENEPROFILE LINK',
                    'ADD_SCENE_PROFILE_ITEM',
                    'ADD_SCENE_PAID',
                    'ADD_SCENE_OTHERS'
                ]);
            }
        }

        if(I("get.user_type")){
            //客户档案获取可绑定用户为客户类型
            $_filter['a.user_type'] = array("in","4,5");
            $_filter['a.is_follow'] = 1;
        }
        $_filter['a.branch_id'] = $this->_user_session->currBranchId;
        $count = M("SysUser")->alias("a")->where($_filter)->count();

        if (I("order_followed_at") == "asc") {
            $_order = "followed_at asc";
        }else{
            $_order = "followed_at desc";
        }
        
        $list = M("SysUser")->alias("a")->field("a.*")->where($_filter)->page($page_index, $page_size)->order($_order)
        ->select();
        $this->_before_list($list);
        $result["total"] = $count;
        $result["rows"] = $list;
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($result));
    }
    //下载附件
    public function downloadFileAction()
    {
        if (IS_GET) {
            $url = I("url");
            $name = I("name", '下载');
            $content = file_get_contents($url);
            \Org\Net\Http::download('',$name,$content);
            // \Org\Net\Http::download($url,$name);
        }
    }
    
    //发送留言信息
    public function liuyanSendWxAction($attach_group,$content){
        // $sysUser = M("SysUser")->where(['attach_group'=>$attach_group])->find();
        $sysUser = M("SysUser")->where(['attach_group'=>$attach_group])->find();
        if ($sysUser['id'] != $this->_user_session->userId){
            $condition['user_id'] = $sysUser['id'];
        }else{
            $leader = M('SysUser')
            ->alias("a")
            ->join('LEFT JOIN sys_branch b ON b.leader_id = a.id')
            ->where("b.id = ".$this->_user_session->currBranchId)
            ->field("a.*")
            ->find();
            $condition['user_id'] = $leader['id'];
        }

        $condition['consultant'] = "留言";
        $condition['type'] = 3 ;
        $condition['reply'] = $content;
        $rst = D('ComPotential')->sendWXConsult($condition,4,$attach_group);
        $this->ajaxReturn(array('rst'=>$rst));
    }
    // 同步用户头像、昵称
    public function syncFansAction($token =''){
        $branch_id = $this->_user_session->currBranchId;
        $WxConfig = M("WxConfig")->where(['branch_id'=>$branch_id])->field("appid,appsecret")->find();
        $appid = $WxConfig['appid'];
        $appsecret = $WxConfig['appsecret'];

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
                if (D("SysBranch")->addFans($branch_id, $sys_user)){
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

    public function comCompanyValidateAction($id){
        $rst = D('ComCompany')->setDacFilter("a")
        ->where(['a.id'=>$id])
        ->find();
        if (!empty($rst)) {
            $this->ajaxReturn(array('code'=>0,'message'=>'增加标签成功'));
        }else{
            $this->ajaxReturn(array('code'=>1,'message'=>'未通过验证'));
        }
    }

    //为单个用户绑定公司
    public function bindsingleAction($id,$type = null){
        if (IS_POST) {
            if ($type == 'search') {
                $page_index = I("page/d", 1);
                $page_size = I("rows/d", 1024);
                $search = I('search');
                if (!empty($search)) {
                        $where['name']  = array('like', '%'.$search.'%');
                        $where['querykey']  = array('like', '%'.$search.'%');
                        $where['contact']  = array('like', '%'.$search.'%');
                        $where['_logic']  = 'or';
                        $condition['_complex'] = $where;
                }
                $is_bind = I('is_bind');
                if (!empty($is_bind)) {
                    $branch_ids = [];
                    $sysUserBranch = M("SysUserBranch")->alias("a")
                    ->join('LEFT JOIN sys_branch b ON b.id = a.branch_id')
                    ->join('LEFT JOIN sys_user c ON c.id = a.user_id')
                    ->where(["b.parent_id" => $this->_user_session->currBranchId,"b.type"=>1,"c.id"=>array("exp","is not null")])
                    ->field("b.*")
                    ->select();

                    foreach ($sysUserBranch as $k => $v) {
                        array_push($branch_ids, $v['id']);
                    }
                    if ($is_bind == 1) {
                        if (!empty($branch_ids)) {
                            $condition["id"] = array('in',$branch_ids);
                        }else{
                            $condition["id"] = 0;                            
                        }
                    }else{
                        if (!empty($branch_ids)) {
                            $condition["id"] = array('not in',$branch_ids);
                        }
                    }
                }
                $condition["parent_id"] = $this->_user_session->currBranchId;
                $condition["type"] = 1;
                $count = M("SysBranch")->where($condition)->field("id,name,linkman,contact")->count();
                $sysBranch = M("SysBranch")->where($condition)->page($page_index, $page_size)->field("id,name,linkman,contact")->select();
                foreach ($sysBranch as $k => $v) {
                    $sysBranch[$k]['user_count'] = M("SysUser")->alias("a")
                    ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
                    ->where(["b.branch_id" => $v['id']])->count();
                    $sysBranch[$k]['users'] = M("SysUser")->alias("a")
                    ->join('LEFT JOIN sys_user_branch b ON b.user_id = a.id')
                    ->where(["b.branch_id" => $v['id']])->field("id,head_pic,name,comments,mobile")->select();
                }
                $result["total"] = $count;
                $result["rows"] = $sysBranch;
                $this->ajaxReturn($result);
            } else {               
                $branch_ids = I('branch_ids');
                foreach ($branch_ids as $k => $v) {
                    $SysUserBranch = M("SysUserBranch")->where(["user_id"=>$id,"branch_id"=>$v,"type"=>1])->find();
                    if (empty($SysUserBranch)) {
                        M("SysUserBranch")->add(["user_id"=>$id,"branch_id"=>$v,"type"=>1]);
                    }
                }
                $this->ajaxReturn(array('code'=>0,'message'=>'绑定公司成功'));
            }
        } else {
            $this->assign("id",$id);
            $this->display('bindSingle');
        }
    }

    public function tagListAction(){
        $rst = [];
        //获取公司
        $rst = M('SysBranch')
        ->where(['id'=>$this->_user_session->currBranchId])
        ->field("id,name")
        ->find();

        $user_count = M("SysUser")
        ->where(['branch_id'=>$this->_user_session->currBranchId])
        // ->where(['branch_id'=>$this->_user_session->currBranchId,'user_type' => array("neq",USER_TYPE_COMPANY_MANAGER)])
        ->count();
        $rst['id'] = 'company'; 
        $rst['user_count'] = $user_count;
        $rst['name'] = '标签类型';        
        $rst['text'] = $rst['name']."(".$user_count.")";

        $rst['children'] = [];
        $rst['tagList'] = [];
        $tmp['id'] = 'all';
        $tmp['user_count'] = $user_count;
        $tmp['name'] = '全部';        
        $tmp['text'] = $rst['name']."(".$user_count.")";
        array_push($rst['children'], $tmp);
        //获取无标签
        $tmp['id'] = 'other'; 
        $tmp['name'] = '暂无标签';
            $condition = [];
            $condition['branch_id'] = $this->_user_session->currBranchId;
            // $condition['user_type'] = array("neq",USER_TYPE_COMPANY_MANAGER);
            $user_ids = M('SysUserRelationTag')
            ->alias('a')
            ->join('sys_target_tag as b on b.id = a.tag')
            ->where(['a.branch_id' => getBrowseBranchId(),'b.branch_id' => getBrowseBranchId(),'b.id'=>array('exp','is not null')])->getField('user_id',true);
            // $other = M('SysUserRelationTag')->alias('a')
            // ->join('sys_target_tag as b on b.id = a.tag')
            // ->where(['a.branch_id' => getBrowseBranchId(),'b.id'=>array('exp','is not null')])->getField('user_id',true);


            if ($user_ids){
                $condition["id"][] = array('not in',$user_ids);
            }
            $user_count = M("SysUser")
            ->where($condition)
            ->count();
        $tmp['user_count'] = $user_count;
        $tmp['text'] = $tmp['name']."(".$tmp['user_count'].")";
        // array_push($rst['children'], $tmp);
        array_push($rst['children'], $tmp);
        array_push($rst['tagList'], $tmp);

        //获取标签
        $children = M('SysTargetTag')
        ->where(['branch_id'=>$this->_user_session->currBranchId])
        ->order("sort asc")
        ->field("id,value as name")
        ->select();
        foreach ($children as $k => $v) {
            $user_count = M("SysUser")->alias('a')
            ->join("LEFT JOIN sys_user_relation_tag b ON b.user_id = a.id")
            ->where(['b.tag'=>$v['id'],'a.branch_id'=>$this->_user_session->currBranchId])
            // ->where(['b.tag'=>$v['id'],'a.branch_id'=>$this->_user_session->currBranchId,'a.user_type' => array("neq",USER_TYPE_COMPANY_MANAGER)])
            ->count();
            $children[$k]['user_count'] = $user_count;
            $children[$k]['text'] = $v['name']."(".$user_count.")";
            array_push($rst['children'], $children[$k]);
            array_push($rst['tagList'], $children[$k]);
        }

        // $rst['children'] = $children;
        $result[] = $rst;
        $this->ajaxReturn($result);
    }
    public function saveTagAction($id = null)
    {
        if (IS_POST) {
            if (!empty($id)) {
                $data['value'] = I('value');
                if(empty($data['value'])){
                    $this->ajaxReturn(array('code'=>1,'message'=>'标签名称不能为空！'));
                }
                $result = M("SysTargetTag")
                ->where([
                    'branch_id'=>$this->_user_session->currBranchId,
                    'value'=>$data['value'],
                    'id'=>array("neq",$id)
                ])->select();
                if($result){
                    $this->ajaxReturn(array('code'=>1,'message'=>'该标签已存在，请重新输入！'));
                }
                M('SysTargetTag')->where(['id'=>$id])->save($data);
                $this->ajaxReturn(array('code'=>0,'message'=>'编辑标签成功'));
            } else {
                $data['value'] = I('value');
                if(empty($data['value'])){
                    $this->ajaxReturn(array('code'=>1,'message'=>'标签名称不能为空！'));
                }
                $result = M("SysBranch")
                ->where([
                    'id'=>$this->_user_session->currBranchId,
                    'name'=>$data['value']
                ])->find();        
                if(!empty($result)){
                    $this->ajaxReturn(array('code'=>1,'message'=>'不能与公司名称重复'));
                }
                $result = M("SysTargetTag")
                ->where([
                    'branch_id'=>$this->_user_session->currBranchId,
                    'value'=>$data['value']
                ])->select();
                if($result){
                    $this->ajaxReturn(array('code'=>1,'message'=>'该标签已存在，请重新输入！'));
                }
                $sort = M('SysTargetTag')->where(['branch_id'=>$this->_user_session->currBranchId])->max('sort');
                $data['created_at'] = time();
                $data['branch_id'] = $this->_user_session->currBranchId;
                $data['sort'] = $sort + 1;
                M('SysTargetTag')->add($data);
            }
            $this->ajaxReturn(array('code'=>0,'message'=>'增加标签成功'));
        } else {
            if (!empty($id)) {
                $sysTargetTag = M('SysTargetTag')->where(['id'=>$id])->find();
                $this->assign('model',$sysTargetTag);
            }
            $this->display('addTag');
        }
    }
    //批量保存标签
    public function saveTagAllAction()
    {
        if (IS_POST) {
            $arr = I("arr");
            $i = 0;
            foreach ($arr as $k => $v) {
                if ($v['id']!='all' && $v['id']!='other') {
                    $data = [];
                    $data['value'] = $v['name'];
                    $data['created_at'] = time();
                    $data['branch_id'] = $this->_user_session->currBranchId;
                    $data['sort'] =  $i;
                    $i++;
                    if (!empty($v['id'])) {
                        M('SysTargetTag')->where(['id'=>$v['id']])->save($data);
                    }else{
                        M('SysTargetTag')->add($data);
                    } 
                }
            }

            $this->ajaxReturn(array('code'=>0,'message'=>'保存标签成功'));
        }
    }

    public function changeTagSortAction($id = null,$type)
    {
        $branch_id = $this->_user_session->currBranchId;
        $tags = M("SysTargetTag")->where("branch_id = $branch_id")->select();
        foreach ($tags as $k=>$v){
            if($v['sort'] == "" or $v['sort'] == null){
                $maxSort = M("SysTargetTag")->where("branch_id = $branch_id and sort != ''")->order("sort desc")->getField("sort");
                M("SysTargetTag")->where("id = ".$v['id'])->setField("sort",$maxSort +1);
            }
        }
        if ($type == 1) {
            M('SysTargetTag')->where(['id'=>$id])->setDec("sort",1);
            $sort = M('SysTargetTag')->where(['id'=>$id])->getField("sort");
            M('SysTargetTag')->where(['id'=>array('neq',$id),'sort'=>$sort])->setInc("sort",1);
            $this->ajaxReturn(array('code'=>0,'message'=>'上调标签排序成功'));
        }else{
            M('SysTargetTag')->where(['id'=>$id])->setInc("sort",1);
            $sort = M('SysTargetTag')->where(['id'=>$id])->getField("sort");
            M('SysTargetTag')->where(['id'=>array('neq',$id),'sort'=>$sort])->setDec("sort",1);
            $this->ajaxReturn(array('code'=>0,'message'=>'降低标签排序成功'));
        }
    }

    public function bindNoticeAction()
    {
        if (IS_POST) {
            //获取openid且发送模板消息
            $openids = D(CONTROLLER_NAME)->getUsersOpenid(I('post.id'));
            $this->handlerSendTemplateMessageForNotice($openids);
            var_dump($openids);die;
        }
    }
    //获取或更新备注附件
    public function getAttachGroupAction($id)
    {
        $attach_group = M('SysUser')->where(['id'=>$id])->getField('attach_group');
        //如果为空就新建备注附件码
        if (empty($attach_group)) {
            $attach_group = genUniqidKey();
            M('SysUser')->where(['id'=>$id])->save(['attach_group'=>$attach_group]);
        }
        $result['attach_group'] = $attach_group;
        $this->ajaxReturn($result);
    }

    public function showTipAction()
    {
        $this->display('show_tip');
    }
    public function usersBindCompanyAction()
    {
        if (IS_GET) {
            $companys = D(CONTROLLER_NAME)->getCompanys();
            $this->companys = $companys;
            $this->display('users_bind_company');
        } else {
            $postdata = I('post.');
            if ($postdata['company_id'] > 0 && !empty($postdata['ids'])) {
                $result = D(CONTROLLER_NAME)->handlerUsersBindCompany($postdata);
                $this->ajaxReturn($result);
            } else {
                $this->ajaxReturn(buildMessage('请选择一个公司',1));
            }
        }
    }
    public function matchViewAction($type = null)
    {
        if (IS_GET) {
            $this->title = "匹配查看";
            $this->display('bindCompany');
            // $this->assign('model',$data);
        } else {
            if ($type=='search') {
                $page_index = I("page/d", 1);
                $page_size = I("rows/d", 1024);

                $condition['_string'] = 'a.contact is not null AND a.contact!="" AND b.is_follow = 1 AND b.mobile is not null AND b.mobile!=""';
                $condition['b.branch_id'] = $this->_user_session->currBranchId;
                $condition['a.parent_id'] = $this->_user_session->currBranchId;
                $count = M('SysBranch')->where($condition)
                ->alias('a')
                ->join('LEFT JOIN sys_user b ON b.mobile = a.contact')
                ->field('a.id as company_id,a.name as company_name,a.linkman,a.contact,b.id,b.name,b.head_pic')
                ->count();
                $data = M('SysBranch')->where($condition)
                ->alias('a')
                ->join('LEFT JOIN sys_user b ON b.mobile = a.contact')
                ->field('a.id as company_id,a.name as company_name,a.linkman,a.contact,b.id,b.name,b.head_pic')
                ->page($page_index, $page_size)
                ->select();
                foreach ($data as $k => $v) {
                    $sysUserBranch = D('SysUserBranch')->where([
                        'branch_id'=>$v['company_id'],
                        'user_id'=>$v['id'],
                    ])->select();
                    if (!empty($sysUserBranch)) {
                        unset($data[$k]);
                        $count--;
                    }
                }
                $result["total"] = $count;
                $result["rows"] = array_values($data);
                $this->ajaxReturn($result);
            }elseif ($type=='init') {
                $result["total"] = 0;
                $result["rows"] = [];
                $this->ajaxReturn($result);
            }else{
                $user_ids = I('post.user_ids');
                $branch_ids = I('post.branch_ids');
                $data = [];
                $condition['user_id'] = array('in',$user_ids);
                $num1 = 0;
                $num2 = 0;
                // D('SysUserBranch')->where($condition)->delete();
                $user_data = [];
                foreach ($user_ids as $k => $v) {
                    $num1++;
                    $user_data[] = array(
                        'user_id'=>$v,
                        'type'=>1,
                        'branch_id'=>$branch_ids[$k]
                    );
                }
                D('SysUserBranch')->addAll($user_data);
                $data['code'] = 0;
                $data['message'] = "已经成功匹配".$num1."用户，未成功匹配".$num2."用户。";
                // $data['message'] = "绑定成功";
                $this->ajaxReturn($data);
            }
        }
    }

    protected function handlerSendTemplateMessageForNotice()
    {

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
    }



    protected function _before_list(&$list)
    {
        parent::_before_list($list); // TODO: Change the autogenerated stub
        // $user_type = [USER_TYPE_COMPANY_MANAGER=>'员工',USER_TYPE_CUSTOMER=>'成交客户',USER_TYPE_PROSPECTIVE=>'意向客户'];
        foreach($list as $key => $val) {
            $list[$key]['tags_value'] = D(CONTROLLER_NAME)->getGroupNames($val);
            $company_names = D(CONTROLLER_NAME)->getCompanyNames($val);
            $list[$key]['company_names'] = $company_names;

            $list[$key]['company_ids'] = D(CONTROLLER_NAME)->getCompanyIds($val);
            $list[$key]['service_man_value'] = D(CONTROLLER_NAME)->getServiceMan($val);
            $list[$key]['tag_ids'] = D(CONTROLLER_NAME)->getTagIds($val);
            if (!empty($val['comments'])) {
                $list[$key]['name'] = $val['comments']."(". $val['name'].")";
            }
            if ($val['is_follow']==1) {
                $list[$key]['followed_at'] = date("Y-m-d H:i",$val['followed_at']) ;
            }else{
                $list[$key]['followed_at'] = '-';                
            }

            if ($list[$key]['user_type']==USER_TYPE_COMPANY_MANAGER) {
                $list[$key]['user_type_value'] = '员工';
            } else {
                if (!empty($company_names)) {
                    $list[$key]['user_type_value'] = '客户';
                }else{
                    $list[$key]['user_type_value'] = '粉丝';
                }
            }
            // $scene = [
            //     'ADD_SCENE_SEARCH'=>'公众号搜索',
            //     'ADD_SCENE_ACCOUNT_MIGRATION'=>'公众号迁移',
            //     'ADD_SCENE_PROFILE_CARD'=>'名片分享',
            //     'ADD_SCENE_QR_CODE'=>'扫描二维码',
            //     'ADD_SCENEPROFILE LINK'=>'图文页内名称点击',
            //     'ADD_SCENE_PROFILE_ITEM'=>'图文页右上角菜单',
            //     'ADD_SCENE_PAID'=>'支付后关注',
            //     'ADD_SCENE_OTHERS'=>'其他'
            // ];
            $scene = [
                'ADD_SCENE_SEARCH'=>'公众号搜索',
                'ADD_SCENE_ACCOUNT_MIGRATION'=>'其他',
                'ADD_SCENE_PROFILE_CARD'=>'名片分享',
                'ADD_SCENE_QR_CODE'=>'扫描二维码',
                'ADD_SCENEPROFILE LINK'=>'其他',
                'ADD_SCENE_PROFILE_ITEM'=>'其他',
                'ADD_SCENE_PAID'=>'其他',
                'ADD_SCENE_OTHERS'=>'其他'
            ];
            $inviter = M("SysUser")
            ->alias("a")
            ->join("LEFT JOIN distribution_relation b on b.inviter_id = a.id")
            ->where(['b.openid' => $val['openid'] ])
            ->field("a.name")
            ->find();
            if (!empty($inviter)) {
                $list[$key]['superior_user'] = $inviter['name'];
            }else{
                $list[$key]['superior_user'] = '-';
            }


            if (isset($scene[$val['subscribe_scene']]) ) {
                $list[$key]['subscribe_scene_value'] = $scene[$val['subscribe_scene']];
            }else{
                $list[$key]['subscribe_scene_value'] = '-';
            }

        }
    }

    public function tagListForSelectAction(){
        $condition['branch_id'] = $this->_user_session->currBranchId;
        $tags = M("SysTargetTag")->where($condition)->field("id as value,value as text")->select();
        $this->ajaxReturn($tags);
    }

    protected function _before_detail(&$data) {
        $data['tags_value'] = D(CONTROLLER_NAME)->getGroupNames($data);
        $company_names = D(CONTROLLER_NAME)->getCompanyNames($data);
        $data['company_names'] = $company_names;
        $data['company_ids'] = D(CONTROLLER_NAME)->getCompanyIds($data);
        $data['service_man_value'] = D(CONTROLLER_NAME)->getServiceMan($data);
        $data['tag_ids'] = D(CONTROLLER_NAME)->getTagIds($data);
        $data['followed_at'] = date("Y-m-d h:i",$data['followed_at']);
        // $list[$key]['company_names'] = D(CONTROLLER_NAME)->getCompanyNames($data);
        // $list[$key]['company_ids'] = D(CONTROLLER_NAME)->getCompanyIds($data);
        // $user_type = [USER_TYPE_COMPANY_MANAGER=>'员工',USER_TYPE_CUSTOMER=>'成交客户',USER_TYPE_PROSPECTIVE=>'意向客户'];
        if ($data['user_type']==USER_TYPE_COMPANY_MANAGER) {
            $data['user_type_value'] = '员工';
        } else {
            if (!empty($company_names)) {
                $data['user_type_value'] = '客户';
            }else{
                $data['user_type_value'] = '粉丝';
            }
        }
        // $scene = [
        //     'ADD_SCENE_SEARCH'=>'公众号搜索',
        //     'ADD_SCENE_ACCOUNT_MIGRATION'=>'公众号迁移',
        //     'ADD_SCENE_PROFILE_CARD'=>'名片分享',
        //     'ADD_SCENE_QR_CODE'=>'扫描二维码',
        //     'ADD_SCENEPROFILE LINK'=>'图文页内名称点击',
        //     'ADD_SCENE_PROFILE_ITEM'=>'图文页右上角菜单',
        //     'ADD_SCENE_PAID'=>'支付后关注',
        //     'ADD_SCENE_OTHERS'=>'其他'
        // ];
        $scene = [
            'ADD_SCENE_SEARCH'=>'公众号搜索',
            'ADD_SCENE_ACCOUNT_MIGRATION'=>'其他',
            'ADD_SCENE_PROFILE_CARD'=>'名片分享',
            'ADD_SCENE_QR_CODE'=>'扫描二维码',
            'ADD_SCENEPROFILE LINK'=>'其他',
            'ADD_SCENE_PROFILE_ITEM'=>'其他',
            'ADD_SCENE_PAID'=>'其他',
            'ADD_SCENE_OTHERS'=>'其他'
        ];
        $inviter = M("SysUser")
        ->alias("a")
        ->join("LEFT JOIN distribution_relation b on b.inviter_id = a.id")
        ->where(['b.openid' => $data['openid'] ])
        ->find();
        if (!empty($inviter)) {
            $data['subscribe_scene_value'] = $inviter['name'];
        }else{
        if (isset($scene[$data['subscribe_scene']]) ) {
            $data['subscribe_scene_value'] = $scene[$data['subscribe_scene']];
        }else{
            $data['subscribe_scene_value'] = '-';
        }
            
        }
        parent::_before_detail($data);
    }

    public function getCompanyDataAction($id){
        $data['id'] = $id;
        $rst = D(CONTROLLER_NAME)->getCompanyData($data);
        $this->ajaxReturn($rst);
    }

    public function targetUpdatesAction() {
        $model = D(CONTROLLER_NAME);
        $post_data = I('post.');
        $data = [];
        $sysUser = $model->where(["id"=>array('in',$post_data['users'])])->select();
        foreach($sysUser as $key => $val){
                $tmp['id'] = $val['id'];
            if(!empty(I('post.tag_ids'))){
                if (!empty($val['tag_ids'])) {
                    $tmp['tag_ids'] = explode(",", $val['tag_ids']);
                    $tmp['tag_ids'] = array_merge($tmp['tag_ids'],I('post.tag_ids'));
                }else{
                    $tmp['tag_ids'] = I('post.tag_ids');
                }
            }
            
            $tmp['branch_id'] = getBrowseBranchId();
            $data[] = $tmp;
        }

        try {
            $model->startTrans();
            $num1 = 0;
            $num2 = 0;
            foreach($data as $key => $val) {
                $data["tag_ids"] = implode(",", $data['tag_ids']);
                if ($user_data = $model->create($val)) {
                    $updated = false;
                    $updated = $model->where("id=".$val['id'])->save($user_data);
                    $model->commit();
                    if ($updated !== false) {
                        if(!empty(I('post.tag_ids'))){
                            $tag_ids = I('post.tag_ids');
                            $tag_data = [];
                            foreach ($tag_ids as $k1 => $v1) {
                                $sysUserRelationTag = D('SysUserRelationTag')->where([
                                    'user_id'=>$val['id'],
                                    'tag'=>$v1,
                                    'branch_id'=>getBrowseBranchId()
                                ])->select();

                                if (empty($sysUserRelationTag)) {
                                    $tag_data[] = array(
                                        'user_id'=>$val['id'],
                                        'tag'=>$v1,
                                        'branch_id'=>getBrowseBranchId(),
                                        'created_at'=>time()
                                    );
                                }
                            }
                            D('SysUserRelationTag')->addAll($tag_data);
                        }
                        $num1++;
                        $this->addLog($val['id']);
                        $result_data = $this->_getLastData($val['id']);
                        if ((count($post_data['users']) - 1) == $key){
                            $this->responseJSON(array("data"=>$result_data,"code"=>0,"message"=>"已经成功为".$num1."用户添加标签，未成功".$num2."用户"));

                            // $this->responseJSON(array("data"=>$result_data,"code"=>0,"message"=>"保存成功"));
                            break;
                        }
                    } else {
                        $this->responseJSON(buildMessage("保存失败：" . $model->getError(), 1));
                        break;
                    }
                } else {
                    $this->responseJSON(buildMessage("保存失败：" . $model->getError(), 1));
                    break;
                }
            }
        } catch (\Think\Exception $e) {
            $model->rollback();
            $this->responseJSON(buildMessage("保存失败：" . $model->getError(), 1));
        }

    }


    public function assignPermissions($controller = CONTROLLER_NAME)
    {
        parent::assignPermissions($controller); // TODO: Change the autogenerated stub
        //获取标签和分组
        // $groups = D(CONTROLLER_NAME)->getBranchTarget('group');
        // $tags = D(CONTROLLER_NAME)->getBranchTarget('tag');
        // foreach($groups as $k=>$v){
        //     $groups[$k]['user_count'] = D('SysUser')->setDacFilter('a')
        //     ->where(['a.group_id'=>$v['id'],'a.mobile' => [['exp','is not null'],['exp','<> ""']]])
        //     ->count();
        // }
        // $tmp = array('id'=>0,'value' => '未分组','branch_id' => $this->_user_session->currBranchId);
        // $tmp['user_count'] = D('SysUser')
        //     ->setDacFilter('a')
        // ->where(['_string' => 'a.group_id is null or a.group_id =""',
        //     'a.branch_id' => $this->_user_session->currBranchId,'a.mobile' => [['exp','is not null'],['exp','<> ""']]])
        // ->count();
        // array_push($groups,$tmp);
        // foreach($tags as $k=>$v){
        //     $tags[$k]['user_count'] = D('SysUser')
        //     ->setDacFilter('b')
        //     ->join('right JOIN sys_user_relation_tag a ON a.user_id = b.id')
        //     ->where(['a.tag'=>$v['id'],'b.mobile' => [['exp','is not null'],['exp','<> ""'],['neq',0]]])->count();
        // }
        // $this->groups = $groups;
        // $this->tags = $tags;
    }
    protected function handlerPermissionsProcessing()
    {
        parent::handlerPermissionsProcessing();
        switch (ACTION_NAME){
            case 'bindCompany':
            case 'editTagGroup':
            case 'matchView':
                $this->_permission_action_name = 'update';
                break;
            case 'addGroup':
                $this->_permission_name = 'SysTargetGroup';
                $this->_permission_action_name = 'add';
                break;
            case 'addTag':
                $this->_permission_name = 'SysTargetTag';
                $this->_permission_action_name = 'add';
                break;
        }
    }
}