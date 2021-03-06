<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/6
 * Time: 11:59
 */
namespace ESAdmin\Model;

use Common\Lib\Model\BillModel;

class SpTicketModel extends BillModel {
    protected $_link = array(
        "SpActivityTicket" => array(
            "join_name" => "LEFT",
            'class_name' => "SpActivityTicket",
            'foreign_key' => 'id',
            'mapping_name' => 'at',
            'mapping_fields' => 'total,remain',
            "mapping_key" => "ticket_id"
        ),
        "SpActivity" => array(
            "join_name" => "LEFT",
            'class_name' => "SpActivity",
            'foreign_key' => 'at.activity_id',
            'mapping_name' => 'ac',
            'mapping_fields' => 'user_get_limit,ticket_begin_date,ticket_end_date,is_over,is_scope,scope',
            "mapping_key" => "id"
        )
    );
    protected function _before_delete($options)
    {
        parent::_before_delete($options); // TODO: Change the autogenerated stub
        //条件 有人领取的不能删除
        $tickets_ids = $options['where']['id'][1];
        $condition['ticket_id'] = array('in',$tickets_ids);
        $activity_ticket = M('SpActivityTicket')->field('total,remain')->where($condition)->select();
        foreach ($activity_ticket as $key =>$val){
            if($val['total'] != $val['remain']){
                E("所选择优惠卷已有人领取,不能删除!");
                return false;
            }
        }
    }
    protected function _after_delete($data, $options)
    {
//        parent::_after_delete($data, $options); // TODO: Change the autogenerated stub
//        var_dump($data);die;
        foreach ($data['id'][1] as $key=>$val){
            //删除其他表
            $sat = M('SpActivityTicket')->field('activity_id,ticket_id')->where('ticket_id = '.$val)->find();
            $this->deleteTicketStock($sat);
            $this->deleteActivityTicket($sat);
            $this->deleteActivity(['id'=>$sat['activity_id']]);
        }

    }

    protected function _before_update(&$data, $options)
    {
        parent::_before_update($data, $options);
        //获取当前是否有人领取
        $connector = M('SpActivityTicket')
//                    ->field('total,remain,activity_id')
                    ->where('ticket_id = '.$data['id'])
                    ->find();
        //模拟数据
        $postdata = I('post.');
        if ($connector['total'] != $connector['remain']) {
            $activity = M('SpActivity')->where('id = '.$connector['activity_id'])->find();
            $ticket = M('SpTicket')->where('id = '.$connector['ticket_id'])->find();
            if ($postdata['is_scope'] == 1) {
                sort($postdata['scope']);
            }
            if ($ticket['reduce_cost'] != $postdata['reduce_cost'] || $ticket['least_cost'] != $postdata['least_cost'] || $postdata['total'] != $connector['total'] ||
                $postdata['user_get_limit'] != $activity['user_get_limit'] || $postdata['is_scope'] != $activity['is_scope'] || (implode(',',$postdata['scope']) != $activity['scope'] && $postdata['is_scope'] == 1)||
                strtotime($postdata['ticket_begin_date']) != $activity['ticket_begin_date'] || strtotime($postdata['ticket_end_date']) != $activity['ticket_end_date']
            ){
                E("该优惠券已有人领取不能修改,您可以选择失效选项后重新创建优惠券!");
                return false;
            }
        }
        $data['updated_at'] = time();
        if($postdata['is_scope'] == 0 && $postdata['is_over'] == 0 && $this->hasRuningActivityCurrency($data,$postdata)){
            E("同一时间段不能同时开始两种优惠活动!");
            return false;
        }
        if($postdata['is_scope'] == 1 && $postdata['is_over'] == 0 && $hasTitle = $this->hasRuningActivityScope($data,$postdata)){
            $error = '';
            foreach ($hasTitle as $key => $val){
                $error .= ' '.$val.' ';
            }
            E($error."在该时间段已有优惠券活动进行中!");
            return false;
        }
    }
    protected function _before_insert(&$data, $options)
    {
        parent::_before_insert($data, $options); // TODO: Change the autogenerated stub
        //判断是否有通用券在同一时间段存在开启状态
        $postdata = I('post.');
        if($postdata['is_scope'] == 0 && $postdata['is_over'] == 0 && $this->hasRuningActivityCurrency($data,$postdata)){
            E("同一时间段不能同时开始两种优惠活动!");
            return false;
        }
        if($postdata['is_scope'] == 1 && $postdata['is_over'] == 0 && $this->hasRuningActivityScope($data,$postdata)){
            $hasTitle = $this->hasRuningActivityScope($data,$postdata);
            $error = '';
            foreach ($hasTitle as $key => $val){
                $error .= ' '.$val.' ';
            }
            E($error."在该时间段已有优惠券活动进行中!");
            return false;
        }
    }

