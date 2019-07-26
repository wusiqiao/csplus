<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/* 财穗快线公众号 */
/*用户类型->对应用户查看范围*/
const USER_TYPE_SYSTEM_MANAGER = 1;//系统管理员
const USER_TYPE_COMPANY_MANAGER = 2;//商户员工
//const USER_TYPE_COMPANY_SALES = 3;//已经取消使用，商户都用USER_TYPE_COMPANY_MANAGER
const USER_TYPE_CUSTOMER = 4;//成交客户
const USER_TYPE_PROSPECTIVE = 5;//意向客户
const USER_TYPE_BUSINESS = 6;//推广伙伴
//define(USER_TYPE_CUSTOMER,3);//客户
/*用户预设角色*/
const ROLE_ID_SYSTEM_MANAGER = 1; //公司管理员 权限ID
const ROLE_ID_COMPANY_MANAGER = 2; //公司管理员 权限ID
const ROLE_ID_COMPANY_FREE = 3;//免费版本权限
const ROLE_ID_CUSTOMER = 4; //客户权限ID
const ROLE_ID_PLATFORM_MAX = 10; //平台内部最大使用角色ID（10以后的为商户自定义权限）

const VERSION_FREE_TEMPLATE_ID = 10;//免费模板对应的基本资料模板branch_id
/* Create March */
const PRO_CASHTYPE_PRICE = 0;//标价 - 产品
const PRO_CASHTYPE_NEGOTIABLE = 1;//面议 - 产品
const PRO_STEP_ON = 1;//服务流程 - 开启
const PRO_STEP_OFF = 0;//服务流程 - 关闭

/**
 * 商户用户身份角色分类
 */
const USR_IDENTITY_NORMAL = 0; //一般角色
const USR_IDENTITY_MANAGER = 1; //管理角色，可以看全部功能
const USR_IDENTITY_SUPPER = 2; //超级角色，可以看全部功能，也是平台指定的商户负责人
//红包
const TICKET_MIN_COUNT = 100;
const TICKET_CARD_STATE_NORMAL = 0;//红包卷状态 - 正常
const TICKET_CARD_STATE_GETED = 1;//红包卷状态 - 已领取
const TICKET_CARD_STATE_USED = 2;//红包卷状态 - 已使用
const TICKET_CARD_STATE_GIFTING = 3;//红包卷状态 - 转赠
const ACTIVITY_TYPE_SERVICE = 2;//红包卷状态 - 代金券
const TCP_SERVER_IP = "127.0.0.1";
const TCP_SERVER_PORT = 9556; //定时服务器端口
const PAY_STATUS_OPEN = 20;  //开启支付
const PAY_STATUS_DEFAULT = 10;  //开启支付,并且默认
const PAY_STATUS_CLOSE = 30; //关闭支付
const ADMIN_HOST = 'https://esadmin.caisuikx.com/';
//const WEB_ROOT = "http://esadmin.caisuikx.com";
//const SHOP_ROOT = "https://eshop.caisuikx.com";
const WEB_ROOT = "http://yyzs.caisuikx.com";
const SHOP_ROOT = "https://tshop.caisuikx.com";
define("TICKET_CARD_TYPE_DISCOUNT", 1); //红包卷类型 - 折扣
define("TICKET_CARD_TYPE_FULL_CUT", 0); //红包卷类型 - 满减

define("ACTIVITY_STATE_NOT_BEGIN",1);//活动状态 - 暂未开始
define("ACTIVITY_STATE_IS_BEGIN",2);//活动状态 - 活动进行中
define("ACTIVITY_STATE_IS_END",3);//活动状态 - 活动结束
define("ACTIVITY_STATE_IS_OVER",4);//活动状态 - 活动关闭
define("ACTIVITY_STATE_NOT_OVER",5);//活动状态 - 活动开启
define("ACTIVITY_TYPE_COMMONLY",0);//活动类型 - 普通红包
define("ACTIVITY_TYPE_FISSION",1);//活动类型 - 裂变红包

define("TICKET_OBJECT_ORDER",0);//红包券的使用对象 -- 服务订单
define("TICKET_OBJECT_TASK",1);//红包券的使用对象 -- 需求
/**
 * 短信模板
 */
