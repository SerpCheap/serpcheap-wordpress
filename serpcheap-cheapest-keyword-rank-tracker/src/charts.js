const points = ( values ) => values.filter( ( v ) => v !== null && v !== undefined );

export function Sparkline( { data, width = 96, height = 28 } ) {
	const vals = points( data );
	if ( vals.length < 2 ) {
		return <span className="scrt-spark-empty">—</span>;
	}
	const min = Math.min( ...vals );
	const max = Math.max( ...vals );
	const span = max - min || 1;
	const step = width / ( data.length - 1 );

	const coords = data.map( ( v, i ) => {
		if ( v === null || v === undefined ) return null;
		const x = i * step;
		const y = height - ( ( max - v ) / span ) * ( height - 4 ) - 2;
		return [ x, y ];
	} );

	const line = coords
		.filter( Boolean )
		.map( ( [ x, y ], i ) => `${ i === 0 ? 'M' : 'L' }${ x.toFixed( 1 ) },${ y.toFixed( 1 ) }` )
		.join( ' ' );

	const last = coords.filter( Boolean ).slice( -1 )[ 0 ];

	return (
		<svg className="scrt-spark" width={ width } height={ height } viewBox={ `0 0 ${ width } ${ height }` }>
			<path d={ line } fill="none" />
			{ last && <circle cx={ last[ 0 ] } cy={ last[ 1 ] } r="2.5" /> }
		</svg>
	);
}

export function RankChart( { history, width = 520, height = 200 } ) {
	const rows = ( history || [] ).filter( ( h ) => h.rank !== null && h.rank !== undefined );
	if ( rows.length < 2 ) {
		return <div className="scrt-chart-empty">Not enough history yet.</div>;
	}
	const vals = rows.map( ( r ) => r.rank );
	const min = Math.min( ...vals );
	const max = Math.max( ...vals );
	const span = max - min || 1;
	const pad = { l: 34, r: 12, t: 14, b: 22 };
	const iw = width - pad.l - pad.r;
	const ih = height - pad.t - pad.b;
	const step = iw / ( history.length - 1 );

	const yFor = ( rank ) => pad.t + ( ( rank - min ) / span ) * ih;
	const coords = history.map( ( h, i ) =>
		h.rank === null || h.rank === undefined ? null : [ pad.l + i * step, yFor( h.rank ) ]
	);
	const line = coords
		.filter( Boolean )
		.map( ( [ x, y ], i ) => `${ i === 0 ? 'M' : 'L' }${ x.toFixed( 1 ) },${ y.toFixed( 1 ) }` )
		.join( ' ' );
	const area = `${ line } L${ ( pad.l + ( history.length - 1 ) * step ).toFixed( 1 ) },${ pad.t + ih } L${ pad.l },${ pad.t + ih } Z`;

	const ticks = [ min, Math.round( ( min + max ) / 2 ), max ].filter( ( v, i, a ) => a.indexOf( v ) === i );

	return (
		<svg className="scrt-chart" width="100%" viewBox={ `0 0 ${ width } ${ height }` } preserveAspectRatio="none">
			{ ticks.map( ( t ) => (
				<g key={ t }>
					<line className="scrt-grid" x1={ pad.l } x2={ width - pad.r } y1={ yFor( t ) } y2={ yFor( t ) } />
					<text className="scrt-axis" x={ pad.l - 6 } y={ yFor( t ) + 3 } textAnchor="end">
						#{ t }
					</text>
				</g>
			) ) }
			<path className="scrt-area" d={ area } />
			<path className="scrt-line" d={ line } fill="none" />
			{ coords.filter( Boolean ).map( ( [ x, y ], i ) => (
				<circle key={ i } className="scrt-dot" cx={ x } cy={ y } r="2.5" />
			) ) }
		</svg>
	);
}
