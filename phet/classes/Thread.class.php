<?php

abstract class Thread {
	abstract protected function handleParent( $pid );
	
	abstract protected function handleChild();

	public function fork() {
		$parentPid = posix_getpid();
		$pid = pcntl_fork();

		if ( 0 > $pid )
			throw new RuntimeException( 'Failed to fork parent pid ' . $parentPid );

		else if ( 0 < $pid )
			$this->handleParent( $pid );

		else {
			$this->handleChild( $parentPid );
			unset( $parentPid, $pid );
			exit();
		}

		unset( $parentPid, $pid );
	}
}

?>
