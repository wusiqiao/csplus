<?php

namespace Common\Lib\Controller;

use Think\Controller;
use ESAdmin\Model\ComIterationMessageModel;
use ESAdmin\Model\MsgGroupMemberModel;
use ESAdmin\Model\MsgGroupModel;
use ESAdmin\Model\MsgReadStatisticsModel;
use ESAdmin\Model\MsgContentsModel;
use ESAdmin\Model\WxConfigModel;
use ESAdmin\Model\SysUserModel;


class MsgBaseController extends Controller {
    const TEMPLATE_ID = 'OPENTM202109783';
    const ALL_MEMBER_CACHE_TIME = 150;
    protected function getUserId(){
        E("错误的用户ID");
    }
    protected function getBranchId(){
        E("错误的公司ID");
    }
    protected function getUserType(){
        E("错误的用户类型");
    }
    protected function getCurrBranchId(){
        E("错误的上级组织");
    }
    /**
     * 获取所有的会员信息
     */
    public function MemberAction($isReturn = false){
        $userType = $this->getUserType();
        if($userType == 4 || $userType == 5){
            $list = $this->getBranchcurrMember();
        }else{
            $list = $this->getBranchMemberAll();
        }

        $list['group'] = $this->getGroupAction(true);
        $list['user']['id'] = [
            'id'   => $this->getUserId(),
            'type' => $userType
        ];


        $this->readMsg($list);
        if($isReturn){
            return $list;
        }

        $this->ajaxReturn($list);
    }

    /**
     * 好友查询
     * @param null $name
     * @param int $groupId
     */
    public function searchMemberAction($name = null, $groupId = 0){
        $groupId = intval($groupId);
        $userType = $this->getUserType();
        $userId   = $this->getUserId();
        $branchId = intval($this->getBranchId());
       try{
           $currBranchId = $this->getCurrBranchId();
       }catch(\Exception $e){
           $currBranchId = 0;
       }
        $keys = 'searchMemberAction' . $branchId;
        $search = S($keys);
        if(empty($list)){
            $sql = "SELECT `id`,`name`, `user_type`,`is_follow`,`comments`,`head_pic`,`staff_name` FROM `sys_user` WHERE  `branch_id` = {$branchId} AND `user_type` > 0 AND  `user_type` < 6";
            $search = MsgGroupMemberModel::getInstance()->query($sql);
            S($keys, $search, 150);
        }

        $isChild = false;
        if(($currBranchId != $branchId) && ($userType == 4 || $userType == 5)){
            $isChild = true;
            $childMember = S('childMemberItems_' . $currBranchId);
            if(empty($childMember)){
                $sql = "SELECT user_id AS id FROM `sys_user_branch` WHERE `branch_id` = {$currBranchId}";
                $childMember = MsgGroupMemberModel::getInstance()->query($sql);
                S('childMemberItems_' . $currBranchId, $childMember, 150);
            }
        }

        $list = [];
        foreach($search as $item){
            if($item['id'] == $userId){
                continue;
            }

            if($isChild && $item['user_type'] != 2){
                $bool = true;
                foreach($childMember as $k => $child){
                    if($child['id'] == $item['id']){
                        $bool = false;
                        unset($childMember[$k]);
                    }
                }

                if($bool){
                    continue;
                }
            }

            if(!empty($name)){
                $bool = true;
                if(strstr($item['name'], $name) !== false){
                    $bool = false;
                }

                if(strstr($item['comments'], $name) !== false){
                    $bool = false;
                }
                if(strstr($item['staff_name'], $name) !== false){
                    $bool = false;
                }

                if($bool){
                    continue;
                }
            }

            array_push($list, $item);
        }

        if($groupId > 0){
            $member = MsgGroupMemberModel::getInstance()->where(['msg_group_id' => $groupId])->field('user_id as id')->select();
            foreach($member as $item){
                foreach($list as $key => $value){
                    if($item['id'] == $value['id']){
                        unset($list[$key]);
                    }
                }
            }
        }

        $search = [];
        foreach($list as $item){
            array_push($search, $item);
        }

        $total = count($search);
        $page = intval(I('page')) - 1;
        $page = $page <= 0 ? 0 : $page;
        $list = array_chunk($search, 50);
        $search = isset($list[$page]) ? $list[$page] : [];

        return $this->ajaxReturn($search, 0, null,  'rows', $total);
    }
    /**
     * @return mixed
     */
    public function getGroupAction($isReturn = false){
        $key = 'getGroupAction_' . $this->getUserId();
        if(!$isReturn && S($key)){
            return $this->errorReturn('请不要过于频繁请求!');
        }

        $list =  (new MsgGroupMemberModel)
            ->alias('a')
            ->join('msg_group b ON a.msg_group_id=b.id')
            ->where(['a.user_id' => $this->getUserId()])
            ->field('b.id as group_id, b.name, b.mun,b.lord_id')
            ->select();

        if($isReturn){
            foreach($list as $key => $item){
                $list[$key]['count'] = 0;
            }

            return $list;
        }

        return $this->ajaxReturn($list);
    }
    /**
     * 创建新的群聊
     * @param $name
     */
    public function createGroupAction($name = null,$isReturn = false){
        if(empty($name)){
            return $this->errorReturn('请输入群名称!');
        }

        $userId = $this->getUserId();
        $time = time();
        $data['name'] = $name;
        $data['lord_id'] = $userId;
        $data['type'] = MsgGroupModel::TYPE_20;
        $data['branch_id'] = $this->getBranchId();
        $data['mun'] = 1;
        $data['member'] = '-';
        $data['create_time'] = $time;
        $data['update_time'] = $time;

        $groupModel = MsgGroupModel::getInstance();
        $groupModel->startTrans();
        try{
            $groupId = $groupModel->add($data);
            MsgGroupMemberModel::getInstance()->add([
                'msg_group_id' => $groupId,
                'create_time'  => $time,
                'user_id'       => $userId,
                'role'          => 20,
            ]);

            (new MsgReadStatisticsModel)->add([
                'user_id' => $userId,
                'msg_group_id' => $groupId,
                'wait_total' => 0,
                'last_receive_time' => 0,
                'last_send_time' => 0,
                'update_time' => time(),
            ]);

            $groupModel->commit();
        }catch(\Exception $e){
            $groupModel->rollback();
			if ($isReturn){
				return false;
			}
            return $this->errorReturn($e->getMessage());
        }

        if ($isReturn){
        	return ['group_id' => $groupId, 'name' => $name];
		}

        return $this->ajaxReturn(['group_id' => $groupId, 'name' => $name]);
    }

