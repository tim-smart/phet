<?php

abstract class PhetRequest {
	public function getInput() {
		return $this->raw;
	}

	public function getBody() {
		return $this->body;
	}

	static public function factory( $input ) {
		if ( '' === $input )
			return false;

		if ( preg_match( '/GET (?:\/\?(.*?)|.*?) HTTP\/1\.[0-9]/', substr( $input, 0, strpos( $input, "\r\n" ) ), $match ) ) {
			unset( $match[0] );
			return new PhetRequestGet( $input, isset( $match[1] ) ? $match[1] : false );
		}
		else
			return new PhetRequestRaw( $input );
	}
}

?>
