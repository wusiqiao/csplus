<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/19
 * Time: 10:19
 */

namespace EShop\Controller;
use Think\Controller;
use Think\Log;

class SpreadController extends BaseController
{
    protected $_MODEL = 'ComTweets';
    protected $request;
    protected $tweets_data;
    protected $tweets_additional_data;
    protected $filter_request = ['tweets','getTweetsLists','handlerShareInc','tweets_additional_update'];//可以获取数据的方法
    protected $filter_location = ['tweets','handlerShareInc'];//可以直接访问的方法

    public function _initialize(){
        parent::_initialize();
        if(!$this->handlerFilterLocationModule()){
            $this->error('您没有访问的权限','/');
            die;
        }
        if($this->handlerFilterRequestModule()){
            $this->handlerRequest();
        }
    }
    public function indexAction(){
        $this->_getShareDate('index');
        $this->title = '推广管理';
        $this->display();
    }
    public function tweets_listAction(){
        $this->title = '热点广告';
        $this->display();
    }
    public function getTweetsListsAction(){
        $this->ajaxReturn(D($this->_MODEL)->getTweetsLists($this->request));
    }
    public function handlerShareIncAction(){
        $this->handlerSetInc('share_count');
    }
    public function tweetsAction(){
        $this->handlerSetInc('browse_count');
        $this->_getTweetsData();
        $this->_getTweetsAdditionalData();
        $this->_getShareDate('tweets');
        $this->display();
    }
    public function tweets_additionalAction(){
        $this->setTweetsAdditionalData();//设置商户的数据 -- 如果没有创建则为false
        $this->additional = $this->tweets_additional_data ? $this->tweets_additional_data : [];
        $this->display();
    }
    public function tweets_additional_updateAction(){
        $this->handlerRequestImagers();//处理上传的图片
        $res = D($this->_MODEL)->handlerUpdateAdditional($this->request);
        $this->handlerResponse($res ? ['error'=>0,'message'=>'提交成功,已开始生效!!!'] : ['error'=>1,'message'=>'提交失败!!!']);
    }
    //提取出图片
    protected function handlerRequestImagers(){
        $files = array();
        foreach($this->request as $key=>$value){
            if(in_array($key,['top_pic','bottom_pic1','bottom_pic2']) && trim($value) != ''){
                $files[$key] = $value;
            }
        }
        $this->handlerImagersUpload($files);
    }
    //处理base64图片
    protected function handlerImagersUpload($files){
        if(count($files) > 0){
            foreach($files as $key => $val){
                $base64_image_content = $val;
                if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
                    $type = $result[2];
                    $new_file = "/Application/EShop/Upload/tweets_additional/" . time() . $key . ".{$type}";
                    if (file_put_contents('.'.$new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))) {
                        $this->request->$key = $new_file;
                        Log::write('测试');
                    }else{
                        return false;
                    }
                }
            }
        }
        return true;
    }
    protected function handlerSetInc($inc){
        D($this->_MODEL)->handlerSetInc($this->request->id,$inc);
    }
    /*
     * 输出软文详情
     */
    protected function _getTweetsData(){
        $this->setTweetsData(D($this->_MODEL)->getTweetsById($this->request->id));
        $this->tweets = $this->getTweetsData();
    }
    /*
     * 输出附加信息
     */
    protected function _getTweetsAdditionalData(){
        $this->setTweetsAdditionalData();
        $additional = $this->tweets_additional_data;
        if($additional['is_open'] == 1){
            $this->additional = $additional;
        }
    }
    protected function setTweetsAdditionalData(){
        $this->tweets_additional_data = D($this->_MODEL)->getBranchTweetsAdditional();
    }
    /*
     * 输出软文详情页 分享数据
     */
    protected function _getShareDate($type)
    {
        if($type == 'index'){
            $shopData = getComStoreData("all");
            $share_data['title']  = $shopData['name'];
            $share_data['desc']	  = $shopData['share_desc'];
            $share_data['link']   = WEB_ROOT;
            $share_data['imgUrl'] = getDefalutHeadPic();
            $this->is_login = session('user_id') ? 1 : 0;
        }else{
            $tweets_data = $this->getTweetsData();
            $share_data['title']  = $tweets_data['title'];
            $share_data['desc']	  = $tweets_data['describe'];
            $share_data['imgUrl'] = WEB_ROOT.$tweets_data['pic'];
            $share_data['link']   = WEB_ROOT.'/Spread/tweets/id/'.$this->request->id.'.html';
        }
        $signPackage = getWeChatInstance()->getJsSign();
        $this->share_data = $share_data;
        $this->signPackage = $signPackage;

    }
    protected function handlerRequest(){
        if(I('param.')){
            $this->request = (object) I('param.');
        }else{
            $this->error('找不到数据','/');
        }
    }
    protected function handlerFilterLocationModule(){
        if(!in_array(ACTION_NAME,$this->filter_location)){
            if(session('user_type') != USER_TYPE_COMPANY_MANAGER || empty(session('user_type'))){
                return false;
            }
        }
        return true;
    }
    protected function handlerFilterRequestModule(){
        return in_array(ACTION_NAME,$this->filter_request) ? true : false;
    }
    protected function handlerResponse($data,$type = 'json'){
        $this->ajaxReturn($data,$type);
    }
    //设置软文信息
    protected function setTweetsData($data){
        $this->tweets_data = $data;
    }
    /*
     * 输出软文信息
     */
    protected function getTweetsData(){
        return $this->tweets_data;
    }
    protected function handlerPermissionsProcessing()
    {
        parent::handlerPermissionsProcessing();
        $this->_permission_name = 'ComTweetsAdditional';
        switch (ACTION_NAME){
            case 'tweets_additional_update':
                $this->_permission_action_name = 'update';
                break;
            case 'tweets_additional':
                $this->_permission_action_name = 'detail';
                break;
        }
    }
}