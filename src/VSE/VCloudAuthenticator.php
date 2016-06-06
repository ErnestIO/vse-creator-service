<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace VSE;

use Slim\Middleware\HttpBasicAuthentication\AuthenticatorInterface;

/**
 * Class VCloudAuthenticator
 *
 * Sign in user using Basic HTTP Authentication
 * http://en.wikipedia.org/wiki/Basic_access_authentication
 */
class VCloudAuthenticator implements AuthenticatorInterface
{

    /**
     * @var string Currently only supports Carenza's VCloud instance.
     */
    public $hostname = "myvdc.carrenza.net";

    /**
     * @var float Vetsion of the API to use.
     */
    private $apiVersion = 5.5;

    /**
     * The __invoke() method is called when a script tries to call an object as a function.
     *
     * @param array $arguments Arguments hastable with user and password.
     * @return bool boolean Result of the authentication.
     */
    public function __invoke(array $arguments)
    {
        $user = $arguments['user'];
        $pass = $arguments['password'];

        $service = \VMware_VCloud_SDK_Service::getService();

        try {
            $service->login(
                $this->hostname,
                array(
                    'username' => $user,
                    'password' => $pass,
                ),
                array(
                    'proxy_host' => null,
                    'proxy_port' => null,
                    'proxy_user' => null,
                    'proxy_password' => null,
                    'ssl_verify_peer' => false,
                    'ssl_verify_host' => false,
                    'ssl_cafile' => null,
                ),
                $this->apiVersion
            );
            return true;
        } catch (\VMware_VCloud_SDK_Exception $e) {
            echo $e;
            return false;
        }
    }
}
