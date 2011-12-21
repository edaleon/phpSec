<?php
/**
  phpSec - A PHP security library

  @author    Audun Larsen <larsen@xqus.com>
  @copyright Copyright (c) Audun Larsen, 2011
  @link      https://github.com/phpsec/phpSec
  @license   http://opensource.org/licenses/mit-license.php The MIT License
  @package   phpSec
 */

/**
 * phpSec core functionality.
 */
class phpsec {

  /**
   * Config: Charset used for filter methods.
   */
  public static $_charset    = 'utf-8';

  /**
   * Config: Database Source Name.
   */
  public static $_dsn        = null;

  /**
   * Config: Enable phpSec session handler.
   */
  public static $_sessenable = true;

  /**
   * User identifier.
   */
  public static $uid         = null;

  /**
   * Storage object.
   */
  public static $store       = null;

  /* Constants. */
  const HASH_TYPE      = 'sha256';
  const VERSION        = '0.2-beta';

  /**
   * Autoload function to load required files when needed.
   */
  public static function load($class) {
    $basePath = dirname(__FILE__);
    $classes = array(
      'phpsecCache'           => 'phpsec.cache.php',
      'phpsecCrypt'           => 'phpsec.crypt.php',
      'phpsecFilter'          => 'phpsec.filter.php',
      'phpsecOtp'             => 'phpsec.otp.php',
      'phpsecPw'              => 'phpsec.pw.php',
      'phpsecRand'            => 'phpsec.rand.php',
      'phpsecSession'         => 'phpsec.session.php',
      'phpsecStore'           => 'phpsec.store.php',
      'phpsecStoreFilesystem' => 'phpsec.store.filesystem.php',
      'phpsecStorePdo'        => 'phpsec.store.pdo.php',
      'phpsecToken'           => 'phpsec.token.php',
      'phpsecYubikey'         => 'phpsec.yubikey.php',
    );

    if(isset($classes[$class])) {
      require_once $basePath.'/phpsec/'.$classes[$class];
    }
  }

  /**
   * Initialize the library.
   */
  public static function init() {
    /* First of all, register the autoloading function.
     * If we have one set from somewhere else, keep it. */
     $autoLoadFunctions   = spl_autoload_functions();
     $autoLoadFunctions[] = 'phpsec::load';
     foreach($autoLoadFunctions as $autoLoadFunction) {
       spl_autoload_register($autoLoadFunction);
     }

    /* Open store. */
    list($storeType, $storeDest) = explode(':', self::$_dsn);
    switch($storeType) {
      case 'filesystem':
        self::$store = new phpsecStoreFilesystem($storeDest);
      break;
      case 'mysql':
        self::$store = new phpsecStorePdo($storeDest);
      break;
      default:
      self::error('Store type('.$storeType.') invalid', E_USER_ERROR);
    }

    /* Set the charset of the multibyte functions in PHP. */
    mb_internal_encoding(self::$_charset);
    mb_regex_encoding(self::$_charset);

    /* Register the custom session handler if enabled. */
    if(self::$_sessenable === true) {
      ini_set('session.save_handler', 'user');
      session_set_save_handler(
        'phpsecSession::open',
        'phpsecSession::close',
        'phpsecSession::read',
        'phpsecSession::write',
        'phpsecSession::destroy',
        'phpsecSession::gc'
      );

      /* Since we set a session cookie on our session handler, disable the built-in cookies. */
      ini_set('session.use_cookies', 0);

      /* Start a new session. */
      session_start();

      /* Check the fingerprint to see if it matches, if not clear session data. */
      $fingerprint = hash(self::HASH_TYPE, 'phpSec-fingerprint'.$_SERVER['HTTP_USER_AGENT']);
      if(!isset($_SESSION['phpSec-fingerprint'])) {
        $_SESSION['phpSec-fingerprint'] = $fingerprint;
      }
      if($fingerprint != $_SESSION['phpSec-fingerprint']) {
        $_SESSION = array();
      }
    }

    /* Create a random token for each visitor and store it the users session.
       This is for example used to identify owners of cache data. */
    if(!isset($_SESSION['phpSec-uid'])) {
      self::$uid = self::genUid();
      $_SESSION['phpSec-uid'] = self::$uid;
    } else {
      self::$uid = $_SESSION['phpSec-uid'];
    }
  }

  /**
   * Provides the library a simple way of reporting errors to the developer using PHPs error
   * handler.
   *
   * @param string $msg
   *   String containing the error message
   *
   * @param constant $level
   *   Error level (optional).
   *   If none is specified E_USER_WARNING is used.
   */
  public static function error($msg, $level = E_USER_WARNING) {
    $callee = next(debug_backtrace());
    trigger_error($msg.'. (Called from <strong>'.$callee['file'].' line '.$callee['line'].'</strong>)', $level);
  }

  /**
   * Returns a unique identifier in the format spsecified in
   * OpenID Authentication 2.0 protocol.
   * For example: 2005-05-15T17:11:51ZUNIQUE
   * This function is used to generate all unique tokens used by
   * phpSec.
   * @see http://openid.net/specs/openid-authentication-2_0.html
   *
   * @param integer $length
   *   The total length of the uid. Must be above 25.
   */
  public static function genUid($length = 50) {
    if($length < 25) {
      self::error('Length must be longer than 25');
      return false;
    }
    $timeStamp = gmdate('Y-m-d\TH:i:se');
    $randLength = $length-strlen($timeStamp);
    return $timeStamp.phpsecRand::str($randLength);
  }

  /**
   * Check structure of an array.
   * This method checks the structure of an array (only the first layer of it) against
   * a defined set of rules.
   *
   * @param array $array
   *   Array to check.
   *
   * @param array $structure
   *   Expected array structure. Defined for example like this:
   *   array(
   *     'string' => array(
   *       'callback' => 'strlen',
   *       'params'   => array('%val'),
   *       'match'    => 3,
   *     ),
   *     'not allowed' = false, // Only makes sense with $strict = false
   *     'needed'      = true,
   *   ),
   *
   * @param bool $strict
   *   If strict is set to false we will allow keys that's not defined in the structure.
   */
  public static function arrayCheck($array, $structure, $strict = true) {
    $success = true;
    /* First compare the size of the two arrays. Return error if strict is enabled. */
    if(sizeof($array) != sizeof($structure) && $strict === true) {
      self::error('Array does not match defined structure');
      return false;
    }

    /* Loop trough all the defined keys defined in the structure. */
    foreach($structure as $key => $callbackArray) {
      if(isset($array[$key])) {
        /* The key exists in the array we are checking. */

        if(is_array($callbackArray) && isset($callbackArray['callback'])) {
          /* We have a callback. */

          /* Replace %val with the acutal value of the key. */
          $callbackArray['params'] = str_replace('%val', $array[$key], $callbackArray['params']);

          if(call_user_func_array($callbackArray['callback'], $callbackArray['params']) !== $callbackArray['match']) {
            /* Call the *duh* callback. If this returns false throw error, or an axe. */
            self::error('Array does not match defined structure. The '.$key.' key did not pass the '.$callbackArray['callback'].' callback');
            $success = false;
          }
        } elseif($callbackArray === false) {
          /* We don't have a callback, but we have found a disallowed key. */
          self::error('Array does not match defined structure. '.$key.' is not allowed');
          $success = false;
        }
      } else {
        /* The key don't exist in the array we are checking. */

        if($callbackArray !== false) {
          /* As long as this is not a disallowed key, sound the general alarm. */
          self::error('Array does not match defined structure. '.$key.' not defined');
          $success = false;
        }
      }
    }
    return $success;
  }
}

