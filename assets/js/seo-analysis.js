/**
 * EO Tools: keeps the detail of Yoast SEO's analysis when a content is saved.
 *
 * Yoast SEO only stores its overall scores. This script reads the detailed criteria
 * from Yoast's editor store and sends them to the site (never elsewhere), so that the
 * AI connected to EO Tools knows which criteria fail.
 */
( function () {
	'use strict';

	var config = window.eoToolsSeoAnalysis;
	if ( ! config || ! window.wp || ! window.wp.data ) {
		return;
	}

	var STORE = 'yoast-seo/editor';

	function mapResults( results ) {
		return ( results || [] ).map( function ( result ) {
			return {
				id: result.id || result._identifier || '',
				rating: result.rating || '',
				score: result.score,
				text: result.text || ''
			};
		} );
	}

	function collect() {
		var yoast = window.wp.data.select( STORE );
		if ( ! yoast || 'function' !== typeof yoast.getResultsForFocusKeyword ) {
			return null;
		}

		var seo = yoast.getResultsForFocusKeyword() || {};
		var readability = 'function' === typeof yoast.getReadabilityResults ? yoast.getReadabilityResults() || {} : {};

		return {
			keyword: 'function' === typeof yoast.getFocusKeyphrase ? yoast.getFocusKeyphrase() : '',
			seo_score: seo.overallScore,
			readability_score: readability.overallScore,
			seo_results: mapResults( seo.results ),
			readability_results: mapResults( readability.results )
		};
	}

	// Block editor: send once the content and the meta boxes (Yoast's fields) are saved.
	function watchBlockEditor() {
		var data = window.wp.data;
		var wasSaving = false;
		var timer = null;

		function send() {
			var editor = data.select( 'core/editor' );
			var postId = editor.getCurrentPostId();
			var analysis = collect();
			if ( postId && analysis && window.wp.apiFetch ) {
				window.wp.apiFetch( {
					path: config.path + postId,
					method: 'POST',
					data: analysis
				} ).catch( function () {} );
			}
		}

		data.subscribe( function () {
			var editor = data.select( 'core/editor' );
			if ( ! editor ) {
				return;
			}
			var editPost = data.select( 'core/edit-post' );
			var saving = ( editor.isSavingPost() && ! editor.isAutosavingPost() ) ||
				!! ( editPost && 'function' === typeof editPost.isSavingMetaBoxes && editPost.isSavingMetaBoxes() );

			if ( saving && timer ) {
				// The meta boxes are saved right after the content: wait for the end.
				window.clearTimeout( timer );
				timer = null;
			}
			if ( wasSaving && ! saving && editor.didPostSaveRequestSucceed() ) {
				timer = window.setTimeout( function () {
					timer = null;
					send();
				}, 1500 );
			}
			wasSaving = saving;
		} );
	}

	// Classic editor: the analysis goes with the form, in hidden fields.
	function watchClassicEditor() {
		var form = document.getElementById( 'post' );
		if ( ! form ) {
			return;
		}

		form.addEventListener( 'submit', function () {
			var analysis = collect();
			if ( ! analysis ) {
				return;
			}
			[ [ 'eo_tools_seo_analysis', JSON.stringify( analysis ) ], [ 'eo_tools_seo_analysis_nonce', config.nonce ] ].forEach( function ( field ) {
				var input = form.querySelector( 'input[name="' + field[ 0 ] + '"]' );
				if ( ! input ) {
					input = document.createElement( 'input' );
					input.type = 'hidden';
					input.name = field[ 0 ];
					form.appendChild( input );
				}
				input.value = field[ 1 ];
			} );
		} );
	}

	if ( config.blockEditor ) {
		watchBlockEditor();
	} else if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', watchClassicEditor );
	} else {
		watchClassicEditor();
	}
}() );
