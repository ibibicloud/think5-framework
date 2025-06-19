<?php

namespace think;

use think\exception\ClassNotFoundException;
use think\exception\HttpResponseException;
use think\route\Dispatch;

/**
 * App 应用管理
 */
class App extends Container
{
    /**
     * 当前模块路径
     * @var string
     */
    protected $modulePath;

    /**
     * 应用调试模式
     * @var bool
     */
    protected $appDebug = true;

    /**
     * 应用开始时间
     * @var float
     */
    protected $beginTime;

    /**
     * 应用内存初始占用
     * @var integer
     */
    protected $beginMem;

    /**
     * 应用类库命名空间
     * @var string
     */
    protected $namespace = 'app';

    /**
     * 应用类库后缀
     * @var bool
     */
    protected $suffix = false;

    /**
     * 严格路由检测
     * @var bool
     */
    protected $routeMust;

    /**
     * 应用类库目录
     * @var string
     */
    protected $appPath;

    /**
     * 框架目录
     * @var string
     */
    protected $thinkPath;

    /**
     * 应用根目录
     * @var string
     */
    protected $rootPath;

    /**
     * 运行时目录
     * @var string
     */
    protected $runtimePath;

    /**
     * 配置目录
     * @var string
     */
    protected $configPath;

    /**
     * 路由目录
     * @var string
     */
    protected $routePath;

    /**
     * 配置后缀
     * @var string
     */
    protected $configExt;

    /**
     * 应用调度实例
     * @var Dispatch
     */
    protected $dispatch;

    /**
     * 绑定模块（控制器）
     * @var string
     */
    protected $bindModule;

    /**
     * 初始化
     * @var bool
     */
    protected $initialized = false;

    public function __construct($appPath = '')
    {
        $this->thinkPath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        $this->path($appPath);
    }

    /**
     * 绑定模块或者控制器
     * @access public
     * @param  string $bind
     * @return $this
     */
    public function bind($bind)
    {
        $this->bindModule = $bind;
        return $this;
    }

    /**
     * 设置应用类库目录
     * @access public
     * @param  string $path 路径
     * @return $this
     */
    public function path($path)
    {
        $this->appPath = $path ? realpath($path) . DIRECTORY_SEPARATOR : $this->getAppPath();
        
        return $this;
    }

    /**
     * 初始化应用
     * @access public
     * @return void
     */
    public function initialize()
    {
        // 注册错误和异常处理机制
        Error::register();

        if ($this->initialized) {
            return;
        }

        $this->initialized = true;
        $this->beginTime   = microtime(true);
        $this->beginMem    = memory_get_usage();

        $this->rootPath    = dirname($this->appPath) . DIRECTORY_SEPARATOR;
        $this->runtimePath = $this->rootPath . 'runtime' . DIRECTORY_SEPARATOR;
        $this->routePath   = $this->rootPath . 'config' . DIRECTORY_SEPARATOR;
        $this->configPath  = $this->rootPath . 'config' . DIRECTORY_SEPARATOR;

        static::setInstance($this);

        $this->instance('app', $this);

        // 加载环境变量配置文件
        if (is_file($this->rootPath . '.env')) {
            $this->env->load($this->rootPath . '.env');
        }

        $this->configExt = $this->env->get('config_ext', '.php');

        // 加载惯例配置文件
        $this->config->set(include $this->thinkPath . 'config.php');

        // 设置路径环境变量
        $this->env->set([
            'think_path'   => $this->thinkPath,
            'root_path'    => $this->rootPath,
            'app_path'     => $this->appPath,
            'config_path'  => $this->configPath,
            'route_path'   => $this->routePath,
            'runtime_path' => $this->runtimePath,
            'extend_path'  => $this->rootPath . 'extend' . DIRECTORY_SEPARATOR,
            'vendor_path'  => $this->rootPath . 'vendor' . DIRECTORY_SEPARATOR,
        ]);

        $this->namespace = $this->env->get('app_namespace', $this->namespace);
        $this->env->set('app_namespace', $this->namespace);

        // 初始化应用
        $this->init();

        // 开启类名后缀
        $this->suffix = $this->config('app.class_suffix');

        // 应用调试模式
        $this->appDebug = $this->env->get('app_debug', $this->config('app.app_debug'));
        $this->env->set('app_debug', $this->appDebug);

        if (!$this->appDebug) {
            ini_set('display_errors', 'Off');
        } elseif (PHP_SAPI != 'cli') {
            //重新申请一块比较大的buffer
            if (ob_get_level() > 0) {
                $output = ob_get_clean();
            }
            ob_start();
            if (!empty($output)) {
                echo $output;
            }
        }

        // 注册异常处理类
        if ($this->config('app.exception_handle')) {
            Error::setExceptionHandler($this->config('app.exception_handle'));
        }

        // 数据库配置初始化
        Db::init($this->config->pull('database'));

        // 设置系统时区
        date_default_timezone_set($this->config('app.default_timezone'));

        // 路由初始化
        $this->routeInit();
    }