    public function updateGroupAction($groupId = null, $name = null){
        $groupId = intval($groupId);
        if($groupId <= 0){
            return $this->errorReturn('群ID丢失');
        }
        if(empty($name)){
            return $this->errorReturn('请输入要修改的名称');
        }

        $group = MsgGroupModel::getInstance()->where(['id' => $groupId])->find();
        if(empty($group)){
            return $this->errorReturn('群不存在！');
        }

        if($group['lord_id'] != $this->getUserId()){
            return $this->errorReturn('仅允许群主修改！');
        }

        if(!MsgGroupModel::getInstance()->where(['id' => $groupId])->save(['name' => $name, 'update_time' => time()])){
            return $this->errorReturn('修改失败！');
        }

        return $this->ajaxReturn();
    }
    /**
     * 获取消息组成员
     * @param $groupId
     */
    public function getGroupMemberAction($groupId, $isReturn = false){
        $groupId = intval($groupId);
        if($groupId < 1){
            return $this->errorReturn('请求异常，消息组ID丢失!');
        }

        if(! $this->validateGroupMember($groupId, $this->getUserId(), $group)){
            return $this->errorReturn('您已不是' . $group['name'] . '的成员!');
        }

        $list = MsgGroupMemberModel::getInstance()
            ->alias('a')
            ->join('sys_user b ON a.user_id = b.id')
            ->field('b.`id`,b.`name`, b.`user_type`,b.`is_follow`,b.`comments`,b.`head_pic`,b.`staff_name`,a.role')
            ->where(['a.msg_group_id' => $groupId])
            ->order(['create_time' => 'ASC'])
            ->select();

        if($isReturn){
            return $list;
        }

        return $this->ajaxReturn($list);
    }
    /**
     * 邀请成员
     * @param $groupId
     * @param $userId
     */
    public function addMemberAction(){
        $groupId = intval(I('groupId'));
        $userIds  = explode(',', I('userIds'));
        if($groupId < 1 || count($userIds) < 1){
            return $this->errorReturn('您要添加的成员丢失!');
        }

        if(!$groupData = MsgGroupModel::getInstance()->where(['id' => $groupId, 'branch_id' => $this->getBranchId()])->find()){
            return $this->errorReturn('群不存在!');
        }

        if($groupData['type'] != 20){
            return $this->errorReturn('群不存在!');
        }

        if(! MsgGroupMemberModel::getInstance()->where(['user_id' => $this->getUserId(), 'msg_group_id' => $groupId])->find()){
            return $this->errorReturn('您不是该群的成员无法邀请!');
        }

        $userIds = (new SysUserModel)->where(['branch_id' => $this->getBranchId(),'id' => ['in', $userIds]])->field('id')->select();
        $userIds = array_column($userIds, 'id');
        $ids = MsgGroupMemberModel::getInstance()->where(['msg_group_id' => $groupId, 'user_id' => ['in', $userIds]])->field('user_id as id')->select();
        if(count($userIds) == count($ids)){
            return $this->errorReturn('您要请成员，已存在!');
        }

        foreach($ids as $id){
            foreach($userIds as $key => $userId){
                if($userId == $id){
                    unset($userIds[$key]);
                }
            }
        }

        $time = time();
        $addData = [];
        $statisticsData = [];
        foreach($userIds as $id){
            array_push($addData, [
                'user_id' => $id,
                'msg_group_id' => $groupId,
                'create_time' => $time
            ]);

            array_push($statisticsData, [
                'user_id' => $id,
                'msg_group_id' => $groupId,
                'wait_total' => 0,
                'last_receive_time' => 0,
                'last_send_time' => 0,
                'update_time' => $time,
            ]);
        }

        try{
            MsgGroupMemberModel::getInstance()->startTrans();
            MsgGroupMemberModel::getInstance()->addAll($addData);
            (new MsgReadStatisticsModel)->addAll($statisticsData);
            MsgGroupModel::getInstance()->where(['id' => $groupId])->save([
                'mun' => $groupData['mun'] + count($addData),
                'update_time' => $time
            ]);
            MsgGroupMemberModel::getInstance()->commit();
            return $this->ajaxReturn(['group_id' => $groupId, 'member' => array_column($addData, 'user_id')]);
        }catch(\Exception $e){
            MsgGroupMemberModel::getInstance()->rollback();
            return $this->errorReturn('邀请失败!');
        }
    }

