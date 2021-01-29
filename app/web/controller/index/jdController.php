<?php

require_once EXTEND_PATH . "/QueryList/getHttpBody.php"; // 加载QueryList
require_once EXTEND_PATH . "/Jd/creatSign.php"; // 加载京东签名生成类
require_once EXTEND_PATH . "/WeiXin/wxCode.php"; // 加载小程序二维码生成类

class jdController extends BaseController {

    public $initphp_list = array('jd_getcat', 'jd_hot', 'jd_detail', 'jd_list');
    private static $querylist;
    private static $jd;
    private static $InitPHP_conf; // 配置信息

    public function __construct() {
        parent::__construct();

        $this->querylist = new getHttpBody();
        $this->jd = new creatSign();

        // 获取配置文件
        $this->InitPHP_conf = InitPHP::getConfig();
    }

    public function run() {
        $this->ajax_return('1', '接口不存在');
    }

    /**
     * 获取京东商品标准类目
     */
    public function jd_getcat() {
        $page = (int) $this->controller->get_gp('page'); // 页码
        $perpage = (int) $this->controller->get_gp('perpage'); // 分页大小

        $list = $this->_getJdCatDao()->getList($page, $perpage, array(), array('sort desc'));

        $this->ajax_return('0', '', $list);
    }

    /**
     * 首页京东热销榜单
     */
    public function jd_hot() {
        $page = (int) $this->controller->get_gp('page'); // 页码
        $perpage = (int) $this->controller->get_gp('perpage'); // 分页大小

        $key = md5('jd_hot' . $page . $perpage);
        $this->getRedis()->redis()->select(5);
        //$cache_data = $this->getRedis()->get($key);

        if (!$cache_data) {

            $param_json = array(
                'goodsReq' => array(
                    'eliteId' => $perpage > 2 ? 110 : 22, // 频道id：1-好券商品,2-超级大卖场,10-9.9专区,22-热销爆品,23-为你推荐,24-数码家电,25-超市,26-母婴玩具,27-家具日用,28-美妆穿搭,29-医药保健,30-图书文具,31-今日必推,32-王牌好货,33-秒杀商品,34-拼购商品,109-新品首发,110-自营
                    'pageIndex' => $page ? $page : 1, // 页码，默认1
                    'pageSize' => $perpage ? $perpage : 2, // 每页数量，默认20，上限50
                    'pid' => $this->InitPHP_conf['jd']['pid'], // 推广位ID
                )
            );

            $parm = array(
                'v' => '1.0',
                'method' => 'jd.union.open.goods.jingfen.query',
                //'access_token' => '',
                'app_key' => $this->InitPHP_conf['jd']['appkey'],
                'sign_method' => 'md5',
                'format' => 'json',
                'timestamp' => date('Y-m-d H:i:s'),
                'param_json' => json_encode($param_json),
            );

            $url = $this->jd->create_sign_url($parm, $this->InitPHP_conf);
            $ret = $this->querylist->http_get($url);

            $data = json_decode($ret['jd_union_open_goods_jingfen_query_response']['result'], true);
            if ($data['code'] != 200) {
                $this->ajax_return('1', '返回数据异常，请稍后再试');
            }

            $tmp = array();
            foreach ($data['data'] as $k => $v) {
                // 佣金
                $brokerage = $this->get_price($v['commissionInfo']['couponCommission'] ? $v['commissionInfo']['couponCommission'] : $v['commissionInfo']['commission']);

                $tt = array(
                    'goods_id' => $v['skuId'], // 商品ID
                    'goods_name' => $v['skuName'], // 商品名称
                    'min_group_price' => $this->get_price($v['priceInfo']['lowestCouponPrice'] ? $v['priceInfo']['lowestCouponPrice'] : $v['priceInfo']['price']), // 最小成团价格
                    'min_normal_price' => $this->get_price($v['priceInfo']['lowestPrice']), // 最小单买价格 【原价】
                    'promotion_rate' => $v['commissionInfo']['commissionShare'], // 佣金比例,  1就是1%
                    'brokerage' => $this->get_price_bk($brokerage, 2), // 自购佣金
                    'brokerage_ys' => $brokerage, // 佣金
                    'goods_thumbnail_url' => $v['imageInfo']['imageList'][0]['url'], // 商品缩略图
                    'goods_thumbnail_url_list' => $v['imageInfo']['imageList'], // 商品缩略图
                    'coupon_discount' => floatval($v['couponInfo']['couponList'][0]['discount']), // 优惠券面额
                    'sales_tip' => $v['inOrderCount30Days'], // 30天引单数量
                    'coupon_get_starttime' => (integer)$v['couponInfo']['couponList'][0]['useStartTime'] / 1000, // 优惠券领取开始时间
                    'coupon_get_endtime' => (integer)$v['couponInfo']['couponList'][0]['useEndTime'] / 1000, // 优惠券领取结束时间 
                    'goods_url' => $v['materialUrl'], // 商品链接 
                    'coupon_url' => $v['couponInfo']['couponList'][0]['link'], // 优惠券链接 
                    'pid' => $this->InitPHP_conf['jd']['pid'], // 推广位id
                );

                $tmp[] = $tt;

                // 存储商品详情信息
                $this->getRedis()->redis()->select(6);
                $keys = 'jd_detail' . $v['skuId'];
                $this->getRedis()->set($keys, json_encode($tt));



//                $tmp[] = array(
//                    'goods_id' => $v['skuId'], // 商品ID
//                    'min_group_price' => $v['priceInfo']['lowestCouponPrice'] ? $v['priceInfo']['lowestCouponPrice'] : $v['priceInfo']['lowestPrice'], // 最小成团价格
//                    'min_normal_price' => $v['priceInfo']['price'], // 最小单买价格
//                    'promotion_rate' => $v['commissionInfo']['commissionShare'] * 1000, // 佣金比例,千分比
//                    'brokerage' => $v['commissionInfo']['couponCommission'], // 佣金
//                    'goods_thumbnail_url' => $v['imageInfo']['imageList'][0]['url'], // 商品缩略图
//                    'pid' => $this->InitPHP_conf['jd']['pid'], // 推广位id
//                );
            }
            $cache_data = json_encode($tmp);
            $this->getRedis()->set($key, $cache_data, $this->InitPHP_conf['jd']['cache']['hot']); // 缓存120秒
        }

        $tmp = json_decode($cache_data, true);

        $this->ajax_return('0', '', $tmp);
    }

