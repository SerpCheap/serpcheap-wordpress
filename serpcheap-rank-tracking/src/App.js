import { useState, useEffect, useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	listTrackers,
	createTracker,
	updateSchedule,
	refreshTracker,
	deleteTracker,
	getAlerts,
	markAlertsRead,
} from './api';
import { Sparkline, RankChart } from './charts';
import {
	ScheduleSelect,
	PagesSelect,
	MetricCard,
	RankBadge,
	Delta,
	TargetCell,
	flag,
	scheduleLabel,
} from './components';
import { fmtCredits, monthlyCost, minutesForSchedule, costPerCheck, defaultPages } from './lib';

const boot = window.serpcheapRT || {};

function Header( { query, setQuery, onAdd, unread, onAlerts } ) {
	return (
		<header className="scrt-header">
			<div className="scrt-brand">
				<span className="scrt-brand-name">
					serp<span className="scrt-brand-dim">.cheap</span>
				</span>
				<span className="scrt-brand-tag">{ __( 'Rank Tracking', 'serpcheap-rank-tracking' ) }</span>
			</div>
			<div className="scrt-header-actions">
				<input
					className="scrt-search"
					type="search"
					placeholder={ __( 'Search keywords…', 'serpcheap-rank-tracking' ) }
					value={ query }
					onChange={ ( e ) => setQuery( e.target.value ) }
				/>
				<button className="scrt-btn scrt-btn-ghost scrt-bell" onClick={ onAlerts } title={ __( 'Alerts', 'serpcheap-rank-tracking' ) }>
					🔔
					{ unread > 0 && <span className="scrt-bell-badge">{ unread > 99 ? '99+' : unread }</span> }
				</button>
				<button className="scrt-btn scrt-btn-primary" onClick={ onAdd }>
					+ { __( 'Track target', 'serpcheap-rank-tracking' ) }
				</button>
				{ boot.settingsUrl && (
					<a className="scrt-btn scrt-btn-ghost" href={ boot.settingsUrl } title={ __( 'Settings', 'serpcheap-rank-tracking' ) }>
						⚙
					</a>
				) }
			</div>
		</header>
	);
}

function Metrics( { trackers } ) {
	const found = trackers.filter( ( t ) => t.rank !== null && t.rank !== undefined );
	const avg = found.length
		? Math.round( ( found.reduce( ( a, t ) => a + t.rank, 0 ) / found.length ) * 10 ) / 10
		: '—';
	const notFound = trackers.length - found.length;
	const mover = trackers.reduce(
		( best, t ) =>
			t.delta_7d !== null && t.delta_7d !== undefined && t.delta_7d < ( best ? best.delta_7d : 0 )
				? t
				: best,
		null
	);
	const balance = trackers.reduce( ( max, t ) => ( t.balance && t.balance > max ? t.balance : max ), 0 );
	const monthly = trackers.reduce( ( sum, t ) => sum + ( t.monthly_cost || 0 ), 0 );

	return (
		<div className="scrt-metrics">
			<MetricCard label={ __( 'Tracked', 'serpcheap-rank-tracking' ) } value={ trackers.length } />
			<MetricCard label={ __( 'Avg position', 'serpcheap-rank-tracking' ) } value={ avg } accent="primary" />
			<MetricCard
				label={ __( 'Top mover (7d)', 'serpcheap-rank-tracking' ) }
				value={ mover ? `▲ ${ Math.abs( mover.delta_7d ) }` : '—' }
				sub={ mover ? mover.keyword : '' }
				accent="green"
			/>
			<MetricCard
				label={ __( 'Not found', 'serpcheap-rank-tracking' ) }
				value={ notFound }
				accent={ notFound ? 'amber' : '' }
			/>
			<MetricCard
				label={ __( 'Est. credits / month', 'serpcheap-rank-tracking' ) }
				value={ fmtCredits( monthly ) }
				sub={ balance ? `${ fmtCredits( balance ) } ${ __( 'left', 'serpcheap-rank-tracking' ) }` : '' }
				accent="amber"
			/>
		</div>
	);
}

