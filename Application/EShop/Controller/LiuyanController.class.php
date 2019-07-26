<?php

namespace EShop\Controller;

use Think\Controller;

class LiuyanController extends UserLoginController {

    public function indexAction() {
        redirect('/Talks/index');
    }

    public function orderAction(){
        $title = "留言管理";
        $attach_group = I('attach_group');
        $this->assign('attach_group', $attach_group);
        $this->assign('title', $title);
        $this->display('index');
    }

    public function HistoryAction(){
        $ask_model = D('SysAsk');
        $start = I('get.start', 0, 'intval');
        $num   = I('get.n', 0, 'intval');
        $prefix = C('DB_PREFIX');
        //获取当前branch_id
        $branch_id  = getBrowseBranchId();
        $where      ="a.obj_type <> 'shop' and a.branch_id = ".$branch_id;
        // $where      =" a.branch_id = ".$branch_id;
        $where     .=" and (";
        //添加系统消息
        $where     .=" ( a.obj_id = ".$_SESSION['user_id']." and a.obj_type = 'system' ) ";
        if($_SESSION['user_type'] == USER_TYPE_COMPANY_MANAGER){
            $where	.=" or a.obj_id = ".$_SESSION['user_id'];
            //获取该用户的所有产品的所有订单
            $pro_where['branch_id']	=	$branch_id;
            $order_id	=  M("ComOrder")->where($pro_where)->getField("id",true);
            if($order_id){
                $order_lists = implode(',', $order_id);
                $where	.=" or (a.obj_id in(".$order_lists.")  and  a.origin_id != ".$_SESSION['user_id']." and a.obj_type = 'order' )";
            }
            $tool_id	=  M("tool_query")->where($pro_where)->getField("id",true);
            if($tool_id){
                $tool_ids = implode(',', $tool_id);
                $where	.=" or (a.obj_id in(".$tool_ids.")  and  a.origin_id != ".$_SESSION['user_id']." and a.obj_type = 'tool' )";
            }
            $where .=" or (a.obj_id = ".$branch_id.')';
            $where .=" )";
        }else{
            $where     .="  or a.origin_id = ".$_SESSION['user_id']." or a.user_id = ".$_SESSION['user_id'].')';
        }
        //获取数据
        $list		=$ask_model
            ->alias('a')
            ->field("a.origin_id,a.obj_id,a.obj_type,max(a.comment_time) as time")
            ->where($where)
            ->group("a.obj_type,a.obj_id,a.origin_id")
            ->limit($start,$num)
            ->order("time desc")
            ->select();
        //获取对应数据
        $lists	=	array();
        foreach ($list as $key => $value) {
            $ask_id			=	$ask_model->where("comment_time = ".$value['time'])->getField("id");
            if($value['obj_type']	==	'shop' && $value['origin_id'] == $_SESSION['user_id']){
                $lists[$key] = $ask_model
                    ->alias("a")
                    ->field("a.id,a.attach_1,CONCAT('微店咨询') as title,a.obj_type,a.content,a.comment_time,a.origin_id,a.obj_id")
                    ->where("a.id = ".$ask_id.' and a.branch_id ='.$branch_id)
                    ->find();
            }elseif($value['obj_type']	==	'shop' && $value['origin_id'] != $_SESSION['user_id']){
                $lists[$key] =$ask_model
                    ->alias("a")
                    ->field("a.id,a.attach_1,CONCAT('微店留言') as title,a.obj_type,a.content,a.comment_time,a.origin_id,a.obj_id")
                    ->where("a.id = ".$ask_id.' and a.branch_id ='.$branch_id)
                    ->find();
            }elseif($value['obj_type']	==	'order' && $value['origin_id'] == $_SESSION['user_id']){
                $lists[$key] = $ask_model
                    ->alias("a")
                    ->field("a.id,a.attach_1,CONCAT('服务|',o.product_title) as title,a.obj_type,a.content,a.comment_time,a.origin_id,a.obj_id")
                    ->join("com_order o on o.id = a.obj_id")
                    ->where("a.id = ".$ask_id.' and a.branch_id ='.$branch_id)
                    ->find();
            }elseif($value['obj_type']	==	'order' && $value['origin_id'] != $_SESSION['user_id']){
                $lists[$key] = $ask_model
                    ->alias("a")
                    ->field("a.id,a.attach_1,CONCAT('服务|',o.product_title) as title,a.obj_type,a.content,a.comment_time,a.origin_id,a.obj_id")
                    ->join("com_order o on o.id = a.obj_id")
                    ->where("a.id = ".$ask_id.' and a.branch_id ='.$branch_id)
                    ->find();
            }elseif($value['obj_type']	==	'tool'){
                $lists[$key] = $ask_model
                    ->alias("a")
                    ->field("a.id,a.attach_1,(case o.type when 0 then '工具|免费核名' when 1 then '工具|商标查询' when 2 then '工具|免费咨询' else '' end) as title,a.obj_type,a.content,a.comment_time,a.origin_id,a.obj_id")
                    ->join("tool_query o on o.id = a.obj_id")
                    ->where("a.id = ".$ask_id.' and a.branch_id ='.$branch_id)
                    ->find();
            } elseif($value['obj_type']	==	'system' && $value['obj_id'] == $_SESSION['user_id']){
                $system_ask_id	=	$ask_model
                    ->alias("a")
                    ->where("a.obj_id = ".$_SESSION['user_id']." and a.comment_time = ".$value['time'])
                    ->getField("a.id");
                $lists[$key] = $ask_model
                    ->alias("a")
                    ->field("a.id,CONCAT('系统信息') as title,a.obj_type,a.content,a.comment_time,a.origin_id,a.obj_id")
                    ->where("a.id = ".$system_ask_id.' and a.branch_id ='.$branch_id)
                    ->find();
            }
        }

//        dump($lists);
//        die;
        // echo json_encode($lists);
        //       exit;
        foreach ($lists as $key => $val) {
            //判断是否有未读信息
            if($val['id']){
                //默认头像
                $default	=	getDefalutHeadPic();
                //判断是我的留言还是他人留言
                if($val['origin_id']	==	$_SESSION['user_id']){
                    $head_url	=	M('SysUser')
                        ->where("branch_id = ".$branch_id.' and user_type = '.USER_TYPE_COMPANY_MANAGER)
                        ->getField("head_pic");
                    $url_type	=	'me';
                    $head_pic	=	$head_url?$head_url:$default;
                }else{
                    $head_url	=	M("SysUser")->where('id = '.$val['origin_id'])->getField('head_pic');
                    $head_pic	=	$head_url?$head_url:$default;
                    $url_type	=	'ta';
                }
                $ask_data	    =	$ask_model->where("id = ".$val['id'])->find();
                $read           =   $ask_model->where("`read`='1' and branch_id = ".$branch_id." and origin_id = '" .$ask_data['origin_id']. "' and obj_id = ".$ask_data['obj_id']."  and user_id <>'".$_SESSION['user_id']."'")->count();//获取到他人留言后 对方回复后,未浏览的数量
                $is_read	    =	$read?1:0;
                if($val['obj_type'] == 'shop'){

                } elseif($val['obj_type'] == 'system'){
                    $sayList['lists'][] = array('kid' => $val['id'],
                        'title'=> $val['title'],
                        'is_read'=> $is_read,
                        'url_type'=>$url_type,
                        'type'=> 'system',
                        'content' =>($val['attach_1'] != '') ?
                            getAskUploadFileImages($val['attach_1'],'',true)['type_name'] :
                            base64_decode($val['content']),
                        'begtime' => timeline($val['comment_time'],1),
                        'timestamp' => $val['comment_time'],
                        'head_url'=>$head_pic
                    );
                }else{
                    //如果是用户的话,
                    $val['title']		=	$val['title'];
                    $sayList['lists'][] = array('kid' => $val['id'],
                        'title'=> $val['title'],
                        'is_read'=> $is_read,
                        'url_type'=>$url_type,
                        'type'=> 'user',
                        'content' => ($val['attach_1'] != '') ?
                            getAskUploadFileImages($val['attach_1'],'',true)['type_name'] :
                            base64_decode($val['content']),
                        'begtime' => timeline($val['comment_time'],1),
                        'timestamp' => $val['comment_time'],
                        'head_url'=>$head_pic
                    );
                }
            }

        }
        $last_names = array_column($sayList['lists'],'timestamp');
        array_multisort($last_names,SORT_DESC,$sayList['lists']);
        $sayList['count'] = count($sayList['lists']);

        echo json_encode($sayList);
        // $start = I('get.start', 0, 'intval');
    }
    public function MeLeaveAction(){
        $title = "我的咨询-回复";
        $this->assign('title', $title);
        $ask_model = D('SysAsk');
        $branch_id = getBrowseBranchId();
        $action = I('post.action', '', 'strip_tags');
        if ($action == "") {
//            $ask_id = I('get.id', 0, 'intval');
//            $ask = $ask_model->where("ask_id='$ask_id'")->find();
//            $this->assign('ask', $ask);
//            if ($ask['obj_type'] == 'shop') {
//                $shop = M('company')->where("company_id='$id'")->find();
//                $this->assign('shop', $shop);
//                $this->assign('id', $id);
//                $this->display();
//            } else {
//                $task = M('task t')->join(C('DB_PREFIX') . 'users u on u.user_id = t.parent_id')->where("task_id=" . $ask['obj_id'])->find();
//                $this->assign('task', $task);
//                $this->assign('id', $ask['ask_id']);
//                $this->display();
//            }
        } else {
            $ask_id = I('post.id', 0, 'intval');
            if($action == 'shop_ask'){
                $ask =	array(
                    'obj_id' =>$branch_id,
                    'origin_id' => $_SESSION['user_id'],
                    'obj_type'	=>	'shop',
                );
            }elseif($action == 'order_ask'){
                $order = M("ComOrder")->field("user_id")->where('id = '.$ask_id)->find();
                $ask =	array(
                    'obj_id' =>$ask_id,
                    'origin_id' => $order['user_id'],
                    'obj_type'	=>	'order',
                );
            }else{
                $ask = $ask_model -> where("id='$ask_id'") -> find();
            }
            $data['obj_id'] = $ask['obj_id'];
            $data['origin_id'] = $ask['origin_id'];
            $data['obj_type'] = $ask['obj_type'];
            $data['user_id'] = $_SESSION['user_id'];
            $data['content'] = base64_encode(I('post.content'));
            $data['attach_1'] = I('post.attach_1');
            $data['branch_id'] = $branch_id;
            $data['comment_time'] = time();
            $result = $ask_model->data($data)->add();
            if($result){
                //判断上条消息是否超过3分钟，超过则发送微信通知
                $condition['_string'] = ' comment_time < '.strtotime(date('Y-m-d H:i:s',time())).' and comment_time > '.strtotime(date('Y-m-d H:i:s',strtotime('-3 minute')));
                $condition['obj_id'] = $data['obj_id'];
                $condition['origin_id'] = $ask['origin_id'];
                $condition['obj_type'] = $ask['obj_type'];
                $count = M("SysAsk")->where($condition)->count();
                if($count<1){
                    $this->liuyanSendWx($data['obj_id'],I('post.content'));
                }
            }
            $user = M('SysUser')->where(array('id' => $_SESSION['user_id']))->find();
            $head_pic = $user['head_pic'];
            $face = strpos($head_pic, 'Public/') === false ? $head_pic : "/" . $head_pic;
            $face = is_null($face)?getDefalutHeadPic():$face;
            echo json_encode(array("error" => 0, 'msg' => '留言成功！', 'face' => $face, 'comment_time' => timeline($data['comment_time'])));
            exit();
        }
    }

