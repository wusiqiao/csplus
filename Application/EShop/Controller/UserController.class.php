<?php

namespace EShop\Controller;

use function GuzzleHttp\Psr7\str;
use Think\Controller;

class UserController extends UserLoginController {
    protected $_MODEL = "SysUser";
    public function myVoucherAction(){
        if(IS_POST){
            //判断是否存在手机号码,如果不存在的话,跳转设置手机号码地址
            $start	=	I("get.pageStart");
            $num	=	I("get.n");
            $type	=	I("post.type");
            $TicketModal	=	D("SpTicket");
            $data			=	$TicketModal->getUserTicketLists($type,$start,$num);
            echo json_encode($data);
        }else{
//            if(!GetUserIsSetMobile()){
//                redirect(GetSetMobileLocation());
//            }
            $this->is_set_mobile = GetUserIsSetMobile() ? 1 : 0;
            $location = I('get.location',0);
            $this->assign('location',$location);
            $this->assign('title','我的优惠券');
            $this->display();
        }
    }
    //领券中心
    public function voucherCenterAction(){
        if(IS_POST){
            $Micorstore     =   D('SpTicket');
            $field			=	't.reduce_cost,t.least_cost,FROM_UNIXTIME(a.ticket_end_date,\'%Y.%m.%d\') as ticket_end_date,a.is_scope,a.scope,a.activity_begin_date,a.activity_end_date,a.id as activity_id';
            $ticket_list	=	$Micorstore->getServiceMayReceiveTicket($field);
            foreach ($ticket_list as $key => $value) {
                $is_surplus									=	$Micorstore->getIsTicketStockSurplus($value['activity_id']);
                if(!$is_surplus){
                    unset($ticket_list[$key]);
                }
                $ticket_list[$key]['activity_begin_date']	=	date('Y-m-d',$value['activity_begin_date']);
                $ticket_list[$key]['activity_end_date']		=	date('Y-m-d',$value['activity_end_date']);
                $ticket_list[$key]['is_receive']			=	$Micorstore->getUserMayReceiveTicket($value['activity_id']);
                $ticket_list[$key]['ticket_date']	=	$value['ticket_begin_date'].'-'.$value['ticket_end_date'];
                $is_receive = D('SpTicket')->getUserMayReceiveTicket($value['activity_id']);
                if($is_receive == 1){
                    $ticket_list[$key]['show_pilot'] = 'style=\'background-color: #bfbfbf;\'';
                    $ticket_list[$key]['show_button'] = '已领取';
                }elseif($is_receive == 2){
                    $ticket_list[$key]['show_pilot'] =  'data-id = '.$value['activity_id'] .' onclick=\'redpacket(this);\'';
                    $ticket_list[$key]['show_button'] = '点击领取';
                }elseif($is_receive == 3){
                    $ticket_list[$key]['show_pilot'] = 'style=\'background-color: #bfbfbf;\'';
                    $ticket_list[$key]['show_button'] = '不可领取';
                }elseif($is_receive == 4){
                    $ticket_list[$key]['show_pilot'] = 'style=\'background-color: #bfbfbf;\'';
                    $ticket_list[$key]['show_button'] = '暂无库存';
                }elseif($is_receive == 5){
                    $ticket_list[$key]['show_pilot'] = 'onclick=\'location.href=\"'.GetSetMobileLocation().'\"';
                    $ticket_list[$key]['show_button'] = '设置手机';
                }
                if ($value['is_scope'] == 1){
                    $ticket_list[$key]['show_type'] ='blue';
                    $condition['id'] = array('in',$value['scope']);
                    $product_ids = M('ComProduct')->where($condition)->getField('product_title',true);
                    $ticket_list[$key]['show_scope'] = '仅限'.implode(',',$product_ids).'使用';
                }else{
                    $ticket_list[$key]['show_type'] ='red';
                    $ticket_list[$key]['show_scope'] = '店铺通用';
                }
            }
            $this->ajaxReturn(['count'=>count($ticket_list),'list'=>$ticket_list]);
        }
    }
    public function indexAction(){
        if(IS_GET){
            $user = M('SysUser')->where('id = '.$_SESSION['user_id'].' and branch_id = '.getBrowseBranchId())->find();
            $mobile_type = !empty($user['mobile']) && GetUserIsSetMobile($user['mobile']) ? 'edit_mobile' : 'set_mobile';
            $this->title = '账户管理';
            $this->assign('mobile_type',$mobile_type);
            $this->assign('user',$user);
            $this->display();
        }else{

        }
    }
    //用户列表
    public function user_listAction(){
        $this->title = '客户管理';
        $this->display();
    }
    public function getBelongsToBranchAction(){
        $result = D('SysUser')->getBelongsToBranch(I('param.'));
        $this->ajaxReturn($result);
    }
    public function jumpAction(){
        $this->display('Voucher/index');
    }
    public function userEditAction(){
        $title = "账户管理";
        $this->assign('title', $title);
        $postdata = I('post.');
        if (empty($postdata)) {
            $users_table = M('SysUser');
            $branch_id = getBrowseBranchId();
            $user = $users_table->where(array('id' => $_SESSION['user_id'],'branch_id'=>$branch_id))->find();
            $this->assign('user', $user);
            $this->display();
        } else {
            $users_table = M('SysUser');
            $data = $postdata;
            $branch_id = getBrowseBranchId();
            $result = $users_table->data($data)->where(array('id' => $_SESSION['user_id'],'branch_id'=>$branch_id))->save();
            echo json_encode(array("error" => "0", "msg" => "账户信息保存成功！"));
            exit();
        }
    }
    public function mobileVerifyAction(){
        $title = "更换手机号码";
        $this->assign('title', $title);
        $action = I('post.action', '', 'strip_tags');
        if ($action == "") {
            $user = M('SysUser')->where('id = '.$_SESSION['user_id'].' and branch_id = '.getBrowseBranchId())->find();
            $this->assign('user',$user);
            $this->display();
        } else {
            $code = I('post.code', '', 'strip_tags');
            if ($code == "") {
                echo json_encode(array("error" => "1", "msg" => "验证码不能为空"));
                exit();
            }
            if ($code <> $_SESSION['regcode']) {
                echo json_encode(array("error" => "1", "msg" => "验证码输入不正确"));
                exit();
            }
            //手验证通过
            session('mobile_verify', true);
            echo json_encode(array("error" => 0));
            exit();
        }
    }
    public function passwordAction(){
        if(IS_POST){
            $xpassword=md5_plus(I('post.password','','strip_tags'));
            if(I("post.re_password") && I("post.re_password") != I("post.password")){
                echo json_encode(array("error"=>1,"msg"=>"两次密码输入不一致！"));
                exit();
            }
            $code =I('post.code');
             if($code != $_SESSION['passworkcode']){
                 echo json_encode(array("error"=>1,'msg'=>'验证码错误!!'));
                 exit();
             }
            $members=D("SysUser");
            $susu=$members->where("id='".$_SESSION['user_id']."'")->find();
//            if (!check_md5_plus($jpassword,$susu['password'])){
//                echo json_encode(array("msg"=>"对不起，您的旧密码不对呀","error"=>1));
//                exit();
//            }
            $members->password=$xpassword;
            $members->where("id='".$_SESSION['user_id']."'")->save();
            echo json_encode(array("error"=>0));
            exit();
        }
    }
    public function mobileChangeAction() {
//        $type = I('get.type');
//        $action = I('post.action', '', 'strip_tags');
//        if ($action == "") {
//            if (empty($_SESSION['mobile_verify']) && $type == 1) {
//                $this->error("当前手机号未认证,不能进行修改!", U('Index/user'));
//                exit;
//            }
//            if (!empty($_SESSION['mobile_verify']) || ( $type == 0 && GetUserIsSetMobile() ) ) {
//                $this->error("该账户已设置手机号码,无需重新设置!", U('Index/user'));
//                exit;
//            }
//            if ($type == 1 && !GetUserIsSetMobile()) {
//                $this->error("该账户未设置手机号码,需初始化设置手机号码!", U('User/mobileChange',array('type'=>0)));
//                exit;
//            }
//            $user = M('SysUser')->where('id = '.$_SESSION['user_id'].' and branch_id = '.getBrowseBranchId())->find();
//            $title = $type == 1 ? '修改手机号码' : '设置手机号码' ;
//            $this->assign('user',$user);
//            $this->assign('type',$type);
//            $this->assign('title',$title);
//            unset($_SESSION['regcode']);
//            $this->display();
//        }
        if (IS_POST){
//            if (empty($_SESSION['mobile_verify']) && $type == 1) {
//                echo json_encode(array("error" => 1,"msg"=>'当前手机号未认证,不能进行修改!'));
//                exit();
//            }
//            if (!empty($_SESSION['mobile_verify']) || ( $type == 0 && GetUserIsSetMobile() ) ) {
//                echo json_encode(array("error" => 1,"msg"=>'该账户已设置手机号码,无需重新设置!'));
//                exit();
//            }
//            if ($type == 1 && !GetUserIsSetMobile()) {
//                echo json_encode(array("error" => 1,"msg"=>'该账户未设置手机号码,需初始化设置手机号码!'));
//                exit();
//            }
            $code = I('post.code', '', 'strip_tags');
            if (trim($code) == "") {
                echo json_encode(array("error" => "1", "msg" => "验证码不能为空"));
                exit();
            }

            if (trim($code) <> $_SESSION['regcode']) {
                echo json_encode(array("error" => "1", "msg" => "验证码输入不正确"));
                exit();
            }
            $condition["mobile"]   = I('post.mobile');
            $condition["branch_id"] = session('branch_id');
            $group = M("SysUser")->where($condition)->find();
            if ($group) {
                die(json_encode(array("error" => "1", "msg" => "该手机号已存在！")));
            }
            //账户管理页面修改手机号验证密码是否正确
            if(I("post.password")){
                $user_password = M("SysUser")->where("id =".session("user_id"))->getField("password");
                if(!check_md5_plus(I("post.password"),$user_password)){
                    die(json_encode(array("error" => "1", "msg" => "密码不正确！")));
                }
            }
            $members = D("SysUser");
            $members->mobile = $condition["mobile"];
            $members->binded_at = time();
            session('mobile',$condition["mobile"]);
            $members->where("id='".$_SESSION['user_id']."'")->save();
            echo json_encode(array("error" => 0,"msg"=>'手机设置成功'));
            exit();
        }
    }
    public function scopeTicketReceiveAction(){
        if(IS_POST){
            $activity_id = I("post.activity_id");
            $Microstore	= D("SpTicket");
            $branch_id	= getBrowseBranchId();
            //判断登录的用户是不是该商店的经营者
            $is_admin	=	handleIsManager();
            if($is_admin){
                echo json_encode(array('error'=>1,'msg'=>'领取失败,不能领取自己商城的优惠券','data'=>array('error_msg'=>'不可领取')));
                die;
            }
            //判断领取条件
            $is_receive	=	$Microstore->getUserMayReceiveTicket($activity_id);
            if($is_receive == 1){
                echo json_encode(array('error'=>1,'msg'=>'领取失败,您已经领取过该代金券','data'=>array('error_msg'=>'已领取')));
                die;
            }elseif ($is_receive == 4){
                echo json_encode(array('error'=>1,'msg'=>'领取失败,该代金券已经领取完了','data'=>array('error_msg'=>'无库存')));
                die;
            }elseif($is_receive == 5){
                //判断用户类型是否符合领取条件
                echo json_encode(array('error'=>2,'msg'=>'领取失败,未设置手机号码','url'=>GetSetMobileLocation(),'data'=>array('error_msg'=>'设置手机')));
                die;
            }
            //领取代金券的操作 返回领取数量 满 减金额
            $data		=	$Microstore->userReceiveTicket($activity_id);
            if($data){
                echo json_encode(array('error'=>0));
                die;
            }
        }
    }
    public function user_headerAction(){
        $title = "头像编辑";
        $this->assign('title', $title);
        $postdata = I('post.');
        if (empty($postdata)) {
            $users_table = M('SysUser');
            $user = $users_table->where(array('id' => $_SESSION['user_id']))->find();
            $user['head_pic'] = empty($user['head_pic']) ? getDefalutHeadPic() : $user['head_pic'];
            $file = $user['head_pic'];
            $this->assign('file', $file);
            $this->display();
        } else {
            $base64_image_content = $postdata['base64'];
            if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
                $type = $result[2];
                $new_file = "uploads/header/" . $_SESSION['user_id'] . ".{$type}";
                if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))) {
                    $users_table = M('SysUser');
                    $data['head_pic'] = '/' . $new_file;
                    $branch_id   = getBrowseBranchId();
                    $result = $users_table->data($data)->where(array('id' => $_SESSION['user_id'],'branch_id'=>$branch_id))->save();
                    echo json_encode(array("error" => "0", "msg" => "头像保存成功!"));
                    exit();
                }
                echo json_encode(array("error" => "0", "msg" => "头像保存失败，请重试！"));
                exit();
            }
        }
    }

    public function downloadAction(){
        /*$img_url = $this->setWXQrcode();
        //$img_url = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=gQFL8DwAAAAAAAAAAS5odHRwOi8vd2VpeGluLnFxLmNvbS9xLzAyeTlCWndrRmJmdmkxUVBPcU5yMWUAAgSzN9FbAwSAOgkA";
        $img = file_get_contents($img_url,true);
        header("Content-Type: image/jpeg;text/html; charset=utf-8");
        exit($img);*/
//        $filename = realpath("./"). ltrim(MODULE_UPLOAD_PATH,".")."1.jpg";
//        die($filename);
        $mimeType = 'application/octet-stream';
        header("Content-Type: $mimeType");
        $filename = realpath("./"). ltrim(MODULE_UPLOAD_PATH,".")."1.jpg";
        //$serverPath = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=gQFL8DwAAAAAAAAAAS5odHRwOi8vd2VpeGluLnFxLmNvbS9xLzAyeTlCWndrRmJmdmkxUVBPcU5yMWUAAgSzN9FbAwSAOgkA";
       // $serverPath = file_get_contents($serverPath,true);;
        $filesize = filesize($filename);
//        $filesize = 1024;
        //$charset = FLEA::getAppInf('responseCharset');//根据实际文件编码类型，如utf-8，gbk
        header("Content-Disposition: attachment; filename=1.jpg; charset=utf-8");
        header('Pragma: cache');
        header('Cache-Control: public, must-revalidate, max-age=0');
        header("Content-Length: $filesize");
        readfile($filename);
        exit;
    }

    public function testDownloadAction(){
        $url = ltrim(MODULE_UPLOAD_PATH, ".") . 'qrcode/' . '1.jpg';
        $file_url = $url;
        $out_filename = '1.jpg';
        $filePath = realpath("./") . $file_url;
        if (!file_exists($filePath)) {
            return array('error' => 1, 'message' => '文件不存在!!');
        } else {
            header('Accept-Ranges: bytes');
            header('Accept-Length: ' . filesize($filePath));
            header('Content-Transfer-Encoding: binary');
            header('Content-type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . $out_filename);
            header('Content-Type: application/octet-stream; name=' . $out_filename);
            if (is_file($filePath) && is_readable($filePath)) {
                $file = fopen($filePath, "r");
                echo fread($file, filesize($filePath));
                fclose($file);
            }
        }
    }

    public function businessCardAction(){
        /*$openid = session('openid');
        $share_data['link']  = WEB_ROOT . "/User/businessCardShare/inviter/$openid";
        $this->assign('share_data', $share_data);*/
        $this->assign('title','我的二维码');
        $WxQrcode = $this->setWXQrcode();
        $this->assign('WxQrcode',$WxQrcode['WxQrcode']);
		$this->assign('resources',$WxQrcode['resources']);
        $signPackage = getWeChatInstance()->getJsSign();
        $this->assign('signPackage', $signPackage);

        $this->display();
    }

    private function setWXQrcode(){
        $id = $_SESSION['user_id'];
        $wechat = getWeChatInstance();
        $qrcode_data = $wechat->getQRCode("inviter_$id","2");
        $img_url = $wechat->getQRUrl($qrcode_data['ticket']);

        $ticket = $qrcode_data['ticket'];
        $url = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=".urlencode($ticket);
        $imageInfo = $this->downloadImgFromWX($url);
        //$filename = "qrcode.jpg";
        $filePath = ltrim(MODULE_UPLOAD_PATH, ".") . 'qrcode/' . 'qrcode'.$_SESSION['user_id'].'.jpg';
        $data['resources'] = str_replace('shop','shop'.getBrowseBranchId(),SHOP_ROOT).$filePath;
        $filePath = realpath("./") . $filePath;
        //$localfile = fopen("".$filename,'w');
        $localfile = fopen($filePath,'w');
        if(false !== $localfile){
            if(false !== fwrite($localfile,$imageInfo['body'])){
                fclose($localfile);
            }
        }
        $this->WxQrcode = $img_url;
		$data['WxQrcode'] = $this->WxQrcode;
        return $data;
    }

    public function downloadImgFromWX($url){
        $ch = curl_init($url);
        curl_setopt($ch,CURLOPT_HEADER,0);
        curl_setopt($ch,CURLOPT_NOBODY,0);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        $package = curl_exec($ch);
        $httpinfo = curl_getinfo($ch);
        curl_close($ch);
        return array_merge(array('body'=>$package),array('header'=>$httpinfo));
    }

    //修改手机号时获取手机号是否可用
    public function getMobileUsableAction(){
        $condition['mobile'] = I("post.mobile");
        $condition['branch_id'] = $_SESSION['branch_id'];
        $result = M("SysUser")->where($condition)->find();
        if(!$result){
            $this->ajaxReturn(array("error"=>0,"message"=>"该手机号可用"));
        }else{
            $this->ajaxReturn(array("error"=>1,"message"=>"该手机号已绑定其他账号"));
        }
    }

}
