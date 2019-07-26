<?php

namespace EShop\Controller;

use Think\Controller;

class HoleController extends Controller {

    public function ErrorWebAction(){
        $this->display('404:404');
    }

}
