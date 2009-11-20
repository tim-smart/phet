<?php

class PhetCache {
	public function __construct( $host, $port ) {
		$this->memcache = new Memcache;

		$this->memcache->connect( $host, $port );
	}

	protected $memcache;

	public function set( $name, $value ) {
		return $this->memcache->set( 'phet' . PHET_PORT . '_' . $name, $value, false, 0 );
	}

	public function get( $name, $default = null ) {
		$ret = $this->memcache->get( 'phet' . PHET_PORT . '_' . $name );
		if ( false === $ret ) {
			unset( $ret );
			return $default;
		} else
			return $ret;
	}

	public function delete( $name ) {
		return $this->memcache->delete( 'phet' . PHET_PORT . '_' . $name );
	}
}

?>
