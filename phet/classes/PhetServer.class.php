<?php

class PhetServer {
	private $socket;
	private $sockets = array();
	private $activeSockets;

	public function __construct( $host, $port ) {
		// Connect to $host:$port
		$this->socket = socket_create( AF_INET, SOCK_STREAM, SOL_TCP );
		if ( !socket_bind( $this->socket, $host, $port ) )
			throw new RuntimeException('socket_bind() failed.');

		socket_set_option( $this->socket, SOL_SOCKET, SO_REUSEADDR, 1 );
		socket_listen( $this->socket );
		socket_set_nonblock( $this->socket );

		$this->sockets[0] = $this->socket;
	}

	public function cleanUp() {
		$this->activeSockets = null;
	}

	public function getSocket() {
		return $this->socket;
	}

	public function addSocket( $socket, $i ) {
		$this->sockets[ $i + 1 ] = $socket;
	}

	public function removeSocket( $i ) {
		unset( $this->sockets[ $i + 1 ] );
	}

	public function listen() {
		$null = null;
		$this->activeSockets = $this->sockets;
		$count = socket_select( $this->activeSockets, $null, $null, 0 );

		if ( 0 === $count )
			usleep(300);
		else if ( 0 < $count ) {
			unset( $null, $count );
			return true;
		} else
			throw new RuntimeException( 'Failed socket_select(). ' . socket_strerror( socket_last_error() ) );
	}

	public function getActiveSockets() {
		return $this->activeSockets;
	}

	public function disconnect() {
		$this->cleanUp();

		socket_shutdown( $this->socket, 1 );
		usleep(100);
		socket_shutdown( $this->socket, 0 );
		socket_close( $this->socket );
	}
}

?>
