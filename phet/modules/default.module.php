<?php

class PhetModuleDefault implements iPhetModule {
	public function eventHandler( $event, $data ) {
		switch ( $event ) {
			case '':
				// code...
				break;
		}
		return;

		if ( 'js' === $data['GET']['module'] ) {
			$host = $server->webHost;
			$port = $server->port;

			ob_start();
			include PHET_DIR . 'js/phet.js';
			$js = ob_get_contents();
			ob_end_clean();

			$client->send('HTTP/1.1 200 OK' . "\r\n" . 'Content-Type: application/x-javascript' . "\r\n\r\n" . $js );
			$server->disconnectClient( $client );

			unset( $host, $port, $js );
		} else if ( 'iframe' === $data['GET']['module'] ) {
			$client->send( 'HTTP/1.1 200 OK' . "\r\n" .
				'Content-Type: text/html' . "\r\n\r\n" .
				'<script type="text/javascript">' .
				'document.domain = "' . $server->webHost . '";</script>' );
		}
	}
}

?>
