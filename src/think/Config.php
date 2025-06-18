<?php

namespace think;

// 配置管理类
class Config
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    /**
     * 配置文件目录
     * @var string
     */
    protected $path;

    /**
     * 配置文件后缀
     * @var string
     */
    protected $ext;

    /**
     * 构造方法
     * @access public
     */
    public function __construct($path = '', $ext = '.php')
    {
        $this->path = $path;
        $this->ext  = $ext;
    }

    public static function __make(App $app)
    {
        $path = $app->getConfigPath();
        $ext  = $app->getConfigExt();
        return new static($path, $ext);
    }

    /**
     * 加载配置文件（多种格式）
     * @access public
     * @param  string $file 配置文件名
     * @param  string $name 一级配置名
     * @return mixed
     */
    public function load($file, $name = '')
    {
        if ( is_file($file) ) {
            $filename = $file;
        } elseif ( is_file($this->path . $file . $this->ext) ) {
            $filename = $this->path . $file . $this->ext;
        }
        if ( isset($filename) ) {
            return $this->parse($filename, $name);
        }
        return $this->config;
    }

    /**
     * 解析配置文件
     * @access public
     * @param  string $file 配置文件名
     * @param  string $name 一级配置名
     * @return array
     */
    public function parse($file = '', $name = '')
    {
        $config = include $file;
        return is_array($config) ? $this->set($config, strtolower($name)) : [];
    }

    /**
     * 检测配置是否存在
     * @access public
     * @param  string    $name 配置参数名（支持多级配置 .号分割）
     * @return bool
     */
    public function has($name)
    {
        if ( false === strpos($name, '.') && !isset($this->config[strtolower($name)]) ) {
            return false;
        }
        return !is_null($this->get($name));
    }

    /**
     * 获取一级配置
     * @access public
     * @param  string    $name 一级配置名
     * @return array
     */
    public function pull($name)
    {
        $name = strtolower($name);
        return $this->config[$name] ?? [];
    }

    /**
     * 获取配置参数 为空则获取所有配置
     * @access public
     * @param  string    $name      配置参数名（支持多级配置 .号分割）
     * @param  mixed     $default   默认值
     * @return mixed
     */
    public function get($name = null, $default = null)
    {
        // 无参数时获取所有
        if ( empty($name) ) {
            return $this->config;
        }
        // 获取一级配置的所有
        if ( false === strpos($name, '.') ) {
            return $this->pull($name);
        }
        $name    = explode('.', $name);
        $name[0] = strtolower($name[0]);
        $config  = $this->config;
        // 按.拆分成多维数组进行判断
        foreach ( $name as $val ) {
            if ( isset($config[$val]) ) {
                $config = $config[$val];
            } else {
                return $default;
            }
        }
        return $config;
    }

    /**
     * 设置配置参数 name为数组则为批量设置
     * @access public
     * @param  array  $config 配置参数
     * @param  string $name 配置名
     * @return array
     */
    public function set($config, $name = null)
    {
        if ( !empty($name) ) {
            if ( isset($this->config[$name]) ) {
                $result = array_merge($this->config[$name], $config);
            } else {
                $result = $config;
            }
            $this->config[$name] = $result;
        } else {
            $result = $this->config = array_merge($this->config, array_change_key_case($config));
        }
        return $result;
    }

}