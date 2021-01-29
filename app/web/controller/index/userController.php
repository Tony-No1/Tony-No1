<?php

require_once EXTEND_PATH . "/WeiXin/wxCode.php"; // 加载小程序二维码生成类

class userController extends BaseController {

    public $initphp_list = array('code_openid', 'openid_user', 'add_order',
        'add_account', 'edit_account', 'order_list', 'team_order_list', 'team_list',
        'mycode', 'withdraw', 'withdraw_list', 'income', 'center', 'prerogative', 'get_account');
    private static $InitPHP_conf; // 配置信息

    public function __construct() {
        parent::__construct();

        // 获取配置文件
        $this->InitPHP_conf = InitPHP::getConfig();
    }

    public function run() {
        $this->ajax_return('1', '接口不存在');
    }

    /**
     * 特权
     */
    public function prerogative() {
        // 查询团队合伙人等级和人数
        // 获取有效一级合伙人
        $sql = "SELECT `id` FROM `user` WHERE `fid` = '{$this->user_info['id']}'";
        $ret1 = $this->_getUserDao()->query($sql);
        $one = 0; // 有效一级合伙人
        $one_c = 0; // 有效一级中级合伙人
        foreach ($ret1 as $k => $v) {
            $tmp = $this->user_grade($v['id']);
            if ($tmp == 2) {
                $one_c++;
            }
            $one++;
        }

        // 获取有效二级合伙人
        $sql = "SELECT `id` FROM `user` WHERE `fid` IN(SELECT `id` FROM `user` WHERE `fid` = '{$this->user_info['id']}')";
        $ret2 = $this->_getUserDao()->query($sql);
        $two = 0; // 有效二级合伙人
        $two_c = 0; // 有效二级中级合伙人
        foreach ($ret2 as $k => $v) {
            $tmp = $this->user_grade($v['id']);
            if ($tmp == 2) {
                $two_c++;
            }
            $two++;
        }

        $data = array(
            'bk_num' => $this->user_info['bk_num'], // 合伙人等级   0=合伙人   1=中级合伙人   2=高级合伙人
            'str' => "注：三个目标进度达到100%后，即可成为中级合伙人，联系导师快速升级更高等级合伙人",
            'data' => array(
                '0' => array(
                    'title' => '合伙人特权',
                    'a' => '隐藏优惠券<br>大额优惠券免费领',
                    'b' => "自购奖励<br>自购奖励{$this->user_info['bk']['val2']}%",
                    'c' => "邀请好友<br>好友下单可享{$this->user_info['bk']['val3']}%奖励",
                    'd' => '专属导师<br>官方群一对一指导',
                //'e' => $this->user_info['bk']['str'],
                ),
                '1' => array(
                    'title' => '中级合伙人特权',
                    'a' => '隐藏优惠券<br>大额优惠券免费领',
                    'b' => "自购奖励<br>自购奖励{$this->bk_data[1]['val2']}%",
                    'c' => '团队奖励<br>额外收益翻20倍',
                    'd' => "邀请好友<br>好友下单可享{$this->bk_data[1]['val3']}%奖励",
                    'e' => "任务量：" . $this->bk_data[1]['str'],
                    'f' => "您的邀请量：有效直邀合伙人{$one}，有效间邀合伙人{$two}",
                ),
                '2' => array(
                    'title' => '高级合伙人特权',
                    'a' => '隐藏优惠券<br>大额优惠券免费领',
                    'b' => "自购奖励<br>自购奖励{$this->bk_data[2]['val2']}%",
                    'c' => '团队奖励(无限级)<br>额外收益翻40倍',
                    'd' => "邀请好友<br>好友下单可享{$this->bk_data[2]['val3']}%奖励",
                    'e' => "任务量：" . $this->bk_data[2]['str'],
                    'f' => "您的邀请量：有效直邀中级合伙人{$one_c}，有效间邀中级合伙人{$two_c}",
                ),
            )
        );

        $this->ajax_return('0', '', $data);
    }

