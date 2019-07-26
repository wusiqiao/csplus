<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019-04-08
 * Time: 13:38
 */

namespace ESAdmin\Model;

use Common\Lib\Model\DataModel;
use Think\Exception;

class VcrBillTemplatesModel extends DataModel{
    protected $tableName = 'vcr_bill_template';
    /**
     * @var 验证类型--必须
     * */
    const VALIDATE_REQUIRED = 'required';
    /**
     * @var 模板类型--银行对账单
     * */
    const TYPE_STATEMENT_10 = 10;
    /**
     * @var 模板类型--自开票
     * */
    const TYPE_SELF_OPEN_20 = 20;
    /**
     * @var 模板信息--银行对账单模板类型
     * */
    const TEMPLATE_STATEMENT = [
        'deal_time' => ['交易日期', self::VALIDATE_REQUIRED],
        'income' => ['贷方发生额（收入）', self::VALIDATE_REQUIRED],
        'disbursement' => ['借方发生额（支取）', self::VALIDATE_REQUIRED],
        'account' => ['对方(账号|户名)', self::VALIDATE_REQUIRED],
        'summary' => ['摘要'],
        'remarks' => ['备注'],
        'mark' => ['标记'],
    ];
    /**
     * @var 模板信息--自开票导入模板
     * */
    const TEMPLATE_SELF_OPEN = [
        'invo_no' => ['发票号码', self::VALIDATE_REQUIRED],
        'buyer' =>  ['购方企业名称', self::VALIDATE_REQUIRED],
        'goods_name' =>  ['商品名称', self::VALIDATE_REQUIRED],
        'invo_date' => ['开票日期', self::VALIDATE_REQUIRED],
        'unit' => ['单位',],
        'quantity' => ['数量',],
        'price' => ['单价', ],
        'amount' => ['金额', self::VALIDATE_REQUIRED],
        'tax_rate' => ['税率', ],
        'tax_amount' => ['税额', self::VALIDATE_REQUIRED],
    ];

    protected $_auto = [
        ['update_at', 'time', 3, 'function'], // 对update_time字段在更新的时候写入当前时间戳
    ];

    public function checkDataPermission($id, $branch_id = null){
        $branch_id = $branch_id ? $branch_id : getBrowseBranchId();
        return   $this->where(['id' => ['in', $id], 'branch_id' => $branch_id])->find();
    }
    /**
     * @param int $type 模板类型!
     * */
    public function getTemplates($type){
        $type = intval($type);
        switch(intval($type)){
            case self::TYPE_STATEMENT_10 :
                return self::TEMPLATE_STATEMENT;
                break;
            case self::TYPE_SELF_OPEN_20 :
                return self::TEMPLATE_SELF_OPEN;
                break;
            default :
                return false;
        }
    }

    public function _after_find(&$result, $options){
        $templates = json_decode($result['templates'], true);
        foreach ($templates as $field => $val) {
            $result[$field] = $val;
        }
    }
}