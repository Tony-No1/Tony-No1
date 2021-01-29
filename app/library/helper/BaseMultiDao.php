<?php

/**
 * Multi Dao 基类
 */
class BaseMultiDao extends \Dao {

    /** @var string 数据库标识 */
    protected $db = 'default';

    /** @var string 表名 */
    protected $table = '';

    /** @var string 主键 */
    protected $id = 'id';
    
      /** @var string 存储此缓存的缓存名 */
    protected $_cachename_name;

    public function __construct() {
        parent::__construct();
        $this->_cachename_name = 'CacheTable_'.$this->db .'_' . $this->table;
    }

    /**
     * 添加
     * @param  array $data  数据
     * @param  bool  $cache 是否有缓存，默认值：true
     * @return bool
     */
    public function add($data, $cache = true) {
        if (!is_array($data) || empty($data)) {
            return false;
        }

        $result = $this->init_db($this->db)->insert($data, $this->table);

        if (($cache === true) && $result) {
            $this->dao->cache->clear_all(CACHE_TYPE);
            //$this->delCacheData();
            //$this->dao->cache->clear($this->cacheKey('all'), CACHE_TYPE);
        }

        return $result;
    }

    /**
     * 编辑
     * @param  int   $id    主键ID
     * @param  array $data  数据
     * @param  bool  $cache 是否有缓存，默认值：true
     * @return bool
     */
    public function edit($id, $data, $cache = true) {
        $id = (int) $id;
        if (1 > $id || !is_array($data) || empty($data)) {
            return false;
        }

        $result = $this->init_db($this->db)->update($id, $data, $this->table, $this->id);

        if (($cache === true) && $result) {
            $this->dao->cache->clear_all(CACHE_TYPE);
            //$this->delCacheData();
            //$this->dao->cache->clear($this->cacheKey('all'), CACHE_TYPE);
            //$this->dao->cache->clear($this->cacheKey($id), CACHE_TYPE);
        }

        return $result;
    }

    /**
     * 更新
     * @param  int   $id    主键ID
     * @param  array $data  数据
     * @param  bool  $cache 是否有缓存，默认值：true
     * @return bool
     */
    public function update($id, $data, $cache = true) {
        $id = (int) $id;
        if (1 > $id || !is_array($data) || empty($data)) {
            return false;
        }

        $result = $this->init_db($this->db)->update_by_field(
                $data, array($this->id => $id), $this->table
        );

        if (($cache === true) && $result) {
            $this->dao->cache->clear_all(CACHE_TYPE);
            //$this->delCacheData();
            //$this->dao->cache->clear($this->cacheKey('all'), CACHE_TYPE);
            //$this->dao->cache->clear($this->cacheKey($id), CACHE_TYPE);
        }

        return $result;
    }

    /**
     * 删除
     * @param  int  $id    主键ID
     * @param  bool $cache 是否有缓存，默认值：true
     * @return bool
     */
    public function delete($id, $cache = true) {
        $id = (int) $id;
        if (1 > $id) {
            return false;
        }

        $result = $this->init_db($this->db)->delete($id, $this->table, $this->id);

        if (($cache === true) && $result) {
            $this->dao->cache->clear_all(CACHE_TYPE);
            //$this->delCacheData();
            //$this->dao->cache->clear($this->cacheKey('all'), CACHE_TYPE);
            //$this->dao->cache->clear($this->cacheKey($id), CACHE_TYPE);
        }

        return $result;
    }

    /**
     * 删除（delete的别名）
     * @param  int  $id    主键ID
     * @param  bool $cache 是否有缓存，默认值：true
     * @return bool
     */
    public function del($id, $cache = true) {
        return $this->delete($id, $cache);
    }

    /**
     * 获取单条
     * @param  int  $id      主键ID
     * @param  bool $cache   是否缓存
     * @param  int  $expires 缓存时间，默认值: CACHE_TIME
     * @return bool
     */
    public function get($id, $cache = true, $expires = CACHE_TIME) {
        $id = (int) $id;
        if (1 > $id) {
            return false;
        }

        if ($cache === true) {
            $cacheKey = array(
                'condition' => array('id' => $id)
            );
            $val = $this->getCachedata($cacheKey);
            //$val = $this->dao->cache->get($this->cacheKey($id), CACHE_TYPE);
            $expires = (int) $expires;
        }

        if (!$val) {
            $val = $this->init_db($this->db)->get_one($id, $this->table, $this->id);
            if ($cache === true) {
                $this->setCachedata($cacheKey, $val, $expires);
                //$this->dao->cache->set(
                //        $this->cacheKey($id), $val, $expires, CACHE_TYPE
                //);
            }
        }

        return $val;
    }
    
