<?php

/*
 * PhetClient class: Manages all the client specific logic
 * phet
 *
 * @copyright  (c) 2009 Tim Smart
 * @license    MIT Style License. Copy of terms in LICENSE
 */

class PhetClient {
	public $id;
	private $socket;
	private $handle;
	private $request;
	private $thread;
	private $handler;

	function __construct( $handler, $thread, &$socket, $id ) {
		$this->socket = &$socket;
		$this->thread = $thread;
		$this->handler = $handler;
		$this->id = $id;

		socket_set_nonblock( $this->socket );

		$this->handler->sendEvent( 'ClientConnect', $this );
		$this->log('Connected');
	}

	public function log( $message ) {
		$this->thread->log('Client #' . $this->id . ': ' . $message );
	}

	public function get( $key, $default = null ) {
		$clients = $this->handler->cache->get( 'clients', array() );

		if ( empty( $clients[ $this->id ]['data'][ $key ] ) )
			$ret = $default;
		else
			$ret = $clients[ $this->id ]['data'][ $key ];

		unset( $clients );
		return $ret;
	}

	public function set( $key, $value ) {
		$clients = $this->handler->cache->get( 'clients', array() );

		$clients[ $this->id ]['data'][ $key ] = $value;
		$this->handler->cache->set( 'clients', $clients );
		unset( $clients );

		return true;
	}

	public function read() {
		$buffer = NULL;
		$input = '';

		while ( true ) {
			$buffer = @socket_read( $this->socket, 1024 );

			if ( false === $buffer || '' === $buffer )
				break;

			$input .= $buffer;
		}
		unset( $buffer );

		$this->log('Request recieved');
		return $input;
	}

	public function write( $body ) {
		$sent = 0;
		$length = strlen( $body );
		while ( $length > $sent ) {
			$success = @socket_write( $this->socket, substr( $body, $sent ), $length - $sent );

			if ( false !== $success )
				$sent += $success;
			else {
				$this->log( 'Error writing data: ' . socket_strerror( socket_last_error() ) );
				break;
			}
		}
		unset( $sent, $length, $success );
	}

	public function disconnect() {
		socket_close( $this->socket );

		$this->handler->sendEvent( 'ClientDisconnect', $this );
		$this->log('Disconnected');
	}
}

?>
