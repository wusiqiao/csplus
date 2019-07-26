<?php

namespace EShop\Model;

use Think\Model;



class ModelBase extends Model {

    public function getMaxBillNo($branch_id, $billdate_field = "bill_date", $billno_field = "bill_no", $serinal_size = 4, $condition = array()) {
        $condition[$billdate_field] = strtotime(date("Ymd"));
        $condition["branch_id"] = $branch_id;
        $max_datebill = $this->where($condition)->max($billno_field);
        return $this->incBillNo($max_datebill, $serinal_size);
    }

    public function getMaxNo($branch_id, $prefix = "", $no_field = "no", $serinal_size = 4, $condition = array()) {
        $condition["branch_id"] = $branch_id;
        $max_no = $this->where($condition)->max($no_field);
        if (empty($max_no)) {
            $result = $prefix . str_pad("1", $serinal_size, "0", STR_PAD_LEFT);
        } else {
            $last_val = substr($max_no, -$serinal_size, $serinal_size);
            $result = $prefix . str_pad(intval($last_val) + 1, $serinal_size, "0", STR_PAD_LEFT);
        }
        return $result;
    }

    public function incBillNo($max_datebill, $serinal_size = 4){
        if (empty($max_datebill)) {
            $result = date("Ymd") . str_pad("1", $serinal_size, "0", STR_PAD_LEFT);
        } else {
            $last_val = substr($max_datebill, -$serinal_size, $serinal_size);
            $result = date("Ymd") . str_pad(intval($last_val) + 1, $serinal_size, "0", STR_PAD_LEFT);
        }
        return $result;
    }
}
