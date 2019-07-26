<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;
use ESAdmin\Model\MaterialCenterConfigModel;
use ESAdmin\Model\MaterialCenterModel;
use Think\Controller;
use Think\Exception;

/**
 * 微信公众号 素材库
 * */
class ComMaterialLibraryController extends DataController{
    const _MATERIAL_RECYCLING_COUNT = 20;//每次同步请求数
    const _MATERIAL_RECYCLING_FIRST = 0;//每次同步第一个数
    const CONTENT_MAX = 200000;
    protected $_material_options = ['news', 'image', 'video', 'voice'];
    protected $_material_default = 'news';
    protected $_material_prefix = 'material_';
    protected $_material_first = 0;
    protected $_material_data = [];
    protected $_material_filter = [
        'image' => ['key' => 'name', 'value' => 'CropImage']
    ];//过滤条件

    public function indexAction($material = ''){
        $this->assignPermissions();
        if (empty($material) || !in_array($material, $this->_material_options)) {
            $template = 'index';
        } else {
            $template = $this->_material_prefix . $material;
        }

        $this->assign('material', $material);
        $this->display($template);
    }

    public function materialSingleDataAction(){
        if (IS_POST) {
            $postdata = I('post.');
            $condition['branch_id'] = getBrowseBranchId();
            $condition['various'] = $postdata['material'];
            $condition['media_id'] = $postdata['media_id'];
            $condition['parent_id'] = 0;
            $result = M(CONTROLLER_NAME)->where($condition)->find();
            $result['updated_at'] = date('Y-m-d H:i:s', $result['update_time']);
            if ($postdata['material'] == 'news') {
                unset($condition['media_id']);
                $condition['parent_id'] = $result['id'];
                $childrens = M(CONTROLLER_NAME)->where($condition)->select();
                $result['childrens'] = $childrens;
            }
            $this->responseJSON(['error' => 0, 'data' => $result]);
        }
    }

    /**
     * 素材选择器
     * @param  string material 素材类型  news | images
     * */
    public function queryAction(){
        $this->assign('material', I('material', 'news'));
        return $this->display('query');
    }

    /**
     * 素材同步操作
     * */
    public function synchronizationAction(){
        if (IS_POST) {
            if (isset($_POST['material']) && in_array($_POST['material'], $this->_material_options)) {
                $instance = getWeChatInstance();
                $result = $this->synchronizationRecycling($instance, $_POST['material']);
                if ($result) {
                    //进行数据库 & 数据操作
                    $result = D(CONTROLLER_NAME)->materialSynchronization($this->_material_data, $_POST['material']);
                    $this->ajaxReturn($result);
                } else {
                    $this->ajaxReturn(buildMessage('数据错误!', 1));
                }
            } else {
                $this->ajaxReturn(buildMessage('数据错误!', 1));
            }
        }
    }

    /**
     * 图文添加,允许用户操作图文编辑
     * @param  array data [
     *  [
     *      'title' => '标题', @
     *      'author' => '作者',
     *      'digest' => '摘要',
     *      'content_source_url' => '原文地址', @
     *       thumb_media_id => '图文模板ID', @
     *      'thumb_url' => '图文封面地址', @
     *      'local_thumb_url' => '图文本地地址' @
     *  ]
     * ]
     * */
    public function addNewsByEditAction(){
        $data = $post_data = I('post.data');
        $attributes['source'] = I('post.source');
        if (empty($data)) {
            return $this->ajaxReturn(buildMessage('文章不能为空!', 1));
        }
        $attributes = [];
        $attributes['branch_id'] = getBrowseBranchId();
        $ids = array_filter(array_unique(array_column($data, 'id')));
        if (count($ids) > 0) {
            $logModel = D('MaterialCenterUsedLog');
            if ($this->isFreeClient()) {
                $attributes['version'] = MaterialCenterConfigModel::VERSION_10;
            } else {
                $attributes['version'] = MaterialCenterConfigModel::VERSION_20;
            }

            if (!$logModel->isAllow($attributes['version'], $attributes['source'], $attributes['branch_id'], $ids)) {
                return $this->ajaxReturn(buildMessage('超过可用次数', 1));
            }
        }

        $attributes['updateTime'] = time();
        $parent['media_id'] = '';
        $parent['update_time'] = $attributes['updateTime'];
        $parent['parent_id'] = 0;
        $parent['branch_id'] = $attributes['branch_id'];
        $parent['various'] = 'news';
        $parent['syn_status'] = 30;
        $parent['title'] = $data[0]['title'];

        $ComMaterialLibraryModel = D('ComMaterialLibrary');
        //一下是MyISAM 表操作
        $parentId = $ComMaterialLibraryModel->add($parent);
        if ($parentId) {
            $signature = D('MaterialCenterSignature')->getSignature($attributes['branch_id']);
            $logData = $this->_before_list_write($data, new MaterialCenterModel(), $attributes);
            if (is_string($logData)) {
                $ComMaterialLibraryModel->where(['id' => $parentId])->delete();
                return $this->ajaxReturn(buildMessage($logData, 1));
            }

            $count = 0;
            $total = count($data);
            foreach ($data as $item) {
                if ($ComMaterialLibraryModel->add($item)) {
                    $count++;
                }
            }

            if ($count == 0) {
                $ComMaterialLibraryModel->where(['id' => $parentId])->delete();
                $parentId = false;
            }
        }

        if ($parentId) {
            return $this->ajaxReturn(buildMessage('添加成功!', 0));
        } else {
            return $this->ajaxReturn(buildMessage('添加失败', 1));
        }
    }