    /**
     * 我的专属二维码
     */
    public function mycode() {
        $key = 'my_' . $this->user_info['id'];
        $this->getRedis()->redis()->select(7);
        $cache_data = $this->getRedis()->get($key);

        if (!$cache_data) {
            // 生成二维码
            $wxcode = new wxCode();
            $wx_data = array(
                'path' => $this->InitPHP_conf['mini']['index'],
                'scene' => 'userid=' . $this->user_info['id'],
            );
            $ret = array('img' => $wxcode->getWxcode1($wx_data));

            $cache_data = $ret;
            $this->getRedis()->set($key, $cache_data, 86400); // 缓存120秒
        }

        $data = array(
            'bg' => $this->InitPHP_conf['mini']['codeurl'] . 'img/mycodeimgbgm.png', // 背景图
            'code' => $cache_data,
        );

        $this->ajax_return('0', '', $data);
    }

    /**
     * 提现账号查询
     */
    public function get_account() {
        // 查询提现账号信息
        $sql = sprintf(
                "SELECT * FROM `user_account` WHERE `uid` ='%s' LIMIT 1", $this->user_info['id']
        );
        $tmp = $this->_getUserAccountDao()->query($sql);
        if (!isset($tmp[0]['id'])) {
            $this->ajax_return('1', '请先添加提现账号');
        }

        $this->ajax_return('0', '', $tmp[0]);
    }

    /**
     * 申请提现
     */
    public function withdraw() {
        $money = floatval($this->controller->get_gp('money'));
        if ($money < 1) {
            $this->ajax_return('1', '提现金额必须大于1元');
        }
        // 判断可提现金额
        $a_data = $this->_getUserDao()->get($this->user_info['id']);
        $money_a = floatval($a_data['money']);
        if ($money > $money_a) {
            $this->ajax_return('1', '余额不足，请修改提现金额');
        }
        // 查询提现账号信息
        $sql = sprintf(
                "SELECT * FROM `user_account` WHERE `uid` ='%s' LIMIT 1", $this->user_info['id']
        );
        $tmp = $this->_getUserAccountDao()->query($sql);
        if (!isset($tmp[0]['id'])) {
            $this->ajax_return('1', '请先添加提现账号');
        }
        $data = array(
            'money' => $money,
            'no' => $tmp[0]['no'],
            'name' => $tmp[0]['name'],
            'uid' => $this->user_info['id'],
            'time' => time(),
            'update_time' => 0,
        );
        $abc = $this->_getUserWithdrawDao()->add($data);
        if (!$abc) {
            $this->ajax_return('1', '申请提现失败，请稍后再试');
        }

        $this->ajax_return('0', '申请提现成功，审核后两小时内到账');
    }

    /**
     * 提现记录
     */
    public function withdraw_list() {
        $page = (int) $this->controller->get_gp('page'); // 页码
        $perpage = (int) $this->controller->get_gp('perpage'); // 分页大小

        $data['uid'] = $this->user_info['id'];

        $list = $this->_getUserWithdrawDao()->getList($page, $perpage, $data, array('time desc'));

        $this->ajax_return('0', '', $list ? $list : array());
    }

    /**
     * 收入记录
     */
    public function income() {
        $page = (int) $this->controller->get_gp('page'); // 页码
        $perpage = (int) $this->controller->get_gp('perpage'); // 分页大小

        $data['uid'] = $this->user_info['id'];

        $list = $this->_getUserIncomeDao()->getList($page, $perpage, $data, array('time desc'));

        $this->ajax_return('0', '', $list ? $list : array());
    }

