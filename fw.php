<?php

// # FW: PHP Micro Framework
//
// FW is a Micro PHP Framework for simple applications, heavily inspired by [PHP Fat-Free Framework][f3-home],
// basically a URL router and a view renderer
//
// FW reads the annotation attribute `@route` and routes each url to its action
//
// The route parts are:
//
// Method
// :   The HTTP method for the route
// :   May be `'*'` to accept any HTTP method
//
// Route
// :   The route regex
// :   Any PHP valid regex to be used within `preg_match`
// :   The matches are accessible through the method `FW::getMatches()`
//
// See the [sample app][sample-url] for detailed usage
//
// Project source code at [GitHub][project-url]
//
// @author Tarcísio Gruppi <txgruppi@gmail.com>
// @contributor Tadeu Zagallo <tadeuzagallo@gmail.com>
//
// [f3-home]: http://bcosca.github.com/fatfree/
// [yii-home]: http://www.yiiframework.com/
// [sample-url]: https://github.com/TXGruppi/fw/tree/master/sample
// [project-url]: https://github.com/TXGruppi/fw
class FW {

  const VERSION = '0.7.0';

  public static $viewPath;
  protected static $routes = array();
  protected static $baseUrl;
  protected static $basePath;
  protected static $scriptUrl;
  protected static $requestUri;
  protected static $matches = array();
  protected static $vars = array();

  // Copied from F3 Framework
  // @see [PHP Fat-Free Framework][f3-home]
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

