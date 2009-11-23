#!/opt/lampp/bin/php -q
<?php

require 'phet/phet.inc.php';

require 'phet/modules/admin.module.php';
require 'phet/modules/comet.module.php';
require 'phet/modules/test.module.php';

$phet->registerModule( new PhetModuleAdmin() );
$phet->registerModule( new PhetModuleComet() );
$phet->registerModule( new PhetModuleTest() );

$phet->serve();

?>
