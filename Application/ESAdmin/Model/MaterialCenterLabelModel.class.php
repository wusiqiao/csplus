<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019-04-08
 * Time: 13:38
 */

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class MaterialCenterLabelModel extends DataModel
{
    /**
     * 状态 -- 等待上架；
     * */
    const STATUS_10 = 10;
    /**
     * 状态 -- 已上架；
     * */
    const STATUS_20 = 20;

    /**
     * 更新 状态
     * @param int $id
     * */
    public function updateStatus($id, $status = null)
    {
        $where['id'] = $id;
        $data = $this->where($where)->find();
        if (empty($data)) {
            return false;
        }
        if ($data['status'] == self::STATUS_20) {
            $updata['status'] = self::STATUS_10;
        } else {
            $updata['status'] = self::STATUS_20;
        }

        $updata['status'] = $status == null ?  $updata['status'] : $status;

        return $this->where($where)->save($updata);
    }
}