    protected function _after_update($data, $options)
    {
        parent::_after_update($data, $options); // TODO: Change the autogenerated stub
        $data['total'] = I('post.total');
        $data['user_get_limit'] = I('post.user_get_limit');
        $data['ticket_begin_date'] = strtotime(I('post.ticket_begin_date'));
        $data['ticket_end_date'] = strtotime(I('post.ticket_end_date'));
        $data['is_over'] = I('post.is_over');
        $data['is_scope'] = I('post.is_scope');
        if ($data['is_scope'] == 1){
            $scope = I('post.scope');
            sort($scope);
            $data['scope'] = implode(',',$scope);
        }

        //获取之前的数据
        $old_data = $this->getActivityEditData($data['id']);
        $options  = array(
            'activity_id' => $old_data['activity_id'],
            'sat_id' => $old_data['sat_id']
        );
        unset($old_data['activity_id']);
        unset($old_data['sat_id']);
        //如果ticket_begin_date ticket_end_date total 有修改的话
        if(array_intersect($old_data,$data) != $old_data || $data['is_over'] != $old_data['is_over'] ){
            //总数是否改变
            $this->HandleUpdateDateTotal($data,$old_data,$options);
            $this->HandleUpdateTicketTotal($data,$old_data,$options);
        }

    }