define("SMS_REG_CODE", "SMS_10330761"); //注册验证码${code}
define("SMS_RESET_PASSWORD_CODE", "SMS_45620201"); //重置密码验证码${code}
define("SMS_INIT_PASSWORD", "SMS_45700056"); //业务初始密码${code}
define("SMS_CHANGE_MOBILE_CODE", "SMS_45560166"); //更换手机{code}
define("SMS_PAY_NOTICE", "SMS_45665172"); //付款通知${user}
define("SMS_BID_NOTICE", "SMS_45590113"); //中标通知${user}
define("SMS_USR_MESSAGE", "SMS_45685075"); //留言 ${user}
define("SMS_NEW_ORDER", "SMS_48810023"); //新需求 ${request}
define("SMS_NEW_ORDER_EX", "SMS_60225532"); //新需求1 ${request}
define("SMS_BIND_CARD", "SMS_69075189"); //绑定银行卡${request}

define("PRODUCT_STATE_ON_GROUNDING", 1);//产品状态 - 出售中
define("PRODUCT_STATE_DOWN_GEOUNDING", 0);//产品状态 - 已下架
define("PRODUCT_PRICE_TYPE_MARK", 0);//产品服务价格类型 - 标价
define("PRODUCT_PRICE_TYPE_NEGOTIABLE", 1);//产品服务价格类型 - 面议
define("USER_CUSTOMER_TAG", 106);

define("ORDER_PAY", 1); //用户已支付
define("ORDER_DONT_PAY", 0); //用户待付款
define("ORDER_STATE_USER_BUY", 3); //用户已购买
define("ORDER_STATE_SERVICE", 4); //服务中
define("ORDER_STATE_WAITING_CHECK", 5); //待验收
define("ORDER_STATE_APPLY_CLOSE", 6); //申请结束
define("ORDER_STATE_WAITING_JUDGE", 7); //评价
define("ORDER_STATE_CLOSED", 8); //关闭
define("ORDER_STATE_HAS_JUDGE", 9); //评价完成
define("SERVICE_ORDER_SN", 'SO_'); //订单号码前缀


define("FIN_USER_REFUND", -6); //用户退款
define("FIN_PROTIF_WITHDRAW", -5); //收益提现
define("FIN_SMS_PACKAGE", -4); //短信套餐
define("FIN_SERVICE_PACKAGE", -3); //服务套餐购买
define("FIN_UIZ_WITHDRAW", -2); //个人提现
define("FIN_CIZ_WITHDRAW", -1); //公司提现
define("FIN_CIZ_WITHDRAW_FLOW_TO_COMPANY", 0); //提现至公司账号
define("FIN_CIZ_RECHARGE", 1); //公司充值
define("FIN_UIZ_RECHARGE", 2); //个人充值
define("FIN_DIZ_RECHARGE", 3); //佣金入账
define("FIN_TASK_INCOME", 4); //服务商需求收款 --废除
define("FIN_TASK_BALANCE_PAY", 5); //客户余额付款  -- 废除
define("FIN_ORDER_PAY", 6); //服务产品订单-客户现金付款
define("FIN_ORDER_INCOME", 7); //服务产品订单-服务商需求收款
define("FIN_ORDER_BALANCE_PAY", 8); //服务产品订单-客户余额付款
define("FIN_TASK_LINE_PAY",9);//NEW DEC 29,2017 客户线下付款 - 需求
define("FIN_ORDER_LINE_PAY",10);//NEW DEC 29,2017客户线下付款 - 服务订单
define("FIN_PAYMENT",-7); //收款账户出款
define("FIN_PROMPT_BALANCE_PAY",-8); //缴费余额付款
define("FIN_INCOME",11); //收款账户入款
define("FIN_RECEIVABLES_CONFIRMED",12); //收款确认充值

