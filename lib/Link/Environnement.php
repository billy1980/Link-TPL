<?php
/**
 * This file is part of Link TPL
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @copyright Copyleft (c) 2007+, Baptiste Clavié, Talus' Works
 * @link http://www.talus-works.net Talus' Works
 * @license http://www.opensource.org/licenses/BSD-3-Clause Modified BSD License
 * @version $Id$
 */

defined('E_USER_DEPRECATED') || define('E_USER_DEPRECATED', E_USER_NOTICE);

/**
 * The templating engine itself
 *
 * @package Link
 * @author Baptiste "Talus" Clavié <clavie.b@gmail.com>
 */
class Link_Environnement {
  protected
    $_root = null,

    $_last = array(),
    $_included = array(),

    $_vars = array(),
    $_references = array(),

     $_autoFilters = array(),

    /** @var Link_Interfaces_Parser */
    $_parser = null,

    /** @var Link_Interfaces_Cache */
    $_cache = null;

  const
    INCLUDE_TPL = 0,
    REQUIRE_TPL = 1,
    VERSION = '1.13.0-DEV';

  /**
   * Initialisation.
   *
   * Available options :
   *  - dependencies : Handle the dependencies (parser, cache, ...). Each of
   *                   these must be an object.
   *
   * @param string $root Directory where the templates files are.
   * @param string $cache Directory where the php version of the templates will be stored.
   * @param array $_options Options for the templating engine
   * @return void
   */
  public function __construct($root, $cache, array $_options = array()){
    // -- Resetting the PHP cache concerning the files' information.
    clearstatcache();

    // -- Options
    $defaults = array(
      'dependencies' => array(
        'parser' => new Link_Parser,
        'cache' => new Link_Cache,
       )
     );

    $_options = array_replace_recursive($defaults, $_options);

    // -- Dependency Injection
    $this->dependencies($_options['dependencies']['parser'],
                        $_options['dependencies']['cache']);

    $this->dir($root, $cache);
  }

  /**
   * Set the templates & cache directory.
   *
   * @param string $root Directory containing the original templates.
   * @param string $cache Directory containing the cache files.
   * @throws Talus_Dir_Exception
   * @return void
   *
   * @since 1.7.0
   */
  public function dir($root = null, $cache = null) {
    if ($root === null) {
      $root = $this->_root;
    }

    // -- Removing the final "/", if it's there.
    $root = rtrim($root, '/');

    if (!is_dir($root)) {
      throw new Link_Exceptions_Dir(array('%s is not a directory.', $root), 1);
      return;
    }

    $this->_root = $root;

    // -- Let the cache engine handle his own directory !
    $this->_cache->dir($cache);
  }

  /**
   * Sets the global variable for all the templates
   *
   * @param array|string $vars Var(s)' name (tpl side)
   * @param mixed $value Var's value if $vars is not an array
   * @return array
   *
   * @since 1.3.0
   */
  public function set($vars, $value = null){
    if (is_array($vars)) {
      $this->_vars = array_replace_recursive($this->_vars, $vars);
      return;
    }
    
    $this->_vars[$vars] = $value;
  }

  /**
   * Adds a default filter to be applied on variables (except references)
   * WARNING : BEWARE of the order of declaration !
   *
   * @param string $name Filters' names
   * @throws Link_Exceptions_Filter
   * @return array
   *
   * @since 1.9.0
   */
  public function autoFilters($name) {
    if (!method_exists($this->parser()->parameter('filters'), $name)) {
      throw new Link_Exceptions_Filter(array('The filter %s doesn\'t exist...', $name), 404);
    }

    $this->_autoFilters[] = $name;
  }

  /**
   * Sets a variable $var, referencing $value.
   *
   * @param mixed $var Var's name
   * @param mixed &$value Variable to be referenced by $var
   * @throws Link_Exceptions_Var
   * @return void
   *
   * @since 1.7.0
   */
  public function bind($var, &$value) {
    $this->_vars[$var] = &$value;
    $this->_references[] = $var;
  }

