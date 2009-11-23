<?php

// Load config and such
require_once 'config.inc.php';

require 'classes/PhetHandler.class.php';
require 'classes/PhetServer.class.php';
require 'classes/PhetCache.shm.class.php';
require 'classes/Thread.class.php';
require 'classes/PhetThread.class.php';
require 'classes/PhetClient.class.php';
require 'classes/PhetRequest.class.php';
require 'classes/PhetRequestGet.class.php';
require 'classes/PhetRequestRaw.class.php';

// phet is a instance of PhetHandler
$phet = new PhetHandler( array(
	'host'				=>	PHET_HOST,
	'port'				=>	PHET_PORT,
	'maxThreadClients'	=>	PHET_MAXTHREADCLIENTS,
	'threads'		=>	PHET_THREADS,
	'cache_host'		=>	MEMCACHE_HOST,
	'cache_port'		=>	MEMCACHE_PORT
) );

?>
