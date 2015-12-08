# IndexPHP 

## 简介
IndexPHP是一个单入口、单文件PHP框架。遵循MVC（模型一视图一控制器）模式，实现了ActiveRecords模式的数据库CURD操作，适用于简单功能的快速开发。如果您正在开发一个简单的功能，而又不想使用Yii，CodeIgniter，ThinkPHP等框架（虽然有些以小巧著称，但也操过了我们要实现的业务代码量），则可以试用一下该框架。

区别于其他Web框架，该框架是一个绝对的单文件框架（整个框架只有一个index.php文件，与PHP入口文件index.php相同），不需要额外的引用或配置。

使用方法也极其简单，只需下载框架文件index.php到项目根目录，然后通过浏览器输入项目访问地址，即可自动生成框架目录结构（项目目录需要有写入权限）。

[文档手册](http://index-php.top)

## 示例代码
```php
class IndexController extends Controller
{
    public function index()
    {
        // 从数据库表user中取得$_POST['id']参数对应的用户信息
        $user = Model::db('user')->select(Param::post('id'));
        
        // 从数据库表user中取得年龄>18的用户总数，并且按照年龄倒序排列，返回从第11条开始的10条数据。
        list($user_list, $user_count) = Model::db('user')->select(array('age >'=>18), 
                                                                  array('_order'=>'age DESC',  // 年龄倒序
                                                                        '_limit'=>'11,10',     // 分页输出
                                                                        '_count'=>'id'));      // COUNT(id)

        $this->assign('user', $user);
        $this->assign('user_list', $user_list);
        $this->assign('user_count', $user_count);
        $this->display();
    }
}
```

## 基本功能
1. PATHINFO模式路由
2. MVC框架
3. 类库导入
4. 模板布局
5. ORM数据库操作
6. 日志功能
7. 缓存功能

## 目录结构

 **注：只需引入框架文件index.php，框架会自动生成如下目录结构**
```php

│  index.php                     # 框架、入口文件
└─app
    │  common.php                # 公共函数库 （可选，自动导入）
    │  conf.php                  # 配置文件
    ├─cache                      # 文件缓存（可选）
    ├─ctrl                       # 控制器目录
    │      index.class.php       # 控制器文件
    ├─lib                        # 外部库目录 （可选，自动导入）
    │      Test.class.php        # 外部库文件
    ├─log                        # 日志目录
    │      20151208.log          # 日志文件
    └─view                       # 模板视图根目录
        ├─index                  # 控制器模板目录（对应index控制器）
        │      index.html        # 模板文件
        └─_layout                # 布局目录（可选）
                main.html        # 布局文件
                

```

## 命名规范

```php
  文件： 统一为小写形式（杜绝window与linux对文件大小写处理不一致的问题）；对于驼峰命名的控制器文件，使用下划线方式命名；
         如：控制器 TestServerController 对应的文件名为 test_server.class.php
控制器： 首字母大写，驼峰命名方式；对应的控制器文件名采用下划线命名方式。
  方法： 首字母小写，驼峰命名方式；
外部库： 文件、类命名方法同控制器，方法命名同控制器方法。
```

## 系统常量

```php
名称                    默认值            说明
PATH_APP		        ./app/           系统路径
PATH_APP_CTRL           ./app/ctrl       控制器路径
PATH_APP_VIEW           ./app/view       视图模板路径
PATH_APP_LIB            ./app/lib        外部库路径
PATH_APP_LOG            ./app/log        日志路径

FILE_APP_CONF           ./app/conf.php   配置文件路径
FILE_APP_COMM           ./app/common.php 公共函数文件路径

CTRL_NAME		        index            控制器名    （驼峰式命名）
ACTION_NAME		        index            控制器方法名（驼峰式命名）
```

## 配置选项

```php
名称                    默认值            说明
ENABLE_SESSION          false            $_SESSION是否可用，默认不可用
DEFAULT_CTRL_ACTION     index/index      默认的控制器/方法(/斜杠分割)

PARAM_CTRL              c                控制器url请求参数，如：/index.php?c=index
PARAM_ACTION            a                控制器方法url请求参数，如：/index.php?a=index

FILE_EXTENSION_CTRL     .class.php       控制器文件扩展名
FILE_EXTENSION_LIB      .class.php       外部库文件扩展名
FILE_EXTENSION_VIEW     .html            模板文件扩展名

POSTFIX_CTRL            Controller       控制器类定义后缀
POSTFIX_ACTION          (空)             控制器方法后缀，默认为空

DB_TYPE                                  数据库类型，如：mysql
DB_HOST                                  数据库主机地址，如：127.0.0.1
DB_PORT                                  数据库端口，如：3306
DB_USER                                  数据库用户名，如：root
DB_PWD                                   数据库密码，如：123456
DB_NAME                                  数据库名，如：test
DB_CHARSET              utf8             数据库编码，默认utf8
```

###### [【单文件框架IndexPHP交流群】](http://jq.qq.com/?_wv=1027&k=dwNtr0) ：281536964
