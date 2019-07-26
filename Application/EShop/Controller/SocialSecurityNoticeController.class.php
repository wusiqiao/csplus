<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/04/29
 * Time: 13:46
 */

namespace EShop\Controller;
use Think\Controller;

class SocialSecurityNoticeController extends BaseController{

	const BLACK_CUTTER = '：';

	/**
	 * 商户查看清卡通知
	 * 公司列表页
	 * @param year	年份
	 * @param month	月份
	 * @param message_state 状态：1全部、2未确认、3已确认
	 * @return array
	 */
	public function indexAction(){
		$this->companyId = session('branch_id');

		$message_state = I('message_state') ? I('message_state') : 1;//默认全部

		//获取可以查看的公司列表
		//当前用户是通知人或者发送通知人：1，可见人：2，协作人：4，负责人：8
		$list = D('SysUserModuleSetting')->alias('s')
			->field('b.id as company_id,b.name as company_name')
			->join('INNER JOIN sys_branch as b ON b.id = s.company_id')
			->where(array('s.module'=>'SocialSecurityNotice','s.type'=>DAC_SETTING_TYPE_BRANCH,'s.branch_id'=>$this->companyId,'s.user_id'=>$this->userId))
			->group('b.id')
			->select();


		$tmcondition["standard_id"] = array('eq','OPENTM417844694');
		$tmcondition["title"] = array('like','%社保%');
		$template = M('WxTemplateMessage')->where($tmcondition)->field('id,title')->find();
		$template_id = $template['id'];

		$year = I('year') ? I('year') : date('Y');
		$month = I('month') ? I('month') : date('m');
		$this->assign('year',$year);
		$this->assign('month',$month);

		$wntlON = 'wntl.template_id = '.$template_id.' AND wntl.branch_id = '.$this->companyId.' ';
		$start_time=mktime(0,0,0,$month,1,$year);
		$end_time=mktime(0,0,0,$month+1,1,$year);;


		if ($year == date('Y') && $month == date('m')){
			$wntlON .= 'AND (wntl.created_at >= '.$start_time.') ';
		} else {
			$wntlON .= 'AND ((wntl.created_at >= '.$start_time.' AND wntl.created_at < '.$end_time.') OR wntl.created_at IS NULL) ';
		}

		$companyList = array();
		$where = '';
		$unSendCompanyList = array();
		$isSendCOmpanyList = array();
		foreach($list as $key=>$company){
			$where = $wntlON .' AND wnru.company_id = '.$company['company_id'].' AND wnru.state = 1';
			$notice = M('WxNoticeTemplateLibrary')->alias('wntl')
				->field('wnru.message_state,wnru.id as wnru_id')
				->join('LEFT JOIN wx_notice_relation_user as wnru ON wnru.notice_id = wntl.id')
				->where($where)
				->order('wnru.id DESC')
				->find();

			$company['type'] = '未发送';
			$company['status'] = '';
			$company['wnru_id'] = 0;
			if ($notice){
				$company['type'] = '已发送';
				$company['status'] = $notice['message_state'] == 2 ? '已确认':'未确认';
				$company['wnru_id'] = $notice['wnru_id'];
			}

			if ($company['type'] == '未发送'){
				$unSendCompanyList[] = $company;
			} elseif ($message_state == 1) {
				$isSendCOmpanyList[] = $company;
			} elseif ($message_state == 2 && $company['status'] == '未确认') {
				$isSendCOmpanyList[] = $company;
			} elseif ($message_state == 3 && $company['status'] == '已确认') {
				$isSendCOmpanyList[] = $company;
			}

			$isSendCOmpanyList = arraySort($isSendCOmpanyList,'wnru_id',SORT_DESC);

			$companyList['isSendCOmpanyList'] = $isSendCOmpanyList;
			if ($message_state == 1){
				$companyList['isSendNumber'] = '全部：'.count($isSendCOmpanyList);
			} elseif($message_state == 2){
				$companyList['isSendNumber'] = '未确认：'.count($isSendCOmpanyList);
			} else {
				$companyList['isSendNumber'] = '已确认：'.count($isSendCOmpanyList);
			}

			$companyList['unSendCompanyList'] = $unSendCompanyList;
			$companyList['unSendNumber'] = '未发送：'.count($unSendCompanyList);
		}


		if (IS_AJAX){
			$this->ajaxReturn($companyList);
		} else {
			$this->assign('companyList',json_encode($companyList));
			$this->assign("title","社保通知");
			$this->display();
		}

	}

