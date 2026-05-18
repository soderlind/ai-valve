import { __ } from '@wordpress/i18n';

function fmt( n ) {
	return Number( n ).toLocaleString();
}

export default function ContextTable( { data } ) {
	if ( ! data || ! data.length ) {
		return null;
	}
	return (
		<div
			style={ {
				flex: '1 1 calc(50% - 12px)',
				minWidth: 280,
				overflow: 'auto',
			} }
		>
			<h2 style={ { marginTop: 0 } }>
				{ __( 'Contexts (This Month)', 'soderlind-aivalve' ) }
			</h2>
			<table
				className="widefat striped"
				style={ { width: '100%', tableLayout: 'auto' } }
			>
				<thead>
					<tr>
						<th>{ __( 'Context', 'soderlind-aivalve' ) }</th>
						<th style={ { textAlign: 'right' } }>
							{ __( 'Requests', 'soderlind-aivalve' ) }
						</th>
						<th style={ { textAlign: 'right' } }>
							{ __( 'Tokens', 'soderlind-aivalve' ) }
						</th>
					</tr>
				</thead>
				<tbody>
					{ data.map( ( row, i ) => (
						<tr key={ i }>
							<td>{ row.context }</td>
							<td style={ { textAlign: 'right' } }>
								{ fmt( row.request_count ) }
							</td>
							<td style={ { textAlign: 'right' } }>
								{ fmt( row.total_tokens ) }
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}
