<?php

class PhetCache {
	public function __construct() {
		$this->ftokKey = ftok( __FILE__, 'p' );
		$this->writeData( array() );
	}

	private $key;
	private $ftokKey;

	public function get( $key, $default = NULL ) {
		$data = $this->getData();

		if ( empty( $data[ $key ] ) )
			return $default;

		$data = $data[ $key ];
		return $data;
	}

	public function set( $key, $value ) {
		$data = $this->getData();

		if ( false === $data ) {
			return $data;
		}

		$data[ $key ] = $value;
		$ret = $this->writeData( $data ) ? true : false;

		unset( $data );
		return $ret;
	}

	public function delete( $key = null ) {
		if ( null === $key )
			if ( $this->writeData( serialize( array() ) ) )
				return true;
			else
				return false;

		else {
			$data = $this->getData();

			if ( false === $data ) {
				return $data;
			}

			unset( $data[ $key ] );
			$ret = $this->writeData( $data ) ? true : false;

			unset( $data );
			return $ret;
		}
	}

	public function close() {
		shmop_delete( $this->key );
		return shmop_close( $this->key ) ? true : false;
	}

	private function getData() {
		if ( $data = shmop_read( $this->key, 0, shmop_size( $this->key ) ) ) {
			var_dump( $data );
			return unserialize( $data );
		}
		else
			return false;
	}

	private function writeData( $data ) {
		$data = serialize( $data );
		$dataLength = strlen( $data );

		shmop_delete( $this->key );
		shmop_close( $this->key );
		$this->key = shmop_open( $this->ftokKey, 'c', 0644, $dataLength );

		$ret = shmop_write( $this->key, $data, 0 );

		unset( $data, $dataLength );
		return $ret;
	}
}

?>
