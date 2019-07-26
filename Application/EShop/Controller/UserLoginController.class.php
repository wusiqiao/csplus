<?php

namespace EShop\Controller;

use Think\Controller;

Class UserLoginController extends Controller {

    public  $user_branch;
    public function _initialize()
    {
        checkLogin();
        $this->user_branch = getBrowseBranchId();

        if(IS_GET) {
            $this->vesion = '100';
        }

    }
}
