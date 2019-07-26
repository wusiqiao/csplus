<?php


namespace EShop\Controller;

use Think\Controller;

class StoreController extends CommonController {
     public function myAction(){
         $this->checkCompany();
         $store_data = M("ComStore")->field("remark")->where("branch_id=".$this->branch_id)->find();
//         $wxUser = getCurrentWXUserInfo(false);
         $this->setWXShareData();
         $this->assign("store_data", $store_data);
         $this->assign('title',getComStoreData('name'));
         $this->display("index");
     }
    private function setWXShareData(){
        $shopData = getComStoreData("all");
        $location = explode(',',$shopData['map_location']);
        if(count($location) == 2){
            $this->map = [
                'name' => $shopData['name'],
                'address' => $shopData['map_address'],
                'location_x' => $location[0],
                'location_y' => $location[1],
            ];
            $this->is_map = 1;
        }
        $signPackage          = getWeChatInstance()->getJsSign();

        $this->assign('signPackage', $signPackage);
//        $this->assign('wxUser', $wxUser);

    }
}

