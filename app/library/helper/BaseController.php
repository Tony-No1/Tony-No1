<?php

/**
 * 控制器基类
 */
class BaseController extends \Controller {

    /** @var array GET */
    protected $input_get = array();

    /** @var array POST */
    protected $input_post = array();

    /** @var string 表前缀 */
    protected $table_prefix = '';
    private static $user_info; // 用户信息   

    /** 获取返利设置 * */
    private static $rebate_data; // 返利设置 

    /** 获取佣金设置 * */
    private static $bk_data; // 佣金设置     

    /**
     * 前置执行Action
     */

    public function before() {
        // 获取外部变量：GET、POST，并转义
        $this->input_get = filter_input_array(INPUT_GET, FILTER_SANITIZE_MAGIC_QUOTES);
        $this->input_post = filter_input_array(INPUT_POST, FILTER_SANITIZE_MAGIC_QUOTES);

        // 表前缀
        $this->table_prefix = InitPHP::getConfig('table_prefix');

        // 功能权限验证
        $c = $this->controller->get_gp('c');
        $a = $this->controller->get_gp('a');
        $m = $this->controller->get_gp('m');
        if ('' == $c && '' == $a) {
            $c = 'index';
            $a = 'run';
        }

        /**         * *防止恶意刷新***** */
        // 开启 Session
        if (PHP_SESSION_ACTIVE !== session_status()) {
            session_start();
        }
        // 时间间隔[秒]
        $cc_interval = '3';
        // 刷新次数
        $cc_max_times = '6';
        // 当前时间
        $cc_cur_time = time();
        // 计数
        if (isset($_SESSION['cc_last_time'])) {
            $_SESSION['cc_times'] += 1;
        } else {
            $_SESSION['cc_times'] = 1;
            $_SESSION['cc_last_time'] = $cc_cur_time;
        }
        // 处理结果
        if ($cc_cur_time - $_SESSION['cc_last_time'] < $cc_interval) {
            if ($_SESSION['cc_times'] >= $cc_max_times) {
                // 跳转至攻击者服务器地址
                header(sprintf('Location: %s', '//127.0.0.1'));
                exit('Access Denied');
            }
        } else {
            $_SESSION['cc_times'] = 0;
            $_SESSION['cc_last_time'] = $cc_cur_time;
        }
        unset($cc_interval, $cc_max_times, $cc_cur_time);

        // 验证签名
        $gg = $this->input_get? : [];
        $pp = $this->input_post? : [];
        $sign_tmp = array_merge($gg, $pp);
        if ($sign_tmp['sign']) {
            // 判断参数时间戳与当前时间戳差异
            if (time() - $sign_tmp['time'] > 600) {
                $this->ajax_return('1', '参数中时间戳与服务器时间存在差异');
            }

            unset($sign_tmp['sign']); // 剔除签名参数
            ksort($sign_tmp); // 参数名按照abcd排序	            
            $sign = md5(sha1(implode($sign_tmp)) . API_KEY);
            if ($sign !== $this->controller->get_gp('sign')) {
                $this->ajax_return('1', '签名错误');
            }
        }







        // code交换openid时无需验证
        if ($a != 'code_openid') {
            //$openid = $this->controller->get_gp('openid');
            $openid = '1313'; // 模拟OPENID
            if (!$openid) {
                $this->ajax_return('1', '请传入OPENID');
            }

            //$this->getRedis()->redis()->select(0);
        }
    }

    /**
     * 后置执行Action
     */
    public function after() {
        $this->view->display();
    }

    /**
     * Ajax操作统一返回值函数
     * @param  int    $errcode 是否有错误
     * @param  string $errmsg  错误信息，默认值：''
     * @param  array  $data    数据，默认值：array()
     * @return void
     */
    public function ajax_return($errcode, $errmsg = '', $data = array()) {
        $result = array(
            'code' => (int) $errcode,
            'result' => (array) $data,
            'msg' => (string) $errmsg,
        );

        unset($errcode, $errmsg, $data);

        header('Content-type: application/json');
        exit(json_encode($result));
    }

    public function request_curl($url, $data = '') {
        $headerArray = array("Content-type:application/json;charset='utf-8'", "Accept:application/json");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headerArray);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);

        return $output;
    }

    public function getRealIp() {
        static $realip = null;

        if (null !== $realip) {
            return $realip;
        }

        if (isset($_SERVER)) {
            if (isset($_SERVER['HTTP_ALI_CDN_REAL_IP'])) {
                $realip = $_SERVER['HTTP_ALI_CDN_REAL_IP'];
            } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $realip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else if (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $realip = $_SERVER['HTTP_CLIENT_IP'];
            } else {
                $realip = $_SERVER['REMOTE_ADDR'];
            }
        } else {
            if (getenv('HTTP_X_FORWARDED_FOR')) {
                $realip = getenv('HTTP_X_FORWARDED_FOR');
            } else if (getenv('HTTP_CLIENT_IP')) {
                $realip = getenv('HTTP_CLIENT_IP');
            } else {
                $realip = getenv('REMOTE_ADDR');
            }
        }

        // 处理多层代理的情况
        if (false !== strpos($realip, ',')) {
            $realip = reset(explode(',', $realip));
        }

        // IP地址合法验证
        $realip = filter_var($realip, FILTER_VALIDATE_IP, null);
        if (false === $realip) {
            return '0.0.0.0';   // unknown
        }

        return ($realip ? $realip : $_SERVER['REMOTE_ADDR']);
    }

    /**
     * 结束程序并输出JSON
     * @param array $data 数据
     * @return void
     */
    public function json($data) {
        header('Content-type: application/json');
        echo json_encode($data);
    }

    // 获取返利设置
    public function _getRebateDao() {
        return InitPHP::getDao('rebate', 'admin');
    }

    // 获取佣金设置
    public function _getBrokerageDao() {
        return InitPHP::getDao('brokerage', 'admin');
    }

    // 获取用户
    public function _getUserDao() {
        return InitPHP::getDao('user', 'admin');
    }

}
