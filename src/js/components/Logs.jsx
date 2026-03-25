import { useState, useEffect, useCallback } from '@wordpress/element';
import { Spinner, Button, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchLogs, fetchLogFilterOptions, purgeLogs } from '../api';
import LogFilters from './LogFilters';
import LogTable from './LogTable';

function getDateRange( preset ) {
	const today = new Date();
	const fmt = ( d ) => d.toISOString().slice( 0, 10 );
	const to = fmt( today );

	switch ( preset ) {
		case '24h': {
			return { date_from: to, date_to: to };
		}
		case '7d': {
			const from = new Date( today );
			from.setDate( from.getDate() - 6 );
			return { date_from: fmt( from ), date_to: to };
		}
		case '30d': {
			const from = new Date( today );
			from.setDate( from.getDate() - 29 );
			return { date_from: fmt( from ), date_to: to };
		}
		case 'month': {
			return {
				date_from: to.slice( 0, 8 ) + '01',
				date_to: to,
			};
		}
		default:
			return { date_from: '', date_to: '' };
	}
}

export default function Logs( { setNotice } ) {
	const [ timeRange, setTimeRange ] = useState( '' );
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
	const [ purging, setPurging ] = useState( false );
	const [ filterOptions, setFilterOptions ] = useState( { plugins: [], providers: [], models: [] } );

	useEffect( () => {
		fetchLogFilterOptions()
			.then( setFilterOptions )
			.catch( () => {} );
	}, [] );

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
		setTimeRange( '' );
		setPage( 1 );
	}

	function handleTimeRange( value ) {
		setTimeRange( value );
		const range = getDateRange( value );
		setFilters( ( prev ) => ( { ...prev, ...range } ) );
		setPage( 1 );
	}

	async function handlePurge() {
		if (
			! window.confirm(
				__(
					'Permanently delete ALL logged requests? This cannot be undone.',
					'ai-valve'
				)
			)
		) {
			return;
		}
		setPurging( true );
		try {
			await purgeLogs();
			setNotice( {
				type: 'success',
				message: __( 'All logs purged.', 'ai-valve' ),
			} );
			load();
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Failed to purge logs.', 'ai-valve' ),
			} );
		}
		setPurging( false );
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
			<div style={ { display: 'flex', alignItems: 'flex-end', gap: 16, marginBottom: 12 } }>
				<SelectControl
					label={ __( 'Time range', 'ai-valve' ) }
					value={ timeRange }
					options={ [
						{ label: __( 'All time', 'ai-valve' ), value: '' },
						{ label: __( 'Last 24 hours', 'ai-valve' ), value: '24h' },
						{ label: __( 'Last 7 days', 'ai-valve' ), value: '7d' },
						{ label: __( 'Last 30 days', 'ai-valve' ), value: '30d' },
						{ label: __( 'This month', 'ai-valve' ), value: 'month' },
					] }
					onChange={ handleTimeRange }
					__nextHasNoMarginBottom
				/>
			</div>
			<LogFilters filters={ filters } onChange={ handleFilter } filterOptions={ filterOptions } />

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

			{ /* --- Danger Zone --- */ }
			<hr style={ { marginTop: 32 } } />
			<h2 style={ { color: '#d63638' } }>
				{ __( 'Danger Zone', 'ai-valve' ) }
			</h2>
			<p className="description">
				{ __(
					'Permanently delete all logged requests. This action cannot be undone.',
					'ai-valve'
				) }
			</p>
			<p>
				<Button
					variant="secondary"
					isDestructive
					isBusy={ purging }
					disabled={ purging }
					onClick={ handlePurge }
				>
					{ __( 'Purge All Logs', 'ai-valve' ) }
				</Button>
			</p>
		</div>
	);
}
