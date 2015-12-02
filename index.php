<?php

// 单文件入口
IndexPHP::run();

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * 框架入口类
 * 包含自动加载处理、错误处理等
 */
class IndexPHP
{
    /**
     * 执行
     */
	public static function run()
	{
        defined('PATH_APP') or define('PATH_APP', './app/');

        defined('PATH_APP_CTRL') or define('PATH_APP_CTRL', PATH_APP . 'ctrl/');
        defined('PATH_APP_VIEW') or define('PATH_APP_VIEW', PATH_APP . 'view/');
        defined('PATH_APP_LIB') or define('PATH_APP_LIB', PATH_APP . 'lib/');
        defined('PATH_APP_LOG') or define('PATH_APP_LOG', PATH_APP . 'log/');

        defined('FILE_APP_CONF') or define('FILE_APP_CONF', PATH_APP . '/conf.php');
        defined('FILE_APP_COMM') or define('FILE_APP_COMM', PATH_APP . '/common.php');

        // 初始化框架
        self::_initialize();

        Config::set(self::import(FILE_APP_CONF));
        Config::get('ENABLE_SESSION') && session_start();

        $ca = Config::get('DEFAULT_CTRL_ACTION', 'index/index');
        list($c, $a) = explode('/', trim(Param::server('PATH_INFO', $ca), '/'));
        define('CONTROLLER_NAME', strtolower(Param::get(Config::get('PARAM_CTRL', 'c'), $c)));
        define('ACTION_NAME', strtolower(Param::get(Config::get('PARAM_ACTION', 'a'), $a)));

        // 导入控制器文件，判断是否存在
        if(!self::import(PATH_APP_CTRL . CONTROLLER_NAME . Config::get('POSTFIX_CTRL', '.class.php')))
        {
        	self::_error('没有控制器文件');
        }

        // TODO::: sys_news/add_news => SysNewsController::addNews();
        $c = ucwords(CONTROLLER_NAME) . Config::get('POSTFIX_CTRL', 'Controller');
        $a = ACTION_NAME . Config::get('POSTFIX_ACTION', '');
        // preg_replace("/( :^|_)([a-z])/e", "strtoupper('\\1')", $f);
        // strtolower(preg_replace('/((?<=[a-z])(?=[A-Z]))/', '_', $str))

        // 是否存在控制器类
        if (class_exists($c))
        {
            self::import(FILE_APP_COMM);
            spl_autoload_register('self::_autoload');

            call_user_func(array(new $c(), $a));
        }
    }

    /**
     * @param $file
     * @return bool|mixed
     */
    public static function import($file)
    {
	    if (file_exists($file))
	    {
	        return include $file;
	    }

	    return false;
    }


    /**
     * 初始化处理
     */
    private static function _initialize()
    {
        if(!is_dir(PATH_APP))
        {
            mkdir(PATH_APP, 0755);
            mkdir(PATH_APP_CTRL, 0755);
            mkdir(PATH_APP_VIEW, 0755);
            mkdir(PATH_APP_LIB, 0755);
            mkdir(PATH_APP_LOG, 0755);
            file_put_contents(FILE_APP_CONF, "<?php\nreturn array(\n\t//'配置项'=>'配置值'\n);");
            file_put_contents(PATH_APP_CTRL . "index.class.php", "<?php\r\nclass IndexController extends Controller {\r\n\r\n    public function index(){\r\n        \$this->show('hi index-php~');\r\n    }\r\n}");

            // 压缩
            file_put_contents('index.min.php', php_strip_whitespace('index.php'));
        }
        return true;
    }


    /**
     * 自动加载函数
     * @param string $clazz 类名
     * @return bool
     */
    private static function _autoload($clazz)
    {
    	return self::import(PATH_APP_LIB . strtolower($clazz) . Config::get('POSTFIX_LIB', '.class.php'));
    }

    /**
     * 错误处理
     * @param $msg
     */
    private static function _error($msg)
    {
        die($msg);
    }
}



