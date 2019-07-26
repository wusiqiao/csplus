<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class DistributionSettingModel extends DataModel {

    /**获取分销设定的服务个性化设置列表
     * @param $branch_id
     * @return mixed
     */
    public function getProductCommisions($branch_id){
        $result = $this->where("branch_id=$branch_id")->find();
        if ($result){
            $sql = "select (1-ISNULL(b.product_id)) as is_valid,p.id as product_id,p.product_title,b.commision_type,b.commision_rate,b.commision_amount,b.activity_start,b.activity_end
                      from com_product p left join distribution_product b on p.id=b.product_id where p.branch_id=$branch_id";
            $result["product_commisions"] = $this->query($sql);
        }else{
            $sql = "select id as product_id,product_title,0 as commision_type,'' as commision_rate,'' as commision_amount,'' as activity_start,'' as activity_end
                 from com_product where branch_id=$branch_id";
            $result["product_commisions"] = $this->query($sql);
        }
        return $result;

    }

    protected function _after_insert($data,$options) {
        $this->_insertDistributionProduct($data);
    }

    protected function _after_update($data,$options) {
        M("DistributionProduct")->where(array("branch_id"=>getBrowseBranchId()))->delete();
        $this->_insertDistributionProduct($data);
    }

    /**插入服务个性化佣金设置
     * @param $data
     */
    private function _insertDistributionProduct($data){
        if ($data["commision_type"] == COMMISION_TYPE_CUSTOM){
            $products = I("post.product");
            $product_commisions = array();
            foreach ($products as $product){
                $is_valid = I("post.prd_is_valid_$product");
                if ($is_valid){
                    $prd_data["branch_id"] = getBrowseBranchId();
                    $prd_data["commision_type"] = I("post.prd_commision_type_$product");
                    $prd_data["commision_rate"] = I("post.prd_commision_rate_$product");
                    $prd_data["commision_amount"] = I("post.prd_commision_amount_$product");
                    $prd_data["activity_start"] = strtotime(I("post.prd_activity_start_$product"));
                    $prd_data["activity_end"] = strtotime(I("post.prd_activity_end_$product"));
                    $prd_data["product_id"] = $product;
                    $product_commisions[] = $prd_data;
                }
            }
            if ($product_commisions){
                M("DistributionProduct")->addAll($product_commisions);
            }
        }
    }

    /**获取佣金设置
     * @param $branch_id
     * @return mixed|null未启用或时间失效返回null
     */
    public function getSettings($branch_id){
        $condition["branch_id"] = $branch_id;
        $result = $this->where($condition)->find();
        if ($result && $result["is_valid"]){
            $now = time();
            if ($result["commision_type"] == COMMISION_TYPE_CUSTOM){
                $product_settings = M("DistributionProduct")->where($condition)->select();
                foreach ($product_settings as $setting){
                    if (empty($setting["activity_end"]) || (($now > $setting["activity_start"]) && ($now < $setting["activity_end"]))){
                        $result["product_settings"][$setting["product_id"]] = $setting;
                    }
                }
            }else{
                //未开始或已结束
                if ($now < $result["activity_start"] || ($result["activity_end"]> 0 && $now > $result["activity_end"])){
                    $result = null;
                }
            }
            return $result;
        }
        return null;
    }


    /**获取有设置佣金的商品
     * @param $branch_id
     */
    public function getProductHasCommision($branch_id){
        $result = array();
        $commision_settings = $this->getSettings($branch_id);
        if ($commision_settings) {
            $product_attr_kvdatas = array();
            $condition["attr.branch_id"] = $branch_id;
            //获取服务的以及最小价格，如果未0，标为面议
            $product_attr_datas = M("ComOrderAttribute attr")
                ->join("inner join com_product product on product.id=attr.product_id")
                ->field("product_pic,product_id,product_title,min(attr.real_cash) as real_cash,'' as commision_amount_text,'' as cash_text")
                ->where($condition)->group("attr.product_id")->select();
            foreach ($product_attr_datas as $key=>$product_attr_data){
                if ($product_attr_data["real_cash"] == 0){
                    $product_attr_datas[$key]["cash_text"] = "面议";
                }else{
                    $product_attr_datas[$key]["cash_text"] = $product_attr_data["real_cash"]."元起";
                }
                $product_attr_kvdatas[$product_attr_data["product_id"]] = &$product_attr_datas[$key];
            }
            foreach ($product_attr_kvdatas as $key=>$product_data) {
                 if ($commision_settings["product_settings"]) { //按服务设定
                    $product_setting = $commision_settings["product_settings"][$key];
                    if ($product_setting) { //有佣金设定
                        if (intval($product_setting["commision_type"]) == COMMISION_TYPE_RATE) {
                            if ($product_data["real_cash"] == 0){
                                $commision_amount_text = $product_setting["commision_rate"]."%起";
                            }else{
                                $commision_amount_text = ($product_data["real_cash"] * $product_setting["commision_rate"] / 100)."元起";
                            }
                        } else {
                            $commision_amount_text = $product_setting["commision_amount"]."元"; //固定金额
                        }
                        $product_attr_kvdatas[$key]["commision_amount_text"] = $commision_amount_text;
                        $result[] = &$product_attr_kvdatas[$key];
                    }
                } else { //固定佣金设定
                    if (intval($commision_settings["commision_type"]) == COMMISION_TYPE_RATE) {
                        if ($product_data["real_cash"] == 0){
                            $commision_amount_text = $commision_settings["commision_rate"]."%起";
                        }else{
                            $commision_amount_text = ($product_data["real_cash"] * $commision_settings["commision_rate"] / 100)."元起";
                        }
                    } else {
                        $commision_amount_text = $commision_settings["commision_amount"]."元"; //固定金额
                    }
                    $product_attr_kvdatas[$key]["commision_amount_text"] = $commision_amount_text;
                    $result[] = &$product_attr_kvdatas[$key];
                }
            }
        }
        return $result;
    }
}