const TYPE_FILTERS = [
	{ value: '', label: __( 'All targets', 'serpcheap-rank-tracking' ) },
	{ value: 'post', label: __( 'Posts & products', 'serpcheap-rank-tracking' ) },
	{ value: 'term', label: __( 'Categories', 'serpcheap-rank-tracking' ) },
	{ value: 'home', label: __( 'Home', 'serpcheap-rank-tracking' ) },
	{ value: 'url', label: __( 'URLs', 'serpcheap-rank-tracking' ) },
];

function Row( { tracker, onSchedule, onRefresh, onDelete, onOpen } ) {
	const [ busy, setBusy ] = useState( false );
	const refresh = async () => {
		setBusy( true );
		await onRefresh( tracker.id );
		setBusy( false );
	};
	return (
		<tr className={ busy ? 'is-busy' : '' }>
			<td className="scrt-c-keyword" data-label={ __( 'Keyword', 'serpcheap-rank-tracking' ) }>
				<button className="scrt-link" onClick={ () => onOpen( tracker ) }>
					{ tracker.keyword }
				</button>
			</td>
			<td className="scrt-c-target" data-label={ __( 'Target', 'serpcheap-rank-tracking' ) }>
				<TargetCell tracker={ tracker } />
			</td>
			<td className="scrt-c-country" data-label={ __( 'Country', 'serpcheap-rank-tracking' ) }>
				<span className="scrt-flag">{ flag( tracker.gl ) }</span> { tracker.gl.toUpperCase() }
			</td>
			<td className="scrt-c-rank" data-label={ __( 'Rank', 'serpcheap-rank-tracking' ) }>
				<RankBadge rank={ tracker.rank } />
			</td>
			<td className="scrt-c-delta" data-label={ __( '7d', 'serpcheap-rank-tracking' ) }>
				<Delta delta={ tracker.delta_7d } />
			</td>
			<td className="scrt-c-trend" data-label={ __( 'Trend', 'serpcheap-rank-tracking' ) }>
				<Sparkline data={ tracker.sparkline } />
			</td>
			<td className="scrt-c-sched" data-label={ __( 'Schedule', 'serpcheap-rank-tracking' ) }>
				<div className="scrt-sched-cell">
					<ScheduleSelect
						compact
						schedule={ tracker.schedule }
						minutes={ tracker.interval_minutes }
						onChange={ ( v ) => onSchedule( tracker.id, v ) }
					/>
					<span className="scrt-cost-hint">
						{ tracker.monthly_cost
							? `~${ fmtCredits( tracker.monthly_cost ) } ${ __( 'cr/mo', 'serpcheap-rank-tracking' ) }`
							: __( 'on demand', 'serpcheap-rank-tracking' ) }
					</span>
				</div>
			</td>
			<td className="scrt-c-actions" data-label="">
				<button className="scrt-icon-btn" title={ __( 'Refresh now', 'serpcheap-rank-tracking' ) } onClick={ refresh } disabled={ busy }>
					{ busy ? '…' : '↻' }
				</button>
				<button className="scrt-icon-btn is-danger" title={ __( 'Remove', 'serpcheap-rank-tracking' ) } onClick={ () => onDelete( tracker.id ) }>
					✕
				</button>
			</td>
		</tr>
	);
}