    public function addNewsAction(){
        $ids = I('post.ids');
        $ids = explode(',', $ids);
        $count = count($ids);
        if ($count <= 0) {
            return $this->ajaxReturn(buildMessage('请选择要发表的文章!', 1));
        }

        if ($count > 8) {
            return $this->ajaxReturn(buildMessage('单次最多发表8篇文章!', 1));
        }

        $attributes['branch_id']  = getBrowseBranchId();
        $attributes['source']  =I('post.source');
        $attributes['source']  = $attributes['source'] ? $attributes['source'] : 10;
        //判定是否允许发表文章
        $logModel = D('MaterialCenterUsedLog');
        if ($this->isFreeClient()) {
            $version = MaterialCenterConfigModel::VERSION_10;
        } else {
            $version = MaterialCenterConfigModel::VERSION_20;
        }

        //获取选择的文章
        $materialCenterModel = new MaterialCenterModel();
        $material = $materialCenterModel->where(['source' => $attributes['source'], 'id' => ['in', $ids]])->field('id,title,digest,cover_url,author,content,source')->select();
        if(count($material) <= 0){
            return $this->ajaxReturn(buildMessage('选择的文章可能被删除了，无法操作!', 1));
        }

        if (!$logModel->isAllow($version, $attributes['source'], $attributes['branch_id'], $ids)) {
            return $this->ajaxReturn(buildMessage('超过可用次数', 1));
        }

        //根据用户的排序，进行排序
        $list = [];
        foreach($ids as $id){
            foreach($material as $key => $item){
                if($item['id'] == $id){
                    array_push($list, $item);
                    unset($material[$key]);
                }
            }
        }

        $attributes['updateTime'] = time();
        $attributes['branch_id']  = getBrowseBranchId();
        $attributes['signature']  =  D('MaterialCenterSignature')->getSignature($attributes['branch_id']);

        //上传封面!
        $model    = M('ComMaterialLibrary');
        $materialCenterModel = new MaterialCenterModel();
        $instance = getWeChatInstance();;
        foreach ($list as $key => $item) {
            $list[$key]['content'] = $materialCenterModel->cleanShowImgUrl($item['content']);
            $thumb = $this->uploadThumb($item['cover_url'], $instance, $model);
            if(!$thumb){
                return $this->ajaxReturn(buildMessage('保存失败', 1));
            }

            unset($list[$key]['cover_url']);
            $list[$key]['thumb_media_id'] = $thumb['media_id'];
            $list[$key]['thumb_url'] = $thumb['url'];
            $list[$key]['local_thumb_url'] = $thumb['local_thumb_url'];
        }

        //创建图文父框架
        $parent['media_id'] = '';
        $parent['update_time'] = $attributes['updateTime'];
        $parent['parent_id'] = 0;
        $parent['branch_id'] = $attributes['branch_id'];
        $parent['various'] = 'news';
        $parent['syn_status'] = 30;
        $parent['title'] = $list[0]['title'];
        if(!$attributes['parentId'] = $model->add($parent)){
            return $this->ajaxReturn(buildMessage('保存失败!', 1));
        }

        //对文章数据验证
        $logData = $this->_before_list_write($list, $materialCenterModel, $attributes);
        if(!is_array($logData)){
            return $this->ajaxReturn(buildMessage($logData, 1));
        }

        M()->startTrans();
        if(!$model->addAll($list)){
            M()->rollback();
            $model->where(['id' => $attributes['parentId']])->delete();
        }

        $logModel->addAll($logData);

        return $this->ajaxReturn(['code' => 0, 'id' => $attributes['parentId']]);
    }

    public function addImagesAction($data = null){
        $data = $data ? $data : I("post.");
        $data['parent_id'] = 0;
        $data['media_id'] = '';
        $data['various'] = 'image';
        $data['local_thumb_url'] = $data['url'];
        $data['url'] = '';
        $data['update_time'] = time();
        $data['branch_id'] = getBrowseBranchId();
        $data['syn_status'] = 30;
        $data = M(CONTROLLER_NAME)->create($data);
        try {
            $id = M(CONTROLLER_NAME)->add($data);
            $this->ajaxReturn(['code' => 0, 'message' => '添加成功!', 'id' => $id]);
        } catch (Exception $e) {
            $this->ajaxReturn(['code' => 1, 'message' => $e->getMessage()]);
        }
    }

