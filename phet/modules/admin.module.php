<?php

/*
 * PhetModuleAdmin class: The admin module for phet server
 * phet
 */

class PhetModuleAdmin {
	// Called by the server
	public function run( $data, &$client, &$server ) {
		if ( false === empty( $data['HEAD'] ) )
			return;

		$input = trim( $data['raw'] );

		if ( true !== $client->get('admin') ) {
			if ( preg_match( '/login:(.*?)\|(.*)/', $input, $match ) ) {
				if ( PHET_ADMIN_USER === $match[1] && PHET_ADMIN_PASSWORD === $match[2] ) {
					$client->set( 'admin', true );
					$client->send('You are now logged in as admin' . "\n");
				} else {
					$this->disconnect( $client, $server );
					return;
				}
			} else {
				$this->disconnect( $client, $server );
				return;
			}
		}

		// The commands
		switch ( $input ) {
			case 'exit':
				$server->disconnectClient( $client );
				break;

			case 'kill':
				$server->stop();
				break;
		}

		// Send a message to other clients
		if ( preg_match( '/send:(.*)/', $input, $match ) )
			$server->send( $match[1] . "\n" );

		unset( $input, $match );
	}

	private function disconnect( &$client, &$server ) {
		$client->send('Not authorised to do that silly!' . "\n");
		$server->disconnectClient( $client );
	}
}

?>