  /**
   * Parse and execute the Template $tpl.
   *
   * If $tpl is an array of files, all the files will be parsed.
   *
   * @param mixed $tpl TPL to be parsed & executed
   * @param array $_context Local variables to be given to the template
   * @param mixed $cache If the cache exists, erase it only if not fresh
   * @throws Link_Exceptions_Parse
   * @return bool
   */
  public function parse($tpl, array $_context = array(), $cache = true){
    if (strlen((string) $tpl) === 0) {
      throw new Link_Exceptions_Parse('No template to be parsed.', 5);
      return false;
    }

    $file = sprintf('%1$s/%2$s', $this->_root, $tpl);

    if (!isset($this->_last[$file])) {
      if (!is_file($file)) {
        throw new Link_Exceptions_Parse(array('The template <b>%s</b> doesn\'t exist.', $tpl), 6);
        return false;
      }

      $this->_last[$file] = filemtime($file);
    }

    $this->_cache->file($tpl, 0);

    if (!$this->_cache->isValid($this->_last[$file]) || !$cache) {
      $this->_cache->put($this->str(file_get_contents($file), false));
    }
    
    // -- extracting the references...
    $vars = array_diff_key($this->_vars, array_flip($this->_references));
    $context = array_replace_recursive($vars, $_context);
    
    // -- Applying the filters...
    foreach ($this->_autoFilters as &$filter) {
      array_walk_recursive($context, array($this->parser()->parameter('filters'), $filter));
    }
    
    // -- and, finally, replacing the references...
    $context += array_diff($this->_vars, $vars);

    $this->_cache->exec($this, $context);
    return true;
  }

  /**
   * Parse & execute a string
   *
   * @param string $str String to parse
   * @param array $_context Local variables to be given to the template
   * @param bool $exec Execute the result ?
   * @throws Link_Exceptions_Parse
   * @return string PHP Code generated
   */
  public function str($str, array $_context = array(), $exec = true) {
    if (empty($str)) {
      return '';
    }

    // -- Compilation
    $compiled = $this->_parser->parse($str);

    // -- Cache if need to be executed. Will be destroyed right after the execution
    if ($exec === true) {
      $this->_cache->file(sprintf('tmp_%s.html', sha1($str)), 0);
      $this->_cache->put($compiled);
      $this->_cache->exec($this, $_context);
      $this->_cache->destroy();
    }

    return $compiled;
  }

  /**
   * Parse a TPL
   * Implemention of magic method __invoke() for PHP >= 5.3
   *
   * @param string $tpl TPL to be parsed & executed
   * @param array $_context Local variables to be given to the template
   * @see Link_Environnement::parse()
   * @return void
   */
  public function __invoke($tpl, array $_context = array()) {
    return $this->parse($tpl, $_context);
  }

  /**
   * Parse and execute a template
   *
   * Do the exact same thing as Link_Environnement::parse(), but instead of just executing
   * the template, returns the final result (already executed by PHP).
   *
   * @param string $tpl Template's name.
   * @param array $_context Local variables to be given to the template
   * @param integer $ttl Time to live for the cache 2. Not implemented yet
   * @return string
   *
   * @todo Cache 2 ?
   */
  public function pparse($tpl = '', array $_context = array(), $ttl = 0){
    ob_start();
    $this->parse($tpl, $_context);
    return ob_get_clean();
  }

