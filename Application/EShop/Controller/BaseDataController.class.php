<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/12
 * Time: 10:46
 */

namespace EShop\Controller;


use Think\Action;
/*
 * 限制各个Action的访问权限
 */

class BaseDataController extends BaseController
{
    protected $request;//请求
    protected $open = false;//是否启动
    protected $filter_request = [];//可以获取数据的方法
    protected $request_default = [];//请求默认数值
    protected $filter_location = [];//允许通过自由通过的ACTION_NAME
    protected $dont_location_data = [];//禁止通行附带提醒信息
    protected $filter_location_post = [];//限制只能post才能通行
    protected $handler_storage = [];//处理函数数据存放
    protected $storage;//数据存放库
    public function _initialize(){
        parent::_initialize();
        if ($this->open === true){
            if(!$this->handlerFilterLocationModule()){
                $this->handlerDontLocation();
            }
            if($this->handlerFilterRequestModule()){
                $this->handlerFilterLocationPostModule();
                $this->handlerRequest();
            }else{
                $this->handlerDontRequest();
            }
            if(IS_GET) {
                $this->handlerGetDefaultData();
            }
        }
    }
    /****************************************基础函数**************Lynn*************************************/
    protected function handlerFilterRequestModule(){
        $this->handlerActionNameLower('filter_request');
        $action_name = strtolower(ACTION_NAME);
        return in_array($action_name,$this->filter_request) ? true : false;
    }
    //返回是否禁止通行
    protected function handlerFilterLocationModule(){
        $this->handlerActionNameLower('filter_location');
        $action_name = strtolower(ACTION_NAME);
        if(!in_array($action_name,$this->filter_location)){
            if($this->validateFilterLocationModule()){
                return false;
            }
        }
        return true;
    }
    //返回是否限制只能post通行
    protected function handlerFilterLocationPostModule(){
        $this->handlerActionNameLower('filter_location_post');
        $action_name = strtolower(ACTION_NAME);
        if(in_array($action_name,$this->filter_location_post)){
            if(!IS_POST){
                $this->error('该操作被禁止!!');
            }
        }
        return true;
    }
    //拒接访问的条件
    protected function validateFilterLocationModule(){
        return (session('user_type') != USER_TYPE_COMPANY_MANAGER || empty(session('user_type'))) ? true : false;
    }
    //处理 Action_name 转化成小写
    protected function handlerActionNameLower($functon_name,$type = 'value'){
        foreach ($this->{$functon_name} as $key=>$value){
            if($type == 'value'){
                $this->{$functon_name}[$key] = strtolower($value);
            }else{
                $this->{$functon_name}[strtolower($key)] = $value;
            }
        }
    }
    //处理禁止通行
    protected function handlerDontLocation($message = '您没有访问的权限',$url = '/Login'){
        $this->handlerActionNameLower('dont_location_data','key');
        $action_name = strtolower(ACTION_NAME);
        if(isset($this->dont_location_data[$action_name])){
            $message = $this->dont_location_data[$action_name]['message'] ?? $message;
            $url = $this->dont_location_data[$action_name]['url'] ?? $url;
        }
        if(IS_GET){
            $this->error($message,$url);
        }else{
            $this->handlerResponse(['error'=>1,'message'=>$message]);
        }
        die;
    }
    //处理禁止获取数据处理
    protected function handlerDontRequest(){
        if(I('param.')){
            if(IS_GET){
                $this->error('不能传输数据!!','/');
            }else{
                $this->handlerResponse(['error'=>1,'message'=>'该页面没有传输数据的权利!!']);
            }
            die;
        }
    }
    //默认数据
    protected function handlerRequestDefault(){
        $this->handlerActionNameLower('request_default','key');
        $action_name = strtolower(ACTION_NAME);
        if(isset($this->request_default[$action_name])){
            $this->request->{$this->request_default[$action_name][0]} = $this->request->{$this->request_default[$action_name][0]} ?? $this->request_default[$action_name][1];
            return true;
        }else{
            return false;
        }
    }
    protected function handlerRequest(){
        if(I('param.')){
            $this->request = (object) I('param.');
            $this->handlerRequestDefault();
        }else{
            if(!$this->handlerRequestDefault()){
                $this->handlerDontLocation('数据出错!!');
            }
        }
    }
    //get默认数据
    protected function handlerGetDefaultData(){

    }
    protected function handlerResponse($data,$type = 'json'){
//        header('Content-Type:application/json; charset=utf-8');
        header('Content-Type:text/html; charset=utf-8');
        exit(json_encode($data));
//        var_dump(json_last_error());die;
        //$this->ajaxReturn($data,$type);
    }
}