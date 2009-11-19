<?php

/*
 * PhetClient class: Manages all the client specific logic
 * phet
 *
 * @copyright  (c) 2009 Tim Smart
 * @license    MIT Style License. Copy of terms in LICENSE
 */

class PhetClient {
	public $socket;
	public $ipAddress;
	public $id;

	private $active = true;
	private $data = array();
	private $server;

	function __construct( $id, &$server ) {
		$this->id = $id;
		$this->server = &$server;
	}

	public function get( $key, $default = NULL ) {
		if ( isset( $this->data[ $key] ) )
			return $this->data[ $key ];
		else
			return $default;
	}

	public function set( $key, $value ) {
		$this->data[ $key ] = $value;
	}

	public function isActive() {
		return $this->active;
	}

	public function retrieveInfo() {
		socket_getpeername( $this->socket, $this->ipAddress );
	}

	public function send( $body ) {
		$this->server->writeToClient( $this, $body );
	}

	public function disconnect() {
		socket_close( $this->socket );
		$this->active = false;
	}
}

?>