    /**
     * 个人中心
     */
    public function center() {
        // 每月25号结算上月已收货订单

        $keys = md5('center_' . $this->user_info['id']);
        $this->getRedis()->redis()->select(0);
        $cache_data = $this->getRedis()->get($keys);

        $js = strtotime(date('Y-m') . '-25 00:00:00');
        if (time() > $js && !$cache_data) {
            // 查询收入表，将所有未结算进用户余额的查询出来并且修改结算状态
            $sql = "SELECT SUM(`money`) AS `total`, GROUP_CONCAT(`id`) AS `ids` FROM `user_income` WHERE `uid` ='{$this->user_info['id']}' AND `status` = 1 AND `checkout` = 0 AND PERIOD_DIFF(date_format(now(), '%Y%m'),date_format(from_unixtime(time), '%Y%m'))=1";
            $ret = $this->_getUserIncomeDao()->query($sql);
            $total = floatval($ret[0]['total']);
            if ($ret[0]['ids']) {
                // 修改结算状态
                $sql = "UPDATE `user_income` SET `checkout` = 1 WHERE `id` IN ({$ret[0]['ids']})";
                $this->_getUserIncomeDao()->query($sql);
                // 增加余额
                $sql = "UPDATE `user` SET `money` = `money` + {$total} WHERE `id` = '{$this->user_info['id']}'";
                $this->_getUserDao()->query($sql);
            }
            $this->getRedis()->set($keys, '1', 600); // 缓存一小时，不用时时刻刻去查询这个结算
        }

        // 查询提现表
        $sql = "SELECT SUM(`money`) AS `total` FROM `user_withdraw` WHERE `uid` ='{$this->user_info['id']}' AND `status` = 0"; // 查询待审核
        $ret_ds = $this->_getUserWithdrawDao()->query($sql);
        $money_ds = floatval($ret_ds[0]['total']);
        // 查询审核通过的提现申请，并且扣除余额
        $sql = sprintf(
                "SELECT SUM(`money`) AS `total`, GROUP_CONCAT(`id`) AS `ids` FROM `user_withdraw` WHERE `uid` ='%s' AND `status` = 1 AND `checkout` = 0", $this->user_info['id']
        );
        $ret_ok = $this->_getUserWithdrawDao()->query($sql);
        $money_ok = floatval($ret_ok[0]['total']);
        if ($ret_ok[0]['ids']) {
            // 修改结算状态
            $sql = "UPDATE `user_withdraw` SET `checkout` = 1 WHERE `id` IN ({$ret_ok[0]['ids']})";
            $this->_getUserIncomeDao()->query($sql);
            // 扣除余额
            $sql = "UPDATE `user` SET `money` = `money` - {$money_ok} WHERE `id` = '{$this->user_info['id']}'";
            $this->_getUserDao()->query($sql);
        }

        $a_data = $this->_getUserDao()->get($this->user_info['id']);
        $money = floatval($a_data['money']) - $money_ds;
        unset($ret, $sql);

        // 今日预估      
        $sql = sprintf(
                "SELECT SUM(`money`) AS `total` FROM `user_income` WHERE `uid` ='%s' AND `status` < 2 AND date(now()) = date(from_unixtime(`time`))", $this->user_info['id']
        );
        $ret = $this->_getUserIncomeDao()->query($sql);
        $c = floatval($ret[0]['total']);
        unset($ret, $sql);

        // 本月预估      
        $sql = "SELECT SUM(`money`) AS `total` FROM `user_income` WHERE `uid` ='{$this->user_info['id']}' AND `status` < 2 AND DATE_FORMAT(from_unixtime(time),'%Y%m') = DATE_FORMAT(CURDATE(),'%Y%m')";
        $ret = $this->_getUserIncomeDao()->query($sql);
        $d = floatval($ret[0]['total']);
        unset($ret, $sql);

        // 上月预估      
        $sql = "SELECT SUM(`money`) AS `total` FROM `user_income` WHERE `uid` ='{$this->user_info['id']}' AND `status` < 2 AND PERIOD_DIFF(date_format(now(), '%Y%m'),date_format(from_unixtime(time), '%Y%m'))=1";
        $ret = $this->_getUserIncomeDao()->query($sql);
        $e = floatval($ret[0]['total']);
        unset($ret, $sql);

        $data = array(
            'a' => sprintf("%01.2f", $money), // 可提现  
            'b' => '每月25号提现上月确认收货的订单收入', // 描述  
            'c' => sprintf("%01.2f", $c), // 今日预估  
            'd' => sprintf("%01.2f", $d), // 本月预估  
            'e' => sprintf("%01.2f", $e), // 上月预估  
            'f' => 'sqxgjlm', // 导师微信号  
            'g' => '温馨提示：所有的商品信息均来源于淘宝（天猫）/拼多多/京东，您的所有交易均在淘宝（天猫）/拼多多/京东进行，请放心使用。', // 温馨提示  
            'h' => '1', // 是否开启淘宝   0=否  1=是 
        );

        $this->ajax_return('0', '', $data);
    }

