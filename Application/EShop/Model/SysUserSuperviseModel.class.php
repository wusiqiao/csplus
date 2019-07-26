<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/3
 * Time: 10:34
 */

namespace EShop\Model;

use Think\Model;
class SysUserSuperviseModel extends Model
{
    protected $_model =  [
        'user_relation_tag' => 'SysUserRelationTag',
        'tag' => 'SysTargetTag',
        'group' => 'SysTargetGroup',
        'user' => 'SysUser',
        'information' => 'SysCustomerInformation'
    ];
    protected $tableName = 'sys_user';
    protected $branch_id;
    protected $storage;
    public function _initialize()
    {
        $this->_model = (Object) $this->_model;
        $this->branch_id = getBrowseBranchId();
    }
    /*
     * 返回用户组列表 包括每个组多少用户
     * effect 客户管理 - 分组管理
     * return array
     * created Aug 3,2018
     */
    public function getCustomerGroups()
    {
        $branch_id = $this->branch_id;
        //获取所有的组别
        $field = 'target_group.value,target_group.id';
        $condition = 'target_group.branch_id = '.$branch_id;
        $groups = D($this->_model->group)   ->setDacFilter('target_group')
                                            ->field($field)
                                            ->where($condition)
                                            ->order('target_group.id desc')
                                            ->select();
        if ($groups) {
            $condition_count['user.is_follow'] = 1;
            $condition_count['user.is_valid'] = 1;
            foreach($groups as $key =>$value){
                $condition_count['user.group_id'] = $value['id'];
                $groups[$key]['user_count'] = D($this->_model->user)
                    ->setDacFilter('user')
                    ->where($condition_count)
                    ->count();
            }
        }
        //判断是否有未分组的用户
        $where  = 'a.is_follow = 1 and a.is_valid = 1 and a.branch_id = '.$branch_id. ' and (a.group_id IS NULL or a.group_id = 0 or a.group_id= \'\')';
        $dont_group_total = D($this->_model->user)->setDacFilter('a')->where($where)->count();
        if ($dont_group_total > 0)
        {
            $groups[] = [
                'value'=>'未分组',
                'id' => 0,
                'default' =>1,
                'user_count' => $dont_group_total
            ];
        }
        return $groups;
    }
    /*
     * 返回用户组列表 包括每个组多少用户
     * effect 客户管理 - 标签管理
     * return array
     * created Aug 3,2018
     */
    public function getCustomerTags()
    {
        $branch_id = $this->branch_id;
        //获取所有标签
        $field = 'target_tag.value,target_tag.id';
        $condition = 'target_tag.branch_id = '.$branch_id;
        $tags = D($this->_model->tag)       ->setDacFilter('target_tag')
                                            ->field($field)
                                            ->where($condition)
                                            ->group('target_tag.id')
                                            ->order('target_tag.id desc')
                                            ->select();
        if ($tags) {
            $condition_count['user.is_follow'] = 1;
            $condition_count['user.is_valid'] = 1;
            foreach($tags as $key =>$value){
                $condition_count['user_relation_tag.tag'] = $value['id'];
                $tags[$key]['user_count'] = D($this->_model->user)
                                            ->setDacFilter('user')
                                            ->join($this->getModelString('user_relation_tag') .' as user_relation_tag on user_relation_tag.user_id = user.id')
                                            ->where($condition_count)
                                            ->count();
            }
        }
//        var_dump($tags);die;
        return $tags;
    }
    /*
     * 返回组列表
     * effect 客户管理 - 全部用户(客户分组)
     * return array
     * created Aug 7,2018
     */
    public function getTargetGroups($option = [])
    {
        $field = '*';
        $order = 'a.count desc';
        $condition = $option;
        $condition['a.branch_id'] = $this->branch_id;
        $result = D($this->_model->group)
                        ->setDacFilter('a')
                        ->field($field)
                        ->where($condition)
                        ->order($order)
                        ->select();
        return $result;
    }
    /*
     * 返回标签列表
     * effect 客户管理 - 全部用户(客户标签)
     * return array
     * created Aug 7,2018
     */
    public function getTargetTags($option = [])
    {
        $field = '*';
        $condition = $option;
        $condition['branch_id'] = $this->branch_id;
        $order = 'a.count desc';
        $result = D($this->_model->tag)
                             ->setDacFilter('a')
                             ->field($field)
                              ->where($condition)
                              ->order($order)
                              ->select();
        return $result;
    }
    /*
     * 获取标签/分组信息
     *
     */
    public function getTargetData($data) {
        return M($this->_model->{$data->key})->where('id = '.$data->id)->find();
    }
    /*
     * 返回用户列表
     * effect 客户管理 - 全部用户(客户列表)
     * return array
     * created Aug 7,2018
     */
    public function getCustomerLists($storage)
    {
        $condition = $storage->condition;
        $order = '';
        $field  = $storage->field;
        if(count($storage->tags) > 1) {
            $user_ids = $this->handlerTagsReturnUserId($storage);
            if($user_ids != [] && count($user_ids) > 0){
                if (isset($condition['user.id'])) {
                    $condition['user.id'] = array($condition['user.id'],array('in',$user_ids),'and');
                } else {
                    $condition['user.id'] = array('in',$user_ids);
                }
                $result  =  D($this->_model->user)
                    ->setDacFilter('user')
                    ->field($field)
                    ->join($this->getModelString('group').' as team on team.id = user.group_id','LEFT')
                    ->where($condition)
                    ->group('user.id')
                    ->order($order)
                    ->limit($storage->page)
                    ->select();
                return $result;
            } else {
                return [];
            }
        } elseif (count($storage->tags) === 1) {
            $condition['tags.tag'] = array('in',$storage->tags_parse[0]);
            $result  =  D($this->_model->user)
                                ->setDacFilter('user')
                                ->field($field)
                                ->join($this->getModelString('user_relation_tag').' as tags on tags.user_id = user.id')
                                ->join($this->getModelString('group').' as team on team.id = user.group_id','LEFT')
                                ->where($condition)
                                ->group('user.id')
                                ->order($order)
                                ->limit($storage->page)
                                ->select();
            return $result;
        } else {
            $result  =  D($this->_model->user)
                                ->setDacFilter('user')
                                ->field($field)
                                ->join($this->getModelString('group') . ' as team on team.id = user.group_id','LEFT')
                                ->where($condition)
                                ->order($order)
                                ->limit($storage->page)
                                ->select();
            return $result;
        }
    }
    /*
     * 修改标签名称
     * effect 客户管理 - 标签列表(修改标签名称)
     * return array
     * created Aug 8,2018
     */
    public function saveTargetNameRevise($data)
    {
        $condition['id'] = $data->id;
        $condition['value'] =  $data->value;
        return M($this->_model->{$data->key})->save($condition);
    }
    /*
      * 标签/组 单项查询用户列表
      * param key value id
      * effect 客户管理 - 标签列表(修改标签名称)
      * return array
      * created Aug 8,2018
      */
    public function getCustomerTargetData($data)
    {
        $result = ($data->key == 'group') ? $this->SingleGroupFromCustomer($data) : $this->SingleTagFromCustomer($data);
        return $result;
    }
    /*
     * 增加标签的 查询次数
     * effect 客户管理 - 全部用户(客户列表)
     * return array
     * created Aug 8,2018
     */
    public function incTagQueryCount($tags)
    {
        $tag_array = [];
        foreach ($tags as $key => $val)
        {
            foreach ($val as $k => $v)
            {
                $tag_array[] = $v;
            }
        }
        $condition['id'] = array('in',$tag_array);
        M($this->_model->group) ->where($condition) ->setInc('count');
    }
    /*
     * 单个标签返回客户列表
     * effect 客户管理 - 标签管理(客户列表)
     * return array
     * created Aug 8,2018
     */
    public function SingleTagFromCustomer($tags)
    {
        $condition['link.branch_id'] = $this->branch_id;
        $condition['link.tag'] = $tags->id;
        $condition['user.is_valid'] = 1;
        $condition['user.user_type'] = array('neq',USER_TYPE_COMPANY_MANAGER);
        $order = '';
        $result = M($this->_model->user_relation_tag)
                            ->alias('link')
                            ->field('user.name,user.id,user.head_pic,user.group_id,if(user.group_id > 0 , groups.value , \'未分组\') as group_name')
                            ->join($this->getModelString('user') . ' as user on user.id = link.user_id')
                            ->join($this->getModelString('group') . ' as groups on groups.id = user.group_id','left')
                            ->where($condition)
                            ->order($order)
                            ->select();
        return ['rows'=>$result,'total'=>count($result)];
    }
    /*
     * 增加组的 查询次数
     * effect 客户管理 - 全部用户(客户列表)
     * return array
     * created Aug 8,2018
     */
    public function incGroupQueryCount($group)
    {
        $condition['id'] = array('in',$group);
        M($this->_model->group) ->where($condition) ->setInc('count');
    }
    /*
     * 单个组返回客户列表
     * effect 客户管理 - 组管理(客户列表)
     * return array
     * created Aug 8,2018
     */
    public function SingleGroupFromCustomer($group)
    {
        $condition['branch_id'] = $this->branch_id;
        $condition['_string'] = $group->id > 0 ? 'group_id = '.$group->id : ' group_id is null or group_id = 0';
        $condition['is_valid'] = 1;
        $condition['user_type'] = array('neq',USER_TYPE_COMPANY_MANAGER);
        $order = '';
        $result = M($this->_model->user)
                            ->field('name,id,head_pic')
                            ->where($condition)
                            ->order($order)
                            ->select();
        return ['rows'=>$result,'total'=>count($result)];
    }
    public function createCustomerTarget($data)
    {
        $condition['created_at'] = time();
        $condition['count'] = 0;
        $condition['value'] = $data->value;
        $condition['branch_id'] = $this->branch_id;
        $condition['user_id'] = session('user_id');
        $condition['creator_id'] = session('user_id');
        $result = D($this->_model->{$data->key})->add($condition);
        return $result;
    }
    public function hasCustomerTargetId(Array $data)
    {
        $condition['id'] = array('in',$data['id']);
        return M($this->_model->{$data['key']}) ->where($condition) ->find();
    }
    public function rmCustomerTag($data)
    {
        $result = $this->rmCustomerTargetSurface(['id'=>$data->id,'key'=>'tag']);
        if($result){
            $condition['tag'] = array('in',$data->id);
            M($this->_model->user_relation_tag) ->where($condition) ->delete();
            return ['error'=>0,'message'=>'删除成功'];
        }
        return  ['error'=>'1','message'=>'删除失败'];
    }
    public function rmCustomerGroup($data)
    {
        $result = $this->rmCustomerTargetSurface(['id'=>$data->id,'key'=>'group']);
        if($result){
            $where['group_id'] = array('in',$data->id);
            $relation_ids = M($this->_model->user)->where($where)->getField('id',true);
            if($relation_ids) {
                $save['id'] = array('in',$relation_ids);
                $save['group_id'] = null;
                $res = M($this->_model->user)->save($save);
                return $res ? ['error'=>0,'message'=>'删除成功'] : ['error'=>'1','message'=>'删除失败'];
            }
            return ['error'=>0,'message'=>'删除成功'];
        }
        return ['error'=>'1','message'=>'删除失败'];
    }
    public function getOtherGroupCustomer($data)
    {
        $condition = $data->condition;
        $order = '';
        $field = 'user.name,user.id,user.head_pic,if(groups.id > 0 , groups.value , \'未分组\') as group_name,groups.id as group_id';
        $result  = M($this->_model->user)
                            ->alias('user')
                            ->field($field)
                            ->join($this->getModelString('group').' as groups on groups.id = user.group_id','LEFT')
                            ->where($condition)
                            ->order($order)
                            ->select();
        return ['rows'=>$result,'total'=>count($result)];
    }
    public function getOtherTagCustomer($data)
    {
        $condition = $data->condition;
        $order = '';
        $field = 'user.name,user.id,user.head_pic,if(groups.id > 0 , groups.value , \'未分组\') as group_name,groups.id as group_id';
        $result  = M($this->_model->user)
                                ->alias('user')
                                ->field($field)
                                ->join($this->getModelString('group').' as groups on groups.id = user.group_id','LEFT')
                                ->where($condition)
                                ->order($order)
                                ->select();
        return ['rows'=>$result,'total'=>count($result)];
    }
    /*
     * 分组添加客户
     * param users id
     */
    public function appendCustomerFromGroup($data)
    {
        $condition['branch_id'] = $this->branch_id;
        $condition['id'] = array('in',$data->users);
        $save['group_id'] = $data->id;
        return M($this->_model->user) ->where($condition) ->save($save);
    }
    /*
     * 分组删除客户
     * param users id
     */
    public function rmCustomerFromGroup($data)
    {
        if($data->users = $this->hasCustomerFromGroup($data,'delete')) {
            $condition['id'] = array('in',$data->users);
            $condition['group_id'] = $data->id;
            $result = D($this->_model->user) ->where($condition)->setField('group_id',null);
            return $result ? ['error' => 0,'分组用户删除成功'] : ['error' => 1 , 'message' => '分组用户删除失败'];
        } else {
            return ['error'=>1,'message'=>'所选择的用户已删除!'];
        }
    }
    /*
     * 标签添加客户
     * param users id
     */
    public function appendCustomerFromTag($data)
    {
        if($data->users = $this->hasCustomerFromTag($data)) {
            $appends = [];
            foreach($data->users as $key=>$val) {
                $appends[] = [
                    'branch_id' => $this->branch_id,
                    'created_at' => time(),
                    'user_id' => $val,
                    'tag' => $data->id
                ];
            }
            if($appends) {
                $result =  M($this->_model->user_relation_tag)->addAll($appends);
                return $result ? ['error' => 0,'message' => '标签用户添加成功'] : ['error' => 1 , 'message' => '标签用户添加失败'];
            } else {
                return ['error' => 1 , 'message' => '标签用户添加失败'];
            }
        } else {
            return ['error'=>1,'message'=>'所选择的用户已添加!'];
        }
    }
    /*
     * 标签删除用户
     */
    public function rmCustomerFromTag($data)
    {
        if($data->users = $this->hasCustomerFromTag($data,'delete')) {
            $condition['tag'] = $data->id;
            $condition['user_id'] = array('in',$data->users);
            $result = M($this->_model->user_relation_tag)->where($condition)->delete();
            return $result ? ['error' => 0,'标签用户删除成功'] : ['error' => 1 , 'message' => '标签用户删除失败'];
        } else {
            return ['error'=>1,'message'=>'所选择的用户已删除!'];
        }
    }

