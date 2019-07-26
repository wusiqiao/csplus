<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;


class DistributionIncomeModel extends DataModel {

    public function getIncomeList($_filter,$page_index, $page_size,$_order){
        $branch_id = getBrowseBranchId();
        //由于inviter可以表示手机或名称，所以需要特殊处理，没办法按机制处理
        if(I("get.id")){
            $_filter['inviter_id'] = I("get.id");
        }else{
            $inviter_name = I("post.inviter_name");
            if ($inviter_name){
                if (preg_match("/13[1235689]{1}\d{8}|15[1235689]\d{8}|18[235689]{1}\d{8}/", $inviter_name)){
                    $_filter["inviter.mobile"] = array("like", '%'.$inviter_name .'%');
                }else{
                    $where["inviter.name"] = array("like", '%'.$inviter_name .'%');
                    $where["inviter.comments"] = array("like", '%'.$inviter_name .'%');
                    $where["inviter.staff_name"] = array("like", '%'.$inviter_name .'%');
                    $where['_logic'] = "or";
                    $_filter["_complex"] = $where;
                    //$_filter["inviter.name"] = array("like", '%'.$inviter_name .'%');
                }
            }
        }
        $cache_key = "getIncomeList_".md5($branch_id.json_encode($_filter));
        $count = S($cache_key);
        if (empty($count)){
            $sql = "select count(a.id) as count from distribution_income a inner join sys_user user on user.id=a.user_id
                           inner join distribution_relation relation on relation.openid=user.openid 
                           inner join sys_user inviter on inviter.id=relation.inviter_id
                           where a.branch_id=$branch_id";
            $sql = mergeString($sql, $this->parseWhere($_filter), " and ");
            $count_list = $this->query($sql);
            $count = empty($count_list)?0:$count_list[0]["count"];
            S($cache_key, $count, 6000);
        }
        $sql = "select a.*,user.name as member_name,user.mobile as member_mobile,inviter.name as inviter_name,inviter.mobile,inviter.staff_name as inviter_staff_name,inviter.comments as inviter_comments,
                    (case when a.status = 0 then '未解冻' when a.status = 1 then '已解冻' else '无效' end) as status_view,
                    (case when a.source_type = 0 then '关注' when a.source_type = 1 then '注册' else '订单' end) as source_type_view  
                    from distribution_income a                     
                    inner join sys_user user on user.id=a.user_id
                    inner join distribution_relation relation on relation.openid=user.openid 
                    inner join sys_user inviter on inviter.id=relation.inviter_id                   
                    where a.branch_id=$branch_id";
        $sql = mergeString($sql, $this->parseWhere($_filter), " and ");
        $sql = mergeString($sql, $this->parseWhere($_order), " order by ");
        $sql.= " limit ". ($page_index - 1) * $page_size . ",$page_size";
        $income_list = $this->query($sql);
        foreach ($income_list as $k=>$v){
            $income_list[$k]['inviter_name'] = $v['inviter_staff_name'] == "" ? ($v['inviter_comments'] == "" ? $v['inviter_name'] : $v['inviter_comments']):$v['inviter_staff_name'];
        }
        return array("count"=>$count, "list"=>$income_list);
    }
}