    //留言发送通知给商家
    public function liuyanSendWx($obj_id,$content){
        $condition['consultant'] = "留言";
        $condition['type'] = 3 ;
        $condition['id'] = $obj_id;
        $condition['reply'] = $content;
        D('ComOrder')->sendWXConsult($condition,3);
    }

    public function getNewNewsAction(){
        $ask_model = D('SysAsk');
        if(I("post.id") != ""){
            $tmp['id'] = I('post.id');
            $ask = $ask_model->where($tmp)->find();
            $condition['obj_id'] =  $ask['obj_id'];
            $condition['origin_id'] = $ask['origin_id'];
            $condition['user_id'] = array("neq",$_SESSION['user_id']);
            //$ask = $ask_model->where($condition)->order("id desc")->find();
            $last_time = I("post.last_time");
            /*if($last_time != $ask['comment_time']){
                $user = M('SysUser')->where(array('id' => $ask['user_id']))->find();
                $head_pic = $user['head_pic'];
                $face = strpos($head_pic, 'Public/') === false ? $head_pic : "/" . $head_pic;
                $face = is_null($face)?getDefalutHeadPic():$face;
                $this->ajaxReturn(array("content"=>base64_decode($ask['content']),"begtime"=>date("Y-m-d H:i:s",$ask['comment_time']),"comment_time"=>$ask['comment_time'],"face"=>$face));
            }*/
            if($last_time){
                $condition['_string'] = ' comment_time > '.$last_time;
            }
            $condition['read'] = 1;
            $ask = $ask_model->where($condition)->order("comment_time asc")->select();
            foreach ($ask as $k=>$value){
                $ask_model->where("id = " .$value['id'])->setField("read",0);
                $ask[$k]['begtime'] = date("Y-m-d H:i:s",$value['comment_time']);
                $ask[$k]['content'] = base64_decode($value['content']);
                //头像
                $user = M('SysUser')->where(array('id' => $value['user_id']))->find();
                $head_pic = $user['head_pic'];
                $face = strpos($head_pic, 'Public/') === false ? $head_pic : "/" . $head_pic;
                $face = is_null($face)?getDefalutHeadPic():$face;
                $ask[$k]['face'] = $face;
            }
            //var_dump($ask);
            $this->ajaxReturn($ask);
        }else{
            $obj_type = I("post.action");
            if($obj_type == "tool"){
                $condition['obj_id'] = I("post.tool_id");
            }else{
                $condition['origin_id'] = $_SESSION['user_id'];
            }
            $condition['user_id'] = array("neq",$_SESSION['user_id']);
            $condition['obj_type'] = $obj_type;
            //$ask = $ask_model->where($condition)->order("id desc")->find();
            $last_time = I("post.last_time");
            /*if($ask['content'] != "" && $last_time !=$ask['comment_time']){
                $user = M('SysUser')->where(array('id' => $ask['user_id']))->find();
                $head_pic = $user['head_pic'];
                $face = strpos($head_pic, 'Public/') === false ? $head_pic : "/" . $head_pic;
                $face = is_null($face)?getDefalutHeadPic():$face;
                $this->ajaxReturn(array("content"=>base64_decode($ask['content']),"begtime"=>date("Y-m-d H:i:s",$ask['comment_time']),"comment_time"=>$ask['comment_time'],"face"=>$face));
            }*/
            if($last_time){
                $condition['_string'] = ' comment_time > '.$last_time;
            }
            $condition['read'] = 1;
            $ask = $ask_model->where($condition)->order("comment_time asc")->select();
            foreach ($ask as $k=>$value){
                $ask_model->where("id = " .$value['id'])->setField("read",0);
                $ask[$k]['begtime'] = date("Y-m-d H:i:s",$value['comment_time']);
                $ask[$k]['content'] = base64_decode($value['content']);
                //头像
                $user = M('SysUser')->where(array('id' => $value['user_id']))->find();
                $head_pic = $user['head_pic'];
                $face = strpos($head_pic, 'Public/') === false ? $head_pic : "/" . $head_pic;
                $face = is_null($face)?getDefalutHeadPic():$face;
                $ask[$k]['face'] = $face;
            }
            //var_dump($ask);
            $this->ajaxReturn($ask);
        }
    }