    public function childAction($id){
        $id = intval($id);
        if ($id <= 0) {
            return $this->ajaxReturn(['code' => 1, '数据异常']);
        }

        $list = D(CONTROLLER_NAME)->where(['parent_id' => $id, 'branch_id' => getBrowseBranchId()])->select();
        return $this->ajaxReturn(['code' => 0, 'data' => $list]);
    }

    protected function synchronizationRecycling($instance, $material_various){
        $material = $instance->getForeverList($material_various, $this->_material_first, self::_MATERIAL_RECYCLING_COUNT);
        if (isset($material['errcode']) && $material['errcode'] == '40007') {
            return false;
        }else{
            if ($this->_material_first == self::_MATERIAL_RECYCLING_FIRST) {
                if (!empty($this->_material_filter[$material_various])) {
                    foreach ($material['item'] as $key => $value) {
                        if ($value[$this->_material_filter[$material_various]['key']] == $this->_material_filter[$material_various]['value']) {
                            unset($material['item'][$key]);
                        }
                    }
                    $material['item'] = array_values($material['item']);
                }
                foreach ($material['item'] as $key => $value) {
                    if (!empty($this->_material_filter[$material_various]) && $value[$this->_material_filter[$material_various]['key']] == $this->_material_filter[$material_various]['value']) {
                        unset($material['item'][$key]);
                    } else {
                        if (isset($value['thumb_url']) || $material_various == 'image') {
                            $thumb_url = isset($value['thumb_url']) ? $value['thumb_url'] : $value['url'];
                            $img_location_url = './uploads/asks/' . str_replace(['http://mmbiz.qpic.cn/mmbiz_jpg/', 'http://mmbiz.qpic.cn/mmbiz_jpeg/', 'http://mmbiz.qpic.cn/mmbiz_png/', '/0?wx_fmt=jpeg', '/0?wx_fmt=png', '/0?wx_fmt=jpg'], '', $thumb_url) . '.jpeg';
                            if (!file_exists($img_location_url)) {
                                file_put_contents($img_location_url, file_get_contents($thumb_url));
                            }
                            $material['item'][$key]['local_thumb_url'] = $img_location_url;
                        }
                    }
                    $material['item'] = array_values($material['item']);
                }
                $this->_material_data = [
                    'total_count' => $material['total_count'],
                    'item' => $material['item']
                ];
            } else {
                if ($material['item_count'] > 0) {
                    foreach ($material['item'] as $key => $value) {
                        if (!(isset($this->_material_filter[$material_various]) && $value[$this->_material_filter[$material_various]['key']] == $this->_material_filter[$material_various]['value'])) {
                            if (isset($value['thumb_url']) || $material_various == 'image') {
                                $thumb_url = isset($value['thumb_url']) ? $value['thumb_url'] : $value['url'];
                                $img_location_url = './uploads/asks/' . str_replace(['http://mmbiz.qpic.cn/mmbiz_jpg/', 'http://mmbiz.qpic.cn/mmbiz_png/', '/0?wx_fmt=jpeg', '/0?wx_fmt=png'], '', $thumb_url) . '.jpeg';
                                if (!file_exists($img_location_url)) {
                                    file_put_contents($img_location_url, file_get_contents($thumb_url));
                                }
                                $value['local_thumb_url'] = $img_location_url;
                            }
                            array_push($this->_material_data['item'], $value);
                        }
                    }
                }
            }
            if ($material['item_count'] == self::_MATERIAL_RECYCLING_COUNT) {
                $this->_material_first += self::_MATERIAL_RECYCLING_COUNT;
                $this->synchronizationRecycling($instance, $material_various);
            }
            return true;
        }
    }

    /**
     * 图文 封面的上传
     * */
    private function uploadThumb($path, $instance, $model){
        $filePath = realpath('./' . $path);
        $pathinfo = pathinfo($filePath);
        $data['media'] = class_exists('\CURLFile') ? new \CURLFile($filePath, strtolower($pathinfo['extension']), $pathinfo['basename']) : '@' . $filePath;
        $res = $instance->uploadForeverMedia($data, 'image');
        if (!$res) {
            return $this->ajaxReturn(buildMessage('由于' . $instance->errMsg . '封面同步失败', 1));
        }

        $data['url'] = $res['url'];
        $data['media_id'] = $res['media_id'];
        $data['parent_id'] = 0;
        $data['various'] = 'image';
        $data['local_thumb_url'] = $path;
        $data['update_time'] = time();
        $data['branch_id'] = getBrowseBranchId();
        $data['syn_status'] = 10;
        $model->save($data);

        return $data;
    }

