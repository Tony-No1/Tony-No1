<?php

/**
 * ---------------------------------------------------------------
 * 应用环境
 * ---------------------------------------------------------------
 *     development  -   开发环境
 *     testing      -   测试环境
 *     production   -   生产环境
 */
define('ENVIRONMENT', 'development');

/**
 * ---------------------------------------------------------------
 * 错误报告级别
 * ---------------------------------------------------------------
 */
switch (ENVIRONMENT) {
    case 'development':
        error_reporting(E_ALL);
        ini_set('display_errors', 'On');
        break;

    case 'testing':
        if (version_compare(PHP_VERSION, '5.3', '>=')) {
            error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
        } else {
            error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_USER_NOTICE);
        }
        ini_set('display_errors', 'On');
        break;

    case 'production':
        error_reporting(0);
        ini_set('display_errors', 'Off');
        break;

    default:
        header('HTTP/1.1 503 Service Unavailable.', true, 503);
        echo 'The application environment is not set correctly.';
        exit(1);
        break;
}

date_default_timezone_set('Etc/GMT-8');
header('Content-Type:text/html; charset=utf-8');

// *代表允许任何网址请求【上线后删除】
header('Access-Control-Allow-Origin:*'); // *代表允许任何网址请求
header('Access-Control-Allow-Methods:POST,GET,OPTIONS,DELETE'); // 允许请求的类型
header('Access-Control-Allow-Credentials: true'); // 设置是否允许发送 cookies
header('Access-Control-Allow-Headers: Content-Type,Content-Length,Accept-Encoding,X-Requested-with, Origin'); // 设置允许自定义请求头的字段

define('ROOT', __DIR__);
define('APP_PATH', dirname(ROOT) . '/app');
define('EXTEND_PATH', dirname(APP_PATH) . '/extend');
define('WWW_PATH', dirname(APP_PATH) . '/www');
require APP_PATH . '/initphp/initphp.php';

require APP_PATH . '/conf/comm.conf.php';
session_start();
require APP_PATH . '/conf/www.conf.php';
InitPHP::import('library/helper/BaseController.php');
InitPHP::import('library/helper/BaseService.php');
InitPHP::import('library/helper/BaseDao.php');
InitPHP::import('library/helper/BaseMultiDao.php');
InitPHP::init();
