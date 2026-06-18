import { __ } from '@wordpress/i18n';

export const SCHEDULES = [
	{ value: 'hourly', label: __( 'Every hour', 'serpcheap-cheapest-keyword-rank-tracker' ), minutes: 60 },
	{ value: '6h', label: __( 'Every 6 hours', 'serpcheap-cheapest-keyword-rank-tracker' ), minutes: 360 },
	{ value: '12h', label: __( 'Every 12 hours', 'serpcheap-cheapest-keyword-rank-tracker' ), minutes: 720 },
	{ value: 'daily', label: __( 'Daily', 'serpcheap-cheapest-keyword-rank-tracker' ), minutes: 1440 },
	{ value: 'weekly', label: __( 'Weekly', 'serpcheap-cheapest-keyword-rank-tracker' ), minutes: 10080 },
	{ value: 'manual', label: __( 'Manual only', 'serpcheap-cheapest-keyword-rank-tracker' ), minutes: 0 },
	{ value: 'custom', label: __( 'Custom…', 'serpcheap-cheapest-keyword-rank-tracker' ), minutes: null },
];

export const scheduleLabel = ( schedule, minutes ) => {
	const preset = SCHEDULES.find( ( s ) => s.value === schedule );
	if ( preset && preset.value !== 'custom' ) {
		return preset.label;
	}
	if ( ! minutes ) {
		return __( 'Manual', 'serpcheap-cheapest-keyword-rank-tracker' );
	}
	if ( minutes % 1440 === 0 ) {
		return `${ __( 'Every', 'serpcheap-cheapest-keyword-rank-tracker' ) } ${ minutes / 1440 }d`;
	}
	if ( minutes % 60 === 0 ) {
		return `${ __( 'Every', 'serpcheap-cheapest-keyword-rank-tracker' ) } ${ minutes / 60 }h`;
	}
	return `${ __( 'Every', 'serpcheap-cheapest-keyword-rank-tracker' ) } ${ minutes }m`;
};

export const targetIcon = ( type ) => {
	switch ( type ) {
		case 'post':
			return '📄';
		case 'term':
			return '🏷️';
		case 'home':
			return '🏠';
		case 'url':
			return '🔗';
		default:
			return '•';
	}
};

export const flag = ( gl ) =>
	( gl || '' )
		.toUpperCase()
		.replace( /./g, ( c ) => String.fromCodePoint( 127397 + c.charCodeAt( 0 ) ) );

const PRICING = ( window.serpcheapRT || {} ).pricing || {
	perPageFresh: 6,
	perPageCached: 3,
	pages: 10,
	minutesMonth: 43200,
};

export const defaultPages = () => PRICING.defaultPages || PRICING.pages || 1;
export const maxPages = () => PRICING.maxPages || 10;

export const costPerCheck = ( pages = defaultPages(), fresh = true ) =>
	pages * ( fresh ? PRICING.perPageFresh : PRICING.perPageCached );

export const monthlyCost = ( minutes, perCheck ) => {
	const cost = perCheck === undefined ? costPerCheck() : perCheck;
	return minutes > 0 ? Math.round( ( PRICING.minutesMonth / minutes ) * cost ) : 0;
};

export const fmtCredits = ( n ) => ( n || 0 ).toLocaleString();

export const minutesForSchedule = ( schedule, custom ) => {
	const m = ( window.serpcheapRT || {} ).scheduleMinutes || {};
	if ( schedule === 'custom' ) {
		return custom || 0;
	}
	return schedule in m ? m[ schedule ] : 1440;
};

export const rankClass = ( rank ) => {
	if ( rank === null || rank === undefined ) return 'is-none';
	if ( rank <= 3 ) return 'is-top';
	if ( rank <= 10 ) return 'is-good';
	if ( rank <= 30 ) return 'is-mid';
	return 'is-low';
};
