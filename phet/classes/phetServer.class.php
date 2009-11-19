<?php

/*
 * PhetServer class: main server class
 * phet
 *
 * @copyright  (c) 2009 Tim Smart
 * @license    MIT Style License. Copy of terms in LICENSE
 */

class PhetServer {
	public $host = 'localhost';
	public $webhost = 'localhost';
	public $port = 54321;
	public $maxClients = 20;

	private $running = false;
	private $socket;
	private $sockets = array();
	private $clients = array();
	private $modules = array();

	public function isRunning() {
		return $this->running;
	}

	public function getClients() {
		return $this->clients;
	}

	private function log( $message, $priority = 3 ) {
		echo $message . "\n";
	}

	public function registerModule( $className ) {
		try {
			$this->modules[ $className ] = new $className();
		} catch ( Exception $error ) {
			$this->log( 'Error loading module ' . $className . ': ' . $error->getMessage() );
		}
	}

	public function start() {
		$this->log('Starting phet...');

		// Create the socket and bind to set host and port
		$this->socket = socket_create( AF_INET, SOCK_STREAM, SOL_TCP );
		socket_bind( $this->socket, $this->host, $this->port ) or die( 'Could not bind to ' . $this->host . ':' . $this->port );
		socket_set_option( $this->socket, SOL_SOCKET, SO_REUSEADDR, 1 );

		$this->log( 'phet started on: ' . $this->host . ':' . $this->port );

		// Set running flag to true and start listening
		$this->running = true;
		$this->listen();
	}

	private function listen() {
		socket_listen( $this->socket );
		$this->log('phet started listening');

		// A placeholder for NULL
		$null = NULL;

		socket_set_block( $this->socket );
		while ( $this->running ) {
			// Reset sockets read list
			unset( $this->sockets );
			$this->sockets = array();
			$this->sockets[0] = $this->socket;

			// Re-add client sockets to read list
			reset( $this->clients );
			foreach ( $this->clients as &$client )
				if ( NULL !== $client->socket )
					$this->sockets[ $client->id + 1 ] = &$client->socket;
			unset( $client );

			// Pause execution until we have activity.
			$count = socket_select( $this->sockets, $null, $null, $null );

			// Check if we have a new client
			if ( isset( $this->sockets[0] ) && $this->socket === $this->sockets[0] ) {
				// Are we full?
				if ( $this->maxClients <= count( $this->clients ) )
					$this->log('Connection rejected: Too many clients.');
				else {
					for ( $i = 0; $i < $this->maxClients; $i++ ) {
						if ( false === array_key_exists( $i, $this->clients ) ) {
							// Create the client
							$this->clients[ $i ] = new PhetClient( $i, $this );

							// Assign the client socket
							if ( false === ( $this->clients[ $i ]->socket = socket_accept( $this->socket ) ) ) {
								unset( $this->clients[ $i ] );
								$this->log( 'Error performing socket_accept(): ' . socket_strerror( socket_last_error() ) );
							}
							$this->clients[ $i ]->retrieveInfo();

							// Log it!
							$this->log('Client #' . $i . ' connected');

							// Run any hooks
							foreach ( $this->modules as &$module ) {
								if ( method_exists( $module, 'onClientConnect' ) ) {
									try {
										$module->onClientConnect( $this->clients[ $i ], $this );
									} catch ( Exception $error ) {}
								}
							}
							unset( $module );
							break;
						}
					}
					unset( $i );
				}

				// Do we have other stuff to deal with?
				if ( 1 >= $count ) {
					unset( $count );
					continue;
				}

				// Doesn't apply from now on
				unset( $this->sockets[0] );
			}
			unset( $count );

			// Recieving data!
			$keys = array_keys( $this->sockets );
			foreach ( $keys as $key ) {
				$key = $key - 1;

				// Do the IO
				$this->log( 'Request recieved from client #' . $key );
				$this->processRequest( $this->clients[ $key ] );
			}
			unset( $keys, $key );
		}
		
		unset( $null );
	}

	private function processRequest( &$client ) {
		if ( false === ( $input = $this->readFromClient( $client ) ) )
			return;

		$data = $this->parseRequest( $input );

		foreach ( $this->modules as $name => &$module ) {
			if ( method_exists( $module, 'run' ) )
				$module->run( $data, $client, $this );
		}
		unset( $input, $data, $module, $name );
	}