    /**
     * 根据小程序code获取openid
     */
    public function code_openid() {
        $code = $this->controller->get_gp('code');
        // 判断是否为分享
        $parm = $this->controller->get_gp(array(
            'pid', // 推广位id
            'id', // 商品ID 
            'source', // 分享类型  0代表拼多多 1代表天猫 2代表京东
            'uid', // 分享者ID 
        ));

        if (!$code) {
            $this->ajax_return('1', 'code不能为空');
        }

        $url = $this->InitPHP_conf['mini']['api'] . '?appid=' . $this->InitPHP_conf['mini']['appid'] . '&secret=' . $this->InitPHP_conf['mini']['secret'] . '&js_code=' . $code . '&grant_type=authorization_code';
        $data = $this->request_curl($url);
        $tmp = json_decode($data, true);
        if (!isset($tmp['openid'])) {
            $this->ajax_return('1', '获取openid失败');
        }
        $data = array(
            'session_key' => $tmp['session_key'],
            'openid' => $tmp['openid'],
        );

        // 判断openid是否已经注册
        $sql = sprintf(
                "SELECT `id` FROM `user` WHERE `openid` ='%s'", $data['openid']
        );
        $ret = $this->_getUserDao()->query($sql);
        $is_new = 0;
        if (!$ret[0]['id']) {
            $add = array(
                'fid' => (int) $parm['uid'], // 父级ID
                'openid' => $data['openid'], // openid
                'name' => '神秘人', // 昵称
                'img' => '', // 头像
                'time' => time(), // 创建时间
                'update_time' => time(), // 修改时间
                'last_time' => time(), // 最后一次请求时间
                'ip' => $this->getRealIp(), // ip
                'status' => 0, // 状态   0=正常  1=禁用            
            );
            $abc = $this->_getUserDao()->add($add);
            if (!$abc) {
                $this->ajax_return('1', '更新资料失败，请稍后再试');
            }

            // 记录分享表
            $is_new = 1;
        }

        $sql = sprintf(
                "SELECT `id`,`name`,`img` FROM `user` WHERE `openid` ='%s'", $data['openid']
        );
        $ret = $this->_getUserDao()->query($sql);
        $data['userinfo'] = array(
            'id' => $ret[0]['id'],
            'name' => $ret[0]['name'],
            'img' => $ret[0]['img'],
            'tb' => '1', // 是否开启淘宝   0=否  1=是 
        );

        if (isset($parm['source']) && $parm['uid'] > 0) {
            $share_data = array(
                'type' => $parm['source'],
                'uid' => $ret[0]['id'],
                'share_uid' => $parm['uid'],
                'gid' => $parm['id'],
                'time' => time(),
            );
            if ($is_new === 1) {
                $share_data['is_new'] = 1;
            }
            $this->_getUserShareDao()->add($share_data);
        }


        $this->ajax_return('0', '', $data);
    }

    /**
     * openid交换用户信息
     */
    public function openid_user() {
        $parm = $this->controller->get_gp(array(
            'name', // 昵称
            'img', // 头像  
        ));

        $openid = $this->controller->get_gp('openid'); // openid
        // 判断openid是否已经注册
        $sql = sprintf(
                "SELECT `id` FROM `user` WHERE `openid` ='%s'", $openid
        );
        $ret = $this->_getUserDao()->query($sql);
        if ($ret[0]['id']) {
            $update = array(
                'last_time' => time(), // 最后一次请求时间         
            );
            if ($parm['name']) {
                $update['name'] = $parm['name']; // 昵称
            }
            if ($parm['img']) {
                $update['img'] = $parm['img']; // 头像
            }
            if ($parm['name'] || $parm['img']) {
                $update['update_time'] = time(); // 修改时间
            }
            $tmp = $this->_getUserDao()->update($ret[0]['id'], $update);
            if (!$tmp) {
                $this->ajax_return('1', '更新资料失败，请稍后再试');
            }
        } else {
            $this->ajax_return('1', 'openid不存在');
        }

        $userinfo = $this->_getUserDao()->get($ret[0]['id']);
        $data['userinfo'] = array(
            'id' => $userinfo['id'],
            'name' => $userinfo['name'],
            'img' => $userinfo['img'],
        );

        $this->ajax_return('0', '', $data);
    }

