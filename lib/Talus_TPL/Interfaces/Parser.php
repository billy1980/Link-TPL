<?php
/**
 * This file is part of Talus' TPL.
 * 
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @copyright Copyleft (c) 2007+, Baptiste Clavié, Talus' Works
 * @link http://www.talus-works.net Talus' Works
 * @license http://www.opensource.org/licenses/BSD-3-Clause Modified BSD License
 * @version $Id$
 */

/**
 * Interface to implement a new Parser for the templates.
 * 
 * @package Talus_TPL
 * @author Baptiste "Talus" Clavié <clavie.b@gmail.com>
 */
interface Talus_TPL_Interfaces_Parser extends Talus_TPL_Interfaces_Dependency {
  /**
   * Accessor for a given parameter
   *
   * @param string $param Parameter's name
   * @param mixed $value Parameter's value (if setter)
   * @return mixed Parameter's value
   */
  public function parameter($name, $val = null);

  /**
   * Transform a TPL syntax towards an optimized PHP syntax
   *
   * @param string $script TPL script to parse
   * @return string
   */
  public function parse($str);
}

/*
 * EOF
 */
