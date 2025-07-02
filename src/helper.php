<?php

// 框架助手函数
use think\Container;
use think\Db;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Cookie;
use think\facade\Debug;
use think\facade\Request;
use think\facade\Session;
use think\facade\Url;
use think\Response;

/**
 * 抛出HTTP异常
 * @param integer|Response      $code 状态码 或者 Response对象实例
 * @param string                $message 错误信息
 * @param array                 $header 参数
 */
function abort($code, $message = null, $header = [])
{
    if ( $code instanceof Response ) {
        throw new HttpResponseException($code);
    } else {
        throw new HttpException($code, $message, null, $header);
    }
}

/**
 * 快速获取容器中的实例 支持依赖注入
 * @param string    $name 类名或标识 默认获取当前应用实例
 * @param array     $args 参数
 * @param bool      $newInstance    是否每次创建新的实例
 * @return mixed|\think\App
 */
function app($name = 'think\App', $args = [], $newInstance = false)
{
    return Container::get($name, $args, $newInstance);
}

/**
 * 缓存管理
 * @param mixed     $name 缓存名称，如果为数组表示进行缓存设置
 * @param mixed     $value 缓存值
 * @param mixed     $options 缓存参数
 * @param string    $tag 缓存标签
 * @return mixed
 */
function cache($name, $value = '', $options = null, $tag = null)
{
    if ( is_array($options) ) {
        // 缓存操作的同时初始化
        Cache::connect($options);
    } elseif ( is_array($name) ) {
        // 缓存初始化
        return Cache::connect($name);
    }
    if ( '' === $value ) {
        // 获取缓存
        return 0 === strpos($name, '?') ? Cache::has(substr($name, 1)) : Cache::get($name);
    } elseif ( is_null($value) ) {
        // 删除缓存
        return Cache::rm($name);
    }
    // 添加缓存
    if ( is_array($options) ) {
        $expire = isset($options['expire']) ? $options['expire'] : null; //修复查询缓存无法设置过期时间
    } else {
        $expire = is_numeric($options) ? $options : null; //默认快捷缓存设置过期时间
    }
    if ( is_null($tag) ) {
        return Cache::set($name, $value, $expire);
    } else {
        return Cache::tag($tag)->set($name, $value, $expire);
    }
}

/**
 * 获取和设置配置参数
 * @param string|array  $name 参数名
 * @param mixed         $value 参数值
 * @return mixed
 */
function config($name = '', $value = null)
{
    if ( is_array($name) ) {
        return Config::set($name, $value);
    }
    return 0 === strpos($name, '?') ? Config::has(substr($name, 1)) : Config::get($name, $value);
}

/**
 * 获取容器对象实例
 * @return Container
 */
function container()
{
    return Container::getInstance();
}

/**
 * Cookie管理
 * @param string|array  $name cookie名称，如果为数组表示进行cookie设置
 * @param mixed         $value cookie值
 * @param mixed         $option 参数
 * @return mixed
 */
function cookie($name, $value = '', $option = null)
{
    if ( is_array($name) ) {
        // 初始化
        Cookie::init($name);
    } elseif ( $value === '' ) {
        // 获取
        return 0 === strpos($name, '?') ? Cookie::has(substr($name, 1), $option) : Cookie::get($name);
    } elseif ( is_null($value) ) {
        // 删除
        return Cookie::delete($name);
    } else {
        // 设置
        return Cookie::set($name, $value, $option);
    }
}

/**
 * Session管理
 * @param string|array  $name session名称，如果为数组表示进行session设置
 * @param mixed         $value session值
 * @param string        $prefix 前缀
 * @return mixed
 */
function session($name, $value = '', $prefix = null)
{
    if ( is_array($name) ) {
        // 初始化
        Session::init($name);
    } elseif ( is_null($name) ) {
        // 清除
        Session::clear($value);
    } elseif ( '' === $value ) {
        // 判断或获取
        return 0 === strpos($name, '?') ? Session::has(substr($name, 1), $prefix) : Session::get($name, $prefix);
    } elseif ( is_null($value) ) {
        // 删除
        return Session::delete($name, $prefix);
    } else {
        // 设置
        return Session::set($name, $value, $prefix);
    }
}

/**
 * 实例化数据库类
 * @param string        $name 操作的数据表名称（不含前缀）
 * @param array|string  $config 数据库配置参数
 * @param bool          $force 是否强制重新连接
 * @return \think\db\Query
 */
function db($name = '', $config = [], $force = true)
{
    return Db::connect(!empty($config) ? : config('database'), $force)->name($name);
}

/**
 * 记录时间（微秒）和内存使用情况
 * @param string            $start 开始标签
 * @param string            $end 结束标签
 * @param integer|string    $dec 小数位 如果是m 表示统计内存占用
 * @return mixed
 */
