<?php

@error_reporting(E_ALL);

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
        // 设定错误和异常处理
        set_error_handler('IndexPHP::_error');
        set_exception_handler('IndexPHP::_exception');
        register_shutdown_function('IndexPHP::_shutdown');

        defined('PATH_APP') or define('PATH_APP', './app/');

        defined('PATH_APP_CTRL') or define('PATH_APP_CTRL', PATH_APP . 'ctrl/');
        defined('PATH_APP_VIEW') or define('PATH_APP_VIEW', PATH_APP . 'view/');
        defined('PATH_APP_LIB') or define('PATH_APP_LIB', PATH_APP . 'lib/');
        defined('PATH_APP_LOG') or define('PATH_APP_LOG', PATH_APP . 'log/');

        defined('FILE_APP_CONF') or define('FILE_APP_CONF', PATH_APP . '/conf.php');
        defined('FILE_APP_COMM') or define('FILE_APP_COMM', PATH_APP . '/common.php');

        // 初始化框架
        self::_init();

        Config::set(self::import(FILE_APP_CONF));
        Config::get('ENABLE_SESSION') && session_start();

        $ca = explode('/', trim(Param::server('PATH_INFO', Config::get('DEFAULT_CTRL_ACTION')), '/'));
        define('CTRL_NAME', strtolower(Param::get(Config::get('PARAM_CTRL', 'c'), !empty($ca[0])?$ca[0]:'index')));
        define('ACTION_NAME', strtolower(Param::get(Config::get('PARAM_ACTION', 'a'), !empty($ca[1])?$ca[1]:'index')));

        // 导入控制器文件，判断是否存在
        if (!self::import(PATH_APP_CTRL . CTRL_NAME . Config::get('FILE_EXTENSION_CTRL', '.class.php'))) {
            throw new Exception('没有控制器:' . CTRL_NAME);
        }

        $c = self::camelize(CTRL_NAME) . Config::get('POSTFIX_CTRL', 'Controller');
        $a = lcfirst(self::camelize(ACTION_NAME)) . Config::get('POSTFIX_ACTION', '');

        // 是否存在控制器类
        if (class_exists($c)) {
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
        if (file_exists($file)) {
            return include $file;
        }

        return false;
    }

    public static function decamelize($word)
    {
        return preg_replace(
            '/(^|[a-z])([A-Z])/e',
            'strtolower(strlen("\\1") ? "\\1_\\2" : "\\2")',
            $word
        );
    }

    public static function camelize($word)
    {
        return preg_replace('/(^|_)([a-z])/e', 'strtoupper("\\2")', $word);
    }

    /**
     * 初始化处理
     */
    private static function _init()
    {
        if (!is_dir(PATH_APP)) {
            mkdir(PATH_APP, 0755);
            mkdir(PATH_APP_CTRL, 0755);
            mkdir(PATH_APP_VIEW, 0755);
            mkdir(PATH_APP_LIB, 0755);
            mkdir(PATH_APP_LOG, 0755);
            // file_put_contents(FILE_APP_CONF, "<?php\nreturn array(\n\t//'配置项'=>'配置值'\n);");
            file_put_contents(PATH_APP_CTRL . "index.class.php", "<?php\nclass IndexController extends Controller {\n\n    public function index(){\n        \$this->show('hi index-php~');\n    }\n}");

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
        return self::import(PATH_APP_LIB . self::decamelize($clazz) . Config::get('FILE_EXTENSION_LIB', '.class.php'));
    }


    /**
     * 错误处理
     */
    public static function _error($errno, $errmsg, $errfile, $errline)
    {
        Logger::error("_error : [$errno] $errmsg File: $errfile Line: $errline");
    }

    public static function _exception($ex)
    {
        $errno      = $ex->getCode();
        $errmsg     = $ex->getMessage();
        $errfile    = $ex->getFile();
        $errline    = $ex->getLine();
        $errstr     = $ex->getTraceAsString();

        Logger::error("_exception : [$errno] $errmsg File: $errfile Line: $errline \n $errstr");
    }

    public static function _shutdown()
    {
        if($e = error_get_last())
        {
            Logger::error("_shutdown : [{$e['type']}] {$e['message']} File: {$e['file']} Line: {$e['line']}");
        }
        Logger::save();
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
    public function __call($method, $args)
    {
        if (method_exists($this, '_empty')) {
            // 如果定义了_empty操作 则调用
            return $this->_empty($method, $args);
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
     * @param null $layout
     */
    protected function display($tpl = null, $params = null, $layout = null)
    {
        is_array($tpl) && list($params, $tpl) = array($tpl, null);
        $tpl || $tpl = CTRL_NAME . '/' . ACTION_NAME;
        // 主题

        $params = $params ? array_merge($this->_p, $params) : $this->_p;

        if($layout)
        {
            $params['content'] = View::fetch($tpl, $params, PATH_APP_VIEW);
            $tpl = $layout;
        }

        View::render($tpl, $params, PATH_APP_VIEW);
    }

    /**
     * @param $msg
     * @param null $data
     */
    protected function ok($msg, $data = null)
    {
        is_array($msg) && list($data, $msg) = array($msg, '');
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
        View::render(json_encode(array('status' => $status, 'msg' => $msg, 'data' => $data)), null, null, 'application/json');
        exit;
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
     * @param $file
     * @param null $data
     * @param null $path
     * @return string
     */
    public static function fetch($file, $data = null, $path = null)
    {
        // 页面缓存
        ob_start();
        ob_implicit_flush(0);

        // 模板阵列变量分解成为独立变量
        $data && extract($data);
        // 直接载入PHP模板
        isset($path) ? include $path . $file . Config::get('FILE_EXTENSION_VIEW', '.html') : eval('?>' . $file);

        // 获取并清空缓存
        $content = ob_get_clean();

        // 输出模板文件
        return $content;
    }


    /**
     * @param $file
     * @param null $data
     * @param null $path
     * @param string $contentType
     */
    public static function render($file, $data = null, $path = null, $contentType = 'text/html')
    {
        $html = self::fetch($file, $data, $path);

        header('Content-Type:'.$contentType.'; charset='.Config::get('HTML_CHARSET', 'utf-8'));
        header('X-Powered-By:index-php.top');
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
    public $_model = array();

    /**
     * 数据表&文件
     * @var null
     */
    private $_t = null;

    /**
     * 操作
     * @var null
     */
    private $_s = null; // db & file

    /**
     * 构造函数
     * Model constructor.
     * @param $storage
     */
    private function __construct($table, $storage)
    {
        $this->_t = $table;
        $this->_s = $storage;
    }


    /**
     * 魔术方法，调用存储对象的方法
     * @param $name
     * @param $args
     * @return mixed
     */
    public function __call($name, $args)
    {
        $this->_model && $args[0] = $args[0] ? array_merge($this->_model, $args[0]) : $this->_model;
        $this->_t && array_unshift($args, $this->_t);
        $data = call_user_func_array(array($this->_s, $name), $args);
        is_array($data) && $this->_model = $data;

        return $data;
    }


    /**
     * 实例化对象
     * @param null $table
     * @param bool $db_debug
     * @return Model
     */
    public static function db($table = null, $db_debug = false)
    {
        (!isset($db_debug) || is_bool($db_debug)) && $db_debug = DB::instance(null, $db_debug);
        return new self($table, $db_debug);
    }


    /**
     * 实例化对象
     * @param $file
     * @return Model
     */
    public static function file($file)
    {
        return new self($file, File::instance());
    }

    /**
     * 设置数据对象的值
     * @access public
     * @param string $name 名称
     * @param mixed $value 值
     * @return void
     */
    public function __set($name, $value)
    {
        // 设置数据对象属性
        $this->_model[$name] = $value;
    }

    /**
     * 获取数据对象的值
     * @access public
     * @param string $name 名称
     * @return mixed
     */
    public function __get($name)
    {
        return $this->_model[$name];
    }

    /**
     * 检测数据对象的值
     * @access public
     * @param string $name 名称
     * @return boolean
     */
    public function __isset($name)
    {
        return isset($this->_model[$name]);
    }

    /**
     * 销毁数据对象的值
     * @access public
     * @param string $name 名称
     * @return void
     */
    public function __unset($name)
    {
        unset($this->_model[$name]);
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
        if (is_string($key)) {
            return isset(self::$_c[$key]) ? self::$_c[$key] : $default;
        }

        if (is_array($key)) {
            $ret = array();
            foreach ($key as $k) {
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
    public static function set($key, $value = null)
    {
        if (is_string($key)) {
            self::$_c[$key] = $value;
        }

        if (is_array($key)) {
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
    public static function session($key = null, $default = null, $isset = false)
    {
        $isset && $_SESSION[$key] = $default;

        return self::_param($_SESSION, $key, $default);
    }

    /**
     * 取得$_COOKIE键对应的值，键值为空返回所有
     * @param null $key
     * @param null $default
     * @return null
     */
    public static function cookie($key = null, $default = null, $expire = null)
    {
        $expire && setcookie($key, $default, $expire);

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
        if (!isset($key)) {
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

    private static $LEVEL = array(self::INFO => 'INFO', self::DEBUG => 'DEBUG', self::WARNING => 'WARNING', self::ERROR => 'ERROR');

    private static $_log = array();

    /**
     * 保存日志
     */
    public static function save()
    {
        if(count(self::$_log) > 0)
        {
            file_put_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . PATH_APP_LOG . date('Ymd') . '.log', implode(PHP_EOL, self::$_log).PHP_EOL, FILE_APPEND);
        }
    }

    /**
     * 日志输出
     * @param $msg
     * @param int $level
     */
    public static function log($msg, $level = self::DEBUG)
    {
        $level = self::$LEVEL[$level];
        self::$_log[] = date('[ Y-m-d H:i:s ]') . " [{$level}] " . $msg;
        //file_put_contents(PATH_APP_LOG . date('Ymd') . '.log', $msg, FILE_APPEND);
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
    public static function instance($config = null, $debug = false)
    {
        $config || $config = Config::get();
        $key = md5($config['DB_TYPE'] . $config['DB_HOST'] . $config['DB_USER'] . $debug);

        static $_c = array();
        isset($_c[$key]) || $_c[$key] = new self($config, $debug);

        return $_c[$key];
    }

    private $_debug;

    private $_pre;

    /**
     * @var PDO
     */
    private $_pdo;

    private function __construct($config, $debug)
    {
        $dsn = "{$config['DB_TYPE']}:host={$config['DB_HOST']};dbname={$config['DB_NAME']};charset={$config['DB_CHARSET']}";
        $this->_pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PWD']);
        $this->_pre = Config::get('DB_PREFIX');
        $this->_debug = $debug;
    }


    /**
     * 查询
     * @param $query
     * @return mixed
     */
    public function query($query)
    {
        $this->_debug && Logger::debug($query);

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
        $this->_debug && Logger::debug($query);

        return $this->_pdo->exec($query);
    }


    /**
     * 选择
     * @param $table
     * @param null $where
     * @param null $option
     * @return mixed
     */
    public function select($table, $where = null, $option = null)
    {
        !is_array($option) && $option = array('_column'=>$option);

        $column = $option['_column'] ? : '*';
        $order = isset($option['_order']) ? ' ORDER BY ' . $option['_order'] : '';
        $limit = isset($option['_limit']) ? ' LIMIT ' . $option['_limit'] : '';

        is_int($where) && $where = array('id' => $where);
        $where = $this->_where($where);

        $rows = $this->query('SELECT ' . $column . ' FROM ' . $this->_pre . $table . $where. $order . $limit);

        if(!isset($option['_count']))
        {
            return $rows;
        }

        $count = $this->query('SELECT COUNT(' . $option['_count'] . ') _count FROM ' . $this->_pre . $table . $where);

        return array($rows, $count[0]['_count']);
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

        $values = array_map(array($this, '_quote'), $values);
        $this->exec('INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode($values, ', ') . ')');

        return $this->_pdo->lastInsertId();
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
        $where || $where = array('id' => $data['id']);

        $data && $data = $this->_set($data);

        return $this->exec('UPDATE ' . $table . ' SET ' . implode(', ', $data) . $this->_where($where));
    }


    /**
     * 删除
     * @param $table
     * @param null $where
     * @return mixed
     */
    public function delete($table, $where = null)
    {
        is_int($where) && $where = array('id' => $where);
        return $this->exec('DELETE FROM ' . $table . $this->_where($where));
    }


    /**
     * WHERE条件
     * @param $where
     * @return string
     */
    private function _where($where, $prefix = ' WHERE ')
    {
        $logic = isset($where['_logic']) ? $where['_logic'] : 'AND';
        isset($where['_where']) && $_where = $this->_where($where['_where'], '');
        unset($where['_logic']);unset($where['_where']);

        is_array($where) && $where = $this->_set($where);

        isset($_where) && $where[] = $_where;

        return $where ? $prefix .  '(' . implode(" {$logic} ", $where) . ')' : '';
    }

    private function _set($data)
    {
        $fields = array();
        foreach ($data as $col => $val) {

            //is_array($val) || $val = array('=', $val);
            //$fields[] = $col . $val[0] . $this->_quote($val[1]);

            $col = explode(' ', $col);
            $fields[] = $col[0] . (isset($col[1]) ? $col[1] : '=') . $this->_quote($val);
        }

        return $fields;
    }

    private function _quote($value)
    {
        return $this->_pdo->quote($value);
    }
}


/**
 * 文件处理
 * Class File
 */
class File
{

    /**
     * 文件实例
     * @return mixed
     */
    public static function instance()
    {
        static $_i = null;
        $_i || $_i = new self();
        return $_i;
    }


    /**
     * 数据载入
     * @param $file
     * @param null $expire
     * @return mixed|null
     */
    public function load($file, $expire = null)
    {
        // 有效时间判断
        if ($expire && time() - $expire > filemtime($file)) {
            return null;
        }

        return unserialize(file_get_contents($file));
    }


    /**
     * 数据保存
     * @param $file
     * @param $data
     * @return int
     */
    public function save($file, $data)
    {
        return file_put_contents($file, serialize($data));
    }


    /**
     * 文件删除
     * @param $file
     * @return bool
     */
    public function delete($file)
    {
        return unlink($file);
    }
}