    /**
     * 删除群成员
     * @param $groupId
     * @param $userId
     */
    public function delMemberAction($groupId, $userId){
        $groupId = intval($groupId);
        $userId  =  intval($userId);
        if($groupId < 1 || $userId < 1){
            return $this->errorReturn('您要删除的成员丢失!');
        }

        $group = MsgGroupModel::getInstance()->where(['id' => $groupId])->find();
        if(empty($group)){
            return $this->errorReturn('群不存在!');
        }

        if($group['lord_id'] != $this->getUserId() && $userId != $this->getUserId()){
            return $this->errorReturn('您不是群主!');
        }

        if($userId == $this->getUserId() && $group['lord_id'] == $this->getUserId()){
            return $this->errorReturn('群主不允许退出!');
        }

        if(!MsgGroupMemberModel::getInstance()->where(['msg_group_id' => $groupId, 'user_id' => $userId])->find()){
            return $this->errorReturn('当前用户不在群内!');
        }

        try{
            MsgGroupMemberModel::getInstance()->startTrans();
            $count = MsgGroupMemberModel::getInstance()->where(['msg_group_id' => $groupId, 'user_id' => $userId])->delete();
            MsgGroupModel::getInstance()->where(['id' => $groupId])->save(['mun'=> $group['mun'] - 1, 'update_time' => time()]);
            (new MsgReadStatisticsModel)->where(['user_id' => $userId, 'msg_group_id' => $groupId])->delete();
            MsgGroupMemberModel::getInstance()->commit();
            return $this->ajaxReturn($count);
        }catch(\Exception $e){
            MsgGroupMemberModel::getInstance()->rollback();
            return $this->errorReturn('删除失败!');
        }
    }
    /**
     * 获取系统消息  10消息 20 迭代消息
     * @param int $type
     */
    public function getSysMessageAction($type = 10, $isReturn = false){
        $data = (new ComIterationMessageModel)->where(['type' => $type, 'status' => 1])
            ->page(I('get.page', 1), 20)
            ->order(['send_time' => 'DESC'])
            ->select();
        M('ComIterationMessageRead')->where(['user_id' => $this->getUserId(), 'type' => $type])->save(['count' => $this->getComIterationMessageTotal($type)]);

        $len = count($data) - 1;
        $list = [];
        foreach($data as $item){
            array_push($list, $data[$len]);
            $len--;
        }

        if($isReturn){
            return $list;
        }

        return $this->ajaxReturn($list);
    }
    /**
     * 消息统计接口
     */
    public function statisticsAction($isRetuen = false, $isAll = false){
        $noticeTotal = $this->getComIterationMessageTotal(10);
        $count_10 = M('ComIterationMessageRead')->where(['user_id' => $this->getUserId(), 'type' => 10 ])->field('count')->find();
        if(empty($count_10)){
            M('ComIterationMessageRead')->add(['user_id' => $this->getUserId(), 'type' => 10, 'branch_id' => $this->getBranchId(), 'object_id' => 0, 'count' => 0]);
        }else{
            $noticeTotal -= $count_10['count'];
        }

        $iterationTotal = $this->getComIterationMessageTotal(20);
        $count_20 = M('ComIterationMessageRead')->where(['user_id' => $this->getUserId(), 'type' => 20 ])->field('count')->find();
        if(empty($count_10)){
            M('ComIterationMessageRead')->add(['user_id' => $this->getUserId(), 'type' => 20, 'branch_id' => $this->getBranchId(), 'object_id' => 0, 'count' => 0]);
        }else{
            $iterationTotal -= $count_20['count'];
        }

        $data['sys_notice'] = $noticeTotal;
        $data['sys_iteration'] = $iterationTotal;
        if($isRetuen && !$isAll){
            return $data;
        }

        $branchId = $this->getBranchId();
        $userId = $this->getUserId();
        $staff = $member = $group = 0;
        $sqlGroup = "SELECT * FROM msg_group WHERE branch_id = '{$branchId}'";
        $model = new MsgReadStatisticsModel;
        $logs = $model
            ->alias('a')
            ->join("({$sqlGroup}) AS b ON a.msg_group_id = b.id")
            ->where(['a.user_id' => $this->getUserId()])
            ->field('a.id, a.wait_total as total, b.type,b.member')
            ->select();

        $members = '';
        foreach($logs as $log){
            if($log['member'] != '-'){
                $members .= "'{$log["member"]}',";
            }
        }
        $len = strlen($members) - 1;
        if($members[$len] == ','){
            $members = substr($members, 0, $len);
        }

        $users = [];
        if ($members) {
            $sqlUser = "SELECT * FROM (SELECT `user_type`, IF(`id` > {$userId}, CONCAT('{$userId}', '-', `id`), CONCAT(`id`, '-', '{$userId}')) AS member FROM sys_user WHERE branch_id = '{$branchId}') AS a WHERE a.member IN ({$members})";
            $users = $model->query($sqlUser);
        }

        foreach($logs as $key => $log){
            foreach($users as $user){
                if($log['member'] == $user['member']){
                    $logs[$key]['user_type'] = $user['user_type'];
                }
            }
        }

        foreach($logs as $log){
            if($log['type'] == 10){
                if($log['user_type'] < 4){
                    $staff += $log['total'];
                    if($log['total'] > 0){
                        dump($log);
                    }
                }else{
                    $member += $log['total'];
                }
            }else{
                $group += $log['total'];
            }
        }

        $data = array_merge([
            'staff' => $staff,
            'member' => $member,
            'group' => $group,
        ], $data);

        if($isRetuen){
            return $data;
        }

        return $this->ajaxReturn($data);
    }
    /**
     * 获取私聊信息
     * @param null $dialogueId 会人员的ID
     */
    public function getPrivateChatAction($dialogueId = null){
        $dialogueId = intval($dialogueId);
        if($dialogueId <= 0){
            return $this->errorReturn('获取会话Id失败!');
        }

        $sponsorId = $this->getUserId();
        $key = ' getPrivateChatAction_' . $sponsorId  . '__' .$dialogueId;
        if(S($key)){
            return $this->errorReturn('请不要过于频繁请求!');
        }
        if($dialogueId == $sponsorId){
            return $this->errorReturn('不支持自己与自己对话!');
        }

        $data['dialogue'] = (new SysUserModel)->where(['id' => $dialogueId, 'branch_id' => $this->getBranchId()])
            ->field('id,name, user_type,is_follow,comments,head_pic,staff_name')
            ->find();
        if(empty($data['dialogue'])){
            return $this->errorReturn('您要对话的人没有找到!');
        }
        $member = $sponsorId > $data['dialogue']['id'] ? $data['dialogue']['id'] . '-' . $sponsorId : $sponsorId  . '-' . $data['dialogue']['id'];
        $data['group'] = MsgGroupModel::getInstance()->where(['type' => MsgGroupModel::TYPE_10, 'member' => $member])->field('id as group_id,name')->find();
        if(empty($data['group'])){
            MsgGroupModel::getInstance()->startTrans();
            $time = time();
            try{
                $groupId = MsgGroupModel::getInstance()->add([
                    'name' => $member,
                    'type' => MsgGroupModel::TYPE_10,
                    'branch_id' => $this->getBranchId(),
                    'member' => $member,
                    'create_time' => $time,
                    'update_time' => $time,
                ]);

                $readStatistics[] = [
                    'user_id' => $sponsorId,
                    'msg_group_id' => $groupId,
                    'wait_total' => 0,
                    'last_receive_time' => 0,
                    'last_send_time' => 0,
                    'update_time' => $time,
                ];

                $readStatistics[] = [
                    'user_id' => $data['dialogue']['id'],
                    'msg_group_id' => $groupId,
                    'wait_total' => 0,
                    'last_receive_time' => 0,
                    'last_send_time' => 0,
                    'update_time' => $time,
                ];

                (new MsgReadStatisticsModel)->addAll($readStatistics);
                MsgGroupModel::getInstance()->commit();
            }catch(\Exception $e){
                MsgGroupModel::getInstance()->rollback();
                return $this->errorReturn('建立会话失败!!');
            }

            $data['group']['group_id'] = $groupId;
            $data['group']['name'] = $member;
            $data['message'] = [];
        }else{
            $data['message'] = $this->getMessageAction($data['group']['group_id'], null, true);
        }

        unset($data['dialogue']);
        S($key, 5, 3);
        return $this->ajaxReturn($data);
    }
    /**
     * 获取 消息组的消息
     * @param $groupId
     * @param bool $isReturn
     */
    public function getMessageAction($groupId, $type = null, $isReturn = false){
        $cacheKey = 'getMessageAction_' . $type . '_' . $groupId;
        if(!$isReturn && S($cacheKey)){
            return $this->errorReturn('请不要过于频繁请求!');
        }

        $this->validateGroupMember($groupId, $this->getUserId(), $group);
        $where['msg_group_id'] = $groupId;
        if($type == 10 || $type == 20){
            $where['contents_type'] = $type;
        }

        $list = MsgContentsModel::getInstance()
            ->where($where)
            ->field('send_user_id as send_id, accept_user_id as accept_id,is_read,contents_type as type, contents,create_time')
            ->order(['create_time' => 'DESC'])
            ->page(I('page', 1), 10)
            ->select();

        $this->updateReadAction($groupId);
        if(!empty($list)){
            $send_id = array_column($list, 'send_id');
            $userIds = array_unique($send_id);
            $userInfo = (new SysUserModel)
                ->where(['id' => ['in', $userIds]])
                ->field('id,name,user_type,is_follow,comments,head_pic,staff_name')
                ->select();

            foreach($list as $key => $item){
                foreach($userInfo as $info){
                    if($item['send_id'] == $info['id']){
                        $list[$key]['sendInfo'] = $info;
                    }
                }
            }
        }
        $len = count($list) - 1;
        $data = [];
        foreach($list as $item){
            array_push($data, $list[$len]);
            $len--;
        }

        if($isReturn){
            return $data;
        }

        S($cacheKey, 1, 1);
        return $this->ajaxReturn($data);
    }
    /**
     * 更新用户未读数量
     * */
    public function updateReadAction($groupId){
        (new MsgReadStatisticsModel)->where([
            'user_id' => $this->getUserId(),
            'msg_group_id' => $groupId])
            ->save([
                'wait_total' => 0,
                'update_time' => time()
            ]);
    }
    /**
     * 上传附件消息
     * @param bool $isReturn
     * @return array|void
     */
    public function uploadAction(){
        if(IS_GET){
            return $this->errorReturn('请求协议错误!');
        }

        $config = C("Storage");
        $groupId = intval(I('post.groupId'));
        $acceptId = intval(I('post.acceptId'));
        $userId = $this->getUserId();
        if(!$this->validateGroupMember($groupId, $acceptId, $group)){
            return $this->errorReturn('您不是当前消息组成员!');
        }

        $upload = new \Think\Upload($config);
        $filesInfo = [];
        if(count($filesInfo) > 6){
            return $this->errorReturn('同时允许6个附件上传');
        }

        foreach ($_FILES as $key => $file){
            $name = $file['name'];
            $info = $upload->uploadOne($file);
            if(!$info){
                return $this->errorReturn('文件上传失败!');
            }

            array_push($filesInfo, [
                'url'   => $info['url'],
                'size'  =>  $info['size'],
                'name'  => substr( $name,0, strripos($name, '.')),
                'type'  => substr( $info['url'],strripos($info['url'], '.') + 1),
            ]);
        }

        if(count($filesInfo) <= 0){
            return $this->errorReturn('请上传附件!');
        }

        $data = $acceptIds = [];
        if($group['type'] == 10){
            $data['accept_user_id'] = $acceptId;
            $data['read_num'] = 0;
            array_push($acceptIds, $data['accept_user_id']);
        }else{
            $acceptIds = (new MsgGroupMemberModel)->where(['msg_group_id' => $groupId])->field('user_id as id')->select();
            $mun = count($acceptIds);
            if($group['mun'] != $mun){
                MsgGroupModel::getInstance()->where(['msg_group_id' => $groupId])->save(['mun' => $mun]);
            }

            $data['accept_user_id'] = 0;
            $data['read_num'] = $mun - 1;
        }


        $data['msg_type'] = $group['type'];
        $data['msg_group_id'] = $groupId;
        $data['send_user_id'] = $userId;
        $data['is_read'] = 10;
        $data['contents_type'] = 20;
        $data['contents'] = json_encode($filesInfo, true);
        $data['branch_id'] = $group['branch_id'];
        $data['create_time'] = time();

        if(!($data['id'] = (new MsgContentsModel)->add($data))){
            return $this->errorReturn('上传失败');
        }

        $this->updateWaitTotalAction($groupId);
        $data['acceptIds'] = $acceptIds;
        $data['type'] = 20;
        $data['sendInfo']  = $this->getUserInfo($this->getUserId());
        $data['send_id'] = $userId;
        $data['groupType'] = $group['type'];

        return $this->ajaxReturn($data);
    }

