<?php

/**
 * Dao 基类
 */
class BaseDao extends \Dao {

    /** @var string 表名 */
    protected $table = '';

    /** @var string 主键 */
    protected $id = 'id';

    /**
     * 添加
     * @param  array $data  数据
     * @param  bool  $cache 是否清除缓存，默认值: true
     * @return bool
     */
    public function add($data, $cache = true) {
        if (!is_array($data) || empty($data)) {
            return false;
        }

        $result = $this->dao->db->insert($data, $this->table);

        if (($cache === true) && $result) {
            $this->dao->cache->clear($this->cacheKey('all'), CACHE_TYPE);
        }

        return $result;
    }

    /**
     * 编辑
     * @param  int   $id    主键ID
     * @param  array $data  数据
     * @param  bool  $cache 是否清除缓存，默认值: true
     * @return bool
     */
    public function edit($id, $data, $cache = true) {
        $id = (int) $id;
        if (1 > $id || !is_array($data) || empty($data)) {
            return false;
        }

        $result = $this->dao->db->update($id, $data, $this->table, $this->id);

        if (($cache === true) && $result) {
            $this->dao->cache->clear($this->cacheKey('all'), CACHE_TYPE);
            $this->dao->cache->clear($this->cacheKey($id), CACHE_TYPE);
        }

        return $result;
    }

    /**
     * 更新
     * @param  int   $id    主键ID
     * @param  array $data  数据
     * @param  bool  $cache 是否清除缓存，默认值: true
     * @return bool
     */
    public function update($id, $data, $cache = true) {
        $id = (int) $id;
        if (1 > $id || !is_array($data) || empty($data)) {
            return false;
        }

        $result = $this->dao->db->update_by_field(
                $data, array($this->id => $id), $this->table
        );

        if (($cache === true) && $result) {
            $this->dao->cache->clear($this->cacheKey('all'), CACHE_TYPE);
            $this->dao->cache->clear($this->cacheKey($id), CACHE_TYPE);
        }

        return $result;
    }

    /**
     * 删除
     * @param  int  $id    主键ID
     * @param  bool $cache 是否清除缓存，默认值: true
     * @return bool
     */
    public function delete($id, $cache = true) {
        $id = (int) $id;
        if (1 > $id) {
            return false;
        }

        $result = $this->dao->db->delete($id, $this->table, $this->id);

        if (($cache === true) && $result) {
            $this->dao->cache->clear($this->cacheKey('all'), CACHE_TYPE);
            $this->dao->cache->clear($this->cacheKey($id), CACHE_TYPE);
        }

        return $result;
    }

    /**
     * 删除（delete的别名）
     * @param  int  $id    主键ID
     * @param  bool $cache 是否清除缓存，默认值: true
     * @return bool
     */
    public function del($id, $cache = true) {
        return $this->delete($id, $cache);
    }

    /**
     * 获取单条
     * @param  int  $id      主键ID
     * @param  bool $cache   是否缓存，默认值: true
     * @param  int  $expires 缓存时间，默认值: CACHE_TIME
     * @return bool
     */
    public function get($id, $cache = true, $expires = CACHE_TIME) {
        $id = (int) $id;
        if (1 > $id) {
            return false;
        }

        if ($cache === true) {
            $val = $this->dao->cache->get($this->cacheKey($id), CACHE_TYPE);
            if (!$val) {
                $val = $this->dao->db->get_one($id, $this->table, $this->id);
                if ($val) {
                    $expires = (int) $expires;
                    $this->dao->cache->set(
                            $this->cacheKey($id), $val, $expires, CACHE_TYPE
                    );
                }
            }
        } else {
            $val = $this->dao->db->get_one($id, $this->table, $this->id);
        }

        return $val;
    }

    /**
     * 获取所有
     * @param  bool $cache   是否缓存，默认值: true
     * @param  int  $expires 缓存时间，默认值: CACHE_TIME
     * @return array
     */
    public function getAll($cache = true, $expires = CACHE_TIME) {
        if ($cache === true) {
            $val = $this->dao->cache->get($this->cacheKey('all'), CACHE_TYPE);
            if (!$val) {
                $sql = sprintf('SELECT * FROM %s ', $this->table);
                $val = $this->dao->db->get_all_sql($sql);
                if ($val) {
                    $expires = (int) $expires;
                    $this->dao->cache->set(
                            $this->cacheKey('all'), $val, $expires, CACHE_TYPE
                    );
                }
            }
        } else {
            $sql = sprintf('SELECT * FROM %s ', $this->table);
            $val = $this->dao->db->get_all_sql($sql);
        }

        return $val;
    }

