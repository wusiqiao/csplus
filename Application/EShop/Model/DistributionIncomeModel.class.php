<?php

namespace EShop\Model;

use Think\Model;


class DistributionIncomeModel extends Model {
    /** 添加服务订单佣金，先判断是否启用分销
     * @param $order_id
     */
    public function addOrderCommision($order_id){
        $user_id = session("user_id");
        $branch_id = getBrowseBranchId();
        return D("ESAdmin/ComOrder")->addOrderCommision($user_id, $branch_id, $order_id);
    }
}