    /**
     * 上传普通文本消息
     */
    public function sendMegAction(){
        $groupId = intval(I('post.groupId'));
        $acceptId = intval(I('post.acceptId'));
        $contents = I('post.contents');
        if(empty($contents)){
            return $this->errorReturn('请输入您要发送的消息!');
        }
        if(!$this->validateGroupMember($groupId, $acceptId, $group)){
            return $this->errorReturn('您不是当前消息组成员!');
        }

        $data = $acceptIds = [];
        if($group['type'] == 10){
            $data['accept_user_id'] = $acceptId;
            $data['read_num'] = 0;
            array_push($acceptIds, $data['accept_user_id']);
        }else{
            $acceptIds = (new MsgGroupMemberModel)->where(['msg_group_id' => $groupId, 'user_id' => ['neq', $this->getUserId()]])->field('user_id as id')->select();
            $mun = count($acceptIds);
            if($group['mun'] != $mun + 1){
                MsgGroupModel::getInstance()->where(['msg_group_id' => $groupId])->save(['mun' => $mun + 1]);
            }

            $data['accept_user_id'] = 0;
            $data['read_num'] = $mun;
        }

        $data['msg_type'] = $group['type'];
        $data['msg_group_id'] = $groupId;
        $data['send_user_id'] = $this->getUserId();
        $data['is_read'] = 10;
        $data['contents_type'] = 10;
        $data['contents'] = $contents;
        $data['branch_id'] = $group['branch_id'];
        $data['create_time'] = time();
        if(!($data['id'] = (new MsgContentsModel)->add($data))){
            return $this->errorReturn('上传失败');
        }

        $this->updateWaitTotalAction($groupId);
        $data['groupType'] = $group['type'];
        $data['type'] = 10;
        $data['acceptIds'] = $acceptIds;
        $data['sendInfo']  = $this->getUserInfo($this->getUserId());
        $data['send_id'] = $this->getUserId();

        return $this->ajaxReturn($data);
    }

