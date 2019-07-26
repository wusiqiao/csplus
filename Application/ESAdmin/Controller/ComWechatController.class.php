<?php
namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;
use ESAdmin\Model\ComMaterialLibraryModel;

/**
 * 微信操作
 * @method
 * */
class ComWechatController extends DataController
{
    /**
     * 操作类型 -- 同步
     * */
    const TYPE_SYN = 'syn';
    /**
     * 操作类型 --  发送
     * */
    const TYPE_SEND = 'send';
    /**
     * 查询 部门员工的微信信息
     * */

    /**
     * 素材类型 -- 新闻
     * */
    const VAR_NEWS = 'news';
    /**
     * 素材类型 -- 图片
     * */
    const VAR_IMAGES = 'image';

    public function queryAction()
    {
        return $this->display('query');
    }

    /**
     * 发送客服消息
     * @param string $openid 微信openid
     * @param string $val 要发送的信息
     * @param string $type 发送素材类型 text 文本 news 图文
     * */
    public function sendMessageAction($openid, $val, $type = self::VAR_NEWS){
        switch ($type) {
            case 'news' :
                return $this->sendNewsAction($openid, $val);
                break;
            case 'text' :
                return $this->sendTextAction($openid, $val);
                break;
            default :
                return $this->ajaxReturn(['code' => 1, 'message' => $type . '类型, 暂不支持微信发送']);
        }
    }

    /**
     * 发送 文字消息
     * @param  string $openid 微信用户的openid
     * @param  string $content 要发送的文本
     * */
    public function sendTextAction($openid, $content){
        if (empty($openid) || empty($content)) {
            return $this->ajaxReturn(['code' => 1, 'message' => '数据丢失']);
        }

        $data['touser'] = $openid;
        $data['msgtype'] = 'text';
        $data['text']['content'] = $content;
        $instance = getWeChatInstance();
        if (!$instance->sendCustomMessage($data)) {
            return $this->weChatError($instance->errCode, $instance->errMsg);
        }

        return $this->ajaxReturn(['code' => 0, 'message' => '发送成功!']);
    }
    /**
     * 发送 图片消息
     * @param  string $openid 微信用户的openid
     * @param  string $id com_material_library 的ID
     * @param  string mediaId 图片的media_id
     * */
    public function sendImgAction($openid, $id = null, $mediaId = null){
        if(empty($openid) || (empty($id) && empty($mediaId))){
            return $this->ajaxReturn(['code' => '1', 'message' => '数据丢失!']);
        }

        if(empty($mediaId) || intval($id) > 0){
            $material = $this->getMaterial($id, self::VAR_IMAGES, self::TYPE_SEND);
            $mediaId = $material['mediaId'];
        }

        $data = [
            'touser' => $openid,
            'msgtype' => self::VAR_IMAGES,
            'image' => [
                'media_id' => $mediaId
            ]
        ];

        $instance = getWeChatInstance();
        if (!$instance->sendCustomMessage($data)) {
            return $this->weChatError($instance->errCode, $instance->errMsg);
        }

        return $this->ajaxReturn(['code' => 0, 'message' => '发送成功!']);
    }
    /**
     * 发送 图文消息
     * */
    public function sendNewsAction($openid, $materialLibraryId){
        $materialLibraryId = intval($materialLibraryId);
        if (empty($openid) || $materialLibraryId <= 0) {
            return $this->ajaxReturn(['code' => 1, 'message' => '数据丢失']);
        }

        $branch_id = getBrowseBranchId();
        $where = '`parent_id`  = ' . $materialLibraryId . ' AND `branch_id` = ' . $branch_id;
        $list = D('com_material_library')->where($where)->select();
        if (empty($list)) {
            return $this->ajaxReturn(['code' => 1, 'message' => '数据丢失']);
        }

        $instance = getWeChatInstance();
        foreach ($list as $item) {
            $data['touser'] = $openid;
            $data['msgtype'] = 'news';
            $data['news']['articles'];
            $data['news']['articles'][] = [
                'title' => $item['title'],
                'description' => $item['digest'],
                "url" => $item['url'],
                "picurl" => $item['thumb_url']
            ];

            if (!$instance->sendCustomMessage($data)) {
                return $this->weChatError($instance->errCode, $instance->errMsg);
            }

            unset($data);
        }

        return $this->ajaxReturn(['code' => 0, 'message' => '发送成功!']);
    }
    /**
     * 消息群发--图文群发
     * @param int $id com_material_library 的ID
     * */
    public function massSendallAction($id){
        $material = $this->getMaterial($id, self::VAR_NEWS, self::TYPE_SEND);
        $data['filter']['is_to_all'] = true;
        $data['mpnews']['media_id'] = $material['media_id'];
        $data['msgtype'] = 'mpnews';
        $data['send_ignore_reprint'] = 1;

        $instance = getWeChatInstance();
        if(!$instance->sendGroupMassMessage($data)){
            return $this->weChatError($instance->errCode, $instance->errMsg);
        }

        return $this->ajaxReturn(['code' => 0, 'message' => '群发任务发送成功，等待微信群发!']);
    }
    /**
     * 图片素材,上传  图片素材 不允许修改
     * @param int id com_material_library 的ID
     * */
    public function materialImgAction($id){
        $material = $this->getMaterial($id, self::VAR_IMAGES, self::TYPE_SYN);
        $path = strtr(dirname(THINK_PATH) . DIRECTORY_SEPARATOR . $material['local_thumb_url'], '\\', '/');
        $data['media'] = class_exists('\CURLFile') ? new \CURLFile($path, null, $material['name']): '@' . $path;
        $instance = getWeChatInstance();
        $res = $instance->uploadForeverMedia($data, 'image');
        if (!$res) {
            return $this->weChatError($instance->errCode, $instance->errMsg);
        }

        $this->updateComMaterialLibrary($material['id'], ['url' => $res['url'], 'media_id' => $res['media_id']]);
        return $this->ajaxReturn(['code' => 0, 'message' => $res['url']]);
    }

