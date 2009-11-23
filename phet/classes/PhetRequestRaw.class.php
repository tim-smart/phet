<?php

class PhetRequestRaw extends PhetRequest {
	public function __construct( $input ) {
		$this->body = trim( $input );
		$this->raw = $input;
	}

	public $type = 'raw';
	protected $body;
	protected $raw;
}

?>