    /**
     * 初始化应用或模块
     * @access public
     * @param  string $module 模块名
     * @return void
     */
    public function init($module = '')
    {
        // 定位模块目录
        $module = $module ? $module . DIRECTORY_SEPARATOR : '';
        $path   = $this->appPath . $module;

        // 加载自定义函数
        if (is_file($path . 'functions.php')) {
            include_once $path . 'functions.php';
        }

        // 加载框架助手函数
        if ('' == $module) {
            include $this->thinkPath . 'helper.php';
        }

        // 加载中间件
        if (is_file($path . 'middleware.php')) {
            $middleware = include $path . 'middleware.php';
            if (is_array($middleware)) {
                $this->middleware->import($middleware);
            }
        }

        // 自动读取配置文件
        if (is_dir($path . 'config')) {
            $dir = $path . 'config' . DIRECTORY_SEPARATOR;
        } elseif (is_dir($this->configPath . $module)) {
            $dir = $this->configPath . $module;
        }

        $files = isset($dir) ? scandir($dir) : [];

        foreach ($files as $file) {
            if ('.' . pathinfo($file, PATHINFO_EXTENSION) === $this->configExt) {
                $this->config->load($dir . $file, pathinfo($file, PATHINFO_FILENAME));
            }
        }

        $this->setModulePath($path);
    }


    /**
     * 执行应用程序
     * @access public
     * @return Response
     * @throws Exception
     */
    public function run()
    {
        try {
            // 初始化应用
            $this->initialize();

            if ($this->bindModule) {
                // 模块/控制器绑定
                $this->route->bind($this->bindModule);
            } elseif ($this->config('app.auto_bind_module')) {
                // 入口自动绑定
                $name = pathinfo($this->request->baseFile(), PATHINFO_FILENAME);
                if ($name && 'index' != $name && is_dir($this->appPath . $name)) {
                    $this->route->bind($name);
                }
            }

            $dispatch = $this->dispatch;

            if (empty($dispatch)) {
                // 路由检测
                $dispatch = $this->routeCheck()->init();
            }

            // 记录当前调度信息
            $this->request->dispatch($dispatch);

            // 记录路由和请求信息
            if ($this->appDebug) {
                $this->log('[ ROUTE ] ' . var_export($this->request->routeInfo(), true));
                $this->log('[ HEADER ] ' . var_export($this->request->header(), true));
                $this->log('[ PARAM ] ' . var_export($this->request->param(), true));
            }

            // 请求缓存检查
            $this->checkRequestCache(
                $this->config('app.request_cache'),
                $this->config('app.request_cache_expire'),
                $this->config('app.request_cache_except')
            );

            $data = null;
        } catch (HttpResponseException $exception) {
            $dispatch = null;
            $data     = $exception->getResponse();
        }

        $this->middleware->add(function (Request $request, $next) use ($dispatch, $data) {
            return is_null($data) ? $dispatch->run() : $data;
        });

        $response = $this->middleware->dispatch($this->request);

        return $response;
    }

    protected function getRouteCacheKey()
    {
        if ($this->config->get('route_check_cache_key')) {
            $closure  = $this->config->get('route_check_cache_key');
            $routeKey = $closure($this->request);
        } else {
            $routeKey = md5($this->request->baseUrl(true) . ':' . $this->request->method());
        }

        return $routeKey;
    }

    /**
     * 设置当前地址的请求缓存
     * @access public
     * @param  string $key 缓存标识，支持变量规则 ，例如 item/:name/:id
     * @param  mixed  $expire 缓存有效期
     * @param  array  $except 缓存排除
     * @param  string $tag    缓存标签
     * @return void
     */
    public function checkRequestCache($key, $expire = null, $except = [], $tag = null)
    {
        $cache = $this->request->cache($key, $expire, $except, $tag);

        if ($cache) {
            $this->setResponseCache($cache);
        }
    }

