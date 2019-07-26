<?php

namespace EShop\Controller;

use Common\Lib\Controller\MsgBaseController;
/**
 * 用户聊天，谈话控制器
 * Class ConversationController
 * @package EShop\Controller
 */
class TalksController extends MsgBaseController{

    public function indexAction() {
        $statistics = $this->statisticsAction(true, true);
        $this->assign('statistics', $statistics);
        $this->assign('currBranchId', session('currBranchId'));
        $this->assign('title', "消息管理");
        $this->display();
    }

    public function staffAction(){
        $this->assign('title', '员工沟通');
        $list = parent::MemberAction(true);
        $staff = $list['staff'];
        if(IS_AJAX && IS_POST){
            $name = I('post.name');
            if(!empty($name)){
                $searchList = [];
                foreach($staff as $item){
//                    if(strstr($item['name'], $name) !== false){
//                        $bool = false;
//                    }
//
//                    if(strstr($item['comments'], $name) !== false){
//                        $bool = false;
//                    }

                    if(strstr($item['staff_name'], $name) !== false){
                       array_push($searchList, $item);
                        continue;
                    }
                }

                $staff = $searchList;
            }

            return $this->ajaxReturn($staff);
        }

        $staff = json_encode($staff, true);
        $this->assign('staff', $staff);
        $this->display();
    }
    /**
     *会员列表
     */
    public function memberAction(){
        $title = '客户沟通';
        if($this->getUserType() > 3){
            $title = '服务商';
        }

        $this->assign('title', $title);
        $list = parent::MemberAction(true);
        $staff = $list['member'];
        if(IS_AJAX && IS_POST){
            $name = I('post.name');
            if(!empty($name)){
                $searchList = [];
                foreach($staff as $item){
                    if(strstr($item['name'], $name) !== false){
                        array_push($searchList, $item);
                        continue;
                    }

                    if(strstr($item['comments'], $name) !== false){
                        array_push($searchList, $item);
                        continue;
                    }
                }

                $staff = $searchList;
            }

            return $this->ajaxReturn($staff);
        }

        $staff = json_encode($staff, true);
        $this->assign('member', $staff);
        $this->display();
    }
    /**
     * 群了列表
     */
    public function groupAction(){
        $list = parent::getGroupAction(true);

        $this->assign('list', json_encode($list, true));
        $this->assign('title', "群聊");
        $this->display();
    }
    /**
     * 群聊会员
     * @param $group_id
     * @return mixed
     */
    public function groupMemberAction($group_id){
        $group_id = intval($group_id);
        if($group_id < 1){
            return $this->error('消息分组丢失!');
        }

        $userId = $this->getUserId();
        $group = $this->getGroupById($group_id);
        $isLord = $group['lord_id'] == $userId;
        $list = $this->getGroupMemberAction($group_id, true);
        $this->assign('list', $list);
        $this->assign('isLord', $isLord);
        $this->assign('groupId', $group_id);
        $this->display();
    }

    public function addgroupmemberAction($groupId = null){
        $this->assign('groupId', $groupId);
        $this->display();
    }
    /**
     *系统统计界面
     */
    public function systemAction(){
        $statistics = $this->statisticsAction(true);
        $this->assign('statistics', $statistics);
        $this->display();
    }
    /**
     * 系统信息界面
     * @param $type
     */
    public function system_detailsAction($type){
        $type  = $type == 10 ? 10 : 20;
        $title = $type == 10 ? '系统通知' : '迭代通知';
        $this->assign('title', $title);
        $list = $this->getSysMessageAction($type, true);
        $this->assign('list', json_encode($list, true));
        $this->display();
    }
    /**
     * 消息列表
     * @param $group_id
     */
    public function messageAction($group_id, $type = null){
        $group_id = intval($group_id);
        if($group_id < 1){
            return $this->error('消息分组丢失!');
        }

        $group = $this->getGroupById($group_id);
        if(empty($group)){
            return $this->error('会话不存在!');
        }

        if($type != 20){
            $userId = $this->getUserId();
            if($group['type'] == 20){
                $name = $group['name'];
            }else{
                $acceptId = $group['members'][0] == $userId ?  $group['members'][1] :  $group['members'][0];
                $this->assign('acceptId', $acceptId);
                $acceptInfo = M('SysUser')->where(['id' => $acceptId])->field('user_type,`name`,`comments`,`staff_name`')->find();
                if($acceptInfo['user_type']  < 4){
                    if($this->getUserType() > 3){
                        $acceptName = empty($acceptInfo['staff_name']) ? $acceptInfo['comments'] . '(' . $acceptInfo['name'] .')':  $acceptInfo['staff_name'] . ( empty($acceptInfo['comments']) ? '(' . $acceptInfo['name'] .')' : '(' . $acceptInfo['comments'] .')');
                    }else{
                        $acceptName = empty($acceptInfo['staff_name']) ? $acceptInfo['comments'] . '(' . $acceptInfo['name'] .')': $acceptInfo['staff_name'];
                    }
                }else{
                    $acceptName = empty($acceptInfo['comments']) ? $acceptInfo['name'] : $acceptInfo['comments'] . '(' . $acceptInfo['name'] .')';
                }

                $name = '正在与  <span style="color: #6fb1df">' . $acceptName . '</span>  对话';
            }
        }

        $list = $this->getMessageAction($group_id, $type, true);
        $this->assign('list', json_encode($list, true));
        $this->assign('userId', $userId);
        $this->assign('groupId', $group_id);
        $this->assign('type', $type);
        $this->assign('title', $name);
        $this->assign('group', $group);
        $this->display();
    }

    public function annexAction($group_id){
        $group_id = intval($group_id);
        if($group_id < 1){
            return $this->error('消息分组丢失!');
        }

        $list = $this->getMessageAction($group_id, 20, true);
        $files = [];
        foreach($list as $item){
            $contents = json_decode($item['contents'], true);
            $date = date('Y-m-d H:i:s', $item['create_time']);
            foreach($contents as $key => $content){
                $contents[$key]['create_time'] = $date;
            }
            $files = array_merge($files, $contents);
        }

        $this->assign('list', json_encode($files, true));
        $this->assign('title', '查看附件');
        $this->display();
    }

    public function _initialize(){
        checkLogin();
        if(IS_GET) {
            $this->vesion = '100';
        }

        $isStaff = false;
        if($this->getUserType() < 4){
            $isStaff = true;
        }

        $this->assign('isStaff', $isStaff);
        $this->assign('userId', $this->getUserId());
        $this->assign('branchId', $this->getBranchId());
    }

    protected function getUserId(){
        $userId =  session('user_id');
        if(!$userId){
            E("未登录");
        }

        return $userId;
    }

    protected function getBranchId(){
        $branchId = getBrowseBranchId();
        if(!$branchId){
            E("公司丢失!");
        }

        return $branchId;
    }

    protected function getUserType(){
        $userType = session("user_type");
        if(!$userType){
            E("用户类型丢失!");
        }

        return $userType;
    }

    protected function getCurrBranchId(){
        $currBranchId = session('currBranchId');
        if(!$currBranchId){
            E("当前商户丢失!");
        }

        return $currBranchId;
    }
}