    /**
     * 京东商品列表查询
     */
    public function jd_list() {
        $parm = $this->controller->get_gp(array(
            'page', // 页码
            'perpage', // 分页大小
            'isCoupon',
            'keyword',
            'sortName',
            'cat_id',
            'sort',
            'owner',
        ));
        $page = (int) $parm['page'];
        $perpage = (int) $parm['perpage'];

        $param_json = array(
            'goodsReqDTO' => array(
                'pageIndex' => $page ? $page : 1, // 页码，默认1
                'pageSize' => $perpage ? $perpage : 10, // 每页数量，默认20，上限50
                'pid' => $this->InitPHP_conf['jd']['pid'], // 推广位ID
            //'isCoupon' => $isCoupon, // 是否是优惠券商品，1：有优惠券，0：无优惠券
            )
        );
        if (isset($parm['isCoupon']) && $parm['isCoupon'] > 0) {
            $param_json['goodsReqDTO']['isCoupon'] = (int) $parm['isCoupon'];
        }
        if ($parm['keyword']) {
            $param_json['goodsReqDTO']['keyword'] = $parm['keyword'];
        }
        if ($parm['cat_id'] > 0) {
            $param_json['goodsReqDTO']['cid1'] = $parm['cat_id'];
        }else{
            $param_json['goodsReqDTO']['sortName'] = 'inOrderCount30Days'; // 精选分类
        }
        if ($parm['sort']) {
            $param_json['goodsReqDTO']['sort'] = $parm['sort']; // asc,desc升降序,默认降序
        }
        if ($parm['sortName']) {
            $param_json['goodsReqDTO']['sortName'] = $parm['sortName']; // 排序字段(price：单价, commissionShare：佣金比例, commission：佣金， inOrderCount30Days：30天引单量， inOrderComm30Days：30天支出佣金)
        }
        if (isset($parm['owner'])) {
            $param_json['goodsReqDTO']['owner'] = $parm['owner']; // 商品类型：自营[g]，POP[p]
        }


        $parm = array(
            'v' => '1.0',
            'method' => 'jd.union.open.goods.query',
            //'access_token' => '',
            'app_key' => $this->InitPHP_conf['jd']['appkey'],
            'sign_method' => 'md5',
            'format' => 'json',
            'timestamp' => date('Y-m-d H:i:s'),
            'param_json' => json_encode($param_json),
        );

        $url = $this->jd->create_sign_url($parm, $this->InitPHP_conf);
        $ret = $this->querylist->http_get($url);

        $data = json_decode($ret['jd_union_open_goods_query_response']['result'], true);
        if ($data['code'] != 200) {
            $this->ajax_return('1', '返回数据异常，请稍后再试');
        }
        if ($data['totalCount'] < 1) {
            $this->ajax_return('0', '数据为空', array());
        }

        $tmp = array();
        foreach ($data['data'] as $k => $v) {
            // 佣金
            $brokerage = $this->get_price($v['commissionInfo']['couponCommission'] ? $v['commissionInfo']['couponCommission'] : $v['commissionInfo']['commission']);

            $tt = array(
                'goods_id' => $v['skuId'], // 商品ID
                'goods_name' => $v['skuName'], // 商品名称
                'min_group_price' => $this->get_price($v['priceInfo']['lowestCouponPrice'] ? $v['priceInfo']['lowestCouponPrice'] : $v['priceInfo']['price']), // 最小成团价格
                'min_normal_price' => $this->get_price($v['priceInfo']['price']), // 最小单买价格 【原价】
                'promotion_rate' => $v['commissionInfo']['commissionShare'], // 佣金比例,  1就是1%
                'brokerage' => $this->get_price_bk($brokerage, 2), // 自购佣金
                'brokerage_ys' => $brokerage, // 佣金
                'goods_thumbnail_url' => $v['imageInfo']['imageList'][0]['url'], // 商品缩略图
                'goods_thumbnail_url_list' => $v['imageInfo']['imageList'], // 商品缩略图
                'coupon_discount' => floatval($v['couponInfo']['couponList'][0]['discount']), // 优惠券面额
                'sales_tip' => $v['inOrderCount30Days'], // 30天引单数量
                'coupon_get_starttime' => (integer)$v['couponInfo']['couponList'][0]['useStartTime'] / 1000, // 优惠券领取开始时间
                'coupon_get_endtime' => (integer)$v['couponInfo']['couponList'][0]['useEndTime'] / 1000, // 优惠券领取结束时间 
                'goods_url' => $v['materialUrl'], // 商品链接 
                'coupon_url' => $v['couponInfo']['couponList'][0]['link'], // 优惠券链接 
                'pid' => $this->InitPHP_conf['jd']['pid'], // 推广位id
            );

            $tmp[] = $tt;

            // 存储商品详情信息
            $this->getRedis()->redis()->select(6);
            $key = 'jd_detail' . $v['skuId'];
            $this->getRedis()->set($key, json_encode($tt));
        }

        $this->ajax_return('0', '', $tmp);
    }

