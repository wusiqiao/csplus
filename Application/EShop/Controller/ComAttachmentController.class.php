<?php

namespace EShop\Controller;

use Common\Lib\Controller\AttachmentController;

class  ComAttachmentController extends AttachmentController {
    public function indexAction(){

        $this->display("Public:attachment");
    }

    public function WrkAttachmentAction(){
        //$this->assign("user_type",session("user_type"));
        $this->display("Public:wrk_attachment");
    }

    protected function getUserId(){
        return $_SESSION["user_id"];
    }

    protected function getBranchId(){
        return SHOP_ID;
    }

	/**
	 * 备注记录汇总
	 */
	public function summaryAction()
	{
		$contract_id = I('contract_id');

		$wrkTaskPlans = M('wrkTaskPlan')
			->field('contract_id,task_name as name,attach_group')
			->where(array('contract_id'=>array('eq',$contract_id)))
			->select();
		foreach($wrkTaskPlans as $key=>$value){
			$wrkTaskPlans[$key]['module'] = 'WrkTaskPlan';
		}

		$data = array_merge($wrkTaskPlans);

		$this->assign('model',json_encode($data));

		$this->display("Public:summary");
	}

}