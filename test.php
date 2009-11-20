<?php

require 'phet/classes/Thread.class.php';

declare( ticks = 1 );

$pid = pcntl_fork();

if ( 0 > $pid )
	throw new RuntimeException('Failed to fork parent');

else if ( 0 < $pid )
	exit();

// We are daemon
posix_setsid();
umask(0);
chdir('/');

class TestThread extends Thread {
	public function handleParent( $childPid ) {

	}

	public function handleChild( $parentPid ) {
		
	}
}

?>
