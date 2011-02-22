<?php
/**
  phpSec - A PHP security library

  @author    Audun Larsen <larsen@gmail.com>
  @copyright Copyright (c) Audun Larsen, 2011
  @link      https://github.com/xqus/phpSec
  @license   http://opensource.org/licenses/mit-license.php The MIT License
  @package   phpSec
 */
class phpsecOtp {

  /**
   * Generate a one-time-password (OTP). The password is only valid for a given time,
   * and must be delivered to the user instantly. The password is also only valid
   * for the current session.
   *
   * @param string $action
   *   The action to generate a OTP for. This should be as specific as possible.
   *   Used to ensure that the OTP is used for the intended action.
   *
   * @param array $data
   *   Optional array of data that belongs to $action. Used to ensure that the action
   *   is performed with the same data as when the OTP was generated.
   *
   * @param integer $length
   *   OTP length.
   *
   * @param integer $ttl
   *   Time to live for the OTP. In seconds.
   *
   */
  public static function generate($action, $data = null, $length = 6, $ttl = 480) {

  }

  /**
   * Validate a one-time-password.
   *
   * @param string $action
   *   See phpsecOtp::generate().
   *
   * @param array $data
   *   See phpsecOtp::generate().
   *
   */
  public static function validate($action, $data = array()) {

  }

  /**
   * Create a list of pre shared one-time-passwords.
   * This differs from phpsecOtp::generate() because passwords generated by
   * this function is saved permanent and can be validated on a later time.
   */
  public static function psGenerate() {

  }

  /**
   * Validates a pre shared one-time-password.
   */
  public static function psValidate() {

  }

}