  // Run the app
  // Look for the constants:
  //
  // BASE_PATH
  // :   The application base path
  // :   If not set will use the directory of this file
  //
  // VIEW_PATH
  // :   The application views path
  // :   If not set will use the base path + /views
  public static function run() {
    ob_start();
    self::$baseUrl = rtrim(dirname(self::getScriptUrl()));
    self::$baseUrl = preg_replace('/\/$/', '', self::$baseUrl);

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

  // Call a specific route
  //
  // @param `string $method` any HTTP method or `'*'`
  // @param `string $path` the route path
  // @param `bool $callNotFound` call 404 if can't find route
  public static function callRoute($method, $path, $callNotFound = true) {
    $pathArray = self::getPathArray($method);
    if (empty($pathArray) && $method == '*')
      return $callNotFound ? self::callHttpStatus('404') : null;
    $callbackArray = self::getCallbackArray($pathArray, $path);
    if (empty($callbackArray))
      return $callNotFound ? self::callHttpStatus('404') : null;
    self::runCallbackArray($callbackArray);
  }

  // Get all paths for a specific method
  // @param `string $method` any HTTP method or `'*'`
  // @return `array` the array of paths
  public static function getPathArray($method, $includeStar = true) {
    $array = array();
    if (isset(self::$routes[$method]))
      $array = array_merge($array, self::$routes[$method]);
    if ($includeStar && isset(self::$routes['*']))
      $array = array_merge($array, self::$routes['*']);
    return $array;
  }

  // Get all valid callbacks for a specific path
  // @param `array $pathArray` array of paths
  // @param `string $path` the path regex to validate or `'*'` to return all callbacks
  // @return `array` the callback array valid for the specified path
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

  // Call the actions for a HTTP status code
  // @param `int $tatus` the status code
  // @throws `Exception` if no action is found for the status received
  public static function callHttpStatus($status) {
    $pathArray = self::getPathArray('HTTP_STATUS', false);
    if (!empty($pathArray)) {
      $callbackArray = self::getCallbackArray($pathArray, $status);
      if (!empty($callbackArray)) {
        return self::runCallbackArray($callbackArray);
      }
    }
    self::failWith($status);
  }

  // Run all callbacks in an array
  // Stop the callback chain if a callback returns `false`
  // Call the method `beforeAction` if it exists
  // @param `array $callbackArray` the callback array
  public static function runCallbackArray($callbackArray) {
    foreach ($callbackArray as $callback) {
      if (method_exists($callback['object'], 'beforeAction')) {
        if (false === call_user_func(array($callback['object'], 'beforeAction'), self::$matches, $callback)) {
          return;
        }
      }
      if (false === $callback['method']->invoke($callback['object'], self::$matches, $callback)) {
        return;
      }
      if (method_exists($callback['object'], 'afterAction')) {
        if (false === call_user_func(array($callback['object'], 'afterAction'), self::$matches, $callback)) {
          return;
        }
      }
    }
  }

  // Copied from Yii Framework
  // @see [Yii Framework][yii-home]
  public static function getRequestUri() {
    if(self::$requestUri === null) {
      if(isset($_SERVER['HTTP_X_REWRITE_URL'])) {
        self::$requestUri = $_SERVER['HTTP_X_REWRITE_URL'];
      } elseif (isset($_SERVER['REQUEST_URI'])) {
        self::$requestUri = $_SERVER['REQUEST_URI'];
        if(!empty($_SERVER['HTTP_HOST'])) {
          if(strpos(self::$requestUri,$_SERVER['HTTP_HOST']) !== false)
            self::$requestUri = preg_replace('/^\w+:\/\/[^\/]+/','',self::$requestUri);
        } else {
          self::$requestUri = preg_replace('/^(http|https):\/\/[^\/]+/i','',self::$requestUri);
        }
      } elseif (isset($_SERVER['ORIG_PATH_INFO'])) {
        self::$requestUri = $_SERVER['ORIG_PATH_INFO'];
        if(!empty($_SERVER['QUERY_STRING'])) {
          self::$requestUri .= '?'.$_SERVER['QUERY_STRING'];
        }
      } else {
        throw new Exception('FW is unable to determine the request URI.');
      }
    }

    return self::$requestUri;
  }

  // Copied from Yii Framework
  // @see [Yii Framework][yii-home]
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

  // Return the base URL of the app
  // @return `string` the base url
  public static function baseUrl() {
    return self::$baseUrl;
  }

  // Return the base path of the app
  // @return `string` the base path
  public static function basePath() {
    return self::$basePath;
  }

  // Get all known routes
  // @return `array` the array of routes
  public static function getRoutes() {
    return self::$routes;
  }

  // Return all matches for the current route
  // @return `array` the matches array
  public static function getMatches() {
    return self::$matches;
  }

  // Add a controller object and search each method for a route
  // @param `object $object`
  public static function add($object) {
    if (is_string($object))
      $object = new $object;
    $reflection = new ReflectionObject($object);
    foreach ($reflection->getMethods() as $method) {
      self::checkMethod($object, $method);
    }
  }

  // Check the method for a route
  // Each method can have more than one route and each route can have more than one method
  // A route is defined by the attribute `@route` and a regular expression
  // @param `object $object` the controller object
  // @param `ReflectionMethod $method` the method to look
  public static function checkMethod($object, $method) {
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

  // Add a route to the list of routes
  // @param `string $method` the http method or `'*'` for all methods
  // @param `string $path` the url path or `'*'` for a catch-all method
  // @param `object $object` the controller object
  // @param `ReflectionFunctionAbstract $reflection` the reflection of the action
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

  // Render a view with a layout and pass values to the view
  // The layout will have a variable named `$content` with the view content
  // @param `string $layout` the relative path of the layout file without `.php` or null to render withour layout
  // @param `string $view` the relative path of the view file without `.php`
  // @param `array vars` a associative array where the keys will be variables in the view
  // @return `strign` the contents of the rendered file
  // @throws `Exception` if cant find the view or layout file
  public static function render($layout, $view, $vars = array()) {
    if (file_exists(self::$viewPath  . '/' . $view . '.php')) {
      $content = self::renderFile(self::$viewPath . '/' . $view . '.php', $vars);
      if (empty($layout))
        return $content;
      elseif (file_exists(self::$viewPath . '/' . $layout . '.php')) {
        return self::renderFile(self::$viewPath . '/' . $layout . '.php', array_merge($vars, array('content' => $content)));
      } else
        throw new Exception("Layout '$layout' not found in '" . self::$viewPath . "'.");
    } else
      throw new Exception("View '$view' not found in '" . self::$viewPath . "'.");
  }

  // Render a file
  // @param `string $path` the file path
  // @param `array vars` a associative array where the keys will be variables in the view
  // @return `strign` the contents of the rendered file
  public static function renderFile($path, $vars = array()) {
    ob_start();
    extract($vars);
    require $path;
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
  }

  // Get and index of an array or `$default`
  // @param `array $arr` the array
  // @param `mixed $index` the index
  // @param `mixed $default` the default value if the index doesn't exists
  public static function getIndex($arr, $index, $default = null) {
    return isset($arr[$index]) ? $arr[$index] : $default;
  }

  // Get a index from `$_REQUEST`
  // @param `mixed $name` the index
  // @param `mixed $default` the default value if the index doesn't exists
  public static function param($name, $default = null) {
    return self::getIndex($_REQUEST, $name, $default);
  }

  // Set a value for the `$vars` array
  // It can be accessed from any file using the `FW` class
  // @param `mixed $name` the index for the array
  // @param `mixed $value` the value
  public static function set($name, $value) {
    self::$vars[$name] = $value;
  }

  // Get a value from the `$vars` array or default if the index doesn't exists
  // It can be accessed from any file using the `FW` class
  // @param `mixed name` the index for the array
  // @param `mixed $default` the default value if the index doesn't exists
  public static function get($name, $default = null) {
    return self::getIndex(self::$vars, $name, $default);
  }

  public static function sessionStart() {
    if (!headers_sent()) {
      $session_id = session_id();
      if (empty($session_id))
        session_start();
      $session_id = session_id();
      return !empty($session_id);
    }
    return false;
  }

  // Set a flash value
  // Push $data to a array with $key as index
  // @param `mixed $key` any valid value for an array key
  // @param `mixed $data` any serializable value to be stored
  // @return `boolean` true if the data was set, false otherwise
  public static function setFlash($key, $data) {
    if (FW::sessionStart()) {
      if (!isset($_SESSION['FW_FLASH']))
        $_SESSION['FW_FLASH'] = array();

      if (!isset($_SESSION['FW_FLASH'][$key]))
        $_SESSION['FW_FLASH'][$key] = array();

      $_SESSION['FW_FLASH'][$key][] = $data;
      return true;
    }

    return false;
  }

  // Get a flash value
  // Get all values stored for a key in the flash array
  // @param `mixed $key` any valid value for an array key
  // @return `array` return an array with the flash data or an empty array if $key is not set
  public static function getFlash($key) {
    FW::sessionStart();

    if (isset($_SESSION['FW_FLASH']) && isset($_SESSION['FW_FLASH'][$key])) {
      $data = $_SESSION['FW_FLASH'][$key];
      if (count($_SESSION['FW_FLASH']) == 1) {
        unset($_SESSION['FW_FLASH']);
      } else {
        unset($_SESSION['FW_FLASH'][$key]);
      }
      return $data;
    }

    return array();
  }

  // Check if a flash key exists
  // @param `mixed $key` any valid value for an array key
  // @return `boolean` return true if the key exists, false otherwise
  public static function hasFlash($key) {
    FW::sessionStart();

    return isset($_SESSION['FW_FLASH']) && isset($_SESSION['FW_FLASH'][$key]);
  }

  // Set an array of values for a flash key
  // @param `mixed $key` any valid value for an array key
  // @param `array $values` an array of values to be added
  // @return `boolean` true if all data was set, false otherwise
  public static function setEachFlash($key, $values) {
    $result = true;
    foreach ($values as $value) {
      if (!FW::setFlash($key, $value)) {
        $result = false;
      }
    }
    return $result;
  }

  // Refresh the client's browser
  // Send a Location header with the url from current request
  // @param `string $anchor` value to append to the refresh URL
  // @param `integer $statusCode` HTTP status code
  // @return `boolean` true if refresh is set, false otherwise
  public static function refresh($anchor = '', $statusCode = 302) {
    return FW::redirect(self::getRequestUri() . $anchor, $statusCode);
  }

  // Refresh the client's browser
  // Send a Location header with a url
  // @param `string $url` URL to redirect
  // @param `integer $statusCode` HTTP status code
  // @return `boolean` true if redirect is set, false otherwise
  public static function redirect($url, $statusCode = 302) {
    if (!headers_sent()) {
      header('Location: ' . $url, true, $statusCode);
      return true;
    }
    return false;
  }

  // Set the status code in the response header and throw an exception
  // @param `int $tatus` the status code
  // @throws `Exception` if no action is found for the status received
  public static function failWith($status) {
    $statusText = isset(self::$httpStatus[$status]) ? self::$httpStatus[$status] : "Unknown status ($status)";
    if (!headers_sent()) {
      header("HTTP/1.0 $status $statusText");
    }
    throw new Exception("Error $status $statusText");
  }

}