/**
 * 控制器
 */
class Controller
{
    /**
     * @var array
     */
    private $_p = array();

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->_init();
    }


    /**
     * 魔术方法 有不存在的操作的时候执行
     * @access public
     * @param string $method 方法名
     * @param array $args 参数
     * @return mixed
     */
    public function __call($method,$args)
    {
        if(method_exists($this,'_empty'))
        {
            // 如果定义了_empty操作 则调用
            return $this->_empty($method,$args);
        }

        $this->display();
    }


    /**
     *
     */
    protected function _init()
    {
    }

    /**
     * @param $name
     * @param $value
     */
    protected function assign($name, $value)
    {
    	$this->_p[$name] = $value;
    }


    /**
     * @param $text
     * @param null $params
     */
    protected function show($text, $params = null)
    {
        $params = $params ? array_merge($this->_p, $params) : $this->_p;
        View::render($text, $params);
    }


    /**
     * @param null $tpl
     * @param null $params
     */
    protected function display($tpl = null, $params = null)
    {
        is_array($tpl) && list($params, $tpl) = array($tpl, null);
        $tpl || $tpl = CONTROLLER_NAME . '/' . ACTION_NAME;
        // 主题

        $params = $params ? array_merge($this->_p, $params) : $this->_p;
        View::display($tpl, $params, PATH_APP_VIEW);
    }

    /**
     * @param $msg
     * @param null $data
     */
    protected function ok($msg, $data = null)
    {
        $this->json(1, $msg, $data);
    }

    /**
     * @param $msg
     */
    protected function error($msg)
    {
        $this->json(0, $msg);
    }

    /**
     * @param $status
     * @param $msg
     * @param null $data
     */
    protected function json($status, $msg, $data = null)
    {
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode(array('status'=>$status, 'msg'=>$msg, 'data'=>$data)));
    }


    /**
     * 重定向至指定url
     * @param string $url 要跳转的url
     * @param void
     */
    protected function redirect($url)
    {
        header("Location: $url");
        exit;
    }
}



/**
 * 视图类
 */
class View
{

    /**
     * @param $text
     * @param array $data
     * @param bool|true $include
     * @return string
     */
    public static function fetch($text, $data = array(), $include = false)
    {
        // 页面缓存
        ob_start();
        ob_implicit_flush(0);

        // 模板阵列变量分解成为独立变量
        extract($data);
        // 直接载入PHP模板
        $include?include $text:eval('?>'.$text);

        // 获取并清空缓存
        $content = ob_get_clean();

        // 输出模板文件
        return $content;
    }


    /**
     * @param $file
     * @param array $data
     * @param null $path
     */
    public static function display($file, $data = array(), $path = null)
    {
//        extract($data);
//        include $path . $file . Config::get('POSTFIX_VIEW', '.html');
        self::render($path . $file . Config::get('POSTFIX_VIEW', '.html'), $data, true);
    }


    /**
     * @param $text
     * @param array $data
     * @param bool|false $include
     */
    public static function render($text, $data = array(), $include = false)
    {
        $html = self::fetch($text, $data, $include);

//        // 网页字符编码
//        header('Content-Type:'.$contentType.'; charset='.$charset);
//        header('X-Powered-By:index-php.top');
        echo $html;
    }
}


/**
 * 数据模型
 * Class Model
 */
class Model
{
    /**
     * 数据
     * @var array
     */
    private $_d = array();

    /**
     * 操作
     * @var null
     */
    private $_s = null;

    /**
     * 构造函数
     * Model constructor.
     * @param $storage
     */
    private function __construct($storage)
    {
        $this->_s = $storage;
    }


    /**
     * 魔术方法，调用存储对象的方法
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        array_unshift($arguments, $this->_d);
        $data = call_user_func_array(array($this->_s, $name), $arguments);
        is_array($data) && $this->_d = $data;
    }


    /**
     * 实例化对象
     * @param null $storage
     * @return Model
     */
    public static function model($storage = null)
    {
        // TODO:::
        //$storage || $storage = Db::getInstance();
        return new self($storage);
    }

