<?php

namespace EShop\Controller;

use Think\Controller;

class  ComFanController extends BaseController {
 	public function indexAction($ajax = null) {
    $data = [];
    $json_tags = [];
    $json_groups = [];
    $tags = D('SysTargetTag')->setDacFilter('a')->where(['a.branch_id'=>$this->user_branch])->select();
    $groups = D('SysTargetGroup')->setDacFilter('a')->where(['a.branch_id'=>$this->user_branch])->select();
    foreach($tags as $k=>$v){
        $tags[$k]['user_count'] =  D('SysUser')
            ->setDacFilter('b')
            ->join('right JOIN sys_user_relation_tag a ON a.user_id = b.id')
            ->where([
              'a.tag'=>$v['id'],
              'b.is_follow'=>1,
              '_string' => 'b.mobile is null or b.mobile =""',
              'a.branch_id' => $this->user_branch
            ])->count();
        $json_tags[$k]['value'] = $v['id'];
        $json_tags[$k]['text'] = $v['value'];
    }
    $data['tags'] = json_encode($tags);
    $data['json_tags'] = json_encode($json_tags);
    foreach ($groups as $k => $v) {
      $groups[$k]['user_count'] = D('SysUser')
      ->where(['group_id'=>$v['id'],'branch_id' => $this->user_branch,'is_follow'=>1,'_string' => 'mobile is null or mobile =""'])
      ->count();
      $json_groups[$k]['value'] = $v['id'];
      $json_groups[$k]['text'] = $v['value'];
    }

    $tmp = array('id'=>'other','value' => '未分组','branch_id' => $this->user_branch);
    $tmp['user_count'] = D('SysUser')
                    ->setDacFilter('a')
                    ->where([
                      'a.branch_id' => $this->user_branch,'a.is_follow'=>1,
                      '_string' => '(a.group_id is null or a.group_id ="") and (a.mobile is null or a.mobile ="")'
                    ])->count();
    array_push($groups,$tmp);
    array_push($json_groups,['value'=>0,'text'=>'未分组']);
    
    $data['groups'] = json_encode($groups);
    $data['json_groups'] = json_encode($json_groups);
    $this->assign('data',$data);
    $this->title = "用户管理";
    if (!empty($ajax)) {
      $this->display('ajax');
    } else {
      $this->display('index');
    }
 	}

  public function searchAction() {
    $condition = [
      '_string' => '(a.mobile is null OR a.mobile="")',
      // 'a.name'=>[['exp','is not null'],['exp','<> ""']],
      'a.is_follow'=>1,
      'a.branch_id'=>$this->user_branch
    ];
    $page = I("post.page");
    $name = I("post.name");
    $groupIds = I("post.groupIds");
    $tagIds = I("post.tagIds");
    $branchId = I("post.branchId");

    if (!empty($name)) {
      $condition["a.name"] = array("like","%".$name."%");
    }
    if (!empty($groupIds)) {
      $group_conditon = [];
      foreach ($groupIds as $k => $v) {
        if($v=='other'){
          //处理未分组
          unset($groupIds[$k]);
          $group_conditon[] = "a.group_id is null";
          $group_conditon[] = "a.group_id =''";

        }
      }
      if (!empty($groupIds)) {
        //处理其他分组
        $group_conditon[] = " a.group_id in (".implode(",", $groupIds).")";
      }
      $condition["_string"] .= " AND (".implode(" or ",$group_conditon).")";
    }


    if (!empty($tagIds)) {
      $where = [];
      $user_ids = [];
      foreach ($tagIds as $k => $v) {
        $where["tag"] = array('in',$v);
          $sysTargetTag = D('SysUser')->setDacFilter('a')
              ->join('sys_user_relation_tag as b on b.user_id = a.id')
              ->where($where)
              ->select();
        $tmp = array();
        foreach ($sysTargetTag as $k1 => $v1) {
          array_push($tmp, $v1['user_id']);
        }
        if (!empty($tmp)) {
          $user_ids[] = array('in',$tmp);
        }else{
          $user_ids[] = 0;
        }
      }
      $condition["a.id"] = $user_ids;
    }
    $user_ids = [];
    if (!empty($branchId)) {
      $sysUserBranch = D('SysUserBranch')->where(['branch_id' =>$branchId])->select();
      
      if (!empty($sysUserBranch)) {
          foreach ($sysUserBranch as $k => $v) {
              array_push($user_ids, $v['user_id']);
          }
          $condition["a.id"][] = array('in',$user_ids);
      }else{
        $condition["a.id"] = 0;
      }
    }
    $condition["a.user_type"] = array("neq",USER_TYPE_COMPANY_MANAGER);
    $data = D('SysUser')
        ->setDacFilter('a')
    ->where($condition)
    ->join('LEFT JOIN sys_target_group b ON b.id = a.group_id')
    ->field('b.value as group_name,a.id,a.name,a.tag_ids,a.head_pic')
    // ->limit(20*($page-1),20)
    ->order('a.followed_at desc')
    ->select();
    foreach ($data as $k => $v) {
      //设置公司名称
      $condition = ['b.user_id' => $v['id']];
      $sysBranch = D('SysBranch')
      ->alias('a')
      ->join('LEFT JOIN sys_user_branch b ON b.branch_id = a.id')
      ->where($condition)
      ->field('a.name')
      ->select();
      $branch_name = [];
      foreach ($sysBranch as $k1 => $v1) {
        if ($k1 < 2) {
          $branch_name[] = $v1['name'];
        }elseif($k1 == 2){
          $branch_name[] = "……";
        }
      }
      $data[$k]['branch_name'] = implode(' ',$branch_name);
      //设置未分组
      if (empty($data[$k]['group_name'])) {
        $data[$k]['group_name'] = "未分组";
      }
      //设置标签
      $data[$k]['tags'] = D('SysTargetTag')->where(['b.user_id'=>$v['id']])
      ->setDacFilter('a')
      ->join('LEFT JOIN sys_user_relation_tag b ON b.tag = a.id')
      ->field('a.value')
      ->select();
    }
    $this->ajaxReturn($data);
  }