function CostEstimate( { sched, pages } ) {
	const minutes = sched.interval_minutes != null ? sched.interval_minutes : minutesForSchedule( sched.schedule );
	const perCheck = costPerCheck( pages, true );
	const monthly = monthlyCost( minutes, perCheck );
	const heavy = monthly >= 5000;
	const depth = sprintf( __( 'Top %d', 'serpcheap-rank-tracking' ), pages * 10 );

	if ( minutes <= 0 ) {
		return (
			<div className="scrt-cost-box">
				<span className="scrt-cost-ic">◷</span>
				{ sprintf( __( '%s · no recurring cost — runs only on manual refresh.', 'serpcheap-rank-tracking' ), depth ) }
			</div>
		);
	}

	return (
		<div className={ `scrt-cost-box ${ heavy ? 'is-heavy' : '' }` }>
			<span className="scrt-cost-ic">◉</span>
			<span>
				{ depth } · ≈ <strong>{ perCheck }</strong> { __( 'cr / check', 'serpcheap-rank-tracking' ) } ·{' '}
				{ __( 'up to', 'serpcheap-rank-tracking' ) } <strong>{ fmtCredits( monthly ) }</strong> { __( 'cr / month', 'serpcheap-rank-tracking' ) }
			</span>
			{ heavy && (
				<span className="scrt-cost-warn">{ __( 'High cost — fewer pages or lower frequency reduces it.', 'serpcheap-rank-tracking' ) }</span>
			) }
		</div>
	);
}

function AddModal( { onClose, onCreate } ) {
	const [ type, setType ] = useState( 'home' );
	const [ url, setUrl ] = useState( '' );
	const [ keyword, setKeyword ] = useState( '' );
	const [ gl, setGl ] = useState( ( boot.countries || [ 'us' ] )[ 0 ] );
	const [ match, setMatch ] = useState( 'domain' );
	const [ sched, setSched ] = useState( { schedule: 'daily' } );
	const [ pages, setPages ] = useState( defaultPages() );
	const [ busy, setBusy ] = useState( false );
	const [ err, setErr ] = useState( '' );

	const submit = async () => {
		if ( ! keyword.trim() ) {
			setErr( __( 'Enter a keyword.', 'serpcheap-rank-tracking' ) );
			return;
		}
		setBusy( true );
		setErr( '' );
		try {
			await onCreate( {
				target_type: type,
				target_url: type === 'url' ? url : undefined,
				keyword: keyword.trim(),
				gl,
				match_type: match,
				pages,
				...sched,
			} );
			onClose();
		} catch ( e ) {
			setErr( ( e && e.message ) || __( 'Could not save.', 'serpcheap-rank-tracking' ) );
			setBusy( false );
		}
	};

	return (
		<div className="scrt-modal-backdrop" onClick={ onClose }>
			<div className="scrt-modal" onClick={ ( e ) => e.stopPropagation() }>
				<h2>{ __( 'Track a new target', 'serpcheap-rank-tracking' ) }</h2>
				<p className="scrt-muted">
					{ __( 'Track the home page or any URL. Posts, products and categories are tracked from their own edit screens.', 'serpcheap-rank-tracking' ) }
				</p>
				<label>
					{ __( 'Target', 'serpcheap-rank-tracking' ) }
					<select value={ type } onChange={ ( e ) => setType( e.target.value ) }>
						<option value="home">{ __( 'Home page', 'serpcheap-rank-tracking' ) }</option>
						<option value="url">{ __( 'Custom URL', 'serpcheap-rank-tracking' ) }</option>
					</select>
				</label>
				{ type === 'url' && (
					<label>
						{ __( 'URL', 'serpcheap-rank-tracking' ) }
						<input type="url" placeholder="https://example.com/page" value={ url } onChange={ ( e ) => setUrl( e.target.value ) } />
					</label>
				) }
				<label>
					{ __( 'Keyword', 'serpcheap-rank-tracking' ) }
					<input type="text" placeholder={ __( 'best running shoes', 'serpcheap-rank-tracking' ) } value={ keyword } onChange={ ( e ) => setKeyword( e.target.value ) } />
				</label>
				<div className="scrt-modal-grid">
					<label>
						{ __( 'Country', 'serpcheap-rank-tracking' ) }
						<select value={ gl } onChange={ ( e ) => setGl( e.target.value ) }>
							{ ( boot.countries || [] ).map( ( c ) => (
								<option key={ c } value={ c }>
									{ flag( c ) } { c.toUpperCase() }
								</option>
							) ) }
						</select>
					</label>
					<label>
						{ __( 'Match', 'serpcheap-rank-tracking' ) }
						<select value={ match } onChange={ ( e ) => setMatch( e.target.value ) }>
							<option value="domain">{ __( 'Domain', 'serpcheap-rank-tracking' ) }</option>
							<option value="exact">{ __( 'Exact URL', 'serpcheap-rank-tracking' ) }</option>
						</select>
					</label>
					<label>
						{ __( 'Depth', 'serpcheap-rank-tracking' ) }
						<PagesSelect pages={ pages } onChange={ setPages } />
					</label>
					<label>
						{ __( 'Check', 'serpcheap-rank-tracking' ) }
						<ScheduleSelect schedule="daily" minutes={ 1440 } onChange={ setSched } />
					</label>
				</div>
				<CostEstimate sched={ sched } pages={ pages } />
				{ err && <div className="scrt-error">{ err }</div> }
				<div className="scrt-modal-actions">
					<button className="scrt-btn scrt-btn-ghost" onClick={ onClose }>
						{ __( 'Cancel', 'serpcheap-rank-tracking' ) }
					</button>
					<button className="scrt-btn scrt-btn-primary" onClick={ submit } disabled={ busy }>
						{ busy ? __( 'Saving…', 'serpcheap-rank-tracking' ) : __( 'Track', 'serpcheap-rank-tracking' ) }
					</button>
				</div>
			</div>
		</div>
	);
}

