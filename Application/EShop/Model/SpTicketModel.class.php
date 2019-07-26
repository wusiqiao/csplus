<?php

namespace EShop\Model;

use Think\Model;

class SpTicketModel extends Model {

    const  SERVICE_HAVA_ACCESS	=	'1';//未使用 代金券
    const  SERVICE_USE_ALREADY	=	'2';//已使用 代金券
    const  SERVICE_OVERDUE		=	'3';//已过期 代金券
    const  SERVICE_INVALID      =   '5';//已失效
    protected $_MODEL = 'SysUser';
    public function getUserTicketLists($type,$start,$num){
        $time	=	time();
        $where['a.branch_id'] = getBrowseBranchId();
        switch ($type) {
            case self::SERVICE_HAVA_ACCESS:
                $where['ts.mobile']	=	$_SESSION['mobile'];
                $where["ts.state"]	=	TICKET_CARD_STATE_GETED;
                $where['a.is_over'] =  array('in',[0,1]);
                $where["ts.ticket_begin_date"]	=	array("elt",$time);
                $where["ts.ticket_end_date"]	=	array("egt",$time);
                $where['a.activity_type']	=	ACTIVITY_TYPE_SERVICE;
                $template			=	"template_service_voucher";
                $add				=	"";
                $order = "ts.ticket_end_date asc";
                break;
            case self::SERVICE_USE_ALREADY:
                $where['ts.mobile']	=	$_SESSION['mobile'];
                $where["ts.state"]	=	TICKET_CARD_STATE_USED;
                $where['a.activity_type']	=	ACTIVITY_TYPE_SERVICE;
                $template			=	"template_service_voucher";
                $add				=	"";
                $order = "ts.used_time desc";
                break;
            case self::SERVICE_OVERDUE:
                $where['ts.mobile']	=	$_SESSION['mobile'];
                $where["ts.ticket_end_date"]	=	array("lt",$time);
                $where["ts.state"]	=	TICKET_CARD_STATE_GETED;
                $where['a.activity_type']	=	ACTIVITY_TYPE_SERVICE;
                $template			=	"template_service_voucher";
                $add				=	"";
                $order = "ts.ticket_end_date desc";
                break;
            case self::SERVICE_INVALID:
                $where['ts.mobile']	=	$_SESSION['mobile'];
                $where["a.is_over"]	=	2;
                $where["ts.state"]	=	TICKET_CARD_STATE_GETED;
                $where['a.activity_type']	=	ACTIVITY_TYPE_SERVICE;
                $template			=	"template_service_voucher";
                $add				=	"";
                $order = "ts.ticket_end_date desc";
                break;
            default:

                break;
        }
        $field	=	"ts.id,ts.state,t.reduce_cost,a.is_scope,a.scope,ts.code,ts.activity_id,ts.give_user,ts.ticket_id,t.least_cost,ts.used_time,FROM_UNIXTIME(ts.ticket_begin_date,'%Y.%m.%d') as ticket_begin_date,FROM_UNIXTIME(ts.ticket_end_date,'%Y.%m.%d') as ticket_end_date,a.can_give_friend,ts.mobile";
        $result_ALL	=	M("SpTicketStock ts")
                        ->field("ts.id")
                        ->join("sp_ticket           t on t.id           = ts.ticket_id")
                        ->join("sp_activity_ticket at on at.ticket_id   = t.id")
                        ->join("sp_activity         a on a.id           = at.activity_id")
                        ->where($where)
                        ->order($order)
                        ->group("ts.id")
                        ->select();
        $result	    =	M("SpTicketStock ts")	->field($field)
                        ->join("sp_ticket           t on t.id           = ts.ticket_id")
                        ->join("sp_activity_ticket at on at.ticket_id   = t.id")
                        ->join("sp_activity         a on a.id           = at.activity_id")
                        ->where($where)
                        ->order($order)
                        ->group("ts.id")
                        ->limit($start,$num)
                        ->select();

        $data['total_count']	=	count($result_ALL);
        $data['template']		=	$template;
        foreach ($result as $key => $value) {
            if ($template == 'template_service_voucher') {
                if($type == self::SERVICE_HAVA_ACCESS){
                    $user_id			=	$value['user_id'];
                    $add				=	"";
                    $result[$key]['add']	=	$add;
                }else{
                    $result[$key]['add']	=	$add;
                }
                if($type == self::SERVICE_USE_ALREADY){
                    $result[$key]['ticket_end_view'] = $value['used_time'] ?  date('Y.m.d',$value['used_time']).'使用' : $value['ticket_end_date'].'到期';
                }
            }
            if ($value['is_scope'] == 1){
                $result[$key]['show_type'] ='blue';
                $condition['id'] = array('in',$value['scope']);
                $product_ids = M('ComProduct')->where($condition)->getField('product_title',true);
                $result[$key]['show_scope'] = '仅限'.implode(',',$product_ids).'使用';
                $result[$key]['jump_url'] = '/index/search_result/keyword/SCOPE@'.$value['code'];
            }else{
                $result[$key]['show_type'] ='red';
                $result[$key]['show_scope'] = '店铺通用';
                $result[$key]['jump_url'] = '/index/search_result';
            }
            $result[$key]['ticket_date']	=	$value['ticket_begin_date'].'-'.$value['ticket_end_date'];
        }
        $data['count']	=	count($result);
        $data['list']=  $result;
        return $data;
    }
    //根据订单号取消红包券和代金券的使用
    public function setObjertOrderCloseVoucher($order_id){
        $ticket_id	=	M("SpTicketStock")->where(" state = ".TICKET_CARD_STATE_USED."  and object_id = ".$order_id.' and use_object = '.TICKET_OBJECT_ORDER)->getField("id",true);
        return $ticket_id;
    }
    /*
     * 服务商可领取的代金券信息
     * return Data
     * Author: Lynn
     * Start: June 9th
     */
    public function getServiceMayReceiveTicket($field){
        $time		=	time();
        $branch_id  =   getBrowseBranchId();
        $where['a.branch_id']			=	$branch_id;
        $where['a.is_over']				=	0;
        $where['a.activity_begin_date']	=	array('elt',$time);
        $where['a.activity_end_date']	=	array('gt',$time);
        $where['at.remain']				=	array('gt',0);
        $prefix 	                    = 	'sp_';
        $ticket_list                    =	M("SpActivity a")
                                                    ->field($field)
                                                    ->join($prefix."activity_ticket at on at.activity_id = a.id")
                                                    ->join($prefix."ticket t on t.id = at.ticket_id")
                                                    ->where($where)
                                                    ->select();
        //转换成array
        return $ticket_list;
    }
    /*
     * 服务商可领取的代金券信息
     * return Data
     * Author: Lynn
     * Start: June 9th
     */
    public function getServiceCreateVoucherCount(){
        $branch_id = getBrowseBranchId();
        $activity_count = M('SpActivity')->where('branch_id = '.$branch_id)->count();
        return $activity_count;
    }
    /*
 * 判断代金券库存中是否还有剩余的券
 * return Data
 * Author: Lynn
 * Start: June 9th
 */
    public function getIsTicketStockSurplus($activity_id){
        $count	=	M("SpTicketStock")->where("state = 0 and activity_id = ".$activity_id)->count();
        if($count > 0){
            return true;
        }else{
            return false;
        }
    }
    /*
 * 返回用户是否可领取的指定代金券
 * return Data
 * Author: Lynn
 * Start: June 9th
 */
    public function getUserMayReceiveTicket($activity_id){
        define("DONT_LOGIN", 0);//未登录
        define("TICKET_ALREADY_RECEIVE", 1);//已领取
        define("TICKET_SURE_RECEIVE", 2);//可以领取
        define("LOGIN_USER_IS_SERVICE", 3);//登录的用户是该服务商 -- 不能领自己的商店的优惠券
        define("TICKET_IS_OVER", 4);//该代金券是否已经被领取完了
        define("IS_SET_MOBILE", 5);//该登录用户是否设定手机号码
        //判断是否已登录
        if(!$_SESSION['user_id']){
            return DONT_LOGIN;
        }
        if(!GetUserIsSetMobile()){
            return IS_SET_MOBILE;
        }
        $user_id	=	$_SESSION['user_id'];
        $branch_id  =   getBrowseBranchId();
        //判断登录的用户是否就是这个服务商
        $if_admin	=	handleIsManager();
        if($if_admin){
            return LOGIN_USER_IS_SERVICE;
        }
        //判断该代金券是否还有剩余
        $ticket_is_surplus	=	$this->getIsTicketStockSurplus($activity_id);
        if(!$ticket_is_surplus){
            return TICKET_IS_OVER;
        }
        //已领取个数
        $count	=	M("SpTicketStock")->where("mobile = '".$_SESSION['mobile']."' and activity_id =".$activity_id." and state > ".TICKET_CARD_STATE_NORMAL)->count();
        $user_get_limit	=	M("SpActivity")->where("id = ".$activity_id)->getField('user_get_limit');
        if($count < $user_get_limit){
            return	TICKET_SURE_RECEIVE;//可以领取
        }else{
            return  TICKET_ALREADY_RECEIVE;//已领取
        }
    }
    /*
 * 领取代金券
 * return Data
 * Author: Lynn
 * Start: June 9th
 */
    public function userReceiveTicket($activity_id){
        $user_id	=	$_SESSION['user_id'];
        $field		=	't.reduce_cost,t.least_cost,a.user_get_limit';
        $activity	=	$this->getOneServiceTicket($activity_id,$field);
        $ticket_count=	M("SpTicketStock")->where("state = ".TICKET_CARD_STATE_NORMAL." and activity_id = ".$activity_id)->count();
        $count		=	min($ticket_count,$activity['user_get_limit']);
        $ticket_list=	M("SpTicketStock")->where("state = ".TICKET_CARD_STATE_NORMAL." and activity_id = ".$activity_id)->limit(0,$count)->order("id asc")->getField("id",true);
        $save['mobile']	    =	$_SESSION['mobile'];
        $save['get_time']	=	time();
        $save['state']		=	TICKET_CARD_STATE_GETED;
        foreach ($ticket_list as $key => $value) {
            $where['id']	=	$value;
            M("SpTicketStock")->where($where)->save($save);
        }
        M("SpActivityTicket")->where('activity_id='.$activity_id)->setDec('remain',$count);//减去已领取的券数
        $data['count']	=	$count;
        $data['reduce_cost']	=	$activity['reduce_cost'];
        $data['least_cost']	=	$activity['least_cost'];
        return $data;
    }
    /*
     * 单个代金券信息
     * return Data
     * Author: Lynn
     * Start: June 9th
     */
    public function getOneServiceTicket($activity_id,$field){
        $prefix 	= 	'sp_';
        $ticket_list=	M("SpActivity a")	->field($field)
            ->join($prefix."activity_ticket at on at.activity_id = a.id")
            ->join($prefix."ticket t on t.id = at.ticket_id")
            ->where("a.id =".$activity_id)
            ->find();
        //转换成array
        return $ticket_list;
    }
    /*
     * 单个代金券信息
     * return Data
     * Author: Lynn
     * Start: June 9th
     */
    public function getOneTicket($ticket_id,$field){
        $prefix 	= 	'sp_';
        $ticket_list=	M("SpTicketStock ts")	->field($field)
            ->join($prefix."activity a on a.id = ts.activity_id")
            ->join($prefix."activity_ticket at on at.activity_id = a.id")
            ->join($prefix."ticket t on t.id = at.ticket_id")
            ->where("ts.id =".$ticket_id)
            ->find();
        //转换成array
        return $ticket_list;
    }
    /*
     * name 获取当前的裂变红包活动
     */
    public function getActivityFissionData($type = ACTIVITY_TYPE_SERVICE,$product_id = 0){
        $time							= time();
        $where['activity_type']			= $type;//活动类型为裂变红包
        $where['activity_begin_date'] 	= array('elt',$time);//开始时间小于等于当前时间
        $where['activity_end_date']		= array('egt',$time); //结束时间大于等于当前时间
        $where['is_over']				= 0;//活动没有结束
        $where['branch_id']             = getBrowseBranchId();
        if ($product_id == 0){
            $where['is_scope']				= 0;//通用
        }else{
            $where['is_scope']				= 1;
            $where['_string'] .= ' FIND_IN_SET('.$product_id.',scope) ';
        }

        $activity		=	M("SpActivity")->where($where)->find();//获取活动信息
        return $activity;
    }
    //登录时,判断该用户是否已经领取了 普通红包 currency 范围红包 scope
    public function setUserIsReceiveCommonly($product_id = 0){
        $user_id	=	session('user_id');
        //判断是否填写手机号码
        if(GetUserIsSetMobile()){
            $mobile = session('mobile');
            //判断是否已经领取
            $condition['a.activity_type']	=	ACTIVITY_TYPE_SERVICE;
            $condition['a.activity_begin_date'] = array('elt',time());
            $condition['a.activity_end_date'] = array('egt',time());
            $condition['a.is_over'] = 0;
            $condition['a.branch_id'] = getBrowseBranchId();
            $condition['_string'] =	"((ts.mobile = '".$mobile."' and ts.give_user IS NULL ) or (ts.give_user = '".$mobile."'))";
            if($product_id == 0){
                $condition['a.is_scope'] = 0;
            }else{
                $condition['a.is_scope'] = 1;
                $condition['_string'] .= ' and FIND_IN_SET('.$product_id.',scope) ';
            }
            $is_receive	=	M("SpActivity a")
                            ->join("sp_activity_ticket at on at.activity_id = a.id")
                            ->join("sp_ticket_stock ts on ts.ticket_id = at.ticket_id")
                            ->where($condition)
                            ->select();
        }else{
            $is_receive = false;
        }
        if($is_receive){
            return false;
        }else{
            //获取当前普通红包活动信息
            $activity	=	$this->getActivityFissionData(ACTIVITY_TYPE_SERVICE,$product_id);
            if($activity){
                //获取当前普通红包中卷库里是否还有没有领取的卷
                $where['activity_id']	=	$activity['id'];
                $where['state']			=	TICKET_CARD_STATE_NORMAL;
                $ticket_count			=	M("SpTicketStock")->where($where)->count();
                if($ticket_count < $activity['user_get_limit'] || $ticket_count == 0){
                    return false;
                }else{
                    //获取该用户领取的数据
                    $field = "t.reduce_cost,t.least_cost,a.id";
                    $prefix 	= 	'sp_';
                    $ticket_list=	M("SpActivity a")	->field($field)
                                    ->join($prefix."activity_ticket at on at.activity_id = a.id")
                                    ->join($prefix."ticket t on t.id = at.ticket_id")
                                    ->where("a.id =".$activity['id'])
                                    ->find();
                    return $ticket_list;
                }
            }else{
                return false;
            }
        }
    }
}