	private function parseRequest( $input ) {
		$data = array(
			'GET'	=>	NULL,
			'HEAD'	=>	NULL,
			'body'	=>	NULL,
			'raw'	=>	$input
		);

		if ( preg_match( '/GET (?:\/\?(.*?)|.*?) HTTP\/1\.[0-9]/', substr( $input, 0, strpos( $input, "\r\n" ) ), $match ) ) {
			$split = strpos( $input, "\r\n\r\n" );

			if ( false === empty( $match[1] ) )
				parse_str( $match[1], $data['GET'] );

			$data['HEAD'] = $this->parseHeaders( substr( $input, 0, $split ) );
			$data['body'] = substr( $input, $split + 4 );
		}
		unset( $match, $split );

		return $data;
	}

	// Found function on http_parse_headers() documentation page
	private function parseHeaders( $header ) {
		// Have we got the native function?
		if ( function_exists('http_parse_headers') )
			return http_parse_headers( $header );

		$retVal = array();

		$fields = explode( "\r\n", preg_replace( '/\x0D\x0A[\x09\x20]+/', ' ', $header ) );
		foreach ( $fields as $field ) {
			if( preg_match( '/([^:]+): (.+)/m', $field, $match ) ) {
				$match[1] = preg_replace( '/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower( trim($match[1] ) ) );

				if( isset( $retVal[ $match[1] ] ) ) {
					$retVal[ $match[1] ] = array( $retVal[ $match[1] ], $match[2] );
				} else {
					$retVal [$match[1] ] = trim( $match[2] );
				}
			}
		}
		return $retVal;
	}

	// Sends a message to all or specified clients
	public function send( $message, $whitelist = NULL, $blacklist = NULL ) {
		reset( $this->clients);

		if ( is_array( $whitelist ) && is_array( $blacklist ) ) {
			foreach( $this->clients as $id => &$client )
				if ( in_array( $id, $whitelist ) && false === in_array( $id, $blacklist ) )
					$this->writeToClient( $client, $message );

		} else if ( is_array( $blacklist ) ) {
			foreach( $this->clients as $id => &$client )
				if ( false === in_array( $id, $blacklist ) )
					$this->writeToClient( $client, $message );

		} else if ( is_array( $whitelist ) ) {
			foreach( $whitelist as $id )
				if ( isset( $this->clients[ $id ] ) )
					$this->writeToClient( $this->clients[ $id ], $message );
		
		} else {
			foreach( $this->clients as &$client )
				$this->writeToClient( $client, $message );
		}
		unset( $client, $id );
	}

	public function writeToClient( &$client, $body ) {
		$sent = 0;
		$length = strlen( $body );
		while ( $length > $sent ) {
			$success = @socket_write( $client->socket, substr( $body, $sent ), $length - $sent );

			if ( false !== $success )
				$sent += $success;
			else {
				$this->log( 'Error writing data to client #' . $client->id . ': ' . socket_strerror( socket_last_error() ) );
				break;
			}
		}
		unset( $sent, $length, $success );
	}
	
	public function readFromClient( &$client ) {
		socket_set_nonblock( $client->socket );

		$buffer = NULL;
		$input = '';

		while ( true ) {
			$buffer = @socket_read( $client->socket, 1024 );

			if ( false === $buffer )
				break;
			else if ( '' === $buffer ) {
				$this->disconnectClient( $client );
				unset( $buffer );
				return false;
			}

			$input = $input . $buffer;
		}
		unset( $buffer );

		socket_set_block( $client->socket );

		if ( '' !== $input )
			return $input;
		else
			return false;
	}

	public function stop() {
		foreach ( $this->modules as $name => &$module ) {
			if ( method_exists( $module, 'onServerDisconnect' ) )
				$module->onServerDisconnect( $this );
		}

		$this->running = false;

		socket_shutdown( $this->socket, 1 );
		usleep( 500 );
		socket_shutdown( $this->socket, 0 );
		socket_close( $this->socket );

		$this->socket = NULL;
		$this->log( 'phet stopped listening on: ' . $this->host . ':' . $this->port );
	}

	public function disconnectClient( &$client ) {
		$client->disconnect();
		$this->log( 'Client #' . $client->id . ' disconnected.' );

		foreach ( $this->modules as &$module ) {
			if ( method_exists( $module, 'onClientDisconnect' ) ) {
				try {
					$module->onClientDisconnect( $client, $this );
				} catch ( Exception $error ) {}
			}
		}

		unset( $this->clients[ $client->id ], $module );
	}
}

?>
