<?php

namespace ESAdmin\Model;


class UserParseModel extends SysUserModel {
    protected $tableName = 'sys_user';
    protected $_filter = ['is_follow' => 0,'mobile' =>array(array('eq',''),array('exp','is null'),'or')];
    protected $_model_list = [
        'group' => 'sys_target_group',
        'tag' => 'sys_target_tag',
        'user_relation_tag' => 'sys_user_relation_tag',
        'user_relation_company' => 'sys_user_branch'
    ];
    protected $_link = array(
        "Company" => array(
            "join_name" => "LEFT",
            'class_name' => "SysBranch",
            'foreign_key' => 'branch_id',
            'mapping_name' => 'company',
            'mapping_fields' => 'name',
            "mapping_key" => "id"
        ),
        "Group" => array(
            "join_name" => "LEFT",
            'class_name' => "sysTargetGroup",
            'foreign_key' => 'group_id',
            'mapping_name' => 'groups',
            'mapping_fields' => 'value',
            "mapping_key" => "id"
        )
    );
    public function getUsersOpenid($ids)
    {
        $condition['id'] = array('in',$ids);
        return $this->where($condition)->getField('openid',true);
    }
    public function getUserIndexesCompany($id)
    {
        $condition['user_id'] = $id;
        $condition['type'] = 1;
        $company_ids = M($this->_model_list['user_relation_company'])
            ->where($condition)
            ->getField('branch_id',true);
        $where['parent_id'] = getBrowseBranchId();
        if ($company_ids){
            $where['id'] = array('not in',$company_ids);
        }
        $companys = D('ComCompany')->field('name,id,contact,linkman')->where($where)->select();
        return $companys;
    }
    public function handlerUserSingleBindCompany($data)
    {
        $temp = [
            'branch_id' => $data['company_id'],
            'type' => 1,
            'user_id' => $data['id'],
        ];
        $result = M($this->_model_list['user_relation_company'])->add($temp);
        return $result ? buildMessage('绑定成功',0) : buildMessage('绑定失败',1);
    }
    public function unbindCompany($data)
    {
        $condition['user_id'] = $data['id'];
        $condition['branch_id'] = $data['company_id'];
        // $condition['type'] = 1;
        $result = M($this->_model_list['user_relation_company'])->where($condition)->delete();
        return $result ? buildMessage('该公司已成功解除绑定',0) : buildMessage('解除绑定失败,请重试',1);
    }
    //人员管理专用
    public function getGroupNames($data)
    {
        $condition['relation.user_id'] = $data['id'];
        $condition['relation.branch_id'] = getBrowseBranchId();
        $condition['tags.branch_id'] = getBrowseBranchId();
        $tags = M($this->_model_list['user_relation_tag'])
            ->alias('relation')
            ->field('tags.value')
            ->join($this->_model_list['tag'].' as tags on tags.id = relation.tag')
            ->where($condition)
            ->select();
        if ($tags) {
            $tags_value = [];
            foreach($tags as $key=>$val) {
                $tags_value[] = $val['value'];
            }
            return implode(',',$tags_value);
        } else {
            return '';
        }
    }
    //获取所有添加的标签/分组
    public function getBranchTarget($type)
    {
        $condition['branch_id'] = getBrowseBranchId();
        $tags = M($this->_model_list[$type])->where($condition)->select();
        return $tags;
    }

    public function getCompanys()
    {
        $companys = D("ComCompany")->select();
        return $companys;
    }

    public function handlerUsersBindCompany($data)
    {
        $addll = [];
        foreach($data['ids'] as $key=>$val){
            $condition = [
                'branch_id' => $data['company_id'],
                'type' => 1,
                'user_id' => $val
            ];
            $result = M($this->_model_list['user_relation_company'])->where($condition)->count();
            if (!$result){
                $addll[] = $condition;
            }
        }
        if (!empty($addll)) {
            $result = M($this->_model_list['user_relation_company'])->addAll($addll);
            return $result ? buildMessage('公司绑定成功',0) : buildMessage('公司绑定失败',0);
        }
        return buildMessage('公司绑定成功',0);

    }
    public function getUserBindCompanys($data)
    {
        $condition['users.id'] = $data['id'];
        $condition['branch.parent_id'] = getBrowseBranchId();
        $condition['branch.type'] = array('neq','2');
        $branchs = $this->alias('users')
            ->field('branch.*')
            ->join($this->_model_list['user_relation_company'].' as urt on urt.user_id = users.id')
            ->join('sys_branch as branch on branch.id = urt.branch_id')
            ->where($condition)
            ->select();
        return $branchs ?? [];
    }
}