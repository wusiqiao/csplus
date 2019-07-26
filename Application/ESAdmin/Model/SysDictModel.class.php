<?php

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;

class SysDictModel extends DataModel {
        public function getGroupValues($group) {
        $sql = "select a.id as value,a.name as text,a.querykey from sys_dict a "
                . " inner join sys_dict b on a.parent_id=b.id "
                . " where b.querykey='".$group."'";
        $list = $this->query($sql);
        return $list;
    }
}