    public function MeAction(){
        $title = "留言管理";
        $this->assign('title', $title);
        $is_read = 0;
        $ask_model = D('SysAsk');
        $branch_id = getBrowseBranchId();
        if(I("get.order_id")){
            $obj_order_id	=	I("get.order_id");
            //获取留言表中的信息
            $ask_lists		=	$ask_model->where("branch_id = ".$branch_id." and obj_type = 'order' and obj_id = ".$obj_order_id)->order("comment_time desc")->find();
            if($ask_lists){
                //如果在留言表里有存在,获取ask_id 且继续进行下一步
                $ask_id	=	$ask_lists['id'];
            }else{
                //如果在留言表里没有存在,获取company信息,终止下一步
                $company	=	M("ComOrder o")
                    ->field("o.*")
                    ->where("o.id = ".$obj_order_id)->find();
                $user_name	=	'';
                $product['product_title']	=		$company['product_title'];
                $product['product_category']=		$company['product_category'];
                $product['order_state']		=		order_stateing($company);
                $this->assign('product',$product);
                $this->assign('user_name',$user_name);
                $this->assign('count',0);
                $this->assign('siblings_type','order');
                $this->assign('company',$company);
                $this->assign('obj_id',$obj_order_id);
                $this->assign('company_name',$company['company_name']);
                $this->assign("action","order");
                $this->display();
                die;
            }
        }elseif(I("get.id")){
            $ask_id = I('get.id', 0, 'intval');
        }else{ //传入branch_id或空，表示店铺咨询
            $obj_branch_id	=	getBrowseBranchId();
            //获取留言表中的信息
            $ask_lists		=	$ask_model->where("obj_id = ".$obj_branch_id.' and origin_id = '.$_SESSION['user_id'])->order("comment_time desc")->find();
            if($ask_lists){
                //如果在留言表里有存在,获取ask_id 且继续进行下一步
                $ask_id	=	$ask_lists['id'];
            }else{
                $this->assign('siblings_type','shop');
                $this->assign('count',0);
                $this->assign('company_name',getComStoreData('name'));
                $this->assign("action","shop");
                $this->display();
                die;
            }
        }
        $ask = $ask_model->where("id='$ask_id'")->find();
        $this->assign('ask', $ask);
        if ($ask['obj_type'] == 'order') {
            $order   = M('ComOrder')   -> where("id = " . $ask['obj_id']) -> find();
            $product['product_title']	=		$order['product_title'];
            $product['product_category']		=		$order['product_category'];
            $product['order_state']		=		order_stateing($product);
            $this ->assign('product',$product);
            $this -> assign('id', $ask['id']);
            $this->assign('company_name',$product['product_title']);
        }else if ($ask['obj_type'] == 'tool') {
            $tool	=	M("tool_query")
                ->field("*")
                ->where("id = ".$ask['obj_id'])->find();
            $this->handlerToolShow($tool);
            $this->assign('user_name', $tool['nickname']);
            $this->assign('tool',$tool['view']);
            $this -> assign('id', $ask['id']);
            $this->assign('company_name',$tool['title'].'消息');
        }else{
            $this->assign('company_name',getComStoreData('name'));
            $this -> assign('id', $ask['id']);
        }
        $where = $ask['obj_type'] == 'order' ?
            array("a.branch_id"=>$branch_id, "a.obj_id" => $ask['obj_id'], "a.obj_type" => $ask['obj_type']):
            array("a.branch_id"=>$branch_id,"a.origin_id" => $_SESSION['user_id'], "a.obj_id" => $ask['obj_id'], "a.obj_type" => $ask['obj_type']);
        $a_table = 'sys_ask';
        $u_table = 'sys_user';
        $list = M()->field('a.user_id,a.content,a.comment_time,u.head_pic,a.id,a.read,a.attach_1')
            ->table($a_table . ' a')
            ->join($u_table . ' u on a.user_id=u.id')
            ->where($where)
            ->order('a.comment_time')
            ->select();
        $sayList['count'] = count($list);
        foreach ($list as $val) {
            //设置为已读
            if($val['user_id'] != $_SESSION['user_id'] && $val['read'] == 1){
                $datay['read'] = 0;
                $ask_model->where("id=".$val['id'])->save($datay);
                $is_read = 1;
            }
            $flag = 0;
            if ($val['user_id'] != $_SESSION['user_id']) {
                $flag = 1;
            }
            $face	=	strpos($val['head_pic'], 'Public/') === false ? $val['head_pic'] : "/" . $val['head_pic'];
            $attachs =   getAskUploadFileImages($val['attach_1'],'',true);
            $sayList['lists'][] = array(
                'face' 		=> $face?$face:getDefalutHeadPic(),
                'content' 	=> base64_decode($val['content'], true),
                'begtime' 	=> timeline($val['comment_time']),
                'flag' 		=> $flag,
                'user_id' 	=> $val['user_id'],
                'attach'	=> $val['attach_1'],
                'attach_type'=> $attachs['type'],
                'comment_time'=> $val['comment_time']
            );
        }
        $this->assign('list',$sayList['lists']);
        $this->assign('count',$sayList['count']);
        $this->display();
    }
    public function TaReplyAction(){
        $title = "留言管理";
        $this->assign('title', $title);
        $ask_model = D('SysAsk');
        $branch_id = getBrowseBranchId();
        if (I('get.order_id')){
            $obj_order_id = I('get.order_id');
            $ask_lists		=	$ask_model->where("branch_id = ".$branch_id." and obj_type = 'order' and obj_id = ".$obj_order_id)->order("comment_time desc")->find();
            if ($ask_lists){
                $ask_id = $ask_lists['id'];
            }else{
                $company	=	M("ComOrder o")
                    ->field("o.*")
                    ->where("o.id = ".$obj_order_id)->find();
                $user_name	=	'';
                $product['product_title']	=		$company['product_title'];
                $product['product_category']=		$company['product_category'];
                $product['order_state']		=		order_stateing($company);
                $this->assign('product',$product);
                $this->assign('user_name',$user_name);
                $this->assign('count',0);
                $this->assign('msg_type','order');
                $this->assign('company',$company);
                $this->assign('obj_id',$obj_order_id);
                $this->assign('company_name',$company['contacts']);
                $this->assign("action","order");
                $this->display();
                exit();
            }
        }else if(I('get.tool_id')){
            $obj_tool_id = I('get.tool_id');
            $ask_lists		=	$ask_model->where("branch_id = ".$branch_id." and obj_type = 'tool' and obj_id = ".$obj_tool_id)->order("comment_time desc")->find();
            if ($ask_lists){
                $ask_id = $ask_lists['id'];
                $this->assign("obj_id",$obj_tool_id);
            }else{
                $tool	=	M("tool_query")
                    ->field("*")
                    ->where("id = ".$obj_tool_id)->find();
                $this->handlerToolShow($tool);
                $this->assign('user_name', $tool['nickname']);
                $this->assign('tool',$tool['view']);
                $this->assign('count',0);
                $this->assign('msg_type',$tool['msg_type']);
                $this->assign('obj_id',$obj_tool_id);
                $this->assign('company_name',$tool['title'].'消息');
                $this->assign("action","tool");
                $this->display();
                exit();
            }
        }else{
            $ask_id = I('get.id', 0, 'intval');
            $this->assign("action","shop");
        }
        $ask_model = D('SysAsk');
        $user_model= D('SysUser');
        $branch_id  = getBrowseBranchId();
        $ask = $ask_model->where("id='$ask_id'")->find();
        $susu = $ask_model->where("id='$ask_id'")->find();
        $is_read = 0;
        if ($susu['read'] == 1) {
            $datay['read'] = 0;
            $is_read = 1;
            $ask_model->where("id='$ask_id' and user_id = ".$susu['origin_id'])->save($datay);
        }
        $this->assign('ask', $ask);
        if($ask['origin_id'] > 0 || I('get.order_id') > 0 ){
            $view['company_name']	=	'消息';
            $order_condition = 'a.comment_time asc';
            $is_system = 0;

        }else{
            $this->assign('system','1');
            $view['company_name']	=	'系统消息';
            $order_condition = 'a.comment_time desc';
            $is_system = 1;
        }
        $this->assign('is_system',$is_system);
        if($ask['obj_type'] == 'order'){
            $a_table = 'sys_ask';
            $d_table = 'com_order';
            $service_data = M()->table($a_table . ' a')
                ->field("d.*")
                ->join($d_table . ' d on a.obj_id=d.id')
                ->where(array('a.id' => $ask_id))
                ->find();
            $product['product_title']	=		$service_data['product_title'];
            $product['product_category']=		$service_data['product_category'];
            $product['order_state']		=		order_stateing($service_data);
            $user_name					=		$service_data['contacts'];
            $this->assign('user_name', $user_name);
            $this->assign('product',$product);
        } else if($ask['obj_type'] == 'tool'){
            $a_table = 'sys_ask';
            $d_table = 'tool_query';
            $tool = M()->table($a_table . ' a')
                ->field("d.*")
                ->join($d_table . ' d on a.obj_id=d.id')
                ->where(array('a.id' => $ask_id))
                ->find();
            $this->handlerToolShow($tool);
            $this->assign('user_name', $tool['nickname']);
            $this->assign('tool',$tool['view']);
            $view['company_name']	=	$tool['title'].'消息';
        }
        $this->assign('company_name', $view['company_name']);
        $this->assign('id', $ask['id']);
        $ask = $ask_model->where("id='$ask_id'")->find();
        $a_table = 'sys_ask';
        $u_table = 'sys_user';
        $where = array('a.origin_id' => $ask['origin_id'], 'a.obj_type' => $ask['obj_type'], 'a.obj_id' => $ask['obj_id'],'a.branch_id'=>$branch_id);
        $list = M()->field('a.content,a.comment_time,a.user_id,u.head_pic,a.id,a.read,a.attach_1')->table($a_table.' a')->join($u_table." u on a.user_id=u.id",'left')->where($where)->order($order_condition)->select();
        $sayList['count'] = count($list);
        // var_dump($list);die;
        foreach ($list as $val) {
            //设置为已读
            if($val['user_id'] != $_SESSION['user_id'] && $val['read'] == 1){
                $datay['read'] = 0;
                $ask_model->where("id=".$val['id'])->save($datay);
                $is_read = 1;
            }
            $flag = 0;
            $ids = D('SysUser')->getBranchManager('id');
            if (!in_array($val['user_id'],$ids)) {
                $flag = 1;
            }
            $face	=	strpos($val['head_pic'], 'Public/') === false ? $val['head_pic'] : "/" . $val['head_pic'];
            //NEW Jan 5,2018 文件处理
            $attachs =   getAskUploadFileImages($val['attach_1'],'',true);
            if($is_system == 1 ){
                $content = base64_decode($val['content'], true);
                $default_jump_url = 'https://eshop'.getBrowseBranchId().'.caisuikx.com';
                $regular = '/(https|ftp|file|http):\/\/[a-zA-Z0-9][-a-zA-Z0-9]{0,62}(\.[a-zA-Z0-9][-a-zA-Z0-9]{0,62})+\.?/';
                $content = strpos($content,'w3shop') === false ? $content : preg_replace($regular,$default_jump_url,$content);
            }else{
                $content = base64_decode($val['content'], true);
            }
            $sayList['lists'][] = array(
                'face' => $face?$face:getDefalutHeadPic(),
                'content' =>$content,
                'begtime' => timeline($val['comment_time']),
                'flag' => $flag,
                'user_id' => $val['user_id'],
                'attach'	=> $val['attach_1'],
                'attach_type'=> $attachs['type'],
                'comment_time'=> $val['comment_time']
            );
        }
        //添加 - 刷新session中的unread_ask
//        if($is_read == 1){
//                $unread_ask = D('ComComment')->getDontReadAskCount();
//            session('unread_ask',$unread_ask);
//        }
        $this->assign('list',$sayList['lists']);
        $this->assign('count',$sayList['count']);
        $this->display();
    }
    public function TaLeaveAction() {
        $action = I('post.action', '', 'strip_tags');
        if($action == 'order'){
            $ask_data = M('ComOrder')->where('id ='.I('post.oid'))->find();
            $ask['obj_type']  = 'order';
            $ask['origin_id'] = $ask_data['user_id'];
            $ask['obj_id']	  = $ask_data['id'];
        }else if (strpos($action,'tool') !== false){
            $ask_data = M('ToolQuery')->where('id ='.I('post.tid'))->find();
            $ask['obj_type']  = 'tool';
            $ask['origin_id'] = $ask_data['user_id'];
            $ask['obj_id']	  = $ask_data['id'];
        }else{
            $ask_id = I('post.id', 0, 'intval');
            $ask = M('SysAsk')->where("id='$ask_id'")->find();
        }
        $data['obj_id'] = $ask['obj_id'];
        $data['origin_id'] = $ask['origin_id'];
        $data['obj_type'] = $ask['obj_type'];
        $data['user_id'] = $_SESSION['user_id'];
        $data['content'] = base64_encode(I('post.content'));
        $data['attach_1'] = I('post.attach_1');
        $data['branch_id'] = getBrowseBranchId();
        $data['comment_time'] = time();

        $result = M('SysAsk')->data($data)->add();

        /*$count = M("SysAsk")->where('obj_id = '.$data['obj_id'])->count();
        if($count == 1){
            $this->replyConsult($data['obj_id']);
        }*/
        //判断上条消息是否超过3分钟，超过则发送微信通知
        $condition['_string'] = ' comment_time < '.strtotime(date('Y-m-d H:i:s',time())).' and comment_time > '.strtotime(date('Y-m-d H:i:s',strtotime('-3 minute')));
        $condition['obj_id'] = $data['obj_id'];
        $condition['origin_id'] = $data['origin_id'];
        $condition['obj_type'] = $data['obj_type'];
        $count = M("SysAsk")->where($condition)->count();
        if($count<1){
            $this->replyConsult($data);
        }
        $user = M('SysUser')->where(array('id' => $_SESSION['user_id']))->find();
        $head_pic = $user['head_pic'];
        $face = strpos($head_pic, 'Public/') === false ? $head_pic : "/" . $head_pic;
        echo json_encode(array("error" => 0, 'msg' => '留言成功！', 'face' => $face, 'comment_time' => timeline($data['comment_time'])));
        exit();
    }

