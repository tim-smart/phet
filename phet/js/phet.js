(function(){

var Phet = function() {
	// Set-up iframe
	this._iframe = document.createElement('iframe');
};

Phet.prototype = {
	host: '<?php echo $host; ?>',
	port: <?php echo $port; ?>,
	_listeners: [],
	_queue: [],
	_current: null,
	_image: null,
	_iframe: null,
	_chatid: null,
	start: function() {
		document.domain = this.host;

		this._iframe.src = 'http://' + this.host + ':' + this.port + '/?module=iframe&tmp=' + new Date().getTime();
		document.body.appendChild( this._iframe );
	},
	send: function( body ) {
		this._queue.push( body );
		this._updateQueue();
	},
	_updateQueue: function() {
		if ( null !== this._current || 0 >= this._queue.length )
			return;

		this._current = this._queue.shift();
		this._image = new Image()
		this._image.src = 'http://' + this.host + ':' + this.port + '/?' + this._current;

		// Make sure we don't overload
		setTimeout( function( fn ) {
			fn._onQueueUpdate.call( fn );
		}, 100, this );
	},
	_onQueueUpdate: function() {
		this._current = null;
		this._image = null;

		this._updateQueue();
	},
	addRequestListener: function( fn ) {
		this._listeners.push( fn );
	},
	_setChatId: function( chatid ) {
		this._chatid = chatid;
	},
	_onRecieve: function( data ) {
		for ( var i = 0, fn; fn = this._listeners[ i++ ]; )
			fn.call( this, data );
	},
	_addEvent: function( element, type, callback ) {
		if ( 'function' === typeof element.addEventListener )
			element.addEventListener( type, callback, false );
		else if ( 'object' === typeof element.attachEvent )
			return element.attachEvent( 'on' + type, function() {
				fn.call( element, window.event );
			} );
	}
};

window['phet'] = new Phet();

})();
