<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class WxOperateTemplateModel extends DataModel {
    protected $tableName = 'wx_notice_template_library';
    protected $_link = array(
        "WxNoticeRelationUser" => array(
            "join_name" => "INNER",
            'class_name' => "WxNoticeRelationUser",
            'foreign_key' => 'id',
            'mapping_name' => 'relation',
            'mapping_fields' => 'mold,object_type,company_name,created_at,state,message_state,id',
            "mapping_key" => "notice_id"
        ),
        "SysUser" => array(
            "join_name" => "LEFT",
            'class_name' => "SysUser",
            'foreign_key' => 'relation.user_id',
            'mapping_name' => 'user',
            'mapping_fields' => 'name',
            "mapping_key" => "id"
        ),
        "SysBranch" => array(
            "join_name" => "LEFT",
            'class_name' => "SysBranch",
            'foreign_key' => 'relation.company_id',
            'mapping_name' => 'company',
            'mapping_fields' => 'name',
            "mapping_key" => "id"
        ),
    );
    public function templateAppendImplement($storage)
    {
        if ($storage->operation === 'append') {
            $result = $this->add($storage->{$storage->operation});
            if ($result) {
                if (is_array($storage->companys) && count($storage->companys) > 0) {
                    $append_relation =[];
                    foreach ($storage->companys as $val) {
                        $append_relation[] = [
                            'notice_id' => $result,
                            'company_id' => $val,
                            'object_type' => 1,
                            'use' => 0,
                            'state' => WX_TEMPLATE_SEND_DEFAULT,
                            'created_at' => time(),
                            'updated_at' => time()
                        ];
                    }
                    M('wx_notice_relation_user')->addAll($append_relation);
                }
                return $result;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    public function previewUpdateImplement($storage)
    {
            $storage->{$storage->operation}['updated_at'] = time();
            $result = M('WxNoticeTemplateLibrary')->save($storage->{$storage->operation});
            if ($result) {
                //删除 所有所选公司
                $condition['use'] = 0;
                $condition['notice_id'] = $storage->{$storage->operation}['id'];
                $condition['object_type'] = 1;
                $condition['state'] = WX_TEMPLATE_SEND_DEFAULT;
                M('wx_notice_relation_user')->where($condition)->delete();
                if (is_array($storage->companys) && count($storage->companys) > 0) {
                    $append_relation =[];
                    foreach ($storage->companys as $val) {
                        $append_relation[] = [
                            'notice_id' =>  $storage->{$storage->operation}['id'],
                            'company_id' => $val,
                            'object_type' => 1,
                            'use' => 0,
                            'state' => WX_TEMPLATE_SEND_DEFAULT,
                            'created_at' => time(),
                            'updated_at' => time()
                        ];
                    }
                    M('wx_notice_relation_user')->addAll($append_relation);
                }
                return true;
            } else {
                return false;
            }
    }
    public function getPreviewUpdateData($id)
    {
        $notice = M('WxNoticeTemplateLibrary')->where("id=$id")->find();
        if($notice){
            $condition['notice_id'] = $id;
            $condition['use'] = 0;
            $condition['object_type'] = 1;
            $companys = M('WxNoticeRelationUser')
                        ->where($condition)
                        ->getField('company_id',true);
            return compact('notice','companys');
        } else {
            return false;
        }
    }
    public function getCompanyOpenidsFromTemplate($id,$companys)
    {
        $condition['ntl.id'] = $id;
        $condition['nru.company_id'] = array('in',$companys);
        $result = M('WxNoticeTemplateLibrary')
            ->alias('ntl')
            ->field('user.openid,user.id as user_id,user.mobile,company.id as company_id,company.name as company_name')
            ->join('inner join wx_notice_relation_user as nru on nru.notice_id = ntl.id')
            ->join('inner join sys_branch as company on company.id = nru.company_id')
            ->join('inner join sys_user as user on user.id = company.customer_leader_id')
            ->where($condition)
            ->select();
        return $result;
    }

	/**
	 * @param $id 消息模板id
	 * @param $companys 选择的公司id
	 * @return mixed	数组
	 */
	public function getCompanyUserOpenidsFromTemplate($id,$companys)
	{
		$condition['ntl.id'] = $id;
		$condition['nru.company_id'] = array('in',$companys);
		$result = M('WxNoticeTemplateLibrary')
			->alias('ntl')
			->field('user.openid,user.id as user_id,user.mobile,company.id as company_id,company.name as company_name,company.insured_number,company.insurance_amount')
			->join('inner join wx_notice_relation_user as nru on nru.notice_id = ntl.id')
			->join('inner join sys_branch as company on company.id = nru.company_id')
			->join('inner join sys_user as user on user.id = nru.user_id')
			->where($condition)
			->select();
		return $result;
	}

    public function getPlanNotYetCount($id)
    {
        $condition['notice_id'] = $id;
        $condition['use'] = 1;
        $condition['object_type'] = 1;
        $condition['state'] = 1;
        $result_after = M('wx_notice_relation_user')->field('company_id')->where($condition)
                         ->group('company_id')->select();
        $condition['use'] = 0;
        $condition['state'] = 0;
        $result_before = M('wx_notice_relation_user')->where($condition)->count();
        return $result_before - count($result_after);
    }
    public function companySendTemplateFinally($finally)
    {
        if (isset($finally['success'])) {
            $this->hanlderCompanySendTemplateFinally($finally['success'],$finally['notice_id'],WX_TEMPLATE_SEND_SUCCESS);
        }
        if (isset($finally['error'])) {
            $this->hanlderCompanySendTemplateFinally($finally['error'],$finally['notice_id'],WX_TEMPLATE_SEND_ERROR);
        }
    }
    protected function hanlderCompanySendTemplateFinally($company,$notice_id,$state)
    {
        //判断是否有存在数据库中
        $condition['company_id'] = array('in',array_keys($company));
        $condition['notice_id'] = $notice_id;
        $condition['use'] = 1;
        $condition['object_type'] = 1;
        $condition['state'] = 0;
//        $condition['branch_id'] = $this->user_branch;
        $result = M('wx_notice_relation_user')->where($condition)->field('company_id,id')->select();
        if ($result) {
            foreach ($result as $key => $val) {
                //修改
                $save_relation['state'] = $state;
                $save_relation['id'] = $val['id'];
                $save_relation['errcode'] = $company[$val['company_id']]['errcode'];
                $save_relation['errmsg'] = getGlobalReturnCode($company[$val['company_id']]['errcode']);
//                var_dump($save_relation);die;
                M('wx_notice_relation_user')->save($save_relation);
            }
        }
    }


	/**
	 * @param $template_id	微信模板id
	 * @param $module		功能模块名
	 * @param $company_id	商户id
	 * @return array		返回的数据
	 */
	public function statisticalNotice($template_id,$module,$company_id){
		$year = date('Y');
		$month = date('m');

		$life_time = strtotime($year."-".$month);

		//获取商户负责人管理的客户公司id列表
		$list = D("SysUserModuleSetting")->getCompanysByModule($module, $keyword='',array(DAC_PERMIT_VALUE_COLLABORATOR,DAC_PERMIT_VALUE_LEADER));
		$company_id_list = array();
		foreach ($list as $val){
			$company_id_list[] = $val['id'];
		}
		if (empty($company_id_list)){
			return array();
		}

		$wherestr = "WHERE tempDate.company_id > 0";

		$bidstr = implode(",",$company_id_list);

		$sql = "SELECT *,count(tempDate.wnru_id) as message_num FROM(
				SELECT b.id AS company_id,b.`name` AS company_name,
				c.`name` AS customer_name,c.head_pic,c.mobile AS customer_mobile,c.is_follow,
				manaer.`name` AS sender_name,u.`name` AS upleader_name,
				wntl.template_id,wntl.id AS wntl_id,wntl.life,wnru.id as wnru_id,
				wnru.state,wnru.created_at,wnru.message_state,wnru.sure_time
				FROM sys_branch AS b
				LEFT JOIN wx_notice_template_library AS wntl ON wntl.template_id  = ".$template_id." AND wntl.branch_id = ".$company_id." AND wntl.life = '".$life_time."'
				LEFT JOIN wx_notice_relation_user AS wnru ON  wntl.id = wnru.notice_id AND wnru.company_id = b.id
				LEFT JOIN sys_user as c ON c.id = b.customer_leader_id
				LEFT JOIN sys_user_module_setting as upleader ON upleader.company_id = b.id AND upleader.type = ".DAC_SETTING_TYPE_BRANCH." AND upleader.permit_value = ".DAC_PERMIT_VALUE_NOTICER." AND upleader.module = '".$module."' AND upleader.branch_id = '".$company_id."'
				LEFT JOIN sys_user as u ON u.id = upleader.user_id
				LEFT JOIN sys_user_module_setting as ums ON ums.company_id = b.id AND ums.type = ".DAC_SETTING_TYPE_BRANCH." AND ums.permit_value = ".DAC_PERMIT_VALUE_LEADER." AND ums.module = '".$module."' AND ums.branch_id = '".$company_id."'
				LEFT JOIN sys_user as manaer ON manaer.id = ums.user_id
				WHERE b.type = 1 AND b.id IN (".$bidstr.")
				ORDER BY wnru.id DESC) AS tempDate
				".$wherestr."
				GROUP BY tempDate.company_id
				ORDER BY tempDate.company_id ASC
				";

		$list = M()->query($sql);
		$allCompanyNumber = count($list);
		$isSendNumber = 0;
		$unSendNumber = 0;
		$sendFailureNumber = 0;
		$confirmedNumber = 0;
		foreach($list as $value){
			switch ($value['state'])
			{
				case 1:$isSendNumber++;break;
				case 2:$sendFailureNumber++;break;
				default:$unSendNumber++;break;
			}
			if ($value['message_state'] == 2){
				$confirmedNumber++;
			}
		}
		$data = array('allCompanyNumber'=>$allCompanyNumber,
			'isSendNumber'=>$isSendNumber,
			'unSendNumber'=>$unSendNumber,
			'sendFailureNumber'=>$sendFailureNumber,
			'confirmedNumber'=>$confirmedNumber);
		return $data;
	}





}