    /**
     * 返回符合条件的单条记录
     * @param type $search 筛选条件
     * @param  array $sort    排序
     * @param  bool  $cache   是否缓存
     * @param  int   $expires 缓存时间，默认值: CACHE_TIME
     * @return array
     */
    public function getOne($search,$sort,$cache = true, $expires = CACHE_TIME){
        $info = $this->getList(1, 1, $search, $sort,$cache,$expires);
        return $info[0];
    }

    /**
     * 返回符合条件的记录
     * @param  array $search  筛选条件
     * @param  array $sort    排序
     * @param  bool  $cache   是否缓存
     * @param  int   $expires 缓存时间，默认值: CACHE_TIME
     * @return array
     */
    public function getAll($search, $sort, $cache = true, $expires = CACHE_TIME) {
        $where = $this->getSearchParam($search);
        $orderby = $this->getSortParam($sort);
        
        $sql = sprintf(
                'SELECT * FROM `%s` %s %s', $this->table, $where, $orderby
        );
        $key = ($search || $sort) ? md5($sql) : 'all';

        unset($search, $sort);

        if ($cache === true) {
            $val = $this->getCachedata($key);
            //$val = $this->dao->cache->get($this->cacheKey($key), CACHE_TYPE);
            $expires = (int) $expires;
        }

        if (!$val) {
            $val = $this->init_db($this->db)->get_all_sql($sql);
            if ($cache === true && $val) {
                 $this->setCachedata($key,$val,$expires);
                /*$this->dao->cache->set(
                        $this->cacheKey($key), $val, $expires, CACHE_TYPE
                );*/
            }
        }

        return $val;
    }

    /**
     * 分页返回符合条件的记录
     * @param  int   $page    页码
     * @param  int   $perpage 分页大小
     * @param  array $search  筛选条件
     * @param  array $sort    排序
     * @param  bool  $cache   是否缓存
     * @param  int   $expires 缓存时间，默认值: CACHE_TIME
     * @return array
     */
    public function getList($page, $perpage, $search, $sort, $cache = true, $expires = CACHE_TIME) {
        $page = (int) $page;
        $perpage = (int) $perpage;
        if (1 > $page) {
            $page = 1;
        }

        $offest = $perpage * ($page - 1);
        $limit = $this->init_db($this->db)->build_limit($offest, $perpage);
        $where = $this->getSearchParam($search);
        $orderby = $this->getSortParam($sort);
        unset($search, $sort);

        $sql = sprintf('SELECT * FROM `%s` %s %s %s', $this->table, $where, $orderby, $limit);

        $key = md5($sql);

        if ($cache === true) {
            $val = $this->getCachedata($key);
            //$val = $this->dao->cache->get($this->cacheKey($key), CACHE_TYPE);
            $expires = (int) $expires;
        }

        if (!$val) {
            $val = $this->init_db($this->db)->get_all_sql($sql);
            if ($cache === true && $val) {
                $this->setCachedata($key, $val, $expires);
                /*$this->dao->cache->set(
                        $this->cacheKey($key), $val, $expires, CACHE_TYPE
                );*/
            }
        }

        return $val;
    }

    /**
     * 返回符合条件的记录数量
     * @param  array $search 筛选条件
     * @param  bool  $cache   是否缓存
     * @param  int   $expires 缓存时间，默认值: CACHE_TIME
     * @return array
     */
    public function getCount($search, $cache = true, $expires = CACHE_TIME) {
        $where = $this->getSearchParam($search);
        $sql = sprintf(
                'SELECT COUNT(1) AS `count` FROM `%s` %s', $this->table, $where
        );
        $key = md5($sql);

        if ($cache === true) {
            $val = $this->getCachedata($key);
            //$val = $this->dao->cache->get($this->cacheKey($key), CACHE_TYPE);
            $expires = (int) $expires;
        }

        if (!$val) {
            $result = $this->init_db($this->db)->query($sql);
            $result = $this->init_db($this->db)->fetch_assoc($result);
            $val = (int) $result['count'];
            unset($result);
            if ($cache === true && $val) {
                $this->setCachedata($key, $val, $expires);
                /*$this->dao->cache->set(
                        $this->cacheKey($key), $val, $expires, CACHE_TYPE
                );*/
            }
        }

        return $val;
    }
    