    /**
     * 设置数据对象的值
     * @access public
     * @param string $name 名称
     * @param mixed $value 值
     * @return void
     */
    public function __set($name,$value) {
        // 设置数据对象属性
        $this->_d[$name]  =   $value;
    }

    /**
     * 获取数据对象的值
     * @access public
     * @param string $name 名称
     * @return mixed
     */
    public function __get($name) {
        return $this->_d[$name];
    }

    /**
     * 检测数据对象的值
     * @access public
     * @param string $name 名称
     * @return boolean
     */
    public function __isset($name) {
        return isset($this->_d[$name]);
    }

    /**
     * 销毁数据对象的值
     * @access public
     * @param string $name 名称
     * @return void
     */
    public function __unset($name) {
        unset($this->_d[$name]);
    }
}


/**
 * 配置处理
 * Class Config
 */
class Config
{
    /**
     * 配置缓存
     * @var array
     */
	private static $_c = array();


    /**
     * 取得配置
     * @param $key
     * @param null $default
     * @return array|null
     */
	public static function get($key = null, $default = null)
	{
		if (is_string($key))
		{
            return isset(self::$_c[$key]) ? self::$_c[$key] : $default;
        }

        if (is_array($key))
        {
            $ret = array();
            foreach ($key as $k)
            {
                $ret[$k] = isset(self::$_c[$k]) ? self::$_c[$k] : $default;
            }
            return $ret;
        }

    	return self::$_c;
	}


    /**
     * 设置配置
     * @param $key
     * @param null $value
     * @return array
     */
	public static function set($key, $value= null)
	{
		if (is_string($key))
        {
            self::$_c[$key] = $value;
        }

        if (is_array($key))
        {
        	self::$_c = array_merge(self::$_c, $key);
        }

        return self::$_c;
	}
}


/**
 * 参数处理
 * Class Param
 */
class Param
{
    /**
     * 取得$_GET键对应的值，键值为空返回所有
     * @param null $key
     * @param null $default
     * @return null
     */
	public static function get($key = null, $default = null)
	{
        return self::_param($_GET, $key, $default);
	}

    /**
     * 取得$_POST键对应的值，键值为空返回所有
     * @param null $key
     * @param null $default
     * @return null
     */
	public static function post($key = null, $default = null)
	{
        return self::_param($_POST, $key, $default);
	}

    /**
     * 取得$_REQUEST键对应的值，键值为空返回所有
     * @param null $key
     * @param null $default
     * @return null
     */
	public static function request($key = null, $default = null)
	{
        return self::_param($_REQUEST, $key, $default);
	}

    /**
     * 取得$_SERVER键对应的值，键值为空返回所有
     * @param null $key
     * @param null $default
     * @return null
     */
	public static function server($key = null, $default = null)
	{
        return self::_param($_SERVER, $key, $default);
	}

    /**
     * 取得$_SESSION键对应的值，键值为空返回所有
     * @param null $key
     * @param null $default
     * @return null
     */
    public static function session($key = null, $default = null)
    {
        return self::_param($_SESSION, $key, $default);
    }

    /**
     * 取得$_COOKIE键对应的值，键值为空返回所有
     * @param null $key
     * @param null $default
     * @return null
     */
	public static function cookie($key = null, $default = null)
	{
		return self::_param($_COOKIE, $key, $default);
	}

    /**
     * 取得$data键对应的值，键值为空返回所有
     * @param $data
     * @param null $key
     * @param null $default
     * @return null
     */
    private static function _param($data, $key = null, $default = null)
    {
        if (!isset($key))
        {
            return $data;
        }
        return isset($data[$key]) ? $data[$key] : $default;
    }
}



/**
 * 日志处理
 * Class Logger
 */