    public function setResponseCache($cache)
    {
        list($key, $expire, $tag) = $cache;

        if (strtotime($this->request->server('HTTP_IF_MODIFIED_SINCE')) + $expire > $this->request->server('REQUEST_TIME')) {
            // 读取缓存
            $response = Response::create()->code(304);
            throw new HttpResponseException($response);
        } elseif ($this->cache->has($key)) {
            list($content, $header) = $this->cache->get($key);

            $response = Response::create($content)->header($header);
            throw new HttpResponseException($response);
        }
    }

    /**
     * 设置当前请求的调度信息
     * @access public
     * @param  Dispatch  $dispatch 调度信息
     * @return $this
     */
    public function dispatch(Dispatch $dispatch)
    {
        $this->dispatch = $dispatch;
        return $this;
    }

    /**
     * 记录调试信息
     * @access public
     * @param  mixed  $msg  调试信息
     * @param  string $type 信息类型
     * @return void
     */
    public function log($msg, $type = 'info')
    {
        $this->appDebug && $this->log->record($msg, $type);
    }

    /**
     * 获取配置参数 为空则获取所有配置
     * @access public
     * @param  string    $name 配置参数名（支持二级配置 .号分割）
     * @return mixed
     */
    public function config($name = '')
    {
        return $this->config->get($name);
    }

    /**
     * 路由初始化 导入路由定义规则
     * @access public
     * @return void
     */
    public function routeInit()
    {
        // 路由检测
        if (is_dir($this->routePath)) {
            $files = glob($this->routePath . '*.route');
            foreach ($files as $file) {
                $rules = include $file;
                if (is_array($rules)) {
                    $this->route->import($rules);
                }
            }
        }

        if ($this->route->config('route_annotation')) {
            // 自动生成路由定义
            if ($this->appDebug) {
                $suffix = $this->route->config('controller_suffix') || $this->route->config('class_suffix');
                $this->build->buildRoute($suffix);
            }

            $filename = $this->runtimePath . 'build_route.php';

            if (is_file($filename)) {
                include $filename;
            }
        }
    }

    /**
     * URL路由检测（根据PATH_INFO)
     * @access public
     * @return Dispatch
     */
    public function routeCheck()
    {
        // 检测路由缓存
        if (!$this->appDebug && $this->config->get('route_check_cache')) {
            $routeKey = $this->getRouteCacheKey();
            $option   = $this->config->get('route_cache_option');

            if ($option && $this->cache->connect($option)->has($routeKey)) {
                return $this->cache->connect($option)->get($routeKey);
            } elseif ($this->cache->has($routeKey)) {
                return $this->cache->get($routeKey);
            }
        }

        // 获取应用调度信息
        $path = $this->request->path();

        // 是否强制路由模式
        $must = !is_null($this->routeMust) ? $this->routeMust : $this->route->config('url_route_must');

        // 路由检测 返回一个Dispatch对象
        $dispatch = $this->route->check($path, $must);

        if (!empty($routeKey)) {
            try {
                if ($option) {
                    $this->cache->connect($option)->tag('route_cache')->set($routeKey, $dispatch);
                } else {
                    $this->cache->tag('route_cache')->set($routeKey, $dispatch);
                }
            } catch (\Exception $e) {
                // 存在闭包的时候缓存无效
            }
        }

        return $dispatch;
    }

    /**
     * 设置应用的路由检测机制
     * @access public
     * @param  bool $must  是否强制检测路由
     * @return $this
     */
    public function routeMust($must = false)
    {
        $this->routeMust = $must;
        return $this;
    }

    /**
     * 解析模块和类名
     * @access protected
     * @param  string $name         资源地址
     * @param  string $layer        验证层名称
     * @param  bool   $appendSuffix 是否添加类名后缀
     * @return array
     */
    protected function parseModuleAndClass($name, $layer, $appendSuffix)
    {
        if (false !== strpos($name, '\\')) {
            $class  = $name;
            $module = $this->request->module();
        } else {
            if (strpos($name, '/')) {
                list($module, $name) = explode('/', $name, 2);
            } else {
                $module = $this->request->module();
            }

            $class = $this->parseClass($module, $layer, $name, $appendSuffix);
        }

        return [$module, $class];
    }

