<?php
/**
 * Parser for Talus' TPL's templates scripts.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 *
 * @package Talus' Works
 * @author Baptiste "Talus" Clavié <clavie.b@gmail.com>
 * @copyright ©Talus, Talus' Works 2008+
 * @link http://www.talus-works.net Talus' Works
 * @license http://www.gnu.org/licenses/gpl.html GNU Public License 2+
 */

// -- If PHP < 5.3, emulating E_USER_DEPRECATED
if (!defined('E_USER_DEPRECATED')) {
  define('E_USER_DEPRECATED', E_USER_NOTICE);
}

/**
 * Template's Parser
 *
 * This class handle the transformation from a Talus TPL code to an optimized
 * PHP code, which can be used by PHP.
 */
class Talus_TPL_Parser implements Talus_TPL_Parser_Interface {
  protected $_parameters = array();

  const
    SET = 1,
    FILTERS = 2,
    INCLUDES = 4,
    CONDITIONS = 8,
    CONSTANTS = 16,

    BASICS = 4,
    DEFAULTS = 31,
    ALL = 31,

    // -- Regex used
    REGEX_PHP_ID = '[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*', // PHP Identifier
    REGEX_ARRAYS = '\[(?!]})(?:.*?)]'; // PHP Arrays

  /**
   * Initialisation
   *
   * @return void
   */
  public function __construct(){
    $this->parameter('parse', self::DEFAULTS);
    $this->parameter('set_compact', false);
    $this->parameter('namespace', '');
  }

  /**
   * Accessor for a given parameter
   *
   * @param string $param Parameter's name
   * @param mixed $value Parameter's value (if setter)
   * @return mixed Parameter's value
   */
  public function parameter($param, $value = null) {
    if ($value !== null) {
      $this->_parameters[$param] = $value;
    }

    return $this->_parameters[$param];
  }

