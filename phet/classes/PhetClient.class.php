<?php

/*
 * PhetClient class: Manages all the client specific logic
 * phet
 *
 * @copyright  (c) 2009 Tim Smart
 * @license    MIT Style License. Copy of terms in LICENSE
 */

class PhetClient {
	private $socket;
	private $handle;
	private $request;
	public $info = array();

	function __construct( &$socket ) {
		$this->socket = &$socket;
	}

	public function read() {
		socket_set_nonblock( $this->socket );

		$buffer = NULL;
		$input = '';

		while ( true ) {
			$buffer = @socket_read( $this->socket, 1024 );

			if ( false === $buffer || '' === $buffer )
				break;

			$input = $input . $buffer;
		}
		unset( $buffer );

		return $input;
	}

	public function disconnect() {
		socket_shutdown( $this->socket, 2 );
		socket_close( $this->socket );
	}
}

?>
