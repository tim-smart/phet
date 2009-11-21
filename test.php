#!/opt/lampp/bin/php -q
<?php

require 'phet/classes/Thread.class.php';

class TestThread extends Thread {
	public function __construct() {
		$this->poos = 'Testing test?' . "\n";
	}

	private $poos;

	public function handleParent( $childPid ) {
	}

	public function handleChild( $parentPid ) {
		$this->registerSignalHandlers();
		while ( true ) {
			pcntl_signal_dispatch();
			usleep(500);
		}
	}

	protected function registerSignalHandlers() {
		pcntl_signal( SIGTERM, array( $this, 'handleSignal' ) );
		pcntl_signal( SIGHUP, array( $this, 'handleSignal' ) );
		pcntl_signal( SIGCHLD, array( $this, 'handleSignal' ) );
		pcntl_signal( SIGUSR1, array( $this, 'handleSignal' ) );
		pcntl_signal( SIGUSR2, array( $this, 'handleSignal' ) );
		pcntl_signal( 50, array( $this, 'handleSignal' ) );
		pcntl_signal( 51, array( $this, 'handleSignal' ) );
		pcntl_signal( 52, array( $this, 'handleSignal' ) );
		pcntl_signal( 53, array( $this, 'handleSignal' ) );
	}

	protected function handleSignal( $signal ) {
		switch ( $signal ) {
			case SIGTERM:
				echo "SIGTERM\n";
				exit();

			case SIGKILL:
				echo "SIGKILL\n";
				exit();
			
		}
	}
}

$pid = pcntl_fork();

if ( 0 > $pid )
	throw new RuntimeException('Failed to fork parent');

else if ( 0 < $pid )
	exit();

// We are daemon
posix_setsid();
umask(0);
chdir('/');

// Set handlers
function echoPid( $pid ) {
	echo 'PID: ' . $pid . "\n";
}

function waitForSignal( $callback ) {
	while ( 0 < ( $pid = pcntl_waitpid( -1, $status ) ) )
		if ( isset( $callback ) && is_callable( $callback ) )
			call_user_func( $callback, $pid );
}

function handleSignal( $signal ) {
	echo 'Signal: ' . $signal . "\n";
	switch ( $signal ) {
		case SIGHUP:
		case SIGTERM:
			exit();

		case SIGCHLD:
			waitForSignal('echoPid');
			break;
	}
}

pcntl_signal( SIGTERM, 'handleSignal' );
pcntl_signal( SIGHUP, 'handleSignal' );
pcntl_signal( SIGCHLD, 'handleSignal' );

$thread = new TestThread();
$childPid = $thread->fork();

posix_kill( $childPid, 53 );
posix_kill( $childPid, 52 );
posix_kill( $childPid, 51 );
posix_kill( $childPid, 50 );
posix_kill( $childPid, SIGUSR1 );
pcntl_signal_dispatch();

?>