    public function downloadFileAction($expire = 180){
        if(IS_GET){
            $url = I('get.url');
            $content = file_get_contents(I('get.url'));
            $type = substr($url, strripos($url, '.') + 1);
            $length = strlen($content);
            //发送Http Header信息 开始下载
            header("Pragma: public");
            header("Cache-control: max-age=".$expire);
            //header('Cache-Control: no-store, no-cache, must-revalidate');
            header("Expires: " . gmdate("D, d M Y H:i:s",time()+$expire) . "GMT");
            header("Last-Modified: " . gmdate("D, d M Y H:i:s",time()) . "GMT");
            header("Content-Disposition: attachment; filename=" . I('get.name', '文件'));
            header("Content-Length: ".$length);
            header("Content-type: ". $type);
            header('Content-Encoding: none');
            header("Content-Transfer-Encoding: binary" );
            echo($content);
            exit();
        }
    }
    /**
     * 推送微信消息!
     * @param $type int 消息类型  10 文本 20 文件
     * @param $branchId int  组织ID
     * @param $wait array 等待推送的用户ID
     * @param $groupId int 消息组ID
     * @param $contents string 消息内容!
     */
    public function sendWechatMsgAction(){
        if(IS_AJAX){
            $data = I('post.');
        }else{
            $data =  file_get_contents('php://input');
            $data = json_decode($data, true);
        }

        $type  = isset($data['type']) ? $data['type'] : 0;
        $branchId = isset($data['branchId']) ? $data['branchId'] : 0;
        if($branchId <= 0){
            return $this->errorReturn('组织丢失!', $branchId);
        }

        $wait  = isset($data['wait']) ? $data['wait'] : [];
        if(empty($wait)){
            return $this->ajaxReturn();
        }

        if(isset($wait[0]['id'])){
            $wait = array_column($wait, 'id');
        }

        $groupId = $data['groupId'];
        $contents = isset($data['contents']) ? $data['contents'] : '新的消息';
        $value = $type == 10 ? $contents : '新的附件消息';
        $instance = $this->getWechat($branchId);
        $message["template_id"] = $this->getWxTemplateIdByStandardId(self::TEMPLATE_ID, $branchId, $instance);
        $message["url"] = str_replace('shop', 'shop' . $branchId, SHOP_ROOT) . '/Talks/message/group_id/' .  $groupId;
        $body["first"]["value"]    = '您收到一条留言信息';
        $body["keyword1"]["value"] = '留言';
        $body["keyword2"]["value"] = $value;
        $body["remark"]["value"] = '点击查看详情';
        $message["data"] = $body;

        $userModel = new SysUserModel();
        $users = $userModel->where(['id' => ['in', $wait], 'branch_id' => $branchId ])->field('openid, is_follow')->select();
        foreach($users as $user){
            if($user['is_follow'] == 0){
                continue;
            }

            $message['touser'] = $user['openid'];
            $instance->sendTemplateMessage($message);
        }
    }

