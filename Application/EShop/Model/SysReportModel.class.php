<?php

namespace EShop\Model;

use Think\Model;

class SysReportModel extends Model {
    protected $_MODEL = 'SysReport';
    protected $_PREFIX= 'sys_';
    protected $_DEFAULT_TOPIC = REPORT_TOPIC_SYSTEM;
    public function getOrderReportList($order_id){
        $order_data = D('ComOrder')->field('user_id')->where('id = '.$order_id)->find();
        $report_data	=	M($this->_MODEL)->where("order_id = ".$order_id)->order("report_time desc,report_id desc")->select();
        foreach ($report_data as $key => $value) {
            $report_data[$key]['view_date']	=	date('Y.m.d H:i',$value['report_time']);
            $report_data[$key]['own'] = $value['user_id'] == $order_data['user_id'] ? 1 : 0;
            $report_data[$key]['report_desc'] = $_SESSION['user_id'] == $order_data['user_id'] ? $value['report_desc'] : ($value['report_service_desc'] ?? $value['report_desc']);
            if ($value['other'] == 1){
                $other_value = json_decode($value['other_value'],true);
                switch ($other_value['object']){
                    case 'unline':
                        $report_data[$key]['line'] = '/Order/unlineInfo/id/'.$other_value['object_id'].'/oid/'.$order_id.'.html';
                        break;
                    default:
                        $report_data[$key]['line'] = '/Order/reportInfo/id/'.$value['report_id'].'.html';
                        break;
                }
            }
        }
        return $report_data;
    }
    //角色 data 0 desc 1 remark 2 topic
    public function addOrderReport($order_id,array $report = []){
        if ($report && !isset($report['desc'])){
            $data['desc'] = isset($report[0]) ? $report[0] : false;
            $data['title'] = isset($report[1]) ? $report[1] : false;
            $data['topic'] = isset($report[2]) ? $report[2] : false;
            $data['other'] = isset($report[3]) ? $report[3] : false;
            if(isset($report[4])){
                if (is_array($report[4])){
                    $data['other_value'] = json_encode($report[4]);
                }else{
                    $data['other_value'] = $report[4];
                }
            }
        }elseif ($report && isset($report['desc'])){
            if(isset($report['other_value'])){
                if (is_array($report['other_value'])){
                    $data['other_value'] = json_encode($report['other_value']);
                }else{
                    $data['other_value'] = $report['other_value'];
                }
            }
            $data = $report;
        }
        //记录业务进度
        $report_table = M($this->_MODEL);
        $data['order_id'] = $order_id;
        $data['user_id'] = $data['user_id'] ? $data['user_id'] : $_SESSION['user_id'];
        $data['report_time'] = time();
        $data['report_desc'] = $data['desc'] ? $data['desc'] : '';
        $data['report_title'] = $data['title'] ? $data['title'] : '' ;
        $data['report_remark'] = $data['remark'] ? $data['remark'] : '' ;
        $data['topic'] = $data['topic'] ? $data['topic'] : $this->_DEFAULT_TOPIC ;
        $data['other'] = $data['other'] ? $data['other'] : 0;
        $result = $report_table->data($data)->add();
        return $result;
    }
    public function addOrderReportStep($order_id,$desc,$remark,$pic=array()){
        //记录业务进度
        $report_table = M($this->_MODEL);
        $data['order_id'] = $order_id;
        $data['user_id'] = $_SESSION['user_id'];
        $data['report_time'] = time();
        $data['report_remark'] = $remark;
        $data['report_desc'] = $desc;
        if(count($pic) > 0){
            foreach ($pic as $key => $value) {
                if(strpos($value, 'uploads/')){
                    $data['pic'.$key] = $value;
                }
            }
        }
        $result = $report_table->data($data)->add();
        return $result;
    }
}
