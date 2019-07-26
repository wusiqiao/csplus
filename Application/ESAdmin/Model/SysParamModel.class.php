<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;



class SysParamModel extends DataModel {

    public function getParamValue($name="") {
        $result = S(SYS_PARAM_CACHE_KEY);
        if (!$result) {
            $data_list = $this->callFilter(false)->where("1=1")->select();
            $result = array();
            //type:0:文本；1：数值；2：单选；3：多选;4 : 日期；5：图片
            foreach ($data_list as $value) {
                switch ($value["type"]) {
                    case 2:
                    case 3:
                        $value_list = explode(",", $value["value"]);
                        $enum_list = explode(";", $value["enums"]);
                        foreach ($enum_list as $enum) {
                            list($en_key, $en_text) = explode("=", $enum);
                            if (in_array($en_key, $value_list)) {
                                $item["selected"] = "selected";
                            } else {
                                $item["selected"] = "";
                            }
                            $item["key"] = $en_key;
                            $item["text"] = $en_text;
                            $result[$value["key"]][] = $item;
                        }
                        break;
                    default:
                        $result[$value["key"]] = $value["value"];
                        break;
                }
            }
            S(SYS_PARAM_CACHE_KEY, $result);
        }
        return ($name == "")?$result:$result[$name];
    }

    public function getParam($name) {
        
    }

}