    /*
     * 客户详情
     */
    public function customerDetail($data)
    {
        $field = 'user.*,groups.value as group_name,(select sum(payment_money) from com_order where user_id = user.id and order_state in ('.ORDER_STATE_WAITING_JUDGE.','.ORDER_STATE_CLOSED.','.ORDER_STATE_HAS_JUDGE.')) as money';
        $condition['user.branch_id'] = $this->branch_id;
        $condition['user.id'] = $data->id;
        $condition['user.is_valid'] = 1;
        $customer = M($this->_model->user)
                            ->alias('user')
                            ->join($this->getModelString('group') .' as groups on groups.id = user.group_id','left')
                            ->field($field)
                            ->where($condition)
                            ->find();
        //获取自添加的客户详细资料
        $where['user_id'] = $data->id;
        $where['branch_id'] = $this->branch_id;
        $info  = M($this->_model->information)
                            ->field('value,title,id')
                            ->where($where)
                            ->select();
        $where_relation['user_id'] = $data->id;
        $where_relation['relation.branch_id'] = $this->branch_id;
        $tags  = M($this->_model->user_relation_tag)
                            ->alias('relation')
                            ->field('tags.value,tags.id')
                            ->join($this->getModelString('tag') . ' as tags on tags.id = relation.tag ')
                            ->where($where_relation)
                            ->select();
        return compact('customer','info','tags');

    }
    /*
     * 客户资料修改
     */
    public function customerEdit($data)
    {
        $condition['id'] = $data['id'];
        $save = $data['users'];
        $save['updated_at'] = time();
        $result = M($this->_model->user) ->where($condition) ->save($save);
        if ($result) {
            //修改所属标签
            $this->handlerEditCustomerTags($data['tags'],$data['id']);
            //修改自定义资料
            $this->handlerEditCustomerInformation($data['information'],$data['id']);
            return ['error' => 0,'message' => '修改成功'];
        } else {
            return ['error' => 1,'message' => '修改失败'];
        }

    }
    public function hasInformationTitle($data) {
        $condition['branch_id'] = $this->branch_id;
        $condition['user_id'] = session('user_id');
        $condition['title'] = trim($data->title);
        $result = M($this->_model->information)->where($condition)->count();
        return $result > 0 ? true : false;
    }
    public function hasCustomerFromGroup($data,$type = 'append')
    {
        $condition['id'] = array('in',$data->users);
        $condition['group_id'] = $data->id;
        $result = D($this->_model->user) ->where($condition)->getField('id',true);
        $result = $result ?? [];
        if ( $type === 'append') {
            return  count($result) === count($data->users) ? false : array_diff($data->users,$result);
        } else {
            return  count($result) == 0 ? false : $result;
        }
    }
    public function hasCustomerFromTag($data,$type = 'append')
    {
        $condition['user_id'] = array('in',$data->users);
        $condition['tag'] = $data->id;
        $result = D($this->_model->user_relation_tag) ->where($condition)->getField('user_id',true);
        $result = $result ?? [];
        if ( $type === 'append') {
            return  count($result) === count($data->users) ? false : array_diff($data->users,$result);
        } else {
            return  count($result) == 0 ? false : $result;
        }

    }
    //删除存放分组/标签表里面的数据
    protected function rmCustomerTargetSurface(Array $data)
    {
        $condition['id'] = array('in',$data['id']);
        return M($this->_model->{$data['key']}) ->where($condition) ->delete();
    }
    protected function handlerEditCustomerTags($tags_new,$id)
    {
        $condition['user_id'] = $id;
        if (!empty($tags_new)) {
            $adds = [];
            $tags_old = M($this->_model->user_relation_tag) ->where($condition) ->getField('tag',true);
            if (!empty($tags_old)) {
                $adds_tag = array_diff($tags_new,$tags_old);
                $remove_tag = array_diff($tags_old,$tags_new);
                if (!empty($adds_tag)) {
                    foreach($adds_tag as $key => $val) {
                        $adds[] = [
                            'user_id' => $id,
                            'branch_id' =>$this->branch_id,
                            'tag' => $val,
                            'created_at' => time()
                        ];
                    }
                    M($this->_model->user_relation_tag) ->addAll($adds);
                }
                if (!empty($remove_tag)) {
                    $condition['tag'] = array('in',$remove_tag);
                    M($this->_model->user_relation_tag) ->where($condition) ->delete();
                }
            } else {
                foreach($tags_new as $key => $val) {
                    $adds[] = [
                        'user_id' => $id,
                        'branch_id' =>$this->branch_id,
                        'tag' => $val,
                        'created_at' => time()
                    ];
                }
                if (!empty($adds)) {
                    M($this->_model->user_relation_tag) ->addAll($adds);
                }
            }
        } else {
            M($this->_model->user_relation_tag) ->where($condition) ->delete();
        }
    }
    protected function handlerEditCustomerInformation($information,$id) {
        $condition['user_id'] = $id;
        if (!empty($information['new']) || !empty($information['old'])) {
            $adds = [];
            if (!empty($information['old'])) {
                $info_old = M($this->_model->information) ->where($condition) ->getField('id');
                $edit_info = $information['old_ids'];
                $remove_info = array_diff($info_old,$information['old_ids']);
                if (!empty($remove_info)) {
                    $condition['id'] = array('in',$remove_info);
                    M($this->_model->information) ->where($condition) ->delete();
                }
                //修改
                if (!empty($edit_info)) {
                    foreach($information['old'] as $key => $val) {
                        if(in_array($val['id'],$edit_info)) {
                            $save['id'] = $val['id'];
                            $save['update_at'] = time();
                            $save['value'] = $val['value'];
                            M($this->_model->information) ->save($save);
                        }
                    }
                }
            } else {
                M($this->_model->information) ->where($condition) ->delete();
            }
            if(!empty($information['new'])) {
                foreach($information['new'] as $key => $val) {
                    $adds[] =[
                        'branch_id' => $this->branch_id,
                        'user_id' => $id,
                        'title' => $val['title'],
                        'value' => $val['value'],
                        'created_at' => time(),
                        'updated_at' => time()
                    ];
                }
                M($this->_model->information) ->addAll($adds);
            }

        } else {
            M($this->_model->information) ->where($condition) ->delete();
        }
    }
    public function getTagFromCustomerIds($data){
        $condition['tag'] = $data->id;
        return M($this->_model->user_relation_tag)->where($condition)->getField('user_id',true);
    }
/*******************************************************ValidateToolFunction****************************************************************/
    /*
     * 分组/标签,判断是否可以修改/增加
     * effect 客户管理 - 标签列表(修改标签名称)
     * return array
     * created Aug 8,2018
     */
    public function validateTarget($data,$type = 'update')
    {
        $result = M($this->_model->{$data->key})
                        ->where('branch_id = ' . $this->branch_id .' and value = \''.$data->value.'\'')
                        ->getField('id',true);
        if($result && $type == 'update') {
            return in_array($data->id,$result) ?
                ['error' => '2','message' => '请填写新的'.$data->name.'名称'] :
                ['error' => '1','message' => '该'.$data->name.'名称已存在'] ;
        } elseif ($result && $type == 'create') {
            return ['error' => '1','message' => '该'.$data->name.'名称已存在'] ;
        } else {
            return ['error' => 0];
        }
    }
    public function validateCustomerFromTag($data)
    {
        $result = M($this->_model->tag)->where('id = '.$data->id)->find();
        return $result ? ['error' => 0] : ['error'=>1,'message'=>'该标签不存在'];
    }
    public function validateCustomerFromGroup($data)
    {
        $result = M($this->_model->group)->where('id = '.$data->id)->find();
        return $result ? ['error' => 0] : ['error'=>1,'message'=>'该分组不存在'];
    }
    public function validatecustomerDetail($data)
    {
        $condition['branch_id']  = $this->branch_id;
        $condition['user_type']  = array('neq',USER_TYPE_COMPANY_MANAGER);
        $condition['id'] = $data->id;
        $result = M($this->_model->user)->where($condition)->find();
        if($result) {
            return $result['is_valid'] == 1 ?  ['error' => 0] : ['error' => 1 ,'message' => '该用户已被冻结'];
        } else {
            return ['error' => 1, 'message' => '该用户不存在!'];
        }
    }
/*******************************************************HandlerToolFunction****************************************************************/
    /*
     * 通过多个串联标签,返回用户列表
     * effect 客户管理 - 全部用户(客户列表)
     * return array
     * created Aug 7,2018
     */
    protected function handlerTagsReturnUserId($storage)
    {
        $user_ids = [];
        $tags_parse_count = count($storage->tags_parse);
        $condition['branch_id'] = $this->branch_id;
//        $condition['user.user_type'] = array('neq',USER_TYPE_COMPANY_MANAGER);
        $tag_array = [];
        foreach ($storage->tags as $key => $val)
        {
            foreach ($val as $k => $v)
            {
                $tag_array[] = $v;
            }
        }
        $condition['tag'] = array('in',$tag_array);
        $result = M($this->_model->user_relation_tag)
                            ->field('if(count(*) >= '.$tags_parse_count.',GROUP_CONCAT(tag),null) as tags,user_id')
                            ->where($condition)
                            ->group('user_id')
                            ->select();

        if($result)
        {
            foreach ($result as $key => $value)
            {
                if ($value['tags'] !== null)
                {
                    $template_tags = explode(',',$value['tags']);
                    foreach ($storage->tags_parse as $k => $v)
                    {
                        $template_parse = explode(',',$v);
                        if(array_intersect($template_parse,$template_tags) === $template_parse && $template_parse){
                            $user_ids[] = $value['user_id'];
                            break;
                        }
                    }

                }
            }
            return $user_ids;
        }
        else
        {
            return [];
        }

    }
    /*
     * author Lynn
     * created Aug 3,2018
     */
    protected function getModelString($inc)
    {
        return strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $this->_model->{$inc}));
    }
}