    /**
     * 分页返回符合条件的记录
     * @param  int   $page    页码
     * @param  int   $perpage 分页大小
     * @param  array $search  筛选条件
     * @param  array $sort    排序
     * @param  bool  $cache   是否缓存，默认值: false
     * @param  int   $expires 缓存时间，默认值: CACHE_TIME
     * @return array
     */
    public function getList($page, $perpage, $search, $sort, $cache = false, $expires = CACHE_TIME) {
        $page = (int) $page;
        $perpage = (int) $perpage;
        if (1 > $page) {
            $page = 1;
        }

        $offest = $perpage * ($page - 1);
        $limit = $this->dao->db->build_limit($offest, $perpage);
        $where = $this->getSearchParam($search);
        $orderby = $this->getSortParam($sort);
        unset($search, $sort);
        $sql = sprintf('SELECT * FROM `%s` %s %s %s', $this->table, $where, $orderby, $limit);
        if ($cache === true) {
            $key = md5($sql);
            $val = $this->dao->cache->get($this->cacheKey($key), CACHE_TYPE);
            if (!$val) {
                $val = $this->dao->db->get_all_sql($sql);
                if ($val) {
                    $expires = (int) $expires;
                    $this->dao->cache->set(
                            $this->cacheKey($key), $val, $expires, CACHE_TYPE
                    );
                }
            }
            unset($key);
        } else {
            $val = $this->dao->db->get_all_sql($sql);
        }

        return $val;
    }

    /**
     * 分页返回符合条件的记录
     * @param  int   $page    页码
     * @param  int   $perpage 分页大小
     * @param  array $search  筛选条件
     * @param  array $sort    排序
     * @param  bool  $cache   是否缓存，默认值: false
     * @param  int   $expires 缓存时间，默认值: CACHE_TIME
     * @return array
     */
    public function getListWhere($page, $perpage, $search, $sort, $cache = false, $expires = CACHE_TIME) {
        $page = (int) $page;
        $perpage = (int) $perpage;
        if (1 > $page) {
            $page = 1;
        }

        $offest = $perpage * ($page - 1);
        $limit = $this->dao->db->build_limit($offest, $perpage);
        $where = ' WHERE ' . $search;
        $orderby = $this->getSortParam($sort);
        unset($search, $sort);
        $sql = sprintf('SELECT * FROM `%s` %s %s %s', $this->table, $where, $orderby, $limit);
        if ($cache === true) {
            $key = md5($sql);
            $val = $this->dao->cache->get($this->cacheKey($key), CACHE_TYPE);
            if (!$val) {
                $val = $this->dao->db->get_all_sql($sql);
                if ($val) {
                    $expires = (int) $expires;
                    $this->dao->cache->set(
                            $this->cacheKey($key), $val, $expires, CACHE_TYPE
                    );
                }
            }
            unset($key);
        } else {
            $val = $this->dao->db->get_all_sql($sql);
        }

        return $val;
    }

    /**
     * 返回符合条件的记录数量
     * @param  array $search  筛选条件
     * @param  bool  $cache   是否缓存，默认值: false
     * @param  int   $expires 缓存时间，默认值: CACHE_TIME
     * @return array
     */
    public function getCount($search, $cache = false, $expires = CACHE_TIME) {
        $where = $this->getSearchParam($search);
        $sql = sprintf(
                'SELECT COUNT(1) AS `count` FROM `%s` %s', $this->table, $where
        );


        if ($cache === true) {
            $key = md5($sql);
            $val = $this->dao->cache->get($this->cacheKey($key), CACHE_TYPE);
            if (!$val) {
                $tmp = $this->dao->db->get_one_sql($sql);
                $val = (int) $tmp['count'];
                unset($tmp);
                if ($val) {
                    $expires = (int) $expires;
                    $this->dao->cache->set(
                            $this->cacheKey($key), $val, $expires, CACHE_TYPE
                    );
                }
            }
            unset($key);
        } else {
            $tmp = $this->dao->db->get_one_sql($sql);
            $val = (int) $tmp['count'];
            unset($tmp);
        }

        return $val;
    }

    /**
     * 执行查询
     * @param type $sql
     * @param type $cache
     * @param type $expires
     */
    public function query($sql, $cache = true, $expires = CACHE_TIME) {
        $key = md5($sql);
        if ($cache === true) {
            $val = $this->dao->cache->get($this->cacheKey($key), CACHE_TYPE);
            $expires = (int) $expires;
        }
        if (!$val) {
            $val = $this->dao->db->get_all_sql($sql);
            if ($cache === true && $val) {
                $this->dao->cache->set(
                        $this->cacheKey($key), $val, $expires, CACHE_TYPE
                );
            }
        }
        return $val;
    }

    /**
     * 缓存KEY
     * @param  string $key
     * @return string
     */
    protected final function cacheKey($key) {
        return CACHE_PREFIX . "_{$this->table}_{$key}";
    }

    /**
     * 返回条件语句
     * @param array $search 筛选条件
     */
    protected function getSearchParam($search) {
        $search_str = '';
        $search_data = array();

        foreach ($search as $key => $val) {
            $search_data[] = "`{$key}` = " . $this->dao->db->build_escape($val);
        }

        unset($search);

        if ($search_data) {
            $search_str = ' WHERE ' . implode(' AND ', $search_data);
        }

        unset($search_data);

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
