<?php

require_once EXTEND_PATH . "/TaoBao/TopSdk.php"; // 加载淘宝SDK
require_once EXTEND_PATH . "/WeiXin/wxCode.php"; // 加载小程序二维码生成类

class tbController extends BaseController {

    public $initphp_list = array('tb_getcat', 'tb_list', 'tb_detail');
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
     * 获取淘宝商品标准类目
     */
    public function tb_getcat() {
        $page = (int) $this->controller->get_gp('page'); // 页码
        $perpage = (int) $this->controller->get_gp('perpage'); // 分页大小

        $list = $this->_getTbCatDao()->getList($page, $perpage, array(), array('sort desc'));

        $this->ajax_return('0', '', $list);
    }

    /**
     * 淘宝商品列表查询
     */
    public function tb_list() {
        $parm = $this->controller->get_gp(array(
            'page',
            'perpage',
            'keyword',
            'cat_id',
            'sort',
            'has_coupon',
        ));
        $page = (int) $parm['page'];
        $perpage = (int) $parm['perpage'];


        if (!$parm['cat_id'] && !$parm['keyword']) {
            // taobao.tbk.dg.optimus.material( 淘宝客-推广者-物料精选 )【首页精选分类专用】 如需要修改调用规则参考https://tbk.bbs.taobao.com/detail.html?appId=45301&postId=8576096
            $c = new TopClient;
            $c->appkey = $this->InitPHP_conf['tbk']['appkey'];
            $c->secretKey = $this->InitPHP_conf['tbk']['secretKey'];
            $req = new TbkDgOptimusMaterialRequest;
            $req->setPageSize($perpage ? $perpage : 10); // 页大小，默认20，1~100
            $req->setPageNo($page ? $page : 1); // 第几页，默认：１
            $req->setMaterialId(13366); //  高佣榜 如需修改参考上方备注参考链接
            $req->setAdzoneId($this->InitPHP_conf['tbk']['adzone_id']); // mm_xxx_xxx_12345678三段式的最后一段数字
            $resp = $c->execute($req);
            $this->getRedis()->redis()->select(5);

            $tmp = array();
            $data = $this->object_array($resp);
            if (count($data['result_list']['map_data']) === 0) {
                // 接口暂无数据            
                $this->ajax_return('0', '接口暂无数据', array());
            }


            $list_data = $data['result_list']['map_data'];
            if (!$list_data[0]) { // 返回结果是单条时处理成多条结果集
                $list_data = array($list_data);
            }
            foreach ($list_data as $k => $v) {
                // 佣金优惠券信息
                $promotion_rate = $v['commission_rate'];
                $brokerage = $this->get_price($v['commission_rate'] / 100 * ($v['coupon_amount'] ? ($v['zk_final_price'] - $v['coupon_amount']) : $v['zk_final_price']));
                $coupon_discount = $this->get_price($v['coupon_amount']);
                // 商品单价信息
                $min_group_price = $this->get_price($v['coupon_amount'] ? ($v['zk_final_price'] - $v['coupon_amount']) : $v['zk_final_price']); // 最小成团价格【折扣价（元）】
                $min_normal_price = $this->get_price($v['zk_final_price']); // 最小单买价格【商品信息-商品一口价格】
                // 存储佣金优惠券信息【用于详情调用】
                $key = md5('tb_detail_' . $v['item_id']);
                $r_data = array(
                    'promotion_rate' => $promotion_rate,
                    'brokerage' => $brokerage,
                    'coupon_discount' => $coupon_discount,
                    'coupon_start_time' => (integer)$v['coupon_start_time'] / 1000, // 优惠券信息-优惠券开始时间
                    'coupon_end_time' => (integer)$v['coupon_end_time'] / 1000, // 优惠券信息-优惠券结束时间
                    'coupon_share_url' => $v['coupon_share_url'], // 链接-宝贝+券二合一页面链接
                    'url' => $v['url'], // 链接-宝贝推广链接
                    'min_group_price' => $min_group_price, // 最小成团价格【折扣价（元）】
                    'min_normal_price' => $min_normal_price, // 最小单买价格【商品信息-商品一口价格】
                );
                $this->getRedis()->set($key, json_encode($r_data));

                $tmp[] = array(
                    'goods_id' => $v['item_id'], // 商品ID
                    'goods_name' => $v['title'], // 商品名称
                    'min_group_price' => $min_group_price, // 最小成团价格【折扣价（元）】
                    'min_normal_price' => $min_normal_price, // 最小单买价格【商品信息-商品一口价格】
                    'promotion_rate' => $promotion_rate, // 商品信息-佣金比率 百分比
                    'brokerage' => $this->get_price_bk($brokerage, 2), // 自购佣金
                    'brokerage_ys' => $brokerage, // 佣金
                    'goods_thumbnail_url' => $v['pict_url'], // 商品缩略图
                    'coupon_discount' => $coupon_discount, // 优惠券面额
                    'sales_tip' => $v['volume'], // 商品信息-30天销量
                );
            }

            $this->ajax_return('0', '', $tmp);
        }


        // taobao.tbk.dg.material.optional( 淘宝客-推广者-物料搜索 )
        $c = new TopClient;
        $c->appkey = $this->InitPHP_conf['tbk']['appkey'];
        $c->secretKey = $this->InitPHP_conf['tbk']['secretKey'];
        $req = new TbkDgMaterialOptionalRequest;
        if ($parm['keyword']) {
            $req->setQ($parm['keyword']); // 商品筛选-查询词
        }
        $req->setPageSize($perpage ? $perpage : 10); // 页大小，默认20，1~100
        $req->setPageNo($page ? $page : 1); // 第几页，默认：１
        $req->setSort($parm['sort'] ? $parm['sort'] : 'total_sales_desc'); // 排序_des（降序），排序_asc（升序），销量（total_sales），淘客佣金比率（tk_rate）， 累计推广量（tk_total_sales），总支出佣金（tk_total_commi），价格（price）

        if ($parm['cat_id']) {
            $req->setCat($parm['cat_id']); // 商品筛选-后台类目ID。用,分割，最大10个，该ID可以通过taobao.itemcats.get接口获取到
        }
        if ($parm['has_coupon']) {
            $req->setHasCoupon($parm['has_coupon']); // 优惠券筛选-是否有优惠券。true表示该商品有优惠券，false或不设置表示不限
        }
        $req->setAdzoneId($this->InitPHP_conf['tbk']['adzone_id']); // mm_xxx_xxx_12345678三段式的最后一段数字
        $resp = $c->execute($req);

        $this->getRedis()->redis()->select(5);

        $tmp = array();
        $data = $this->object_array($resp);
        if ((int) $data['total_results'] === 0) {
            // 接口暂无数据            
            $this->ajax_return('0', '接口暂无数据',array());
        }

        $list_data = $data['result_list']['map_data'];
        if (!$list_data[0]) { // 返回结果是单条时处理成多条结果集
            $list_data = array($list_data);
        }
        foreach ($list_data as $k => $v) {
            // 佣金优惠券信息
            $promotion_rate = $v['commission_rate'];
            $brokerage = $this->get_price($v['commission_rate'] / 10000 * ($v['coupon_amount'] ? ($v['zk_final_price'] - $v['coupon_amount']) : $v['zk_final_price']));
            $coupon_discount = $this->get_price($v['coupon_amount']);
            // 商品单价信息
            $min_group_price = $this->get_price($v['coupon_amount'] ? ($v['zk_final_price'] - $v['coupon_amount']) : $v['zk_final_price']); // 最小成团价格【折扣价（元）】
            $min_normal_price = $this->get_price($v['reserve_price']); // 最小单买价格【商品信息-商品一口价格】
            // 存储佣金优惠券信息【用于详情调用】
            $key = md5('tb_detail_' . $v['item_id']);
            $r_data = array(
                'promotion_rate' => $promotion_rate,
                'brokerage' => $brokerage,
                'coupon_discount' => $coupon_discount,
                'coupon_start_time' => strtotime($v['coupon_start_time']), // 优惠券信息-优惠券开始时间
                'coupon_end_time' => strtotime($v['coupon_end_time']), // 优惠券信息-优惠券结束时间
                'coupon_share_url' => $v['coupon_share_url'], // 链接-宝贝+券二合一页面链接
                'url' => $v['url'], // 链接-宝贝推广链接
                'min_group_price' => $min_group_price, // 最小成团价格【折扣价（元）】
                'min_normal_price' => $min_normal_price, // 最小单买价格【商品信息-商品一口价格】
            );
            $this->getRedis()->set($key, json_encode($r_data));

            $tmp[] = array(
                'goods_id' => $v['item_id'], // 商品ID
                'goods_name' => $v['title'], // 商品名称
                'min_group_price' => $min_group_price, // 最小成团价格【折扣价（元）】
                'min_normal_price' => $min_normal_price, // 最小单买价格【商品信息-商品一口价格】
                'promotion_rate' => $promotion_rate, // 商品信息-佣金比率。1550表示15.5%
                'brokerage' => $this->get_price_bk($brokerage, 2), // 自购佣金
                'brokerage_ys' => $brokerage, // 佣金
                'goods_thumbnail_url' => $v['pict_url'], // 商品缩略图
                'coupon_discount' => $coupon_discount, // 优惠券面额
                'sales_tip' => $v['volume'], // 商品信息-30天销量
            );
        }

        $this->ajax_return('0', '', $tmp);
    }