    /**
     * 执行查询
     * @param type $sql
     * @param type $cache
     * @param type $expires
     */
    public function query($sql, $cache = true, $expires = CACHE_TIME){
        $key = md5($sql);
        if ($cache === true) {
            $val = $this->getCachedata($key);
            $expires = (int) $expires;
        }
        if (!$val) {
            $val = $this->init_db($this->db)->get_all_sql($sql);
            if ($cache === true && $val) {
                $this->setCachedata($key, $val, $expires);
            }
        }
        return $val;
    }

    /**
     * 缓存KEY
     * @param  string $key
     * @return string
    protected final function cacheKey($key) {
        return ($this->db === 'default' && $this->db === '') ?
                "app_cyb_{$this->table}_{$key}" :
                "{$this->db}_{$this->table}_{$key}";
    }*/
    
      /*     * * 缓存 begin** */

    
    /**
     * 存缓存
     * @param array $cacheKeyArr
     * @param array $data
     */
    protected function setCachedata($cacheKeyArr, $data, $expires = CACHE_TIME) {
        $cacheName = $this->cacheKey($cacheKeyArr);
        //存储缓存数据
        $this->dao->cache->set($cacheName, $data, $expires, CACHE_TYPE);
        //存储缓存名
        $data_cachename = $this->dao->cache->get($this->_cachename_name, CACHE_TYPE);
        $data_cachename[] = $cacheName;
        $this->dao->cache->set($this->_cachename_name, $data_cachename, 0, CACHE_TYPE);
    }

    /**
     * 取缓存
     * @param array $cacheKeyArr
     * @return array $data
     */
    protected function getCachedata($cacheKeyArr) {
        $cacheName = $this->cacheKey($cacheKeyArr);
        return $this->dao->cache->get($cacheName, CACHE_TYPE);
    }
    
      /**
     * 删除此表下所有的缓存
     */
    public function delCacheData() {
        $data_cachename = $this->dao->cache->get($this->_cachename_name, CACHE_TYPE);
        if (is_array($data_cachename)) {
            foreach ($data_cachename as $value) {
                $this->dao->cache->clear($value, CACHE_TYPE); //删除此表下所有缓存
            }
        }
        $this->dao->cache->clear($this->_cachename_name, CACHE_TYPE); //删除缓存名
        return true;
    }

    /**
     * 缓存KEY
     * @param  string $key
     * @return string
     * -----2014-9-2 by lk--
     * 支持数组KEY
     */
    protected final function cacheKey($key) {
        if (is_array($key)) {
            $key = $this->combName($key);
        }
        return ($this->db === 'default' && $this->db === '') ?
                "app_cyb_{$this->table}_{$key}" :
                "{$this->db}_{$this->table}_{$key}";
    }
    
    /**
     * 组合key
     * @param type $array
     * @return type
     */
    private function combName($array) {
        ksort($array);
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->combName($value);
            }
        }
        return md5(json_encode($array));
    }

    /**
     * 返回条件语句
     * @param array $search 筛选条件
     */
    protected function getSearchParam($search) {
        $search_str = '';
        if(is_array($search)){
            $search_data = array();
            foreach ($search as $key => $val) {
                $search_data[] = "`{$key}` = " . $this->init_db($this->db)->build_escape($val);
            }
            unset($search);
            if ($search_data) {
                $search_str = ' WHERE ' . implode(' AND ', $search_data);
            }
            unset($search_data);
        }else if('' != $search){
            $search_str = "where {$search}";
        }
        return $search_str;
    }

    /**
     * 返回排序语句
     * @param array $sort 排序条件
     */
    protected function getSortParam($sort) {
        $sort_str = '';
        $sort_data = is_array($sort) ? $sort : (array) $sort;
        unset($sort);

        if ($sort_data) {
            $sort_str = ' ORDER BY ' . implode(', ', $sort_data);
        }

        unset($sort_data);

        return $sort_str;
    }

}
