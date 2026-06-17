/* global serpcheapRT */
( function () {
	'use strict';

	if ( typeof serpcheapRT === 'undefined' ) {
		return;
	}

	var cfg = serpcheapRT;
	var t = cfg.i18n;

	function esc( s ) {
		var d = document.createElement( 'div' );
		d.textContent = s == null ? '' : String( s );
		return d.innerHTML;
	}

	function api( method, path, body ) {
		return fetch( cfg.root + path, {
			method: method,
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce,
			},
			credentials: 'same-origin',
			body: body ? JSON.stringify( body ) : undefined,
		} ).then( function ( r ) {
			if ( ! r.ok ) {
				return r.json().then( function ( e ) {
					throw new Error( ( e && e.message ) || 'Error' );
				} );
			}
			return r.json();
		} );
	}

	function countryOptions( selected ) {
		return cfg.countries
			.map( function ( c ) {
				var s = c === selected ? ' selected' : '';
				return '<option value="' + esc( c ) + '"' + s + '>' + esc( c.toUpperCase() ) + '</option>';
			} )
			.join( '' );
	}

	var pricing = cfg.pricing || { perPageFresh: 6, pages: 1, defaultPages: 1, maxPages: 10, minutesMonth: 43200 };
	var schedMinutes = cfg.scheduleMinutes || {};

	function fmt( n ) {
		return ( n || 0 ).toLocaleString();
	}

	function perCheckCost( pages ) {
		return ( pages || pricing.defaultPages || 1 ) * pricing.perPageFresh;
	}

	function monthlyCost( schedule, pages ) {
		var min = schedule in schedMinutes ? schedMinutes[ schedule ] : 1440;
		return min > 0 ? Math.round( ( pricing.minutesMonth / min ) * perCheckCost( pages ) ) : 0;
	}

	function costText( schedule, pages ) {
		var depth = 'Top ' + ( pages || pricing.defaultPages || 1 ) * 10;
		var m = monthlyCost( schedule, pages );
		if ( m <= 0 ) {
			return depth + ' · ' + esc( t.onDemand );
		}
		return depth + ' · ~' + perCheckCost( pages ) + ' ' + esc( t.perCheck ) + ' · ~' + fmt( m ) + ' ' + esc( t.perMonth );
	}

	function pagesOptions( selected ) {
		var max = pricing.maxPages || 10;
		var out = '';
		for ( var i = 1; i <= max; i++ ) {
			out += '<option value="' + i + '"' + ( i === selected ? ' selected' : '' ) + '>Top ' + ( i * 10 ) + '</option>';
		}
		return out;
	}

	function scheduleOptions( selected ) {
		var sched = cfg.schedules || {};
		return Object.keys( sched )
			.map( function ( k ) {
				var s = k === selected ? ' selected' : '';
				return '<option value="' + esc( k ) + '"' + s + '>' + esc( sched[ k ] ) + '</option>';
			} )
			.join( '' );
	}

	function sparkline( ranks ) {
		var pts = ranks.filter( function ( r ) {
			return r != null;
		} );
		if ( pts.length < 2 ) {
			return '<span class="serpcheap-muted">—</span>';
		}
		var w = 84,
			h = 24,
			max = Math.max.apply( null, pts ),
			min = Math.min.apply( null, pts ),
			span = Math.max( 1, max - min ),
			n = ranks.length,
			step = n > 1 ? ( w - 4 ) / ( n - 1 ) : 0,
			coords = [];
		ranks.forEach( function ( r, i ) {
			if ( r == null ) {
				return;
			}
			var x = ( 2 + i * step ).toFixed( 1 );
			var y = ( 2 + ( ( r - min ) / span ) * ( h - 4 ) ).toFixed( 1 );
			coords.push( x + ',' + y );
		} );
		return (
			'<svg class="serpcheap-spark" width="' + w + '" height="' + h + '" viewBox="0 0 ' + w + ' ' + h +
			'" preserveAspectRatio="none" aria-hidden="true"><polyline fill="none" stroke="#2271b1" stroke-width="1.5" points="' +
			coords.join( ' ' ) + '" /></svg>'
		);
	}

	function rankClass( rank ) {
		if ( rank == null ) {
			return 'is-none';
		}
		if ( rank <= 3 ) {
			return 'is-top';
		}
		if ( rank <= 10 ) {
			return 'is-good';
		}
		if ( rank <= 30 ) {
			return 'is-mid';
		}
		return 'is-low';
	}

	function rankBadge( tr ) {
		var label = tr.rank == null ? esc( t.notRanked ) : '#' + tr.rank;
		var delta = '';
		if ( tr.delta_7d != null && tr.delta_7d !== 0 ) {
			var up = tr.delta_7d < 0;
			delta =
				' <span class="' + ( up ? 'serpcheap-up' : 'serpcheap-down' ) + '">' +
				( up ? '▲' : '▼' ) + ' ' + Math.abs( tr.delta_7d ) + '</span>';
		}
		return '<span class="serpcheap-rank-badge ' + rankClass( tr.rank ) + '">' + label + '</span>' + delta;
	}

	function trackerRow( tr ) {
		return (
			'<li class="serpcheap-tracker" data-id="' + tr.id + '">' +
			'<div class="serpcheap-tracker-top"><span class="serpcheap-kw">' + esc( tr.keyword ) + '</span>' + rankBadge( tr ) + '</div>' +
			'<div class="serpcheap-tracker-mid"><span>' + esc( tr.gl.toUpperCase() ) + ' · ' + esc( tr.match_type ) + '</span>' + sparkline( tr.sparkline ) + '</div>' +
			'<div class="serpcheap-tracker-foot">' +
			'<select class="serpcheap-row-sched" title="' + esc( t.schedule ) + '">' + scheduleOptions( tr.schedule ) + '</select>' +
			'<select class="serpcheap-row-pages" title="' + esc( t.estCost ) + '">' + pagesOptions( tr.pages || 1 ) + '</select>' +
			'<span class="serpcheap-tracker-actions">' +
			'<a href="#" data-act="refresh">' + esc( t.refresh ) + '</a> · ' +
			'<a href="#" data-act="remove" class="serpcheap-del">' + esc( t.remove ) + '</a></span></div>' +
			'<div class="serpcheap-cost-mini">' + ( tr.monthly_cost ? '~' + fmt( tr.monthly_cost ) + ' ' + esc( t.perMonth ) : esc( t.onDemand ) ) + '</div></li>'
		);
	}

	function renderMetabox( box ) {
		var type = box.getAttribute( 'data-target-type' );
		var ref = box.getAttribute( 'data-target-ref' ) || '';
		var tax = box.getAttribute( 'data-taxonomy' ) || '';

		var addForm =
			'<div class="serpcheap-add">' +
			'<input type="text" class="serpcheap-f-keyword" placeholder="' + esc( t.keyword ) + '" />' +
			'<div class="serpcheap-add-grid"><select class="serpcheap-f-gl">' + countryOptions( 'us' ) + '</select>' +
			'<select class="serpcheap-f-match"><option value="domain">' + esc( t.domain ) + '</option><option value="exact">' + esc( t.exact ) + '</option></select></div>' +
			'<div class="serpcheap-add-grid"><select class="serpcheap-f-sched">' + scheduleOptions( 'daily' ) + '</select>' +
			'<select class="serpcheap-f-pages">' + pagesOptions( pricing.defaultPages || 1 ) + '</select></div>' +
			'<div class="serpcheap-cost">' + costText( 'daily', pricing.defaultPages || 1 ) + '</div>' +
			'<button type="button" class="button button-primary button-small serpcheap-f-add">' + esc( t.add ) + '</button></div>';

		box.innerHTML = addForm + '<ul class="serpcheap-trackers"><li class="serpcheap-muted">' + esc( t.checking ) + '</li></ul>';
		var list = box.querySelector( '.serpcheap-trackers' );

		function reload() {
			api( 'GET', 'trackers?target_type=' + encodeURIComponent( type ) + '&target_ref=' + encodeURIComponent( ref ) )
				.then( function ( items ) {
					if ( ! items.length ) {
						list.innerHTML = '<li class="serpcheap-muted">' + esc( t.noTrackers ) + '</li>';
						return;
					}
					list.innerHTML = items.map( trackerRow ).join( '' );
				} )
				.catch( function () {
					list.innerHTML = '<li class="serpcheap-muted">—</li>';
				} );
		}

		function refreshCost() {
			var el = box.querySelector( '.serpcheap-cost' );
			if ( el ) {
				el.innerHTML = costText(
					box.querySelector( '.serpcheap-f-sched' ).value,
					parseInt( box.querySelector( '.serpcheap-f-pages' ).value, 10 )
				);
			}
		}
		box.querySelector( '.serpcheap-f-sched' ).addEventListener( 'change', refreshCost );
		box.querySelector( '.serpcheap-f-pages' ).addEventListener( 'change', refreshCost );

		box.querySelector( '.serpcheap-f-add' ).addEventListener( 'click', function () {
			var kw = box.querySelector( '.serpcheap-f-keyword' ).value.trim();
			if ( ! kw ) {
				return;
			}
			var body = {
				target_type: type,
				target_ref: ref,
				taxonomy: tax,
				keyword: kw,
				gl: box.querySelector( '.serpcheap-f-gl' ).value,
				match_type: box.querySelector( '.serpcheap-f-match' ).value,
				schedule: box.querySelector( '.serpcheap-f-sched' ).value,
				pages: parseInt( box.querySelector( '.serpcheap-f-pages' ).value, 10 ),
			};
			this.disabled = true;
			var btn = this;
			api( 'POST', 'trackers', body )
				.then( function () {
					box.querySelector( '.serpcheap-f-keyword' ).value = '';
					reload();
				} )
				.catch( function ( e ) {
					window.alert( e.message );
				} )
				.finally( function () {
					btn.disabled = false;
				} );
		} );

		list.addEventListener( 'change', function ( ev ) {
			var schedSel = ev.target.closest( '.serpcheap-row-sched' );
			var pagesSel = ev.target.closest( '.serpcheap-row-pages' );
			var sel = schedSel || pagesSel;
			if ( ! sel ) {
				return;
			}
			var li = sel.closest( '.serpcheap-tracker' );
			var id = li.getAttribute( 'data-id' );
			var body = schedSel ? { schedule: sel.value } : { pages: parseInt( sel.value, 10 ) };
			li.style.opacity = '0.5';
			api( 'PATCH', 'trackers/' + id, body ).then( reload ).catch( reload );
		} );

		list.addEventListener( 'click', function ( ev ) {
			var a = ev.target.closest( '[data-act]' );
			if ( ! a ) {
				return;
			}
			ev.preventDefault();
			var li = a.closest( '.serpcheap-tracker' );
			var id = li.getAttribute( 'data-id' );
			if ( 'refresh' === a.getAttribute( 'data-act' ) ) {
				li.style.opacity = '0.5';
				api( 'POST', 'trackers/' + id + '/refresh' ).then( reload ).catch( reload );
			} else if ( 'remove' === a.getAttribute( 'data-act' ) ) {
				if ( ! window.confirm( t.confirmDel ) ) {
					return;
				}
				api( 'DELETE', 'trackers/' + id ).then( reload ).catch( reload );
			}
		} );

		reload();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-serpcheap-metabox]' ).forEach( renderMetabox );
	} );
} )();
