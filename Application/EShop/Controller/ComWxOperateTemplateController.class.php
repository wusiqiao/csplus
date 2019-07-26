<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/28
 * Time: 15:21
 */

namespace EShop\Controller;
use Think\Controller;

class ComWxOperateTemplateController extends BaseController{

	public function indexAction(){

		$this->assign("title","清卡通知");
		$year = I('year') ? I('year') : date('Y');

		$this->assign('year',$year);

		$start_time=mktime(0,0,0,1,1,$year);
		$end_time=mktime(0,0,0,1,1,$year+1);

		$tmcondition["standard_id"] = array('eq','OPENTM403179495');
		$tmcondition["title"] = array('like','%清卡%');
		$template = M('WxTemplateMessage')->where($tmcondition)->field('id,title')->find();
		$template_id = $template['id'];


		$companyId = I('get.company_id') ? I('get.company_id') : (session('wrk_company_id') ? session('wrk_company_id') : $this->companyId);
		session('wrk_company_id',$companyId);
		$lists = M('WxNoticeTemplateLibrary')->alias('wntl')
			->field('b.id as company_id,b.name as company_name,FROM_UNIXTIME(wntl.life,\'%m月\') as month,wntl.life,wntl.branch_id,wnru.created_at,wnru.message_state,count(wnru.id) as number')
			->join('LEFT JOIN wx_notice_relation_user as wnru ON wnru.notice_id = wntl.id')
			->join('LEFT JOIN sys_branch as b ON b.id = wnru.company_id')
			->where(array(
				'wntl.template_id' => array('eq',$template_id),
				'wntl.life' => array(array('egt',$start_time),array('lt',$end_time),'AND'),
				'wnru.company_id'=>array('eq',$companyId),
				'wnru.state' => array('eq',1)
			))
			->group('wntl.life')
			->order('wnru.id DESC')
			->select();

		foreach($lists as $key=>$value){
			$value['created_at'] = date('Y-m-d H:i',$value['created_at']);
			$notice = self::lastNoticeInfo($value['company_id'],$template_id,$value['life'],$value['branch_id']);
			if ($notice){
				$value['message_state'] = $notice['message_state'];
				$value['created_at'] = $notice['wnru_created_at'];
				$value['sure_time'] = $notice['sure_time'];
			}
			$value['message_state'] = $value['message_state'] == 2 ? '已确认':'未确认';
			$value['detail'] = self::detail($value['company_id'],$value['life']);
			$value['sure_able'] = 0;
			if ($value['message_state'] == '未确认' && strtotime(date('Y-m')) == $value['life']){
				$value['sure_able'] = 1;
			}
			$lists[$key] = $value;
		}

		if (IS_AJAX){
			$this->ajaxReturn($lists);
		} else {
			$this->assign('list',json_encode($lists));
			$this->display();
		}
	}

	/**
	 * @param $company_id	客户公司id
	 * @param $template_id	微信模板id
	 * @param $life			年月时间戳
	 * @param $branch_id	商户公司id
	 * @return mixed		最后一个发送的状态详情
	 */
	protected function lastNoticeInfo($company_id,$template_id,$life,$branch_id){
		$info = M("WxNoticeTemplateLibrary")->alias('a')
			->field('b.message_state,FROM_UNIXTIME(b.created_at,\'%Y-%m-%d %H:%i\') as wnru_created_at,b.state,FROM_UNIXTIME(b.sure_time,\'%Y-%m-%d %H:%i\') as sure_time')
			->join('INNER JOIN wx_notice_relation_user as b ON b.notice_id = a.id')
			->where(array(
				"a.template_id" => array('eq',$template_id),
				"a.branch_id"=>array('eq',$branch_id),
				"a.life"=>array("eq",$life),
				"b.company_id"=>array('eq',$company_id),
				'b.state' => array('egt',1)
			))
			->order('b.id DESC')
			->find();
		return $info;
	}

	protected function detail($company_id,$life){
		$this->companyId = session('branch_id');
		$tmcondition["standard_id"] = array('eq','OPENTM403179495');
		$tmcondition["title"] = array('like','%清卡%');
		$template = M('WxTemplateMessage')->where($tmcondition)->field('id,title')->find();
		$template_id = $template['id'];

		$list = M('WxNoticeTemplateLibrary')->alias('wntl')
			->field('wnru.id,wnru.created_at as time,message_state')
			->join('LEFT JOIN wx_notice_relation_user as wnru ON wnru.notice_id = wntl.id')
			->where(array(
				'wntl.template_id' => array('eq',$template_id),
				'wntl.branch_id'=> array('eq',$this->companyId),
				'wntl.life' => array('eq',$life),
				'wnru.company_id'=>array('eq',$company_id),
				'wnru.state' => array('eq',1)
			))
			->order('wnru.id ASC')
			->select();

		$detail = array();
		foreach($list as $key=>$value){
			$value['count'] = $key+1;
			$value['time'] = date('Y-m-d H:i',$value['time']);
			$detail[] = $value;
			if ($value['message_state'] == 0){
				M('wx_notice_relation_user')->where('id = '.$value['id'])->data(['see_time'=>time(),'message_state'=>1])->save();
			}
		}

		return $detail;

	}

	public function sureNoticeAction(){
		$companyId = session('wrk_company_id');
		$life = strtotime(date('Y-m'));
		$list = self::detail($companyId,$life);
		foreach($list as $key=>$value){
			M('wx_notice_relation_user')->where('id = '.$value['id'])->data(['sure_time'=>time(),'message_state'=>2])->save();
		}
		$this->ajaxReturn(['error' =>0,'message' => '操作成功']);
	}


}