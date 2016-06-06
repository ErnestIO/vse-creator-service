<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace VSE;

/**
 * Class Utils
 */
class Utils
{
    /**
     * Loads configuration from a file path.
     *
     * @param string $path Path where the configuration path is stored.
     * @return mixed JSON parsed structure with the configuration.
     */
    public static function loadConfiguration($path)
    {
        $string = file_get_contents($path);
        $config = json_decode($string, true);
        return $config;
    }
}
