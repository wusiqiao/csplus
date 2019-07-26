<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
// 应用入口文件
//
ini_set('display_errors',0);
// 检测PHP环境
if(version_compare(PHP_VERSION,'5.3.0','<'))  die('require PHP > 5.3.0 !');

// 绑定访问模块
define('BIND_MODULE','ESAdmin');
//define('BIND_MODULE','ESAdmin'); //CasBest//Estate//Voucher//AnJian//WeiXin//ESAdmin//EShop
//define('BIND_MODULE','EShop');
define("SITE_URL",'/Application/'.BIND_MODULE.'/');
define("JS_URL",SITE_URL."Public/js/");
define("CSS_URL",SITE_URL."Public/css/");
define("IMG_URL",SITE_URL."Public/images/");
// 开启调试模式 建议开发阶段开启 部署阶段注释或者设为false
define('APP_DEBUG',true);


// 定义应用目录
define('APP_PATH','Application/');

//define('BIND_MODULE','ECShop');

//RunTime
define('RUNTIME_PATH', './Runtime/'.BIND_MODULE.'/');

//定义网站根目录
define('WEB_PATH',dirname(__FILE__));

//商家ID
if ($_GET["bid"]){
	define("SHOP_ID", $_GET["bid"]);
}else{
	define("SHOP_ID", str_replace("eshop", "", current(explode(".", $_SERVER["HTTP_HOST"]))));
}
define("WEB_ROOT", (empty($_SERVER["HTTPS"])?"http://":"https://").$_SERVER["HTTP_HOST"]);
// 引入ThinkPHP入口文件
require './ThinkPHP/ThinkPHP.php';

// 亲^_^ 后面不需要任何代码了 就是如此简单