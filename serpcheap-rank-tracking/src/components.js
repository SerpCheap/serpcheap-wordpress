import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { SCHEDULES, flag, rankClass, targetIcon, scheduleLabel, maxPages } from './lib';

export function PagesSelect( { pages, onChange, compact = false } ) {
	const max = maxPages();
	const opts = [];
	for ( let i = 1; i <= max; i++ ) {
		opts.push( i );
	}
	return (
		<select
			className={ `scrt-pages ${ compact ? 'is-compact' : '' }` }
			value={ pages }
			onChange={ ( e ) => onChange( parseInt( e.target.value, 10 ) ) }
		>
			{ opts.map( ( i ) => (
				<option key={ i } value={ i }>
					{ sprintf( __( 'Top %d', 'serpcheap-rank-tracking' ), i * 10 ) }
				</option>
			) ) }
		</select>
	);
}

export function ScheduleSelect( { schedule, minutes, onChange, compact = false } ) {
	const [ value, setValue ] = useState( schedule || 'daily' );
	const [ custom, setCustom ] = useState( minutes && minutes > 0 ? Math.round( minutes / 60 ) : 3 );

	const apply = ( v, hours ) => {
		if ( v === 'custom' ) {
			onChange( { schedule: 'custom', interval_minutes: Math.max( 1, hours ) * 60 } );
		} else {
			onChange( { schedule: v } );
		}
	};

	return (
		<div className={ `scrt-sched ${ compact ? 'is-compact' : '' }` }>
			<select
				value={ value }
				onChange={ ( e ) => {
					setValue( e.target.value );
					apply( e.target.value, custom );
				} }
			>
				{ SCHEDULES.map( ( s ) => (
					<option key={ s.value } value={ s.value }>
						{ s.label }
					</option>
				) ) }
			</select>
			{ value === 'custom' && (
				<span className="scrt-sched-custom">
					<input
						type="number"
						min="1"
						max="672"
						value={ custom }
						onChange={ ( e ) => {
							const h = parseInt( e.target.value, 10 ) || 1;
							setCustom( h );
							apply( 'custom', h );
						} }
					/>
					<span>{ __( 'hours', 'serpcheap-rank-tracking' ) }</span>
				</span>
			) }
		</div>
	);
}

export function MetricCard( { label, value, sub, accent } ) {
	return (
		<div className={ `scrt-metric ${ accent ? 'is-' + accent : '' }` }>
			<div className="scrt-metric-value">{ value }</div>
			<div className="scrt-metric-label">{ label }</div>
			{ sub && <div className="scrt-metric-sub">{ sub }</div> }
		</div>
	);
}

export function RankBadge( { rank } ) {
	return (
		<span className={ `scrt-rank ${ rankClass( rank ) }` }>
			{ rank === null || rank === undefined ? __( 'Not found', 'serpcheap-rank-tracking' ) : `#${ rank }` }
		</span>
	);
}

export function Delta( { delta } ) {
	if ( delta === null || delta === undefined || delta === 0 ) {
		return <span className="scrt-delta is-flat">—</span>;
	}
	const up = delta < 0; // lower rank number = improved
	return (
		<span className={ `scrt-delta ${ up ? 'is-up' : 'is-down' }` }>
			{ up ? '▲' : '▼' } { Math.abs( delta ) }
		</span>
	);
}

export function TargetCell( { tracker } ) {
	return (
		<div className="scrt-target">
			<span className="scrt-target-icon">{ targetIcon( tracker.target_type ) }</span>
			<span className="scrt-target-meta">
				<span className="scrt-target-label">{ tracker.target_label }</span>
				<span className="scrt-target-url">{ tracker.target_url }</span>
			</span>
		</div>
	);
}

export { flag, scheduleLabel };