    /**
     * 京东商品详情
     */
    public function jd_detail() {
        $parm = $this->controller->get_gp(array(
            'goods_id', // 商品ID        
        ));

        $goods_id = $parm['goods_id'];
        if ($goods_id < 1) {
            $this->ajax_return('1', '请传入商品ID');
        }

        $this->getRedis()->redis()->select(6);
        $key = 'jd_detail' . $goods_id;
        $ret = $this->getRedis()->get($key);

        // 生成二维码
        $wxcode = new wxCode();
        $wx_data = array(
            'path' => $this->InitPHP_conf['mini']['detail'] . '?source=2&id=' . $goods_id . '&userid=' . $this->user_info['id'],
            'userid' => $this->user_info['id'],
            'goods_id' => $goods_id,
        );

        $data = json_decode($ret, true);

        // 分解商品轮播图
        $imgs = array();
        foreach ($data['goods_thumbnail_url_list'] as $k => $v) {
            $imgs[] = $v['url'];
        }

        $tmp = array(
            'goods_gallery_urls' => $imgs, // 商品轮播图
            'min_group_price' => $data['min_group_price'], // 最小成团价格
            'min_normal_price' => $data['min_normal_price'], // 最小单买价格
            'promotion_rate' => $data['promotion_rate'], // 佣金比例,  1就是1%
            'brokerage' => $data['brokerage_ys'], // 佣金
            'coupon_discount' => $data['coupon_discount'], // 优惠券面额
            'coupon_start_time' => $data['coupon_get_starttime'], // 优惠券生效时间，UNIX时间戳
            'coupon_end_time' => $data['coupon_get_endtime'], // 优惠券失效时间，UNIX时间戳
            'sales_tip' => $data['sales_tip'], // 30天引单数量
            'goods_name' => $data['goods_name'], // 商品名称
            'goods_url' => $data['goods_url'], // 商品链接 
            'coupon_url' => $data['coupon_url'], // 优惠券链接
            'a' => $this->get_price_bk($data['brokerage_ys'], 2), // 自购佣金
            'b' => $this->get_price_bk($data['brokerage_ys'], 2), // 分享佣金
            //'share' => $this->InitPHP_conf['mini']['detail'] . '?urltype=share_jd&goods_id=' . $goods_id . '&userid=' . $this->user_info['id'], // 分享链接
            'share' => $wxcode->getWxcode($wx_data), // 分享二维码地址
            'imgurl' => $wxcode->dlfile($imgs[0], md5('jd' . $goods_id)), // 分享二维码地址
            'upgrade_money' => floor($data['brokerage_ys'] * $this->rebate_data * ($this->bk_data[2]['val2'] ? $this->bk_data[2]['val2'] / 100 : 1) * 100) / 100, // 升级多赚金额  高级合伙人的自购算法
            'uid'=>$this->user_info['id'],
        );

        $tmp_url = $this->jd_createurl($tmp['goods_url'], $tmp['coupon_url']);
        $tmp['clickURL'] = $tmp_url; // 京东推广链接

        $tmp['we_app_info'] = array(
            'page_path' => '/pages/jingfen_twotoone/item?spreadUrl=' . urlencode($tmp_url) . '&customerinfo=' . $this->InitPHP_conf['jd']['Customerinfo'],
            'app_id' => $this->InitPHP_conf['jd']['mini']['appid'],
            'source_display_name' => '京东',
        );

        $this->ajax_return('0', '', $tmp);
    }

