<?php

namespace EShop\Controller;

use Faker\Provider\Base;
use Think\Controller;

class ComPotentialController extends BaseController {
  public function indexAction($ajax = null) {
    $data = [];
    $json_tags = [];
    $json_groups = [];
    $tags = D('SysTargetTag')->setDacFilter('a')->where(['a.branch_id'=>$this->user_branch])->select();
    $groups = D('SysTargetGroup')->setDacFilter('a')->where(['a.branch_id'=>$this->user_branch])->select();
    // $condition["a.user_type"] = array("neq",USER_TYPE_COMPANY_MANAGER);
    $temp_tag = [];
    $branch = M('SysBranch')
        ->where(['id'=>getBrowseBranchId()])
        ->field("id,name as value")
        ->find();
    $user_count = M("SysUser")
        // ->where(['branch_id'=>getBrowseBranchId(),'user_type' => array("neq",USER_TYPE_COMPANY_MANAGER)])
        ->where(['branch_id'=>getBrowseBranchId()])
        ->count();
    $branch['id'] = 'all'; 
    $branch['value'] = '全部';
    $branch['user_count'] = $user_count;
    array_push($temp_tag, $branch);

    foreach($tags as $k=>$v){
        $tags[$k]['user_count'] = M("SysUser")->alias('a')
            ->join("LEFT JOIN sys_user_relation_tag b ON b.user_id = a.id")
            // ->where(['b.tag'=>$v['id'],'a.branch_id'=>getBrowseBranchId(),'a.user_type' => array("neq",USER_TYPE_COMPANY_MANAGER)])
            ->where(['b.tag'=>$v['id'],'a.branch_id'=>getBrowseBranchId()])
            ->count();
        // $tags[$k]['user_count'] = M('SysUser')
        //         ->alias('a')
        //         ->join('LEFT JOIN sys_user_relation_tag b ON b.user_id = a.id')
        //         ->where([
        //           'b.tag'=>$v['id'],
        //           'a.user_type' => array("neq",USER_TYPE_COMPANY_MANAGER),
        //           // 'b.is_follow'=>1,
        //           // '_string' => 'b.mobile is not null and b.mobile <>""',
        //           'a.branch_id' => $this->user_branch
        //         ])->count();
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
    // $condition['user_type'] = array("neq",USER_TYPE_COMPANY_MANAGER);
    $user_ids = M('SysUserRelationTag')->where(['branch_id'=>getBrowseBranchId()])->getField('user_id',true);
    if ($user_ids){
        $condition["id"][] = array('not in',$user_ids);
    }
    $user_count = M("SysUser")->where($condition)->count();
    $tmp['user_count'] = $user_count;
    array_push($temp_tag, $tmp);
    foreach ($temp_tag as $k=>$v){
        if(mb_strlen($v['value'] > 5)){
            $temp_tag[$k]['value'] = mb_substr($v['value'],0,5)."...";
        }
    }
    foreach ($tags as $k=>$v){
        if(mb_strlen($v['value'] > 5)){
            $tags[$k]['value'] = mb_substr($v['value'],0,5)."...";
        }
    }
      foreach ($json_tags as $k=>$v){
          if(mb_strlen($v['value'] > 5)){
              $json_tags[$k]['value'] = mb_substr($v['value'],0,5)."...";
          }
      }
    $data['tags'] = json_encode($temp_tag);
    $data['origin_tags'] = json_encode($tags);
    $data['json_tags'] = json_encode($json_tags);

    foreach ($groups as $k => $v) {
      $groups[$k]['user_count'] = D('SysUser')
                  ->setDacFilter('a')
                  ->where([
                    'a.group_id'=>$v['id'],
                    // 'a.is_follow'=>1,
                    // '_string' => 'a.mobile is not null and a.mobile <>""',
                    'a.branch_id' => $this->user_branch
                  ])->count();
      $json_groups[$k]['value'] = $v['id'];
      $json_groups[$k]['text'] = $v['value'];
    }

    $tmp = array('id'=>'other','value' => '未分组','branch_id' => $this->user_branch);
    $tmp['user_count'] = M('SysUser')
        ->alias('a')
        ->where([
          'a.branch_id' => $this->user_branch,
          // 'a.is_follow'=>1,
          // '_string' => '(a.group_id is null or a.group_id ="") and (a.mobile is not null AND a.mobile!="")'
        ])->count();
    array_push($groups,$tmp);
    array_push($json_groups,['value'=>0,'text'=>'未分组']);

    $data['groups'] = json_encode($groups);
    $data['json_groups'] = json_encode($json_groups);
    $this->assign('data',$data);
    $this->title = "用户列表";
    if (!empty($ajax)) {
      $this->display('comFan/ajax');
    } else {
      $this->display('index');
    }
  }
    //发送留言信息
    public function liuyanSendWxAction($attach_group,$content){
        $sysUser = M("SysUser")->where(['attach_group'=>$attach_group])->find();
        if ($sysUser['id'] != $_SESSION['user_id']){
            $condition['user_id'] = $sysUser['id'];
        }else{
            $leader = M('SysUser')
            ->alias("a")
            ->join('LEFT JOIN sys_branch b ON b.leader_id = a.id')
            ->where("b.id = ".getBrowseBranchId())
            ->field("a.*")
            ->find();
            $condition['user_id'] = $leader['id'];
        }

        $condition['consultant'] = "留言";
        $condition['type'] = 3 ;
        // $condition['user_id'] = $sysUser['id'];
        $condition['reply'] = $content;
        $rst = D('ComPotential')->sendWXConsult($condition,4,$attach_group);
        $this->ajaxReturn(array('rst'=>$rst));
    }
    public function editTagGroupAction(){
        A("ComFan")->editTagGroupAction();
    }

    //获取或更新备注附件
    public function getAttachGroupAction($id = null)
    {
        if (empty($id)) {
            $id = $_SESSION['user_id'];
        }
        $attach_group = M('SysUser')->where(['id'=>$id])->getField('attach_group');
        //如果为空就新建备注附件码
        if (empty($attach_group)) {
            $attach_group = genUniqidKey();
            M('SysUser')->where(['id'=>$id])->save(['attach_group'=>$attach_group]);
        }
        $result['attach_group'] = $attach_group;
        $this->ajaxReturn($result);
    }

    public function searchAction()
    {
        $condition = [
            // '_string' => '(a.mobile is not null AND a.mobile!="")',
            // 'a.name'=>[['exp','is not null'],['exp','<> ""']],
            // 'a.is_follow' => 1,
            // 'a.branch_id' => $this->user_branch
        ];
        $page = I("post.page");
        $name = I("post.name");
        // $groupIds = I("post.groupIds");
        $tagIds = I("post.tagIds");
        //关注公众号筛选
        $is_follow = I("is_follow");
        if(!empty($is_follow)){
            if ($is_follow == 1) {
                $condition['a.is_follow'] = 1;
            } else {
                $condition['a.is_follow'] = 0;
            }
        }

        //用户类型筛选
        $user_type = I("user_type");
        $company_bind = null;
        if(!empty($user_type)){
            //是否员工
            if ($user_type == 1) {
                $condition['a.user_type'] = USER_TYPE_COMPANY_MANAGER;
            }else{
                $condition['a.user_type'] = array('neq',USER_TYPE_COMPANY_MANAGER);
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
                $condition['a.mobile'] = array(array('neq',''),array('exp','is not null'),'and');
            } else {
                $condition['a.mobile'] = array(array('eq',''),array('exp','is null'),'or');
            }
        }
        $user_ids = [];
        if (!empty($company_bind)) {
            $sysUserBranch = M('SysUserBranch')->alias('a')
            ->join('LEFT JOIN sys_branch b ON b.id = a.branch_id')
            ->where(['a.type' =>['neq',2],'b.parent_id'=>$this->user_branch])
            ->field('a.user_id,a.branch_id')
            ->select();
            if (!empty($sysUserBranch)) {
                foreach ($sysUserBranch as $k => $v) {
                    array_push($user_ids, $v['user_id']);
                }
                if ($company_bind == 1) {
                    $condition["a.id"][] = array('in',$user_ids);
                } else {
                    $condition["a.id"][] = array('not in',$user_ids);
                }
            }else{
                if ($company_bind == 1) {
                    $condition["a.id"] = 0;
                }
            }
        }

        if (!empty($name)) {
            $condition["a.name"] = array("like", "%" . $name . "%");
        }
        if (!empty($tagIds)) {
            $where = [];
            $user_ids = [];
            foreach ($tagIds as $k => $v) {
                if (in_array("other", $v)) {
                    $v = array_diff($v,['other']);
                    $other = M('SysUserRelationTag')->where(['branch_id' => $this->user_branch])->getField('user_id',true);
                    $tmp = [];
                    if (!empty($v)) {
                        $where["b.tag"] = array('in', $v);
                        $sysTargetTag = D('SysUser')->setDacFilter('a')
                            ->join('sys_user_relation_tag as b on b.user_id = a.id')
                            ->where($where)
                            ->getField('b.user_id',true);
                        $tmp = $sysTargetTag;
                        $other = array_diff($other,$tmp);
                    }
                    if (!empty($other)) {
                        $user_ids[] = array('not in', $other);
                    }
                } else {
                    $where["b.tag"] = array('in', $v);
                    $sysTargetTag = D('SysUser')->setDacFilter('a')
                        ->join('sys_user_relation_tag as b on b.user_id = a.id')
                        ->where($where)
                        ->getField('user_id',true);
                    $tmp = $sysTargetTag;
                    if (!empty($tmp)) {
                        $user_ids[] = array('in', $tmp);
                    } else {
                        $user_ids[] = 0;
                    }
                }
            }
            $condition["a.id"][] = $user_ids;
        }
        // $user_ids = [];
        // if (!empty($branchId)) {
        //     $sysUserBranch = D('SysUserBranch')->where(['branch_id' => $branchId])->select();
        //     if (!empty($sysUserBranch)) {
        //         foreach ($sysUserBranch as $k => $v) {
        //             array_push($user_ids, $v['user_id']);
        //         }
        //         $condition["a.id"][] = array('in', $user_ids);
        //     } else {
        //         $condition["a.id"] = 0;
        //     }
        // }
        
        // $condition["a.user_type"] = array("neq",USER_TYPE_COMPANY_MANAGER);
        $condition["a.branch_id"] = getBrowseBranchId();
        $data = M('SysUser')
            ->alias('a')
            ->where($condition)
            // ->join('LEFT JOIN sys_target_group b ON b.id = a.group_id')
            ->field('a.id,a.name,a.mobile,a.tag_ids,a.head_pic,user_type')
            ->limit(50*($page-1),50)
            ->order('a.followed_at desc')
            ->select();
        foreach ($data as $k => $v) {
            // //设置公司名称
            // $condition = ['b.user_id' => $v['id']];
            // $condition['a.type'] = array('neq',2);
            // $sysBranch = D('SysBranch')
            //     ->alias('a')
            //     ->join('LEFT JOIN sys_user_branch b ON b.branch_id = a.id')
            //     ->where($condition)
            //     ->field('a.name')
            //     ->select();
            // $branch_name = [];
            // foreach ($sysBranch as $k1 => $v1) {
            //     if ($k1 < 2) {
            //         $branch_name[] = $v1['name'];
            //     } elseif ($k1 == 2) {
            //         $branch_name[] = "……";
            //     }
            // }
            // $data[$k]['branch_name'] = implode(' ', $branch_name);
            // //设置未分组
            // if (empty($data[$k]['group_name'])) {
            //     $data[$k]['group_name'] = "未分组";
            // }
            //设置标签
            $data[$k]['tags'] = D('SysTargetTag')->where(['b.user_id' => $v['id']])
                ->setDacFilter('a')
                ->join('LEFT JOIN sys_user_relation_tag b ON b.tag = a.id')
                ->field('a.value')
                ->select();
            //设置用户类型
            if ($data[$k]['user_type']==USER_TYPE_COMPANY_MANAGER) {
                $data[$k]['user_type_value'] = '员工';
            } else {
                $condition = [];
                $condition['a.id'] = $v['id'];
                $condition['c.type'] = array('neq',2);
                $condition['c.parent_id'] = $this->user_branch;
                $branchs = D("SysUser")->alias('a')
                    ->field('c.*')
                    ->join('sys_user_branch as b on b.user_id = a.id')
                    ->join('sys_branch as c on c.id = b.branch_id')
                    ->where($condition)
                    ->select();

                if (!empty($branchs)) {
                    $data[$k]['user_type_value'] = '客户';
                }else{
                    $data[$k]['user_type_value'] = '粉丝';
                }
            }
        }
        $this->ajaxReturn($data);
    }

    public function getCompanyDataAction($id){
        $condition = [];
        $condition['a.id'] = $id;
        $condition['c.type'] = array('neq',2);
        $condition['c.parent_id'] = getBrowseBranchId();
        $rst = D("SysUser")->alias('a')
            ->field('c.*')
            ->join('sys_user_branch as b on b.user_id = a.id')
            ->join('sys_branch as c on c.id = b.branch_id')
            ->where($condition)
            ->select();
        $this->ajaxReturn($rst);
    }

    
    public function tagManageAction(){
        if (IS_GET) {
            $this->display("tagManage");
        } else {
            $rst['tag_list'] = [];
            $branch = M('SysBranch')
            ->where(['id'=>getBrowseBranchId()])
            ->field("id,name as value")
            ->find();
            $user_count = M("SysUser")
            // ->where(['branch_id'=>getBrowseBranchId(),'user_type' => array("neq",USER_TYPE_COMPANY_MANAGER)])
            ->where(['branch_id'=>getBrowseBranchId()])
            ->count();
            $branch['id'] = 'all'; 
            $branch['user_count'] = $user_count;
            array_push($rst['tag_list'], $branch);

            $condition = [];
            $condition['branch_id'] = getBrowseBranchId();
            $tag_list = M("SysTargetTag")->field('*')->where($condition)->select();
            foreach ($tag_list as $k => $v) {
               $tag_list[$k]['user_count'] = M("SysUser")->alias('a')
                ->field('a.id,value as name')
                ->join('sys_user_relation_tag as b on b.user_id = a.id')
                // ->where(['b.tag'=> $v['id'],'a.branch_id'=>getBrowseBranchId(),'a.user_type' => array("neq",USER_TYPE_COMPANY_MANAGER)])
                ->where(['b.tag'=> $v['id'],'a.branch_id'=>getBrowseBranchId()])
                ->count();
            }
            foreach ($tag_list as $k => $v) {
                array_push($rst['tag_list'], $v);
            }
            $tmp['id'] = 'other'; 
            $tmp['value'] = '暂无标签';
            $condition = [];
            $condition['branch_id'] = getBrowseBranchId();
            // $condition['user_type'] = array("neq",USER_TYPE_COMPANY_MANAGER);
            $user_ids = M('SysUserRelationTag')->where(['branch_id'=>getBrowseBranchId()])->getField('user_id',true);
            if ($user_ids){
                $condition["id"][] = array('not in',$user_ids);
            }
            $user_count = M("SysUser")->where($condition)->count();
            $tmp['user_count'] = $user_count;
            array_push($rst['tag_list'], $tmp);
            foreach ($rst['tag_list'] as $k=>$v){
                if(mb_strlen($v['value'] > 5)){
                    $rst['tag_list'][$k]['value'] = mb_substr($v['value'],0,5)."...";
                }
            }
            $this->ajaxReturn($rst);
        }
    }

    public function addTagAction() {
        $sort = M('SysTargetTag')->where(['branch_id'=>getBrowseBranchId()])->max('sort');
        $data['value'] = I('value');
        $data['created_at'] = time();
        $data['branch_id'] = getBrowseBranchId();
        $data['sort'] = $sort+1;
        if(empty($data['value'])){
            $this->ajaxReturn(array('code'=>1,'message'=>'标签名称不能为空'));
        }
        $result = M("SysBranch")
        ->where([
            'id'=>getBrowseBranchId(),
            'name'=>$data['value']
        ])->find();        
        if(!empty($result)){
            $this->ajaxReturn(array('code'=>1,'message'=>'不能与公司名称重复'));
        }
        $result = M("SysTargetTag")
        ->where([
            'branch_id'=>getBrowseBranchId(),
            'value'=>$data['value']
        ])->select();
        if($result){
            $this->ajaxReturn(array('code'=>1,'message'=>'该标签已存在，请重新输入！'));
        }
        $last_id = M('SysTargetTag')->add($data);
        $data = [];
        if ($last_id) {
          $data['message'] = "添加标签成功";
          $data['code'] = 0;
          $data['last_id'] = $last_id;
          $temp_tag = [];
          $branch = M('SysBranch')
              ->where(['id'=>getBrowseBranchId()])
              ->field("id,name as value")
              ->find();
          $user_count = M("SysUser")
              // ->where(['branch_id'=>getBrowseBranchId(),'user_type' => array("neq",USER_TYPE_COMPANY_MANAGER)])
            ->where(['branch_id'=>getBrowseBranchId()])
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
                // ->where(['b.tag'=>$v['id'],'a.branch_id'=>getBrowseBranchId(),'a.user_type' => array("neq",USER_TYPE_COMPANY_MANAGER)])
                ->where(['b.tag'=>$v['id'],'a.branch_id'=>getBrowseBranchId()])
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
          // $condition['user_type'] = array("neq",USER_TYPE_COMPANY_MANAGER);
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

    public function  updateTagAction($id){
        $data['value'] = I('value');
        if(empty($data['value'])){
            $this->ajaxReturn(array('code'=>1,'message'=>'标签名称不能为空'));
        }
        $branch_name = M("SysBranch")->where("id = ".getBrowseBranchId())->getField("name");
        if($data['value'] == $branch_name){
            $this->ajaxReturn(array('code'=>1,'message'=>'不能与公司名称重复'));
        }
        $result = M("SysTargetTag")
        ->where([
            'branch_id'=>getBrowseBranchId(),
            'value'=>$data['value'],
            'id'=>array("neq",$id)
        ])->select();
        if($result){
            $this->ajaxReturn(array('code'=>1,'message'=>'该标签已存在，请重新输入！'));
        }
        M("SysTargetTag")->where(['id'=>$id])->save(['value'=>$data['value']]);
        $data['code'] = 0;
        $this->ajaxReturn($data);
    }

    public function deleteTagAction($id){
        M("SysTargetTag")->where(['id'=>$id])->delete();
        $user_ids = [];
        $sysUserRelationTag = M("SysUserRelationTag")->where(['tag'=>$id])->select();
        foreach ($sysUserRelationTag as $k => $v) {
            array_push($user_ids,$v['id']);
        }
        M("SysUserRelationTag")->where(['tag'=>$id])->delete();
        foreach ($user_ids as $k => $v) {
            $user_data = [];
            $user_data['tag_ids'] = D('SysUser')->getTagIds($v['id']);
            M("SysUser")->where(['id'=>$v['id']])->save($user_data);
        }
        $data['code'] = 0;
        $this->ajaxReturn($data);
    }

    public function getUserTagAction($id){
        $condition = [];
        $condition['b.user_id'] = $id;
        $condition['b.branch_id'] = getBrowseBranchId();
        $tag_ids = [];
        $rst['user_tag'] = M("SysTargetTag")->alias('a')
            ->field('a.id,value as name')
            ->join('sys_user_relation_tag as b on b.tag = a.id')
            ->where($condition)
            ->select();
        foreach ($rst['user_tag'] as $k => $v) {
            array_push($tag_ids, $v['id']);
        }
        $condition = [];
        $condition['branch_id'] = getBrowseBranchId();
        $rst['tag_list'] = M("SysTargetTag")->field('*')->where($condition)->select();
        foreach ($rst['tag_list'] as $k => $v) {
            if (in_array($v['id'], $tag_ids)) {
                $rst['tag_list'][$k]['is_checked'] = 1;
            } else {
                $rst['tag_list'][$k]['is_checked'] = 0;
            }
        }
        $this->ajaxReturn($rst);
    }
    public function setUserTagAction($id){
        $tag_ids = I("post.tag_ids");
        $data = [];
        $tmp = [];
        $condition['branch_id'] = $this->user_branch;
        $condition['id'] = $id;
        if(!empty(I('post.tag_ids'))){
            $tmp['tag_ids'] = implode(",",I('post.tag_ids'));
            D('SysUser')->where($condition)->save($tmp);
        }

        M('SysUserRelationTag')->where([
          'user_id'=>$id,
          'branch_id'=>$this->user_branch
        ])->delete();
        $tag_data = [];
        foreach ($tag_ids as $k => $v) {
            $tag_data[] = array(
                'user_id'=>$id,
                'tag'=>$v,
                'branch_id'=>$this->user_branch,
                'created_at'=>time()
            );
        }
        D('SysUserRelationTag')->addAll($tag_data);
        $data['code'] = 0;
        $this->ajaxReturn($data);
    }


    public function bindCompanyAction($init = 0, $name = null, $id = null)
    {
        if (IS_GET) {
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
            $this->id = $id;
            $this->display('bindCompany');
        } else {
            $user_ids = I('post.user_ids');
            $branch_ids = I('post.branch_ids');
            $data = [];
            $condition['user_id'] = array('in',$user_ids);
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

    public function unbindCompanyAction()
    {
        if (IS_POST) {
            $postdata = I('post.');
            $condition['user_id'] = $postdata['id'];
            $condition['branch_id'] = $postdata['company_id'];
            $result = M("SysUserBranch")->where($condition)->delete();
            $result = $result ? buildMessage('该公司已成功解除绑定',0) : buildMessage('解除绑定失败,请重试',1);
            $this->ajaxReturn($result);
        }
    }

    public function notifyUserAction()
    {
        if (IS_POST) {
            //获取openid且发送模板消息
            $user_ids = I("user_ids");
            $condition['id'] = array('in', $user_ids);
            $users = D('SysUser')->where($condition)->field('id,name,openid')->select();
            $result = $this->handlerSendTemplateMessageForNotice($users);
            $this->ajaxReturn($result);
        }
    }

  protected function handlerSendTemplateMessageForNotice($users)
  {
     $template_id =  M('WxConfig')->where('branch_id = '.$this->user_branch)->getField('wx_notice_bind_company');
     if ($template_id) {
         $message = array();
         // $rst = array();
         $message["template_id"] = $template_id;
         // $message["url"] = str_replace('shop','shop'.$this->user_branch,).'/User/index.html';
         $message["url"] = WEB_ROOT.'/User/index.html';
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

    public function matchViewAction($type = null)
    {
        if (IS_GET) {
            $condition = [
                '_string' => 'a.contact is not null AND a.contact!=""',
                'b.name' => [['exp', 'is not null'], ['exp', '<> ""']],
                'b.is_follow' => 1,
                'b.branch_id' => $this->user_branch
            ];
            $condition['parent_id'] = $this->user_branch;
            $data = D('SysBranch')->where($condition)
                ->setDacFilter('a')
                ->join('LEFT JOIN sys_user b ON b.mobile = a.contact')
                // ->join('LEFT JOIN sys_user_branch c ON c.branch_id = a.id')
                ->field('a.id as company_id,a.name as company_name,a.linkman,a.contact,b.id,b.name,b.head_pic')
                ->select();
            foreach ($data as $k => $v) {
                $sysUserBranch = D('SysUserBranch')->where([
                    'branch_id' => $v['company_id'],
                    'user_id' => $v['id'],
                ])->select();
                if (!empty($sysUserBranch)) {
                    unset($data[$k]);
                }
            }
            $this->title = "一键绑定公司";
            $this->assign('model', $data);
            $this->display('match_view');
        } else {
            if ($type=='search') {
                $condition['_string'] = 'a.contact is not null AND a.contact!=""';
                $condition['b.branch_id'] = $this->user_branch;
                $condition['a.parent_id'] = $this->user_branch;
                $data = M('SysBranch')->where($condition)
                ->alias('a')
                ->join('LEFT JOIN sys_user b ON b.mobile = a.contact')
                ->field('a.id as company_id,a.name as company_name,a.linkman,a.contact,b.id,b.name,b.head_pic')
                ->select();
                foreach ($data as $k => $v) {
                    $sysUserBranch = D('SysUserBranch')->where([
                        'branch_id'=>$v['company_id'],
                        'user_id'=>$v['id'],
                    ])->select();
                    if (!empty($sysUserBranch)) {
                        unset($data[$k]);
                    }
                }
                $this->ajaxReturn($data);
            }else{
                $user_ids = I('post.user_ids');
                $branch_ids = I('post.branch_ids');
                $data = [];
                $condition['user_id'] = array('in', $user_ids);
                // D('SysUserBranch')->where($condition)->delete();
                $user_data = [];
                foreach ($user_ids as $k => $v) {
                    $user_data[] = array(
                        'user_id' => $v,
                        'type' => 1,
                        'branch_id' => $branch_ids[$k]
                    );
                }
                D('SysUserBranch')->addAll($user_data);
                $data['code'] = 0;
                $data['message'] = "绑定成功";
                $this->ajaxReturn($data);
            }
        }
    }

    public function user_detailAction(){
        $id = I("get.id");
        $result = M("SysUser")->where("id = '$id'")->select();
        $this->_assign_base_data();
        if(isset($result[0]['province']) && $result[0]['province'] > 0) {
            $result[0]['region'] = region($result[0]['province']) .' '.region($result[0]['city']).' '.region($result[0]['district']);
        }
        if($result[0]['followed_at'] != ""){
            $result[0]['followed_at'] = date("Y-m-d H:i:s",$result[0]['followed_at']);
        }
        if($result[0]['binded_at'] != ""){
            $result[0]['binded_at'] = date("Y-m-d H:i:s",$result[0]['binded_at']);
        }

        //分组名称
        if(!empty($result[0]['group_id'])){
            $group_id = $result[0]['group_id'];
            $result[0]['group'] = M("SysTargetGroup")->where("id = $group_id")->field('value')->find();
        }else{
          $group_id = $result[0]['group_id'];
            $result[0]['group']['value'] = '未分组';
        }
        //标签
        // if($result[0]['tag_ids'] != ""){
        //     $tag_ids = $result[0]['tag_ids'] ;
        //     $tag = M("SysTargetTag")->where("id in($tag_ids)")->select();
        //     $result[0]['tag'] = $tag ;
        // }
        // $tag_ids = $result[0]['tag_ids'];
        $tag = M("SysTargetTag")
          ->alias('a')
          ->join('LEFT JOIN sys_user_relation_tag b ON b.tag = a.id')
          ->where(["b.user_id"=>$id])
          ->select();
          // var_dump($tag);
        $result[0]['tag'] = $tag;

        //已绑定公司信息
        $com_info =  $this->showBindCom($id);
        $this->assign("com_info",$com_info);
        //自定义类型
        $information = M("SysCustomerInformation")->where("user_id = $id")->select();
        //业务负责人、可见人
        $service_man_id = $result[0]['service_man'];
        $service_leader_id = $result[0]['service_leader'];
        if($service_man_id != ""){
            $result[0]['service_man_name'] = M("SysUser")->field('name')->where("id = $service_man_id")->find();
        }
        if($service_leader_id != ""){
            $result[0]['service_leader_name'] = M("SysUser")->field('name')->where("id = $service_leader_id")->find();
        }
        $this->title="用户详情";
        $this->assign('type',I('param.type') == 'fans'? 'potential' : 'fans');
        $this->assign("result",$result[0]);
        $this->assign("information",$information);
        $this->display();
    }

    public function user_editAction(){
        $id = I("get.id");
        $branch_id = getBrowseBranchId();
        //$_filter = ['is_follow' => 1,'mobile' =>array('eq',''),'id'=>$id];
        $result = M("SysUser")->where("id = '$id'")->select();
        $this->_assign_base_data();
        if(isset($result[0]['province']) && $result[0]['province'] > 0) {
            $result[0]['region'] = region($result[0]['province']) .' '.region($result[0]['city']).' '.region($result[0]['district']);
        } else {
            $result[0]['region'] = strtolower(ACTION_NAME) === 'user_edit' ? '点击选择地址' : '';
        }
        if($result[0]['followed_at'] != ""){
            $result[0]['followed_at'] = date("Y-m-d H:i:s",$result[0]['followed_at']);
        }
        if($result[0]['binded_at'] != ""){
            $result[0]['binded_at'] = date("Y-m-d H:i:s",$result[0]['binded_at']);
        }
        if($result[0]['last_time'] != ""){
            $result[0]['last_time'] = date("Y-m-d H:i:s",$result[0]['last_time']);
        }
        //分组名称
        if($result[0]['group_id'] != ""){
            $group_id = $result[0]['group_id'];
            $result[0]['group'] = M("SysTargetGroup")->where("id = $group_id")->field('value')->find();
        }
        //标签
        // if($result[0]['tag_ids'] != ""){
        //     $tag_ids = $result[0]['tag_ids'] ;
        //     $tag = M("SysTargetTag")->where("id in($tag_ids)")->select();
        //     $result[0]['tag'] = $tag ;
        // }
         $tag = M("SysTargetTag")
          ->alias('a')
          ->join('LEFT JOIN sys_user_relation_tag b ON b.tag = a.id')
          ->where(["b.user_id"=>$id])
          ->field('a.*')
          ->select();
        $result[0]['tag'] = $tag;
        $json_tags = D('SysTargetTag')->where(['branch_id'=>$this->user_branch])->field('id as value,value as text')->select();
        $result[0]['json_tags'] = json_encode($json_tags);

        //已绑定公司信息
        $com_info =  $this->showBindCom($id);
        $this->assign("com_info",$com_info);
        //自定义类型
        $information = M("SysCustomerInformation")->where("user_id = $id")->select();
        //处理负责人、可见人
        $service_man_id = $result[0]['service_man'];
        $service_leader_id = $result[0]['service_leader'];
        if($service_man_id != ""){
            $result[0]['service_man_name'] = M("SysUser")->field('name')->where("id = $service_man_id")->find();
        }
        if($service_leader_id != ""){
            $result[0]['service_leader_name'] = M("SysUser")->field('name')->where("id = $service_leader_id")->find();
        }
        //处理分组
        //$result[0]['groups'] = M("SysTargetGroup")->where("branch_id = $branch_id")->select();
        $this->service_man_list = json_encode(D('SysUser')->field('id as value,name as text')->where("user_type=3 and branch_id = $branch_id")->select());
        $this->service_leader_list = json_encode(D('SysUser')->field('id as value,name as text')->where("user_type=3 and branch_id = $branch_id")->select());
        $this->group_list = json_encode(M("SysTargetGroup")->field('id as value,value as text')->where("branch_id = $branch_id")->select());
        if($result[0]['is_follow'] != 1){
            $this->title="访客详情";
        }else{
            $this->title="会员详情";
        }
        //设置用户类型
        if ($result[0]['user_type']==USER_TYPE_COMPANY_MANAGER) {
            $result[0]['user_type_value'] = '员工';
        } else {
            $condition = [];
            $condition['a.id'] = $v['id'];
            $condition['c.type'] = array('neq',2);
            $condition['c.parent_id'] = $this->user_branch;
            $branchs = D("SysUser")->alias('a')
                ->field('c.*')
                ->join('sys_user_branch as b on b.user_id = a.id')
                ->join('sys_branch as c on c.id = b.branch_id')
                ->where($condition)
                ->select();

            if (!empty($branchs)) {
                $result[0]['user_type_value'] = '客户';
            }else{
                $result[0]['user_type_value'] = '粉丝';
            }
        }
        //设置来源渠道
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
        ->where(['b.openid' => $result[0]['openid'] ])
        ->field("a.name")
        ->find();
        if (!empty($inviter)) {
            $result[0]['subscribe_scene_value'] = $inviter['name'];
        }else{
            if (isset($scene[$result[0]['subscribe_scene']]) ) {
                $result[0]['subscribe_scene_value'] = $scene[$result[0]['subscribe_scene']];
            }else{
                $result[0]['subscribe_scene_value'] = '-';
            }
        }
        $this->assign("result",$result[0]);
        $this->assign("information",$information);
        $this->display();
    }

    public function removeTagAction() {
      $condition['branch_id'] = $this->user_branch;
      $condition['tag'] = I('post.tag');
      $condition['user_id'] = I('post.user_id');
      D('SysUserRelationTag')->where($condition)->delete();
      $data['code'] = 0;
      $data['message'] = "删除标签成功";
      $this->ajaxReturn($data);
    }
    public function saveAction(){
        $data = I("post.");
        $id = $data['id'];
        $branch_id = $data['branch_id'];
        //处理解除匹配公司
        // if($data['unbindCom'] != ""){
        //     $condition['user_id'] = $data['id'];
        //     $condition['branch_id'] = array("in",$data['unbindCom']);
        //     // $condition['type'] = 1;
        //     $result = M('sys_user_branch')->where($condition)->delete();
        //     if($result === false){
        //         $this->ajaxReturn(array("error"=>1,"message"=>"解绑失败"));
        //     }
        // }
        // //判断已有自定义属性是否和表单提交的旧属性数量相等，不相等则删除不匹配的
        // $ids = M("SysCustomerInformation")->where("branch_id=$branch_id and user_id =$id")->field("id")->select();
        // if(count($ids) != count($data['information']['old']) ){
        //     $delIds = explode(";",$data['delInfo']);
        //     for($i=0;$i<count($delIds);$i++){
        //         M("SysCustomerInformation")->where("id=$delIds[$i]")->delete();
        //     }
        // }
        // //修改旧属性
        // for($i=0;$i<count($data['information']['old']);$i++) {
        //     $info = explode("||",$data['information']['old'][$i]);
        //     //$information['id'] = $info[0];
        //     $information['title'] = $info[1];
        //     $information['value'] = $info[2];
        //     $information['updated_at'] = time();
        //     M("SysCustomerInformation")->where("id =$info[0] ")->update($information);
        // }
        // //新增属性
        // for($i=0;$i<count($data['information']['new']);$i++){
        //     $info = explode("||",$data['information']['new'][$i]);
        //     $information['title'] = $info[0];
        //     $information['value'] = $info[1];
        //     $information['user_id'] = $id;
        //     $information['branch_id'] = $branch_id;
        //     $information['created_at'] = time();
        //     M("SysCustomerInformation")->add($information);
        // }
        //处理标签
        // if(count($data['tags'] )<= 0){
        //     M("SysUser")->where("id = $id")->setField('tag_ids',"");
        //     M("SysUserRelationTag")->where("user_id = $id and branch_id = $branch_id")->delete();
        // }else{
        //     $tag = "";
        //     //M("SysUserRelationTag")->where("user_id = $id and branch_id = $branch_id")->delete();
        //     for($i=0;$i<count($data['tags']);$i++){
        //         $tag = $tag == "" ?  $data['tags'][$i] : $tag .",".$data['tags'][$i];
        //         $tmp['user_id'] = $id;
        //         $tmp['branch_id'] = $branch_id;
        //         $tmp['tag'] = $data['tags'][$i];
        //         $tmp['created_at'] = time();
        //         $tag_id = $data['tags'][$i];
        //         $result = M("SysUserRelationTag")->where("user_id = $id and branch_id = $branch_id and tag =$tag_id")->find();
        //         if($result == null){
        //             M("SysUserRelationTag")->add($tmp);
        //         }
        //     }
        //     M("SysUser")->where("id = $id")->setField('tag_ids',$tag);
        //     M("SysUserRelationTag")->where("user_id = $id and branch_id = $branch_id and tag not in ($tag)")->delete();
        // }
        $result = M("SysUser")->where("id=".$data['id'])->save($data);
        if($result !== false){
            $this->ajaxReturn(array("error"=>0,"message"=>"保存成功"));
        }else{
            $this->ajaxReturn(array("error"=>1,"message"=>"保存失败"));
        }
    }

    protected function _assign_base_data() {
        $region_list = M('sysRegion') -> field("id as value,name as text,id,parent_id") -> cache(true) -> order("level asc,sort desc") -> select();
        $region = list_to_tree($region_list, 0 ,"id", "parent_id", "children");
        $this -> assign('region', json_encode($region));
    }

    protected function showBindCom($id){
        $condition['user_id'] = $id;
        $condition['type'] = array('neq',ROLE_ID_COMPANY_MANAGER);
        $com_ids = M("SysUserBranch")->where($condition)->field("branch_id")->select();
        if(count($com_ids)>0){
            $com_id ="";
            for($i=0;$i<count($com_ids);$i++){
                $com_id = $com_id =="" ? $com_ids[$i]['branch_id'] :$com_id .",".$com_ids[$i]['branch_id'];
            }
            $com_info = M("SysBranch")->where("id in($com_id)")->select();
            for($i=0;$i<count($com_info);$i++){
                if(isset($com_info[$i]['province']) && $com_info[$i]['province'] > 0) {
                    $com_info[$i]['region'] = region($com_info[$i]['province']) .' '.region($com_info[$i]['city']).' '.region($com_info[$i]['district']);
                }
                if(isset($com_info[$i]['reg_province']) && $com_info[$i]['reg_province'] > 0) {
                    $com_info[$i]['reg_region'] = region($com_info[$i]['reg_province']) .' '.region($com_info[$i]['reg_city']).' '.region($com_info[$i]['reg_district']);
                }
            }
            return $com_info;
        }
    }


    public function select_service_manAction($init = 0,$name = null){
        $condition = [];
        if (!empty($name)) {
            $condition['name'] = array('LIKE','%'.$name.'%');
        }
        $condition['branch_id'] = $this->user_branch;
        $condition['user_type'] = 3;
        $this->service_man = M('SysUser')
            ->field('id as value,name as text')
            ->where($condition)->select();
        $this->init = $init;
        $this->display('select_service_man');
    }
    public function select_service_leaderAction($init = 0,$name = null){
        $condition = [];
        if (!empty($name)) {
            $condition['name'] = array('LIKE','%'.$name.'%');
        }
        $condition['branch_id'] = $this->user_branch;
        $condition['user_type'] = 3;
        $this->service_leader = M('SysUser')
            ->field('id as value,name as text')
            ->where($condition)->select();
        $this->init = $init;
        $this->display('select_service_leader');
    }
    protected function handlerPermissionsProcessing()
    {
        parent::handlerPermissionsProcessing();
        switch (ACTION_NAME){
            case 'user_detail':
                if (I('get.type') == 'fans') {
                    $this->_permission_name = 'ComFans';
                }
                $this->_permission_action_name = 'detail';
                break;
            case 'user_edit':
                if (I('get.type') == 'fans') {
                    $this->_permission_name = 'ComFans';
                }
                $this->_permission_action_name = 'update';
                break;
        }
    }

}