    /**
     * 录入订单
     */
    public function add_order() {
        // 判断是否为分享
        $parm = $this->controller->get_gp(array(
            'dd_type', // 订单类型    0=拼多多， 1=淘宝， 2=京东
            'dd_no', // 订单编号 
        ));

        if (!$parm['dd_no']) {
            $this->ajax_return('1', '订单编号不能为空');
        }

        // 判断订单是否存在
        $sql = sprintf(
                "SELECT `id` FROM `user_order` WHERE `dd_no` ='%s' AND `type`='%s'", $parm['dd_no'], $parm['dd_type']
        );
        $ret = $this->_getUserDao()->query($sql);
        if ($ret[0]['id']) {
            $this->ajax_return('1', '订单已经录入，请勿重复录入');
        }
        $data = array(
            'type' => (int) $parm['dd_type'],
            'dd_no' => $parm['dd_no'],
            'uid' => $this->user_info['id'],
            'create_time' => time(),
        );
        $abc = $this->_getUserOrderDao()->add($data);
        if (!$abc) {
            $this->ajax_return('1', '录入订单失败，请稍后再试');
        }

        $this->ajax_return('0', '录入订单成功');
    }

    /**
     * 录入用户提现账户
     */
    public function add_account() {
        // 判断是否为分享
        $parm = $this->controller->get_gp(array(
            'name', // 姓名
            'no', // 账号 
        ));

        if (!$parm['name'] || !$parm['no']) {
            $this->ajax_return('1', '姓名、账号不能为空');
        }

        // 判断账号是否存在
        $sql = sprintf(
                "SELECT `id` FROM `user_account` WHERE `no` ='%s' OR `uid`='%s'", $parm['no'], $this->user_info['id']
        );
        $ret = $this->_getUserAccountDao()->query($sql);
        if ($ret[0]['id']) {
            $this->ajax_return('1', '账号已经存在，请勿重复添加');
        }

        $data = array(
            'name' => $parm['name'],
            'no' => $parm['no'],
            'uid' => $this->user_info['id'],
            'time' => time(),
            'update_time' => time(),
        );
        $abc = $this->_getUserAccountDao()->add($data);
        if (!$abc) {
            $this->ajax_return('1', '添加失败，请稍后再试');
        }

        $this->ajax_return('0', '添加成功', $data);
    }

    /**
     * 修改用户提现账户
     */
    public function edit_account() {
        // 判断是否为分享
        $parm = $this->controller->get_gp(array(
            'name', // 姓名
            'no', // 账号 
        ));

        if (!$parm['name'] || !$parm['no']) {
            $this->ajax_return('1', '姓名、账号不能为空');
        }

        // 判断账号是否存在
        $sql = sprintf(
                "SELECT `id` FROM `user_account` WHERE `uid` ='%s'", $this->user_info['id']
        );
        $ret = $this->_getUserAccountDao()->query($sql);
        if (!$ret[0]['id']) {
            $this->ajax_return('1', '账号不存在，无法修改');
        }
        $id = $ret[0]['id'];

        // 判断账号是否存在
        $sql = sprintf(
                "SELECT `id` FROM `user_account` WHERE `no` ='%s'", $parm['no']
        );
        $ret = $this->_getUserAccountDao()->query($sql);
        if ($ret[0]['id'] && $ret[0]['id'] != $id) {
            $this->ajax_return('1', '账号已经存在，无法修改');
        }

        $data = array(
            'name' => $parm['name'],
            'no' => $parm['no'],
            'update_time' => time(),
        );
        $abc = $this->_getUserAccountDao()->update($id, $data);
        if (!$abc) {
            $this->ajax_return('1', '修改失败，请稍后再试');
        }

        $this->ajax_return('0', '修改成功', $data);
    }

