<?php

abstract class PhetModule {
	public $handler;
	public $thread;

	public function getClients() {
		return $this->handler->cache->get( 'clients', array() );
	}

	public function shutdown() {
		posix_kill( $this->handler->pid, SIGTERM );
	}
}

?>
