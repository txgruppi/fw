<?php

class FW {

  const VERSION = '0-beta';

  public static $stop = false;
  public static $viewPath;
  protected static $routes = array();
  protected static $baseUrl;
  protected static $basePath;
  protected static $scriptUrl;
  protected static $matches = array();
  protected static $vars = array();
  
  /**
   * From F3 Framework
   */
  protected static $httpStatus = array(
    100 => 'Continue',
    101 => 'Switching Protocols',
    200 => 'OK',
    201 => 'Created',
    202 => 'Accepted',
    203 => 'Non-Authorative Information',
    204 => 'No Content',
    205 => 'Reset Content',
    206 => 'Partial Content',
    300 => 'Multiple Choices',
    301 => 'Moved Permanently',
    302 => 'Found',
    303 => 'See Other',
    304 => 'Not Modified',
    305 => 'Use Proxy',
    307 => 'Temporary Redirect',
    400 => 'Bad Request',
    401 => 'Unauthorized',
    402 => 'Payment Required',
    403 => 'Forbidden',
    404 => 'Not Found',
    405 => 'Method Not Allowed',
    406 => 'Not Acceptable',
    407 => 'Proxy Authentication Required',
    408 => 'Request Timeout',
    409 => 'Conflict',
    410 => 'Gone',
    411 => 'Length Required',
    412 => 'Precondition Failed',
    413 => 'Request Entity Too Large',
    414 => 'Request-URI Too Long',
    415 => 'Unsupported Media Type',
    416 => 'Requested Range Not Satisfiable',
    417 => 'Expectation Failed',
    500 => 'Internal Server Error',
    501 => 'Not Implemented',
    502 => 'Bad Gateway',
    503 => 'Service Unavailable',
    504 => 'Gateway Timeout',
    505 => 'HTTP Version Not Supported',
  );

