<?php

// Load config and such
require_once 'config.inc.php';

require 'classes/PhetHandler.class.php';
require 'classes/PhetServer.class.php';
require 'classes/PhetCache.shmop.class.php';
require 'classes/Thread.class.php';

// phet is a instance of PhetHandler
$phet = new PhetHandler( array(
	'host'				=>	PHET_HOST,
	'port'				=>	PHET_PORT,
	'maxThreadClients'	=>	PHET_MAXCLIENTS,
	'maxThreads'		=>	PHET_MAXTHREADS,
	'cache_host'		=>	MEMCACHE_HOST,
	'cache_port'		=>	MEMCACHE_PORT
) );

?>
