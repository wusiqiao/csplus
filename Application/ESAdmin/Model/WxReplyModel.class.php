<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class WxReplyModel extends DataModel
{
    protected $_link = [
        'father' => [
            "join_name" => "LEFT",
            'class_name' => "ComMaterialLibrary",
            'foreign_key' => 'content',
            'mapping_fields' => 'media_id,various,name,title,author,content,local_thumb_url,content_source_url,show_cover_pic,url,thumb_url,thumb_media_id,order,update_time',
            "mapping_key" => "media_id"
        ],
        'child' => [
            "join_name" => "LEFT",
            'class_name' => "ComMaterialLibrary",
            'foreign_key' => 'father.id',
            'mapping_fields' => 'media_id,various,name,title,author,content,local_thumb_url,content_source_url,show_cover_pic,url,thumb_url,thumb_media_id,order',
            "mapping_key" => "parent_id"
        ],
    ];
    /**
     * 回复事件类型 - 关注
     * */
    const EVEBT_TYPE_10 = 10;
    /**
         * 回复事件类型 - 关键词
     * */
    const EVEBT_TYPE_20 = 20;
    /**
     * 回复事件类型 - 默认
     * */
    const EVEBT_TYPE_30 = 30;

    /**
     * 回复类型 - 文本
     * */
    const REPLY_TYPE_10 = 10;
    /**
     * 回复类型 - 图文
     * */
    const REPLY_TYPE_20 = 20;
    /**
     * 回复类型 - 图片
     * */
    const REPLY_TYPE_30 = 30;
    /**
     * 回复类型 - 语音
     * */
    const REPLY_TYPE_40 = 40;

    /**
     * 匹配类型 - 完全匹配
     * */
    const MATCH_TYPE_20 = 20;
    /**
     * 匹配类型 - 模糊匹配
     * */
    const MATCH_TYPE_30 = 30;

    public function _before_insert(&$data, $options)
    {
        parent::_before_insert($data, $options); // TODO: Change the autogenerated stub
        //关注回复和自动回复仅允许一条数据添加
        if (in_array($data['event_type'], [
                self::EVEBT_TYPE_10,
                self::EVEBT_TYPE_30
            ]) && $this->existsByEvent($data['event_type'], getBrowseBranchId())
        ) {
            E('已创建该消息回复类型请勿重复创建!');
        }
    }

    public function _before_write(&$data)
    {
        parent::_before_write($data); // TODO: Change the autogenerated stub
        $id = isset($data['id']) ? $data['id'] : 0;

        if ($data['event_type'] == self::EVEBT_TYPE_20
            && $this->existsByKeyword($data['keyword'], getBrowseBranchId(), $id)
        ) {
            E('请勿重复创建类型相似的关键字');
        }
    }

    /**
     * 查询关注回复
     * @param int $branchId
     * */
    public function findAttentionByBranchId($branchId, $companyName = NULL)
    {
       $data = $this->setDacFilter("a")
           ->relation(true)
           ->field("a.*")
           ->where(['a.branch_id' => $branchId, 'a.event_type' => self::EVEBT_TYPE_10])
           ->find();
        if($data){
            return $data;
        }

        $data['branch_id'] = $branchId;
        $data['event_type'] = self::EVEBT_TYPE_10;
        $data['match_type'] = self::MATCH_TYPE_20;
        $data['keyword'] = 'hygz';
        $data['content'] = '欢迎关注' . $companyName;

        if( $this->save($data)) {
            return $this->findAttentionByBranchId($branchId);
        }
    }

    /**
     * 查询默认回复
     * @param int $branchId
     * */
    public function findDefaByBranchId($branchId)
    {
        return $this->setDacFilter("a")
            ->relation(true)->field("a.*")
            ->where(['a.branch_id' => $branchId, 'a.event_type' => self::EVEBT_TYPE_30])
            ->find();
    }

    /**
     * 查询关键字是否存在
     * @param string $keyword 关键字
     * @param int $excludeId 要排除的ID
     * */
    private function existsByKeyword($keyword, $branchId, $excludeId = 0)
    {
        return $this->where([
            'branch_id'  => $branchId,
            'event_type' => self::EVEBT_TYPE_20,
            'keyword'    => ['like', '%' . $keyword . '%'],
            'id'          => ['neq', $excludeId],
        ])->find();
    }

    /**
     * 根据回复事件类型，判定是否纯在
     * @param int $eventType 事件类型
     * @param int $branchId
     * */
    private function existsByEvent($eventType, $branchId)
    {
        return $this->where([
            'branch_id'  => $branchId,
            'event_type' => $eventType,
        ])->find();
    }
}