    public function replyConsult($data){
        $result = M("ToolQuery")->where("id = ".$data["obj_id"])->find();
        $tmp = json_decode($result['value'],true);
        $condition['consultant'] = $tmp['consultant']??"留言";
        $condition['id'] = $data["obj_id"];
        $condition['reply'] = base64_decode($data['content']);
        if($data["obj_type"] == "tool"){
            $condition['user_id'] = $result['user_id'];
            D('ComOrder')->sendWXConsult($condition,2);
        }else{
            $condition['user_id'] = $data['origin_id'];
            D('ComOrder')->sendWXConsult($condition,4);
        }
    }

    protected function handlerToolShow(&$tool){
        $tool_value = json_decode($tool['value'],true);
        $tool['msg_type'] = 'tool';
        switch ($tool['type']) {
            case 0 :
                $tool['view'] = [
                    ['key' => '城市','value' =>$tool_value['city']],
                    ['key' => '字号','value' =>$tool_value['firm']],
                    ['key' => '行业','value' =>$tool_value['trade']],
                    ['key' => '公司类型','value' =>$tool_value['form']]
                ];
                $tool['title'] = '免费核名';
                break;
            case 1:
                $tool['view'] = [
                    ['key' => '商标名称','value' =>$tool_value['name']],
                    ['key' => '商标类别','value' =>$tool_value['trade']],
                ];
                $tool['title'] = '免费商标注册';
                break;
            case 2:
                $tool['view'] = [
                    ['key' => '联系姓名','value' =>$tool['nickname']],
                    ['key' => '联系电话','value' =>$tool['mobile']],
                    ['key' => '需求','value' =>$tool_value['consultant']]
                ];
                $tool['title'] = '免费咨询';
                break;
        }
    }
}
