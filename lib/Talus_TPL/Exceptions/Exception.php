<?php
/**
 * This file is part of Talus' TPL.
 * 
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @copyright Copyleft (c) 2007+, Baptiste Clavié, Talus' Works
 * @link http://www.talus-works.net Talus' Works
 * @license http://creativecommons.org/licenses/by-sa/3.0/ CC-BY-SA 3.0+
 * @version $Id$
 */

/**
 * Top Exception for the whole library
 * 
 * @package Talus_TPL
 * @author Baptiste "Talus" Clavié <clavie.b@gmail.com>
 */
class Talus_TPL_Exception extends Exception {
  public function __construct($message = '', $code = 0) {
    if (is_array($message)) {
      $str = array_shift($message);
      $message = vsprintf($str, $message);
    }

    parent::__construct($message, $code);
  }
}

/*
 * EOF
 */