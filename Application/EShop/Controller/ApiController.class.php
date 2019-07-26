<?php

namespace Eshop\Controller;

use Think\Controller;

class ApiController extends Controller {

    public function firewallAction(){
        $this->display('404/404-firewall');
    }
    public function wechatAction() {
        $wx_config = M("WxConfig")->where(array("sid" => I("get.sid")))->find();
        if ($wx_config) {
            Vendor('Wechat.TPWechat', '', '.class.php');
            $options = array(
                'token' => $wx_config['token'], 
                'appid' => $wx_config['appid'], 
                'appsecret' => $wx_config['appsecret'], 
                'encodingaeskey' => $wx_config['encoding_aeskey']);
            $weObj = new \TpWechat($options);
            $weObj->valid(); //明文或兼容模式可以在接口验证通过后注释此句，但加密模式一定不能注释，否则会验证失败
            $type = $weObj->getRev()->getRevType();
            switch ($type) {
                case \Wechat::MSGTYPE_TEXT:
                    break;
                case \Wechat::MSGTYPE_EVENT:
                    $events = $weObj->getRevEvent();
                    switch ($events['event']) {
                        case \Wechat::EVENT_SUBSCRIBE:
                            break;
                        case \Wechat::EVENT_SCAN: //扫码，已经关注过的 
                            break;
                        case \Wechat::EVENT_UNSUBSCRIBE:
                            break;
                        default:
                            break;
                    }
                    break;
                case \Wechat::MSGTYPE_IMAGE:
                    break;
                default:
                    $weObj->text("欢迎来到本店！")->reply();
            }
        }
    }
    private function HandlerOldProductAction(){
        $where['updated_at'] = array('lt',time());
        $oldProduct = M('ComProduct')->where($where)->select();
        //生成options
        foreach($oldProduct as $key => $val){
            $this->HandlerOptions($val);
        }
//        echo '<pre>';
//        var_dump($oldProduct);

    }
    private function HandlerOptions($data){
        $prosCount          =        M('ComProductOptions')->where('product_id = '.$data['id'])->count();
        if($prosCount > 0){
            return false;
        }
        $branch_id          = $data['branch_id'];
        $add_options        = array();
        if(in_array($data['unit'],['次','天','月','季','年'])){
            $checked_option = '每'.$data['unit'];
        }else{
            $checked_option = '每次';
        }
        $product_attributes = [
            [
                ['discount',$data['real_cash']],
                ['origina',$data['original_cash']],
                ['服务周期',$checked_option]
                ]
        ];
        $product_options    = [
            ['title'=>'服务周期',
                'children'=>$checked_option
            ]
        ];
        foreach ($product_options as $key=>$val){
            $temp['created_at'] = time();
            $temp['updated_at'] = time();
            $temp['name']      = $val['title'];
            $temp['parent_id'] = 0;
            $temp['branch_id'] = $branch_id;
            $temp['product_id']= $data['id'];
            $cpo_id = M('ComProductOptions')->add($temp);
            $childrens         = explode('&|',$val['children']);
            foreach ($childrens as $k =>$v){
                $temp['name']  = $v;
                $temp['parent_id']  = $cpo_id;
                $add_options[] = $temp;
            }
        }
        M('ComProductOptions')->addAll($add_options);
        $now_list = M('ComProductOptions')->field('name,parent_id,id')->where('branch_id = '.$branch_id.' and product_id = '.$data['id'])->select();
        $nows_id    = array();
        $nows_name  = array();
        foreach ($now_list as $key=>$val){
            $nows_id[$val['id']] = $val;
        }
        foreach ($now_list as $key=>$val){
            if($val['parent_id'] > 0){
                $nows_name[$nows_id[$val['parent_id']]['name']][$val['name']] =$val;
            }
        }
        $add_attributes['is_open'] = 1;
        $add_attributes['product_id'] = $data['id'];
        $add_attributes['branch_id'] = $branch_id;
        $add_attributes['created_at'] = time();
        $add_attributes['updated_at'] = time();
        $add_attributes_all = array();
        foreach($product_attributes as $key=>$val){
            $temp = array();
            foreach ($val as $k=>$v){
                if($v[0] == 'discount'){
                    $add_attributes['real_cash'] = (float) $v[1];
                }elseif($v[0] == 'origina'){
                    $add_attributes['original_cash'] = (float) $v[1];
                }else{
                    $temp[] = $nows_name[$v[0]][$v[1]]['id'];
                }
            }
            sort($temp);
            $add_attributes['value'] = implode(',',$temp);
            $add_attributes_all[] = $add_attributes;
        }
        M('ComOrderAttribute')->addAll($add_attributes_all);
        $attr = M('ComOrderAttribute')->where('product_id = '.$data['id'])->limit(1)->find();
        $orders = M('ComOrder')->where('product_id = '.$data['id'])->getField('id',true);
        if($orders){
            $condition['id'] = array('in',$orders);
            $condition['attribute'] = $attr['id'];
            M('ComOrder')->save($condition);
        }
    }
    public function testTemplateIdAction(){
        $wechat = getWeChatInstance();
        $result["errcode"] = 0;
        if ($wechat->isRemoteHost()) {
            $tpl_id = 'TM00001';
            if (!$template_id =  $wechat->addTemplateMessage($tpl_id)) {
                $result["errcode"] = $wechat->errCode;
                $result["errmsg"] = $wechat->errMsg;
                \Think\Log::write("send_wx_message error!= message:" . $wechat->errMsg.'|code:'.$wechat->errCode);
            }else{
                echo $template_id;
            }
        }
    }
    //public function
    public function testFollowerAction()
    {
        $save['is_follow'] = 1;
        $save['followed_at'] = time();
        $where['mobile'] = '15559080811';
        $where['branch_id'] = 26;
        $result = M('SysUser')->where($where)->save($save);
        var_dump($result);
    }
    //更新用户关注状态
    public function loadingUserFollowersAction()
    {
        //
        //获取全部商户信息
        $condition_branch['parent_id'] = 1;
        $condition_branch['type'] = ORG_BRANCH;
//        $condition_branch['id'] = 34;
        $branchs = M('SysBranch')->field('id')->where($condition_branch)->select();
        foreach($branchs as $key => $val) {
            $condition_user['branch_id'] = $val['id'];
            $condition_user['is_valid'] = 1;
            $branchs[$key]['options'] = $this->getWxOptions($val['id']);
            $branchs[$key]['users'] = M('SysUser')->field('id,openid')->where($condition_user)->select();
        }
        $openids = [];
        $followers = [];
        $follower_ids = [];
        foreach($branchs as $key =>$val) {
            $wechat = $this->getWeChatInstance($val['options']);
            $isToken = $wechat->checkAuth();
            if ($isToken) {
                $result = $wechat->getUserList();
                if ($result) {
                    $openids['branch_'.$val['id']] = $result['data']['openid'];
                }
            }

            if (!empty($openids['branch_'.$val['id']])) {
                foreach($val['users'] as $k => $v){
                    if (in_array($v['openid'],$openids['branch_'.$val['id']]) !== false) {
                        $subscribe = $wechat->getUserInfo($v['openid']);
                        $follower_ids[$val['id']][] = $v['id'];
                        $followers[$val['id']][$v['id']] = ['followed_at' => $subscribe['subscribe_time']];
                    }
                }
            }
        }
        $save_follower = [];
        $save_unfollower = [];
        $temp = [];
        foreach($follower_ids as $key => $val){
            $save_unfollower['branch_id'] = $key;
            $save_unfollower['id'] = array('not in',$val);
            $save_unfollower['is_follow'] = 0;
            $save_unfollower['updated_at'] = time();
            M('SysUser')->save($save_unfollower);
        }
        foreach($followers as $key => $val) {
            if ($val) {
                foreach($val as $k => $v) {
                    $save_follower['branch_id'] = $key;
                    $save_follower['id'] = $k;
                    $save_follower['followed_at'] = $v['followed_at'];
                    $save_follower['is_follow'] = 1;
                    $save_follower['updated_at'] = time();
                    M('SysUser')->save($save_follower);
                }
            }
        }
        echo 'chenggong';
    }


