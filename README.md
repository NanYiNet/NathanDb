# Nathan - MySQL DB类
博客：http://www.nanyinet.com/
> Nathan - MySQL DB类操作
>
> Author: Nathan
>
> PHP7.0+
>
> 如果写法错误或者使用了不支持的表达式，可能返回错误

~~~
//调用方法：
require_once 'NathanDb.php';
$DBconfig = array(
    'db_type'    => 'pdo',            // 数据库链接扩展 , 支持 pdo | mysqli | mysql
    'db_host'    => '127.0.0.1',     // 服务器地址
    'db_name'    => 'nathan',       // 数据库名
    'db_user'    => 'nathan',      // 数据库用户名
    'db_pwd'     => 'nathan',     // 数据库密码
    'db_port'    => 3306,        // 数据库端口
    'db_prefix'  => '',         // 数据库表前缀
    'db_charset' => 'utf8',    // 数据库编码默认采用utf8
    'echo_error' => false     //是否开启错误提示
);
$DB = new NathanDb($DBconfig);
//使用示例：
$DB_data = $DB->name('admin_log')->where('admin_id',1)->select();
$query = $DB->table('user')->where(array('phone' => $phone))->find();
~~~

> 表名如果不带前缀使用 $DB->name();
>
> 表名如果带前缀使用 $DB->table();

## 查询数据

### 原生查询（query）

query方法用于执行SQL查询操作，返回查询结果数据集（数组）。 使用示例：

~~~
$DB->query("select * from admin_log limit 10");
~~~

### 使用fetchSql

fetchSql用于直接返回SQL而不是执行查询，适用于任何的CURD操作方法。 例如 echo $DB->name('user')->fetchSql(true)->find(1);

### 查询单个数据

查询单个数据使用find方法：

~~~
// table方法必须指定完整的数据表名
$DB->table('admin')->where('id', 1)->find();
~~~

最终生成的SQL语句可能是：

SELECT * FROM `admin` WHERE  `id` = 1 LIMIT 1

> find方法查询结果不存在，返回 null，否则返回结果数组

如果希望查询数据不存在的时候返回空数组，可以使用

~~~
// table方法必须指定完整的数据表名
$DB->table('nathan_user')->where('id', 1)->findOrEmpty();
~~~

### 查询数据集

查询多个数据（数据集）使用select方法：

$DB->table('nathan_user')->where('status', 1)->select();

最终生成的SQL语句可能是：

~~~
SELECT * FROM `nathan_user` WHERE `status` = 1
~~~

select 方法查询结果是一个数组。

### 值查询

查询某个字段的值可以用

~~~
// 返回某个字段的值
$DB->table('nathan_user')->where('id', 1)->value('name');
~~~

> value 方法查询结果不存在，返回 null

## 表达式查询

> 和where方法相同用法的方法还包括whereIn、whereNotIn、whereLike等一系列快捷查询方法，下面仅以where为例说明用法。
>
> 不支持whereOr，如有需要请使用原生查询 $DB->query()

### 使用where方法：

~~~
$DB->table('nathan_user')
    ->where('id','>',1)
    ->where('name','nathan')
    ->select(); 
~~~

> where('字段名','查询表达式','查询条件')
>
> where('查询条件');

#### 关联数组查询：

主要用于等值AND条件，例如：

~~~
// 传入数组作为查询条件
$DB->name('admin_log')->where(['admin_id'=>1,'id'=>2])->select();
~~~

最后生成的SQL语句是

~~~
SELECT  *  FROM `admin_log`  WHERE ( (`admin_id` = '1') and (`id` = '2') )
~~~

### 使用whereTime方法：

whereTime 方法提供了日期和时间字段的快捷查询，示例如下：

> 时间格式必须和数据库中的一致。
>
> 数据库中如果是时间戳，那值必须也是时间戳
>
> 时间是数组的，必须用**between**或者**not between**

```php
// 大于某个时间
$DB->name('user')
->whereTime('create_time', '>=', '1615868057')
->select();
// 小于某个时间
$DB->name('user')
->whereTime('birthday', '<', '2023-1-10')
->select();
// 时间区间查询
$DB->name('user')
->whereTime('create_time', 'between', ['1615868057', '1615888169'])
->select();
// 不在某个时间区间
$DB->name('user')
->whereTime('birthday', 'not between', ['2022-10-1', '2023-10-1'])
->select();
```

输出的sql语句案例：

~~~
SELECT  *  FROM admin_log  WHERE (`createtime` >= 1615868057 AND createtime <= 1615888169)
~~~

### 使用whereBetweenTime|whereNotBetweenTime方法

> 针对时间的区间查询，系统还提供了`whereBetweenTime/whereNotBetweenTime`快捷方法。

```php
// 查询2023年上半年注册的用户
$DB->name('user')
    ->whereBetweenTime('create_time', '2023-01-01', '2023-06-30')
    ->select();
  
// 查询不是2023年上半年注册的用户
$DB->name('user')
    ->whereNotBetweenTime('create_time', '2023-01-01', '2023-06-30')
    ->select();
```

### 使用alias  join

alias用于设置当前数据表的别名，便于使用其他的连贯操作例如join方法等。 示例：

~~~
$DB->table('nathan_user')
->alias('a')
->join('nathan_dept b ','b.user_id= a.id')
->select();
~~~

> SELECT * FROM nathan_user a INNER JOIN nathan_dept b ON b.user_id= a.id

