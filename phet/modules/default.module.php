<?php

class PhetModuleDefault {
	public function run( $data, &$client, &$server ) {
		if ( empty( $data['HEAD'] ) || empty( $data['GET']['module'] ) )
			return;

		if ( 'js' === $data['GET']['module'] ) {
			$client->send('HTTP/1.1 200 OK' . "\r\n" . 'Content-Type: application/x-javascript' . "\r\n\r\n" . file_get_contents( PHET_DIR . 'js/phet.js') );
			$server->disconnectClient( $client );
		}
	}
}

?>