function Drawer( { tracker, onClose, onSchedule, onRefresh } ) {
	const [ busy, setBusy ] = useState( false );
	if ( ! tracker ) return null;
	const refresh = async () => {
		setBusy( true );
		await onRefresh( tracker.id );
		setBusy( false );
	};
	return (
		<div className="scrt-drawer-backdrop" onClick={ onClose }>
			<aside className="scrt-drawer" onClick={ ( e ) => e.stopPropagation() }>
				<div className="scrt-drawer-head">
					<div>
						<h2>{ tracker.keyword }</h2>
						<TargetCell tracker={ tracker } />
					</div>
					<button className="scrt-icon-btn" onClick={ onClose }>
						✕
					</button>
				</div>
				<div className="scrt-drawer-stats">
					<div>
						<span className="scrt-muted">{ __( 'Position', 'serpcheap-rank-tracking' ) }</span>
						<RankBadge rank={ tracker.rank } />
					</div>
					<div>
						<span className="scrt-muted">{ __( 'Change 7d', 'serpcheap-rank-tracking' ) }</span>
						<Delta delta={ tracker.delta_7d } />
					</div>
					<div>
						<span className="scrt-muted">{ __( 'Country', 'serpcheap-rank-tracking' ) }</span>
						<span>{ flag( tracker.gl ) } { tracker.gl.toUpperCase() }</span>
					</div>
					<div>
						<span className="scrt-muted">{ __( 'Est. / month', 'serpcheap-rank-tracking' ) }</span>
						<span>
							{ tracker.monthly_cost
								? `${ fmtCredits( tracker.monthly_cost ) } ${ __( 'cr', 'serpcheap-rank-tracking' ) }`
								: '—' }
						</span>
					</div>
				</div>
				<RankChart history={ tracker.history } />
				<div className="scrt-drawer-controls">
					<label>
						{ __( 'Check frequency', 'serpcheap-rank-tracking' ) }
						<ScheduleSelect
							schedule={ tracker.schedule }
							minutes={ tracker.interval_minutes }
							onChange={ ( v ) => onSchedule( tracker.id, v ) }
						/>
					</label>
					<label>
						{ __( 'Search depth', 'serpcheap-rank-tracking' ) }
						<PagesSelect pages={ tracker.pages || 1 } onChange={ ( p ) => onSchedule( tracker.id, { pages: p } ) } />
					</label>
					<button className="scrt-btn scrt-btn-primary" onClick={ refresh } disabled={ busy }>
						{ busy ? __( 'Checking…', 'serpcheap-rank-tracking' ) : __( 'Refresh now', 'serpcheap-rank-tracking' ) }
					</button>
				</div>
			</aside>
		</div>
	);
}

