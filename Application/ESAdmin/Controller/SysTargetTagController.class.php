<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class  SysTargetTagController extends DataController {

    protected function getChosenNameField() {
        return "a.value";
    }
    protected function _before_write($type, &$data) {
        if(trim($data['value'])==""){
            E("不能为空！");
        }
        parent::_before_write($type, $data);
    }
    protected function _before_list(&$list)
    {
        parent::_before_list($list);
        foreach($list as $k=>$v){
            $list[$k]['user_count'] = D('SysUserRelationTag')
            ->alias('a')
            ->join('LEFT JOIN sys_user b ON b.id = a.user_id')
            ->where(['a.tag'=>$v['id'],'b.is_follow'=>1])->count();
        }
    }
    protected function _before_detail(&$data){
        $data['user_count'] = D('SysUserRelationTag')
            ->alias('a')
            ->join('LEFT JOIN sys_user b ON b.id = a.user_id')
            ->where(['a.tag'=>$data['id'],'b.is_follow'=>1])->count();
        parent::_before_detail($type, $data);
    }

    public function userListAction($tag = null)
    {
        //获取标签和分组
        $groups = D('ComPotential')->getBranchTarget('group');
        $tags = D('ComPotential')->getBranchTarget('tag');
        $condition_groups['is_follow'] = 1;
        $condition_groups_other['is_follow'] = 1;
        $sysUserRelationTag = D('SysUserRelationTag')->where(['tag'=>$tag])->select();
        $user_ids = [];
        foreach ($sysUserRelationTag as $k => $v) {
            // $user_ids = [];
            array_push($user_ids, $v['user_id']);
        }
        foreach($groups as $k=>$v){
                $condition_groups['group_id'] = $v['id'];
                if (!empty($user_ids)) {
                    $condition_groups['id'] = array('not in',$user_ids);
                }
                $groups[$k]['user_count'] = D('SysUser')
                ->where($condition_groups)
                ->count();
        }
        //未分组处理
        $tmp = array('id'=>'other','value' => '未分组','branch_id' => $this->_user_session->currBranchId);
        $condition_groups_other['_string'] = 'group_id is null or group_id =""';
        $condition_groups_other['branch_id'] = $this->_user_session->currBranchId;
        if (!empty($user_ids)) {
            $condition_groups_other['id'] = array('not in',$user_ids);
        }
        $tmp['user_count'] = D('SysUser')
            ->where($condition_groups_other)
            ->count();
        array_unshift($groups,$tmp);
        //标签出路
        $condition_tags['b.is_follow'] = 1;
        if (!empty($user_ids)) {
            $condition_tags['a.user_id'][] = array("not in",$user_ids);
        }
        foreach($tags as $k=>$v){
            if ($v['id']==$tag) {
                unset($tags[$k]);
            } else {
                $condition_tags['a.tag'] = $v['id'];
                $tags[$k]['user_count'] = D('SysUserRelationTag')
                    ->alias('a')
                    ->join('LEFT JOIN sys_user b ON b.id = a.user_id')
                    ->where($condition_tags)->count();
            }
            
        }
        $this->groups = $groups;
        $this->tags = $tags;
        $this->tag = $tag;

        $this->display('userList');
    }

    public function getUserAction($tag = null)
    {
        $_filter = [];
        // $_filter['a.group_id'] = array("neq",$tag);
        if (!empty(I('groups'))) {
            $groups = I('groups');
            if (in_array('other',I('groups'))) {
                unset($groups[array_search('other',I('groups'))]);
                $_filter['_string'] = '';
                if (count($groups) > 0) {
                    $_filter['_string'].= ' a.group_id in ('.implode(',',$groups).') ';
                    $_filter['_string'].= ' or ';
                }
                $_filter['_string'].= ' a.group_id is null or  a.group_id = ""';
            } else {
                $_filter['a.group_id'][] = array('in',$groups);
            }
        }
        if (!empty(I('tags'))) {
            A('UserParse')->handlerTagsSearch(I('tags'),$_filter);
        }
        if (I('lk_name') != ''){
            $lk_name = I('lk_name');
            $_filter['a.name'] = array("like", sprintf("%%%s%%",$lk_name));
        }
        $_filter['a.branch_id'] = $this->_user_session->currBranchId;
        $_filter['a.is_follow'] = 1;
        $sysUserRelationTag = D('SysUserRelationTag')->where(['tag'=>$tag])->select();
        $user_ids = [];
        foreach ($sysUserRelationTag as $k => $v) {
            array_push($user_ids, $v['user_id']);
        }
        if (!empty($user_ids)) {
            $_filter['a.id'][] = array("not in",$user_ids);
        }
        $count = M('SysUser') ->alias('a')
                            ->where($_filter)
                            ->count();
        $user_total = M('SysUser') ->alias('a')
                                ->field('a.id,a.name')
                                ->where($_filter)
                                ->select();
        $users = M('SysUser') ->alias('a')
                              ->join('left join sys_target_group as target on target.id = a.group_id')
                              ->where($_filter)
                              ->field('a.*,if(target.id > 0,target.value,"未分组") as group_name')
                              ->page(I('page'),20)
                              ->order('a.followed_at desc')
                              ->select();
        foreach($users as $key => $val) {
            $users[$key]['tag_name'] = D('UserParse')->getGroupNames($val);
        }
        $this->ajaxReturn(['total'=>$count,'data'=>$users,'user_total' => $user_total]);
    }

    public function getUserByTagAction($tag = null)
    {
        $sysUser = M('SysUser') ->alias('a')
            ->join('left join sys_target_group as target on target.id = a.group_id')
            ->join('left join sys_user_relation_tag as c on c.user_id = a.id')
            ->where(['c.tag'=>$tag,'a.is_follow'=>1])
            ->field('a.id,a.head_pic,a.name,a.comments,a.mobile,if(target.id > 0,target.value,"未分组") as group_name')
            ->order('a.followed_at desc')
            ->select();
        foreach ($sysUser as $k => $v) {
            $sysUser[$k]['tags_value'] = D('UserParse')->getGroupNames($v);
            $sysUser[$k]['company_names'] = D('SysUser')->getCompanyNames($v);

        }
        $this->ajaxReturn($sysUser);
    }

    public function removeUserAction($tag = null)
    {
        $user_ids = I("post.user_ids");
        D('SysUserRelationTag')
            ->where(['user_id'=>array('in',$user_ids),'tag'=>$tag])
            ->delete();
        $sysUser = D('SysUser')
            ->where(['id'=>array('in',$user_ids)])
            ->select();
        foreach ($sysUser as $k => $v) {
            $tmp = explode(",", $v['tag_ids']);
            $tmp = array_diff($tmp,[$tag]);
            $tmp = implode(",",$tmp);
            $sysUser = D('SysUser')
            ->where(['id'=>$v['id']])
            ->save(['tag_ids'=>$tmp]);
        }
        $data['code'] = 0;
        $data['message'] = "移除成功";
        $this->ajaxReturn($data);
    }   

    public function listAction() {
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $_order = array();
        $this->_parseOrder($_order);
        $_filter = array();
        $this->_parseFilter($_filter);
        $count = D(CONTROLLER_NAME)->setDacFilter("a")->where($_filter)->count();
        $list = D(CONTROLLER_NAME)->setDacFilter("a")->relation(true)->field("a.*")->where($_filter)->page($page_index, $page_size)->order($_order)->select();
        $this->_before_list($list);
        $result["total"] = $count;
        $result["rows"] = $list;
        for($i=0;$i<$count;$i++){
            $id=$result['rows'][$i]['id'];
            $total_count = M('SysUser')->where("find_in_set($id,tag_ids)")->count();
            $result['rows'][$i]['total_count']=$total_count;
        }
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($result));
    }

    public function targetUpdatesAction() {
        if (IS_POST) {
            $model = D('SysUser');
            $post_data = I('post.');
            $data = [];
            $_POST['_tip_target'] = 1;
            $sysUser = $model->where(["id"=>array('in',$post_data['users'])])->select();
            foreach($sysUser as $key => $val){
                    $tmp['id'] = $val['id'];
                if(!empty(I('post.tag_ids'))){
                    if (!empty($val['tag_ids'])) {
                        $tmp['tag_ids'] = explode(",", $val['tag_ids']);
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
                
                $tmp['branch_id'] = getBrowseBranchId();
                $data[] = $tmp;
            }

            try {
                $model->startTrans();
                foreach($data as $key => $val) {
                    $this->_before_target_write(self::ACTION_DETAIL, $val);

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
                            $this->addLog($val['id']);
                            $result_data = $this->_getLastData($val['id']);
                            if ((count($post_data['users']) - 1) == $key){
                                $this->responseJSON(buildMessage($result_data));
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
        } else {
            $this->display();
        }
    }
}