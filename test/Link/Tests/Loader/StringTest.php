<?php
/**
 * This file is part of Link TPL.
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @copyright Copyleft (c) 2007+, Baptiste Clavié, Talus' Works
 * @link      http://www.talus-works.net Talus' Works
 * @license   http://www.opensource.org/licenses/BSD-3-Clause Modified BSD License
 * @version   $Id$
 */

/**
 * Tests the prebuilt loader using a simple string
 *
 * @package Link.test
 * @author  Baptiste "Talus" Clavié <clavie.b@gmail.com>
 *
 */
class Link_Tests_Loader_StringTest extends PHPUnit_Framework_TestCase {
    /** @var Link_Loader_String */
    protected $_loader;


    protected $_actual;

    public function setUp() {
        $this->_loader = new Link_Loader_String;

        // For Cap'n Mousse ! :)
        $this->_actual = <<<EOT
I wonder how
I wonder why
Yesterday you told me 'bout the blue blue sky
And all that I can see is just a yellow lemon-tree
I'm turning my head up and down
I'm turning turning turning turning turning around
And all that I can see is just another lemon-tree
EOT;
    }

    public function testSource() {
        $this->assertEquals($this->_actual, $this->_loader->getSource($this->_actual));
    }

    public function testCacheKey() {
        $this->assertEquals('ebef9b10907a0800db741b0f3887f174647b16cd', $this->_loader->getCacheKey($this->_actual));
    }

    public function testFresh() {
        $this->assertTrue($this->_loader->isFresh($this->_actual, time()));
    }
}