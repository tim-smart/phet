<?php

class PhetCache {
	public function __construct() {
		$this->ftokSemKey = ftok( __FILE__, 'a' );
		$this->ftokShmKey = ftok( __FILE__, 'b' );

		$this->shmKey = shm_attach( $this->ftokShmKey, PHET_CACHEBYTES, 0600 );
		shm_detach( $this->shmKey );

		$this->shmKey = null;
	}

	private $shmKey;
	private $semKey;
	private $ftokSemKey;
	private $ftokShmKey;
	private $keyMap = array(
		'processes'			=>	1,
		'clients'			=>	2,
		'calledDisconnect'	=>	3
	);

	private function setLock() {
		$this->semKey = sem_get( $this->ftokSemKey, 1, 0600 );
		sem_acquire( $this->semKey );

		$this->shmKey = shm_attach( $this->ftokShmKey );
	}

	private function releaseLock() {
		shm_detach( $this->shmKey );
		sem_release( $this->semKey );

		$this->semKey = null;
		$this->shmKey = null;
	}

	public function get( $key, $default = NULL ) {
		$this->setLock();

		if ( shm_has_var( $this->shmKey, $this->keyMap[ $key ] ) ) {
			$ret = shm_get_var( $this->shmKey, $this->keyMap[ $key ] );
			$this->releaseLock();
			return $ret;
		}

		$this->releaseLock();
		return $default;
	}

	public function set( $key, $value ) {
		$this->setLock();
		shm_put_var( $this->shmKey, $this->keyMap[ $key ], $value );
		$this->releaseLock();
	}

	public function delete( $key ) {
		$this->setLock();

		shm_remove_var( $this->shmKey, $this->keyMap[ $key ] );

		$this->releaseLock();
	}

	public function close() {
		$this->setLock();

		shm_remove( $this->shmKey );
		return sem_remove( $this->semKey ) ? true : false;
	}
}

?>
