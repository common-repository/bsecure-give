




var is_blocked = function( $node ) {
	return $node.is( '.processing' ) || $node.parents( '.processing' ).length;
};

/**
 * Block a node visually for processing.
 *
 * @param {JQuery Object} $node
 */
var block = function( $node ) {

	if($node.length < 1){
		jQuery(".bsecure-loader-span").addClass("bsecure-ajax-loader");
	}else{

		if ( ! is_blocked( $node ) ) {
			if (jQuery.isFunction(jQuery.fn.block) ) {
			    $node.addClass( 'processing' ).block( {
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				} );
			}else{

				jQuery(".bsecure-loader-span").addClass("bsecure-ajax-loader");
			}			
		}
	}

	
};

/**
 * Unblock a node after processing is complete.
 *
 * @param {JQuery Object} $node
 */
var unblock = function( $node ) {

	if($node.length < 1){
		jQuery(".bsecure-loader-span").removeClass("bsecure-ajax-loader");
	}else{

		if (jQuery.isFunction(jQuery.fn.unblock)) {
			$node.removeClass( 'processing' ).unblock();
		}else{

			jQuery(".bsecure-loader-span").removeClass("bsecure-ajax-loader");
		}
	}
};