    /**
     * 图文上传,修改
     * @param int $id com_material_library 的ID
     * */
    public function materialNewsAction($id){
        $material = $this->getMaterial($id, self::VAR_NEWS, self::TYPE_SYN);
        $news = M('com_material_library')
            ->where(['parent_id' => $material['id']])
            ->select();

        if(count($news) < 1){
            $this->ajaxReturn(['code' => 1, 'message' => '图文信息不全!']);
        }

        foreach($news as $key => $itme){
            $news[$key]['content'] = html_entity_decode( $news[$key]['content']);
        }

        $update = ['syn_status' => ComMaterialLibraryModel::SYN_STATUS_10];
        $instance = getWeChatInstance();
        if($material['syn_status'] == ComMaterialLibraryModel::SYN_STATUS_20){
            foreach($news as $key => $new){
                $result = $this->updateMaterialNews($material['media_id'], $key, $new, $instance);
                if(!$result){
                    break;
                }

                $this->updateComMaterialLibrary($new['id'], $update);
            }
        }else{
            $result = $this->createMaterialNews($news, $instance);
        }

        if(!$result){
            return $this->weChatError($instance->errCode, $instance->errMsg);
        }

        if(isset($result['media_id'])){
            $update['media_id'] = $result['media_id'];
        }

        $this->updateComMaterialLibrary($material['id'], $update);

        return $this->ajaxReturn(['code' => 0, 'message' => '上传成功']);
    }

    /**
     * 图文删除
     * @param int $id com_material_library 的ID
     * */
    public function deleteMaterialAction($id){
        $material = $this->getMaterial($id, self::VAR_IMAGES, self::TYPE_SEND);
        $instance = getWeChatInstance();
        if(!$instance->delForeverMedia($material['media_id'])){
            return $this->weChatError($instance->errCode, $instance->errMsg);
        }

        if(!M('com_material_library')->where("id=5 or parent_id = 5")->delete()){
            return $this->ajaxReturn(['code' => 1, 'message' => '本地数据删除失败!']);
        }

        return $this->ajaxReturn(['code' => 0, 'message' => '删除成功!']);
    }
    /**
     * 查询选择
     * */
    public function queryListAction()
    {
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $_filter['branch_id'] = $this->_user_session->currBranchId;
        $_filter['user_type'] = USER_TYPE_COMPANY_MANAGER;
        $_filter['is_follow'] = 1;
        $_filter['is_valid'] = 1;
        $total = M('SysUser')->where($_filter)->count();
        $result = M('SysUser')->where($_filter)->field('head_pic,staff_name,openid')->page($page_index, $page_size)->select();

        return $this->ajaxReturn(['total' => $total, 'rows' => $result]);
    }
    /**
     * 群发预览
     * @param string $openid 消息类型
     * @param string $msgtype 消息类型, 暂时只支持 media_id 类型数据发送
     * @param string $mediaId
     * */
    public function massPreviewAction($openid, $msgtype, $mediaId){
        if(empty($openid) || empty($msgtype) || empty($mediaId)){
            return $this->ajaxReturn(['code' => '1', 'message' => '数据信息不全']);
        }
        $data['touser'] = $openid;
        $data['msgtype'] = $msgtype;
        $data[$msgtype]['media_id'] = $mediaId;
        if($data['msgtype'] == 'mpnews'){
            $data['send_ignore_reprint'] = 1;
        }

        $instance = getWeChatInstance();
        if(!$instance->previewMassMessage($data)){
            return $this->weChatError($instance->errCode, $instance->errMsg);
        }

        return $this->ajaxReturn(['code' => 0, 'message' => '请求成功!']);
    }
    /**
     * 修改图文素材
     * @param string $mediaId 图文素材的 media_id
     * @param int $index 要修改的索引
     * @param $item array 要修改的数据信息
     * @param WeChat $instance
     * */
    protected function updateMaterialNews($mediaId, $index, $item, $instance){
        $data['articles']= [
            'title' => $item['title'],  //标题
            'thumb_media_id' => $item['thumb_media_id'], //图文消息的封面图片素材id（必须是永久mediaID）
            'author' => $item['author'], //作者
            'digest' => $item['digest'], //图文消息的摘要，仅有单图文消息才有摘要，多图文此处为空。如果本字段为没有填写，则默认抓取正文前64个字。
            'show_cover_pic' => intval($item['show_cover_pic']) , //是否显示封面，0为false，即不显示，1为true，即显示
            'content' => $item['content'], //图文消息的具体内容，支持HTML标签，必须少于2万字符
            'content_source_url' => $item['content_source_url'], //图文消息的原文地址，即点击“阅读原文”后的URL
        ];

        return $instance->updateForeverArticles($mediaId, $data, $index);
    }

