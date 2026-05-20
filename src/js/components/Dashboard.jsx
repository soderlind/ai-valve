import { useState, useEffect, useCallback } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchUsage, fetchSettings } from '../api';
import SummaryCards from './SummaryCards';
import PluginTable from './PluginTable';
import ProviderModelTable from './ProviderModelTable';
import ContextTable from './ContextTable';
import RecentRequests from './RecentRequests';

export default function Dashboard( { setNotice } ) {
	const [ usage, setUsage ] = useState( null );
	const [ settings, setSettings ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const load = useCallback( async () => {
		try {
			const [ u, s ] = await Promise.all( [
				fetchUsage(),
				fetchSettings(),
			] );
			setUsage( u );
			setSettings( s );
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Failed to load dashboard data.', 'ai-valve' ),
			} );
		}
		setLoading( false );
	}, [ setNotice ] );

	useEffect( () => {
		load();
	}, [ load ] );

	if ( loading ) {
		return <Spinner />;
	}
	if ( ! usage || ! settings ) {
		return (
			<p>
				{ __(
					'Unable to load dashboard data. Please try refreshing the page.',
					'ai-valve'
				) }
			</p>
		);
	}

	// Build plugin usage index.
	const pluginUsage = {};
	( usage.by_plugin || [] ).forEach( ( row ) => {
		pluginUsage[ row.plugin_slug ] = row;
	} );

	// Known slugs: merge settings + usage data.
	const knownSlugs = [
		...new Set( [
			...Object.keys( settings.plugin_policies || {} ),
			...Object.keys( settings.plugin_budgets || {} ),
			...( usage.known_slugs || [] ),
		] ),
	].sort();

	return (
		<div style={ { marginTop: '1em' } }>
			<SummaryCards
				daily={ usage.daily }
				monthly={ usage.monthly }
				budgets={ usage.budgets }
				threshold={ settings.alert_threshold_pct || 80 }
			/>

			<PluginTable
				knownSlugs={ knownSlugs }
				settings={ settings }
				pluginUsage={ pluginUsage }
				setNotice={ setNotice }
				onSaved={ load }
			/>

			{ ( ( usage.by_provider_model && usage.by_provider_model.length ) ||
				( usage.by_context && usage.by_context.length ) ) && (
				<div
					style={ {
						display: 'flex',
						flexWrap: 'wrap',
						gap: 24,
						alignItems: 'flex-start',
					} }
				>
					<ProviderModelTable
						data={ usage.by_provider_model }
					/>
					<ContextTable data={ usage.by_context } />
				</div>
			) }

			<RecentRequests items={ usage.recent } />
		</div>
	);
}
