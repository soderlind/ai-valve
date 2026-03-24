import { useState, useEffect, useCallback } from '@wordpress/element';
import { Spinner, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchLogs } from '../api';
import LogFilters from './LogFilters';
import LogTable from './LogTable';

export default function Logs() {
	const [ filters, setFilters ] = useState( {
		plugin_slug: '',
		provider_id: '',
		model_id: '',
		context: '',
		status: '',
		date_from: '',
		date_to: '',
	} );
	const [ page, setPage ] = useState( 1 );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const load = useCallback( async () => {
		setLoading( true );
		try {
			const result = await fetchLogs( {
				...filters,
				page,
				per_page: 25,
			} );
			setData( result );
		} catch {
			setData( { items: [], total: 0, totalPages: 0 } );
		}
		setLoading( false );
	}, [ filters, page ] );

	useEffect( () => {
		load();
	}, [ load ] );

	function handleFilter( newFilters ) {
		setFilters( newFilters );
		setPage( 1 );
	}

	// Build CSV export URL (admin-post action).
	const csvParams = new URLSearchParams( {
		action: 'ai_valve_export_csv',
		_wpnonce: window.aiValve?.csvNonce || '',
		filter_plugin: filters.plugin_slug,
		filter_provider: filters.provider_id,
		filter_model: filters.model_id,
		filter_context: filters.context,
		filter_status: filters.status,
		filter_date_from: filters.date_from,
		filter_date_to: filters.date_to,
	} );
	// Remove empty params.
	for ( const [ key, val ] of [ ...csvParams.entries() ] ) {
		if ( ! val ) {
			csvParams.delete( key );
		}
	}
	// Keep action and nonce always.
	csvParams.set( 'action', 'ai_valve_export_csv' );
	if ( window.aiValve?.csvNonce ) {
		csvParams.set( '_wpnonce', window.aiValve.csvNonce );
	}
	const csvUrl = `${ window.aiValve?.adminPostUrl || '/wp-admin/admin-post.php' }?${ csvParams.toString() }`;

	return (
		<div style={ { marginTop: '1em' } }>
			<LogFilters filters={ filters } onChange={ handleFilter } />

			{ loading ? (
				<Spinner />
			) : (
				<>
					<p>
						<strong>
							{ data
								? `${ data.total.toLocaleString() } ${ __(
										'entries found.',
										'ai-valve'
								  ) }`
								: '' }
						</strong>
						{ data && data.total > 0 && (
							<Button
								variant="secondary"
								href={ csvUrl }
								style={ { marginLeft: '1em' } }
							>
								{ __( 'Export CSV', 'ai-valve' ) }
							</Button>
						) }
					</p>

					<LogTable items={ data?.items || [] } />

					{ data && data.totalPages > 1 && (
						<div
							className="tablenav"
							style={ { marginTop: 12 } }
						>
							<div className="tablenav-pages">
								{ Array.from(
									{ length: data.totalPages },
									( _, i ) => i + 1
								).map( ( p ) => (
									<button
										key={ p }
										type="button"
										className={ `button ${
											p === page ? 'button-primary' : ''
										}` }
										onClick={ () => setPage( p ) }
										style={ { margin: '0 2px' } }
									>
										{ p }
									</button>
								) ) }
							</div>
						</div>
					) }
				</>
			) }
		</div>
	);
}