    protected function getGroupById($id){
        $data = MsgGroupModel::getInstance()->where(['id' => $id])->find();
        if(empty($data)){
            return $data;
        }

        if($data['type'] == 10){
            $data['members'] =  explode('-', $data['member']);
        }

       return $data;
    }
    private function updateWaitTotalAction($groupId){
        $time = time();
        $model = new MsgReadStatisticsModel;
        $model->where(['msg_group_id' => $groupId])->save([
            'wait_total' => 1,
            'last_receive_time' => $time,
            'update_time' => $time,
        ]);

        $model->where(['msg_group_id' => $groupId, 'user_id' =>  $this->getUserId()])->save([
            'last_send_time' => $time,
            'update_time' => $time,
            'wait_total' => 0,
        ]);
    }
    /**
     * @param $id
     * @return mixed
     */
    private function getUserInfo($id){
        return (new SysUserModel)->where(['id' => $id])->field("id,name,user_type,is_follow,comments,head_pic,staff_name")->find();
    }
    /**
     * 系统消息统计
     * @param null $type
     * @return int|mixed
     */
    private function getComIterationMessageTotal($type = null){
        $key = 'ComIterationMessageTotal' . $type;
        $total = S($key);
        if(!$total && $total !== 0){
            $where['status'] = 1;
            if($type){
                $where['type'] = $type;
            }

            $total = 0 + (new ComIterationMessageModel)->where($where)->count();
            S($key, $total);
        }

        return $total;
    }
    /**
     * 验证， 消息成员的存在
     * @param $groupId
     * @param $acceptId
     * @param $group
     * @return bool
     */
    private function validateGroupMember($groupId, $acceptId = 0, &$group){
        $group = $this->getGroupById($groupId);
        if(empty($group)){
            return false;
        }

        if($group['type'] == 10){
            if(!in_array($acceptId, $group['members'])){
                return false;
            }

            if(!in_array($this->getUserId(), $group['members'])){
                return false;
            }
        }else{
            if(!MsgGroupMemberModel::getInstance()->where(['user_id' => $this->getUserId(), 'msg_group_id' => $groupId])->find()){
                return false;
            }
        }

        return true;
    }