    /**
     * 我的订单列表
     */
    public function order_list() {
        $page = (int) $this->controller->get_gp('page'); // 页码
        $perpage = (int) $this->controller->get_gp('perpage'); // 分页大小
        $status = $this->controller->get_gp('status'); // 订单状态 all标识全部  0=已付款 1=已结算  2=已失效
//        $data['uid'] = $this->user_info['id'];
//        if ($status !== 'all') {
//            $data['status'] = (int) $status;
//        }
//
//        $list = $this->_getUserOrderDao()->getList($page, $perpage, $data, array('time desc'));     
        $where = "`time` > 0 AND `uid` = '{$this->user_info['id']}'";
        if ($status !== 'all') {
            $status = (int) $status;
            $where.= " AND `status` = '{$status}'";
        }
        $list = $this->_getUserOrderDao()->getListWhere($page, $perpage, $where, array('time desc'));
        unset($tmp);

        $this->ajax_return('0', '', array('list' => $list ? $list : array(), 'msg' => '确认收货后次月25日之前到账'));
    }

    /**
     * 团队列表
     */
    public function team_list() {
        $type = (int) $this->controller->get_gp('type'); // 类型  // 1=邀请我的人  2=团队

        if ($type === 1) { // 邀请我的人
            if ($this->user_info['fid'] == 0) {
                $ret[] = array(
                    'name' => '省钱小管家官方',
                    'img' => $this->InitPHP_conf['mini']['codeurl'] . 'img/img.png',
                    'last_time' => 0,
                );
            } else {
                $sql = "SELECT `name`,`img`,`last_time` FROM `user` WHERE `id` = '{$this->user_info['fid']}'";
                $ret = $this->_getUserDao()->query($sql);
            }
            $this->ajax_return('0', '', $ret);
        }

        $page = (int) $this->controller->get_gp('page'); // 页码
        $perpage = (int) $this->controller->get_gp('perpage'); // 分页大小

        $this->getRedis()->redis()->select(9);

        $key_set = 'setuser_list_' . $this->user_info['id'];
        $ret_set = $this->getRedis()->get($key_set); // 获取缓存key
        if (!$ret_set) { // 没有有序集合缓存就调用数据库现查
            $parm = $this->get_team($this->user_info['id']);
            $key = $parm . '_data';
            $tmp = $this->getRedis()->get($key . '_' . $this->user_info['id']);
            $this->getRedis()->set($parm . '_' . $this->user_info['id'], '');
            $this->getRedis()->set($key . '_' . $this->user_info['id'], '');
            $ret_set = $this->getRedis()->get($key_set); // 重新拉去缓存key
        }

        //取数据
        $a = $page ? $page : 1; // 第几页 默认=1
        $b = $perpage ? $perpage : 10; // 一页多少条

        $c = ($a - 1) * $b;
        $d = ($a * $b) - 1;

        $result = $this->getRedis()->redis()->zRevRange($ret_set, $c, $d, false);  // 获取有序集合中所有用户ID
        //$count = $this->getRedis()->redis()->ZCARD($ret_set);  //获取总条数

        $ret = array();
        if ($result) {
            $sql = "SELECT `name`,`img`,`time` AS `last_time` FROM `user` WHERE `id` IN(" . trim(implode(',', $result), ',') . ") ORDER BY `time` DESC";
            $ret = $this->_getUserDao()->query($sql);
        }

        $this->ajax_return('0', '', $ret);
    }