define("FIN_PAY_WEIXIN", 0);
define("FIN_PAY_ALI", 1);
define("FIN_PAY_DISTRIBUTION",2);
define("FIN_PAY_OFFLINE", 9);
//会员奖励member_bounty
define("MEMBER_BOUNTY_TYPE_ASSURE", 0);//担保交易
define("MEMBER_BOUNTY_TYPE_COMPLETE", 1);//交易完成
define("MEMBER_BOUNTY_TYPE_FAIL", 2);//交易失败
define("MEMBER_BOUNTY_OBJECT_TYPE_TASK", 0);//需求
define("MEMBER_BOUNTY_OBJECT_TYPE_ORDER", 1);//服务
define("MEMBER_BOUNTY_OBJECT_TYPE_INVITATION", 2);//服务

define("COMMENTS_OBJERT_TYPE_ORDER", 1);//服务订单评论
/**
 * 需求表 -- 退款状态
 * @date New Jan 10,2018
 */
define("ORDER_REFUND_STATE_NO",0);//没有退款
define("ORDER_REFUND_STATE_BEGIN",1);//开始退款
define("ORDER_REFUND_STATE_COMPLETE",2);//成功退款
define("ORDER_REFUND_STATE_FAIL",3);//退款失败
/**
 * SysReport表
 */
define("REPORT_TOPIC_SYSTEM",0);
define("REPORT_TOPIC_FAKER",1);
define("REPORT_TOPIC_FLOWED",2);
define("REPORT_TOPIC_FLOWED_RESC",3);

/**
 * 佣金类型
 * 0，按比例，1：固定，2按服务定制
 */
define(COMMISION_TYPE_RATE, 0);
define(COMMISION_TYPE_FIXED, 1);
define(COMMISION_TYPE_CUSTOM, 2);
/**
 * 佣金来源
 */
define(COMMISION_FROM_SUBSCRIBE, 0);
define(COMMISION_FROM_REGISTER, 1);
define(COMMISION_FROM_ORDER, 2);

/**
 * 佣金冻结类型，0：手动，1：开始服务后N天，2：结束服务后N天
 */
define(COMMISION_FROZEN_TYPE_MANUAL, 0);
define(COMMISION_FROZEN_TYPE_STARTWORK, 1);
define(COMMISION_FROZEN_TYPE_FINISHWORK, 2);
/*
 * wx notice relation user
 */
define('WX_TEMPLATE_SEND_DEFAULT',0);
define('WX_TEMPLATE_SEND_SUCCESS',1);
define('WX_TEMPLATE_SEND_ERROR',2);
define('WX_TEMPLATE_NOTICE_SEND',1);
define('WX_TEMPLATE_NOTICE_PREVIEW',2);

/*
 * com_account_jurisdiction  various 字段使用
 */
define('CAJ_BRANCH_RECHARGE','comrecharge');//商户充值
define('CAJ_BRANCH_WITHDRAWAL','comwithdrawal');//商户提现
define('CAJ_BRANCH_CUSTOMER_CAPITAL','customercapital');//用户资金
define('CAJ_BRANCH_AGREEMENT','wrkagreement');//合同
define('CAJ_BRANCH_INVOICE','wrkinvoiceplan');//开票
/*
 * wx_template_currency_tip   various 字段使用
 */
define('TCT_RECHARGE_COMPLETE_NOTICE','recharge_complete_notice');
define('TCT_RECHARGE_REFUSE_NOTICE','recharge_refuse_notice');
define('TCT_WITHDRAWAL_COMPLETE_NOTICE','withdrawal_complete_notice');
define('TCT_WITHDRAWAL_REFUSE_NOTICE','withdrawal_refuse_notice');
define('TCT_USER_INCOME_COMPLETE_NOTICE','user_transfer_complete_notice');
define('TCT_COMPANY_INCOME_COMPLETE_NOTICE','company_income_complete_notice');
define('TCT_DISTRIBUTION_COMPLETE_NOTICE','distribution_complete_notice');
define('TCT_BRANCH_RECHARGE_COMPLETE_NOTICE','branch_recharge_complete_notice');//客户充值发送给商户的通知标题
define('TCT_BRANCH_RECHARGE_REFUSE_NOTICE','branch_recharge_refuse_notice');//客户充值发送给商户的通知标题
define('TCT_BRANCH_WITHDRAWAL_COMPLETE_NOTICE','branch_withdrawal_complete_notice');//客户提现发送给商户的通知标题
define('TCT_BRANCH_WITHDRAWAL_REFUSE_NOTICE','branch_withdrawal_refuse_notice');//客户提现发送给商户的通知标题