    /**
     * 实例化应用类库
     * @access public
     * @param  string $name         类名称
     * @param  string $layer        业务层名称
     * @param  bool   $appendSuffix 是否添加类名后缀
     * @param  string $common       公共模块名
     * @return object
     * @throws ClassNotFoundException
     */
    public function create($name, $layer, $appendSuffix = false, $common = 'common')
    {
        $guid = $name . $layer;

        if ($this->__isset($guid)) {
            return $this->__get($guid);
        }

        list($module, $class) = $this->parseModuleAndClass($name, $layer, $appendSuffix);

        if (class_exists($class)) {
            $object = $this->__get($class);
        } else {
            $class = str_replace('\\' . $module . '\\', '\\' . $common . '\\', $class);
            if (class_exists($class)) {
                $object = $this->__get($class);
            } else {
                throw new ClassNotFoundException('class not exists:' . $class, $class);
            }
        }

        $this->__set($guid, $class);

        return $object;
    }

    /**
     * 实例化（分层）控制器 格式：[模块名/]控制器名
     * @access public
     * @param  string $name              资源地址
     * @param  string $layer             控制层名称
     * @param  bool   $appendSuffix      是否添加类名后缀
     * @param  string $empty             空控制器名称
     * @return object
     * @throws ClassNotFoundException
     */
    public function controller($name, $layer = 'controller', $appendSuffix = false, $empty = '')
    {
        list($module, $class) = $this->parseModuleAndClass($name, $layer, $appendSuffix);

        if (class_exists($class)) {
            return $this->make($class, true);
        } elseif ($empty && class_exists($emptyClass = $this->parseClass($module, $layer, $empty, $appendSuffix))) {
            return $this->make($emptyClass, true);
        }

        throw new ClassNotFoundException('class not exists:' . $class, $class);
    }

    /**
     * 解析应用类的类名
     * @access public
     * @param  string $module 模块名
     * @param  string $layer  层名 controller model ...
     * @param  string $name   类名
     * @param  bool   $appendSuffix
     * @return string
     */
    public function parseClass($module, $layer, $name, $appendSuffix = false)
    {
        $name  = str_replace(['/', '.'], '\\', $name);
        $array = explode('\\', $name);
        $class = Loader::parseName(array_pop($array), 1) . ($this->suffix || $appendSuffix ? ucfirst($layer) : '');
        $path  = $array ? implode('\\', $array) . '\\' : '';

        return $this->namespace . '\\' . ($module ? $module . '\\' : '') . $layer . '\\' . $path . $class;
    }

    /**
     * 是否为调试模式
     * @access public
     * @return bool
     */
    public function isDebug()
    {
        return $this->appDebug;
    }

    /**
     * 获取模块路径
     * @access public
     * @return string
     */
    public function getModulePath()
    {
        return $this->modulePath;
    }

    /**
     * 设置模块路径
     * @access public
     * @param  string $path 路径
     * @return void
     */
    public function setModulePath($path)
    {
        $this->modulePath = $path;
        $this->env->set('module_path', $path);
    }

    /**
     * 获取应用根目录
     * @access public
     * @return string
     */
    public function getRootPath()
    {
        return $this->rootPath;
    }

    /**
     * 获取应用类库目录
     * @access public
     * @return string
     */
    public function getAppPath()
    {
        if (is_null($this->appPath)) {
            $this->appPath = $this->getRootPath() . 'app' . DIRECTORY_SEPARATOR;
        }

        return $this->appPath;
    }

    /**
     * 获取应用运行时目录
     * @access public
     * @return string
     */
    public function getRuntimePath()
    {
        return $this->runtimePath;
    }

    /**
     * 获取核心框架目录
     * @access public
     * @return string
     */
    public function getThinkPath()
    {
        return $this->thinkPath;
    }

    /**
     * 获取路由目录
     * @access public
     * @return string
     */
    public function getRoutePath()
    {
        return $this->routePath;
    }

    /**
     * 获取应用配置目录
     * @access public
     * @return string
     */
    public function getConfigPath()
    {
        return $this->configPath;
    }

    /**
     * 获取配置后缀
     * @access public
     * @return string
     */
    public function getConfigExt()
    {
        return $this->configExt;
    }

    /**
     * 获取应用类库命名空间
     * @access public
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * 设置应用类库命名空间
     * @access public
     * @param  string $namespace 命名空间名称
     * @return $this
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * 是否启用类库后缀
     * @access public
     * @return bool
     */
    public function getSuffix()
    {
        return $this->suffix;
    }

    /**
     * 获取应用开启时间
     * @access public
     * @return float
     */
    public function getBeginTime()
    {
        return $this->beginTime;
    }

    /**
     * 获取应用初始内存占用
     * @access public
     * @return integer
     */
    public function getBeginMem()
    {
        return $this->beginMem;
    }

}
