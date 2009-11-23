<?php

define( 'PHET_ADMIN_USER', 'admin' );
define( 'PHET_ADMIN_PASSWORD', 'password' );

/*
 * PhetModuleAdmin class: The admin module for phet server
 * phet
 */

class PhetModuleAdmin extends PhetModule {
	public function __invoke( $event, $data ) {
		if ( $event !== 'RequestRaw' )
			return;

		if ( false === $data['client']->get( 'admin', false ) ) {
			if ( preg_match( '/login:(.*?)\|(.*)/', $data['request']->getBody(), $match ) ) {
				if ( PHET_ADMIN_USER === $match[1] && PHET_ADMIN_PASSWORD === $match[2] ) {
					$data['client']->set( 'admin', true );
					$data['client']->write('You are now logged in as ' . PHET_ADMIN_USER . "\n");
				} else {
					$data['client']->write('You need to login first' . "\n");
					$this->thread->disconnectClient( $data['client'] );
				}

			} else {
				$data['client']->write('You need to login first' . "\n");
				$this->thread->disconnectClient( $data['client'] );
			}

			unset( $match );
			return;
		}

		switch ( $data['request']->getBody() ) {
			case 'exit':
				$this->thread->disconnectClient( $data['client'] );
				return;

			case 'kill':
				$this->shutdown();
				return;

			case 'clients':
				$data['client']->write( var_export( $this->getClients(), true ) . "\n" );
				return;
		}

		// Send a message to other clients
		if ( preg_match( '/send:(.*)/', $data['request']->getBody(), $match ) )
			$this->thread->sendGlobalBuffer( $match[1] . "\n" );
		else if ( preg_match( '/kick:(.*)/', $data['request']->getBody(), $match ) )
			$this->thread->disconnectClient( (int)$match[1] );

		unset( $match );
	}
}

?>