  public static function run() {
    ob_start();
    self::$baseUrl = rtrim(dirname(self::getScriptUrl()));
    self::$basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__FILE__);
    self::$viewPath = defined('VIEW_PATH') ? VIEW_PATH : self::$basePath . '/views';
    $scriptUrl = self::getScriptUrl();
    $request = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '*';
    $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '*';
    foreach (explode('/', $scriptUrl) as $part) {
      if (empty($part))
        continue;
      $request = preg_replace('@^/' . $part . '@i', '', $request);
    }
    if (empty($request))
      $request = '/';
    else {
      $request = explode('?', $request, 2);
      $request = array_shift($request);
    }
    self::callRoute($method, $request);
  }

  public static function callRoute($method, $path, $callNotFound = true) {
    $pathArray = self::getPathArray($method);
    if (empty($pathArray) && $method == '*')
      return $callNotFound ? self::callHttpStatus('404') : null;
    $callbackArray = self::getCallbackArray($pathArray, $path);
    if (empty($callbackArray))
      return $callNotFound ? self::callHttpStatus('404') : null;
    self::runCallbackArray($callbackArray);
  }

  public static function getPathArray($method) {
    $array = array();
    if (isset(self::$routes[$method]))
      $array = array_merge($array, self::$routes[$method]);
    if (isset(self::$routes['*']))
      $array = array_merge($array, self::$routes['*']);
    return $array;
  }

  public static function getCallbackArray($pathArray, $path) {
    $catchAll = null;
    foreach ($pathArray as $pattern => $callbackArray) {
      if ($pattern != '*' && preg_match('@^' . $pattern . '$@i', $path, self::$matches))
        return $callbackArray;
      if ($pattern == '*' && $catchAll === null)
        $catchAll = $callbackArray;
    }
    return $catchAll;
  }

  public static function callHttpStatus($status) {
    $pathArray = self::getPathArray('HTTP_STATUS');
    if (!empty($pathArray)) {
      $callbackArray = self::getCallbackArray($pathArray, $status);
      if (!empty($callbackArray)) {
        return self::runCallbackArray($callbackArray);
      }
    }
    $statusText = isset(self::$httpStatus[$status]) ? self::$httpStatus[$status] : null;
    if (!headers_sent())
      header("HTTP/1.0 $status $statusText");
    throw new Exception('Error ' . $status . (empty($statusText) ? null : '<br/>' . $statusText));
  }

  public static function runCallbackArray($callbackArray) {
    foreach ($callbackArray as $callback) {
      if (self::$stop)
        break;
      if (method_exists($callback['object'], 'beforeAction'))
        call_user_func(array($callback['object'], 'beforeAction'), self::$matches, $callback);
      if (self::$stop)
        break;
      $callback['method']->invoke($callback['object'], self::$matches, $callback);
    }
  }

  /**
   * From Yii Framework
   */
  public static function getScriptUrl() {
    if (self::$scriptUrl == null) {
      $scriptName=basename($_SERVER['SCRIPT_FILENAME']);
      if(basename($_SERVER['SCRIPT_NAME'])===$scriptName)
        self::$scriptUrl=$_SERVER['SCRIPT_NAME'];
      else if(basename($_SERVER['PHP_SELF'])===$scriptName)
        self::$scriptUrl=$_SERVER['PHP_SELF'];
      else if(isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME'])===$scriptName)
        self::$scriptUrl=$_SERVER['ORIG_SCRIPT_NAME'];
      else if(($pos=strpos($_SERVER['PHP_SELF'],'/'.$scriptName))!==false)
        self::$scriptUrl=substr($_SERVER['SCRIPT_NAME'],0,$pos).'/'.$scriptName;
      else if(isset($_SERVER['DOCUMENT_ROOT']) && strpos($_SERVER['SCRIPT_FILENAME'],$_SERVER['DOCUMENT_ROOT'])===0)
        self::$scriptUrl=str_replace('\\','/',str_replace($_SERVER['DOCUMENT_ROOT'],'',$_SERVER['SCRIPT_FILENAME']));
      else
        throw new Exception('FW is unable to determine the entry script URL.');
    }
    return self::$scriptUrl;
  }

  public static function baseUrl() {
    return self::$baseUrl;
  }

  public static function basePath() {
    return self::$basePath;
  }

  public static function getRoutes() {
    return self::$routes;
  }

  public static function getMatches() {
    return self::$matches;
  }

  public static function add($object) {
    if (is_string($object))
      $object = new $object;
    $reflection = new ReflectionObject($object);
    foreach ($reflection->getMethods() as $method)
      self::checkMethod($object, $method);
  }

  public function checkMethod($object, $method) {
    $comments = $method->getDocComment();
    if (empty($comments))
      return;
    $comments = explode("\n", $comments);
    foreach ($comments as $comment) {
      $comment = trim($comment);
      $matches = array();
      if (preg_match('/^\*\s+@route\s+([a-z_]+|\*)\s+(.+)$/i', $comment, $matches)) {
        $httpMethod = $matches[1];
        $path = $matches[2];
        self::addRoute($httpMethod, $path, $object, $method);
      }
    }
  }

  public static function addRoute($method, $path, $object, ReflectionFunctionAbstract $reflection) {
    if (!isset(self::$routes[$method]))
      self::$routes[$method] = array();
    if (!isset(self::$routes[$method][$path]))
      self::$routes[$method][$path] = array();
    self::$routes[$method][$path][] = array(
      'method' => $reflection,
      'object' => $object,
      'class' => get_class($object),
      'name' => $reflection->getName(),
    );
  }

  public static function render($layout, $view, $vars = array()) {
    if (file_exists(self::$viewPath  . '/' . $view . '.php')) {
      $content = self::renderFile(self::$viewPath . '/' . $view . '.php', $vars);
      if (empty($layout))
        return $content;
      elseif (file_exists(self::$viewPath . '/' . $layout . '.php')) {
        return self::renderFile(self::$viewPath . '/' . $layout . '.php', array('content' => $content));
      } else
        throw new Exception("Layout '$layout' not found in '" . self::$viewPath . "'.");
    } else
      throw new Exception("View '$view' not found in '" . self::$viewPath . "'.");
  }

  public static function renderFile($path, $vars = array()) {
    ob_start();
    extract($vars);
    require $path;
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
  }
  
  public static function getIndex($arr, $index, $default = null) {
    return isset($arr[$index]) ? $arr[$index] : $default;
  }

  public static function param($name, $default = null) {
    return self::getIndex($_REQUEST, $name, $default);
  }
  
  public static function set($name, $value) {
    self::$vars[$name] = $value;
  }
  
  public static function get($name, $default = null) {
    return self::getIndex(self::$vars, $name, $default);
  }

}