function AlertsDrawer( { data, onClose, onMarkRead } ) {
	const items = data.items || [];
	return (
		<div className="scrt-drawer-backdrop" onClick={ onClose }>
			<aside className="scrt-drawer scrt-alerts" onClick={ ( e ) => e.stopPropagation() }>
				<div className="scrt-drawer-head">
					<h2>{ __( 'Alerts', 'serpcheap-rank-tracking' ) }</h2>
					<div>
						{ items.length > 0 && (
							<button className="scrt-btn scrt-btn-ghost" onClick={ onMarkRead }>
								{ __( 'Mark all read', 'serpcheap-rank-tracking' ) }
							</button>
						) }
						<button className="scrt-icon-btn" onClick={ onClose }>✕</button>
					</div>
				</div>
				{ items.length === 0 ? (
					<div className="scrt-empty">{ __( 'No alerts. Rank changes will show up here.', 'serpcheap-rank-tracking' ) }</div>
				) : (
					<ul className="scrt-alert-list">
						{ items.map( ( a ) => (
							<li key={ a.id } className={ `scrt-alert is-${ a.severity } ${ a.is_read ? 'is-read' : '' }` }>
								<span className="scrt-alert-dot" />
								<div className="scrt-alert-body">
									<div className="scrt-alert-kw">{ a.keyword }</div>
									<div className="scrt-alert-msg">{ a.message }</div>
								</div>
							</li>
						) ) }
					</ul>
				) }
				{ boot.settingsUrl && (
					<p className="scrt-alerts-foot">
						<a href={ boot.settingsUrl }>{ __( 'Configure alert rules →', 'serpcheap-rank-tracking' ) }</a>
					</p>
				) }
			</aside>
		</div>
	);
}

