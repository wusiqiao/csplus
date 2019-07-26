<?php

namespace EShop\Model;


class WxReplyModel extends DataModel
{
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


    /**
     * 查询关注回复
     * @param int $branchId
     * */
    public function findAttentionByBranchId($branchId)
    {
        $data = $this->where([
            'branch_id' => $branchId,
            'event_type' => self::EVEBT_TYPE_10
        ])->find();

        return $this->getReplyList($data);
    }

    /**
     * 查询默认回复
     * @param int $branchId
     * */
    public function findDefaByBranchId($branchId)
    {
        $data = $this
            ->where([
                'branch_id' => $branchId,
                'event_type' => self::EVEBT_TYPE_30
            ])->find();

        return $this->getReplyList($data);
    }

    public function findMatch($branchId, $content)
    {
        $data = $this->where([
            'branch_id' => $branchId,
            'event_type' => self::EVEBT_TYPE_20,
            'keyword' => $content,
            'match_type' => self::MATCH_TYPE_20,
        ])->find();

        return $this->getReplyList($data);
    }

    /**
     * 查询互动回复
     * @param int $branchId
     * @param string $content
     * */
    public function searchInteractiveByBranchId($branchId, $content)
    {
        if ($data = $this->findMatch($branchId, $content)) {
            return $data;
        }

        $list = $this->where([
            'branch_id' => $branchId,
            'event_type' => self::EVEBT_TYPE_20,
            'keyword' => ['like', '%' . $content . '%'],
            'match_type' => self::MATCH_TYPE_30,
        ])->select();

        $count = count($list);
        if ($count == 1) {
            return $this->getReplyList($list[0]);
        }

        if($count > 1){
            return null;
        }

        return $this->findDefaByBranchId($branchId);
    }

    private function getReplyList($data)
    {
        if (empty($data)) {
            return null;
        }

        switch (intval($data['reply_type'])) {
            case self::REPLY_TYPE_20 :
                $sql = "SELECT * FROM com_material_library WHERE parent_id =
(SELECT id AS parent_id FROM com_material_library WHERE media_id = '{$data['content']}')";
                $data['child'] = $this->query($sql);
                break;
        }

        return $data;
    }
}
