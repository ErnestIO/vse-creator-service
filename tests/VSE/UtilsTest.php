<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use phpunit\framework\TestCase;
use \VSE\Utils;

class StackTest extends TestCase
{
    public function testLoadConfiguration()
    {
        $path = "./fixtures/config.json";
        $config = Utils::loadConfiguration($path);

        $this->assertEquals("xxxx", $config['hostname']);
        $this->assertEquals("xxxxx", $config['username']);
        $this->assertEquals("xxxxx", $config['organisation']);
        $this->assertEquals("xxxxx", $config['password']);
    }
}
?>
