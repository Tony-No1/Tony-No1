<?php

/**
 * 是否开启调试
 */
$InitPHP_conf['is_debug'] = true; // 开启-正式上线请关闭
$InitPHP_conf['show_all_error'] = false; // 是否显示所有错误信息，必须在is_debug开启的情况下才能显示


/**
 * View配置
 */
$InitPHP_conf['template']['theme'] = 'default'; // 模板主题
$InitPHP_conf['template']['is_compile'] = true; // 模板每次编译-系统上线后可以关闭此功能

/**
 * 日志目录，非常重要
 * 记录全局的框架层面的异常ERROR
 * 记录使用logInit工具类报的异常错误日志
 */
$InitPHP_conf['log_dir'] = APP_PATH . '/data/log/'; // 日志目录,必须配置

/**
 * 缓存配置
 */
//$InitPHP_conf['memcache'][0] = array('127.0.0.1', '11211'); // Memcache缓存
//$InitPHP_conf['cache']['filepath'] = 'data/filecache';  // 文件缓存目录

/**
 * Redis配置
 */
$InitPHP_conf['redis']['default']['server'] = '127.0.0.1';
$InitPHP_conf['redis']['default']['port'] = '6379';
$InitPHP_conf['redis']['default']['password'] = '';

/**
 * 数据库配置
 */
$InitPHP_conf['db']['driver'] = 'mysqli';

$InitPHP_conf['db']['default']['db_type'] = 0; // 0-单个服务器，1-读写分离，2-随机
$InitPHP_conf['db']['default'][0]['host'] = '127.0.0.1:3306'; // 主机
$InitPHP_conf['db']['default'][0]['username'] = 'root'; // 数据库用户名
$InitPHP_conf['db']['default'][0]['password'] = ''; // 数据库密码
$InitPHP_conf['db']['default'][0]['database'] = 'wh_miniadmin'; // 数据库
$InitPHP_conf['db']['default'][0]['charset'] = 'utf8'; // 数据库编码   
$InitPHP_conf['db']['default'][0]['pconnect'] = 0; // 是否持久链接


/**
 * Cookie
 */
$InitPHP_conf['cookie']['name'] = '3156miniapi';
$InitPHP_conf['cookie']['key'] = 'ac89981c170fe2598ab21194a2f0cweh';
$InitPHP_conf['cookie']['expires'] = 0;

/**
 * Other
 */
$InitPHP_conf['table_prefix'] = ''; // 表前缀