    protected function _after_insert($data, $options)
    {
        parent::_after_insert($data, $options); // TODO: Change the autogenerated stub
        $data['total'] = I('post.total');
        $data['user_get_limit'] = I('post.user_get_limit');
        $data['ticket_begin_date'] = strtotime(I('post.ticket_begin_date'));
        $data['ticket_end_date'] = strtotime(I('post.ticket_end_date'));
        $data['is_over'] = I('post.is_over');
        $data['is_scope'] = I('post.is_scope');
        if ($data['is_scope'] == 1){
            $scope = I('post.scope');
            sort($scope);
            $data['scope'] = implode(',',$scope);
        }
        $this->HandleInsertActivity($data);
        $this->HandleInsertActivityTicket($data);
        $this->HandleInsertTicketStock($data);
    }
    /*
     * ticket添加时,同时添加一下三张表
     */
    protected function HandleInsertActivity(&$data){
        $activity = array();
        $activity['branch_id']              = $data['branch_id'];
        $activity['activity_begin_date']    = $data['ticket_begin_date'];
        $activity['activity_end_date']      = $data['ticket_end_date'];
        $activity['ticket_begin_date']      = $data['ticket_begin_date'];
        $activity['ticket_end_date']        = $data['ticket_end_date'];
        $activity['user_get_limit']         = $data['user_get_limit'];
        $activity['is_over']                = $data['is_over'];
        $activity['is_scope']               = $data['is_scope'];
        $activity['can_give_friend']        = 0; //默认不转赠
        if (isset($data['scope'])){$activity['scope'] = $data['scope'];}
        $activity['activity_type']          = ACTIVITY_TYPE_SERVICE; //活动类型代金券
        $activity_id                        = M("SpActivity")->add($activity);
        $data['activity_id']                = $activity_id;
    }
    protected function HandleInsertActivityTicket($data){
        $activity_ticket = array();
        $activity_ticket['activity_id']     = $data['activity_id'];
        $activity_ticket['ticket_id']       = $data['id'];
        $activity_ticket['total']           = $data['total'];
        $activity_ticket['remain']          = $data['total'];
        M("SpActivityTicket")->add($activity_ticket);
    }
    protected function HandleInsertTicketStock($data){
        $ticket_stock                       = array();
        $ticket_stock_all                   = array();
        $ticket_stock_count                 = min(TICKET_MIN_COUNT, $data['total']); //默认最小生成为100
        $ticket_stock['activity_id']        = $data['activity_id'];
        $ticket_stock['ticket_id']          = $data['id'];
        $ticket_stock['state']              = TICKET_CARD_STATE_NORMAL;
        $ticket_stock['ticket_begin_date']  = $data['ticket_begin_date'];
        $ticket_stock['ticket_end_date']    = $data['ticket_end_date'];
        for ($i = 0; $i < $ticket_stock_count; $i++) {
            $ticket_stock['code'] = get_ticket_code_cuid();
            $ticket_stock_all[]   = $ticket_stock;
        }
        M("SpTicketStock")->addAll($ticket_stock_all);
    }
    /*
     * 代金卷修改
     */
    protected function HandleUpdateTicketTotal($data,$old_data,$options){
        if ($data['total'] != $old_data['total']){
            $save['id']	    =	$options['sat_id'];
            $save['total']	=	$data['total'];
            $save['remain']	=	$data['total'];
            M("SpActivityTicket")->save($save);
            if($data['ticket_begin_date'] == $old_data['ticket_begin_date'] && $data['ticket_end_date'] == $old_data['ticket_end_date']){
                if($data['total'] < TICKET_MIN_COUNT || $old_data['total'] < TICKET_MIN_COUNT){
                    if($data['total'] - $old_data['total'] > 0){
                        $add_count = $data['total'] >= TICKET_MIN_COUNT ?
                                            (TICKET_MIN_COUNT - $old_data['total']):
                                            ($data['total'] - $old_data['total']);
                    }else{
                        $remove_count = $old_data['total'] >= TICKET_MIN_COUNT ?
                                            (TICKET_MIN_COUNT - $data['total']):
                                            ($old_data['total'] - $data['total']);
                    }
                    if($add_count > 0){
                        $add['state']		=	TICKET_CARD_STATE_NORMAL;
                        $add['ticket_id']   =   $data['id'];
                        $add['activity_id'] =   $options['activity_id'];
                        $add['ticket_begin_date']  = $data['ticket_begin_date'];
                        $add['ticket_end_date']    = $data['ticket_end_date'];
                        $all                =   array();
                        for ($i=0; $i < $add_count; $i++) {
                            $add['code']	=	get_ticket_code_cuid();
                            $all[]          =   $add;
                        }
                        $this->addTicketStockAll($all);
                    }elseif ($remove_count > 0){
                        $stock_id = $this->getStockId($options['activity_id']);
                        $start_slice = count($stock_id) - $remove_count;
                        $del_id   = array_slice($stock_id,$start_slice);
                        $this->removeTicketStock($del_id);
                    }
                }
            }
        }
    }
    protected function HandleUpdateDateTotal($data,$old_data,$options){
        if ($data['ticket_begin_date'] != $old_data['ticket_begin_date'] || $data['ticket_end_date'] != $old_data['ticket_end_date'] || $data['user_get_limit'] != $old_data['user_get_limit'] || $data['is_over'] != $old_data['is_over'] || $data['is_scope'] != $old_data['is_scope'] || $data['scope'] != $old_data['scope']) {
            $save_activity['id']       =  $options['activity_id'];
            if($data['is_over'] != $old_data['is_over']){
                $save_activity['is_over']       =  $data['is_over'];
            }
            if($data['is_scope'] != $old_data['is_scope'] || ( $data['is_scope'] == 1 && $data['scope'] != $old_data['scope'])){
                $save_activity['is_scope'] = $data['is_scope'];
                if ($data['is_scope'] == 0){
                    $save_activity['scope'] = null;
                }else{
                    $save_activity['scope'] = $data['scope'];
                }
            }
            if ($data['ticket_begin_date'] != $old_data['ticket_begin_date'] || $data['ticket_end_date'] != $old_data['ticket_end_date']) {
                $save_activity['ticket_begin_date']     = $data['ticket_begin_date'];
                $save_activity['ticket_end_date']       = $data['ticket_end_date'];
                $save_activity['activity_begin_date']   = $data['ticket_begin_date'];
                $save_activity['activity_end_date']     = $data['ticket_end_date'];
                if ($data['total'] != $old_data['total'] && ($data['total'] < TICKET_MIN_COUNT || $old_data['total'] < TICKET_MIN_COUNT)) {
                    //如果总数有修改的话,删除ticket_stock里面的信息重新添加
                    $save_ts['ticket_begin_date']       = $data['ticket_begin_date'];
                    $save_ts['ticket_end_date']         = $data['ticket_end_date'];
                    $save_ts['activity_id']             = $options['activity_id'];
                    $save_ts['ticket_id']               = $data['id'];
                    $save_ts['total']                   = $data['total'];
                    $this->delUnionAddFromTicketStock($save_ts); //删除添加操作
                } else {
                    //如果总数没有修改的话,修改ticket_Stock中的时间
                    $where['id']                  = array('in',$this->getStockId($options['activity_id']));
                    $save_ts['ticket_begin_date']   = $data['ticket_begin_date'];
                    $save_ts['ticket_end_date']     = $data['ticket_end_date'];
                    M("SpTicketStock")->where($where)->save($save_ts);
                }
            }
            if ($data['user_get_limit'] != $old_data['user_get_limit']) {
                $save_activity['user_get_limit'] = $data['user_get_limit'];
            }
            M("SpActivity")->save($save_activity);
        }
    }
    /*
    * 代金卷输出修改数据
    * type 1 : view  0: eidt
    * Author: Lynn
    * Start: June 7th
    */
    protected function getActivityEditData($id) {
        $prefix 	= 	'sp_';
        $field		=	'sat.id as sat_id,sat.activity_id,a.ticket_begin_date,a.ticket_end_date,sat.total,a.user_get_limit,a.is_over,a.is_scope,a.scope';
        $activity	=	M("SpActivityTicket sat")
                                ->field($field)
                                ->join($prefix."activity a on a.id = sat.activity_id")
                                ->where("sat.ticket_id = ".$id)
                                ->find();
        return $activity;
    }
    /*
 * 删除TicketStock表中的数据重新添加
 * Data
 * activity_id total
 * Author: Lynn
 */
    public function delUnionAddFromTicketStock($data){
        $del['activity_id']	=	$data['activity_id'];
        //删除操作
        $this->delTicketStock($del);
        //添加操作
        $data['state']		=	TICKET_CARD_STATE_NORMAL;
        $addCount			=	min(TICKET_MIN_COUNT,$data['total']);
        $all                =   array();
        for ($i=0; $i < $addCount; $i++) {
            $data['code']	=	get_ticket_code_cuid();
            $all[]          =   $data;
        }
        $this->addTicketStockAll($all);
    }
    /*
     * 删除TicketStock表中的数据
     * Author: Lynn
     * Start: June 7th
     */
    public function delTicketStock($where){
        M("SpTicket_stock")->where($where)->delete();
    }
    /*
     * 添加TicketStock表中的数据
     * Author: Lynn
     * Start: June 7th
     */
    public function addTicketStock($add){
        M("SpTicketStock")->add($add);
    }
    public function addTicketStockAll($add){
        M("SpTicketStock")->addAll($add);
    }
    public function removeTicketStock($id){
        $where['id'] = is_array($id) ? array('in',$id) : $id ;
        M("SpTicketStock")->where($where)->delete();
    }
    public function deleteTicketStock($data){
        M("SpTicketStock")->where($data)->delete();
    }
    public function deleteActivityTicket($data){
        M("SpActivityTicket")->where($data)->delete();
    }
    public function deleteActivity($data){
        M("SpActivity")->where($data)->delete();
    }
    private function getStockId($activity_id){
        return M('SpTicketStock')->where('activity_id = '.$activity_id)->getField('id',true);
    }
    protected function hasRuningActivityCurrency($data,$postdata){
        $beginDate = \DateTime::createFromFormat('Y/m/d H:i:s',$postdata['ticket_begin_date']);
        $endDate = \DateTime::createFromFormat('Y/m/d H:i:s',$postdata['ticket_end_date']);
        $beginTime = $beginDate->getTimestamp();
        $endTime = $endDate->getTimestamp();
        $condition['branch_id'] = $data['branch_id'] ? $data['branch_id'] : getBrowseBranchId();
        $condition['_string'] = ' ((activity_begin_date <= '.$beginTime.' and activity_end_date > '.$beginTime.' ) or';
        $condition['_string'].= ' (activity_begin_date <= '.$endTime.' and activity_end_date > '.$endTime.' )) ';
        $condition['is_over'] = 0;
        $condition['is_scope']= 0;
        $condition['activity_type'] = 2;
        if ($data['id'] > 0){
            $connector = M('SpActivityTicket') ->where('ticket_id = '.$data['id']) ->find();
            $condition['id'] = array('neq',$connector['activity_id']);
        }
        $res = M('SpActivity')->where($condition)->find();
        return $res ? true : false ;
    }
    protected function hasRuningActivityScope($data,$postdata){
        $beginDate = \DateTime::createFromFormat('Y/m/d H:i:s',$postdata['ticket_begin_date']);
        $endDate = \DateTime::createFromFormat('Y/m/d H:i:s',$postdata['ticket_end_date']);
        $beginTime = $beginDate->getTimestamp();
        $endTime = $endDate->getTimestamp();
        $where = '';
        foreach ($postdata['scope'] as $key => $val){
            if ($key == 0){
                $where = ' FIND_IN_SET('.$val.',scope)';
            }else{
                $where .= ' or FIND_IN_SET('.$val.',scope)';
            }
        }
        $condition['branch_id'] = $data['branch_id'] ? $data['branch_id'] : getBrowseBranchId();
        $condition['_string'] = ' ((activity_begin_date <= '.$beginTime.' and activity_end_date > '.$beginTime.' ) or';
        $condition['_string'].= ' (activity_begin_date <= '.$endTime.' and activity_end_date > '.$endTime.' )) ';
        $condition['_string'].= ' and ( '.$where.' )';
        $condition['is_over'] = 0;
        $condition['is_scope']= 1;
        $condition['activity_type'] = 2;
        if ($data['id'] > 0){
            $connector = M('SpActivityTicket') ->where('ticket_id = '.$data['id']) ->find();
            $condition['id'] = array('neq',$connector['activity_id']);
        }
//        var_dump($condition['_string']);die;
        $res = M('SpActivity')->distinct(true)->where($condition)->getField('scope',true);
        if ($res){
            $temp = array();
            foreach ($res as  $key => $val){
                $single = explode(',',$val);
                foreach ($single as $k => $v){
                    if(!in_array($v,$temp) && in_array($v,$postdata['scope'])){
                        $temp[] = $v;
                    }
                }
            }
            $product_where['id'] = array('in',$temp);
            //返回名称
            $hasRuningName = D('ComProduct')->where($product_where)->getField('product_title',true);
            return $hasRuningName;
        }else{
            return false;
        }
    }
    public function getSpTicketDetail($id)
    {
//        $condition['t.id'] = $id;
//        $result =    $this   ->alias('t')
//                             ->field()
//                             ->join('left join sp_activity_ticket at on at.ticket_id = t.id')
//                             ->join('left join sp_activity ac on ac.id = at.activity_id ')
//                             ->where($condition)
//                             ->find();

    }

}