<?php

abstract class Thread {
	abstract private function handleParent( $childPid );
	
	abstract private function handleChild( $parentPid );

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

		unset( $parentPid );
		return $pid;
	}
}

?>
