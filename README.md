# Welcome to the phet PHP Comet Server
This server utilises the socket functionality of PHP to create a scalable PHP Comet server.

## Usage
Have a look at example.php to see the most basic usage. You can extend phet by registering modules with a server instance.
Achieve this buy creating a class with the method `run`, `onClientConnect` or `onClientDisconnect`. You can then use the servers `registerModule` method to register the module with the server. E.g.

	require_once 'phet/phet.inc.php';
	
	class MyModule {
		public function run( $data, &$client, &$server ) {
			// Use the passed variables to perform you actions
		}
		
		public function onClientConnect( &$client, &$server ) {
			// A client has connected
		}
		
		public function onClientDisconnect( &$client, &$server ) {
			// A client has disconnected
		}
	}
	
	$phet->registerModule('MyModule');
	
	$phet->start();

Some examples of modules can be found in phet/modules/
