/**
 * Fills the MonoRanks box in the post editor after the editor has loaded (src/PostPanel.php), so opening a post never
 * waits on MonoRanks. The server answers with HTML it already escaped and translated. Plain script, no build step.
 */
( function () {
	function fill( box ) {
		if ( ! window.wp || ! window.wp.apiFetch ) {
			return;
		}
		window.wp.apiFetch( { path: '/monoranks/v1/admin/post/' + encodeURIComponent( box.getAttribute( 'data-post' ) ) } ).then(
			function ( res ) {
				box.innerHTML = res && typeof res.html === 'string' ? res.html : '';
				box.setAttribute( 'aria-busy', 'false' );
			},
			function () {
				var p = document.createElement( 'p' );
				p.className = 'mr-panel-note';
				p.textContent = box.getAttribute( 'data-error' ) || '';
				box.innerHTML = "";
				box.appendChild( p );
				box.setAttribute( 'aria-busy', 'false' );
			}
		);
	}
	function start() {
		document.querySelectorAll( '.mr-panel[data-post]' ).forEach( fill );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
