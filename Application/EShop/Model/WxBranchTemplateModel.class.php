<?php

namespace EShop\Model;



class WxBranchTemplateModel extends DataModel {
    const TEMPLATE_APPEND_DRAFT_FIELD = 'draft';
    const TEMPLATE_APPEND_SEND_FIELD = 'send';
    protected $_model =  [
        'template' => 'WxTemplateMessage',
        'branch_template' => 'WxBranchTemplate',
        'notice_template_library' => 'WxNoticeTemplateLibrary',
        'notice_relation_user' => 'WxNoticeRelationUser',
        'user' => 'SysUser',
        'group' => 'SysTargetGroup',
    ];
    protected $user_branch;
    protected $storage;
    public function _initialize()
    {
        $this->_model = (Object) $this->_model;
        $this->user_branch = getBrowseBranchId();
    }
    public function getContentTemplate($id)
    {
        return D($this->_model->template)->field("content,example")->where("id=$id")->find();
    }
    public function getTemplates($option)
    {
        $list = D('WxBranchTemplate')->setDacFilter("a")
                    ->join("wx_template_message b on b.id=a.parent_id")
                    ->field("b.id as value,b.title as text")
                    ->where($option)
                    ->select();
        return $list;
    }
    public function sendList($data)
    {
        $field = 'CONCAT(user.name,if(user.comments != "",CONCAT(CONCAT(\'(\',user.comments),\')\'),"")) as name,user.mobile,user.id,user.head_pic,user.group_id,if(team.id > 0 , team.value , \'未分组\') as group_name,nru.errmsg';
        $condition['nru.notice_id'] = $data->id;
        $condition['nru.state'] = $data->state;
        $limit = (($data->page - 1) * 20).',20';
        $result = M($this->_model->notice_relation_user)
                            ->alias('nru')
                            ->field($field)
                            ->join($this->getModelString('user') . ' as user on user.id = nru.user_id')
                            ->join($this->getModelString('group') . ' as team on team.id = user.group_id','left')
                            ->where($condition)
                            ->limit($limit)
                            ->select();
        return $result;
    }
    public function templateSingleDetail($id)
    {
        $field = 'nrl.*,';
        $field.= '(select count(*) from '.$this->getModelString("notice_relation_user").' where notice_id = nrl.id and state = 1) as success_count,';
        $field.= '(select count(*) from '.$this->getModelString("notice_relation_user").' where notice_id = nrl.id and state = 2) as error_count';
        $condition['nrl.id'] = $id;
        $result = M($this->_model->notice_template_library)
            ->alias('nrl')
            ->field($field)
            ->where($condition)
            ->find();
        return $result;
    }
    //取出 草稿或发送记录
    public function getNoticeLists($data)
    {
        $page_size = $data->rows;
        $paging = $data->page;
        $condition['nrl.type'] = $data->state;
        $condition['nrl.branch_id'] = $this->user_branch;
        $field = 'nrl.content,nrl.send_at,nrl.id,nrl.template_id,';
        $field.= '(select name from '.$this->getModelString("notice_relation_user").' as nru join '.$this->getModelString("user").' as user on user.id = nru.user_id where nru.notice_id = nrl.id limit 1) as users_name,';
        $field.= '(select count(*) from '.$this->getModelString("notice_relation_user").' where notice_id = nrl.id) as users_count';
        $result = D($this->_model->notice_template_library)
                        ->setDacFilter('nrl')
                        ->field($field)
                        ->where($condition)
                        ->page($paging, $page_size)
                        ->order('nrl.id desc')
                        ->select();
        return $result;
    }
    //判断 微信模板消息是否存在
    public function hasWxTemplate($template_id)
    {
        $result_branch = M($this->_model->branch_template)->where('parent_id = '.$template_id.' and branch_id = '.$this->user_branch)->count();
        $result = M($this->_model->template)->where('id = '.$template_id)->count();
        return ($result > 0 && $result_branch > 0) ? true : false;
    }
    //判断 草稿或发送记录是否存在
    public function isTemplateList($type) {
        $condition['nrl.type'] = $type;
        $condition['nrl.branch_id'] = $this->user_branch;
        $result = D($this->_model->notice_template_library)
                        ->setDacFilter('nrl')
                        ->where($condition)
                        ->count();
        return $result > 0 ? true : false ;
    }
    //获取草稿
    public function getPreviewUpdateData($id)
    {
        $notice = M($this->_model->notice_template_library)->where("id=$id")->find();
        if($notice){
            $users = M($this->_model->notice_relation_user)
                ->alias('relation')
                ->field('user.id,user.name')
                ->join($this->getModelString('user').' as user on user.id = relation.user_id')
                ->where("notice_id=$id")
                ->select();
            return compact('notice','users');
        } else {
            return false;
        }

    }
    public function previewDeleteImplement($id)
    {
        $condition['id'] = $id;
        $condition['branch_id'] = $this->user_branch;
        $result = M($this->_model->notice_template_library)->where($condition)->delete();
        if ($result) {
            $where['notice_id'] = $id;
            M($this->_model->notice_relation_user)->where($where)->delete();
            return true;
        } else {
            return false;
        }
    }
    public function previewUpdateImplement($storage)
    {
        if ($storage->operation === 'update') {
            $storage->{$storage->operation}['updated_at'] = time();
            $result = M($this->_model->notice_template_library)->save($storage->{$storage->operation});
            if ($result) {
                //删除 增加所选用户
                if (!empty($storage->users)) {
                    $add = [];
                    $add_ids = [];
                    $del_ids = [];
                    //获取所有的旧用户
                    $condition['notice_id'] = $storage->{$storage->operation}['id'];
                    $users_old = M($this->_model->notice_relation_user)->where($condition)->getField('user_id',true);
                    if (!empty($users_old)) {
                        $add_ids = array_diff($storage->users,$users_old);
                        $del_ids = array_diff($users_old,$storage->users);
                        if ($add_ids) {
                            foreach($add_ids as $key => $val){
                                $add[] = [
                                    'user_id' => $val,
                                    'notice_id' => $storage->{$storage->operation}['id'],
                                    'state' => WX_TEMPLATE_SEND_DEFAULT,
                                    'created_at' => time(),
                                    'updated_at' => time()
                                ];
                            }
                        }
                        if ($del_ids) {
                            $condition['notice_id'] = $storage->{$storage->operation}['id'];
                            $condition['user_id'] = array('in',$del_ids);
                            M($this->_model->notice_relation_user)->where($condition)->delete();
                        }
                    } else {
                        foreach($storage->users as $key => $val){
                            $add[] = [
                                'user_id' => $val,
                                'notice_id' => $storage->{$storage->operation}['id'],
                                'state' => WX_TEMPLATE_SEND_DEFAULT,
                                'created_at' => time(),
                                'updated_at' => time()
                            ];
                        }

                    }
                    if ($add) {
                        M($this->_model->notice_relation_user)->addAll($add);
                    }
                } else {
                    $condition['notice_id'] = $storage->{$storage->operation}['id'];
                    M($this->_model->notice_relation_user)->where($condition)->delete();
                }
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    public function templateAppendImplement($storage)
    {
        if ($storage->operation === 'append') {
            $result = M($this->_model->notice_template_library)->add($storage->{$storage->operation});
            if ($result) {
                if (is_array($storage->users) && count($storage->users) > 0) {
                    $append_relation =[];
                    foreach ($storage->users as $val) {
                        $append_relation[] = [
                            'notice_id' => $result,
                            'user_id' => $val,
                            'state' => WX_TEMPLATE_SEND_DEFAULT,
                            'created_at' => time(),
                            'updated_at' => time()
                        ];
                    }
                    M($this->_model->notice_relation_user)->addAll($append_relation);
                }
                return $result;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    public function getUserOpenidsFromTemplate($id)
    {
        $condition['ntl.id'] = $id;
        $result = M($this->_model->notice_template_library)
                            ->alias('ntl')
                            ->field('user.openid,user.id')
                            ->join($this->getModelString('notice_relation_user').' as nru on nru.notice_id = ntl.id')
                            ->join($this->getModelString('user') . ' as user on user.id = nru.user_id')
                            ->where($condition)
                            ->select();
        return $result;
    }
    public function getUsersOpenId($id)
    {
        return M($this->_model->user)->where('id = '.$id)->getField('openid');
    }
    public function getTemplateSerialNumber($id)
    {
        return M($this->_model->branch_template)
                                ->where('id = '.$id)
                                ->getField('template_id');
    }
    public function getTemplateTitle($id)
    {
        return M($this->_model->template)
            ->where('id = '.$id)
            ->getField('title');
    }
    public function getBranchTemplateId($id)
    {
        $condition['parent_id'] =$id;
        $condition['branch_id'] = $this->user_branch;
        return M($this->_model->branch_template)->where($condition)->getField('template_id');
    }
    public function getSingleUserData($id)
    {
        $condition['id'] = $id;
        return M($this->_model->user)->where($condition)->field('id,name')->select();
    }
    public function userSendTemplateFinally($finally)
    {
        if (isset($finally['success'])) {
            $this->hanlderUserSendTemplateFinally($finally['success'],$finally['notice_id'],WX_TEMPLATE_SEND_SUCCESS);
        }
        if (isset($finally['error'])) {
            $this->hanlderUserSendTemplateFinally($finally['error'],$finally['notice_id'],WX_TEMPLATE_SEND_ERROR);
        }
    }
    protected function hanlderUserSendTemplateFinally($user,$notice_id,$state)
    {
        $add = [];
        $save = [];
        //判断是否有存在数据库中
        $condition['user_id'] = array('in',array_keys($user));
        $condition['notice_id'] = $notice_id;
//        $condition['branch_id'] = $this->user_branch;
        $result = M($this->_model->notice_relation_user)->where($condition)->field('user_id,id')->select();
        if ($result) {
            foreach ($result as $key => $val) {
                //修改
                $save_relation['state'] = $state;
                $save_relation['id'] = $val['id'];
                $save_relation['errcode'] = $user[$val['user_id']]['errcode'];
                $save_relation['errmsg'] = getGlobalReturnCode($user[$val['user_id']]['errcode']);
//                var_dump($save_relation);die;
                M($this->_model->notice_relation_user)->save($save_relation);
            }
        }
    }
    public function importBranchTemplate($recode)
    {
        $template_list = $recode->template_list;
        $titles = [];
        $contents = [];
        $examples = [];
        $templateids = [];
        $item_selecteds = []; //选中的项
        foreach($template_list as $key =>$val) {
            $titles[] = $val['title'];
            $contents[] = $val['content'];
            $examples[] = $val['example'];
            $templateids[] = $val['template_id'];
            $item_selecteds[] = $key;
        }
        $brancTtemplateKeys = $recode->templatekeys;//公司已经存在的模板消息
        $systemTemplateKeys = D("ESAdmin/WxTemplateMessage")->getSystemExistsTemplateMsgKeys(); //所有模板
        if (count($titles) == count($contents) && count($examples) == count($titles)){
            $datalist = array();
            $detaillist = array();
            foreach($item_selecteds as $key){
                $title = $titles[$key];
                $msg_key = getTemplateIdentKey($contents[$key]);
                if ($brancTtemplateKeys[$msg_key]) {
                    return buildMessage("模板消息【" . $title . "】已经存在",1);
                }
                if ($contents[$key]) {
                    $detail = array();
                    if ($systemTemplateKeys[$msg_key]){ //系统已经存在的
                        $detail["parent_id"] = $systemTemplateKeys[$msg_key];
                        $detail["template_id"] = $templateids[$key];
                        $detail["branch_id"] = $this->user_branch;
                        $detaillist[] = $detail;
                    }else{
                        $data["title"] = $title;
                        $data["content"] = $contents[$key];
                        $data["example"] = $examples[$key];
                        $data["msg_key"] = $msg_key;
                        $data["update_time"] = time();
                        $data["branch_id"] = $this->user_branch;
                        $data["template_id"] = $templateids[$key];
                        $datalist[] = $data;
                    }
                }
            }
            $branch_template = M($this->_model->branch_template);
            $branch_template->startTrans();
            try{
                foreach ($datalist as $data){
                    $last_id = M("WxTemplateMessage")->add($data);
                    if ($last_id){
                        $detail["parent_id"] = $last_id;
                        $detail["template_id"] = $data["template_id"];
                        $detail["branch_id"] = $this->user_branch;
                        $detaillist[] = $detail;
                    }
                }
                foreach ($detaillist as $detail){
                    M($this->_model->branch_template)->add($detail);
                }
                $branch_template->commit();
                return buildMessage("模板导入成功！");
            }catch (Exception $exception){
                $branch_template->rollback();
                return buildMessage("模板导入错误！",1);
            }
        }else{
            return buildMessage("数据有错误！",1);
        }
    }
    /*
     * author Lynn
     * created Aug 3,2018
     */
    protected function getModelString($inc)
    {
        return strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $this->_model->{$inc}));
    }
}