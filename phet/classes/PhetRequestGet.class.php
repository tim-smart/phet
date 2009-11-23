<?php

class PhetRequestGet extends PhetRequest {
	public function __construct( $input, $params ) {
		$this->raw = $input;

		if ( false === empty( $params ) )
			parse_str( $params, $this->params );

		$split = strpos( $input, "\r\n\r\n" );
		$this->headers = $this->parseHeaders( substr( $input, 0, $split ) );

		$this->body = substr( $input, $split + 4 );
		unset( $input, $split );
	}

	public $type = 'get';
	protected $body;
	protected $raw;
	private $headers = array();
	private $params = array();

	private function parseHeaders( $header ) {
		// Have we got the native function?
		if ( function_exists('http_parse_headers') )
			return http_parse_headers( $header );

		$retVal = array();

		$fields = explode( "\r\n", preg_replace( '/\x0D\x0A[\x09\x20]+/', ' ', $header ) );
		foreach ( $fields as $field ) {
			if( preg_match( '/([^:]+): (.+)/m', $field, $match ) ) {
				$match[1] = preg_replace( '/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower( trim( $match[1] ) ) );

				if( isset( $retVal[ $match[1] ] ) ) {
					$retVal[ $match[1] ] = array( $retVal[ $match[1] ], $match[2] );
				} else {
					$retVal[ $match[1] ] = trim( $match[2] );
				}
			}
		}

		unset( $fields, $field, $match, $header );
		return $retVal;
	}

	public function getParam( $name ) {
		if ( empty( $this->params[ $name ] ) )
			return null;

		return $this->params[ $name ];
	}

	public function getHeaders() {
		return $this->headers;
	}
}

?>