  /**
   * Include a template into another
   *
   * @param string $file File to include.
   * @param bool $once Allow the inclusion once or several times
   * @param integer $type Inclusion or requirement ?
   * @return void
   *
   * @see Link_Parser::parse()
   * @throws Link_Exceptions_Runtime
   * @throws Link_Exceptions_Parse
   */
  public function includeTpl($file, $once = false, $type = self::INCLUDE_TPL){
    // -- Parameters extraction
    $qString = '';

    if (strpos($file, '?') !== false) {
      list($file, $qString) = explode('?', $file, 2);
    }

    /*
     * If the file have to be included only once, checking if it was not already
     * included.
     *
     * If it was, we're not treating it ; If not, we add it to the stack.
     */
    if ($once){
      $toInclude = sprintf('%1$s/%2$s', $this->_root, $file);

      if (in_array($toInclude, $this->_included)) {
        return;
      }

      $this->_included[] = $toInclude;
    }
    
    $vars = array();

    try {
      // -- Adding new variables only if there is a QS
      if (!empty($qString)) {
        parse_str($qString, $vars);

        // -- If MAGIC_QUOTES is ON (grmph), Removing the \s...
        if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
          $vars = array_map('stripslashes', $vars);
        }
      }

      $data = $this->pparse($file, $vars);
    } catch (Link_Exceptions_Parse $e) {
      /*
       * If we encounter error n°6 AND it is a require tag, throws an exception
       * Link_Exceptions_Runtime instead of Link_Exceptions_Parse. If not,
       * and still a nÂ°6 error, printing the error message, or else throwing this
       * error back.
       */
      if ($e->getCode() === 6) {
        if ($type == self::REQUIRE_TPL) {
          throw new Link_Exceptions_Runtime(array('That was a "require" tag ; The template <b>%s</b> not existing,  the script shall then be interrupted.', $file), 7);
          exit;
        }

        echo $e->getMessage();
      } else {
        throw $e;
      }
    }

    echo $data;
  }

  /**#@+
   * Getters / Setters
   */

  /**
   * Parser
   *
   * @return Link_Interfaces_Parser
   */
  public function parser() {
    return $this->_parser;
  }

  /**
   * Cache
   *
   * @return Link_Interfaces_Cache
   */
  public function cache() {
    return $this->_cache;
  }

  /**
   * Dependency Injection handler.
   *
   * @param mixed $dependencies,... Dependencies
   * @throws Link_Exceptions_Dependency
   * @return void
   *
   * @todo Review the mechanism, instead of having too many conditions...
   */
  public function dependencies($dependencies = array()) {
    foreach (func_get_args() as $dependency) {
      if ($dependency instanceof Link_Interfaces_Parser) {
        $this->_parser = $dependency;
      } elseif ($dependency instanceof Link_Interfaces_Cache) {
        $this->_cache = $dependency;
      } else {
        throw new Link_Exceptions_Dependency(
                array('%s is not an acknowledged dependency.', get_class($dependency)));
      }
    }
  }

  /**#@-*/
}

/*
 * Functions dependencies
 */
if (!function_exists('array_replace_recursive')) {
  /**
   * **array_replace_recursive()** replaces the values of the first array with
   * the same values from all the following arrays.
   *
   * If a key from the first array exists in the second array, its value will be
   * replaced by the value from the second array. If the key exists in the
   * second array, and not the first, it will be created in the first array. If
   * a key only exists in the first array, it will be left as is. If several
   * arrays are passed for replacement, they will be processed in order, the
   * later array overwriting the previous values.
   *
   * **array_replace_recursive()** is recursive : it will recurse into arrays
   * and apply the same process to the inner value.
   *
   * When the value in `$original` is not an array, it will be replaced by the
   * value in `$array`, whatever may its value be. When the value in `$original`
   * and `$array` are both arrays, **array_replace_recursive()** will replace 
   * their respective value recursively.
   *
   * @param array &$original The array in which elements are replaced.
   * @param array &$array,... The arrays from which elements will be extracted.
   * @link http://www.php.net/manual/en/function.array-replace-recursive.php#92224
   * @return array Joined array
   */
  function array_replace_recursive(array &$original, array &$array) {
    $arrays = func_get_args();
    $return = array_shift($arrays);

    foreach ($arrays as &$array) {
      foreach ($array as $key => &$value) {
        if (isset($original[$key]) && is_array($original[$key]) && is_array($value)) {
          $return[$key] = array_replace_recursive($return[$key], $value);
        } else {
          $return[$key] = $value;
        }
      }
    }

    return $return;
  }
}

/*
 * EOF
 */
