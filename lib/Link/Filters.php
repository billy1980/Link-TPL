<?php
/**
 * This file is part of Link TPL.
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @copyright Copyleft (c) 2007+, Baptiste Clavié, Talus' Works
 * @link http://www.talus-works.net Talus' Works
 * @license http://www.opensource.org/licenses/BSD-3-Clause Modified BSD License
 * @version $Id$
 */

// -- If PHP < 5.2.7, emulate PHP_VERSION_ID
// @codeCoverageIgnoreStart
if (!defined('PHP_VERSION_ID')) {
  $v = explode('.', PHP_VERSION);

  define('PHP_VERSION_ID', $v[0] * 10000 + $v[1] * 100 + $v[2]);
}
// @codeCoverageIgnoreEnd

/**
 * Filters available for Link TPL's templates
 *
 * If you need a new filter, you just have to add a new method, which
 * will be public and static, having at least one argument ($arg or
 * whatever) which will represent the variable to filter itself.
 *
 * <code><?php
 * class Link_Filters {
 *  //...
 *  public static myNewFilter($arg) {
 *    return $arg;
 *  }
 * }</code>
 *
 * Once you have done so, you will have to regenerate the cache file
 * generated by the Cache Engine in order to make sure this filter is
 * now taken as is.
 *
 * @package Link
 * @author Baptiste "Talus" Clavié <clavie.b@gmail.com>
 * @since 1.5.0
 */
class Link_Filters {
  // @codeCoverageIgnoreStart
  /** @ignore */
  final private function __construct() {}

  /** @ignore */
  final private function __clone() {}
  // @codeCoverageIgnoreEnd

  /**
   * Round fractions up
   *
   * @param string $arg The numeric value to round
   * @return string value rounded up to the next integer
   */
  public static function ceil($arg){
    return (string) ceil((float) $arg);
  }

  /**
   * Round fractions down
   *
   * @param string $arg The numeric value to round
   * @return string value rounded to the next lower integer
   */
  public static function floor($arg){
    return (string) floor((float) $arg);
  }

  /**
   * Convert special characters to HTML entities
   *
   * @param string $arg The string being converted
   * @param int $quote_style <b>[Optional]</b>
   *                         The optional second argument, quote_style, tells the
   *                         function what to do with single and double quote
   *                         characters. The default mode, ENT_COMPAT, is the
   *                         backwards compatible mode which only translates the
   *                         double-quote character and leaves the single-quote
   *                         untranslated. If ENT_QUOTES is set, both single and
   *                         double quotes are translated and if ENT_NOQUOTES is
   *                         set neither single nor double quotes are translated.
   * @param string $charset <b>[Optional]</b>
   *                        <p>Defines character set used in conversion. The
   *                        default character set is ISO-8859-1.</p>
   *                        <p>For the purposes of this function, the charsets
   *                        ISO-8859-1, ISO-8859-15, UTF-8, cp866, cp1251,
   *                        cp1252, and KOI8-R are effectively equivalent, as the
   *                        characters affected by htmlspecialchars occupy the
   *                        same positions in all of these charsets.</p>
   *                        &reference.strings.charsets
   * @param bool $double_encode <b>[Optional]</b>
   *                            When double_encode is turned off PHP will not
   *                            encode existing html entities, the default is to
   *                            convert everything.
   * @return string The converted string
   */
  public static function protect($arg, $quote_style = ENT_COMPAT, $charset = 'ISO-8859-1', $double_encode = true){
    return htmlspecialchars($arg, $quote_style, $charset, $double_encode);
  }

  /**
   * UPPERCASE the first letter of a string
   *
   * @param string $arg <p>The string having it's first letter UPPERCASED</p>
   * @param string $encoding <b>[optional]</b> &mbstring.encoding.parameter;
   * @return string str with it's first letter converted to UPPERCASE.
   */
  public static function ucfirst($arg, $encoding = null){
    if ($encoding === null) {
      $encoding = mb_internal_encoding();
    }
    
    $arg[0] = mb_strtoupper($arg[0], $encoding);

    return $arg;
  }

  /**
   * lowercase the first letter of a string
   *
   * @param string $arg <p>The string having it's first letter lowercased</p>
   * @param string $encoding <b>[optional]</b> &mbstring.encoding.parameter;
   * @return string str with it's first letter converted to lowercase.
   */
  public static function lcfirst($arg, $encoding = null){
    if ($encoding === null) {
      $encoding = mb_internal_encoding();
    }
    
    $arg[0] = mb_strtolower($arg[0], $encoding);

    return $arg;
  }

  /**
   * Perform case folding on a string
   *
   * @param string $arg <p>The string being converted.</p>
   * @param int $mode <p>The mode of the conversion. It can be one of
                         MB_CASE_UPPER, MB_CASE_LOWER, or MB_CASE_TITLE.</p>
   * @param string $encoding <b>[optional]</b> &mbstring.encoding.parameter;
   * @return string A case folded version of string converted in the
   *                way specified by $mode.
   */
  public static function convertCase($arg, $mode, $encoding = null){
    if ($encoding === null) {
      $encoding = mb_internal_encoding();
    }

    return mb_convert_case($arg, $mode, $encoding);
  }

