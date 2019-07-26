<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/04/25
 * Time: 11:10
 */

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class SocialSecurityNoticeController extends DataController{

	const BLACK_CUTTER = '：';

	public function _initialize()
	{
		parent::_initialize();
		if (IS_GET){
			$this->assign('version','1.0.0');
		}
	}

	/**
	 * 获取清卡模板
	 * 当天负责人是否创建了通知模板（创建用户的id、template_id、当天时间）
	 * 如果有则直接使用该消息模板，没有则查出内容填充在页面，页面上替换应内容：服务商、通知内容、时间、备注等
	 */
	public function indexAction(){
		$templates = D('WxTemplateMessage')->getScenarioTemplate('OPENTM417844694','社保');
		$template_id = $templates['id'];
		$this->assign('template_id',$template_id);
		if (empty($template_id)){
			$this->ajaxReturn(array('error'=>1,'message'=>'该消息模板不可用'));
		}
		$data['template_id'] = $template_id;
		//当天是否有生成过消息内容
		$notice = D('WxOperateTemplate')
			->where(
				array('template_id'=>array('eq',$template_id),
					'creator_id'=>array('eq',$this->userId),
					'created_at'=>array(array('egt',strtotime(date('Y-m-d 00:00:00'))),array('elt',strtotime(date('Y-m-d 23:59:59'))))
				)
			)
			->find();

		if (empty($notice)){
			$template = D('EShop/WxBranchTemplate')->getContentTemplate($template_id);
			$result = $this->handlerContentRecords($template,'all');
			$this->handlerContentAppendData($result['content_records']);
			foreach($result['content_records'] as $key=>$value){
				switch ($value['view'])
				{
					case '消息提示';
						$result['content_records'][$key]['value'] = '您好，贵公司本月申报期的社保应缴情况如下';
						break;
					case '公司名称';
						$result['content_records'][$key]['value'] = '系统将自动带入你所需要通知的公司名称';
						break;
					case '应缴人数';
						$result['content_records'][$key]['value'] = '系统将读取该公司对应的社保参保人数，如需修改请到公司档案中修改。';
						break;
					case '社保金额';
						$result['content_records'][$key]['value'] = '系统将读取该公司对应的应缴社保金额，如需修改请到公司档案中修改。';
						break;
					case '财务会计';
						$result['content_records'][$key]['value'] = $this->_user_session->staffName?$this->_user_session->staffName:$this->userName;
						break;
					case '消息备注';
						$result['content_records'][$key]['value'] = '为确保社保申报进度按时完成，请及时确认社保人数、金额及社保人员名单，并确认公司帐户余额足额扣缴。如有疑问，请与你的财务会计联系';
						break;
				}
			}
			$data['content'] = $result['content_records'];
			//生成今日发送的消息模板
			$content_array = array();
			foreach( $data['content'] as $key => $val) {
				if (!empty(trim($val['value']))){
					$content_array[] = trim($val['key']) === '' ?
						$val['field'].self::BLACK_CUTTER.trim($val['value']) :
						$val['key'].self::BLACK_CUTTER.$val['field'].self::BLACK_CUTTER.trim($val['value']) ;
				}
			}
			$content = $this->handlerContentCUEnRecords($content_array);
			$template_library_info = array();
			$template_library_info['title'] = $templates['title'];
			$template_library_info['template_id'] = $data['template_id'];
			$template_library_info['content'] = $content;
			$template_library_info['branch_id'] = $this->companyId;
			$template_library_info['type'] = 2;
			$template_library_info['created_at'] = time();
			$template_library_info['updated_at'] = time();
			$template_library_info['user_id'] = $this->userId;
			$template_library_info['creator_id'] = $this->userId;
			$template_library_info['name'] = '社保通知';
			$template_library_info['mold'] = WX_OPERATE_MOLD_DEFAULT;
			$template_library_info['message_type'] = 1;
			$template_library_info['licence_plate'] = genUniqidKey();
			$template_library_info['life'] = strtotime(date('Y-m',time()));

			$data['notice_id'] = D('WxNoticeTemplateLibrary')->add($template_library_info);

		} else {
			$data['notice_id'] = $notice['id'];
			$data['content'] = $this->handlerNoticeContentRecords($notice);
		}


		$this->assign('libraryinfo',json_encode($data));
		$staticInfo = D('WxOperateTemplate')->statisticalNotice($template_id,CONTROLLER_NAME,$this->companyId);
		$this->assign('staticInfo',json_encode($staticInfo));

		$this->display();

	}

	//模板处理函数
	protected function handlerContentRecords($data,$inc ='all')
	{
		$result = array();
		if ($inc === 'all') {
			$result["content_records"] = $this->handlerContentRecords($data,'content');
			$result["example_records"] = $this->handlerContentRecords($data,'example');
			return $result;
		} else {
			if ($inc == 'example') {
				$count = substr_count($data[$inc],self::BLACK_CUTTER);
			}
			$content_records = explode("\r\n", $data[$inc]);
			foreach ($content_records as $key => $content_record){
				if (strpos($content_record,'.DATA') || $inc =='example') {
					if (($inc == 'example' && $key >=(count($content_records) - $count - 2)) || $inc == 'content') {
						$items = explode(self::BLACK_CUTTER, $content_record);
						if($items[0] !='' || $items[1] != '') {
							if (count($items) == 1) {
								$result[$inc . "_records"][] = array("key" => "", "title" => $items[0]);
							} else {
								$result[$inc . "_records"][] = array("key" => $items[0], "title" => $items[1]);
							}
						}
					} else {
						if (trim($content_record) != '') {
							$result[$inc . "_records"]['first'] = array("key" => "", "title" => $content_record);
						}
					}
				}
			}
			return $result[$inc."_records"];
		}
	}

	protected function handlerContentAppendData(&$content)
	{
		foreach ($content as $key => $value) {
			$content[$key]['field'] = str_replace(array('{{','.DATA}}'),'',$value['title']);
			$content[$key]['value'] = '';
			$content[$key]['color'] = '#000000';
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
//        var_dump($content);die;
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
//        var_dump($content);die;
	}

	//储存于数据库时 content的数据处理
	protected function handlerContentCUEnRecords($content)
	{
		return implode("\r\n",$content);
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

	/**
	 * edit_notic_remark
	 * 编辑通知的备注
	 */
	public function edit_notic_remarkAction(){
		try{
			$data = I('post.');
			$content_array = array();
			foreach( $data['content'] as $key => $val) {
				if (empty(trim($val['value']))){
					throw new Exception($val['placeholder']);
				} else {
					$content_array[] = trim($val['key']) === '' ?
						$val['field'].self::BLACK_CUTTER.trim($val['value']) :
						$val['key'].self::BLACK_CUTTER.$val['field'].self::BLACK_CUTTER.trim($val['value']) ;
				}
			}
			$content = $this->handlerContentCUEnRecords($content_array);
			if ($data['id']){

				$savedata['id'] = $data['id'];
				$savedata['content'] = $content;
				$savedata['updated_at'] = time();
				D('WxNoticeTemplateLibrary')->save($savedata);
			} else {
				//新增操作
				$template_library_info = array();
				$template_library_info['title'] = '清卡通知';
				$template_library_info['template_id'] = $data['template_id'];
				$template_library_info['content'] = $content;
				$template_library_info['branch_id'] = $this->companyId;
				$template_library_info['type'] = 2;
				$template_library_info['created_at'] = time();
				$template_library_info['updated_at'] = time();
				$template_library_info['user_id'] = $this->userId;
				$template_library_info['creator_id'] = $this->userId;
				$template_library_info['name'] = '清卡通知';
				$template_library_info['mold'] = WX_OPERATE_MOLD_DEFAULT;
				$template_library_info['message_type'] = 1;
				$template_library_info['licence_plate'] = genUniqidKey();
				$template_library_info['life'] = strtotime(date('Y-m',time()));

				$data['id'] = D('WxNoticeTemplateLibrary')->add($template_library_info);
			}

			$this->ajaxReturn(array('notice_id'=>$data['id'],'error'=>0,'message'=>'保存成功'));
		} catch (Exception $e) {
			$this->ajaxReturn(array('error'=>1,'message'=>$e->getMessage()));
		}
	}

	/**
	 * 获取负责的公司
	 * 管理员可以获取所有公司
	 * 本期则根据服务商负责人查询客户
	 */
	public function noticeCompanyListAction(){

		$template_id = I('template_id');
		$year = I('year') ? I('year') :date('Y');
		$month = I('month') ? I('month') :date('m');

		$life_time = strtotime($year."-".$month);

		//获取商户负责人管理的客户公司id列表
		$list = D("SysUserModuleSetting")->getCompanysByModule(CONTROLLER_NAME, $keyword='',array(DAC_PERMIT_VALUE_COLLABORATOR,DAC_PERMIT_VALUE_LEADER));
		$company_id_list = array();
		foreach ($list as $val){
			$company_id_list[] = $val['id'];
		}
		if (empty($company_id_list)){
			$this->ajaxReturn();
		}


		//发送状态全部：0未处理1成功2失败
		//
		$wherestr = "WHERE tempDate.company_id > 0";

		$bidstr = implode(",",$company_id_list);

		$havingstr = 'HAVING tempDate.company_id > 0';
		//发送状态：0未处理1成功2失败
		if (I('state') != '' && I('state') == 0){
			$havingstr .= " AND (tempDate.state < 1 OR tempDate.state IS NULL)";
		} elseif (I('state') == 1){
			$wherestr .= " AND tempDate.state = 1";
		} elseif(I('state') == 2) {
			$wherestr .= " AND tempDate.state = 2";
		}

		//消息状态：0未查看、1已查看、2已确认(发送成功才有该筛选条件)
		if (I('message_state') != '' && I('message_state') == 0){
			$wherestr .= " AND tempDate.message_state <> 2";
		} elseif (I('message_state') == 2){
			$wherestr .= " AND tempDate.message_state = 2";
		}

		//是否已关注：0未关注、1已关注
		if (I('is_follow') != '' && I('is_follow') == 0){
			$wherestr .= " AND (tempDate.is_follow < 1 OR tempDate.is_follow IS NULL)";
		} elseif (I('is_follow') == 1){
			$wherestr .= " AND tempDate.is_follow = 1";
		}

		//关键字查询
		if (I('keyword')){
//			$where['_string'] = ' (b.name like "%'.I('keyword').'%")  OR ( c.name like "%'.I('keyword').'%") ';
			$wherestr .= " AND tempDate.company_name like '%".I('keyword')."%' OR tempDate.customer_name like '%".I('keyword')."%'";
		}


		$sql = "SELECT *,count(tempDate.wnru_id) as message_num FROM(
				SELECT b.id AS company_id,b.`name` AS company_name,b.insured_number,b.insurance_amount,
				c.`name` AS customer_name,c.head_pic,c.mobile AS customer_mobile,c.is_follow,
				cn.`name` AS c_name,cn.head_pic AS c_head_pic,cn.mobile AS c_mobile,cn.is_follow AS c_is_follow,
				manaer.`name` AS sender_name,u.`name` AS upleader_name,
				wntl.template_id,wntl.id AS wntl_id,wntl.life,wnru.id as wnru_id,
				wnru.state,wnru.created_at,wnru.message_state,wnru.sure_time
				FROM sys_branch AS b
				LEFT JOIN wx_notice_template_library AS wntl ON wntl.template_id  = ".$template_id." AND wntl.branch_id = ".$this->companyId." AND wntl.life = '".$life_time."'
				LEFT JOIN wx_notice_relation_user AS wnru ON  wntl.id = wnru.notice_id AND wnru.company_id = b.id
				LEFT JOIN sys_user as c ON c.id = b.customer_leader_id
				LEFT JOIN sys_user_module_setting as cnotice ON cnotice.company_id = b.id AND cnotice.type = ".DAC_SETTING_TYPE_CUSTOMER." AND cnotice.permit_value = ".DAC_PERMIT_VALUE_NOTICER." AND cnotice.module = '".CONTROLLER_NAME."' AND cnotice.branch_id = '".$this->companyId."'
				LEFT JOIN sys_user as cn ON cn.id = cnotice.user_id
				LEFT JOIN sys_user_module_setting as upleader ON upleader.company_id = b.id AND upleader.type = ".DAC_SETTING_TYPE_BRANCH." AND upleader.permit_value = ".DAC_PERMIT_VALUE_NOTICER." AND upleader.module = '".CONTROLLER_NAME."' AND upleader.branch_id = '".$this->companyId."'
				LEFT JOIN sys_user as u ON u.id = upleader.user_id
				LEFT JOIN sys_user_module_setting as ums ON ums.company_id = b.id AND ums.type = ".DAC_SETTING_TYPE_BRANCH." AND ums.permit_value = ".DAC_PERMIT_VALUE_LEADER." AND ums.module = '".CONTROLLER_NAME."' AND ums.branch_id = '".$this->companyId."'
				LEFT JOIN sys_user as manaer ON manaer.id = ums.user_id
				WHERE b.type = 1 AND b.id IN (".$bidstr.")
				ORDER BY wnru.id DESC limit 999999999) AS tempDate
				".$wherestr."
				GROUP BY tempDate.company_id
				".$havingstr."
				ORDER BY tempDate.company_id ASC
				";


		$company_list = M()->query($sql);


		//数据整理
		foreach($company_list as $key=>$company){
			$company_list[$key]['company_name'] = $company['company_name'] ? $company['company_name']:'';
			if ($company['c_name']){
				$company_list[$key]['customer_name'] = $company['c_name'] ? $company['c_name']:'';
				$company_list[$key]['head_pic'] = $company['c_head_pic'] ? $company['c_head_pic']:'';
				$company_list[$key]['customer_mobile'] = $company['c_mobile'] ? $company['c_mobile']:'';
				$company_list[$key]['is_follow'] = $company['c_is_follow'] ? '已关注':'未关注';
			} else {
				$company_list[$key]['customer_name'] = $company['customer_name'] ? $company['customer_name']:'';
				$company_list[$key]['head_pic'] = $company['head_pic'] ? $company['head_pic']:'';
				$company_list[$key]['customer_mobile'] = $company['customer_mobile'] ? $company['customer_mobile']:'';
				$company_list[$key]['is_follow'] = $company['is_follow'] ? '已关注':'未关注';
			}


			$company_list[$key]['sender_name'] = $company['sender_name'] ? $company['sender_name']:'';
			$company_list[$key]['upleader_name'] = $company['upleader_name'] ? $company['upleader_name']:'';

			$company_list[$key]['life'] = $company['life'] ? $company['life']:'';


			$company_list[$key]['send_able'] = 1; //1可以再次发送、0不可以再次发送
			$company_list[$key]['sure_time'] = $company['sure_time'] ? date('Y-m-d H:i',$company['sure_time']) : '';
			$company_list[$key]['send_able'] = $company['sure_time'] ? 0 : 1;
			if ($year != date('Y') || $month != date('m')){
				$company_list[$key]['send_able'] = 0;
			}

			switch ($company['message_state']){
				case 0:$company_list[$key]['message_state'] = '未确认';break;
//				case 1:$company_list[$key]['message_state'] = '已查看';break;
				case 2:$company_list[$key]['message_state'] = '已确认';break;
//				case 3:$company_list[$key]['message_state'] = '已取消';break;
				default:$company_list[$key]['message_state'] = '未确认';
			}
			$company_list[$key]['customer_mobile'] = $company['customer_mobile'] ? $company['customer_mobile']:'';
			$company_list[$key]['created_at'] = $company['created_at'] ? date('Y-m-d H:i',$company['created_at']):'';
			switch ($company['state']){
				case 0:$company_list[$key]['state'] = '未发送';break;
				case 1:$company_list[$key]['state'] = '已发送';break;
				case 2:$company_list[$key]['state'] = '发送失败';break;
			}
			$company_list[$key]['message_num'] = $company['state'] ? $company['message_num']:0;
		}


		$this->ajaxReturn($company_list);
	}

	/**
	 * 该消息内容是否存在，存在则创建需要发送的关系表wx_notice_relation_user
	 * 不存在则创建wx_notice_template_library并生成发送的关系表wx_notice_relation_user
	 * 发送消息内容
	 */
	public function sendNoticeAction(){
		$post = I('POST.');
		//获取到了wx_notice_template_library的数据后创建wx_notice_relation_user的数据
		$notice_id = $post['id'];
		//获取公司的负责人的openid
		$where['b.type'] = array('eq',1);
		$where['b.id'] = array('in',$post['companys']);
		$company_list = M('SysBranch')->alias('b')
			->field('b.id as company_id,b.name as company_name,b.parent_id,u.id as user_id,u.mobile,u.openid,cn.id as c_user_id,cn.mobile as c_mobile,cn.openid as c_openid')
			->join('left join sys_user as u ON u.id = b.customer_leader_id')
			->join('LEFT JOIN sys_user_module_setting as cnotice ON cnotice.company_id = b.id AND cnotice.type = '.DAC_SETTING_TYPE_CUSTOMER.' AND cnotice.permit_value = '.DAC_PERMIT_VALUE_NOTICER.' AND cnotice.module = "SocialSecurityNotice" AND cnotice.branch_id = "'.$this->companyId.'"')
			->join('LEFT JOIN sys_user as cn ON cn.id = cnotice.user_id')
			->join('LEFT JOIN wx_notice_relation_user AS wnru ON wnru.company_id = b.id')
			->where($where)
			->group('b.id')
			->select();
		$append_relation =[];
		$upLeaderOpenid = [];
		foreach ($company_list as $val) {
			if ($val['c_user_id']){
				$val['user_id'] = $val['c_user_id'];
				$val['mobile'] = $val['c_mobile'];
				$val['openid'] = $val['c_openid'];
			}
			$append_relation[] = [
				'notice_id' => $notice_id,
				'company_id' => $val['company_id'],
				'company_name'=>$val['company_name'],
				'mobile' => $val['mobile'],
				'user_id' => $val['user_id'],
				'object_type' => 1,
				'use' => 0,
				'state' => WX_TEMPLATE_SEND_DEFAULT,
				'keyt' => strtoupper(hash('md5',time().$val['company_id'])),
				'created_at' => time(),
				'updated_at' => time()
			];
			$user_id = D('SysUserModuleSetting')->getMemberByModuleAndCompanyId(CONTROLLER_NAME,$val['company_id'],DAC_PERMIT_VALUE_NOTICER);
			if ($user_id){
				$upLeaderOpenid[$user_id]['openid'] = M('SysUser')->where(array('id'=>array('eq',$user_id)))->getField('openid');
			}
		}

		if (!empty($append_relation)){
			M('wx_notice_relation_user')->addAll($append_relation);
		}
		//发送
		$template_data['notice_id'] = $notice_id;
		$content = D('WxOperateTemplate')->where('id = '.$notice_id)->getField('content');
		$standard_id = M('wx_template_message')->where('id = '.$post['template_id'])->getField('standard_id');
		$template_data['template_id'] = getWxTemplateIdByStandardId($standard_id);
		if (empty($template_data['template_id'])){
			$this->ajaxReturn(['error' =>1,'message' => '微信模板缺失']);
		}
		$template_data['content'] = $this->handlerNoticeContentRecords(['content'=>$content]);
		$companys = $this->generatorFromClearNotice($post['companys'],$template_data['notice_id'],$content);
		if ($companys['success']) {

			foreach	($companys['success'] as $key=>$company){
				$message = array();
				$body = array();
				$message["template_id"] = $template_data['template_id'];
				$message["url"] = str_replace('shop','shop'.$this->_user_session->currBranchId,SHOP_ROOT).'/ComSocialSecurityNotice/index/keys/';
				foreach ( $template_data['content'] as $key =>$val) {
					if ($val['field'] == 'keyword1'){
						$body[$val['field']]["value"] = $company['company_name'];
					} elseif ($val['field'] == 'keyword2'){
						$body[$val['field']]["value"] = $company['insured_number'];
					} elseif ($val['field'] == 'keyword3'){
						$body[$val['field']]["value"] = $company['insurance_amount'];
					} else {
						$body[$val['field']]["value"] = $val['value'];
					}
				}
				$message["body"] = $body;
				$data['message'] = $message;

				$data['companys'] = array();
				$data['companys'] = array($company['company_id']=>$company);
				$data['notice_id'] = $notice_id;
				send_wx_group_message($data,true);
			}
			//获取通知人
			$sendUpLeader = array(
				'template_id'=>getWxTemplateIdByStandardId('OPENTM414554920'),
				'url'=>str_replace('shop','shop'.$this->_user_session->currBranchId,SHOP_ROOT).'/SocialSecurityNotice/index',
				'body'=>array(
					'first'=>array('value'=>'你好，本次已对'.count($companys['success']).'家公司发送社保通知，请知悉。'),
					'keyword1'=>array('value'=>'社保通知'),
					'keyword2'=>array('value'=>'工作通知'),
					'keyword3'=>array('value'=>($this->_user_session->staffName?$this->_user_session->staffName:$this->userName).'-通知社保缴费'),
					'keyword4'=>array('value'=>date('Y-m-d H:i:s')),
					'remark'=>array('value'=>'点击详情可查看客户的确认情况。'),
				)
			);
			echo "<pre>";
			print_r($sendUpLeader);
			echo "</pre>";
			if ($upLeaderOpenid){
				foreach($upLeaderOpenid as $user){
					$sendUpLeader['openid'] = $user['openid'];
					send_wx_message($sendUpLeader);
				}
			}
			$this->ajaxReturn(['error' =>0,'message' => '发送完成']);
		} else {
			$this->ajaxReturn(['error' =>1,'message' => '发送失败']);
		}
	}

	protected function generatorFromClearNotice($companys,$notice_id,$content)
	{
		$companys_result = D('WxOperateTemplate')->getCompanyUserOpenidsFromTemplate($notice_id,$companys);
		$companys_data = [];
		foreach($companys_result as $key=>$value){
			$companys_data[$value['company_id']] = $value;
		}
		$condition['company_id'] = array('in',$companys);
		$condition['use'] = 0;
		$condition['notice_id'] = $notice_id;
		$field = '*';
		$result = M('wx_notice_relation_user')->field($field)->where($condition)->select();
		$array = [];
		$success = [];
		foreach($result as $key => $value) {
			$contentArr = array();
			$temp = $value;
			$temp['use'] = 1;
			$contentArr = $this->handlerNoticeContentRecords(array('content'=>$content));
			foreach ($contentArr as $k=>$item){
				if ($item['field'] == 'keyword1'){
					$contentArr[$k]['value'] = $companys_data[$value['company_id']]['company_name'];
				} elseif ($item['field'] == 'keyword2'){
					$contentArr[$k]['value'] = $companys_data[$value['company_id']]['insured_number'];
				} elseif ($item['field'] == 'keyword3'){
					$contentArr[$k]['value'] = $companys_data[$value['company_id']]['insurance_amount'];
				}
			}
			$content_array = array();
			foreach( $contentArr as $key => $val) {
				$content_array[] = trim($val['key']) === '' ?
				$val['field'].self::BLACK_CUTTER.trim($val['value']) :
				$val['key'].self::BLACK_CUTTER.$val['field'].self::BLACK_CUTTER.trim($val['value']) ;
			}
			$temp['content'] = $this->handlerContentCUEnRecords($content_array);
			$temp['keyt'] = strtoupper(hash('md5',time().$value['company_id']));
			$temp['mold'] = 1;
			$companys_data[$value['company_id']]['keyt'] = $temp['keyt'];
			if (empty($companys_data[$value['company_id']]['openid'])) {
				$temp['state'] = 2;
				$temp['errcode'] = 400;
				$temp['errmsg'] = 'openid缺失';
				$temp['updated_at'] = time();
			} else {
				$success['success'][$value['company_id']] = $companys_data[$value['company_id']];
			}
			M('wx_notice_relation_user')->save($temp);
			$array[] = $temp;
		}
		if ($success){
			return $success;
		} else {
			return false;
		}
	}

	public function hasSendIngAction()
	{
		if (IS_POST) {
			$postdata = I('post.');
			$condition['notice_id'] = $postdata['id'];
			$condition['company_id'] = array('in',$postdata['companys']);
			$condition['object_id'] = 1;
			$condition['use'] = 1;
			$condition['state'] = array('eq',0);
			$result = M('wx_notice_relation_user')->where($condition)->count();
			if ($result > 0){
				$this->ajaxReturn(['error' =>1]);
			} else {
				$this->ajaxReturn(['error' =>0]);
			}
		}
	}

}