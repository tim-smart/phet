<?php

final class PhetHandler {
	public function __construct( $data ) {
		if ( PHP_SAPI !== 'cli' )
			throw new LogicException("phet should be run using CLI SAPI");
 
		if ( version_compare( '5.3.0-dev', PHP_VERSION, '>' ) )
			throw new LogicException("phet requires PHP 5.3.0+");

		$this->data = $data;

		$this->protocol = new PhetServer( $data['host'], $data['port'] );

		$this->log( 'phet Handler initialized @ [' . $data['host'] . ':' . $data['port'] . ']' );

		unset( $data );
	}

	private $modules = array();
	private $pid;
	private $clients = array();

	protected function log( $message ) {
		echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
	}

	public function getClients() {
		return $this->clients;
	}

	public function removeClient( $i ) {
		unset( $this->clients[ $i ] );
		$this->protocol->removeSocket( $i );
	}

	public function serve() {
		// Go daemon
		// $this->daemonise();
		$this->registerSignalHandlers();

		$this->log('phet is now listening...');
		try {
			while( $this->protocol->listen() ) {
				$activeSockets = $this->protocol->getActiveSockets();

				if ( isset( $activeSockets[0] ) ) {
					$this->registerClient( $activeSockets[0] );
					unset( $activeSockets[0] );
				}

				foreach ( $activeSockets as $i => $socket ) {
					$client = $this->clients[ $i - 1 ];

					$input = $client->read();
					$request = $this->
				}

				$this->protocol->cleanUp();
				unset( $activeSockets, $i, $socket );
			}
		} catch ( Exception $error ) {
			$this->protocol->disconnect();
			$this->log( 'Exception: ' . $error->getMessage() );
		}
	}

	protected function registerClient( $socket ) {
		if ( $this->data['maxClients'] <= count( $this->clients ) ) {
			$this->log('Client rejected, maxClients reached.');
			return;
		}

		for ( $i = 0; $i < $this->data['maxClients']; $i++ ) {
			if ( empty( $this->clients[ $i ] ) ) {
				if ( false === ( $socket = socket_accept( $socket ) ) )
					throw new RuntimeException( 'Failed to call socket_accept(). ' . socket_strerror( socket_last_error() ) );

				$this->log('Client #' . $i . ' connected.');
				$this->clients[ $i ] = new PhetClient( $socket );
				$this->clients[ $i ]->id = $i;

				$this->protocol->addSocket( $socket, $i );

				unset( $socket, $i );
				break;
			}
		}
	}

	public function registerModule() {
		// Register module
	}

	public function sendEvent() {
		// code...
	}
}

?>