    /**
     * 团队订单列表
     */
    public function team_order_list() {
        $this->getRedis()->redis()->select(9);
        // 获取所有团队ID
        $key_set = 'setuser_list_' . $this->user_info['id'];
        $ret_set = $this->getRedis()->get($key_set);
        if ($ret_set) { // 先取缓存
            $ret = $this->getRedis()->redis()->zRevRange($ret_set, 0, -1, false);  // 获取有序集合中所有用户ID
            $tmp = implode(',', $ret); // 因有序集合没有存储自己ID，因此需要加上
            //$tmp = $this->user_info['id'] . ',' . implode(',', $ret); // 因有序集合没有存储自己ID，因此需要加上
            unset($ret);
        } else { // 没有有序集合缓存就调用数据库现查
            $parm = $this->get_team($this->user_info['id']);
            $key = $parm . '_data';
            $tmp = $this->getRedis()->get($key . '_' . $this->user_info['id']);
            $this->getRedis()->set($parm . '_' . $this->user_info['id'], '');
            $this->getRedis()->set($key . '_' . $this->user_info['id'], '');
        }
        $page = (int) $this->controller->get_gp('page'); // 页码
        $perpage = (int) $this->controller->get_gp('perpage'); // 分页大小
        $status = $this->controller->get_gp('status'); // 订单状态 all标识全部  0=已付款 1=已结算  2=已失效        
        $list = array();
        $tmp = trim($tmp, ',');
        if ($tmp) {
            $where = "`uid` IN($tmp) AND `time` > 0";
            if ($status !== 'all') {
                $status = (int) $status;
                $where.= " AND `status` = '{$status}'";
            }
            
            $list = $this->_getUserOrderDao()->getListWhere($page, $perpage, $where, array('time desc'));
            unset($tmp);

            $tmp = array();
            foreach ($list as &$v) {
                $data['order_id'] = $v['id'];
                $data['uid'] = $this->user_info['id'];
                $tmp = $this->_getUserIncomeDao()->getList(0, 1, $data, array('id desc'));
                $v['money'] = $tmp[0]['money'] ? $tmp[0]['money'] : 0;
            }
        }

        $this->ajax_return('0', '', array('list' => $list ? $list : array(), 'msg' => '确认收货后次月25日之前到账'));
    }

    /**
     * 递归所有团队ID（包含团长ID）
     * @param type $parm
     * @return type
     */
    public function get_team($parm) {
        $this->getRedis()->redis()->select(9);
        $key = $parm . '_data';
        $a = $this->getRedis()->get($parm . '_' . $this->user_info['id']);
        $b = $this->getRedis()->get($key . '_' . $this->user_info['id']);
        $fid = $a ? $a : $parm;
        $b = $b ? $b : $parm;

        $sql = "SELECT `id`,`time` FROM `user` WHERE `fid` IN(" . $fid . ") ORDER BY `time` DESC";
        $ret = $this->_getUserDao()->query($sql);
        $tmp = array();
        $key_zadd = 'user_list_' . $this->user_info['id'];
        foreach ($ret as $k => $v) {
            $this->getRedis()->redis()->zAdd($key_zadd, $v['time'], $v['id']); // 有序集合   用于团队用户列表查询
            $tmp[] = $v['id'];
        }

        // 存储有序集合key  调用时先去有序集合的用户ID
        $this->getRedis()->set('set' . $key_zadd, $key_zadd, 120); // 会员过多时建议调大缓存周期

        $this->getRedis()->set($parm . '_' . $this->user_info['id'], implode(',', $tmp));
        if (count($tmp) > 0) {
            $this->getRedis()->set($key . '_' . $this->user_info['id'], $b . ',' . implode(',', $tmp));
            $this->get_team($parm);
        } else {
            $this->getRedis()->set($key . '_' . $this->user_info['id'], $b);
        }

        return $parm;
    }

    // 用户
    public function _getUserDao() {
        return InitPHP::getDao('user', 'admin');
    }

    // 用户分享表
    public function _getUserShareDao() {
        return InitPHP::getDao('user_share', 'admin');
    }

    // 用户订单表
    public function _getUserOrderDao() {
        return InitPHP::getDao('user_order', 'admin');
    }

    // 用户提现账户表
    public function _getUserAccountDao() {
        return InitPHP::getDao('user_account', 'admin');
    }

    // 用户提现表
    public function _getUserWithdrawDao() {
        return InitPHP::getDao('user_withdraw', 'admin');
    }

    // 用户收入表
    public function _getUserIncomeDao() {
        return InitPHP::getDao('user_income', 'admin');
    }

}
