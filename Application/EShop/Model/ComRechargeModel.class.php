<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/19
 * Time: 14:16
 */

namespace EShop\Model;


use Think\Model;

class ComRechargeModel extends Model
{
    public function getSingleRechargeData($id,$options = [],$alias = 'a')
    {
        $condition[$alias.'.id'] = $id;
        $condition[$alias.'.branch_id'] = getBrowseBranchId();
        if ($options) {
            foreach ($options as $key =>$value) {
                if (strpos($key,'.') === false) {
                    $condition[$alias.'.'.$key] = $value;
                }
            }
        }
        $result =  $this->alias($alias)
                        ->field($alias.'.*,company.name as company_name,user.name as user_name')
                        ->join('sys_user as user on user.id = a.user_id','left')
                        ->join('sys_branch as company on company.id = a.company_id','left')
                        ->where($condition)
                        ->find();
        return $result;
    }
    public function handlerSingleRechargeSave($id,$options = [])
    {
       return $this->where('id = '.$id)->data($options)->save();
    }

}