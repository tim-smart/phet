<?php

abstract class Thread {
	abstract protected function handleParent( $childPid );
	
	abstract protected function handleChild( $parentPid );

	public function fork() {
		$parentPid = posix_getpid();
		$pid = pcntl_fork();

		if ( 0 > $pid )
			throw new RuntimeException( 'Failed to fork parent pid ' . $parentPid );

		else if ( 0 < $pid ) {
			pcntl_waitpid( -1, $status, WNOHANG );
			unset( $status );

			$this->handleParent( $pid );
		}

		else {
			$this->handleChild( $parentPid );
			unset( $parentPid, $pid );
			exit();
		}

		unset( $parentPid );
		return $pid;
	}
}

?>
