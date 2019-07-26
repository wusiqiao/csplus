<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;
use Think\Controller;

class  WxBranchTemplateController extends DataController {

    const BLACK_CUTTER = '：';
    const TEMPLATE_APPEND_TYPE_SEND = 1;
    const TEMPLATE_APPEND_TYPE_DRAFT = 2;
    const TEMPLATE_APPEND_DRAFT_FIELD = 'draft';
    const TEMPLATE_APPEND_SEND_FIELD = 'send';
    const TEMPLATE_APPEND_PREVIEW_FIELD = 'preview';//预览
    const TEMPLATE_UPDATE_DRAFT_FIELD = 'update_draft';//修改草稿
    protected $storage = [];
    protected $request = [];
    public function showImportViewAction(){
        if ($list = $this->getTemplateMessageList()){
            $templatekeys = D(CONTROLLER_NAME)->getBranchExistsTemplateMsgKeys($this->_user_session->currBranchId);
            $template_list = array();
            foreach ($list["template_list"] as $key=>$v){
                if ($v["title"] != '订阅模板消息' && $v["title"] != '服务进度通知' && $v["title"] != '客户请求通知') {
                    $msg_key = getTemplateIdentKey($v["content"]); //排除重复
                    if (empty($templatekeys[$msg_key])) {
                        $template_list[] = &$list["template_list"][$key];
                    }
                }
            }
            $this->list = $template_list;
        }
        $this->display("import");
    }

    //初始化模板，先读取微信后台模板，如果存在就不添加，否则添加（不能重复添加，否则微信后台会重复，微信的BUG？）
    public function getTemplateMessageList(){
        $wx_instance = getWeChatInstance();
        $list = $wx_instance->getTemplateMessageList();//先获取微信后台模板
        foreach ($list["template_list"] as $key=>$v){
            $msg_key = getTemplateIdentKey($v["content"]);
            $wx_templates[$msg_key] = $v;
        }
        $tmpls = D(CONTROLLER_NAME)->getUnRegisterTemplate($this->_user_session->currBranchId);//获取未被导入的系统模板
        foreach ($tmpls as $key=>$tpl) {
            if (empty($wx_templates[$tpl["msg_key"]]) && $tpl["standard_id"]) {
                $wx_instance->addTemplateMessage($tpl["standard_id"]);
            }
        }
        return $wx_instance->getTemplateMessageList();//重新获取微信后台模板;
    }

    public function importAction(){
        $result = D(CONTROLLER_NAME)->addTemplate($this->_user_session->currBranchId);
        $this->responseJSON($result);
    }