  /**
   * Perform a change of case on a string
   *
   * @param string $arg the string to be converted
   * @param string $encoding <b>[optional]</b> &mbstring.encoding.parameter;
   * @return string the string converted
   */
  public static function invertCase($arg, $encoding = null){
    if ($encoding === null) {
      $encoding = mb_internal_encoding();
    }
    
    for ($i = 0, $length = mb_strlen($arg, $encoding); $i < $length; $i++){
      $tolower = mb_strtolower($arg[$i], $encoding);
      $arg[$i] = $arg[$i] == $tolower ? mb_strtoupper($arg[$i], $encoding) : $tolower;
    }

    return $arg;
  }

  /**
   * Make a string UPPERCASE.
   *
   * This is a helper for convertCase.
   *
   * @param string $arg The string to be UPPERCASED
   * @param string $encoding <b>[optional]</b> &mbstring.encoding.parameter;
   * @return string the string UPPERCASED
   */
  public static function maximize($arg, $encoding = null) {
    return self::convertCase($arg, MB_CASE_UPPER, $encoding);
  }

  /**
   * Make a string lowercase.
   *
   * This is a helper for convertCase.
   *
   * @param string $arg The string to be lowercased
   * @param string $encoding <b>[optional]</b> &mbstring.encoding.parameter;
   * @return string the string lowercased
   */
  public static function minimize($arg, $encoding = null) {
    return self::convertCase($arg, MB_CASE_LOWER, $encoding);
  }

  /**
   * Inserts HTML line breaks before all newlines in a string
   *
   * @param string $arg <p>The input string.</p>
   * @param bool $is_xhtml <b>[optional]</b> Whenever to use XHTML compatible
   *                       line breaks or not.
   * @return string The altered string
   */
  public static function nl2br($arg, $is_xhtml = true){
    return PHP_VERSION_ID >= 50300 ? nl2br($arg, $is_xhtml) : nl2br($arg);
  }

  /**
   * Create the slug for a string, and send it back.
   * This method comes from the Jobeet Project, by the Pratical Symfony tutorial.
   *
   * @param string $arg Str to slugify
   * @link http://www.symfony-project.org Symfony Framework
   * @return string $arg's slug, n-a if not valid.
   */
  public static function slugify($arg) {
    $arg = trim(preg_replace('`[^\\pL\d]+`u', '-', trim($arg)), '-');

    if (function_exists('iconv')) {
      $arg = iconv('utf-8', 'us-ascii//TRANSLIT', $arg);
    }

    $arg = preg_replace('`[^-\w]+`', '', strtolower($arg));

    if (!$arg) {
      $arg = 'n-a';
    }

    return $arg;
  }

  /**
   * Cut a string longer than $max characters. Words are in-interrupted.
   *
   * @param string $arg string to cut
   * @param integer $max maximum number of characters allowed in $arg
   * @param string $finish string to apply at the end $arg if it is cut.
   * @return string string altered if too long, or id if not.
   */
  public static function cut($arg, $max = 50, $finish = '...'){
    if (strlen($arg) <= $max){
      return $arg;
    }

    $max = intval($max) - strlen($finish);

    $arg = substr($arg, 0, $max + 1);
    $arg = strrev(strpbrk(strrev($arg), " \t\n\r\0\x0B"));

    return rtrim($arg) . $finish;
  }

  /**
   * Converts newlines into <p> and <br />s.
   *
   * Two newlines are transformed into a paragraph (<p> tag), and one newline
   * gives one <br />.
   * Adapted from python to php, thanks to the "utils.html" package of the
   * Django Framework.
   *
   * @param string $arg string to be transformed
   * @return string altered string
   */
  public static function paragraphy($arg){
    $arg = str_replace(PHP_EOL, "\n", $arg);

    $paras = preg_split("`\n{2,}`si", $arg);

    foreach ($paras as &$para) {
      $para = str_replace("\n", '<br />' . PHP_EOL, $para);
    }

    return '<p>' . implode('</p>' . PHP_EOL . PHP_EOL . '<p>', $paras) . '</p>';
  }

  /**
   * Unescape a var (meaning she is "safe")
   *
   * @param string $arg Variable
   * @param int $quote_style <b>[Optional]</b>
   *                         <p>Like the htmlspecialchars and htmlentities
   *                         functions you can optionally specify the quote_style
   *                         you are working with.</p>
   *                         See the description of these modes in htmlspecialchars.
   *
   * @return string unescaped var
   */
  public static function safe($arg, $quote_style = ENT_COMPAT) {
    return htmlspecialchars_decode($arg, $quote_style);
  }

  /**
   * Just do... Nothing.
   *
   * @param string $arg Variable
   * @return string the variable's value
   */
  public static function void($arg) {
    return $arg;
  }
  
  /**
   * Sets a default value for $arg if it's empty, false, ... etc
   * 
   * @param mixed $arg Variable
   * @param mixed $default default value
   * @return mixed default value if variable is empty, variables value otherwise 
   */
  public static function defaults($arg, $default = '') {
    if (!$arg) {
      return $default;
    }
    
    return $arg;
  }
}

/** EOF /**/
