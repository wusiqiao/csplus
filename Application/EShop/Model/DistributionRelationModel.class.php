<?php

namespace EShop\Model;

use Think\Model;

class DistributionRelationModel extends Model {


    /**通过邀请者openid生成关系
     * 如果openid不存在关系表，则添加记录，否则，如果未注册，修改上级from_user
     * 如果已经注册，不做任何动作，如果被邀请者已经注册，不能成为邀请者下级。
     * @param $inviter_openid 邀请者微信openid
     * @param $openid 被要求用户openid
     * @param $subscribe是否关注
     */
    public function bindingWithOpenid($inviter_openid, $wxUser){
        $openid = $wxUser["openid"];
        if ($inviter_openid && $openid && ($inviter_openid !== $openid)) {
            //如果被邀请者已经注册，不能成为邀请者下级。
            $inviter_data = M("SysUser")->where(array("openid" => $inviter_openid))->find();
            if (empty($inviter_data)) { //邀请者未注册，就自动注册
                $inviter_data = getWeChatInstance()->getUserInfo($inviter_openid);
                D("SysUser")->userRegisterSilence($inviter_openid, $inviter_data["nickname"], $inviter_data["headimgurl"]);
            }
            $this->bindingWithInviterId($inviter_data, $wxUser);
        }
    }

    /**通过邀请者id生成关系
     * @param $inviter_id
     * @param $openid
     * @param $subscribe是否关注
     */
    private function bindingWithInviterId($inviter_data, $wxUser){
        $openid = $wxUser["openid"];
        if ($inviter_data && $openid) {
            $subscribe = $wxUser["subscribe"];
            //如果被邀请者未注册，建立关系，已经注册，判断是否有建立过关系。
            $data["subscribe"] = $subscribe;
            $data["subscribe_time"] = ($subscribe == true)?time():0;
            $data["inviter_id"] = $inviter_data["id"];
            $branch_id = getBrowseBranchId();
            //被邀请者如果还没成为别人下级，同时不是邀请者的上级（不能互为上下级）
            $relation_exists = $this->where(array("openid"=>$openid))->count();
            if ($relation_exists == 0){
                $condition["relation.openid"] = $inviter_data["openid"];
                if ($user_id = session("user_id")){ //能够获取到session的userid
                    $condition["relation.inviter_id"] = $user_id;
                    $count = $this->alias("relation")->where($condition)->count();
                }else{
                    $condition["user.openid"] = $openid;
                    $count = $this->alias("relation")->join("inner join sys_user user on user.id=relation.inviter_id")->where($condition)->count();
                }
                if ($count == 0){
                    $data["openid"] = $openid;
                    $data["unsubscribe_time"] = 0;
                    $data["branch_id"] = $branch_id;
                    $data["headimgurl"] = strval($wxUser["headimgurl"]);
                    $data["nickname"] = removeEmoji(strval($wxUser["nickname"]));
                    $data["create_time"] = time();
                    $this->add($data);
                }
            }else{
                //如果已经存在关系，修改被邀请者的上级,暂时不再变
                //$this->where(array("openid"=>$openid))->save($data);
            }
        }
    }


    /**关注
     * 存在还未关注，先通过别人推荐进来，然后在关注的情况，
     * 这时候关系表和用户表有记录，但是用户头像和昵称没有，所以要有个更新动作
     * @param $openid
     */
    public function subscribe($openid, $inviter_id = 0, $_tpWeChat = null){
        S($openid, 1);
        if($_tpWeChat == null){
            $wxUser = getWeChatInstance()->getUserInfo($openid);
        }else{
            $wxUser = $_tpWeChat->getUserInfo($openid);
        }
        $data["subscribe_time"] = time();
        $data["headimgurl"] = $wxUser["headimgurl"];
        $data["nickname"] = removeEmoji($wxUser["nickname"]);
        $data["unsubscribe_time"] = 0;
        $data["subscribe"] = 1;
        $this->where(array("openid"=>$openid))->save($data);

        $user_data["head_pic"] =  $data["headimgurl"];
        $user_data["name"] =  $data["nickname"];
        $user_data["followed_at"] = time();
        $user_data["is_follow"] = 1;
        $effect = M("SysUser")->where(array("openid"=>$openid))->save($user_data);
        if ($effect == false){
            session('is_follow', 1);
            session('followed_at', $user_data["followed_at"]);
            D("SysUser")->userRegisterSilence($openid,  $data["nickname"], $data["headimgurl"]);
        }
        if ($inviter_id){ //有邀请人，自动注册被邀者
            $inviter_data = M("SysUser")->where("id=$inviter_id")->find();
            $this->bindingWithInviterId($inviter_data, $wxUser);
        }
    }

    /**取消关注，如果在关系表中有存在，把取消关注时间设置为当前
     * @param $openid 取消关注用户openid
     */
    public function unSubscribe($openid){
        S($openid, 0);
        $data["unsubscribe_time"] = time();
        $data["subscribe"] = 0;
        $this->where(array("openid"=>$openid))->save($data);

        $user_data["unfollowed_at"] = time();
        $user_data["is_follow"] = 0;
        M("SysUser")->where(array("openid"=>$openid))->save($user_data);
    }


    /**获取用户下级,包括注册和未注册
     * @param $inviter_id
     * @param $only_registed 只返回已注册会员
     * @param $level 级数，1表示第一级，0表示全部
     */
    public function getTeamMember($inviter_id, $only_registed = false, $level = 1){
        if (empty($inviter_id)){
            return null;
        }
        $result = array();
        if (is_array($inviter_id)){
            $where = " where dr.subscribe=1 and dr.inviter_id in (". implode(",",$inviter_id).")";
        }else{
            $where = " where dr.subscribe=1 and dr.inviter_id=$inviter_id";
        }
        $sql = "select FROM_UNIXTIME(dr.subscribe_time,'%Y-%m-%d %H:%i') as subscribe_time,
                FROM_UNIXTIME(dr.unsubscribe_time,'%Y-%m-%d %H:%i') as unsubscribe_time, 
                u.id as member_id,'0.00' as commision,
                IF(ISNULL(u.id), dr.nickname, u.name) as member_name, 
                IF(ISNULL(u.id), dr.headimgurl, u.head_pic) as head_pic,
                IF(ISNULL(u.id), '未注册', FROM_UNIXTIME(reg_time,'%Y-%m-%d %H:%i')) as reg_time
                from  distribution_relation dr";
        if ($only_registed){
            $sql.=" inner join sys_user u on u.openid=dr.openid $where order by dr.subscribe_time desc";
        }else{
            $sql.=" left join sys_user u on u.openid=dr.openid $where order by dr.subscribe_time desc";
        }
        $result = $this->query($sql);
        if ($level == 0 && $result){ //获取二级
            $member_registers = array();
            foreach ($result as $son){
                if ($son["member_id"]){ //已注册
                    $member_registers[] = $son["member_id"];
                }
            }
            if ($member_registers){
                $grandsons = $this->getTeamMember($member_registers, $only_registed, $level);
                if ($grandsons){
                    $result = array_merge($result, $grandsons);
                }
            }
        }
        return $result;
    }

}