<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;
use think\console\command\make\Model;

class  DistributionUserController extends DataController {

    protected function _parseFilter(&$filter){
        parent::_parsefilter($filter);
        $filter["a.user_type"] = array("neq", USER_TYPE_BUSINESS);
        $filter["a.branch_id"] = getBrowseBranchId();
        $search = I("post.search");
        if($search){
            $where['a.name'] = array("like","%$search%");
            $where['a.comments'] = array("like","%$search%");
            $where['a.staff_name'] = array("like","%$search%");
            $where['a.mobile'] = array("like","%$search%");
            $where['_logic'] = "or";
            $filter["_complex"] = $where;
        }
    }

    public function listAction(){
        $page_index = I("page/d", 1);
        $page_size = I("rows/d", 1024);
        $_order = array();
        $this->_parseOrder($_order);
        $_filter = array();
        $this->_parseFilter($_filter);
        $count = D(CONTROLLER_NAME)->setDacFilter("a")
            ->join("distribution_relation as b ON a.id = b.inviter_id")
            ->join("sys_user c on b.openid = c.openid")
            ->where($_filter)->group("b.inviter_id")->field("a.id")->select();
        $list = D(CONTROLLER_NAME)->setDacFilter("a")
            ->join("distribution_relation as b ON a.id = b.inviter_id")
            ->join("sys_user c on b.openid = c.openid")
            ->field("a.*,count(b.inviter_id) as member_num")
            ->where($_filter)->page($page_index, $page_size)
            ->order("member_num desc")->group("b.inviter_id")
            ->select();
        $this->_before_list($list);
        $result["total"] = count($count);
        $result["rows"] = $list;
        $this->responseJSON($result);
    }

    public function _before_list(&$list){
        parent::_before_list($list);
        $model = M("DistributionRelation");
        $income_model = M("DistributionIncome");
        foreach ($list as $k=>$v){
            $members = $model->alias('a')
                ->join("sys_user b on a.openid = b.openid")
                ->where("a.inviter_id = ".$v['id'])->field("b.id")->select();
            if($v['user_type'] == USER_TYPE_COMPANY_MANAGER){
                $list[$k]['name'] = $v['staff_name'];
            }else{
                $list[$k]['name'] = $v['comments'] == "" ? $v['name']:$v['comments'];
            }
            /*//下级数量
            $list[$k]['level_account'] = M("DistributionRelation a")
                ->join("left join sys_user user on a.openid = user.openid")->where("a.inviter_id = ".$v['id'])->count();*/
            if(!empty($members)){
                $member_ids = [];
                foreach ($members as $key => $val) {
                    array_push($member_ids, $val['id']);
                }
                $condition = [];
                $condition['user_id'] = array("in",$member_ids);
                //已解冻金额
                $condition['status'] = 1;
                $list[$k]['thaw_amount'] = $income_model->where($condition)->sum("commision");
                if($list[$k]['thaw_amount'] == "" or $list[$k]['thaw_amount'] == null){
                    $list[$k]['thaw_amount'] = 0;
                }
                //未解冻金额
                $condition['status'] = 0;
                $unthaw_amount = $income_model->where($condition)->sum("commision");
                $list[$k]['unthaw_amount'] = $unthaw_amount == "" ? 0 : $unthaw_amount;
                //总佣金
                $condition['status'] = array("lt",2);
                $result = $income_model->where($condition)->sum("commision");
                $list[$k]['total_commission'] = $result == "" ? 0 : $result;
                /*//无效金额
                $list[$k]['invalid_amount'] = $list[$k]['total_commission']-$list[$k]['thaw_amount']-$list[$k]['unthaw_amount'];*/
            }else{
                //$list[$k]['withdrawable_amount'] = 0;
                $list[$k]['unthaw_amount'] = 0;
                $list[$k]['thaw_amount'] = 0;
                $list[$k]['invalid_amount'] = 0;
                $list[$k]['total_commission'] = 0;
            }
        }
    }

    public function showLevelUserAction(){
        $this->assign("id",I("get.id"));
        $this->display();
    }

