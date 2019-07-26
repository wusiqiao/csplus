<?php

namespace EShop\Controller;

use Think\Controller;

class  ComGuestController extends BaseController {
 	public function indexAction() {
    $this->title = "用户管理";
    $this->display('index');
 	} 

  public function searchAction() {
    $page = I("get.page");
    $data = D('SysUser')
        ->setDacFilter('a')
        ->where([
      'a.name'=>[['exp','is not null'],['exp','<> ""']],
      'a.is_follow'=>0,
      'a.branch_id'=>$this->user_branch
    ])->limit(20*($page-1),20)
    ->order('last_time desc')
    ->select();
    foreach ($data as $k => $v) {
      if (isset($data[$k]['last_time'])) {
        $data[$k]['last_time'] = date('Y-m-d H:i:s',$data[$k]['last_time']);
      }
    }
    $this->ajaxReturn($data);
  } 
}