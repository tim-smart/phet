<?php

//Load config and such
require_once 'config.inc.php';

require_once 'classes/phetServer.class.php';
require_once 'classes/phetClient.class.php';

// Default Modules
include_once 'modules/admin.module.php';
include_once 'modules/default.module.php';

// Create server instance
$phet = new PhetServer();
$phet->host = PHET_HOST;
$phet->webHost = PHET_WEBHOST;
$phet->port = PHET_PORT;
$phet->maxClients = PHET_MAXCLIENTS;

// Register default modules
$phet->registerModule('PhetModuleDefault');
$phet->registerModule('PhetModuleAdmin');

?>
