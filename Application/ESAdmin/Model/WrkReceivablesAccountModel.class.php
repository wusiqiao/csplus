<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class WrkReceivablesAccountModel extends DataModel {
	protected $tableName = 'wrk_receivables_account';
    public function createWxAccount()
    {
    	$branch_id = getBrowseBranchId();
    	$user_session = session(USER_SESSION_KEY);
    	$account = M('WrkReceivablesAccount')->where(['branch_id'=>$branch_id,'is_wx'=>1])->find();
    	if (empty($account)) {
	    	$account_data = [];
	    	$account_data['name'] = '微信';
	    	$account_data['account'] = '--';
	    	$account_data['is_wx'] = 1;
	    	$account_data['status'] = 1;
	    	$account_data['branch_id'] = $branch_id;
	    	$account_data['creater_id'] = $user_session->userId;
	    	$account_data['update_id'] = $user_session->userId;	    	
	    	$account_data['update_time'] = time();
	    	$account_data['create_time'] = time();
	    	M('WrkReceivablesAccount')->add($account_data);
    	}
    	
    }

}