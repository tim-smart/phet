<?php

class PhetServer {
	private $socket;
	private $sockets = array();
	private $activeSockets;
	private $listening = false;

	public function __construct( $host, $port ) {
		// Connect to $host:$port
		$this->socket = socket_create( AF_INET, SOCK_STREAM, SOL_TCP );
		if ( !socket_bind( $this->socket, $host, $port ) )
			throw new RuntimeException('socket_bind() failed.');
	}

	public function init() {
		socket_set_option( $this->socket, SOL_SOCKET, SO_REUSEADDR, 1 );
		socket_listen( $this->socket );
		socket_set_nonblock( $this->socket );

		$this->sockets[0] = $this->socket;
		$this->listening = true;
	}

	public function cleanUp() {
		$this->activeSockets = null;
	}

	public function getSocket() {
		return $this->socket;
	}

	public function getSockets() {
		return $this->sockets;
	}

	public function addSocket( &$socket, $i ) {
		$this->sockets[ $i + 1 ] = &$socket;
	}

	public function acceptNewClients() {
		$this->sockets[0] = $this->socket;
	}

	public function removeSocket( $i ) {
		unset( $this->sockets[ $i + 1 ] );
	}

	public function listen() {
		$null = null;
		while ( $this->listening ) {
			pcntl_signal_dispatch();

			$this->activeSockets = $this->sockets;
			$count = socket_select( $this->activeSockets, $null, $null, 0 );

			if ( 0 === $count )
				usleep(500);
			else if ( 0 < $count ) {
				unset( $null, $count );
				return true;
			} else
				throw new RuntimeException( 'Failed socket_select(). ' . socket_strerror( socket_last_error() ) );
		}

		return false;
	}

	public function getActiveSockets() {
		return $this->activeSockets;
	}

	public function stop() {
		$this->cleanUp();
		$this->listening = false;

		socket_close( $this->socket );
	}

	public function disconnect() {
		$this->cleanUp();

		socket_shutdown( $this->socket, 2 );
		socket_close( $this->socket );
	}
}

?>
