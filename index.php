
<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once __DIR__ . '/vendor/autoload.php';

use VSE\App;

$app = new \Slim\Slim(array(
    'log.enabled' => \Slim\Log::DEBUG
));

$app->error(function (\Exception $e) use ($app) {
    var_dump("ERROR");
    var_dump($e);
});

$app->add(new \Slim\Middleware\HttpBasicAuthentication(
    [
        "path" => "/router",
        "realm" => "Protected",
        "authenticator" => new \VSE\VCloudAuthenticator(),
        "error" => function ($arguments) use ($app) {
            $response["status"] = "error";
            $response["message"] = $arguments["message"];
            $app->response->write(json_encode($response, JSON_UNESCAPED_SLASHES));
        }
    ]
    ));

$app->get('/', 'defaultRoute');
$app->get('/teapot', 'teaPotRoute');
$app->get('/status', 'statusRoute');
$app->post('/router', 'addRouterRoute');
$app->delete('/router', 'deleteRouterRoute');

/**
 * Run the application
 */
$app->run();

/**
 * defaultRoute '/'
 */
function defaultRoute()
{
    echo "This aren't the droids you're looking for. Go away.";
}

/**
 * defaultRoute '/teapot'
 */
function teaPotRoute()
{
    $app = \Slim\Slim::getInstance();

    $app->halt(418, "I'm a teapot");
}

/**
 * statusRoute '/status'
 *
 * @throws Exception
 */
function statusRoute()
{
    $app = \Slim\Slim::getInstance();

    $config = \VSE\Utils::loadConfiguration("config.json");

    $app->getLog()->debug("Loaded configuration.");

    try {
        $service = App::getService($config);
        echo "It's alive!";
    } catch (\Exception $e) {
        $app->halt(500, "Can't connect to vCloud.");
    }
}

/**
 * addRouterRoute '/route'
 */