    //同步微信用户到平台，如果存在就不同步
    public function syncUserFromWxPlatformAction($branch_id = 0)
    {
        if (empty($branch_id)){
            die("error branchid");
        }
        $wxConfig = $this->getWxOptions($branch_id);
        $wechatInstance = $this->getWeChatInstance($wxConfig);
        $wx_users =  $wechatInstance->getUserList();
        foreach ($wx_users["data"]["openid"] as $openid){
            $c["openid"] = $openid;
            $sys_user = M("SysUser")->field("id")->where($c)->select();
            if (empty($sys_user)){
                $sys_user["branch_id"] = $branch_id;
                $sys_user["user_type"] = USER_TYPE_PROSPECTIVE;
                $sys_user["role_ids"] = ROLE_ID_VISITOR;
                $sys_user["followed_at"] = time();
                $sys_user["is_follow"] = 1;
                $sys_user["is_valid"] = 1;
                $sys_user["openid"] = $openid;
                $wx_user = $wechatInstance->getUserInfo($openid);
                $sys_user["name"] = removeEmoji($wx_user["nickname"]);
                $sys_user["account"] = "";
                $sys_user["head_pic"] = $wx_user["headimgurl"];
                $sys_user["reg_time"] = time();
                $sys_users[] = $sys_user;
                M("SysUser")->add($sys_user);
            }
        }
        //var_export($sys_users);
    }