  public function addGroupAction() {
    $data = [];
    $data['value'] = I("post.name");
    $data['branch_id'] = $this->user_branch;
    $data['created_at'] = time();
    $sysTargetGroup = D('SysTargetGroup')->where(['branch_id'=>$this->user_branch,'value'=>$data['value']])->select();
    if (!empty($sysTargetGroup)){
      $data['code'] = 1;
      $data['message'] = "已有该分组存在，请重新命名";
      return $this->ajaxReturn($data);
    }
    $last_id = D('SysTargetGroup')->add($data);
    $data = [];
    if ($last_id) {
      $data['code'] = 0;
      $json_groups = [];
      $groups = D('SysTargetGroup')->where(['branch_id'=>$this->user_branch])->select();
      foreach ($groups as $k => $v) {
        $groups[$k]['user_count'] = D('SysUser')->where(['group_id'=>$v['id']])->count();
        $json_groups[$k]['value'] = $v['id'];
        $json_groups[$k]['text'] = $v['value'];
      }
      $data['groups'] = $groups;
      $data['json_groups'] = $json_groups;
    } else {
      $data['code'] = 1;
      $data['message'] = "已有该分组存在，请重新命名";
    }
    $this->ajaxReturn($data);    
  }
  public function addTagAction() {
    $data = [];
    $data['value'] = I("post.name");
    $data['branch_id'] = $this->user_branch;
    $data['created_at'] = time();

    $sysTargetTag = D('SysTargetTag')->where(['branch_id'=>$this->user_branch,'value'=>$data['value']])->select();
    if (!empty($sysTargetTag)){
      $data['code'] = 1;
      $data['message'] = "已有该标签存在，请重新命名";
      return $this->ajaxReturn($data);
    }
    $last_id = D('SysTargetTag')->add($data);
    $data = [];
    if ($last_id) {
      $data['message'] = "添加标签成功";
      $data['code'] = 0;

      $temp_tag = [];
      $branch = M('SysBranch')
          ->where(['id'=>getBrowseBranchId()])
          ->field("id,name as value")
          ->find();
      $user_count = M("SysUser")
          ->where(['branch_id'=>getBrowseBranchId(),'user_type' => array("neq",USER_TYPE_COMPANY_MANAGER)])
          ->count();
      $branch['id'] = 'all'; 
      $branch['value'] = '全部';
      $branch['user_count'] = $user_count;
      array_push($temp_tag, $branch);

      $json_tags = [];
      $tags = D('SysTargetTag')->where(['branch_id'=>$this->user_branch])->select();
      foreach ($tags as $k => $v) {
        // $tags[$k]['user_count'] = D('SysUserRelationTag')->where(['tag'=>$v['id']])->count();
        $tags[$k]['user_count'] = M("SysUser")->alias('a')
            ->join("LEFT JOIN sys_user_relation_tag b ON b.user_id = a.id")
            ->where(['b.tag'=>$v['id'],'a.branch_id'=>getBrowseBranchId(),'a.user_type' => array("neq",USER_TYPE_COMPANY_MANAGER)])
            ->count();
        
        $json_tags[$k]['value'] = $v['id'];
        $json_tags[$k]['text'] = $v['value'];
        
      }
      foreach ($tags as $k => $v) {
        array_push($temp_tag, $v);
      }
      $tmp['id'] = 'other'; 
      $tmp['value'] = '暂无标签';
      $condition = [];
      $condition['branch_id'] = getBrowseBranchId();
      $condition['user_type'] = array("neq",USER_TYPE_COMPANY_MANAGER);
      $user_ids = M('SysUserRelationTag')->where(['branch_id'=>getBrowseBranchId()])->getField('user_id',true);
      if ($user_ids){
          $condition["id"][] = array('not in',$user_ids);
      }
      $user_count = M("SysUser")->where($condition)->count();
      $tmp['user_count'] = $user_count;
      array_push($temp_tag, $tmp);

      $data['tags'] = $temp_tag;
      $data['origin_tags'] = $tags;
      $data['json_tags'] = $json_tags;
    } else {
      $data['code'] = 1;
      $data['message'] = "添加失败";
    }
    $this->ajaxReturn($data);
  }