function addRouterRoute() {

    $app = \Slim\Slim::getInstance();

    $config = \VSE\Utils::loadConfiguration("config.json");

    $app->getLog()->debug("Loaded configuration.");

    // get the payload
    $json = $app->request->getBody();
    $data = json_decode($json, true);

    // Validate input payload data
    if (!array_key_exists("vdc-name", $data)) {
        $res = array("router_ip" => "", "error" => array("code" => "400", "message" => "Payload syntax error. Virtual Data Center name is not present."));
        $app->halt(400, json_encode($res));
    }
    $vdcName = $data["vdc-name"];

    if (!array_key_exists("org-name", $data)) {
        $res = array("router_ip" => "", "error" => array("code" => "400", "message" => "Payload syntax error. Organisation Name is not present."));
        $app->halt(400, json_encode($res));
    }
    $orgName = $data["org-name"];

    // CAR-VCLOUD-EXT-667
    if (!array_key_exists("external-network", $data)) {
        $res = array("router_ip" => "", "error" => array("code" => "400", "message" => "Payload syntax error. External Network is not present."));
        $app->halt(400, json_encode($res));
    }
    $externalNetworkName = $data["external-network"];

    if (!array_key_exists("router-name", $data)) {
        $res = array("router_ip" => "", "error" => array("code" => "400", "message" => "Payload syntax error. Router Name is not present."));
        $app->halt(400, json_encode($res));
    }
    $routerName = $data["router-name"];

    $app->getLog()->debug("Payload validated.");

    // Get the admin org
    // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_API_AdminOrgType.html
    $adminOrg = App::getAdminOrganization($orgName, $config);
    if (is_null($adminOrg)) {
        $res = array("router_ip" => "", "error" => array("code" => "400", "message" => "ERROR: Organization " . $orgName . " does not exist"));
        $app->halt(400, json_encode($res));
    }
    $app->getLog()->debug("Got the organization. " . $adminOrg->get_name());

    // Get the admin virtual data center
    // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_SDK_AdminVdc.html
    $adminVDC = App::getAdminVDC($adminOrg, $vdcName, $config);
    if (is_null($adminVDC)) {
        $res = array("router_ip" => "", "error" => array("code" => "400", "message" => "ERROR: VDC " . $vdcName . " does not exist"));
        $app->halt(400, json_encode($res));
    }
    $app->getLog()->debug("Got the virtual data center: " . $adminVDC->getId());

    // Check for an already created gateway with the same name on the same virtual data center
    // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_API_GatewayType.html
    $existingGateway = App::getEdgeGateway($adminVDC, $routerName, $config);
    if (isset($existingGateway)) {
        $res = array("router_ip" => "", "error" => array("code" => "400", "message" => "ERROR: edgeGateway " . $vdcName . " already exists"));
        $app->halt(400, json_encode($res));
    }
    $app->getLog()->debug("The router is not created yet.");

    // Get the external network
    // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_API_ExternalNetworkType.html
    $externalNetwork = App::getExternalNetwork($externalNetworkName, $config);
    if (is_null($adminOrg)) {
        $res = array("router_ip" => "", "error" => array("code" => "400", "message" => "ERROR: External Network " . $externalNetworkName . " does not exist"));
        $app->halt(400, json_encode($res));
    }
    $app->getLog()->debug("Got the external network: " . $externalNetwork->get_name());


    // Create the router on this external network
    // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_API_GatewayType.html
    $edgeGateway = App::createEdgeGateway($routerName, $externalNetwork);

    // Add the router to the data center
    // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_API_GatewayType.html

    $edgeGateway = $adminVDC->createEdgeGateways($edgeGateway);

    // Get active tasks on this edge gateway
    // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_API_TasksInProgressType.html
    $tasksInProgress = $edgeGateway->getTasks();
    if (!is_null($tasksInProgress)) {
        $tasks = $tasksInProgress->getTask();
        if (!empty($tasks)) {
            // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_API_TaskType.html
            foreach ($tasks as $task) {
                $app->getLog()->debug("Wait for task : " . $task->get_name());
                $service = App::getService($config);
                // Wait until finishes
                $task = $service->waitForTask($task);
                if ($task->get_status() != 'success') {
                    $app->getLog()->error('Failed to create an edge Gateway:' . $routerName);
                    $res = array("router_ip" => "", "error" => array("code" => "500", "message" => 'Failed to create an edge Gateway: ' . $routerName));
                    $app->halt(500, json_encode($res));
                }
            }
        }
    } else {
        $app->getLog()->debug("No tasks in progress for the edge gateway: " . $routerName);
    }


    $edgeGateway = App::getEdgeGateway($adminVDC, $routerName, $config);

    // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_API_GatewayConfigurationType.html
    $edgeGatewayConfiguration = $edgeGateway->getConfiguration();

    // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_API_GatewayInterfacesType.html
    $gatewayInterfaces = $edgeGatewayConfiguration->getGatewayInterfaces();
    $interfaces = $gatewayInterfaces->getGatewayInterface();
    if (!empty($interfaces)) {
        // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_API_GatewayInterfaceType.html

        $subnetParticipations = $interfaces[0]->getSubnetParticipation();
        if (!empty($subnetParticipations)) {
            // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_API_SubnetParticipationType.html
            $ip = $subnetParticipations[0]->getIpAddress();
            $app->getLog()->debug($ip);

            // Allocate IP Address
            // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_API_SubnetParticipationType.html
            $subnetParticipation = new VMware_VCloud_API_SubnetParticipationType();
            $subnetParticipation->setGateway($subnetParticipations[0]->getGateway());
            $subnetParticipation->setNetmask("255.255.255.0");

            // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_API_IpRangesType.html
            $ipRanges = new VMware_VCloud_API_IpRangesType();
            // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_API_IpRangeType.html
            $ipRange = new VMware_VCloud_API_IpRangeType();
            $ipRange->setStartAddress($ip);
            $ipRange->setEndAddress($ip);
            $ipRanges->addIpRange($ipRange);

            $subnetParticipation->setIpRanges($ipRanges);



            $interfaces[0]->addSubnetParticipation($subnetParticipation);

            $gatinfaces = new VMware_VCloud_API_GatewayInterfacesType();
            $gatinfaces->addGatewayInterface($interfaces[0]);

            $edgeGatewayConfiguration->setGatewayInterfaces($gatinfaces);

            $app->getLog()->debug($edgeGatewayConfiguration->export());

            $newEdgeGateway = new VMware_VCloud_API_GatewayType();
            $newEdgeGateway->set_name($routerName);
            $newEdgeGateway->setConfiguration($edgeGatewayConfiguration);

            //$app->getLog()->debug($newEdgeGateway->export());

            $sdkEdgeGateway = App::getEdgeGatewayObj($adminVDC, $routerName, $config);

            $service = App::getService($config);

            $task = $sdkEdgeGateway->modify($newEdgeGateway);
            $task = $service->waitForTask($task);
            if ($task->get_status() != 'success')
            {
                $app->getLog()->error('Could not sub-allocate IP on Gateway: ' . $routerName);
                $res = array("router_ip" => "", "error" => array("code" => "500", "message" => 'Could not sub-allocate IP on Gateway: ' . $routerName));
                $app->halt(500, json_encode($res));
            }


            $result = [ "router_ip" => $ip ];
            $response = $app->response;
            $response['Content-Type'] = 'application/json';
            $response->body( json_encode($result) );
        } else {
            $app->getLog()->error('edge Gateway is not present in any subnet: ' . $routerName);
            $res = array("router_ip" => "", "error" => array("code" => "500", "message" => 'edge Gateway is not present in any subnet: ' . $routerName));
            $app->halt(500, json_encode($res));
        }
    } else {
            $app->getLog()->error('There is no interfaces in edge Gateway: ' . $routerName);
            $res = array("router_ip" => "", "error" => array("code" => "500", "message" => 'There is no interfaces in edge Gateway: ' . $routerName));
            $app->halt(500, json_encode($res));
    }
}

