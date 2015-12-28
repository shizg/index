<?php
@error_reporting(0);

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

        // 定义常量
        defined('PATH_APP') or define('PATH_APP', './app/');

        defined('PATH_APP_CTRL') or define('PATH_APP_CTRL', PATH_APP . 'ctrl/');
        defined('PATH_APP_VIEW') or define('PATH_APP_VIEW', PATH_APP . 'view/');
        defined('PATH_APP_LIB') or define('PATH_APP_LIB', PATH_APP . 'lib/');
        defined('PATH_APP_LOG') or define('PATH_APP_LOG', PATH_APP . 'log/');

        defined('FILE_APP_CONF') or define('FILE_APP_CONF', PATH_APP . '/conf.php');
        defined('FILE_APP_COMM') or define('FILE_APP_COMM', PATH_APP . '/common.php');

        define('IS_POST', Param::server('REQUEST_METHOD') =='POST' ? true : false);

        // 初始化框架
        self::_init();

        // 导入配置
        Config::set(self::import(FILE_APP_CONF));
        Config::get('ENABLE_SESSION') && session_start();

        // 路由处理
        $ca = explode('/', trim(Param::server('PATH_INFO', Config::get('DEFAULT_CTRL_ACTION')), '/'));
        define('CTRL_NAME', strtolower(Param::get(Config::get('PARAM_CTRL', 'c'), !empty($ca[0]) ? $ca[0] : 'index')));
        define('ACTION_NAME', strtolower(Param::get(Config::get('PARAM_ACTION', 'a'), !empty($ca[1]) ? $ca[1] : 'index')));

        // 导入控制器文件
        if (!self::import(PATH_APP_CTRL . CTRL_NAME . Config::get('FILE_EXTENSION_CTRL', '.class.php'))) {
            throw new Exception('没有控制器:' . CTRL_NAME);
        }

        // 控制器、方法名称变换处理
        $c = self::camelize(CTRL_NAME) . Config::get('POSTFIX_CTRL', 'Controller');
        $a = lcfirst(self::camelize(ACTION_NAME)) . Config::get('POSTFIX_ACTION', '');

        // 控制器类判断是否存在
        if (class_exists($c)) {
            // 导入公共函数库
            self::import(FILE_APP_COMM);
            // 自动加载外部库
            spl_autoload_register('self::_autoload');

            // 调用控制器方法
            call_user_func(array(new $c(), $a));
        }
    }


    /**
     * 导入文件
     * @param $file
     * @return bool|mixed
     */
    public static function import($file)
    {
        // 判断文件是否存在，存在则导入
        if (file_exists($file)) {
            return include $file;
        }

        return false;
    }


    /**
     * 驼峰命名转下划线命名
     * @param $word
     * @return mixed
     */
    public static function decamelize($word)
    {
        return preg_replace('/(^|[a-z])([A-Z])/e', 'strtolower(strlen("\\1") ? "\\1_\\2" : "\\2")', $word);
    }


    /**
     * 下划线命名转驼峰命名
     * @param $word
     * @return mixed
     */
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
            file_put_contents(".htaccess", "<IfModule mod_rewrite.c>\n   RewriteEngine on\n   RewriteCond %{REQUEST_FILENAME} !-d\n   RewriteCond %{REQUEST_FILENAME} !-f\n   RewriteRule ^(.*)$ index.php/$1 [QSA,PT]\n</IfModule>");

            // 压缩源文件
            ($min = php_strip_whitespace(__FILE__)) && (strlen($min) < filesize(__FILE__)) && (file_put_contents('index.min.php', $min));
        }
        return true;
    }


    /**
     * 自动导入外部库
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

    /**
     * 异常处理
     * @param $ex
     */
    public static function _exception($ex)
    {
        $errno      = $ex->getCode();
        $errmsg     = $ex->getMessage();
        $errfile    = $ex->getFile();
        $errline    = $ex->getLine();
        $errstr     = $ex->getTraceAsString();

        Logger::error("_exception : [$errno] $errmsg File: $errfile Line: $errline \n $errstr");
    }


    /**
     * 关闭处理
     */
    public static function _shutdown()
    {
        // 判断是否存在错误，存在则写入Log
        if ($e = error_get_last()) {
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
     * 主题
     * @var
     */
    protected $_theme;

    /**
     * Controller constructor.
     */
    public function __construct() {
        $this->_init();
    }


    /**
     * 魔术方法 有不存在的操作的时候执行
     * @access public
     * @param string $method 方法名
     * @param array $args 参数
     * @return mixed
     */
    public function __call($method, $args) {
        // 存在_empty 空方法时则执行_empty方法
        if (method_exists($this, '_empty')) {
            // 如果定义了_empty操作 则调用
            return $this->_empty($method, $args);
        }

        // 直接渲染模板
        $this->display();
    }


    /**
     * 初始化处理
     */
    protected function _init() {
    }

    /**
     * 模板赋值
     * @param $name
     * @param $value
     */
    protected function assign($name, $value) {
        $this->_p[$name] = $value;
    }


    /**
     * 显示内容
     * @param $text
     * @param null $params
     */
    protected function show($text, $params = null) {
        $params = $params ? array_merge($this->_p, $params) : $this->_p;
        View::render($text, $params, null);
    }


    /**
     * 显示模板
     * @param null $tpl
     * @param null $params
     * @param null $layout
     */
    protected function display($tpl = null, $params = null, $layout = null) {
        is_array($tpl) && list($tpl, $params, $layout) = array(null, $tpl, $params);
        $tpl || $tpl = CTRL_NAME . '/' . ACTION_NAME;
        $this->_theme && $tpl = $this->_theme . '/' . $tpl; // 主题

        $params = $params ? array_merge($this->_p, $params) : $this->_p;

        // 如果存在布局参数，则将模板数据写到content参数中，然后通过布局输出
        if ($layout) {
            $params['content'] = View::fetch($tpl, $params, PATH_APP_VIEW);
            $tpl = $this->_theme ? $this->_theme . '/' . $layout : $layout; // 主题
        }

        View::render($tpl, $params);
    }

    /**
     * 正常输出
     * @param $msg
     * @param null $data
     */
    protected function ok($msg, $data = null) {
        is_array($msg) && list($msg, $data) = array(null, $msg);
        $this->json(1, $msg, $data);
    }

    /**
     * 错误输出
     * @param $msg
     */
    protected function error($msg) {
        $this->json(0, $msg);
    }

    /**
     * 返回json
     * @param $status
     * @param $msg
     * @param null $data
     */
    protected function json($status, $msg, $data = null) {
        View::render(json_encode(array('status' => $status, 'msg' => $msg, 'data' => $data)), null, null, 'application/json');
        exit;
    }


    /**
     * 重定向至指定url
     * @param string $url 要跳转的url
     * @param void
     */
    protected function redirect($url) {
        header("Location: $url");exit;
    }
}


/**
 * 视图类
 */
class View
{

    /**
     * 渲染模板或者数据，通过参数返回
     * @param $file
     * @param null $data
     * @param null $path
     * @return string
     */
    public static function fetch($file, $data = null, $path = null) {
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
     * 显示模板或数据内容
     * @param $file
     * @param null $data
     * @param string $path
     * @param string $contentType
     */
    public static function render($file, $data = null, $path = PATH_APP_VIEW, $contentType = 'text/html') {
        $html = self::fetch($file, $data, $path);

        header('Content-Type:' . $contentType . '; charset=' . Config::get('HTML_CHARSET', 'utf-8'));
        header('X-Powered-By:index-php.top');
        echo $html;
    }
}


/**
 * 数据模型
 * Class Model
 */
class Model {

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
     * Model constructor
     * @param $table
     * @param $storage
     */
    private function __construct($table, $storage) {
        $this->_t = $table;
        $this->_s = $storage;
    }


    /**
     * 魔术方法，调用存储对象的方法
     * @param $name
     * @param $args
     * @return mixed
     */
    public function __call($name, $args) {
        // 第一个参数与Model数据合并
        $this->_model && $args[0] = ($args[0] ? array_merge($this->_model, $args[0]) : $this->_model);

        // 存在数据表参数时将数据表参数作为第一个参数值
        $this->_t && array_unshift($args, $this->_t);

        // 调用数据库/文件方法
        $data = call_user_func_array(array($this->_s, $name), $args);

        // 如果返回数据为数组，则保存到Model属性_model中
        is_array($data) && $this->_model = $data;

        return $data;
    }


    /**
     * 通过数据库对象实例化Model
     * @param null $table
     * @param bool $db_debug
     * @return Model
     */
    public static function db($table = null, $db_debug = false) {
        (!isset($db_debug) || is_bool($db_debug)) && $db_debug = DB::instance(null, $db_debug);
        return new self($table, $db_debug);
    }


    /**
     * 通过文件操作对象实例化Model
     * @param $file
     * @return Model
     */
    public static function file($file) {
        return new self($file, File::instance());
    }

    /**
     * 设置数据对象的值
     * @access public
     * @param string $name 名称
     * @param mixed $value 值
     * @return void
     */
    public function __set($name, $value) {
        // 设置数据对象属性
        $this->_model[$name] = $value;
    }

    /**
     * 获取数据对象的值
     * @access public
     * @param string $name 名称
     * @return mixed
     */
    public function __get($name) {
        return $this->_model[$name];
    }

    /**
     * 检测数据对象的值
     * @access public
     * @param string $name 名称
     * @return boolean
     */
    public function __isset($name) {
        return isset($this->_model[$name]);
    }

    /**
     * 销毁数据对象的值
     * @access public
     * @param string $name 名称
     * @return void
     */
    public function __unset($name) {
        unset($this->_model[$name]);
    }
}


/**
 * 配置处理
 * Class Config
 */
class Config {
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
    public static function get($key = null, $default = null) {
        // 返回单个配置
        if (is_string($key)) {
            return isset(self::$_c[$key]) ? self::$_c[$key] : $default;
        }

        // 返回一组配置
        if (is_array($key)) {
            $ret = array();
            foreach ($key as $k) {
                $ret[$k] = isset(self::$_c[$k]) ? self::$_c[$k] : $default;
            }
            return $ret;
        }

        // 返回所有配置
        return self::$_c;
    }


    /**
     * 设置配置
     * @param $key
     * @param null $value
     * @return array
     */
    public static function set($key, $value = null) {
        // 设置单个配置
        if (is_string($key)) {
            self::$_c[$key] = $value;
        }

        // 多个配置设置合并
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
    public static function url($action = ACTION_NAME, $ctrl = CTRL_NAME, $params = null)
    {
        is_array($params) && $params = http_build_query($params);
        return "/{$ctrl}/{$action}?{$params}";
    }

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
        // 如果Key值为空，返回参数数组
        if (!isset($key)) {
            return $data;
        }

        // 返回Key对应的参数值，空则返回默认值
        return isset($data[$key]) ? $data[$key] : $default;
    }
}


/**
 * 日志处理
 * Class Logger
 */
class Logger
{
    const INFO = 1;     // 日志类型 信息
    const DEBUG = 2;    // 日志类型 调试
    const WARNING = 3;  // 日志类型 警告
    const ERROR = 4;    // 日志类型 错误

    // 类型对应文字
    private static $LEVEL = array(self::INFO => 'INFO', self::DEBUG => 'DEBUG', self::WARNING => 'WARNING', self::ERROR => 'ERROR');

    // 日志列表
    private static $_log = array();

    /**
     * 保存日志
     */
    public static function save()
    {
        if (count(self::$_log) > 0) {
            file_put_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . PATH_APP_LOG . date('Ymd') . '.log', implode(PHP_EOL, self::$_log) . PHP_EOL, FILE_APPEND);
            self::$_log = array();
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
    private static $OP = array('=', '!=', '<>', '>', '>=', '<', '<=');

    /**
     * 是否输出调试日志
     * @var
     */
    private $_debug;

    /**
     * 表前缀
     * @var
     */
    private $_pre;

    /**
     * @var PDO
     */
    private $_pdo;


    /**
     * @param null $config
     * @param bool|false $debug
     * @return mixed
     */
    public static function instance($config = null, $debug = false)
    {
        $config || $config = Config::get();
        $key = md5($config['DB_TYPE'] . $config['DB_HOST'] . $config['DB_USER'] . $debug);

        static $_c = array();
        isset($_c[$key]) || $_c[$key] = new self($config, $debug);

        return $_c[$key];
    }


    /**
     * DB constructor.
     * @param $config
     * @param $debug
     */
    private function __construct($config, $debug = false)
    {
        isset($config['DB_CHARSET']) || $config['DB_CHARSET'] = 'utf8';
        $dsn = "{$config['DB_TYPE']}:host={$config['DB_HOST']};port={$config['DB_PORT']};dbname={$config['DB_NAME']};charset={$config['DB_CHARSET']}";

        $this->_pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PWD']);
        $this->_pre = $config['DB_PREFIX'];
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
        return $query ? $query->fetchAll(PDO::FETCH_ASSOC) : false;
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
     * @param $table
     * @param null $where
     * @param null $option
     * @return bool
     */
    public function find($table, $where = null, $option = null)
    {
        $limit = array('_limit' => 1);
        !is_array($option) && $option = array('_column' => $option);
        $option = array_merge($option, $limit);
        unset($option['_count']);

        $items = $this->select($table, $where, $option);
        return $items ? $items[0] : false;
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
        ctype_alnum($where) && $where = array('id' => $where);
        $where = $this->_where($where);

        !is_array($option) && $option = array('_column' => $option);

        $column = $option['_column'] ?: '*';
        $group = isset($option['_group']) ? ' GROUP BY ' . $option['_group'] : '';
        $order = isset($option['_order']) ? ' ORDER BY ' . $option['_order'] : '';
        $limit = isset($option['_limit']) ? ' LIMIT ' . $option['_limit'] : '';

        $rows = $this->query('SELECT ' . $column . ' FROM ' . $this->_pre . $table . $where . $group . $order . $limit);

        if (!isset($option['_count'])) {
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
        isset($data[0]) || $data = array($data);

        $ids = array();
        foreach($data as $item)
        {
            $item = $this->_quote($item);

            $this->exec('INSERT INTO ' . $this->_pre . $table . ' (' . implode(', ', array_keys($item)) . ') VALUES (' . implode(array_values($item), ', ') . ')');

            $ids[] = $this->_pdo->lastInsertId();
        }

        return (count($ids) == 1) ? $ids[0] : $ids;
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

        $data = $this->_quote($data);

        $sets = array();
        foreach ($data as $key => $val) {
            $sets[] = "$key = $val";
        }

        return $this->exec('UPDATE ' . $this->_pre . $table . ' SET ' . implode(', ', $sets) . $this->_where($where));
    }


    /**
     * 删除
     * @param $table
     * @param null $where
     * @return mixed
     */
    public function delete($table, $where = null)
    {
        ctype_alnum($where) && $where = array('id' => $where);
        return $this->exec('DELETE FROM ' . $this->_pre . $table . $this->_where($where));
    }


    /**
     * WHERE条件
     * @param $where
     * @param string $prefix
     * @return string
     */
    private function _where($where, $prefix = ' WHERE ')
    {
        if (empty($where) || is_string($where)) {
            return empty($where) ? '' : $prefix . $where;
        }

        $logic = isset($where['_logic']) ? strtoupper($where['_logic']) : 'AND';
        isset($where['_where']) && $_where = $this->_where($where['_where'], '');
        unset($where['_logic'], $where['_where']);

        $items = $this->_quote($where);

        $where = array();
        foreach ($items as $key => $val) {

            // column operator
            list($col, $op) = explode(' ', $key, 2);
            $op = isset($op) ? strtoupper($op) : '=';

            // [~]BETWEEN/IN/IS/LIKE
            $not = (strpos($op, '~') === 0) ? ' NOT ' : null;
            $not && ($op = substr($op, 1));
            switch($op){
                case 'BETWEEN':
                    $op = $not .$op;
                    $val = "{$val[0]} AND {$val[1]}";
                    break;
                case 'IN':
                    $op = $not .$op;
                    $val = '('. (is_array($val) ? implode(',', $val) : $val) . ')';
                    break;
                case 'IS':
                    $op = $op .$not;
                    $val = ($val == "''") ? 'NULL' : $val;
                    break;
                case 'LIKE':
                    $op = $not .$op;
                    $val = "CONCAT('%', {$val}, '%')";
                    break;
                default:
                    !in_array($op, self::$OP) && ($op = false);
                    break;
            }

            $op && $where[] = "$col $op $val";
        }

        isset($_where) && $where[] = $_where;

        return $where ? $prefix . '(' . implode(" {$logic} ", $where) . ')' : '';
    }


    /**
     * 参数转义，根据字段判断是否需要转义
     *  #column => value 不需要转义
     *   column => value 需要转义
     * @param $data
     * @return array
     */
    private function _quote($data)
    {
        $fun_quote = array($this->_pdo, 'quote');

        $items = array();
        foreach($data as $col => $val)
        {
            // 如果字段以#开头则不需要转义，否则需要转义
            (strpos($col, '#') === 0) ? ($col = substr($col, 1)) : ($val = is_array($val) ? array_map($fun_quote, $val) : $this->_pdo->quote($val));

            $items[$col] = $val;
        }

        return $items;
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
