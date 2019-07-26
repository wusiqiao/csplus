<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/07/17
 * Time: 10:18
 */
namespace ESAdmin\Controller;


use Think\Controller;

class ComChangeYhStatusController extends Controller{
	public function indexAction()
	{
		$fp = @fopen("D:/www/csplus/test.txt", "a+");
		$list = M('YhMeeting')->field('id,status,apply_start_time,apply_end_time,start_time,end_time')->where(array('status'=>array('lt',5)))->select();
		foreach ($list as $val){
			$string = '';
			if ($val['status'] == 1){
				if (time() > $val['apply_start_time']){
					$string = "时间:".date("Y-m-d H:i:s")." id:".$val['id']."状态从".$val['status'];
					$val['status'] = 2;
					$string .= "->".$val['status']."\n";
					M('YhMeeting')->save($val);
				}
			}
			if ($val['status'] == 2){
				if (time() > $val['apply_end_time']){
					$string = "时间:".date("Y-m-d H:i:s")." id:".$val['id']."状态从".$val['status'];
					$val['status'] = 3;
					$string .= "->".$val['status']."\n";
					M('YhMeeting')->save($val);
				}
			}
			if ($val['status'] == 3){
				if (time() > $val['start_time']){
					$string = "时间:".date("Y-m-d H:i:s")." id:".$val['id']."状态从".$val['status'];
					$val['status'] = 4;
					$string .= "->".$val['status']."\n";
					M('YhMeeting')->save($val);
				}
			}
			if ($val['status'] == 4){
				if (time() > $val['end_time']){
					$string = "时间:".date("Y-m-d H:i:s")." id:".$val['id']."状态从".$val['status'];
					$val['status'] = 5;
					$string .= "->".$val['status']."\n";
					M('YhMeeting')->save($val);
				}
			}
			if ($string){
				fwrite($fp,$string);
			}
		}
		fclose($fp);
		exit;
	}
}