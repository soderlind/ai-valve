import { __ } from '@wordpress/i18n';

function fmt( n ) {
	return Number( n ).toLocaleString();
}

function fmtDuration( ms ) {
	const n = Number( ms );
	if ( ! n ) {
		return '—';
	}
	if ( n >= 1000 ) {
		return `${ ( n / 1000 ).toFixed( 1 ) }s`;
	}
	return `${ n }ms`;
}

export default function LogTable( { items } ) {
	if ( ! items || ! items.length ) {
		return (
			<p>
				<em>{ __( 'No log entries yet.', 'ai-valve' ) }</em>
			</p>
		);
	}

	return (
		<table className="widefat fixed striped">
			<thead>
				<tr>
					<th>{ __( 'Time', 'ai-valve' ) }</th>
					<th>{ __( 'Plugin', 'ai-valve' ) }</th>
					<th>{ __( 'Provider / Model', 'ai-valve' ) }</th>
					<th>{ __( 'Capability', 'ai-valve' ) }</th>
					<th>{ __( 'Context', 'ai-valve' ) }</th>
					<th style={ { textAlign: 'right' } }>
						{ __( 'Tokens', 'ai-valve' ) }
					</th>
					<th style={ { textAlign: 'right' } }>
						{ __( 'Duration', 'ai-valve' ) }
					</th>
					<th>{ __( 'Status', 'ai-valve' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ items.map( ( row ) => {
					const isDenied = ( row.status || '' ).startsWith(
						'denied'
					);
					return (
						<tr key={ row.id }>
							<td>{ row.created_at }</td>
							<td>
								<code>{ row.plugin_slug }</code>
							</td>
							<td>
								{ row.provider_id }
								{ row.model_id && (
									<>
										<br />
										<small style={ { opacity: 0.7 } }>
											{ row.model_id }
										</small>
									</>
								) }
							</td>
							<td>{ row.capability }</td>
							<td>{ row.context }</td>
							<td style={ { textAlign: 'right' } }>
								{ fmt( row.total_tokens ) }
							</td>
							<td style={ { textAlign: 'right' } }>
								{ fmtDuration( row.duration_ms ) }
							</td>
							<td>
								<span
									style={ {
										color: isDenied
											? '#d63638'
											: '#00a32a',
										fontWeight: isDenied ? 600 : 'normal',
									} }
								>
									{ row.status }
								</span>
							</td>
						</tr>
					);
				} ) }
			</tbody>
		</table>
	);
}
