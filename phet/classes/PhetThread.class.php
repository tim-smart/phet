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
	private $waitingForSignal = false;

	public function log( $message ) {
		$this->handler->log( 'Process #' . $this->id . ': ' . $message );
	}

	private function shutdown() {
		$this->handler->protocol->stop();
		$this->log('Disconnected');
		exit();
	}

	public function sendGlobalBuffer( $data, $clientId = null ) {
		$processes = $this->handler->cache->get( 'processes', array() );

		foreach ( $processes as &$process )
			if ( $this->pid !== $process['pid'] ) {
				$process['globalBuffer'][] = $data;
			}

		$this->handler->cache->set( 'processes', $processes );

		for ( $i = 0; $i < $this->handler->data['threads']; $i++ )
			if ( $this->pid !== $processes[ $i ]['pid'] )
				posix_kill( $processes[ $i ]['pid'], SIGUSR2 );

		unset( $processes, $process );

		$this->writeGlobalBuffer( $data, $clientId );
	}

	private function handleGlobalBuffer() {
		$processes = $this->handler->cache->get( 'processes', array() );

		if ( isset( $processes[ $this->id ] ) )
			foreach ( $processes[ $this->id ]['globalBuffer'] as $data )
				$this->writeGlobalBuffer( $data, null );

		$processes[ $this->id ]['globalBuffer'] = array();
		$this->handler->cache->set( 'processes', $processes );

		unset( $processes, $data );
	}

	private function writeGlobalBuffer( $data, $clientId = null ) {
		if ( 0 >= count( $this->clients ) )
			return;

		foreach ( $this->clients as $client )
			if ( $clientId !== $client->id )
				$client->write( $data );

		unset( $client );
	}

	public function sendBatchBuffer( $data, $clients ) {
		$cacheClients = $this->handler->cache->get( 'clients', array() );

		$processes = array();
		$localClients = array();

		foreach ( $clients as $clientId ) {
			$process = $cacheClients[ $clientId ]['process'];
			if ( $this->id === $process ) {
				$localClients[] = $clientId;
				continue;
			}

			if ( false === in_array( $process, $processes ) )
				$processes[ $process ] = array(
					'clients'	=>	array(),
					'data'		=>	$data
				);

			$processes[ $process ]['clients'][] = $clientId;
		}

		if ( 0 < count( $processes ) ) {
			$cacheProcesses = $this->handler->cache->get( 'processes', array() );

			foreach ( $processes as $process => $buffer )
				$cacheProcesses[ $process ]['batchBuffer'][] = $buffer;

			$this->handler->cache->set( 'processes', $cacheProcesses );

			foreach ( array_keys( $processes ) as $process )
				posix_kill( $cacheProcesses[ $process ]['pid'], 50 );

			unset( $cacheProcesses, $buffer );
		}

		$this->writeBatchBuffer( $data, $localClients );

		unset( $data, $localClients, $cacheClients, $processes, $clients, $clientId );
	}

	private function handleBatchBuffer() {
		$processes = $this->handler->cache->get( 'processes', array() );
		$buffers = empty( $processes[ $this->id ]['batchBuffer'] ) ? null : $processes[ $this->id ]['batchBuffer'];
		$processes[ $this->id ]['batchBuffer'] = array();
		$this->handler->cache->set( 'processes', $processes );

		foreach ( $buffers as $buffer )
			$this->writeBatchBuffer( $buffer['data'], $buffer['clients'] );

		unset( $processes, $buffers, $buffer );
	}

	private function writeBatchBuffer( $data, $clients ) {
		foreach ( $clients as $clientId )
			if ( isset( $this->clients[ $clientId ] ) )
				$this->clients[ $clientId ]->write( $data );

		unset( $clientId );
	}

	public function sendClientBuffer( $data, $clientId ) {
		if ( isset( $this->clients[ $clientId ] ) )
			$this->writeClientBuffer( $data, $clientId );
		else {
			$clients = $this->handler->cache->get( 'clients', array() );

			if ( isset( $clients[ $clientId ] ) ) {
				$processes = $this->handler->cache->get( 'processes', array() );
				$processes[ $clients[ $clientId ]['process'] ]['clientBuffer'][] = array(
					'data'		=>	$data,
					'client'	=>	$clientId
				);
				$this->handler->cache->set( 'processes', $processes );

				posix_kill( $processes[ $clients[ $clientId ]['process'] ]['pid'], 51 );

				unset( $processes );
			}
			unset( $clients );
		}
		unset( $data );
	}

	private function handleClientBuffer() {
		$processes = $this->handler->cache->get( 'processes', array() );
		$buffers = empty( $processes[ $this->id ]['clientBuffer'] ) ? array() : $processes[ $this->id ]['clientBuffer'];
		$processes[ $this->id ]['clientBuffer'] = array();
		$this->handler->cache->set( 'processes', $processes );

		foreach ( $buffers as $buffer )
			if ( isset( $this->clients[ $buffer['client'] ] ) )
				$this->writeClientBuffer( $buffer['data'], $buffer['client'] );

		unset( $processes, $buffers, $buffer );
	}

	private function writeClientBuffer( $data, $clientId ) {
		if ( false === $data )
			$this->disconnectClient( $this->clients[ $clientId ] );

		$this->clients[ $clientId ]->write( $data );
	}

	protected function handleParent( $childPid ) {
		$this->handler->registerProcess( $this->id, $childPid );
	}

	protected function handleChild( $parentPid ) {
		$this->pid = posix_getpid();
		$this->handler->cleanUpChild();

		$processes = $this->handler->cache->get( 'processes', array() );
		$processes[ $this->id ] = array(
			'pid'			=>	$this->pid,
			'globalBuffer'	=>	array(),
			'batchBuffer'	=>	array(),
			'clientBuffer'	=>	array()
		);
		$this->handler->cache->set( 'processes', $processes );
		unset( $processes );

		$this->registerSignalHandlers();
		$this->handler->initModules( $this );

		$this->log('Started');
		$this->waitForSignal();

		$this->handler->protocol->init();

		// Start serving
		try {
			while ( $this->handler->protocol->listen() ) {
				$sockets = $this->handler->protocol->getActiveSockets();

				if ( isset( $sockets[0] ) ) {
					$socket = socket_accept( $sockets[0] );
					if ( false !== $socket )
						$this->registerClient( $socket );
					else
						throw new RuntimeException('Failed socket_accept().');

					unset( $sockets[0], $socket );

					if ( 0 < count( $sockets ) ) {
						unset( $sockets );
						continue;
					}
				}

				$keys = array_keys( $sockets );
				foreach ( $keys as $key )
					$this->handleRequest( $this->clients[ $key - 1 ] );

				unset( $keys, $key );
			}
		} catch ( Exception $error ) {
			$this->log( '[Exception] ' . $error->getMessage() );
		}

		$this->shutdown();
	}

	private function waitForSignal() {
		$this->waitingForSignal = true;
		while ( $this->waitingForSignal ) {
			pcntl_signal_dispatch();
			usleep(1000);
		}
	}

	private function registerClient( $socket ) {
		$clients = $this->handler->cache->get( 'clients', array() );
		$clients[] = array(
			'process'	=>	$this->id,
			'data'		=>	array()
		);
		$this->handler->cache->set( 'clients', $clients );

		$clientId = array_keys( $clients );
		$clientId = end( $clientId );

		$this->clients[ $clientId ] = new PhetClient( $this->handler, $this, $socket, $clientId );
		$this->handler->protocol->addSocket( $socket, $clientId );

		unset( $clients, $clientId );

		if ( $this->handler->data['maxThreadClients'] <= count( $this->clients ) ) {
			$this->handler->protocol->removeSocket( -1 );

			$this->log('Stopped accepting new clients');
		}

		posix_kill( $this->handler->pid, SIGUSR1 );
	}

	private function serveNewClients() {
		$this->waitingForSignal = false;
		$this->handler->protocol->acceptNewClients();
		$this->log('Started accepting new clients');
	}

	private function handleRequest( $client ) {
		$input = $client->read();

		$request = PhetRequest::factory( $input );

		if ( false === $request ) {
			$this->disconnectClient( $client );
			return;
		}

		switch ( $request->type ) {
			case 'get':
				$this->handler->sendEvent( 'RequestGet', $client, $request );
				break;

			case 'raw':
				$this->handler->sendEvent( 'RequestRaw', $client, $request );
				break;
		}

		unset( $input, $request );
	}

	public function disconnectClient( $client ) {
		if ( is_int( $client ) )
			if ( isset( $this->clients[ $client ] ) )
				$client = $this->clients[ $client ];
			else {
				$this->sendClientBuffer( false, $client );
				return;
			}


		$client->disconnect();
		$this->handler->protocol->removeSocket( $client->id );

		$array = $this->handler->cache->get( 'calledDisconnect', array() );
		$array[] = array(
			'id'		=>	$this->id,
			'clients'	=>	count( $this->clients )
		);
		$this->handler->cache->set( 'calledDisconnect', $array );

		$clients = $this->handler->cache->get( 'clients', array() );
		unset( $clients[ $client->id ] );
		$this->handler->cache->set( 'clients', $clients );

		unset( $clients, $this->clients[ $client->id ], $array, $request );

		posix_kill( $this->handler->pid, SIGUSR2 );

		if ( 0 >= count( $this->handler->protocol->getSockets() ) )
			$this->waitForSignal();
	}

	private function registerSignalHandlers() {
		pcntl_signal( SIGHUP, array( &$this, 'handleSignal' ) );
		pcntl_signal( SIGTERM, array( &$this, 'handleSignal' ) );
		pcntl_signal( SIGUSR1, array( &$this, 'handleSignal' ) );
		pcntl_signal( SIGUSR2, array( &$this, 'handleSignal' ) );
		pcntl_signal( 50, array( &$this, 'handleSignal' ) );
		pcntl_signal( 51, array( &$this, 'handleSignal' ) );
	}

	public function handleSignal( $signal ) {
		switch ( $signal ) {
			case SIGTERM:
				$this->shutdown();

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