    /**
     * 新增新闻
     * @param  array $news 新增图文的子集
     * @param WeChat $instance
     * */
    protected function createMaterialNews($news, $instance){
        $data['articles'] = [];
        foreach($news as $new){
            $data['articles'][] = [
                'title' => $new['title'],  //标题
                'thumb_media_id' => $new['thumb_media_id'], //图文消息的封面图片素材id（必须是永久mediaID）
                'author' => $new['author'], //作者
                'digest' => $new['digest'], //图文消息的摘要，仅有单图文消息才有摘要，多图文此处为空。如果本字段为没有填写，则默认抓取正文前64个字。
                'show_cover_pic' => intval($new['show_cover_pic']) , //是否显示封面，0为false，即不显示，1为true，即显示
                'content' => $new['content'], //图文消息的具体内容，支持HTML标签，必须少于2万字符
                'content_source_url' => $new['content_source_url'], //图文消息的原文地址，即点击“阅读原文”后的URL
            ];
        }

       return $instance->uploadForeverArticles($data);
    }
    /**
     * 获取数据库素材
     * @param string $various 素材类型  image | news
     * */
    protected function getMaterial($id, $various = 'image', $type = 'syn'){
        $id = intval($id);
        if ($id <= 0) {
            return $this->ajaxReturn(['code' => 1, 'message' => '数据丢失']);
        }

        $material = M('com_material_library')
            ->where(['id' => $id, 'branch_id' => getBrowseBranchId()])
            ->find();
        if (empty($material) || $material['various'] != $various) {
            return $this->ajaxReturn(['code' => 1, 'message' => '上传素材不存在!']);
        }

        if($type == 'syn' && $material['syn_status'] == ComMaterialLibraryModel::SYN_STATUS_10){
            return $this->ajaxReturn(['code' => 1, 'message' => '已同步到微信端!']);
        }

        if($type == 'send' && $material['syn_status'] != ComMaterialLibraryModel::SYN_STATUS_10){
            return $this->ajaxReturn(['code' => 1, 'message' => '请同步微信端']);
        }

        return $material;
    }
    /**
     *修改ComMaterialLibrary表
     * @param int $id 主键
     * @param array $data 要修改的数据
     * */
    protected function updateComMaterialLibrary($id, $data = []){
        if(empty($data)){
            return false;
        }

        $data['syn_status'] = $data['syn_status'] ? $data['syn_status'] :  ComMaterialLibraryModel::SYN_STATUS_10;
        if(! M('com_material_library')->where(['id' => $id])->save($data)){
            return $this->ajaxReturn(['code'=> 1, 'message' => '数据更新失败!']);
        }

        return true;
    }

    private function weChatError($code, $message){
        $msg = [
            40001 => 'AppID错误或者AppSecret错误或者AccessToken无效,请检查',
            40002 => '不合法的凭证类型',
            40003 => '用户OpenID失效',
            40164 => '服务IP不在白名单内， 请联系客服设置' ,
            50005 => '用户未关注公众号',
            45011 => '使用太频繁，请稍候再试',
            48002 => '用户拒收消息',
            41009 => '用户OpenID失效',
            40132 => '微信号不合法',
            40121 => '不合法的 media_id',
            40118 => '不合法的 media_id',
            40014 => 'AccessToken过期',
            40013 => 'AppID配置错误',
            45015 => '用户取消关注，或者长时间未互动',
        ];

        $data['code'] = $code;
        $data['message'] = isset($msg[$code]) ? $msg[$code] : ($message ? $message : '微信处理失败!');
        return $this->ajaxReturn($data);
    }
}