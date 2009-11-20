<?php

class PhetClientThread extends Thread {
	public function __construct( $handler, $client ) {
		$this->handler = $handler;
		$this->client = $client;
	}

	protected $handler;
	protected $client;

	protected function log( $message ) {
		echo '[' . date('Y-m-d H:i:s') . '] Client #' . $this->client->id . ': ' . $message . "\n";
	}

	protected function handleParent( $pid ) {
		if ( empty( $this->handler->requests[ $pid ] ) ) {
			$this->handler->requests[ $pid ] = $this->client->id;
			return;
		}

		$this->client->disconnect();
		$this->handler->removeClient( $this->client->id );
		unset( $this->handler->requests[ $pid ] );
		$this->log('Disconnected');
	}

	protected function handleChild() {
		socket_close( $this->handler->protocol->getSocket() );

		// Read from client
		$input = $this->client->read();
		if ( '' === $input ) {
			unset( $input );
			$this->disconnectClient();
		}

		// Trigger modules for input
		$this->handler->sendEvent( 'request', $input );

		$input = trim( $input );
	}

	protected function disconnectClient() {
		exit(1);
	}

	public function getClient() {
		return $this->client;
	}

	public function getHandler() {
		return $this->handler;
	}
}

?>
