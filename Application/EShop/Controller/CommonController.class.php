<?php

namespace EShop\Controller;

use Think\Controller;

Class CommonController extends Controller {

    protected $branch_id = null;
    
    protected function checkCompany() {
        $this->branch_id = getBrowseBranchId();
        if (empty($this->branch_id)) {
            redirect('/Hole/ErrorWeb');
            die();
        }
    }

}
