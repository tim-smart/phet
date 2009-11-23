<?php

abstract class PhetModule {
	public $handler;
	public $thread;

	protected function getClients() {
		return $this->handler->cache->get( 'clients', array() );
	}

	protected function shutdown() {
		posix_kill( $this->handler->pid, SIGTERM );
	}
}

?>
