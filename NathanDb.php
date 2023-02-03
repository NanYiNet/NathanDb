<?php

/**
 * @title   Nathan - DB类
 * @auther  Nathan
 * @date    2022-01-15
 * @desc    数据库操作类
 */
class NathanDb
{
    /**
     * PDO连接参数
     * @var array
     */
    protected $params = [
        PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES  => false,
    ];

    /**
     * 绑定参数
     * @var array
     */
    protected $bind = [];

    protected $items = [];

    protected $key = [];                //存放条件语句

    public function __construct($config = [])
    {
        $this->config = $config;
        $this->connect($this->config);
    }

    public function __call($name, $value)
    {
        $arr = ['name', 'order', 'limit', 'field', 'group', 'having', 'alias'];
        if (in_array($name, $arr)) {
            $this->key[$name] = $value[0];

            return $this;
        } else {
            //报错日志
            $this->getError('Call to '.$name.' function not exist!');
        }

        return $this;
    }

    /**
     * 连接数据库方法
     * @access public
     *
     * @param array      $config         连接参数
     * @param integer    $linkNum        连接序号
     * @param array|bool $autoConnection 是否自动连接主数据库（用于分布式）
     *
     * @return PDO
     * @throws PDOException
     */
    public function connect($config = [], $linkNum = 0, $autoConnection = false)
    {
        if (isset($this->links[$linkNum])) {
            return $this->links[$linkNum];
        }

        if (empty($config)) {
            $config = $this->config;
        } else {
            $config = array_merge($this->config, $config);
        }

        // 连接参数
        $params = $this->params;

        // 记录当前字段属性大小写设置
        $this->attrCase = $params[PDO::ATTR_CASE];

        try {
            if (empty($config['db_dsn'])) {
                $config['db_dsn'] = $this->parseDsn($config);
            }

            $dns = $config['db_dsn'];
            $this->links[$linkNum] = new PDO($dns, $this->config['db_user'], $this->config['db_pwd'], $params);

            return $this->links[$linkNum];
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    public function field($str = '')
    {
        if (is_array($str)) {
            $str = implode(',', $str);
        }
        $this->key['field'] = $str;

        return $this;
    }

    /**
     * 自定义执行SQL语句
     *
     * @param  $sql sql语句
     *
     * @return （self::$link->query返回值）
     */
    public function query($sql = '')
    {
        $selectQuery = $this->execute($sql);
        try {
            return $selectQuery->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {

        }
    }

    /**
     * 获取name
     *
     * @param $name
     *
     * @return $this
     */
    public function name($name)
    {
        $this->key['name'] = $name;

        return $this;
    }

    /**
     * 获取table
     *
     * @param $name
     *
     * @return $this
     */
    public function table($name)
    {
        $this->key['table'] = $name;

        return $this;
    }

    public function where($field, $operator = '', $value = null, $logic = '&&')
    {
        if (is_null($value)) {
            $value    = $operator;
            $operator = '=';
        }
        switch ($operator) {
            case "in":
            case "not in":
                if (is_array($value)) {
                    $valueStr = implode(',', $value);
                    $value    = "({$valueStr})";
                } else {
                    if ( ! strstr($value, '(')) {
                        $value = "({$value})";
                    }
                }
                $result = " `{$field}` ".$operator." ".$value;
                break;
            case "between":
                list($min, $max) = is_string($value) ? explode(',', $value) : $value;
                $result = " `{$field}` >= {$min} {$logic} {$field} <= {$max}";
                break;
            case "not between":
                list($min, $max) = is_string($value) ? explode(',', $value) : $value;
                $result = " `{$field}` > {$max} || {$field} < {$min}";
                break;
            default:
                if (is_null($value)) {
                    $result = " `{$field}` IS NULL";
                } else {
                    if (is_array($field)) {
                        $r = '';
                        foreach ($field as $k => $v) {
                            $r .= " (`{$k}` ".$operator." '".$v."') and";
                        }
                        $result = rtrim($r, "and");
                    } else {
                        if (empty($operator)) {
                            $result = " `{$field}` ".$operator." '".$value."'";
                        } else {
                            $result = " {$field} ";
                        }
                    }
                }
                break;
        }
        $this->key['where'][] = $result;

        return $this;
    }

    /**
     * 将where数组转换成字符串
     * @return string
     */
    private function getWhereArr($op = 'and')
    {
        $whereArr = $this->key['where'];
        $whereStr = "(".implode(") {$op} (", $whereArr).")";

        return $whereStr;
    }

    /**
     * LIKE过滤
     * @access public
     *
     * @param string $field 字段名
     * @param string $value 数据
     *
     * @return static
     */
    public function whereLike($field, $value)
    {
        return $this->where($field, 'like', $value);
    }

    /**
     * NOT LIKE过滤
     * @access public
     *
     * @param string $field 字段名
     * @param string $value 数据
     *
     * @return static
     */
    public function whereNotLike($field, $value)
    {
        return $this->where($field, 'not like', $value);
    }

    /**
     * IN过滤
     * @access public
     *
     * @param string $field 字段名
     * @param array  $value 数据
     *
     * @return static
     */
    public function whereIn($field, $value)
    {
        return $this->where($field, 'in', $value);
    }

    /**
     * NOT IN过滤
     * @access public
     *
     * @param string $field 字段名
     * @param array  $value 数据
     *
     * @return static
     */
    public function whereNotIn($field, $value)
    {
        return $this->where($field, 'not in', $value);
    }

    /**
     * BETWEEN 过滤
     * @access public
     *
     * @param string $field 字段名
     * @param mixed  $value 数据
     *
     * @return static
     */
    public function whereBetween($field, $value)
    {
        return $this->where($field, 'between', $value);
    }

    /**
     * NOT BETWEEN 过滤
     * @access public
     *
     * @param string $field 字段名
     * @param mixed  $value 数据
     *
     * @return static
     */
    public function whereNotBetween($field, $value)
    {
        return $this->where($field, 'not between', $value);
    }

    /**
     * 查询日期或者时间
     * @access public
     *
     * @param string       $field 日期字段名
     * @param string       $op    比较运算符或者表达式
     * @param string|array $range 比较范围
     * @param string       $logic AND OR
     *
     * @return $this
     */
    public function whereTime($field, $op, $range = null, $logic = 'AND')
    {
        return $this->where($field, $op, $range, $logic);
    }

    /**
     * 查询某个时间区间
     * @access public
     *
     * @param string       $field 日期字段名
     * @param string       $start 开始时间
     * @param string|array $end   结束时间
     *
     * @return $this
     */
    public function whereBetweenTime($field, $start, $end)
    {
        return $this->where($field, 'between', [$start, $end]);
    }

    /**
     * 查询非某个时间区间
     * @access public
     *
     * @param string       $field 日期字段名
     * @param string       $start 开始时间
     * @param string|array $end   结束时间
     *
     * @return $this
     */
    public function whereNotBetweenTime($field, $start, $end)
    {
        return $this->where($field, 'not between', [$start, $end]);
    }

    /**
     * 组装limit条件
     *
     * @param $arr   （可以是字符串如：('0,5')；可以是数组如：[0,5]
     * @param $limit 如果limit不为空，arr值只能是int
     *
     * @return $this
     */
    public function limit($arr = '')
    {
        if ( ! empty($arr)) {
            if (is_array($arr)) {
                $args = func_get_args();
                $str  = '';
                foreach ($args as $k => $v) {
                    $str .= implode(',', $v);
                }
                $this->key['limit'] = $str;

                return $this;
            } else {
                $this->key['limit'] = $arr;

                return $this;
            }
        } else {
            return $this;
        }
    }

    /**
     * 关联join查询
     *
     * @param $a    例如：nathan_admin_log l ，必须要包含表前缀
     * @param $b    条件
     * @param $type 类型
     *
     * @return $this
     */
    public function join($a, $b, $type = 'INNER')
    {
        $this->key['join'] = $type.' JOIN '.$a.' ON '.$b;

        return $this;
    }

    /**
     * LEFT JOIN
     * @access public
     *
     * @param mixed $join      关联的表名
     * @param mixed $condition 条件
     * @param array $bind      参数绑定
     *
     * @return $this
     */
    public function leftJoin($join, $condition = null, $bind = [])
    {
        return $this->join($join, $condition, 'LEFT', $bind);
    }

    /**
     * RIGHT JOIN
     * @access public
     *
     * @param mixed $join      关联的表名
     * @param mixed $condition 条件
     * @param array $bind      参数绑定
     *
     * @return $this
     */
    public function rightJoin($join, $condition = null, $bind = [])
    {
        return $this->join($join, $condition, 'RIGHT', $bind);
    }

    /**
     * FULL JOIN
     * @access public
     *
     * @param mixed $join      关联的表名
     * @param mixed $condition 条件
     * @param array $bind      参数绑定
     *
     * @return $this
     */
    public function fullJoin($join, $condition = null, $bind = [])
    {
        return $this->join($join, $condition, 'FULL');
    }

    /**
     * 获取执行的SQL语句而不进行实际的查询
     * @access public
     *
     * @param bool $fetch 是否返回sql
     *
     * @return $this|Fetch
     */
    public function fetchSql($fetch = true)
    {
        if ($fetch) {
            $this->options['fetch_sql'] = $fetch;
        }

        return $this;
    }

    /**
     * 获取查询多条结果，返回二维数组
     * @return array
     */
    public function select()
    {
        $field       = isset($this->key['field']) ? str_replace('nathan_', $this->config['db_prefix'],
            $this->key['field']) : ' * ';
        $join        = isset($this->key['join']) ? $this->key['join'] : '';
        $where       = ! empty($this->key['where']) ? ' WHERE '.self::getWhereArr() : '';
        $group       = isset($this->key['group']) ? ' GROUP BY '.$this->key['group'] : '';
        $having      = isset($this->key['having']) ? ' HAVING '.$this->key['having'] : '';
        $order       = isset($this->key['order']) ? ' ORDER BY '.$this->key['order'] : '';
        $limit       = isset($this->key['limit']) ? ' LIMIT '.$this->key['limit'] : '';
        $sql         = 'SELECT '.$field.' FROM '.$this->get_tablename().$join.$where.$group.$having.$order.$limit;
        $selectQuery = $this->execute($sql);
        try {
            return $selectQuery->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {

        }
    }

    /**
     * 获取查询一条结果，返回一维数组
     * @return array or false
     */
    public function find()
    {
        $field  = isset($this->key['field']) ? str_replace('nathan_', $this->config['db_prefix'],
            $this->key['field']) : ' * ';
        $join   = isset($this->key['join']) ? $this->key['join'] : '';
        $where  = ! empty($this->key['where']) ? ' WHERE '.self::getWhereArr() : '';
        $group  = isset($this->key['group']) ? ' GROUP BY '.$this->key['group'] : '';
        $having = isset($this->key['having']) ? ' HAVING '.$this->key['having'] : '';
        $order  = isset($this->key['order']) ? ' ORDER BY '.$this->key['order'] : '';
        $limit  = ' LIMIT 1';

        $sql       = 'SELECT '.$field.' FROM '.$this->get_tablename().$join.$where.$group.$having.$order.$limit;
        $findquery = $this->execute($sql);

        $res = $findquery->fetch(PDO::FETCH_ASSOC);
        if ( ! empty($res)) {
            return $res;
        } else {
            if ($this->options['allow_empty'] == true) {
                return [];
            } else {
                return null;
            }

            return null;
        }
    }

    /**
     * 返回记录总行数。
     *
     * @param $field
     *
     * @return false|int|mixed
     */
    public function count($field = '*')
    {
        $join      = isset($this->key['join']) ? $this->key['join'] : '';
        $where     = ! empty($this->key['where']) ? ' WHERE '.self::getWhereArr() : '';
        $sql       = 'SELECT COUNT('.$field.') AS hi_total FROM '.$this->get_tablename().$join.$where;
        $findquery = $this->execute($sql);
        if ( ! empty($findquery)) {
            $res   = $findquery->fetch(PDO::FETCH_ASSOC);
            $total = ! empty($res['hi_total']) ? $res['hi_total'] : 0;

            return $total;
        } else {
            $this->getError('参数错误');

            return false;
        }

    }

    /**
     * 获取最大值
     *
     * @param $field
     *
     * @return false|int|mixed
     */
    public function max($field = '')
    {
        if (empty($field)) {
            $this->getError('字段不能为空');
        }
        $join      = isset($this->key['join']) ? $this->key['join'] : '';
        $where     = ! empty($this->key['where']) ? ' WHERE '.self::getWhereArr() : '';
        $limit     = ' LIMIT 1';
        $sql       = 'SELECT MAX('.$field.') AS nathan_max  FROM '.$this->get_tablename().$join.$where.$limit;
        $findquery = $this->execute($sql);
        if ( ! empty($findquery)) {
            $res = $findquery->fetch(PDO::FETCH_ASSOC);
            $max = ! empty($res['nathan_max']) ? $res['nathan_max'] : 0;

            return $max;
        } else {
            $this->getError('参数错误');

            return false;
        }
    }

    /**
     * 获取最小值
     *
     * @param $field
     *
     * @return false|int|mixed
     */
    public function min($field = '')
    {
        if (empty($field)) {
            $this->getError('字段不能为空');
        }
        $join      = isset($this->key['join']) ? $this->key['join'] : '';
        $where     = ! empty($this->key['where']) ? ' WHERE '.self::getWhereArr() : '';
        $limit     = ' LIMIT 1';
        $sql       = 'SELECT MIN('.$field.') AS nathan_min  FROM '.$this->get_tablename().$join.$where.$limit;
        $findquery = $this->execute($sql);
        if ( ! empty($findquery)) {
            $res = $findquery->fetch(PDO::FETCH_ASSOC);
            $min = ! empty($res['nathan_min']) ? $res['nathan_min'] : 0;

            return $min;
        } else {
            $this->getError('参数错误');

            return false;
        }
    }

    /**
     * 获取平均值
     *
     * @param $field
     *
     * @return false|int|mixed
     */
    public function avg($field = '')
    {
        if (empty($field)) {
            $this->getError('字段不能为空');
        }
        $join      = isset($this->key['join']) ? $this->key['join'] : '';
        $where     = ! empty($this->key['where']) ? ' WHERE '.self::getWhereArr() : '';
        $limit     = ' LIMIT 1';
        $sql       = 'SELECT AVG('.$field.') AS nathan_avg FROM '.$this->get_tablename().$join.$where.$limit;
        $findquery = $this->execute($sql);
        if ( ! empty($findquery)) {
            $res = $findquery->fetch(PDO::FETCH_ASSOC);
            $avg = ! empty($res['nathan_avg']) ? $res['nathan_avg'] : 0;

            return $avg;
        } else {
            $this->getError('参数错误');

            return false;
        }
    }

    /**
     * 获取平均值
     *
     * @param $field
     *
     * @return false|int|mixed
     */
    public function sum($field = '')
    {
        if (empty($field)) {
            $this->getError('字段不能为空');
        }
        $join      = isset($this->key['join']) ? $this->key['join'] : '';
        $where     = ! empty($this->key['where']) ? ' WHERE '.self::getWhereArr() : '';
        $limit     = ' LIMIT 1';
        $sql       = 'SELECT SUM('.$field.') AS nathan_sum FROM '.$this->get_tablename().$join.$where.$limit;
        $findquery = $this->execute($sql);
        if ( ! empty($findquery)) {
            $res = $findquery->fetch(PDO::FETCH_ASSOC);
            $sum = ! empty($res['nathan_sum']) ? $res['nathan_sum'] : 0;

            return $sum;
        } else {
            $this->getError('参数错误');

            return false;
        }
    }

    /**
     * 查找单条记录 如果不存在则抛出异常
     * @access public
     *
     * @param array|string|Query|\Closure $data
     *
     * @return array|\PDOStatement|string|Model
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     */
    public function findOrEmpty($data = null)
    {
        return $this->allowEmpty(true)->find($data);
    }

    /**
     * 返回数据中指定的一列
     * @access public
     *
     * @param string|null $columnKey 键名
     * @param string|null $indexKey  作为索引值的列
     *
     * @return array
     */
    public function value($valueKey)
    {
        $field  = $valueKey;
        $join   = isset($this->key['join']) ? $this->key['join'] : '';
        $where  = ! empty($this->key['where']) ? ' WHERE '.self::getWhereArr() : '';
        $group  = isset($this->key['group']) ? ' GROUP BY '.$this->key['group'] : '';
        $having = isset($this->key['having']) ? ' HAVING '.$this->key['having'] : '';
        $order  = isset($this->key['order']) ? ' ORDER BY '.$this->key['order'] : '';
        $limit  = ' LIMIT 1';

        $sql       = 'SELECT '.$field.' FROM '.$this->get_tablename().$join.$where.$group.$having.$order.$limit;
        $findquery = $this->execute($sql);
        if ( ! empty($findquery)) {
            $res = $findquery->fetch(PDO::FETCH_ASSOC);
            if ( ! empty($res)) {
                return $res[$valueKey];
            } else {
                return '';
            }
        } else {
            return null;
        }

    }

    /**
     * 执行添加记录操作
     *
     * @param $data 写入的数据（数组）
     *
     * @return void
     */
    public function insert($data, $replace = false)
    {
        if ( ! is_array($data)) {
            $this->getError('写入的数据必须为数组');

            return false;
        }

        $fields = $values = [];
        foreach ($data as $key => $val) {
            $fields[] = '`'.$key.'`';
            $values[] = "'".$val."'";
        }
        if (empty($fields)) {
            return false;
        }
        $sql = ($replace ? 'REPLACE' : 'INSERT').' INTO '.$this->get_tablename().' ('.implode(', ',
                $fields).') VALUES ('.implode(', ', $values).')';

        $res = $this->execute($sql);
        if ( ! empty($res)) {
            $lastId = $this->connect()->lastInsertId($sql);

            return $lastId;
        } else {
            return 0;
        }

    }

    /**
     * 自增
     *
     * @param $name
     * @param $step
     *
     * @return void
     */
    public function inc($name, $step = 1)
    {
        if (empty($name)) {
            $this->getError('字段名不能为空');

            return false;
        }
        $this->key['inc'] = [$name, $step];

        return $this;
    }

    /**
     * 自减
     *
     * @param $name
     * @param $step
     *
     * @return void
     */
    public function dec($name, $step = 1)
    {
        if (empty($name)) {
            $this->getError('字段名不能为空');

            return false;
        }
        $this->key['dec'] = [$name, $step];

        return $this;
    }

    /**
     * 执行更新记录操作
     *
     * @param $data
     *
     * @return void
     */
    public function update($data = '')
    {
        $inc = isset($this->key['inc']) ? $this->key['inc'] : '';
        $dec = isset($this->key['dec']) ? $this->key['dec'] : '';

        if (is_array($data)) {
            $sets = [];
            foreach ($data as $key => $val) {
                $sets[] = '`'.$key.'` = \''.$val.'\'';
            }
            $value = implode(', ', $sets);
        } else {
            if ( ! empty($data)) {
                $value = $data;
            } else {
                //自增
                if ( ! empty($inc)) {
                    $value = '`'.$inc[0].'` = `'.$inc[0].'` + '.$inc[1];
                } //自减
                elseif ( ! empty($dec)) {
                    $value = '`'.$dec[0].'` = `'.$dec[0].'` - '.$dec[1];
                } else {
                    $this->getError('update方法参数错误');

                    return false;
                }
            }
        }
        $where = ! empty($this->key['where']) ? ' WHERE '.self::getWhereArr() : '';
        $sql   = 'UPDATE '.$this->get_tablename().' SET '.$value.$where;

        $statement = $this->execute($sql);

        return $statement->rowCount();

    }

    /**
     * 执行删除记录操作
     * @return mixed
     */
    public function delete()
    {
        $where     = ! empty($this->key['where']) ? ' WHERE '.self::getWhereArr() : '';
        $sql       = 'DELETE FROM '.$this->get_tablename().$where;
        $statement = $this->execute($sql);

        return $statement->rowCount();

    }

    /**
     * 解析pdo连接的dsn信息
     * @access protected
     *
     * @param array $config 连接信息
     *
     * @return string
     */
    protected function parseDsn($config)
    {
        if ( ! empty($config['db_port'])) {
            $dsn = 'mysql:host='.$config['db_host'].';port='.$config['db_port'];
        } else {
            $dsn = 'mysql:host='.$config['db_host'];
        }
        $dsn .= ';dbname='.$config['db_name'];

        if ( ! empty($config['db_charset'])) {
            $dsn .= ';charset='.$config['db_charset'];
        }

        return $dsn;
    }

    /**
     * 内部方法：数据库执行方法
     *
     * @param $sql 要执行的sql语句
     *
     * @return 查询资源句柄
     */
    private function execute($sql)
    {
        try {
            $fetch_sql = ! empty($this->options['fetch_sql']) ? $this->options['fetch_sql'] : false;
            if ($fetch_sql) {
                die('<div style="background:rgba(0,0,0,0.66);font-size:14px;text-align:left; border:1px solid #e91e63;line-height:25px; padding:5px 10px;color:#6fdc39;font-family:Arial, Helvetica,sans-serif;"><pre><b>SQL语句：'.PHP_EOL.'</b>'.$sql.'</pre></div>');
            }

            $this->queryStr       = $sql;
            $this->queryStartTime = microtime(true);
            // 预处理
            $this->PDOStatement = $this->connect()->prepare($sql);
            // 执行查询
            $this->PDOStatement->execute();
            $this->reConnectTimes = 0;

            return $this->PDOStatement;
        } catch (PDOException $e) {
            $this->getError('Execute SQL error, message : '.$e->getMessage(), $sql);
        }
    }

    /**
     * 输出数据库表名
     * @return string
     */
    private function get_tablename()
    {
        $alias = isset($this->key['alias']) ? ' '.$this->key['alias'].' ' : '';
        $name  = isset($this->key['name']) ? $this->config['db_prefix'].$this->key['name'] : '';
        $table = isset($this->key['table']) ? $this->key['table'] : '';
        if ( ! empty($name)) {
            $tableName = '`'.$name.'`'.' '.$alias;
        } elseif ( ! empty($table)) {
            $tableName = '`'.$table.'`'.' '.$alias;
        } else {
            $this->getError('缺少数据库表名');

            return false;
        }

        return $tableName;
    }

    /**
     * 是否允许返回空数据（或空模型）
     * @access public
     *
     * @param bool $allowEmpty 是否允许为空
     *
     * @return $this
     */
    public function allowEmpty($allowEmpty = true)
    {
        $this->options['allow_empty'] = $allowEmpty;

        return $this;
    }

    private function getError($msg, $sql = '')
    {
        //这里可以写入日志

        //判断是否开启错误输出
        $eachError = ($this->config['echo_error']);
        if ($eachError) {
            die("Error：".$msg);
        }

        return false;
    }

}