	public function listAction(){
		$this->companyId = session('branch_id');
		$company_id = I('company_id');
		$year = I('year') ? I('year') : date('Y');

		$this->assign('year',$year);
		$this->assign('company_id',$company_id);

		$start_time=mktime(0,0,0,1,1,$year);
		$end_time=mktime(0,0,0,1,1,$year+1);

		$tmcondition["standard_id"] = array('eq','OPENTM417844694');
		$tmcondition["title"] = array('like','%社保%');
		$template = M('WxTemplateMessage')->where($tmcondition)->field('id,title')->find();
		$template_id = $template['id'];

		$lists = M('WxNoticeTemplateLibrary')->alias('wntl')
			->field('b.id as company_id,b.name as company_name,FROM_UNIXTIME(wntl.life,\'%m月\') as month,wntl.life,wnru.created_at,wnru.message_state,count(wnru.id) as number,wnru.content')
			->join('LEFT JOIN wx_notice_relation_user as wnru ON wnru.notice_id = wntl.id')
			->join('LEFT JOIN sys_branch as b ON b.id = wnru.company_id')
			->where(array(
				'wntl.template_id' => array('eq',$template_id),
				'wntl.branch_id'=> array('eq',$this->companyId),
				'wntl.life' => array(array('egt',$start_time),array('lt',$end_time),'AND'),
				'wnru.company_id'=>array('eq',$company_id),
				'wnru.state' => array('eq',1)
			))
			->group('wntl.life')
			->order('wnru.id DESC')
			->select();

		foreach($lists as $key=>$value){
			$value['message_state'] = $value['message_state'] == 2 ? '已确认':'未确认';
			$value['created_at'] = date('Y-m-d H:i',$value['created_at']);
			$value['detail'] = self::detail($value['company_id'],$value['life']);

			$value['insured_number'] = 0;
			$value['insurance_amount'] = 0.00;
			$contentArr = $this->handlerNoticeContentRecords($value);
			foreach ($contentArr as $k=>$item){
				if ($item['field'] == 'keyword2'){
					$value['insured_number'] = $item['value'];
				} elseif ($item['field'] == 'keyword3'){
					$value['insurance_amount'] = $item['value'];
				}
			}

			$lists[$key] = $value;
		}

		if (IS_AJAX){
			$this->ajaxReturn($lists);
		} else {
			$this->assign('list',json_encode($lists));
			$this->display('month_list');
		}
	}

	protected function detail($company_id,$life){
		$this->companyId = session('branch_id');
		$tmcondition["standard_id"] = array('eq','OPENTM417844694');
		$tmcondition["title"] = array('like','%社保%');
		$template = M('WxTemplateMessage')->where($tmcondition)->field('id,title')->find();
		$template_id = $template['id'];

		$list = M('WxNoticeTemplateLibrary')->alias('wntl')
			->field('wnru.created_at as time')
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
		}

		return $detail;

	}

	protected  function handlerNoticeContentRecords($data,$inc='content',$c = 2)
	{
		$result = array();
		$content_records = explode("\r\n", $data[$inc]);
		foreach ($content_records as $content_record){
			$items = explode(self::BLACK_CUTTER,$content_record);
			if (count($items) == $c){
				$result[$inc."_records"][] = array("field"=>$items[0], "value"=>$items[1]);
			}else{
				$result[$inc."_records"][] = array("key"=>$items[0],"field"=>$items[1], "value"=>$items[2]);
			}
		}
		$this->handlerContentDetailData($result[$inc."_records"]);
		return $result[$inc."_records"];
	}

	protected function handlerContentDetailData(&$content)
	{
		foreach ($content as $key => $value) {
			if ($key == 0) {
				$content[$key]['placeholder'] = '请输入消息提示';
				$content[$key]['view'] = '消息提示';
			} else if (trim($value['key']) != '') {
				$content[$key]['placeholder'] = '请输入'.$value['key'];
				$content[$key]['view'] = $value['key'];
			} else {
				$content[$key]['placeholder'] = '请输入消息备注';
				$content[$key]['view'] = '消息备注';
			}
		}
	}


}