  public function editTagGroupAction() {
    $user_ids = I("post.user_ids");
    $tag_ids = I("post.tag_ids");
    $data = [];
    $tmp = [];
    // $data['group_id'] = I("post.group_id");
    // if (!empty($tag_ids)) {
    //   $data['tag_ids'] = implode(",", $tag_ids);
    // }
    $condition['branch_id'] = $this->user_branch;
    $condition['id'] = array('in',$user_ids);
    $sysUser = D('SysUser')->where($condition)->select();
    foreach($sysUser as $k => $v){
      $condition['id'] = $v['id'];
      if(!empty(I('post.tag_ids'))){
        if (!empty($v['tag_ids'])) {
            $tmp['tag_ids'] = explode(",", $v['tag_ids']);
            $tmp['tag_ids'] = implode(",", array_merge($tmp['tag_ids'],I('post.tag_ids')));
        }else{
            $tmp['tag_ids'] = implode(",",I('post.tag_ids'));
        }
      }
      if(!empty(I('post.group_id'))){
        if (I('post.group_id') == 'other') {
            $tmp['group_id'] = null;
        }else{
            $tmp['group_id'] = I('post.group_id');
        }
      }
      $tmp['branch_id'] = $this->user_branch;
      $data[] = $tmp;
      D('SysUser')->where($condition)->save($tmp);
    }
    // var_dump($data);
    // $condition['user_id'] = $condition['id'];
    // unset($condition['id']);
    $tag_data = [];
    foreach ($user_ids as $k => $v) {
      foreach ($tag_ids as $k1 => $v1) {
        $sysUserRelationTag = D('SysUserRelationTag')->where([
          'user_id'=>$v,
          'tag'=>$v1,
          'branch_id'=>$this->user_branch
        ])->select();

        if (empty($sysUserRelationTag)) {
          $tag_data[] = array(
            'user_id'=>$v,
            'tag'=>$v1,
            'branch_id'=>$this->user_branch,
            'created_at'=>time()
          );
        }
      }
    }
    D('SysUserRelationTag')->addAll($tag_data);
    $data['code'] = 0;
    $this->ajaxReturn($data);
  }

  public function editUserTypeAction() {
    $user_ids = I("post.user_ids");
    $user_type = I("post.user_type");
    $data = [];
    $data['user_type'] = $user_type;
    $condition['branch_id'] = $this->user_branch;
    $condition['id'] = array('in',$user_ids);
    $last_id = D('SysUser')->where($condition)->save($data);

    $data['code'] = 0;
    $this->ajaxReturn($data);
  }

    public function selectCompanyAction($init = 0, $name = null, $type = null)
    {
        $condition = [];
        if (!empty($name)) {
            $condition['a.name'] = array('LIKE', '%' . $name . '%');
        }
        $condition['a.parent_id'] = $this->user_branch;
        $condition['a.type'] = array('neq', 2);
        $this->company = D('sysBranch')
            ->setDacFilter('a')
            ->field('a.id as value,a.name as text,a.linkman,a.contact')
            ->where($condition)->select();
        $this->init = $init;
        if ($type == 1) {
            $this->display('select_company-1');
        } else {
            $this->display('select_company');
        }
    }