export default function App() {
	const [ trackers, setTrackers ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ query, setQuery ] = useState( '' );
	const [ typeFilter, setTypeFilter ] = useState( '' );
	const [ adding, setAdding ] = useState( false );
	const [ open, setOpen ] = useState( null );
	const [ alerts, setAlerts ] = useState( { items: [], unread: 0 } );
	const [ showAlerts, setShowAlerts ] = useState( false );

	const load = async () => {
		setLoading( true );
		try {
			setTrackers( await listTrackers() );
		} catch ( e ) {
			setTrackers( [] );
		}
		setLoading( false );
	};

	const loadAlerts = async () => {
		try {
			setAlerts( await getAlerts() );
		} catch ( e ) {
			setAlerts( { items: [], unread: 0 } );
		}
	};

	useEffect( () => {
		load();
		loadAlerts();
	}, [] );

	const markAlerts = async () => {
		await markAlertsRead();
		setAlerts( ( a ) => ( { items: a.items.map( ( i ) => ( { ...i, is_read: true } ) ), unread: 0 } ) );
	};

	const patch = ( view ) => {
		setTrackers( ( prev ) => prev.map( ( t ) => ( t.id === view.id ? view : t ) ) );
		setOpen( ( o ) => ( o && o.id === view.id ? view : o ) );
	};

	const onSchedule = async ( id, data ) => patch( await updateSchedule( id, data ) );
	const onRefresh = async ( id ) => patch( await refreshTracker( id ) );
	const onCreate = async ( data ) => {
		const view = await createTracker( data );
		setTrackers( ( prev ) => [ view, ...prev ] );
	};
	const onDelete = async ( id ) => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Remove this tracker and its history?', 'serpcheap-rank-tracking' ) ) ) {
			return;
		}
		await deleteTracker( id );
		setTrackers( ( prev ) => prev.filter( ( t ) => t.id !== id ) );
	};

	const filtered = useMemo( () => {
		const q = query.trim().toLowerCase();
		return trackers.filter( ( t ) => {
			if ( typeFilter && t.target_type !== typeFilter ) return false;
			if ( q && ! ( t.keyword.toLowerCase().includes( q ) || ( t.target_label || '' ).toLowerCase().includes( q ) ) )
				return false;
			return true;
		} );
	}, [ trackers, query, typeFilter ] );

	return (
		<div className="scrt-app">
			<Header
				query={ query }
				setQuery={ setQuery }
				onAdd={ () => setAdding( true ) }
				unread={ alerts.unread }
				onAlerts={ () => setShowAlerts( true ) }
			/>

			{ boot.mock && (
				<div className="scrt-demo-banner">
					<strong>{ __( 'Demo mode', 'serpcheap-rank-tracking' ) }</strong>{' '}
					{ __( 'ranks are mocked locally so you can test the full experience without a serp.cheap account.', 'serpcheap-rank-tracking' ) }
				</div>
			) }

			{ ! boot.mock && ! boot.connected && (
				<div className="scrt-connect-banner">
					<span>
						<strong>{ __( 'Not connected.', 'serpcheap-rank-tracking' ) }</strong>{' '}
						{ __( 'Connect your serp.cheap account to start tracking real Google rankings.', 'serpcheap-rank-tracking' ) }
					</span>
					{ boot.settingsUrl && (
						<a className="scrt-btn scrt-btn-primary" href={ boot.settingsUrl }>
							{ __( 'Connect →', 'serpcheap-rank-tracking' ) }
						</a>
					) }
				</div>
			) }

			<Metrics trackers={ trackers } />

			<div className="scrt-toolbar">
				<div className="scrt-tabs">
					{ TYPE_FILTERS.map( ( f ) => (
						<button
							key={ f.value }
							className={ `scrt-tab ${ typeFilter === f.value ? 'is-active' : '' }` }
							onClick={ () => setTypeFilter( f.value ) }
						>
							{ f.label }
						</button>
					) ) }
				</div>
				<span className="scrt-count">
					{ filtered.length } { __( 'of', 'serpcheap-rank-tracking' ) } { trackers.length }
				</span>
			</div>

			<div className="scrt-panel">
				{ loading ? (
					<div className="scrt-empty">{ __( 'Loading…', 'serpcheap-rank-tracking' ) }</div>
				) : filtered.length === 0 ? (
					<div className="scrt-empty">
						{ __( 'No trackers yet. Add a target, or track keywords from a post/product edit screen.', 'serpcheap-rank-tracking' ) }
					</div>
				) : (
					<table className="scrt-table">
						<thead>
							<tr>
								<th>{ __( 'Keyword', 'serpcheap-rank-tracking' ) }</th>
								<th>{ __( 'Target', 'serpcheap-rank-tracking' ) }</th>
								<th>{ __( 'Country', 'serpcheap-rank-tracking' ) }</th>
								<th>{ __( 'Rank', 'serpcheap-rank-tracking' ) }</th>
								<th>{ __( '7d', 'serpcheap-rank-tracking' ) }</th>
								<th>{ __( 'Trend', 'serpcheap-rank-tracking' ) }</th>
								<th>{ __( 'Schedule', 'serpcheap-rank-tracking' ) }</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							{ filtered.map( ( t ) => (
								<Row
									key={ t.id }
									tracker={ t }
									onSchedule={ onSchedule }
									onRefresh={ onRefresh }
									onDelete={ onDelete }
									onOpen={ setOpen }
								/>
							) ) }
						</tbody>
					</table>
				) }
			</div>

			{ adding && <AddModal onClose={ () => setAdding( false ) } onCreate={ onCreate } /> }
			{ open && (
				<Drawer
					tracker={ open }
					onClose={ () => setOpen( null ) }
					onSchedule={ onSchedule }
					onRefresh={ onRefresh }
				/>
			) }
			{ showAlerts && (
				<AlertsDrawer data={ alerts } onClose={ () => setShowAlerts( false ) } onMarkRead={ markAlerts } />
			) }
		</div>
	);
}