/*
 * 合同 wx_template_currency_tip   various 字段使用
 */
define('TCT_AGREEMENT_UPDATE_MONEY_NOTICE','agreement_update_money_notice');
define('TCT_BRANCH_AGREEMENT_UPDATE_MONEY_NOTICE','branch_agreement_update_money_notice');
/*
 * 开票 wx_template_currency_tip   various 字段使用
 */
define("TCT_INVOICE_NOTICE","new_invoice_notice");
define("TCT_BRANCH_INVOICE_NOTICE","branch_new_invoice_notice");
define("TCT_CANCEL_INVOICE_NOTICE","cancel_invoice_notice");
define("TCT_BRANCH_CANCEL_INVOICE_NOTICE","branch_cancel_invoice_notice");
define("TCT_CANCEL_APPLY_NOTICE","cancel_apply_notice");
define("TCT_BRANCH_CANCEL_APPLY_NOTICE","branch_cancel_apply_notice");
define("TCT_FINISH_INVOICE_NOTICE","finish_invoice_notice");
define("TCT_BRANCH_FINISH_INVOICE_NOTICE","branch_finish_invoice_notice");

/*
 * 收款 wx_template_currency_tip   various 字段使用
 * */
define("TCT_OVERDUE_FREEZE_ASSIGNMENT_DAY","overdue_freeze_assignment_day");//逾期自动冻结任务延期天数
define("TCT_OFFLINE_PAYMENT_ARTIFICIAL_NOTICE","offline_payment_artificial_notice");//客户端线下付款成功 人工通知
define("TCT_BRANCH_OFFLINE_PAYMENT_ARTIFICIAL_NOTICE","branch_offline_payment_artificial_notice");//商户端线下付款成功 人工
define("TCT_BALANCE_PAYMENT_AUTOMATIC_NOTICE","balance_payment_automatic_notice");//客户端余额付款成功 自动
define("TCT_BRANCH_BALANCE_PAYMENT_AUTOMATIC_NOTICE","branch_balance_payment_automatic_notice");//商户端余额付款成功 自动
define("TCT_REFUND_AUTOMATIC_NOTICE","refund_automatic_notice");//客户端 退款通知
define("TCT_BRANCH_REFUND_AUTOMATIC_NOTICE","branch_refund_automatic_notice");//商户端 退款通知
define("TCT_BAD_DEBT_AUTOMATIC_NOTICE","bad_debt_automatic_notice");//客户端 坏账通知
define("TCT_BRANCH_BAD_DEBT_AUTOMATIC_NOTICE","branch_bad_debt_automatic_notice");//商户端 坏账通知
define("TCT_FREEZE_AUTOMATIC_NOTICE","freeze_automatic_notice");//客户端 冻结通知
define("TCT_BRANCH_FREEZE_AUTOMATIC_NOTICE","branch_freeze_automatic_notice");//商户端 冻结通知

/*
 * 催款 wx_template_currency_tip   various 字段使用
 * */
define("TCT_AUTOMATIC_RENEWAL_NOTICE","automatic_renewal_notice");//客户端 自动续费
define("TCT_BRANCH_AUTOMATIC_RENEWAL_NOTICE","branch_automatic_renewal_notice");//商户端 自动续费
define("TCT_MANUAL_RENEWAL_NOTICE","manual_renewal_notice");//客户端 手动续费
define("TCT_BRANCH_MANUAL_RENEWAL_NOTICE","branch_manual_renewal_notice");//商户端 手动续费

/*
 * 收款账户 wx_template_currency_tip   various 字段使用
 * */
define("TCT_DELETE_RECEIPT_RECORD_NOTICE","delete_receipt_record_notice");//删除收款记录 客户
define("TCT_BRANCH_DELETE_RECEIPT_RECORD_NOTICE","branch_delete_receipt_record_notice");//商户
