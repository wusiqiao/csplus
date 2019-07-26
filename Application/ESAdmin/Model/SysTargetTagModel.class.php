<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class SysTargetTagModel extends DataModel {
    protected function _before_insert(&$data, $options)
    {
        $branch_id = getBrowseBranchId();
        $where = ['branch_id'=>$branch_id,'value'=>$data['value']];
        $result = M("SysTargetTag")->field('value')->where($where)->select();
        if($result){
            E("该标签已存在，请重新输入！");
        }
        parent::_before_insert($data, $options);
        $data['created_at'] = time();
    }
    protected function _before_update(&$data, $options)
    {
        $branch_id = getBrowseBranchId();
        $where = ['branch_id'=>$branch_id,'value'=>$data['value']];
        $where['id'] = array("neq",$data['id']);
        $result = M("SysTargetTag")->field('value')->where($where)->select();
        if($result){
            E("该标签已存在，请重新输入！");
        }
        parent::_before_update($data, $options);
    }

    protected function _before_delete(&$data, $options)
    {
        $tagId = $data['where']['id'];
        $where = ['tag'=>$tagId];
        $result = M('SysUserRelationTag')->field('user_id')->where($where)->select();
        if($result){
            M('SysUserRelationTag')->where($where)->delete();
            // E("已经有用户使用该标签，不能删除!");
        }
        parent::_before_delete($data, $options);
    }
}