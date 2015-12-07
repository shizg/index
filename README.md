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
        
        // 从数据库表user中取得年龄>18的用户总数，并且按照年龄倒序排列，返回第11-20条数据。
        list($user_list, $user_count) = Model::db('user')->select(array('age >'=>18), 
                                                                  array('_order'=>'age DESC', '_limit'=>'11,10', '_count'=>'id'));

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

## 命名规范

## 系统常量

## 系统配置