/**
 * deleteRouterRoute '/router'
 *
 * @throws Exception
 */
function deleteRouterRoute()
{
    $app = \Slim\Slim::getInstance();

    $config = \VSE\Utils::loadConfiguration("config.json");

    $app->getLog()->debug("Loaded configuration.");

    // get the payload
    $json = $app->request->getBody();
    $data = json_decode($json, true);

    // Validate input payload data
    if (!array_key_exists("vdc-name", $data)) {
        $app->halt('400', "Payload syntax error. Virtual Data Center name is not present.");
    }
    $vdcName = $data["vdc-name"];

    if (!array_key_exists("org-name", $data)) {
        $app->halt('400', "Payload syntax error. Organisation Name is not present.");
    }
    $orgName = $data["org-name"];

    if (!array_key_exists("router-name", $data)) {
        $app->halt('400', "Payload syntax error. Router Name is not present.");
    }
    $routerName = $data["router-name"];

    $app->getLog()->debug("Payload validated.");

    // Get the admin org
    // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_API_AdminOrgType.html
    $adminOrg = App::getAdminOrganization($orgName, $config);
    if (is_null($adminOrg)) {
        $app->halt(400, "ERROR: Organization " . $orgName . " does not exist");
    }
    $app->getLog()->debug("Got the organization. " . $adminOrg->get_name());

    // Get the admin virtual data center
    // http://purple-dbu.github.io/vmware-vcloud-sdk-php-patched/VMware_VCloud_SDK_AdminVdc.html
    $adminVDC = App::getAdminVDC($adminOrg, $vdcName, $config);
    if (is_null($adminVDC)) {
        $app->halt(400, "ERROR: VDC " . $vdcName . " does not exist");
    }
    $app->getLog()->debug("Got the virtual data center: " . $adminVDC->getId());

    // Delete the router
    $edgeGateway = App::getEdgeGatewayObj($adminVDC, $routerName, $config);
    if (is_null($edgeGateway)) {
        $app->halt(400, "ERROR: Edge Gateway " . $routerName . " does not exist");
    }

    $app->getLog()->debug("Got the edge gateway: " . $edgeGateway->getEdgeGateway()->get_id());
    $app->getLog()->debug("Got the edge gateway: " . $edgeGateway->getEdgeGateway()->get_name());

    $task = $edgeGateway->delete();

    $app->getLog()->debug("Edge Gateway deleted.");

    return null;
}
