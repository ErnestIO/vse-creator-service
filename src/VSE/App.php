<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace VSE;

/**
 * Class App
 */
class App
{
    /**
     * Get a VCloud Director Service object
     *
     * @param object $config Configuration.
     * @return \VMware_VCloud_SDK_Service
     * @throws \Exception If Error.
     */
    public static function getService($config)
    {
        $service = \VMware_VCloud_SDK_Service::getService();

        try {
            $service->login(
                $config["hostname"],
                array(
                    'username' => $config["username"] . '@' . $config["organisation"],
                    'password' => $config["password"],
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
                $config["apiversion"]
            );
        } catch (\VMware_VCloud_SDK_Exception $e) {
            throw new \Exception('Login failed');
        }

        return $service;
    }

    /**
     * Get all external network entities or external network entity with the
     * given name.
     *
     * @param string $name   Name of the external network. If null, returns all.
     * @param object $config Configuration.
     * @return array object Exernal Network.
     */
    public static function getExternalNetwork($name, $config)
    {
        $service = App::getService($config);
        $sdkAdminObj = $service->createSDKAdminObj();
        $externalNetwork = $sdkAdminObj->getExternalNetworks($name)[0];
        return $externalNetwork;
    }

    /**
     * Get references to admin organization entities.
     *
     * @param string $name   Name of the admin organization.
     * @param object $config Configuration.
     * @return \VMware_VCloud_API_AdminOrgType Admin Organization.
     * @throws \Exception If Error.
     */
    public static function getAdminOrganization($name, $config)
    {
        $service = App::getService($config);
        $sdkAdminObj = $service->createSDKAdminObj();
        $adminOrganizations = $sdkAdminObj->getAdminOrgs($name);
        if (empty($adminOrganizations)) {
            return null;
        }
        return $adminOrganizations[0];
    }

    /**
     * Get the Virtual Data Center admin object.
     *
     * @param string $adminOrg Admin organization.
     * @param string $name     Name of the data center.
     * @param object $config   Configuration.
     * @return mixed Admin Viriutal Data Center.
     * @throws \Exception If error.
     */
    public static function getAdminVDC($adminOrg, $name, $config)
    {
        $service = App::getService($config);
        // Get from the organization all the VDCs references
        // http://www.bitrefinery.com/vcloud/vcloudPHP-5.5.0/docs/VMware_VCloud_API/VMware_VCloud_API_ReferenceType.html
        $vdcs = $adminOrg->getVdcs()->getVdc();
        foreach ($vdcs as $key => $vdc) {
            if ($vdc->get_name() === $name) {
                // Get the final VDC by reference
                $vdcObj = $service->createSDKObj($vdcs[$key]);
                return $vdcObj;
            }
        }
        return null;
    }

    /**
     * Get the Edge Gateway.
     *
     * @param resource $adminVDC Admin virtual data center.
     * @param string   $name     Name.
     * @param object   $config   Configuration.
     * @return bool If the router is created or not.
     * @throws \Exception If creation fails.
     */
    public static function getEdgeGateway($adminVDC, $name, $config)
    {
        $edgeGateway = null;

        $service = App::getService($config);
        // Get from the VDC a list of references of Edge Gateways
        // http://www.bitrefinery.com/vcloud/vcloudPHP-5.5.0/docs/VMware_VCloud_API/VMware_VCloud_API_ReferenceType.html
        $edgeGatewayRefs = $adminVDC->getEdgeGatewayRefs($name);

        // Get the final Edge Gateway by reference
        if (!empty($edgeGatewayRefs)) {
            $edgeGateway =  $service->createSDKObj(current($edgeGatewayRefs))->getEdgeGateway();
        }
        return $edgeGateway;
    }


    /**
     * Get the Edge Gateway Object.
     *
     * @param resource $adminVDC Admin virtual data center.
     * @param string   $name     Name.
     * @param object   $config   Configuration.
     * @return bool If the router is created or not.
     * @throws \Exception If creation fails.
     */
    public static function getEdgeGatewayObj($adminVDC, $name, $config)
    {
        $edgeGateway = null;

        $service = App::getService($config);
        // Get from the VDC a list of references of Edge Gateways
        // http://www.bitrefinery.com/vcloud/vcloudPHP-5.5.0/docs/VMware_VCloud_API/VMware_VCloud_API_ReferenceType.html
        $edgeGatewayRefs = $adminVDC->getEdgeGatewayRefs($name);

        // Get the final Edge Gateway by reference
        if (!empty($edgeGatewayRefs)) {
            $edgeGateway =  $service->createSDKObj(current($edgeGatewayRefs));
        }
        return $edgeGateway;
    }


    /**
     * Create an EdgeGateway
     *
     * 2.1. Create Gateway
     * 2.2. Create Gateway Configuration
     * 2.3. Set Gateway Configuration
     * 2.4. Create Gateway Interface
     * 2.5. Set Gateway Interface to external network
     * 2.6. Set Interface type uplink
     * 2.7. Set interface use for default route true
     * 2.8 Set Gateway Interface to Gateway
     *
     * @param string $routerName      Name of the router.
     * @param string $externalNetwork External network.
     * @return \VMware_VCloud_API_GatewayType
     * @throws \Exception Error exception creating the router.
     */
    public static function createEdgeGateway($routerName, $externalNetwork)
    {
        try {
            // Create gateway
            $gateway = new \VMware_VCloud_API_GatewayType();
            $gateway->set_name($routerName);
            $gateway->setDescription($routerName);

            // Create a Gateway Configuration
            // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_API_GatewayConfigurationType.html
            $gatewayConfiguration = new \VMware_VCloud_API_GatewayConfigurationType();
            $gatewayConfiguration->setGatewayBackingConfig("full");

            // create an interface
            // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_API_GatewayInterfaceType.html
            $gatewayInterface = new \VMware_VCloud_API_GatewayInterfaceType();
            $gatewayInterface->setDisplayName("gateway interface");
            $gatewayInterface->setInterfaceType("uplink");
            $gatewayInterface->setUseForDefaultRoute(true);

            // add this interface to the public network
            $extNetRef = \VMware_VCloud_SDK_Helper::createReferenceTypeObj($externalNetwork->get_href());
            $gatewayInterface->setNetwork($extNetRef);

            // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_API_GatewayInterfacesType.html
            $gatewayInterfaces = new \VMware_VCloud_API_GatewayInterfacesType();
            $gatewayInterfaces->addGatewayInterface($gatewayInterface);

            // assign the interface to the gateway configuration
            $gatewayConfiguration->setGatewayInterfaces($gatewayInterfaces);

            // assign the configuration to the gateway
            $gateway->setConfiguration($gatewayConfiguration);

            return $gateway;
        } catch (\VMware_VCloud_SDK_Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