    private function getWxTemplateIdByStandardId($standard_id, $branch_id, $instance){
        $condition["standard_id"] = $standard_id;
        $condition["branch_id"] = $branch_id;
        $template_id  = M('WxTemplateConfig')->where($condition)->getField("template_id");
        if (empty($template_id)){
            $template_id = $instance->addTemplateMessage($standard_id);
            if ($template_id){
                $condition["template_id"] = $template_id;
                M('WxTemplateConfig')->add($condition);
            }
        }

        return (trim($template_id) == '' || !$template_id) ? false : trim($template_id);
    }
    /**
     * @param $branchId
     * @return \TpWechat
     */
    private function getWechat($branchId){
        Vendor('Wechat.TPWechat', '', '.class.php');
        $wx_config = (new WxConfigModel)->where(['branch_id' => $branchId])->find();
        if(empty($wx_config)){
            return false;
        }

        $options = [
            'id' => $wx_config['id'],
            'token' => $wx_config['token'], //填写你设定的key
            'appid' => $wx_config['appid'],
            'appsecret' => $wx_config['appsecret'],
            'encodingaeskey' => $wx_config['encoding_aeskey'], //填写加密用的EncodingAESKey，如接口为明文模式可忽略
            'is_author' => $wx_config['is_author'],
            'authorizer_refresh_token' => $wx_config['authorizer_refresh_token'],
        ];

        return new \TpWechat($options);
    }

    private function getSign($data, &$time, &$key){
        $time = time();
        $key = md5(json_encode(ksort($data)) . $time);
    }