class Logger
{
    const INFO = 1;
    const DEBUG = 2;
    const WARNING = 3;
    const ERROR = 4;

    static $LEVEL = array(self::INFO => 'INFO',self::DEBUG => 'DEBUG',self::WARNING => 'WARNING',self::ERROR => 'ERROR');

    /**
     * 日志输出
     * @param $msg
     * @param int $level
     */
    public static function log($msg, $level = self::DEBUG)
    {
        $level = self::$LEVEL[$level];

        $msg = date('[ Y-m-d H:i:s ]') . "[{$level}]" . $msg . "\r\n";

        file_put_contents(PATH_APP_LOG . date('Ymd') . '.log', $msg, FILE_APPEND);
    }

    /**
     * 错误日志
     * @param $msg
     */
    public static function error($msg)
    {
        self::log($msg, self::ERROR);
    }

    /**
     * 警告日志
     * @param $msg
     */
    public static function warning($msg)
    {
        self::log($msg, self::WARNING);
    }

    /**
     * 调试日志
     * @param $msg
     */
    public static function debug($msg)
    {
        self::log($msg, self::DEBUG);
    }

    /**
     * 信息日志
     * @param $msg
     */
    public static function info($msg)
    {
        self::log($msg, self::INFO);
    }
}


/**
 * 数据库操作
 * Class DB
 */
class DB
{
    public static function instance($config = null)
    {
        $config || $config = Config::get();
        $key = md5($config['DB_TYPE'].$config['DB_HOST'].$config['DB_USER']);

        static $_c = array();
        isset($_c[$key]) || $_c[$key] = new self($config);

        return $_c[$key];
    }


    /**
     * @var PDO
     */
    private $_pdo;

    private function __construct($config)
    {
        $dsn = "{$config['DB_TYPE']}:host={$config['DB_HOST']};dbname={$config['DB_NAME']};charset={$config['DB_CHARSET']}";
        $this->_pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PWD']);
    }


    /**
     * 查询
     * @param $query
     * @return mixed
     */
    public function query($query)
    {
        $query = $this->_pdo->query($query);

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * 执行
     * @param $query
     * @return mixed
     */
    public function exec($query)
    {
        return $this->_pdo->exec($query);
    }


    /**
     * 选择
     * @param $table
     * @param null $where
     * @param null $limit
     * @param string $column
     * @return mixed
     */
    public function select($table, $where = null, $limit = null, $column = '*')
    {
        return $this->query('SELECT ' . $column . ' FROM ' . $table . self::_where($where));
    }


    /**
     * 插入
     * @param $table
     * @param $data
     * @return mixed
     */
    public function insert($table, $data)
    {
        $columns = array_keys($data);
        $values = array_values($data);

        $this->exec('INSERT INTO "' . $table . '" (' . implode(', ', $columns) . ') VALUES (' . implode($values, ', ') . ')');

        return $this->pdo->lastInsertId();
    }


    /**
     * 更新
     * @param $table
     * @param $data
     * @param null $where
     * @return mixed
     */
    public function update($table, $data, $where = null)
    {
        $fields = array();
        foreach ($data as $col => $val) {
            $fields[] = "{$col} = {$val}";
        }
        return $this->exec('UPDATE "' . $table . '" SET ' . implode(', ', $fields) . self::_where($where));
    }


    /**
     * 删除
     * @param $table
     * @param null $where
     * @return mixed
     */
    public function delete($table, $where = null)
    {
        return $this->exec('DELETE FROM "' . $table . '"' . self::_where($where));
    }


    /**
     * WHERE条件
     * @param $where
     * @return string
     */
    private static function _where($where)
    {
        if(is_array($where))
        {
            $fields = array();
            foreach ($where as $col => $val) {
                $fields[] = "{$col} = {$val}";
            }
            $where = $fields;
        }

        return  $where ? ' WHERE ' . implode(', ', $where) : '';
    }
}