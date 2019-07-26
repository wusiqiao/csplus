<?php
require_cache('./system_store.inc');
return array(
    'LOAD_EXT_CONFIG' => 'db,version',
    'VAR_SESSION_ID'=>'access_token',
    'DEFAULT_MODULE'     => 'ESAdmin', //默认模块
    'SESSION_AUTO_START' => true, //是否开启session
    'URL_PARAMS_BIND'  =>  true,  //参数绑定到方法
    'URL_ROUTER_ON'   => true,
    'URL_MODEL' => URL_REWRITE,
    'ACTION_SUFFIX'   =>  'Action', // 操作方法后缀
    'LANG_SWITCH_ON' => true,
    'ERROR_PAGE' => __ROOT__.'/'.APP_PATH.'404.htm',
    'ADMIN_ALLOW_UPDATE' => true //超级用户是否有权限修改任何资料
);