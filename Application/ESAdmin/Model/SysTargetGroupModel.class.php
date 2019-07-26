<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class SysTargetGroupModel extends DataModel {

    protected function _before_insert(&$data, $options)
    {
        $branch_id = getBrowseBranchId();
        $where = ['branch_id'=>$branch_id,'value'=>$data['value']];
        $result = M("SysTargetGroup")->field('value')->where($where)->select();
        if($result){
            E("该分组已存在，请重新输入！");
        }
        parent::_before_insert($data, $options);
        $data['created_at'] = time();
    }

    protected function _before_delete(&$data, $options)
    {
        $groupId = $data['where']['id'];
        $where = ['group_id'=>$groupId];
        $result = M('SysUser')->field('id')->where($where)->find();

        if($result){
            //删除该组，所有联系人都将移至未分组
            M('SysUser')->where($where)->save(['group_id'=>null]);
            // var_dump($where);
            // E("!!!");
        }
        parent::_before_delete($data, $options);
    }

    protected function _before_update(&$data, $options)
    {
        $branch_id = getBrowseBranchId();
        $where = ['branch_id'=>$branch_id,'value'=>$data['value']];
        $where['id'] = array("neq",$data['id']);
        $result = M("SysTargetGroup")->field('value')->where($where)->select();
        if($result){
            E("该分组已存在，请重新输入！");
        }
        parent::_before_update($data, $options);
    }

}