function debug($start, $end = '', $dec = 6)
{
    if ( '' == $end ) {
        Debug::remark($start);
    } else {
        return 'm' == $dec ? Debug::getRangeMem($start, $end) : Debug::getRangeTime($start, $end, $dec);
    }
}

/**
 * 计算磁盘占用空间
 * @param  integer  $size   大小
 * @param  integer  $dec    小数位
 * @return string
 */
function disk_usage($size, $dec = 2)
{
    $a    = ['B', 'KB', 'MB', 'GB', 'TB'];
    $pos  = 0;
    while ($size >= 1024) {
        $size /= 1024;
        $pos++;
    }
    return round($size, $dec) . " " . $a[$pos];
}

/**
 * 浏览器友好的变量输出
 * @param mixed     $var 变量
 * @param boolean   $echo 是否输出 默认为true 如果为false 则返回输出字符串
 * @param string    $label 标签 默认为空
 * @return void|string
 */
function dump($var, $echo = true, $label = null)
{
    return Debug::dump($var, $echo, $label);
}

/**
 * 获取当前Request对象实例
 * @return Request
 */
function request()
{
    return app('request');
}

/**
 * 创建普通 Response 对象实例
 * @param mixed      $data   输出数据
 * @param int|string $code   状态码
 * @param array      $header 头信息
 * @param string     $type
 * @return Response
 */
function response($data = '', $code = 200, $header = [], $type = 'html')
{
    return Response::create($data, $type, $code, $header);
}

/**
 * 获取\think\response\Download对象实例
 * @param string  $filename 要下载的文件
 * @param string  $name 显示文件名
 * @param bool    $content 是否为内容
 * @param integer $expire 有效期（秒）
 * @return \think\response\Download
 */
function download($filename, $name = '', $content = false, $expire = 360, $openinBrowser = false)
{
    return Response::create($filename, 'download')->name($name)->isContent($content)->expire($expire)->openinBrowser($openinBrowser);
}

/**
 * 获取\think\response\Json对象实例
 * @param mixed   $data 返回的数据
 * @param integer $code 状态码
 * @param array   $header 头部
 * @param array   $options 参数
 * @return \think\response\Json
 */
function json($data = [], $code = 200, $header = [], $options = [])
{
    return Response::create($data, 'json', $code, $header, $options);
}

/**
 * 获取\think\response\Jsonp对象实例
 * @param mixed   $data    返回的数据
 * @param integer $code    状态码
 * @param array   $header 头部
 * @param array   $options 参数
 * @return \think\response\Jsonp
 */
function jsonp($data = [], $code = 200, $header = [], $options = [])
{
    return Response::create($data, 'jsonp', $code, $header, $options);
}

/**
 * 获取\think\response\Redirect对象实例
 * @param mixed         $url 重定向地址 支持Url::build方法的地址
 * @param array|integer $params 额外参数
 * @param integer       $code 状态码
 * @return \think\response\Redirect
 */
function redirect($url = [], $params = [], $code = 302)
{
    if ( is_integer($params) ) {
        $code   = $params;
        $params = [];
    }
    return Response::create($url, 'redirect', $code)->params($params);
}

/**
 * 获取\think\response\Xml对象实例
 * @param mixed   $data    返回的数据
 * @param integer $code    状态码
 * @param array   $header  头部
 * @param array   $options 参数
 * @return \think\response\Xml
 */
function xml($data = [], $code = 200, $header = [], $options = [])
{
    return Response::create($data, 'xml', $code, $header, $options);
}

/**
 * 实例化Model
 * @param string    $name Model名称
 * @param string    $layer 业务层名称
 * @param bool      $appendSuffix 是否添加类名后缀
 * @return \think\Model
 */
function model($name = '', $layer = 'model', $appendSuffix = false)
{
    return app()->model($name, $layer, $appendSuffix);
}

/**
 * 生成表单令牌
 * @param string $name 令牌名称
 * @param mixed  $type 令牌生成方法
 * @return string
 */
function token($name = '__token__', $type = 'md5')
{
    $token = Request::token($name, $type);
    return '<input type="hidden" name="' . $name . '" value="' . $token . '" />';
}

/**
 * Url生成
 * @param string        $url 路由地址
 * @param string|array  $vars 变量
 * @param bool|string   $suffix 生成的URL后缀
 * @param bool|string   $domain 域名
 * @return string
 */
function url($url = '', $vars = '', $suffix = true, $domain = false)
{
    return Url::build($url, $vars, $suffix, $domain);
}

/**
 * 渲染输出Widget
 * @param string    $name Widget名称
 * @param array     $data 传入的参数
 * @return mixed
 */
function widget($name, $data = [])
{
    $result = app()->action($name, $data, 'widget');
    if ( is_object($result) ) {
        $result = $result->getContent();
    }
    return $result;
}
