<?php

class PhetHandler {
	public function __construct( $data ) {
		if ( PHP_SAPI !== 'cli' )
			throw new LogicException("phet should be run using CLI SAPI");
 
		if ( version_compare( '5.3.0-dev', PHP_VERSION, '>' ) )
			throw new LogicException("phet requires PHP 5.3.0+");

		$this->data = $data;

		$this->protocol = new PhetServer( $data['host'], $data['port'] );
		$this->cache = new PhetCache( $data['cache_host'], $data['cache_port'] );

		$this->log( 'phet Handler initialized @ [' . $data['host'] . ':' . $data['port'] . ']' );

		unset( $data );
	}

	private $modules = array();
	public $pid;
	private $processes = array();
	public $data = array();
	private $currentListeningProcess;

	public function log( $message ) {
		echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
	}

	private function shutdown() {
		foreach ( $this->processes as $process )
			posix_kill( $process['pid'], SIGTERM );
		unset( $process );

		$this->cache->close();
		$this->protocol->disconnect();
		$this->log('Disconnected');
		exit();
	}

	public function cleanUpChild() {
		unset( $this->processes );
	}

	public function serve() {
		// Go daemon
		$this->daemonise();

		for ( $i = 0; $i < $this->data['threads']; $i++ )
			$this->createThread();

		posix_kill( $this->processes[0]['pid'], SIGUSR1 );
		$this->currentListeningProcess = 0;

		$this->registerSignalHandlers();

		while( true ) {
			pcntl_signal_dispatch();

			usleep(500);
		}
	}

	private function daemonise() {
		$pid = pcntl_fork();

		if ( 0 > $pid )
			throw new RuntimeException('Failed to fork parent');

		else if ( 0 < $pid )
			exit();

		unset( $pid );
		posix_setsid();
		umask(0);
		chdir('/');

		$this->pid = posix_getpid();
	}

	private function createThread() {
		for ( $i = 0; $i < $this->data['threads']; $i++ )
			if ( empty( $this->processes[ $i ] ) ) {
				$this->processes[ $i ] = array(
					'clients'	=>	0,
					'pid'		=>	null
				);

				$thread = new PhetThread( $this, $i );
				$thread = $thread->fork();
				break;
			}

		unset( $i, $thread );
	}

	public function registerProcess( $id, $childPid ) {
		$this->processes[ $id ]['pid'] = $childPid;
		$this->currentListeningProcess = $id;
	}

	private function registerSignalHandlers() {
		pcntl_signal( SIGHUP, array( &$this, 'handleSignal' ) );
		pcntl_signal( SIGTERM, array( &$this, 'handleSignal' ) );
		pcntl_signal( SIGCHLD, array( &$this, 'handleSignal' ) );
		pcntl_signal( SIGUSR1, array( &$this, 'handleSignal' ) );
		pcntl_signal( SIGUSR2, array( &$this, 'handleSignal' ) );
	}

	private function handleSignal( $signal ) {
		switch ( $signal ) {
			case SIGHUP:
			case SIGTERM:
				$this->shutdown();

			case SIGCHLD:
				$this->waitForSignal( array( &$this, 'handleChildExit' ) );
				break;

			case SIGUSR1:
				$this->handleClientConnect();
				break;

			case SIGUSR2:
				$this->handleClientDisconnect();
				break;
		}
	}

	private function waitForSignal( $callback ) {
		while ( 0 < ( $pid = pcntl_waitpid( -1, $status ) ) )
			if ( isset( $callback ) && is_callable( $callback ) )
				call_user_func( $callback, $pid );
		unset( $pid );
	}

	private function handleChildExit( $childPid ) {
		foreach ( $this->processes as $i => $process )
			if ( $childPid === $process['pid'] ) {
				unset( $this->processes[ $i ] );
				break;
			}
	}

	private function handleClientConnect() {
		$this->processes[ $this->currentListeningProcess ]['clients']++;

		if ( $this->data['maxThreadClients'] <= $this->processes[ $this->currentListeningProcess ]['clients'] ) {
			foreach ( $this->processes as $i => $process ) {
				if ( $this->data['maxThreadClients'] > $process['clients'] ) {
					posix_kill( $process['pid'], SIGUSR1 );
					$this->currentListeningProcess = $i;
					unset( $process, $i );
					return;
				}
			}

			$this->currentListeningProcess = false;
			$this->log('Maximum capacity reached.');

			unset( $process );
		}
	}

	private function handleClientDisconnect() {
		$processes = $this->cache->get( 'calledDisconnect', array() );

		foreach ( $processes as $i )
			if ( isset( $this->processes[ $i ] ) )
				$this->processes[ $i ]['clients']--;

		$this->cache->set( 'calledDisconnect', array() );

		if ( false === $this->currentListeningProcess ) {
			foreach ( $this->processes as $i => $process )
				if ( $this->data['maxThreadClients'] > $process['clients'] ) {
					posix_kill( $process['pid'], SIGUSR1 );
					$this->currentListeningProcess = $i;
					break;
				}
		}
		unset( $processes, $i, $process );
	}

	public function registerModule( $module ) {
		if ( is_object( $module ) && is_callable( $module ) )
			$this->modules[] = $module;
		else
			throw new Exception('Failed to register an invalid module.');

		unset( $module );
	}

	public function initModules( $thread ) {
		foreach ( $this->modules as $module ) {
			$module->handler = $this;
			$module->thread = $thread;
		}

		unset( $module );
	}

	public function sendEvent( $event, $client = null, $data = null ) {
		foreach ( $this->modules as $module )
			$module( $event, array(
				'client'	=>	$client,
				'request'	=>	$data
			) );

		unset( $module );
	}
}

?>