    /**
     * 淘宝商品详情
     */
    public function tb_detail() {
        $parm = $this->controller->get_gp(array(
            'goods_id', // 商品ID        
        ));

        $goods_id = $parm['goods_id'];
        if (!$goods_id) {
            $this->ajax_return('1', '请传入商品ID');
        }

        $keys = md5('detail_' . $goods_id);
        $this->getRedis()->redis()->select(5);
        $cache_data = $this->getRedis()->get($keys);

        if (!$cache_data) {

            $c = new TopClient;
            $c->appkey = $this->InitPHP_conf['tbk']['appkey'];
            $c->secretKey = $this->InitPHP_conf['tbk']['secretKey'];
            $req = new TbkItemInfoGetRequest;
            $req->setNumIids($goods_id); // 商品ID串，用,分割，最大40个
            //$req->setPlatform("1"); // 链接形式：1：PC，2：无线，默认：１
            $req->setIp($this->getRealIp());
            $resp = $c->execute($req);

            $tmp = array();
            $data = $this->object_array($resp);
            $data = $data['results']['n_tbk_item'];

            // 调取存储佣金优惠券信息
            //$this->getRedis()->redis()->select(7);
            $key = md5('tb_detail_' . $goods_id);
            $r_data = $this->getRedis()->get($key);
            $r_data = json_decode($r_data, true);

            // 生成二维码
            $wxcode = new wxCode();
            $wx_data = array(
                'path' => $this->InitPHP_conf['mini']['detail'] . '?source=1&id=' . $goods_id . '&userid=' . $this->user_info['id'],
                'userid' => $this->user_info['id'],
                'goods_id' => $goods_id,
            );

            $tmp = array(
                'goods_gallery_urls' => $data['small_images']['string'], // 商品轮播图
                'min_group_price' => $r_data['min_group_price'], // 最小成团价格【折扣价（元）】
                'min_normal_price' => $r_data['min_normal_price'], // 最小单买价格【商品信息-商品一口价格】
                'promotion_rate' => $r_data['promotion_rate'], // 商品信息-佣金比率。1550表示15.5%
                'brokerage' => $r_data['brokerage'], // 佣金
                'coupon_discount' => $this->get_price($r_data['coupon_discount']), // 优惠券面额
                'coupon_start_time' => $r_data['coupon_start_time'], // 优惠券生效时间，UNIX时间戳
                'coupon_end_time' => $r_data['coupon_end_time'], // 优惠券失效时间，UNIX时间戳
                'sales_tip' => $data['volume'], // 商品信息-30天销量
                'mall_name' => $data['nick'], // 店铺名称
                //'desc_txt' => $data['desc_txt'], // 描述分
                //'serv_txt' => $data['serv_txt'], // 服务分
                //'lgst_txt' => $data['lgst_txt'], // 物流分
                'desc_txt' => '高', // 描述分
                'serv_txt' => '高', // 服务分
                'lgst_txt' => '高', // 物流分
                'goods_name' => $data['title'], // 商品名称
                //'goods_desc' => $data['goods_desc'], // 商品描述
                //'coupon_remain_quantity' => $data['coupon_remain_quantity'], // 优惠券剩余数量
                'coupon_share_url' => $r_data['coupon_share_url'], // 链接-宝贝+券二合一页面链接
                'url' => $r_data['url'], // 链接-宝贝推广链接
                'pict_url' => $data['pict_url'], // 商品主图
                'a' => $this->get_price_bk($r_data['brokerage'], 2), // 自购佣金
                'b' => $this->get_price_bk($r_data['brokerage'], 2), // 分享佣金
                //'share' => $this->InitPHP_conf['mini']['detail'] . '?urltype=share_tb&goods_id=' . $goods_id . '&userid=' . $this->user_info['id'], // 分享链接
                'share' => $wxcode->getWxcode($wx_data), // 分享二维码地址
                'imgurl' => $wxcode->dlfile($data['small_images']['string'][0], md5('tb' . $goods_id)), // 分享二维码地址
                'upgrade_money' => floor($r_data['brokerage'] * $this->rebate_data * ($this->bk_data[2]['val2'] ? $this->bk_data[2]['val2'] / 100 : 1) * 100) / 100, // 升级多赚金额  高级合伙人的自购算法
            );

            $tkl = $this->tb_createurl($tmp);
            $tmp['tkl'] = 'fu致这行话' . $tkl . '转移至淘宀┡ē，【' . $data['title'] . '】'; // 淘口令

            $cache_data = json_encode($tmp);

            $this->getRedis()->set($keys, $cache_data, $this->InitPHP_conf['tbk']['cache']['detail']);
        }

        $this->ajax_return('0', '', json_decode($cache_data, true));
    }

    /**
     * 生成淘口令
     */
    public function tb_createurl($data) {
        // 判断是否包含https
        $url = $data['coupon_share_url'] ? $data['coupon_share_url'] : $data['url'];
        $preg = "/^http(s)?:\\/\\/.+/";
        if (!preg_match($preg, $url)) {
            $url = 'https:' . $url;
        }
        $c = new TopClient;
        $c->appkey = $this->InitPHP_conf['tbk']['appkey'];
        $c->secretKey = $this->InitPHP_conf['tbk']['secretKey'];
        $req = new TbkTpwdCreateRequest;
        $req->setUserId("1151518796");
        $req->setText($data['goods_name']);
        $req->setUrl($url);
        $req->setLogo($data['pict_url']);
        $req->setExt("{'id':110120119}");
        $resp = $c->execute($req);

        return $resp->data->model;
    }

    /**
     * xml转换成数组
     * @param type $array
     * @return type
     */
    public function object_array($array) {
        if (is_object($array)) {
            $array = (array) $array;
        }
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                $array[$key] = $this->object_array($value);
            }
        }
        return $array;
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

    // 获取淘宝类目信息
    public function _getTbCatDao() {
        return InitPHP::getDao('cat', 'tb');
    }

}