    protected function getWxOptions($branch_id)
    {
        return M("WxConfig")->field('token,appid,appsecret,encoding_aeskey,xcx_appid,xcx_appsecret')->where('branch_id = '.$branch_id)->find();
    }
    protected function getWeChatInstance($wx_config = null) {
        Vendor('Wechat.TPWechat', '', '.class.php');
        if (empty($wx_config)){
            $wx_config = getWxOptions();
        }
        $options = array(
            'token' => $wx_config['token'], //填写你设定的key
            'appid' => $wx_config['appid'],
            'appsecret' => $wx_config['appsecret'],
            'encodingaeskey' => $wx_config['encoding_aeskey'] //填写加密用的EncodingAESKey，如接口为明文模式可忽略
        );
        $_tpWeChat = new \TpWechat($options);
        return $_tpWeChat;
    }
    public function uploadAction(){
        echo "1";
    }
    public function handlerBranchDefaultAction()
    {
        echo "已关闭";
        die;
        $condition['parent_id'] = 1;
        $branch_list = D('SysBranch')->where($condition)->select();
        foreach($branch_list as $key=>$val) {
            $append_branch['name'] = '未分组客户';
            $append_branch['branch_id'] = $val['id'];
            $append_branch['parent_id'] = $val['id'];
            $append_branch['type'] = ORG_COMPANY;
            $append_branch['is_valid'] = 1;
            $append_branch['update_time'] = time();
            $append_branch["querykey"] = firstPinyin('未分组客户');
            $default_customer = D('SysBranch')->add($append_branch);
            $append_group['branch_id'] = $val['id'];
            $append_group['value'] = '未分组';
            $append_group['count'] = 0;
            $append_group['created_at'] = time();
            $default_group = M('SysTargetGroup')->add($append_branch);
            $save['default_group'] = $default_group;
            $save['default_customer'] = $default_customer;
            $condition['id'] = $val['id'];
            D('SysBranch')->where($condition)->save($save);
        }
    }
    public function handlerUserDefaultAction()
    {
        echo "已关闭";
        die;
        $condition['parent_id'] = 1;
        $branch_list = D('SysBranch')->where($condition)->select();
        foreach($branch_list as $key=>$val) {
            $condition['branch_id'] = $val['id'];
            $condition['group_id'] = array(array('eq', 0), array('exp', 'is null'), 'or');
            $user_list = D('SysUser')->field('group_id')->where($condition)->getField('id', true);
            //group_id
            $save_user['group_id'] = $val['default_group'];
            $where_user['id'] = array('in',$user_list);
            D('SysUser')->where($where_user)->save($save_user);
            //sys_user_branch
            $append_user_branch_all = [];
            foreach($user_list as $k => $v) {
                $append_user_branch['user_id'] = $v;
                $append_user_branch['branch_id'] = $val['default_customer'];
                $append_user_branch['type'] = 1;
                $append_user_branch_all[] = $append_user_branch;
            }
            D('SysBranch')->addAll($append_user_branch_all,null,true);
        }
    }
    public function deleteQiNiuFileAction(){
        //获取全部文件
        $paths = M('sys_document')->where('type = 1')->field('path,id')->select();
        $upload = new \ESAdmin\Controller\UploadController();
        $ids = [];
        foreach ($paths as $key => $value) {
            $result = $upload->QiNiuDelete($value['path']);
            $ids[] = $value['id'];
        }
//        M('sys_document')->where('type = 1')->delete();
    }
    public function handlerOrderUpdateInformationAction()
    {
        //获取所有的订单
        $field = 'a.id,b.product_title,b.category_id,b.category_pid,c.value';
//        $field = 'count(*),b.id';
        $orders =   M('ComOrder')
                            ->alias('a')
                            ->field($field)
                            ->join('com_product as b on b.id = a.product_id')
                            ->join('com_order_attribute as c on c.id = a.attribute','left')
//                            ->group('b.id')
                            ->select();
        foreach($orders as $key => $value) {
            $orders[$key]['product_category'] = category($value['category_pid']).'-'.category($value['category_id']);
            if ($value['value']) {
                $options['a.id'] = array('in',$value['value']);
                $result = M('ComProductOptions')
                    ->alias('a')
                    ->where($options)
                    ->join('com_product_options as b on b.id = a.parent_id')
                    ->field('b.name as parent_name,a.name')
                    ->distinct(true)
                    ->select();
            }
            $orders[$key]['product_attribute'] = $result ? json_encode($result) :'';
            unset($orders[$key]['category_id']);
            unset($orders[$key]['category_pid']);
            unset($orders[$key]['value']);
            M('ComOrder')->save($orders[$key]);
        }
//        var_dump($orders);
    }
    public function setFileCacheAction()
    {
        S('1sadfasdafdd',array(
                'type'=>'memcachedd',
                'host'=>'192.168.1.10',
                'port'=>'11211',
                'prefix'=>'think',
                'expire'=>60)
        ,array('type'=>'file','expire'=>2000));
    }
    public function getFileCacheAction()
    {
        var_dump(S('FILE_WxCodePay_CIZ_20181229093606403'));
    }

    public function branchListAction(){
        $list = D("ComCompany")->setDacFilter()->field("DISTINCT a.id,a.name")->select();
        print_r($list);
    }
}