JOIN方法用于根据两个或多个表中的列之间的关系，从这些表中查询数据。join通常有下面几种类型，不同类型的join操作会影响返回的数据结果。

* INNER JOIN: 等同于 JOIN（默认的JOIN类型）,如果表中有至少一个匹配，则返回行
* LEFT JOIN: 即使右表中没有匹配，也从左表返回所有的行
* RIGHT JOIN: 即使左表中没有匹配，也从右表返回所有的行
* FULL JOIN: 只要其中一个表中存在匹配，就返回行

### group|having|order|limit

group方法只有一个参数，并且只能使用字符串。

having方法只有一个参数，并且只能使用字符串。

order方法只有一个参数，并且只能使用字符串。

limit方法主要用于指定查询和操作的数量。 limit可以如下写法：

* limit(0)  字符串
* limit(1,5)  分页
* limit([1,2]) 数组

---

## 添加数据

### 添加一条数据

使用 **insert** 方法向数据库提交数据

insert(需要写入的数组,是否replace)

> insert 方法添加数据成功返回新增数据的自增主键

~~~
$data = ['name' => 'nathan', 'qq' => '2322796106'];
$DB->name('user')->insert($data);
~~~

---

## 修改数据

使用update()方法：(1个参数)

> 参数可以是数组或者字符串：
>
> array(要更改的数组)
>
> string（要更改的字符串）

~~~
$DB->name('user')
    ->where('id', 1)
    ->update(['name' => 'nathan']);
~~~

实际生成的SQL语句可能是：

~~~
UPDATE `nathan_user`  SET `name`='nathan'  WHERE  `id` = 1
~~~

> **update**方法返回影响数据的条数，没修改任何数据返回 0

### 自增/自减 (inc/dec)

可以使用inc/dec方法自增或自减一个字段的值（ 如不加第二个参数，默认步长为1）。

```php
// score 字段加 1
$DB->table('nathan_user')
    ->where('id', 1)
    ->inc('score')
    ->update();

// score 字段加 5
$DB->table('nathan_user')
    ->where('id', 1)
    ->inc('score', 5)
    ->update();

// score 字段减 1
$DB->table('nathan_user')
    ->where('id', 1)
    ->dec('score')
    ->update();

// score 字段减 5
$DB->table('nathan_user')
    ->where('id', 1)
    ->dec('score', 5)
    ->update();
```

最终生成的SQL语句可能是：

~~~
UPDATE `nathan_user`  SET `score` = `score` + 1  WHERE  `id` = 1 
UPDATE `nathan_user`  SET `score` = `score` + 5  WHERE  `id` = 1
UPDATE `nathan_user`  SET `score` = `score` - 1  WHERE  `id` = 1
UPDATE `nathan_user`  SET `score` = `score` - 5  WHERE  `id` = 1
~~~

---

## 删除数据

返回操作成功N条

~~~
$DB->table('nathan_user')->where('id',1)->delete();
~~~

最终生成的SQL语句可能是：

~~~
DELETE FROM `nathan_user` WHERE  `id` = 1 
~~~

## 聚合查询

在应用中我们经常会用到一些统计数据，例如当前所有（或者满足某些条件）的用户数、所有用户的最大积分、用户的平均成绩等等，我们为这些统计操作提供了一系列的内置方法，包括：


| 方法  | 说明                                     |
| ------- | ------------------------------------------ |
| count | 统计数量，参数是要统计的字段名（可选）   |
| max   | 获取最大值，参数是要统计的字段名（必须） |
| min   | 获取最小值，参数是要统计的字段名（必须） |
| avg   | 获取平均值，参数是要统计的字段名（必须） |
| sum   | 获取总分，参数是要统计的字段名（必须）   |

>聚合方法如果没有数据，默认都是0，聚合查询都可以配合其它查询条件

用法示例
### count
获取用户数：
~~~
$DB->table('nathan_user')->count();
~~~
实际生成的SQL语句是：
~~~
SELECT COUNT(*) AS tp_count FROM `nathan_user` LIMIT 1
~~~
或者根据字段统计：
~~~
$DB->table('nathan_user')->count('id');
~~~
生成的SQL语句是：
~~~
SELECT COUNT(id) AS hi_total FROM `nathan_user` LIMIT 1
~~~

### max
获取用户的最大积分：
~~~
$DB->table('nathan_user')->max('score');
~~~
生成的SQL语句是：
~~~
SELECT MAX(score) AS max FROM `nathan_user` LIMIT 1
~~~

### min
获取用户的最大积分：
~~~
$DB->table('nathan_user')->min('score');
~~~
生成的SQL语句是：
~~~
SELECT MIN(score) AS min FROM `nathan_user` LIMIT 1
~~~
### avg
获取用户的平均积分：
~~~
$DB->table('nathan_user')->avg('score');
~~~
生成的SQL语句是：
~~~
SELECT AVG(score) AS tp_avg FROM `nathan_user` LIMIT 1
~~~
### sum
统计用户的总成绩：
~~~
$DB->table('nathan_user')->where('id',10)->sum('score');
~~~
生成的SQL语句是：
~~~
SELECT SUM(score) AS tp_sum FROM `nathan_user` LIMIT 1
~~~
如果你要使用group进行聚合查询，需要自己实现查询，例如：
~~~
$DB->table('score')->field('user_id,SUM(score) AS sum_score')->group('user_id')->select();
~~~
