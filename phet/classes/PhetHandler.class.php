<?php

class PhetHandler {
	public function __construct( $data ) {
		if ( PHP_SAPI !== 'cli' )
			throw new LogicException("phet should be run using CLI SAPI");
 
		if ( version_compare( '5.3.0-dev', PHP_VERSION, '>' ) )
			throw new LogicException("phet requires PHP 5.3.0+");

		$this->data = $data;

		$this->protocol = new PhetServer( $data['host'], $data['port'] );
		$this->cache = new PhetCache();

		$this->log( 'phet Handler initialized @ [' . $data['host'] . ':' . $data['port'] . ']' );

		unset( $data );
	}

	private $modules = array();
	private $pid;
	private $processes = array();
	private $data = array();

	public function log( $message ) {
		echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
	}

	public function getInfo() {
		return $this->data;
	}

	public function exit() {
		foreach ( $this->processes as $pid )
			posix_kill( $pid, SIGTERM );

		$this->cache->close();
		$this->server->disconnect();
		exit();
	}

	public function cleanUpChild() {
		unset( $this->processes );
	}

	public function serve() {
		// Go daemon
		$this->daemonise();
		$this->registerSignalHandlers();

		$this->log('phet is now listening...');
		$this->createThread();

		while( true ) {
			pcntl_signal_dispatch();

			usleep(300);
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
		for ( $i = 0; $i < $this->data['maxThreads']; $i++ )
			if ( empty( $this->processes[ $i ] ) ) {
				$this->processes[ $i ] = array(
					'clients'	=>	0,
					'pid'		=>	null
				);

				$thread = new PhetThread( $this, $i );
				$thread = $thread->fork();
			}

		unset( $i, $thread );
	}

	public function registerProcess( $id, $childPid ) {
		$this->processes[ $id ]['pid'] = $childPid;
	}

	private function registerSignalHandlers() {
		pcntl_signal( SIGHUP, array( &$this, 'handleSignal' ) );
		pcntl_signal( SIGTERM, array( &$this, 'handleSignal' ) );
		pcntl_signal( SIGUSR1, array( &$this, 'handleSignal' ) );
		pcntl_signal( SIGUSR2, array( &$this, 'handleSignal' ) );
	}

	private function handleSignal( $signal ) {
		switch ( $signal ) {
			case SIGHUP:
			case SIGTERM:
				$this->exit();

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

		if ( $this->data['maxThreadClients'] <= $this->processes[ $this->currentListeningProcess ]['clients'] )
			if ( $this->data['maxThreads'] > count( $this->processes ) ) {
				foreach ( $this->processes as $process ) {
					if ( $this->data['maxThreadClients'] > $process['clients'] ) {
						$this->switchListeningProcess( $process );
						unset( $process );
						return;
					}
				}

				$this->createThread();
				unset( $process );
			} else
				$this->log('Maximum threads reached.');
	}

	private function switchListeningProcess( $process ) {
		// code...
	}

	private function handleClientDisconnect() {
		$processes = $this->cache->get( 'calledDisconnect', array() );

		foreach ( $processes as $i )
			if ( isset( $this->processes[ $i ] ) )
				$this->processes[ $i ]['clients']--;

		$this->cache->set( 'calledDisconnect', array() );
		unset( $processes, $i );
	}

	public function registerModule( &$module ) {
		if ( is_object( $module ) && is_callable( $module ) )
			$this->modules[] = &$module;
		else
			throw new Exception('Failed to register an invalid module.');

		unset( $module );
	}

	public function sendEvent( $event, $data ) {
		foreach ( $this->modules as $module )
			$module( $event, $data );

		unset( $module );
	}
}

?>