    public function listAction(){
//        $_filter = array();
//        $this->_parseFilter($_filter);
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $count = D(CONTROLLER_NAME)->setDacFilter("a")->where("a.branch_id=".$this->_user_session->currBranchId)->count();
        $list = D(CONTROLLER_NAME)->setDacFilter("a")
            ->join("inner join wx_template_message b on a.parent_id=b.id")
            ->field("a.id,b.title,b.example")->where("a.branch_id=".$this->_user_session->currBranchId)->page($page_index, $page_size)->select();
        $result["total"] = $count;
        $result["rows"] = $list;
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($result));
    }

    //新增 - start -  Sep 27
    public function templateHistoryAction()
    {
        $this->display('template_history');
    }
    public function history_listAction()
    {

        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $condition['a.type'] = 1;
        $condition['a.branch_id'] = $this->_user_session->currBranchId;
        $this->_parseFilter($condition);
        $count = D('WxNoticeTemplateLibrary')
            ->setDacFilter('a')
            ->where($condition)
            ->count();
        $library = D('WxNoticeTemplateLibrary')
            ->setDacFilter('a')
            ->field('a.title,a.id,a.point,a.url,a.xcx_space_url,a.send_at')
            ->page($page_index, $page_size)
            ->where($condition)
            ->order('a.send_at desc')
            ->select();
        foreach($library as $key => $val) {
            $users_name = $this->getTemplateChoiceUsers($val['id'],'name');
            $library[$key]['users'] = $users_name ? implode(',',$users_name) : '';
            $library[$key]['send_time'] = date('Y年m月d日 H:i:s',$val['send_at']);
        }
        $result["total"] = $count;
        $result["rows"] = $library;
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($result));
    }
    public function templateDraftAction()
    {
        $this->display('template_draft');
    }
    public function draft_listAction()
    {
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $condition['a.type'] = 2;
        $condition['a.mold'] = 1;
        $condition['a.branch_id'] = $this->_user_session->currBranchId;
        $this->_parseFilter($condition);
        $_order = 'a.updated_at desc';
        $count = D('WxNoticeTemplateLibrary')
                    ->setDacFilter('a')
                    ->where($condition)
                    ->count();
        $library = D('WxNoticeTemplateLibrary')
                    ->setDacFilter('a')
                    ->field('a.title,a.id,a.point,a.url,a.xcx_space_url,a.updated_at,a.template_id')
                    ->page($page_index, $page_size)
                    ->where($condition)
                    ->order($_order)
                    ->select();
        foreach($library as $key => $val) {
            $users_name = $this->getTemplateChoiceUsers($val['id'],'name');
            $library[$key]['users'] = $users_name ? implode(',',$users_name) : '';
            $library[$key]['updated_time'] = date('Y年m月d日 H:i:s',$val['updated_at']);
            $library[$key]['is_open'] = $this->handlerHasTemplate($val['template_id']);
        }
        $result["total"] = $count;
        $result["rows"] = $library;
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($result));
    }
    public function deleteDraftAction($id)
    {
        if ($id) {
            $result = $this->handlerDeleteTemplateLibrary($id);
            $this->ajaxReturn($result);
        } else {
            $this->ajaxReturn(buildMessage('数据出错!',1));
        }
    }
    public function deleteHistoryAction($id)
    {
        if ($id) {
            $result = $this->handlerDeleteTemplateLibrary($id);
            $this->ajaxReturn($result);
        } else {
            $this->ajaxReturn(buildMessage('数据出错!',1));
        }
    }
    public function getTemplateChoiceUsers($id,$inc = 'name')
    {
        $condition['a.notice_id'] = $id;
        $result =   M("wx_notice_relation_user")
                        ->alias('a')
                        ->join('inner join sys_user as user on user.id = a.user_id')
                        ->where($condition)
                        ->getField($inc,true);
        return $result;
    }
    public function handlerDeleteTemplateLibrary($id)
    {
        $condition['id'] = $id;
        $condition['branch_id'] = $this->_user_session->currBranchId;
        $resule = M('wx_notice_template_library')->where($condition)->delete();
        return $resule ? buildMessage('删除成功!',0) : buildMessage('删除失败!',1);
    }
    public function showTipAction()
    {
        $this->display('show_tip');
    }
    public function templateAppendAction()
    {
        $condition["a.branch_id"] = $this->_user_session->currBranchId;
        $list = M(CONTROLLER_NAME)->alias("a")
            ->join("inner join wx_template_message b on b.id=a.parent_id")
            ->field("b.id as value,b.title as text")
            ->where($condition)->select();
        $this->templates = count($list) > 0 ? json_encode($list) : [];
        $this->point = 0;
        $this->users = json_encode([]);
        $this->sign = 'append';
        $this->contents = json_encode([]);
        $this->display('template_append');
    }
    public function preview_updateAction($id)
    {
        $result = D('EShop/'.CONTROLLER_NAME)->getPreviewUpdateData($id);
        $this->handlerPreviewUpdateData($result);
        $this->display('template_append');
    }
    /*
     * 编辑发送
     */
    public function edit_sendAction($id)
    {
            if($result = D('EShop/'.CONTROLLER_NAME)->getPreviewUpdateData($id)){
                $this->handlerPreviewUpdateData($result,'append');
                $this->display('template_append');
            } else {
                echo '该历史记录不存在或已删除';
            }
    }
    public function previewForUsersAction()
    {
        //获取标签和分组
        $groups = D('ComPotential')->getBranchTarget('group');
        $tags = D('ComPotential')->getBranchTarget('tag');
        $condition_groups['a.is_follow'] = 1;
        $condition_groups_other['is_follow'] = 1;
        foreach($groups as $k=>$v){
            $condition_groups['a.group_id'] = $v['id'];
            $groups[$k]['user_count'] = D('SysUser')
                ->setDacFilter('a')
                ->where($condition_groups)
                ->count();
        }
        //未分组处理
        $tmp = array('id'=>'other','value' => '未分组','branch_id' => $this->_user_session->currBranchId);
        $condition_groups_other['_string'] = 'a.group_id is null or a.group_id =""';
        $condition_groups_other['a.branch_id'] = $this->_user_session->currBranchId;
        $tmp['user_count'] = D('SysUser')
            ->setDacFilter('a')
            ->where($condition_groups_other)
            ->count();
        array_unshift($groups,$tmp);
        //标签出路
        $condition_tags['b.is_follow'] = 1;
        foreach($tags as $k=>$v){
            $condition_tags['a.tag'] = $v['id'];
            $tags[$k]['user_count'] = D('SysUser')
                ->setDacFilter('b')
                ->join('right JOIN sys_user_relation_tag a ON a.user_id = b.id')
                ->where($condition_tags)->count();
        }
        $this->groups = $groups;
        $this->tags = $tags;
        $this->display('preview_for_users');
    }
    public function templatePreviewImplementAction()
    {
        if ($this->handlerTemplateCUPImplementRevise()) {
            $template_id = D('EShop/'.CONTROLLER_NAME)->getBranchTemplateId($this->request->template_id);
            $this->storage->template_data['template_id'] = $template_id;
            $result = $this->handlerPreviewTemplateFromUser($this->storage->template_data);
            $this->ajaxReturn($result === true ? ['error' =>0,'message' => '通知预览已发送'] : $result);
        }
    }
    public function previewUpdateImplementAction()
    {
        if($this->handlerTemplateCUPImplementRevise()) {
            $result = D('EShop/'.CONTROLLER_NAME)->previewUpdateImplement($this->storage);
            if ($this->storage->key === self::TEMPLATE_APPEND_SEND_FIELD) {
                if ($result) {
                    //发送模板消息
                    $template_id = D('EShop/'.CONTROLLER_NAME)->getBranchTemplateId($this->request->template_id);
                    $this->storage->template_data['template_id'] = $template_id;
                    $this->storage->template_data['notice_id'] = $this->request->id;
                    $user = $this->handlerUserOpenidsFromTemplate(D('EShop/'.CONTROLLER_NAME)->getUserOpenidsFromTemplate($this->request->id));
                    $this->handlerSendTemplateFromUser($this->storage->template_data,$user);
                    $this->ajaxReturn(['error' =>0,'message' => '发送完成']);
                } else {
                    $this->ajaxReturn(['error' =>1,'message' => '发送失败']);
                }
            } else {
                $this->ajaxReturn($result ? ['error' =>0,'message' => '草稿修改成功'] : ['error' =>1,'message' => '草稿修改失败']);
            }
        }
    }
    public function templateAppendImplementAction(){
        if($this->handlerTemplateCUPImplementRevise()) {
            $result = D('EShop/'.CONTROLLER_NAME)->templateAppendImplement($this->storage);
            if ($this->storage->key === self::TEMPLATE_APPEND_SEND_FIELD) {
                if ($result) {
                    //发送模板消息
                    $template_id = D('EShop/'.CONTROLLER_NAME)->getBranchTemplateId($this->request->template_id);
                    $this->storage->template_data['template_id'] = $template_id;
                    $this->storage->template_data['notice_id'] = $result;
                    $user = $this->handlerUserOpenidsFromTemplate(D('EShop/'.CONTROLLER_NAME)->getUserOpenidsFromTemplate($result));
                    $this->handlerSendTemplateFromUser($this->storage->template_data,$user);
                    $this->ajaxReturn(['error' =>0,'message' => '发送完成']);
                } else {
                    $this->ajaxReturn(['error' =>1,'message' => '发送失败']);
                }
            } else {
                $this->ajaxReturn($result ? ['error' =>0,'message' => '草稿添加成功','id'=>$result] : ['error' =>1,'message' => '草稿添加失败']);
            }
        }
    }
    /*
     * 分流性添加草稿/发送 - 初始化
     */
    public function templateCUImplementShuntAction()
    {
        if($this->handlerTemplateCUPImplementRevise()) {
            if ($this->request->id > 0) {
                $result = D('EShop/'.CONTROLLER_NAME)->previewUpdateImplement($this->storage);
                if ($this->storage->key === self::TEMPLATE_APPEND_SEND_FIELD) {
                    $this->ajaxReturn($result ? ['error' =>0,'message' => '发送成功','id'=>$this->request->id] : ['error' =>1,'message' => '发送失败']);
                } else {
                    $this->ajaxReturn($result ? ['error' =>0,'message' => '草稿保存成功','id'=>$this->request->id] : ['error' =>1,'message' => '草稿保存失败']);
                }
            } else {
                $result = D('EShop/'.CONTROLLER_NAME)->templateAppendImplement($this->storage);
                if ($this->storage->key === self::TEMPLATE_APPEND_SEND_FIELD) {
                    $this->ajaxReturn($result ? ['error' =>0,'message' => '发送成功','id'=>$result] : ['error' =>1,'message' => '发送失败']);
                } else {
                    $this->ajaxReturn($result ? ['error' =>0,'message' => '草稿添加成功','id'=>$result] : ['error' =>1,'message' => '草稿添加失败']);
                }
            }

        }
    }
    /*
     * 分流性添加草稿/发送 - 加载用户
     */
    public function templateAppendImplementShuntUsersAction()
    {
        if(I('post.id') > 0) {
            $temp_id = I('post.id');
            $users = [];
            foreach(I('post.users') as $key => $val) {
                $users[] = $val['id'];
            }
            $append_relation = [];
            foreach ($users as $val) {
                $append_relation[] = [
                    'notice_id' => $temp_id,
                    'user_id' => $val,
                    'state' => WX_TEMPLATE_SEND_DEFAULT,
                    'created_at' => time(),
                    'updated_at' => time()
                ];
            }
            M('wx_notice_relation_user')->addAll($append_relation);
            $this->ajaxReturn(['error' =>0]);
        } else {
            $this->ajaxReturn(['error' =>1,'message' => '数据出错!']);
        }
    }
    public function templateAppendImplementShuntSendAction()
    {
        if (I('post.id') > 0) {
            //发送模板消息
            $notice_id = I('post.id');
            $notice = D('WxNoticeTemplateLibrary')->where('id = '.$notice_id)->find();
            $template_id = D('EShop/'.CONTROLLER_NAME)->getBranchTemplateId($notice['template_id']);
            $notice['template_id'] = $template_id;
            $notice['notice_id'] = $notice_id;
            $notice['content'] = I('post.content');
            $user = $this->handlerUserOpenidsFromTemplate(D('EShop/'.CONTROLLER_NAME)->getUserOpenidsFromTemplate($notice_id));
            $this->handlerSendTemplateFromUser($notice,$user);
            $this->ajaxReturn(['error' =>0,'message' => '发送完成']);
        } else {
            $this->ajaxReturn(['error' =>1,'message' => '发送失败']);
        }
    }
    public function sendUsersListsAction()
    {
        //获取标签和分组
        $groups = D('ComPotential')->getBranchTarget('group');
//        array_unshift($groups,['value'=>'未分组','id'=>'other']);
        $tags = D('ComPotential')->getBranchTarget('tag');
        $condition_groups['a.is_follow'] = 1;
        $condition_groups_other['is_follow'] = 1;
        foreach($groups as $k=>$v){
            $condition_groups['a.group_id'] = $v['id'];
            $groups[$k]['user_count'] = D('SysUser')
                                        ->setDacFilter('a')
                                        ->where($condition_groups)
                                        ->count();
        }
        //未分组处理
        $tmp = array('id'=>'other','value' => '未分组','branch_id' => $this->_user_session->currBranchId);
        $condition_groups_other['_string'] = 'a.group_id is null or a.group_id =""';
        $condition_groups_other['a.branch_id'] = $this->_user_session->currBranchId;
        $tmp['user_count'] = D('SysUser')
                            ->setDacFilter('a')
                            ->where($condition_groups_other)
                            ->count();
        array_unshift($groups,$tmp);
        //标签出路
        $condition_tags['b.is_follow'] = 1;
        foreach($tags as $k=>$v){
            $condition_tags['a.tag'] = $v['id'];
            $tags[$k]['user_count'] = D('SysUser')
                ->setDacFilter('b')
                ->join('right JOIN sys_user_relation_tag a ON a.user_id = b.id')
                ->where($condition_tags)->count();
        }
        $this->groups = $groups;
        $this->tags = $tags;
        $this->display('send_users_lists');
    }
    public function getSendUserAction()
    {
        $_filter = [];
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
                $_filter['a.group_id'] = array('in',$groups);
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
//        $_filter['a.user_type'] = array('neq',USER_TYPE_COMPANY_MANAGER);
        $count = D('SysUser') ->setDacFilter('a')
                            ->where($_filter)
                            ->count();
        $user_total = D('SysUser') ->setDacFilter('a')
                                ->field('a.id,a.name')
                                ->where($_filter)
                                ->select();
        $users = D('SysUser') ->setDacFilter('a')
                              ->join('left join sys_target_group as target on target.id = a.group_id')
                              ->where($_filter)
                              ->field('a.*,if(target.id > 0,target.value,"未分组") as group_name')
                              ->page(I('page'),20)
                              ->order('a.followed_at desc')
                              ->select();

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


        foreach($users as $key => $val) {
            $users[$key]['tag_name'] = D('UserParse')->getGroupNames($val);
			$inviter = M("SysUser")
				->alias("a")
				->join("LEFT JOIN distribution_relation b on b.inviter_id = a.id")
				->where(['b.openid' => $val['openid'] ])
				->field("a.name")
				->find();
			if (!empty($inviter)) {
				$users[$key]['superior_user'] = $inviter['name'];
			}else{
				$users[$key]['superior_user'] = '-';
			}


			if (isset($scene[$val['subscribe_scene']]) ) {
				$users[$key]['subscribe_scene_value'] = $scene[$val['subscribe_scene']];
			}else{
				$users[$key]['subscribe_scene_value'] = '-';
			}

			$company_names = D('ComPotential')->getCompanyNames($val);
			$users[$key]['company_names'] = $company_names;
			$users[$key]['followed_at'] = $val['followed_at'] ? date('Y-m-d H:i',$val['followed_at']) : '-';

        }
        $this->ajaxReturn(['total'=>$count,'data'=>$users,'user_total' => $user_total]);
    }
    public function linkShowAction()
    {
        $products = D('EShop/ComProduct')->getProductList();
        if ($products) {
            foreach($products as $key => $value)
            {
                $products[$key]['url'] = str_replace('shop','shop'.$this->_user_session->currBranchId,SHOP_ROOT).'/Product/productDetail/product_id/'.$value['product_id'].'.html' ;
            }
        }
        $this->products = json_encode($products ?? []);
        $tweets = D('EShop/ComTweets')->getSpreadList();
        if ($tweets) {
            foreach($tweets as $key => $value)
            {
                $tweets[$key]['url'] = str_replace('shop','shop'.$this->_user_session->currBranchId,SHOP_ROOT).'/Spread/tweets/id/'.$value['id'].'.html';
            }
        }
        $this->tweets = json_encode($tweets ?? []);
        $this->others  = json_encode($this->getLinkShowOther() ?? []);
        $this->display('link_show');
    }
    public function getContentAction()
    {
        $template_id = I('param.template_id');
        $template = D('EShop/'.CONTROLLER_NAME)->getContentTemplate($template_id);
        $result = $this->handlerContentRecords($template,'all');
        $this->handlerContentAppendData($result['content_records']);

        $this->ajaxReturn(buildResult($result));
    }
    public function userSendStatisticsAction()
    {
        $this->display('user_send_statistics');
    }
    public function statistics_listAction()
    {
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $_order = 'last_time desc';
        $condition['user.branch_id'] = $this->_user_session->currBranchId;
        $condition['user.is_valid'] = 1;
        $condition['a.type'] = 1;
        $keyword = trim(I('keyword',''));
        if ($keyword != '') {
            $condition['_string'] = '';
            $condition['_string'].= 'a.title like \''.sprintf("%%%s%%",$keyword).'\'';
            $condition['_string'].= '  or  ';
            $condition['_string'].= 'user.name like \''.sprintf("%%%s%%",$keyword).'\'';
            $condition['_string'].= '  or  ';
            $condition['_string'].= 'atg.value like \''.sprintf("%%%s%%",$keyword).'\'';
            $this->handlerStatisticsWhereFromTag($condition,$keyword);
            $condition['_string'].= '  or  ';
            $condition['_string'].= 'user.id = \''.$keyword.'\'';
        }
        $this->_parseFilter($condition);
        $field = 'user.id,user.no,user.name,a.title,user.comments,user.head_pic,user.mobile,if(user.group_id > 0,atg.value,\'未分组\') as group_name,max(a.id) as last_notice,max(a.send_at) as last_time';
        $count =  D('WxNoticeTemplateLibrary') ->setDacFilter('a')
                                            ->join('inner join wx_notice_relation_user wnru on wnru.notice_id = a.id')
                                            ->join('inner join sys_user user on user.id = wnru.user_id')
                                            ->join('left join sys_target_group atg on atg.id = user.group_id')
                                            ->field('user.id')
                                            ->where($condition)
                                            ->group('user.id')
                                            ->select();
        $users =  D('WxNoticeTemplateLibrary') ->setDacFilter('a')
                                            ->join('inner join wx_notice_relation_user wnru on wnru.notice_id = a.id')
                                            ->join('inner join sys_user user on user.id = wnru.user_id')
                                            ->join('left join sys_target_group atg on atg.id = user.group_id')
                                            ->field($field)
                                            ->where($condition)
                                            ->page($page_index, $page_size)
                                            ->order($_order)
                                            ->group('user.id')
                                            ->select();
        foreach ($users as $key => $value) {
            $where['id'] = $value['last_notice'];
            $template_data = M('wx_notice_template_library')
                                    ->field('title')
                                    ->where($where)
                                    ->find();
            $users[$key]['last_send_time'] = date('Y年m月d日 H:i:s',$value['last_time']);
            $users[$key]['last_template_title'] = $template_data['title'];
            $users[$key]['month_send_count'] = D(CONTROLLER_NAME)->getUserLastNoticeCount($value['id'],strtotime(date('Y-m',time())),'timestamp');
            $users[$key]['week_send_count'] = D(CONTROLLER_NAME)->getUserLastNoticeCount($value['id'],7,'day');
            $users[$key]['tag_name'] = D('UserParse')->getGroupNames($value);
        }
        $result["total"] = count($count);
        $result["rows"] = $users;
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($result));
    }
    //发送/存为草稿 request 处理函数 template_data 用于发送模板消息存放信息
    protected function handlerTemplateCUPImplementRevise()
    {
        $this->request = (object) I('post.');
        $this->storage = (object) $this->storage;
        if (in_array($this->request->key,[self::TEMPLATE_APPEND_DRAFT_FIELD,self::TEMPLATE_APPEND_SEND_FIELD,self::TEMPLATE_APPEND_PREVIEW_FIELD]) && $this->request->template_id > 0) {
            $this->storage->operation = 'append';
            //处理content
            if(is_array($this->request->content) && count($this->request->content) > 0) {
                foreach( $this->request->content as $key => $val) {
                    if (empty(trim($val['value'])) && in_array($this->request->key,[self::TEMPLATE_APPEND_PREVIEW_FIELD,self::TEMPLATE_APPEND_SEND_FIELD]) ){
                        $this->ajaxReturn(['error'=>1,'message' => $val['placeholder']]);
                        return false;
                    } else if (in_array($this->request->key,[self::TEMPLATE_APPEND_DRAFT_FIELD,self::TEMPLATE_APPEND_SEND_FIELD,self::TEMPLATE_APPEND_PREVIEW_FIELD])) {
                        $this->storage->content_array[] = trim($val['key']) === '' ?
                            $val['field'].self::BLACK_CUTTER.trim($val['value']).self::BLACK_CUTTER.$val['color'] :
                            $this->request->examples[$key]['key'].self::BLACK_CUTTER.$val['field'].self::BLACK_CUTTER.trim($val['value']).self::BLACK_CUTTER.$val['color'] ;
                    }
                }
            }
            //判断是否有空值
            if(count($this->request->content) > $this->storage->content_array && in_array($this->request->key,[self::TEMPLATE_APPEND_PREVIEW_FIELD,self::TEMPLATE_APPEND_SEND_FIELD])) {
                $this->ajaxReturn(['error'=>1,'message' => '发送通知时字段内容不能为空']);
                return false;
            } else {
                $this->storage->content = $this->handlerContentCUEnRecords($this->storage->content_array);
            }

            //验证 point 1跳转链接 2跳转至小程序
            if (in_array($this->request->key,[self::TEMPLATE_APPEND_PREVIEW_FIELD,self::TEMPLATE_APPEND_SEND_FIELD])) {
                if(empty($this->request->users) || count($this->request->users) == 0) {
                    $this->ajaxReturn(['error'=>1,'message' => '请选择发送对象']);
                    return false;
                }
                if ($this->request->point == 0 && trim($this->request->url) === '') {
                    $this->request->url = "";
                    /*$this->ajaxReturn(['error'=>1,'message' => '请输入跳转链接']);
                    return false;*/
                } else if ($this->request->point == 0 && trim($this->request->url) != '' ) {
                    $url_template = (strpos($this->request->url,'http') !== false) ? $this->request->url : 'http://'.$this->request->url;
                    $strRegex = '/^(http|https|ftp):\/\/[A-Za-z0-9]+\.[A-Za-z0-9]+[\/=\?%\-&_~`@[\]\’:+!]*([^<>\”])*$/';
                    if(!preg_match($strRegex,$url_template)) {
                        $this->ajaxReturn(['error'=>1,'message' => '请输入正确的跳转链接']);
                        return false;
                    }
                } else if ($this->request->point == 1) {
                    $wxConfig = getWxConfigData();
                    $this->request->xcx_appid = $wxConfig['xcx_appid'];
                    $this->request->xcx_url = 'pages/eshop/eshop';
                    if (trim($this->request->xcx_space_url) !== '') {
                        $url_template = (strpos($this->request->xcx_space_url,'http') !== false) ? $this->request->xcx_space_url : 'http://'.$this->request->xcx_space_url;
                        $strRegex = '/^(http|https|ftp):\/\/[A-Za-z0-9]+\.[A-Za-z0-9]+[\/=\?%\-&_~`@[\]\’:+!]*([^<>\”])*$/';
                        if(!preg_match($strRegex,$url_template)) {
                            $this->ajaxReturn(['error'=>1,'message' => '请输入正确的备用路径']);
                            return false;
                        }
                    }
//                    if (trim($this->request->xcx_space_url) === '') {
//                        $this->handlerResponse(['error'=>1,'message' => '请输入备用路径']);
//                        return false;
//                    }
                }
            }
            if ($this->request->id > 0 && in_array($this->request->key,[self::TEMPLATE_APPEND_DRAFT_FIELD,self::TEMPLATE_APPEND_SEND_FIELD])) {
                $this->storage->operation = 'update';
                $this->storage->{$this->storage->operation}['id'] = $this->request->id;
            }
            $this->storage->template_data['content'] = $this->request->content;
            $this->storage->template_data['point'] = $this->request->point;
            $this->storage->template_data['url'] = $this->request->url ?? null;
            $this->storage->template_data['xcx_appid'] = $this->request->xcx_appid ?? null;
            $this->storage->template_data['xcx_url'] = $this->request->xcx_url ?? null;
            $this->storage->template_data['xcx_space_url'] = $this->request->xcx_space_url ?? null;
            $this->storage->template_data['template_id'] = $this->request->template_id;
            //如果是预览的话,有user_id
            if ($this->request->key === self::TEMPLATE_APPEND_PREVIEW_FIELD) {
                $this->storage->template_data['users'] = $this->request->users;
            }
            if (in_array($this->request->key,[self::TEMPLATE_APPEND_DRAFT_FIELD,self::TEMPLATE_APPEND_SEND_FIELD]))
            {
                if ($this->request->users) {
                    foreach($this->request->users as $key => $val) {
                        $this->storage->users[] = $val['id'];
                    }
                }
                if (!$this->handlerHasTemplate($this->request->template_id)) {
                    $this->ajaxReturn(['error' =>1,'message' =>'所属的微信消息模板不存在或已删除,不能发送模板消息!']);
                    return false;
                }
                //整理出所需添加的数组
                $this->storage->key = $this->request->key;
                $this->storage->{$this->storage->operation}['content'] = $this->storage->content;
                $this->storage->{$this->storage->operation}['template_id'] = $this->request->template_id;
                $this->storage->{$this->storage->operation}['url'] = $this->request->url ?? null;
                $this->storage->{$this->storage->operation}['branch_id'] = $this->_user_session->currBranchId;
                $this->storage->{$this->storage->operation}['xcx_appid'] = $this->request->xcx_appid ?? null;
                $this->storage->{$this->storage->operation}['xcx_url'] = $this->request->xcx_url ?? null;
                $this->storage->{$this->storage->operation}['xcx_space_url'] = $this->request->xcx_space_url ?? null;
                $this->storage->{$this->storage->operation}['created_at'] = time();
                $this->storage->{$this->storage->operation}['updated_at'] = time();
                //新增字段 lynn start
                $this->storage->{$this->storage->operation}['user_id'] = $this->_user_session->userId;
                $this->storage->{$this->storage->operation}['creator_id'] = $this->_user_session->userId;
                //新增字段 lynn end
                $this->storage->{$this->storage->operation}['title'] = D('EShop/'.CONTROLLER_NAME)->getTemplateTitle($this->request->template_id);//Sep 28 新增title
                $this->storage->{$this->storage->operation}['point'] = $this->request->point;
                $this->storage->{$this->storage->operation}['send_at'] = $this->request->key === self::TEMPLATE_APPEND_SEND_FIELD ? time() : null;
                $this->storage->{$this->storage->operation}['type'] = $this->request->key === self::TEMPLATE_APPEND_DRAFT_FIELD ? self::TEMPLATE_APPEND_TYPE_DRAFT : self::TEMPLATE_APPEND_TYPE_SEND ;
            }
            return true;
        } else {
            $this->ajaxReturn(['error' =>1,'message' =>'操作失败!']);
            return false;
        }
    }
    //模板消息预览
    protected function handlerPreviewTemplateFromUser($template_data)
    {
        //获取用户openid
        $openid = D('EShop/'.CONTROLLER_NAME)->getUsersOpenId($template_data['users']);
        if (empty($openid)) {
            return ['error' =>1,'message' => 'openid缺失'];
        } else {
            $result =  $this->handlerWXSendTemplate($template_data,$openid);
            return $result["errcode"] == 0 ? true : ['error' =>1,'message' => getGlobalReturnCode($result["errcode"]).',预览失败'];
        }

    }
    //发送模板消息
    protected function handlerSendTemplateFromUser($template_data,$users)
    {
        if (!empty($users['success'])) {
            //异步发送模板消息
            $this->handlerWXSendTemplate($template_data,$users,$template_data['notice_id']);
        }
        $finally['notice_id'] =$template_data['notice_id'];
        if (isset($users['error'])) {
            $finally['error'] = $users['error'];
        }
        //处理用户发送是否成功信息
        D('EShop/'.CONTROLLER_NAME)->userSendTemplateFinally($finally);

    }
    //判断该模板是否存在
    protected function handlerHasTemplate($template_id)
    {
        $result_branch = D(CONTROLLER_NAME)->where('parent_id = '.$template_id.' and branch_id = '.$this->_user_session->currBranchId)->count();
        $result = D('wx_template_message')->where('id = '.$template_id)->count();
        return ($result > 0 && $result_branch > 0) ? true : false;
    }
    protected function handlerWXSendTemplate($template_data,$users,$notice_id = 0)
    {
        $user['success'] = [];
        $user['error'] = $users['error'];
        $message = array();
        $body = array();
        $message["template_id"] = $template_data['template_id'];
        $message["url"] = $template_data['point'] == 0 ? $template_data['url'] : $template_data['xcx_space_url'];
        if ($template_data['point'] == 1) {
            $message['miniprogram'] = [
                'appid' =>$template_data['xcx_appid'],
                'pagepath' =>$template_data['xcx_url']
            ];
        }
        foreach ( $template_data['content'] as $key =>$val) {
            $body[$val['field']]["value"]    = $val['value'];
            $body[$val['field']]["color"]    = $val['color'];
        }
        $message["body"] = $body;
        if ($notice_id > 0) {
            $data['message'] = $message;
            $data['users'] = $users['success'];
            $data['notice_id'] = $notice_id;
//            var_dump($data);die;
            send_wx_group_message($data,true);
        } else {
            $message["openid"] = $users;
//            var_dump($message);die;
            return send_wx_message($message);
        }
    }
    //处理template的openid
    protected function handlerUserOpenidsFromTemplate($users)
    {
        $array = [];
        if ($users) {
            foreach($users as $key => $val) {
                if (empty($val['openid'] )){
                    $array['error'][$val['id']] = ['id'=>$val['id'],'errcode' => '400' , 'errmsg' => 'openid缺失'];
                } else {
                    $array['success'][$val['id']] = $val;
                }
            }
        }
        return $array;
    }
    //处理修改草稿
    protected function handlerPreviewUpdateData($data,$type='update')
    {
        $condition["a.branch_id"] = $this->_user_session->currBranchId;
        $list = M(CONTROLLER_NAME)->alias("a")
            ->join("inner join wx_template_message b on b.id=a.parent_id")
            ->field("b.id as value,b.title as text")
            ->where($condition)->select();
        $this->templates = count($list) > 0 ? json_encode($list) : [];
        $this->users = json_encode($data['users']);
        $this->notice = $data['notice'];
        $this->template_id = $this->notice['template_id'];
        $example = $this->handlerContentRecords(D('EShop/'.CONTROLLER_NAME)->getContentTemplate($this->template_id),'example');
        $content = $this->handlerNoticeContentRecords($data['notice']);
        $this->contents = json_encode(['content_records'=>$content,'example_records'=>$example]);
        $this->point = $data['notice']['point'];
        $this->url = $data['notice']['url'];
        $this->xcx_appid = $data['notice']['xcx_appid'];
        $this->xcx_url = $data['notice']['xcx_url'];
        $this->xcx_space_url = $data['notice']['xcx_space_url'];
        $this->id = $type == 'update'? $data['notice']['id'] : 0;
        $this->sign = $type;
    }
    //储存于数据库时 content的数据处理
    protected function handlerContentCUEnRecords($content)
    {
        return implode("\r\n",$content);
    }
    public function userSendDescAction($id)
    {
        $this->assign('id',$id);
        $this->display('user_send_desc');
    }
    public function userSendListAction($id)
    {
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $_order = 'a.send_at desc';
        $condition['a.type'] = 1;
        $condition['user.id'] = $id;
        $this->_parseFilter($condition);
        $field = 'user.id as user_id,a.id,a.template_id,a.title,a.send_at,user.no,user.name,user.comments,user.head_pic,user.mobile,if(user.group_id > 0,atg.value,\'未分组\') as group_name';
        $count =  D('WxNoticeTemplateLibrary') ->setDacFilter('a')
                    ->join('inner join wx_notice_relation_user wnru on wnru.notice_id = a.id')
                    ->join('inner join sys_user user on user.id = wnru.user_id')
                    ->field('a.id')
                    ->where($condition)
                    ->group('a.id')
                    ->select();
        $users =  D('WxNoticeTemplateLibrary') ->setDacFilter('a')
                    ->join('inner join wx_notice_relation_user wnru on wnru.notice_id = a.id')
                    ->join('inner join sys_user user on user.id = wnru.user_id')
                    ->join('left join sys_target_group atg on atg.id = user.group_id')
                    ->field($field)
                    ->where($condition)
                    ->page($page_index, $page_size)
                    ->order($_order)
                    ->group('a.id')
                    ->select();
        foreach ($users as $key => $value) {
            $users[$key]['tag_name'] = D('UserParse')->getGroupNames(['id'=>$value['user_id']]);
            $users[$key]['send_time'] = date('Y年m月d日 H:i:s',$value['send_at']);
            $users[$key]['template_title'] = $value['title'];
        }
        $result["total"] = count($count);
        $result["rows"] = $users;
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($result));
    }
    public function noticeDescAction(){
        //获取模板发送的详情
        $id = I('get.id');
        $notice = D('EShop/'.CONTROLLER_NAME)->templateSingleDetail($id);
        $this->handlerTemplateSingleData($notice);
        if (!empty(I('get.sign')) && I('get.sign') == 'history')
        {
            $this->sign = 'history';
        }
        if (!$this->handlerHasTemplate($notice['template_id'])) {
            $this->prohibit = true;
        }
        $this->display('notice_desc');
    }
    public function noticeUsersAction($id){
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $condition['a.notice_id'] = $id;
        $count = D('wx_notice_relation_user')
                        ->alias('a')
                        ->where($condition)
                        ->count();
        $notice = D('wx_notice_relation_user')
                        ->alias('a')
                        ->field('user.id,user.no,user.name,user.comments,user.head_pic,user.mobile,if(user.group_id > 0,atg.value,\'未分组\') as group_name')
                        ->join('inner join sys_user user on user.id = a.user_id')
                        ->join('left join sys_target_group atg on atg.id = user.group_id')
                        ->page($page_index, $page_size)
                        ->where($condition)
                        ->select();
        foreach($notice as $key=>$val) {
            $notice[$key]['tag_name'] = D('UserParse')->getGroupNames($val);
        }
        $result["total"] = $count;
        $result["rows"] = $notice;
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($result));
    }
    //模板处理函数
    protected function handlerContentRecords($data,$inc ='all')
    {
        $result = array();
        if ($inc === 'all') {
            $result["content_records"] = $this->handlerContentRecords($data,'content');
            $result["example_records"] = $this->handlerContentRecords($data,'example');
            return $result;
        } else {
            if ($inc == 'example') {
                $count = substr_count($data[$inc],self::BLACK_CUTTER);
            }
            $content_records = explode("\r\n", $data[$inc]);
            foreach ($content_records as $key => $content_record){
                if (strpos($content_record,'.DATA') || $inc =='example') {
                    if (($inc == 'example' && $key >=(count($content_records) - $count - 2)) || $inc == 'content') {
                        $items = explode(self::BLACK_CUTTER, $content_record);
                        if($items[0] !='' || $items[1] != '') {
                            if (count($items) == 1) {
                                $result[$inc . "_records"][] = array("key" => "", "title" => $items[0]);
                            } else {
                                $result[$inc . "_records"][] = array("key" => $items[0], "title" => $items[1]);
                            }
                        }
                    } else {
                        if (trim($content_record) != '') {
                            $result[$inc . "_records"]['first'] = array("key" => "", "title" => $content_record);
                        }
                    }
                }
            }
            return $result[$inc."_records"];
        }
    }
    protected function handlerContentAppendData(&$content)
    {
        foreach ($content as $key => $value) {
            $content[$key]['field'] = str_replace(array('{{','.DATA}}'),'',$value['title']);
            $content[$key]['value'] = '';
            $content[$key]['color'] = '#000000';
            if ($key == 0) {
                $content[$key]['placeholder'] = '请输入消息提示';
                $content[$key]['view'] = '消息提示';
            } else if (trim($value['key']) != '') {
                $content[$key]['placeholder'] = '请输入'.$value['key'];
                $content[$key]['view'] = $value['key'];
            } else {
                $content[$key]['placeholder'] = '请输入消息备注';
                $content[$key]['view'] = '消息备注';
            }
        }
//        var_dump($content);die;
    }
    protected function handlerStatisticsWhereFromTag(&$condition,$keyword)
    {
        $where['value'] = array('like',sprintf("%%%s%%",$keyword));
        $where['branch_id'] = $this->_user_session->currBranchId;
        $result = M('SysTargetTag')
                                    -> where($where)
                                    -> getField('id',true);
        if ($result) {
            foreach ($result as $value)
            {
                $condition['_string'].= '  or  ';
                $condition['_string'].= ' FIND_IN_SET(\''.$value.'\',user.tag_ids) ';
            }
        }
    }
    protected function handlerTemplateSingleData($data,$type = true)
    {
//        var_dump($this->handlerNoticeContentRecords($data));die;
        $this->template = $type ? json_encode($this->handlerNoticeContentRecords($data)) : $this->handlerNoticeContentRecords($data);
        $this->send_time = date('Y年m月d日 H:i:s',$data['send_at']);
        $this->success = $data['success_count'];
        $this->error = $data['error_count'];
        $this->point = $data['point'];
        $this->url = $this->handlerUrl($data['url']);
        $this->xcx_appid = $data['xcx_appid'];
        $this->xcx_url = $data['xcx_url'];
        $this->xcx_space_url = $this->handlerUrl($data['xcx_space_url']);
        $this->notice_id = $data['id'];
        $this->title = $data['title'];
    }
    //输出默认其他列表 - 功能链接
    protected function getLinkShowOther()
    {
        $branch_id = getBrowseBranchId();
        $tmp = SHOP_ROOT;
        $tmp1 = strstr($tmp,'.caisuikx.com',true);
        $url = $tmp1.$branch_id.strstr($tmp,'.caisuikx');
        $res = [
            [
                'title'=>'商城首页',
                'url' => $url."/Index/index"
            ],
            [
                'title'=>'免费咨询',
                'url' => $url."/Tool/consult"
            ]
        ];
        return $res;
    }
    public function handlerNoticeContentRecords($data,$inc='content')
    {
        $result = array();
        $content_records = explode("\r\n", $data[$inc]);
        foreach ($content_records as $content_record){
            $items = explode(self::BLACK_CUTTER,$content_record);
            if (count($items) == 3){
                $result[$inc."_records"][] = array("field"=>$items[0], "value"=>$items[1],'color' => $items[2]);
            }else{
                $result[$inc."_records"][] = array("key"=>$items[0],"field"=>$items[1], "value"=>$items[2],'color' => $items[3]);
            }
        }
        $this->handlerContentDetailData($result[$inc."_records"]);
        return $result[$inc."_records"];
    }
    protected function handlerContentDetailData(&$content)
    {
        foreach ($content as $key => $value) {
            if ($key == 0) {
                $content[$key]['placeholder'] = '请输入消息提示';
                $content[$key]['view'] = '消息提示';
            } else if (trim($value['key']) != '') {
                $content[$key]['placeholder'] = '请输入'.$value['key'];
                $content[$key]['view'] = $value['key'];
            } else {
                $content[$key]['placeholder'] = '请输入消息备注';
                $content[$key]['view'] = '消息备注';
            }
        }
//        var_dump($content);die;
    }
    protected function handlerUrl($url){
        return strpos($url,'http') === false ? 'http://'.$url : $url;
    }
}