    private function validateSign($data){
        if($_COOKIE['sign'] == md5(json_encode(ksort($data)) . $_COOKIE['time'])){
            return true;
        }

        return false;
    }
    /**
     * @param null $data
     * @param int $code
     * @param string $message
     */
    public function ajaxReturn($data = null, $code = 0, $message = '响应成功!', $field = 'data', $total = 0){
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode(['code' => $code, $field => $data, 'message' => $message, 'total' => $total], JSON_ERROR_UTF8));
    }

    public function errorReturn($message = '响应失败!', $data = null){
        if(!IS_AJAX){
            return $this->error($message);
        }

        return $this->ajaxReturn($data, 1, $message);
    }

    /**
     * 统计 用户消息接口
     * */
    private function readMsg(&$list = []){
        $statisticsTotal = $this->statisticsAction(true);
        $statisticsTotal = array_merge([
            'staff' => 0,
            'member' => 0,
            'group' => 0,
        ], $statisticsTotal);

        $memberIds = array_merge(array_column($list['group'], 'group_id'), $list['memberIds']);
        unset($list['memberIds']);
        $memberIds = array_filter($memberIds);
        $memberIds = array_unique($memberIds);
        if(!empty($memberIds)){
            $model = new MsgReadStatisticsModel;
            $statistics = $model->where(['user_id' => $this->getUserId(), ['msg_group_id' => ['in', $memberIds]]])
                ->field('msg_group_id as group_id,wait_total,update_time')
                ->order(['update_time' => 'DESC'])
                ->select();

            //等待排序的用户信息
            $sortList = [
                'staff' => [],
                'member' => [],
                'group' => [],
            ];

            //读取消息组更新日期排序
            foreach($statistics as $statisticItem){

                foreach($list as $key => $item){
                    foreach($item as $k => $data){
                        if($data['group_id'] == $statisticItem['group_id']){
                            $list[$key][$k]['count'] = $statisticItem['wait_total'];
                            $statisticsTotal[$key] += $statisticItem['wait_total'];
                            array_push($sortList[$key], $list[$key][$k]);
                        }
                    }
                }

            }
        }

        //删除 已读取消息组更新日期排序
        foreach($list as $k => $val){
            foreach($list[$k] as $key => $item){
                foreach($sortList[$k] as  $value){
                    if($value['id'] == $list[$k][$key]['id']){
                        unset($list[$k][$key]);
                    }
                }
            }
        }

        //将已读取消息组压入数组位置
        foreach($sortList as $key => $item){
            $len = count($item) - 1;
            foreach($item as $value){
                array_unshift($list[$key], $item[$len]);
                $len--;
            }
        }

        $list['statistics'] = $statisticsTotal;
    }

    private function getBranchcurrMember(){
        $parentBranchId = $this->getBranchId();
        $userId = $this->getUserId();
        $model = new SysUserModel();
        try{
            $currBranchId = $this->getCurrBranchId();
            //读取员工数组
            $list['staff'] = $model->alias('a')->join('sys_user_branch  AS b ON  a.id = b.user_id')
                ->where(['b.branch_id' => $currBranchId, 'b.user_id' => ['neq', $userId]])
                ->field("a.id,a.name, a.user_type,a.is_follow,a.comments,a.head_pic,a.staff_name")
                ->select();
        }catch(\Exception $e){
            $list['staff'] = [];
            $currBranchId = 0;
        }

        //读取服务商员工
        $list['member'] = $model->where(['user_type' => 2, 'branch_id' => $parentBranchId])->field("id,name,user_type,is_follow,comments,head_pic,staff_name")->select();
        $ids = array_merge(array_column($list['staff'], 'id'), array_column($list['member'], 'id'));
        $membsers = [];
        sort($ids);
        foreach($ids as $id){
            if($id == $userId){
                continue;
            }

            if($userId > $id){
                array_push($membsers, $id . '-' . $userId);
            }else{
                array_push($membsers, $userId . '-' . $id);
            }
        }

        $groups = MsgGroupModel::getInstance()->where(['type' => 10, 'member' => ['in', $membsers]])->field('id,member')->select();
        foreach($groups as $group){
            foreach($list as $k => $typeList){
                foreach($typeList as $key => $item){
                    $memberString = $item['id'] > $userId ? $userId . '-' . $item['id'] : $item['id'] . '-' .$userId;
                    if($group['member'] == $memberString){
                        $list[$k][$key]['group_id'] = $group['id'];
                    }
                }
            }
        }

        $list['memberIds'] = array_column($groups, 'id');

        return $list;
    }
    /**
     *  获取用户 所有的成员!
     * @return array|mixed
     */
    private function getBranchMemberAll(){
        $branchId = intval($this->getBranchId());
        $userId = $this->getUserId();
        if($branchId <= 0){
            return [];
        }

        $cacheKey = 'BranchMemberAll_' . $userId;
        $list = S($cacheKey);
        if(empty($list)){
            $list = [
                'staff'  => [],
                'member' => [],
                'memberIds' => []
            ];

            $subSQL1 = "SELECT `id`,`name`, `user_type`,`is_follow`,`comments`,`head_pic`,`staff_name`,
IF(`id` > {$userId}, CONCAT('{$userId}', '-', `id`), CONCAT(`id`, '-', '{$userId}')) AS `member`
FROM `sys_user` WHERE  `branch_id` = {$branchId} AND `user_type` > 0 AND  `user_type` < 6 AND id != {$userId}";

            $sql = " SELECT a.`id`,a.`name`, a.`user_type`,a.`is_follow`,a.`comments`,a.`head_pic`,a.`staff_name`,b.id AS `group_id`
 FROM ({$subSQL1}) AS a LEFT JOIN msg_group AS b ON a.member = b.member";
            $data = M()->query($sql);
            $list['memberIds'] = array_unique(array_column($data, 'group_id'));
            foreach($data as $item){
                $item['count'] = 0;
                if($item['user_type'] < 3){
                    array_push($list['staff'], $item);
                }else{
                    array_push($list['member'], $item);
                }
            }

            S($cacheKey, $list, self::ALL_MEMBER_CACHE_TIME);
        }

        return $list;
    }
}