<?php
namespace ESAdmin\Model;

class UserSessionModel {
    public $userId;
    public $userName;
    public $account;
    public $currBranchId;
    public $currBranchName;
    public $currBranchCode;
    public $currBranchType;
    public $parentBranchCode;
    public $parentBranchId;
    public $isAdmin;
    public $userType; //1:管理，2、公司管理 3：业务，4：客户
    public $is_leader; //是否商户（公司）管理员
    public $branchRole; //商城版本
    //管理的组织
    public $branchList;
    public $dacType;
    public $permissionList;
    public $dacBranchs;
    public $visiblers;//当登录用户数据查看范围为指定部门时，存储指定部门的所有人员
    public $userIdentity;//用户身份角色
    public $isPlatformUser; //是否平台用户，为了方便一些不必要的权限检查
}