    /**
     * 推广链接生成
     * @param type $materialUrl
     * @param type $couponUrl
     */
    public function jd_createurl($materialUrl, $couponUrl) {
        $key = md5('jd_createurl' . $materialUrl . $couponUrl . $this->user_info['id']);
        $this->getRedis()->redis()->select(5);
        $cache_data = $this->getRedis()->get($key);

        if (!$cache_data) {
            $preg = "/^http(s)?:\\/\\/.+/";
            if (!preg_match($preg, $materialUrl)) {
                $materialUrl = 'https://' . $materialUrl;
            }

            // 返利+用户ID+自购佣金+团队一级佣金+团队二级佣金+无限级分佣佣金
            $str = $this->rebate_data . '_' . $this->user_info['id'] . '_' . $this->user_info['bk']['val2'] . '_' . $this->user_info['bk']['val3'] . '_' . $this->user_info['bk']['val4'] . '_' . $this->user_info['bk']['val5'];

            $param_json = array(
                'promotionCodeReq' => array(
                    'materialId' => $materialUrl, // 推广物料
                    'siteId' => $this->InitPHP_conf['jd']['siteid'], // 站点ID是指在联盟后台的推广管理中的网站Id
                    'subUnionId' => $str, // 子联盟ID 该字段为自定义参数，建议传入字母数字和下划线的格式
                    'ext1' => $str, // 系统扩展参数
                    //'pid' => $this->InitPHP_conf['jd']['pid'], // 联盟子站长身份标识，格式：子站长ID_子站长网站ID_子站长推广位ID
                    'couponUrl' => $couponUrl, // 优惠券领取链接，在使用优惠券、商品二合一功能时入参，且materialId须为商品详情页链接
                )
            );

            $parm = array(
                'v' => '1.0',
                'method' => 'jd.union.open.promotion.common.get',
                //'access_token' => '',
                'app_key' => $this->InitPHP_conf['jd']['appkey'],
                'sign_method' => 'md5',
                'format' => 'json',
                'timestamp' => date('Y-m-d H:i:s'),
                'param_json' => json_encode($param_json),
            );

            $url = $this->jd->create_sign_url($parm, $this->InitPHP_conf);
            $ret = $this->querylist->http_get($url);
            $data = json_decode($ret['jd_union_open_promotion_common_get_response']['result'], true);
            if ($data['code'] != 200) {
                $this->ajax_return('1', '返回数据异常，请稍后再试');
            }
            $cache_data = json_encode($data['data']['clickURL']);
            $this->getRedis()->set($key, $cache_data, $this->InitPHP_conf['jd']['cache']['createurl']); // 缓存120秒
        }

        return json_decode($cache_data, true);
    }

    /**
     * 换算佣金
     * @param type $price 原始佣金
     * @param type $type 2=自购   3=分享
     * @return type
     */
    public function get_price_bk($price, $type = 2) {
        $bk = $this->user_info['bk']['val' . $type];
        $price = $price * $this->rebate_data * ($bk ? $bk / 100 : 1);

        return floor($price * 100) / 100; // 取小数点后两位，不四舍五入
    }

    /**
     * 价格
     */
    public function get_price($price) {
        return floor($price * 100) / 100;
        //return sprintf("%.2f", $price);
    }

    // 获取京东类目信息
    public function _getJdCatDao() {
        return InitPHP::getDao('cat', 'jd');
    }

}