  /**
   * Transform a TPL syntax towards an optimized PHP syntax
   *
   * @param string $script TPL script to parse
   * @return string
   */
  public function parse($script){
    $script = str_replace('<?' ,'<?php echo \'<?\'; ?>', $script);
    $script = preg_replace('`/\*.*?\*/`s', '', $script);

    $nspace = $this->parameter('namespace');

    if (!empty($nspace)) {
      $nspace .= ':';
    }

    // -- Stubs for blocks
    $script = str_replace(array('<blockelse />', '</block>'), array('<foreachelse />', '</foreach>'), $script);

    // -- Filter's transformations
    if ($this->parameter('parse') & self::FILTERS) {
      $matches = array();
      while (preg_match('`\{(\$?' . self::REGEX_PHP_ID . '(?:\.value(?:' . self::REGEX_ARRAYS . ')?|key|is_(?:first|last)|current|size)?)\|((?:' . self::REGEX_PHP_ID . '(?::.+?)*\|?)+)}`', $script, $matches)) {
        $script = str_replace($matches[0], $this->_filters($matches[2], $matches[3], $matches[1]), $script);
      }
    }

    // @deprecated Blocks
    $script = preg_replace_callback('`<' . $nspace . 'block ' . $nspace . 'name="([a-z_\xe0-\xf6\xf8-\xff][a-z0-9_\xe0-\xf6\xf8-\xff]*)"(?: ' . $nspace . 'parent="([a-z_\xe0-\xf6\xf8-\xff][a-z0-9_\xe0-\xf6\xf8-\xff]*)")?>`', array($this, '_block'), $script);

    // -- Inclusions
    if ($this->parameter('parse') & self::INCLUDES) {
      $script = preg_replace_callback('`<' . $nspace . '(include|require) ' . $nspace . 'tpl="((?:.+?\.html(?:\?[^\"]*)?)|(?:\{\$(?:' . self::REGEX_PHP_ID . '(?:' . self::REGEX_ARRAYS . ')?}))"(?: ' . $nspace . 'once="(true|false)")? />`', array($this, '_includes'), $script);
    }

    // -- <foreach> tags// -- <foreach> tag
    $script = preg_replace_callback('`<' . $nspace . 'foreach ' . $nspace . 'ar(?:ra)?y="(\{\$' . self::REGEX_PHP_ID . '(?:\.value(?:' . self::REGEX_ARRAYS . ')?)?})"(?: ' . $nspace . '(?:name|as)="(' . self::REGEX_PHP_ID . ')")?`', array($this, '_foreach'), $script);

    // -- Simple regex which doesn't need any recursive treatment.
    $not_recursives = array(
       // -- Foreach Keys
       '`\{(' . self::REGEX_PHP_ID . ').key}`' => '<?php echo $__tpl_foreach[\'$1\'][\'key\']; ?>',
       '`\{\$(' . self::REGEX_PHP_ID . ').key}`' => '$__tpl_foreach[\'$1\'][\'key\']'
      );

    $recursives = array(
      // -- Foreach values
      '`\{(' . self::REGEX_PHP_ID . ').val(?:ue)?(' . self::REGEX_ARRAYS . ')?}`' => '<?php echo $__tpl_foreach[\'$1\'][\'value\']$2; ?>',
      '`\{\$(' . self::REGEX_PHP_ID . ').val(?:ue)?(' . self::REGEX_ARRAYS . ')?}`' => '$__tpl_foreach[\'$1\'][\'value\']$2',

      // -- Simple variables ({VAR1}, {VAR2[with][a][set][of][keys]}, ...)
      '`\{(' . self::REGEX_PHP_ID . '(?:' . self::REGEX_ARRAYS . ')?)}`' => '<?php echo $__tpl_vars__$1; ?>',
      '`\{\$(' . self::REGEX_PHP_ID . '(?:' . self::REGEX_ARRAYS . ')?)}`' => '$__tpl_vars__$1'
     );

    // -- No Regex (faster !)
    $noRegex = array(
      "</{$nspace}foreach>" => '<?php } $__refering_var = array_pop($__tpl_stack); if (isset($__tpl_foreach[$__refering_var])) unset($__tpl_foreach[$__block]); endif; ?>',
      "<{$nspace}foreachelse />" => '<?php } else : if (true) { $__tpl_stack[] = \'*foo*\'; ?>',

      '{\\' =>  '{'
     );

    // -- Simple regex needing a recursive treatment
    $recursives = array(
      // -- Block variables ({block.VAR1}, ...)
      // -- EX REGEX ; [a-z_\xe0-\xf6\xf8-\xff][a-z0-9_\xe0-\xf6\xf8-\xff]*
      // @todo fix that
      '`\{([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\.([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)(\[(?!]})(?:.*?)])?}`' => '<?php echo $__tpl_foreach[\'$1\'][[\'$2\']$3; ?>',
      '`\{\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\.([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)(\[(?!]})(?:.*?)])?}`' => '$__tplBlock[\'$1\'][\'$2\']$3'
     );

    // -- <set> Tag
    if ($this->parameter('parse') & self::SET) {
      $not_recursives['`<' . $nspace . 'set ' . $nspace . 'var="(' . self::REGEX_PHP_ID . ')(\[(?!]">)(?:.*?)])?">(?!"</set>)(.+?)</set>`'] = '<?php $__tpl_vars__$1$2 = \'$3\'; ?>';
    }

    // -- Constants
    if ($this->parameter('parse') & self::CONSTANTS) {
      //[a-zA-Z_\xe0-\xf6\xf8-\xff\xc0-\xd6\xd8-\xde][a-zA-Z0-9_\xe0-\xf6\xf8-\xff\xc0-\xd6\xd8-\xde]*
      $not_recursives['`\{__(' . self::REGEX_PHP_ID . ')__}`i'] = '<?php echo $1; ?>';
      $not_recursives['`\{__$(' . self::REGEX_PHP_ID . ')__}`i'] = '$1';
    }

    // -- Conditions tags (<if>, <elseif />, <else />)
    if ($this->parameter('parse') & self::CONDITIONS) {
      $not_recursives = array_merge($not_recursives, array(
        '`<' . $nspace . 'if ' . $nspace . 'cond(?:ition)?="(.+?)">`' => '<?php if ($1) : ?>',
        '`<' . $nspace . 'el(?:se)?if ' . $nspace . 'cond(?:ition)?="(.+?)" />`' => '<?php elseif ($1) : ?>'
       ));

      $noRegex["<{$nspace}else />"] = '<?php else : ?>';
      $noRegex["</{$nspace}if>"] = '<?php endif; ?>';
    }

    $script = preg_replace(array_keys($not_recursives), array_values($not_recursives), $script);

    foreach ($recursives as $regex => $replace) {
      while(preg_match($regex, $script)) {
        $script = preg_replace($regex, $replace, $script);
      }
    }

    $script = str_replace(array_keys($noRegex), array_values($noRegex), $script);

    /*
     * Cleaning the newly made script...
     *
     * Depending on the value of the "set_compact" parameter, if it is on, everything
     * considered as "emptyness" between two php tags (?><?php), meaning any spaces,
     * newlines, tabs, or whatever will be cleaned, including the PHP tags in the
     * middle.
     *
     * If it is off (by default), only the ?><?php tags will be removed.
     */
    if ($this->parameter('set_compact')) {
      $script = preg_replace('`\?>\s*<\?php`', '', $script);
    } else {
      $script = str_replace('?><?php', '', $script);
    }

    return $script;
  }

  /**
   * Parse a TPL script
   * Implementation of the magic method __invoke() for PHP >= 5.3
   *
   * @param string $script TPL Script to be parsed
   * @return string PHP Code made
   * @see self::parse()
   */
  public function __invoke($script) {
    return $this->parse($script);
  }

