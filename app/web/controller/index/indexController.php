<?php

class indexController extends BaseController
{

    public $initphp_list = array('abc', 'test');

    public function __construct()
    {
        parent::__construct();
    }

    public function run()
    {
        $this->ajax_return('1', '接口不存在');
    }

    public function abc()
    {

        $sql = "SELECT `user_id`,`user_name` FROM `user_users` WHERE `user_id` < 7";
        $ret1 = $this->_getUserDao()->query($sql);

        $this->ajax_return('0', '', $ret1);
    }

    public function test()
    {


        $this->request_curl('http://www.wh_3156miniapi.test/?a1=1&b1=2&d1=3&c1=4&time=1611717046&sign=13');
    }

    // 获取web_ads
    public function _getWebAdsDao()
    {
        return InitPHP::getDao('web_ads', 'admin');
    }

    // 用户
    public function _getUserDao() {
        return InitPHP::getDao('user', 'user');
    }

}
