<?php
/*
 * Copyright 2012, Jason Kinner
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
class Dogma {
  public static $dsn = null;
  public static $templates_dir = '../templates';
  public static $mode = 'DEV';
  public static $modules_dir = '../modules';
  public static $http_proxy = null;

  private static $search_path = null;
  private static $module_dirs = null;

  public static function autoload($className) {
    $className = str_replace('.', '_', $className);
    $className = str_replace(DIRECTORY_SEPARATOR, '_', $className);
    $className = str_replace('/', '_', $className);
    if ( class_exists($className, false) || interface_exists($className, false) ) {
      return false;
    }

    $classFile = dirname(__FILE__).DIRECTORY_SEPARATOR.$className.'.php';
    if ( file_exists($classFile) ) {
      require $classFile;
      return true;
    }

    // Search modules directories
    foreach ( self::_get_modules_dirs() as $module_dir ) {
      $classFile = $module_dir.DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.$className.'.php';
      if ( file_exists($classFile) ) {
        require $classFile;
        return true;
      }
    }

    // Search templates directories
    $lang_class_path = self::_build_search_path($className.'.php', "web", self::detect_language());
    foreach ( self::_build_search_path($className.'.php', "web", self::detect_language(), true) as $candidate ) {
      if ( file_exists($candidate) ) {
        require $candidate;
        return true;
      }
    }

    return false;
  }

  private static function _get_modules_dirs() {
    if ( ! isset(self::$module_dirs) ) {
      self::$module_dirs = array();
      $real_modules_dir = realpath(self::$modules_dir);
      $dir_h = opendir($real_modules_dir);
      if ( $dir_h ) {
        while ( $file = readdir($dir_h) ) {
          if ( $file == '.' || $file == '..' ) {
            continue;
          }
          	
          if ( is_dir($real_modules_dir.DIRECTORY_SEPARATOR.$file) ) {
            // A module definition
            self::$module_dirs[] = $real_modules_dir.DIRECTORY_SEPARATOR.$file;
          }
        }
      }
      closedir($dir_h);
    }

    return self::$module_dirs;
  }

  private static function _build_search_path($template, $medium, $lang, $include_modules = true) {
    if ( isset(self::$search_path[$template.'&'.$medium.'&'.$lang]) ) {
      return self::$search_path[$template.'&'.$medium.'&'.$lang];
    }

    $resolved_search_path = array();

    /*
     * Directory search is as follows. First found, wins:
    * 1) templates/$medium/$lang/$dialect/$template (if dialect specified)
    * 2) templates/$medium/$lang/$template
    * 3) templates/$lang/$dialect/$template (if dialect specified)
    * 4) templates/$lang/$template
    * 5) templates/$medium/$template
    * 6) templates/$template
    */
    $lang_parts = split('[_-]', $lang);
    $search_path = array();
    if ( count($lang_parts) > 1 ) {
      $search_path = array(
              "/$medium/{$lang_parts[0]}/{$lang_parts[1]}/$template",
              "/$medium/{$lang_parts[0]}/$template",
              "/{$lang_parts[0]}/{$lang_parts[1]}/$template",
              "/{$lang_parts[0]}/$template",
              "/$medium/$template",
              "/$template",
      );
    } else {
      $search_path = array(
              "/$medium/$lang/$template",
              "/$lang/$template",
              "/$medium/$template",
              "/$template",
      );
    }

    $all_dirs = array(self::$templates_dir);

    if ( $include_modules ) {
      foreach ( self::_get_modules_dirs() as $module_dir ) {
        $all_dirs[] = $module_dir.DIRECTORY_SEPARATOR.'templates';
      }
    }

    foreach ( $all_dirs as $dir ) {
      foreach ( $search_path as $search_dir ) {
        if ( file_exists($dir.$search_dir) ) {
          $resolved_search_path[] = $dir.$search_dir;
        }
      }
    }

    if ($template) {
      self::$search_path[$template.'&'.$medium.'&'.$lang] = $resolved_search_path;
    }
    	
    return $resolved_search_path;
  }

  public static function load_messages($context, $lang = NULL, $medium = 'web') {
    if ($lang === NULL) {
      $lang = Dogma::detect_language();
    }

    if ( ! class_exists('Messages') ) {
      $search_path = self::_build_search_path('messages.php', $medium, $lang, false);
      	
      foreach ( $search_path as $template_file ) {
        if ( file_exists($template_file) ) {
          if ( ! self::_load_file($context, $template_file) ) {
            error_log("Warning: Message bundle for $lang on $medium failed during loading. Skipping.");
          } else {
            return true;
          }
        }
      }
    }
  }

  public static function require_https() {
    if ( ! isset($_SERVER['HTTPS']) || ! $_SERVER['HTTPS'] ) {
      $httpsurl= 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
      if (self::$mode != 'DEV' ) {
        self::redirect($httpsurl);
      } else {
        error_log("Would have redirected to $httpsurl, but I am in DEV mode");
      }
    }
  }

  public static function render($template, $context = array(), $lang = NULL, $medium = 'web') {
    if ($lang === NULL) {
      $lang = Dogma::detect_language();
    }

    $search_path = self::_build_search_path($template, $medium, $lang);
    foreach ( $search_path as $template_file ) {
      if ( file_exists($template_file) ) {
        if ( ! self::_render_file($context, $template_file) ) {
          error_log("Warning: $template_file failed during rendering. Skipping.");
        } else {
          return true;
        }
      }
    }

    return false;
  }

  private static function _load_file($context, $file) {
    extract($context, EXTR_SKIP);
    ob_start();
    $result = @include($file);
    $log = ob_end_clean();
    error_log($log);
    return $result;
  }

  private static function _render_file($context, $file) {
    extract($context, EXTR_SKIP);
    try {
      return include($file);
    } catch ( Exception $e ) {
      error_log($e->getMessage());
    }
  }

  public static function redirect($url, $terminate = true) {
    error_log("Redirecting to '$url'");
    $final_url = $url;
    if ( ! preg_match(',^https?://[^/]*/,', $url) ) {
      // Relative redirect. Build absolute part.
      error_log("Fixing relative URL '$url'");
      $http = 'http';
      if ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ) {
        $http = 'https';
      }
      	
      // This is a page-relative URL
      if ( $url[0] != '/' ) {
        $qidx = strpos($_SERVER['REQUEST_URI'], '?');
        if ( $qidx > 0 ) {
          $url = substr($_SERVER['REQUEST_URI'], 0, $qidx).'/'.$url;
        } else {
          $url = $_SERVER['REQUEST_URI'].'/'.$url;
        }
      }
      	
      $final_url = "$http://{$_SERVER['HTTP_HOST']}".(($url[0] == '/')?'':'/').$url;
    }
    error_log("Redirecting to absolute URL '$final_url'");

    header("Location: $final_url", true, 302);
    if ( $terminate ) {
      exit(0);
    }
  }

  public static function require_login() {
    if ( ! self::is_loggedin() ) {
      self::redirect('login?next='.urlencode($_SERVER['REQUEST_URI']));
    }
  }

  public static function is_loggedin() {
    return isset($_SESSION['user_id']);
  }

  public static function detect_language() {
    if (isset($_REQUEST['lang'])) {
      return $_REQUEST['lang'];
    }
    // TODO(jkinner): Do this honoring q=; for now it just takes the first in the list
    $language_spec = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
    $available_languages = $language_spec[0];
    $quality = 1.0;
    if (isset($available_languages[1])) {
      $quality = $available_languages[1];
    }
    $all_languages = split(",", $available_languages);
    foreach ($all_languages as $language) {
      // 2 are the defaults (no language part at all)
      $search_path = Dogma::_build_search_path("", "web", $language);
      if (count($search_path) > 1 + count(split("[-_]", $language))) {
        return $language;
      }
    }
  }

  public static function get_db_parts() {
    $parts = array();
    $db_type = 'mysql';
    $db_server = 'localhost';
    $db_port = 3306;
    $db_username = '';
    $db_password = '';
    $db_name = '';

    if ( preg_match(',([^:]*)://([^/@:]*)(:([^/@]*))(@([^:/]*)(:([0-9]*))?)?/(.*),', self::$dsn, $parts) ) {
      if ( isset($parts[1]) ) {
        $db_type = $parts[1];
      }
      if ( isset($parts[2]) ) {
        $db_username = $parts[2];
      }
      if ( isset($parts[4]) ) {
        $db_password = $parts[4];
      }
      if ( isset($parts[6]) ) {
        $db_server = $parts[6];
      }
      if ( isset($parts[8]) ) {
        $db_port = $parts[8];
      }
      if ( isset($parts[9]) ) {
        $db_name = $parts[9];
      }
    }

    return array(
        'type'      =>  $db_type,
        'username'  =>  $db_username,
        'password'  =>  $db_password,
        'server'    =>  $db_server,
        'port'      =>  $db_port,
        'database'  =>  $db_name
    );
  }
}

if (class_exists('Doctrine')) {
  spl_autoload_register(array('Doctrine', 'autoload'));
}
spl_autoload_register(array('Dogma', 'autoload'));