  /**
   * Blocks interpretations
   *
   * @param array $match Regex matches
   * @return string
   * @see self::compile()
   * @see 97
   */
  protected function _block(array $match){
    /*
     * If there are no parent block, it means it is a root block ; We just need
     * to fetch it thanks to the getter. It can be used as the loop condition and
     * for the block's name. This is the default behaviour.
     *
     * If not, we have to fetch the parent block, get the current iteration, and
     * associate it with the current block. For the loop condition, we just need
     * to check if the block is defined within the parent block.
     */
    $cond = sprintf('$tpl->block(\'%s\', null)', $match[1]);

    // -- Referencing variable for this block.
    $block = '$__tpl_' . sha1(uniqid(mt_rand(), true));
    $ref = sprintf('%1$s = %2$s;', $block, $cond);

    if (!empty($match[2])) {
      $block = sprintf('$__tplBlock[\'%2$s\'][\'%1$s\']', $match[1], $match[2]);
      $cond = sprintf('isset(%s)', $block);
      $ref = '';
    }


    /*
     * In order to make a foreach with referenced values, which wants a variable
     * (and not a function), we have to create a temporary variable to get the
     * reference given by $tpl->getBlock (or the parent block)...
     *
     * To avoid a conflict between two references (e.g the block is called two
     * times in a row), which is a acknowledged bug in PHP (#29992), we have to
     * set a kind of stack for the block's name, insert the current block's name
     * at the top and removing it at the end of the loop, deleting the reference
     * made by the foreach (phew).
     */
    return sprintf('<?php if (%1$s) :
                            %2$s $__tpl_block_stack[] = \'%4$s\';
                            foreach (%3$s as &$__tplBlock[\'%4$s\']){ ?>',
                   $cond, $ref, $block, $match[1]);
  }

  /**
   * Filters implementation
   *
   * Parse all the $filters given for the var $var
   *
   * @param mixed $var Variable
   * @param string $filters Filters
   * @param string $type Variable's type (for {TYPE,VAR})
   * @return string filtered var
   */
  protected function _filters($var = '', $filters = '', $type = null){
    $brackets = 0;
    $toPrint = false;
    $return = sprintf('{%s}', $var);
    $filters = array_reverse(array_filter(explode('|', $filters)));

    /*
     * If we wish to print the variable (the significative $ is missing), we have
     * to set up the variable to have a $... Being printed and not returned.
     *
     * Weh just have to add the $ in front of the name of the variable, and clearly
     * say we have to print the result.
     */
    if ($return[1] != '$') {
      $return = '{$' . mb_substr($return, 1);
      $toPrint = true;
    }

    /*
     * If it is a typed variable ({TYPE,VAR}), we have to replace the first opening
     * { by {TYPE,
     */
    if (!empty($type)) {
      $return = sprintf('{%1$s,%2$s', $type, mb_substr($return, 1));
    }

    foreach ($filters as &$filter) {
      $params = explode(':', $filter);
      $fct = array_shift($params);

      // -- unimplemented filter ?
      if (!method_exists('Talus_TPL_Filters', $fct)){
        trigger_error("The filter \"$fct\" does not exist, and thus shall be ignored.\n\n",
                      E_USER_NOTICE);
        continue;
      }

      // -- Filter's Parameters
      if (count($params) > 0) {
        foreach ($params as &$param) {
          $param = $this->_escape($param);
        }

        $params = ', ' . implode(', ', $params);
      } else {
        $params = '';
      }

      $return = sprintf('Talus_TPL_Filters::%1$s(%2$s%3$s)', $fct, $return, $params);
    }

    // -- Printing the return rather than returning it
    if ($toPrint === true){
      $return = sprintf('<?php echo %s; ?>', $return);
    }

    return $return;
  }

  /**
   * Inclusions' Parser
   *
   * @param array $match Regex matchs
   * @return string include function with the right parameters
   * @todo Find a better way to handle variables in the QS
   */
  protected function _includes(array $match) {
    $qs = '';

    // -- A QS was found
    if (strpos($match[2], '?') !== false) {
      list($match[2], $qs) = explode('?', $match[2], 2);
      $qs = sprintf(' . "?%s"', str_replace(array('{', '}'), array('{{', '}}'), $qs));
    }

    return sprintf('<?php $tpl->includeTpl(%1$s%2$s, %3$s, Talus_TPL::%4$s_TPL); ?>',
                   $this->_escape($match[2]), $qs,
                   isset($match[3]) && $match[3] == 'true' ? 'true' : 'false',
                   mb_strtoupper($match[1]));
  }

  /**
   * Escape a given value
   *
   * Will act accordingly if it is a string, a variable, or numbers
   *
   * @param string $arg Value to escape
   * @param string $delim String's delimiters
   * @return string Escaped value
   */
  protected function _escape($arg, $delim = '\'') {
    if (($arg[0] != $delim || $arg[mb_strlen($arg) - 1] != $delim)
     && ($arg[0] != '{' || $arg[mb_strlen($arg) - 1] != '}')
     && !filter_var($arg, FILTER_VALIDATE_INT)) {
      $arg = sprintf('%1$s%2$s%1$s', $delim, addcslashes($arg, $delim));
    }

    return $arg;
  }
}

/** EOF /**/
