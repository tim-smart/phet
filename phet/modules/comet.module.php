<?php

class PhetModuleComet extends PhetModule {
	public function __invoke( $event, $data ) {
		if ( 'RequestGet' !== $event )
			return;

		$module = $data['request']->getParam('module');

		if ( 'js' === $module ) {
			$host = PHET_WEBHOST;
			$port = $this->handler->data['port'];

			ob_start();
			include PHET_DIR . 'js/phet.js';
			$js = ob_get_contents();
			ob_end_clean();

			$data['client']->write('HTTP/1.1 200 OK' . "\r\n" . 'Content-Type: application/x-javascript' . "\r\n\r\n" . $js );
			$this->thread->disconnectClient( $data['client'] );

			unset( $host, $port, $js );
		} else if ( 'iframe' === $module ) {
			$data['client']->write( 'HTTP/1.1 200 OK' . "\r\n" .
				'Content-Type: text/html' . "\r\n" .
				'Transfer-Encoding: chunked' . "\r\n\r\n" );

			unset( $body );
		} else if ( 'bench' === $module )
			$this->thread->disconnectClient( $data['client'] );
	}
}

?>
