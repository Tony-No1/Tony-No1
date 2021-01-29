<?php

define('CACHE_TYPE', 'FILE');    // 缓存类型  线上用MEM
define('CACHE_PREFIX', 'shop');   // 缓存KEY的前缀
define('CACHE_TIME', 30);     // 缓存时间
define('API_KEY', 'zzy0tqkVbjivo_Kmug0q&database=4');     // api KEY

/**
 * Controller配置
 */
$InitPHP_conf['ismodule'] = true; // 开启module方式
$InitPHP_conf['controller']['path'] = 'web/controller/';
$InitPHP_conf['controller']['module_list'] = array(
    'index'
); // module白名单

$InitPHP_conf['template']['template_path'] = 'web/template'; //模板路径
$InitPHP_conf['template']['template_type'] = 'php'; //模板文件类型
$InitPHP_conf['template']['template_c_path'] = 'data/template_c'; //模板编译路径

/**
 * 路由访问方式
 * 1. 如果为true 则开启path访问方式，否则关闭
 * 2. default：index.php?m=user&c=index&a=run
 * 3. rewrite：/user/index/run/?id=100
 * 4. path: /user/index/run/id/100
 * 5. html: user-index-run.htm?uid=100
 * 6. 开启PATH需要开启APACHE的rewrite模块，详细使用会在文档中体现
 */
$InitPHP_conf['isuri'] = 'rewrite';

/** Session */
$InitPHP_conf['session']['name'] = '3156miniapi';
$InitPHP_conf['session']['save_handler'] = 'redis';
$InitPHP_conf['session']['gc_maxlifetime'] = 604800;
$InitPHP_conf['session']['cookie_lifetime'] = 31536000;
$InitPHP_conf['session']['cookie_httponly'] = true;
$InitPHP_conf['session']['cookie_secure'] = false;



