<?php

namespace EShop\Model;

use Think\Model;

/**
 * 软文处理
 */
class ComTweetsModel extends Model
{
    protected $_MODEL = 'ComTweets';
    protected $_ADDITIONAL_MODEL = 'ComTweetsAdditional';
    protected $_PREFIX= 'com_';
    public function getTweetsById($id)
    {
       return M($this->_MODEL)->where('id = '.$id)->find();
    }
    public function getBranchTweetsAdditional(){
        $branch_id = getBrowseBranchId();
        $additional = M($this->_ADDITIONAL_MODEL)->where('branch_id = '.$branch_id)->find();
        return $additional;
    }
    /*
     * 列出软文列表
     */
    public function getTweetsLists($data){
        $pages = (array) $data;
        $page_size  = $pages['page_size'];
        $paging     = $pages['paging'];
        $condition['is_open'] = 1;
        $condition['_string'] = 'branch_id = '.getBrowseBranchId().' or branch_id is null';
        $tweets = M($this->_MODEL)->order('created_at desc')->where($condition)->page($paging, $page_size)->select();
        foreach ($tweets as $key => $val){
            $tweets[$key]['time'] = formatRevealTime($val['created_at']);
            $tweets[$key]['eye'] = (string) $this->formatRevealCount($val['browse_count'] + $val['base_count']);
            $tweets[$key]['share'] =(string) $this->formatRevealCount($val['share_count'] + (int) ($val['base_count']/3));
        }
        return ['count'=>count($tweets),'list'=>$tweets];
    }
    /*
     * 列出所有的软文列表
     */
    public function getSpreadList()
    {
        $condition['is_open'] = 1;
        $tweets = M($this->_MODEL)->order('created_at desc')->where($condition)->select();
        foreach ($tweets as $key => $val){
            $tweets[$key]['time'] = formatRevealTime($val['created_at']);
            $tweets[$key]['eye'] = (string) $this->formatRevealCount($val['browse_count'] + $val['base_count']);
            $tweets[$key]['share'] =(string) $this->formatRevealCount($val['share_count'] + (int) ($val['base_count']/3));
            $tweets[$key]['url'] = 'https://'.$_SERVER['HTTP_HOST'].'/Spread/tweets/id/'.$val['id'].'.html';
        }
        return $tweets;
    }
    /*
     * setInc
     */
    public function handlerSetInc($id,$single = 'browse_count'){
        M($this->_MODEL)->where('id = '.$id)->setInc($single);
    }
    protected function formatRevealCount($count){
        return  $count > 999 ? '999+' : $count;
    }
    public function handlerUpdateAdditional($data)
    {
        $data = (array) $data;
        $additional = $this->getBranchTweetsAdditional();
        if($additional){
            $data['top_pic'] = $data['top_pic'] ?? null;
            $data['bottom_pic1'] = $data['bottom_pic1'] ?? null;
            $data['bottom_pic2'] = $data['bottom_pic2'] ?? null;
            $data['updated_at'] = time();
            $res = M($this->_ADDITIONAL_MODEL)->where('id = '.$additional['id'])->save($data);
        }else{
            $data['branch_id'] = getBrowseBranchId();
            $data['created_at'] = time();
            $data['updated_at'] = time();
            $res = M($this->_ADDITIONAL_MODEL)->add($data);
        }
        return $res ? true : false;
    }


}