    public function levelUserListAction(){
        $id = I("get.id");
        if($id){
            $page_index = I("page/d", 1);
            $page_size = I("rows/d", 1024);
            $condition = [];
            if(I("post.ql-name")){
                $name = I("post.ql-name");
                $where['a.nickname'] = array("like","%".$name."%");
                $where['user.name'] = array("like","%".$name."%");
                $where['user.staff_name'] = array("like","%".$name."%");
                $where['user.querykey'] = array("like","%".$name."%");
                $where['user.comments'] = array("like","%".$name."%");
                $where['user.mobile'] = array("like","%".$name."%");
                $where['_logic']  = 'or';
                $condition['_complex'] = $where;
            }
            $condition['a.inviter_id'] = $id;
            if(I("post.income_state") == ""){
                //全部
                $count = M("DistributionRelation a")
                    ->join("sys_user user on a.openid = user.openid")
                    ->join("left join distribution_income c on user.id = c.user_id")
                    ->where($condition)->count("distinct user.id");
                $result = M("DistributionRelation a")
                    ->join("sys_user user on a.openid = user.openid")
                    ->join("left join distribution_income c on user.id = c.user_id")
                    ->where($condition)->group("user.id")
                    ->field("a.subscribe,a.unsubscribe_time,a.create_time,user.user_type,user.id,user.staff_name,user.comments,user.name,user.followed_at,user.mobile,a.nickname,a.headimgurl,user.is_follow,a.openid")
                    ->order("a.subscribe desc,a.subscribe_time desc")->page($page_index, $page_size)->select();
            }else{
                //已成交 income_state == 1
                $count = M("DistributionRelation a")
                    ->join("sys_user user on a.openid = user.openid")
                    ->join("distribution_income c on user.id = c.user_id")
                    ->where($condition)->field("user.id,a.openid")->distinct(true)->select();
                $result = M("DistributionRelation a")
                    ->join("sys_user user on a.openid = user.openid")
                    ->join(" distribution_income c on user.id = c.user_id")
                    ->where($condition)->distinct(true)
                    ->field("a.subscribe,a.unsubscribe_time,a.create_time,user.user_type,user.id,user.staff_name,user.comments,user.name,user.followed_at,user.mobile,a.nickname,a.headimgurl,user.is_follow,a.openid")
                    ->order("a.subscribe desc,a.subscribe_time desc")->page($page_index, $page_size)->select();
                if(I("post.income_state") == 0){
                    //通过openid去重
                    //$ids = array_column($count,"openid");
                    $ids = array_column($count,"id");
                    if($ids){
                        /*$id = "";
                        foreach($ids as $v){
                            $id = $id == "" ? "'$v'" : $id . ",'$v'";
                        }*/
                        $ids = implode(",",$ids);
                        //$condition['_string'] = "(user.openid not in ($id) or  user.id is NULL)";
                        $condition['_string'] = " user.id not in ($ids) ";
                    }
                    //未成交
                    $count = M("DistributionRelation a")
                        ->join("sys_user user on a.openid = user.openid")
                        ->where($condition)->group("user.id")->field("a.id")->select();
                    $result = M("DistributionRelation a")
                        ->join("sys_user user on a.openid = user.openid")
                        ->where($condition)->group("user.id")
                        ->field("a.subscribe,a.unsubscribe_time,a.create_time,user.user_type,user.id,user.staff_name,user.comments,user.name,user.followed_at,user.mobile,a.nickname,a.headimgurl,user.is_follow")
                        ->order("a.subscribe desc,a.subscribe_time desc")->page($page_index, $page_size)->select();
                }
            }
            foreach ($result as $k=>$v) {
                if($v['user_type'] == USER_TYPE_COMPANY_MANAGER){
                    $result[$k]['name'] = $v['staff_name'];
                }else{
                    $result[$k]['name'] = $v['comments'] == "" ? ($v['name'] == "" ? $v['nickname'] : $v['name']):$v['comments'];
                }
                $result[$k]['subscribe_time'] = ($v['subscribe']==0 || $v['followed_at']==0)?"-":date("Y-m-d H:i:s",$v['followed_at']);
                if($v['id']){
                    $commission = M("DistributionIncome")->where("status < 2 and user_id = ".$v['id'])->sum("commision");
                }else{
                    $commission = 0;
                }
                //贡献佣金
                $result[$k]['commission'] = $commission == "" ? 0 :$commission;
                if($v['mobile']){
                    $result[$k]['region'] = $this->getMobileArea($v['mobile']);
                }
            }
            $res['rows'] = $result;
            if(I("post.income_state") != ""){
                $res['total'] = count($count);
            }else{
                $res['total'] = $count;
            }
            $this->ajaxReturn($res);
        }
    }

    //获取手机号码归属地
    function getMobileArea($mobile){
        $url = 'http://sp0.baidu.com/8aQDcjqpAAV3otqbppnN2DJv/api.php?query={'.$mobile.'}&resource_id=6004&ie=utf8&oe=utf8&format=json';
        $phone_json = file_get_contents($url);
        $phone_array = json_decode($phone_json,true);
        return $phone_array['data'][0]['prov'].$phone_array['data'][0]['city'];
    }

    public function distributionRegularAction(){
        $this->display();
    }

}