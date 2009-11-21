<?php

class PhetThread extends Thread {
	public function __construct( $handler, $processId ) {
		$this->handler = $handler;
		$this->id = $processId;
	}

	private $handler;
	private $clients;
	private $id;
	private $pid;

	public function log( $message ) {
		$this->handler->log( 'Process #' . $this->id . ': ' . $message );
	}

	public function exit() {
		$this->removeSignalHandlers();
		socket_close( $this->handlers->protocol->getSocket() );
		exit();
	}

	public function sendGlobalBuffer( $data, $client ) {
		$processes = $this->handler->cache->get( 'processes', array() );

		foreach ( $processes as $process )
			if ( $this->pid !== $process['pid'] ) {
				$process['buffer'][] = $data;
				posix_kill( $process['pid'], SIGUSR1 );
			}

		$this->handler->cache->set( 'processes', $processes );
		unset( $processes, $process );

		$this->writeGlobalBuffer( $data, $client->id );
	}

	private function handleGlobalBuffer() {
		
	}

	private function writeGlobalBuffer( $data, $clientId = null ) {
		foreach ( $this->clients as $client )
			if ( $clientId !== $client->id )
	}

	public function sendBatchBuffer( $clients ) {
		$clients = $this->handler->cache->get( 'clients', array() );
		// code...
	}

	public function sendClientBuffer() {
		// TODO: Check if client is on this thread. If not check memcache
		// TODO: 
	}

	private function handleParent( $childPid ) {
		$this->handler->registerProcess( $this->id, $childPid );
	}

	private function handleChild( $parentPid ) {
		$this->pid = posix_getpid();
		$this->handler->cleanUpChild();

		$processes = $this->handler->cache->get( 'processes', array() );
		$processes[ $this->id ] = array(
			'pid'		=>	$this->pid,
			'buffer'	=>	array()
		);
		$this->handler->cache->set( 'processes', $processes );
		unset( $processes );

		// Start serving
		while ( $this->handler->protocol->listen() ) {
			$sockets = $this->handler->protocol->getActiveSockets();

			if ( isset( $sockets[0] ) ) {
				
			}
		}

		$this->exit();
	}

	private function registerSignalHandlers() {
		pcntl_signal( SIGHUP, array( &$this, 'handleSignal' ) );
		pcntl_signal( SIGTERM, array( &$this, 'handleSignal' ) );
		pcntl_signal( SIGUSR1, array( &$this, 'handleSignal' ) );
		pcntl_signal( SIGUSR2, array( &$this, 'handleSignal' ) );
		pcntl_signal( 50, array( &$this, 'handleSignal' ) );
		pcntl_signal( 51, array( &$this, 'handleSignal' ) );
	}

	private function removeSignalHandlers() {
		pcntl_signal( SIGHUP, SIG_IGN );
		pcntl_signal( SIGTERM, SIG_IGN );
		pcntl_signal( SIGUSR1, SIG_IGN );
		pcntl_signal( SIGUSR2, SIG_IGN );
		pcntl_signal( 50, SIG_IGN );
		pcntl_signal( 51, SIG_IGN );
	}

	private function handleSignal( $signal ) {
		switch ( $signal ) {
			case SIGHUP:
			case SIGTERM:
				$this->exit();

			// Start listening for new clients
			case SIGUSR1:
				$this->serveNewClients();
				break;

			// Global write
			case SIGUSR2:
				$this->handleGlobalBuffer();
				break;

			// Batch write
			case 50:
				$this->handleBatchBuffer();
				break;

			// Client write
			case 51:
				$this->handleClientBuffer();
				break;
		}
	}
}

?>