  public function bindCompanyAction() {
    if (IS_GET) {
      $user_ids = I("user_ids");
      $data = D('SysUser')->where([
        'id'=>array('in',$user_ids),
        'branch_id'=>$this->user_branch
      ])->select();
      $this->is_single=0;
      if (count($data) == 1) {
        $data = $data[0];
        if (isset($data['followed_at'])) {
          $data['followed_at'] = date('Y-m-d H:i:s',$data['followed_at']);
        }
        if (isset($data['binded_at'])) {
          $data['binded_at'] = date('Y-m-d H:i:s',$data['binded_at']);
        }
        $this->is_single=1;
      }

      $this->title = "关联公司";

      $condition = ['b.user_id' => $v['id']];
      $sysBranch = D('SysBranch')
        ->alias('a')
        ->join('LEFT JOIN sys_user_branch b ON b.branch_id = a.id')
        ->where($condition)
        ->field('a.name')
        ->select();
      $this->company_ids = "关联公司";
      $this->assign('model',$data);
      // var_dump($data);
      $this->display('bind_company');
    } else {
      $user_ids = I('post.user_ids');
      $branch_ids = I('post.branch_ids');
      $data = [];
      $condition['user_id'] = array('in',$user_ids);
      // D('SysUserBranch')->where($condition)->delete();
      $user_data = [];
      foreach ($user_ids as $k => $v) {
        foreach ($branch_ids as $k1 => $v1) {
          $sysUserBranch = D('SysUserBranch')->where(['user_id'=>$v,'branch_id'=>$v1])->select();
          if (empty($sysUserBranch)) {
            $user_data[] = array(
              'user_id'=>$v,
              'type'=>1,
              'branch_id'=>$v1
            );
          }
        }
      }
      D('SysUserBranch')->addAll($user_data);
      $data['code'] = 0;
      $this->ajaxReturn($data);
    }
  }

    public function notifyUserAction()
    {
        if (IS_GET) {
            $user_ids = I("user_ids");
            $data = D('SysUser')
                ->setDacFilter('a')
                ->where([
                'a.id' => array('in', $user_ids),
                'a.branch_id' => $this->user_branch
            ])->field('a.id,a.name,a.comments,a.head_pic')
                ->select();

            $this->title = "绑定通知";
            $this->assign('model', $data);
            $this->display('notify_personnel');
        } else {
            //获取openid且发送模板消息
            $user_ids = I("user_ids");
            $condition['id'] = array('in', $user_ids);
            $users = D('SysUser')->where($condition)->field('id,name,openid')->select();
            $result = $this->handlerSendTemplateMessageForNotice($users);
            $this->ajaxReturn($result);
        }
    }

    public function notifyStaffAction($users){
        $this->handlerSendTemplateMessageForNotice($users);
    }

  public function handlerSendTemplateMessageForNotice($users)
  {
     $template_id =  M('WxConfig')->where('branch_id = '.$this->user_branch)->getField('wx_notice_bind_company');
     if ($template_id) {
         $message = array();
         // $rst = array();
         $message["template_id"] = $template_id;
         // $message["url"] = str_replace('shop','shop'.$this->user_branch,).'/User/index.html';
         $type = I("post.type");
         if($type == "addStaff"){
             $mobile = I("post.mobile");
             $message["url"] = WEB_ROOT.'/Organization/bound_staff/mobile/'.$mobile;
         }else{
             $message["url"] = WEB_ROOT.'/User/index.html';
         }

          foreach($users as $key => $value) {
              $body = array();
              $body["first"]["value"]    = '绑定手机号，即可使用更多服务功能';
              $body["keyword1"]["value"] = $value['name']; //编号
              $body["keyword2"]["value"] = '绑定手机号'; //类型
              $body["remark"]["value"] = '点击进入页面快速绑定手机号';
              $message["body"] = $body;
              $message["openid"] = $value['openid'];
              // array_push($rst, $message);
              send_wx_message($message);
          }
          // var_dump($rst);
         return ['code'=>0,'message'=>'已发送绑定通知'];
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
                 M('WxConfig')->where('branch_id = '.$this->user_branch)->setField('wx_notice_bind_company',$template_id);
                 return $this->handlerSendTemplateMessageForNotice($users);
             }
         }
     }
  }

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
    protected function handlerPermissionsProcessing()
    {
        parent::handlerPermissionsProcessing();
        switch (ACTION_NAME){
            case 'bindCompany':
                $this->_permission_name = 'ComFans';
                $this->_permission_action_name = 'update';
                break;
            case 'editTagGroup':
                $this->_permission_name = 'ComFans';
                $this->_permission_action_name = 'targetUpdates';
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