    protected function _before_write($type, &$data){
        parent::_before_write($type, $data); // TODO: Change the autogenerated stub
        if (empty($data['various'])) {

        }

        if ($data['various'] == 'news') {
            if (empty($data['thumb_media_id']) || empty($data['title']) || empty($data['content'])) {
                return $this->ajaxReturn(['code' => 1, 'message' => '文章标题|封面|内容为必须']);
            }
        }

        if ($type == self::ACTION_DETAIL && $data['various'] == 'news') {
            $parent = D(CONTROLLER_NAME)->where(['id' => $data['parent_id']])->find();
            if ($parent['syn_status'] == 10 || $data['syn_status'] == 10) {
                $data['syn_status'] = 20;
                if (!D(CONTROLLER_NAME)->where(['id' => $data['parent_id']])->save(['syn_status' => 20, 'update_time' => time()])) {
                    return $this->ajaxReturn(['code' => 1, 'message' => '更新失败']);
                }
            }
        }
    }

    protected function _parseOrder(&$order){
        parent::_parseOrder($order); // TODO: Change the autogenerated stub
        $order = 'a.update_time desc';
    }

    //content 进行 解码
    protected function _getDetailData($id){
        $data = parent::_getDetailData($id);
        $data['content'] = html_entity_decode($data['content']);

        return $data;
    }

    protected function _parsefilter(&$filter){
        parent::_parsefilter($filter); // TODO: Change the autogenerated stub
        $filter['a.parent_id'] = 0;
        if (isset($_GET['material']) && in_array($_GET['material'], $this->_material_options)) {
            $filter['a.various'] = $_GET['material'];
        }
        if (isset($_POST['qlname']) && $_POST['qlname'] != '') {
            if ($_GET['material'] == 'news') {
                $condition['branch_id'] = getBrowseBranchId();
                $condition['various'] = 'news';
                $condition['title'] = array('like', sprintf('%%%s%%', $_POST['qlname']));
                $ql_ids = M(CONTROLLER_NAME)->where($condition)->distinct(true)->getField('parent_id', true);
                if ($ql_ids) {
                    $filter['a.id'] = array('in', $ql_ids);
                } else {
                    $filter['a.id'] = 0;
                }
            } else {
                $filter['a.name'] = array('like', sprintf('%%%s%%', $_POST['qlname']));
            }
        }

        if (isset($_POST['syn_status'])) {
            $filter['a.syn_status'] = intval($_POST['syn_status']);
        }
    }

    protected function _before_list(&$list){
        parent::_before_list($list); // TODO: Change the autogenerated stub
        if (isset($_GET['material']) && $_GET['material'] == 'news') {
            $condition['branch_id'] = getBrowseBranchId();
            $condition['various'] = 'news';
            foreach ($list as $key => $value) {
                $condition['parent_id'] = $value['id'];
                $list[$key]['updated_at'] = date('Y-m-d H:i:s', $value['update_time']);
                $res = M(CONTROLLER_NAME)->where($condition)->field('title,thumb_url,id,order,local_thumb_url')->order(['order' => 'ASC', 'id' => 'ASC'])->select();
                if ($res) {
                    $list[$key]['childrens'] = $res;
                } else {
                    M(CONTROLLER_NAME)->where(['id' => $value['id']])->delete();
                    unset($list[$key]);
                }
            }
        }
    }

    //新闻添加 数组过滤
    protected function _before_list_write(&$data, MaterialCenterModel $model, $attributes = []){
        $logData = []; //日志
        foreach ($data as $key => &$value) {
            if (empty($value['title']) || empty($value['thumb_media_id']) || empty($value['thumb_url']) || empty($value['local_thumb_url'])) {
                return '标题，封面，内容不能为空';
            }

            array_push($logData, [
                'material_center_id' => $value['id'],
                'source' => isset($value['source']) ? $value['source'] : $attributes['source'],
                'branch_id' => $attributes['branch_id'],
                'used_time' => $attributes['updateTime'],
                'user_id' => $this->_user_session->userId,
            ]);

            unset($value['id']);
            if(isset($value['source'])){
                unset($value['source']);
            }
            $value['parent_id'] = $attributes['parentId'];
            $value['various'] = 'news';
            $value['show_cover_pic'] = 1;
            $value['branch_id'] = $attributes['branch_id'];
            $value['syn_status'] = 30;
            $value['update_time'] = $attributes['updateTime'];
            $value['content'] .= $attributes['signature'];
            $value['content'] = $model->cleanShowImgUrl($value['content']);
        }

        return $logData;
    }

    /**
     * 是否是免费用户
     * */
    protected function isFreeClient()
    {
        return $this->_user_session->branchRole == ROLE_ID_COMPANY_FREE;
    }
}