<?php

namespace ESAdmin\Controller;

use Think\Controller;

class IndexController extends Controller{
    public function indexAction(){
        $user = session(USER_SESSION_KEY);
        //在凭证模式下，刷新需要登录刷新身份,因为凭证模式下，商户自动身份变成客户
        if (session("environment") == "voucher") {
            session("environment", null);
            $userData = M("SysUser")->where("id=" . $user->userId)->find();
            if ($userData) {
                $user = D("SysUser")->getLoginUserInfo($userData);
                session(USER_SESSION_KEY, $user);
            }
        }
        //服务到期或暂停禁止进入
        if (!$user->isAdmin && $user->currBranchId) {
            $allow = true;
            $condition['branch_id'] = M("SysUser")->where("id = " . $user->userId)->getField("branch_id");
            $branch_role = M("SysBranch")->where("id = ".$condition['branch_id'])->getField("branch_role");
            if($branch_role == ROLE_ID_COMPANY_FREE){
                $allow = true;
            }else{
                $agreement = M("SysBranchAgreement")->where($condition)->count();
                if ($agreement) {
                    $where['is_valid'] = 0;
                    $where['end_date'] = array("elt", date("Y-m-d"));
                    $where['_logic'] = "or";
                    $condition['_complex'] = $where;
                    $count = M("SysBranchAgreement")->where($condition)->count();
                    if ($count != 0) {
                        $allow = false;
                    }
                } else {
                    $allow = false;
                }
            }
            if (!$allow) {
                $this->display("user_loss");
                die();
            }
        }
        $user_menus = getUserMenus();
        if(!$user->isAdmin){
            $this->hideMaterialCenter($user_menus);
        }
        if( $user->currBranchId != 33){
            $this->hideVoucherMenu($user_menus);
        }

        $this->assign("user_menus", $user_menus);
        $this->user_session = get_object_vars(session(USER_SESSION_KEY));
        //首页九宫格显示
        $user_menus_children = array_column($user_menus, "children");
        foreach ($user_menus_children as $k => $menus) {
            foreach ($menus as $menu) {
                $this->assign("show_" . $menu["url"], $menu["allow"]);
            }
        }
        
        if ($user->userType==USER_TYPE_COMPANY_MANAGER) {
            $userTypeValue = 'merchant';
        }else{
            $userTypeValue = 'customer';
        }
        //是否显示付款通知的红点提示
        $this->assign("receivables_notice",D("WrkReceivables")->getIsShowReceivablesNotice());
        $this->assign("receivables_notice",[]);
        $this->assign("userTypeValue",$userTypeValue);
        $this->assign("is_admin",$user->isAdmin);
        $this->assign('branchId', getBrowseBranchId());
        $this->assign('userType', $user->userType);
        $this->assign('userId', $user->userId);
        $this->display();
    }

    public function toggleComAction(){
        $this->user_session = get_object_vars(session(USER_SESSION_KEY));
        $this->display();
    }

    public function bodyAction(){
        $user_session = session(USER_SESSION_KEY);
        if (!$user_session->isPlatformUser) {
            $this->display();
        } else {
            $this->display("admin_body");
        }
    }

    public function _initialize(){
        if (!session(USER_SESSION_KEY)) {
            redirect(U("Login/index"));
        }
    }

    public function templateAction(){
        $this->display("Public:template");
    }

    public function uploadImagesAction(){
        $this->display("Public:upload_images");
    }

    public function attachmentAction($simple = null){
        if ($simple) {
            $this->display("Public:attachment_simple");
        } else {
            $this->display("Public:attachment");
        }
    }

	/**
	 * 备注记录汇总
	 */
	public function summaryAction()
	{
		$contract_id = I('contract_id');
		$attach_group = I('attach_group');
		$wrkTaskPlans = M('wrkTaskPlan')
			->field('contract_id,task_name as name,attach_group,0 as isThis')
			->where(array('contract_id'=>array('eq',$contract_id)))
			->select();
		foreach($wrkTaskPlans as $key=>$value){
			$wrkTaskPlans[$key]['module'] = 'WrkTaskPlan';
			if ($attach_group == $value['attach_group']){
				$wrkTaskPlans[$key]['isThis'] = 1;
			}
		}

		$data = array_merge($wrkTaskPlans);

		$this->assign('model',json_encode($data));

		$this->display("Public:summary");
	}

    public function showAttachmentAction($attach = ""){
        $attach_params = explode("_", $attach);
        if (count($attach_params) == 2) {
            $filter["group"] = $attach_params[0];
            $filter["id"] = $attach_params[1];
            $images = M("ComAttachment")->where($filter)->getField("images");
            $this->attachments = json_decode($images, true);
        }
        $this->display("Public:attachment_show");
    }


    //自动完成-拥有功能权限的用户
    public function queryModuleUsersAction(){
        $query = I("q");
        $module = I("module");
        $list = D("SysMenu")->queryModuleUsers($module, $query);
        $this->ajaxReturn($list);
    }

    /**
     * 隐藏素材中心
     * */
    private function hideMaterialCenter(&$user_menus){
        foreach($user_menus as $key => $user_menu){
            if($user_menu['name'] == '营销'){
                $children = isset($user_menu['children']) ? $user_menu['children'] : [];
                foreach($children as $i => $menu){
                    if($menu['url'] == 'MaterialCenter'){
                        unset($user_menus[$key]['children'][$i]);
                    }
                }
            }
        }
    }

    /**
     * 隐藏智能凭证
     * */
    public function hideVoucherMenu(&$user_menus){
        foreach($user_menus as $key => $user_menu){
            if($user_menu['name'] == '智能凭证'){
                unset($user_menus[$key]);
            }
        }
    }

    //获取是否显示付款通知的红点
    public function getShowRecNoticeAction(){
        $result = D("WrkReceivables")->getIsShowReceivablesNotice();
        $this->ajaxReturn($result);
    }

    public function tailoringAction(){
        $this->display("Public/tailoring");
    }
    public function attach_addgroupAction(){
        $this->display("Public/attach_addgroup");
    }
    public function attach_editgroupAction(){
        $this->assign('group_id',I("get.group_id"));
        $this->display("Public/attach_editgroup");
    }
    
}