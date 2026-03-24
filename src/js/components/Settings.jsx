import { useState, useEffect } from '@wordpress/element';
import {
	Button,
	ToggleControl,
	SelectControl,
	TextControl,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchSettings, saveSettings } from '../api';

export default function Settings( { setNotice } ) {
	const [ settings, setSettings ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		fetchSettings()
			.then( ( s ) => setSettings( s ) )
			.catch( () =>
				setNotice( {
					type: 'error',
					message: __(
						'Failed to load settings.',
						'ai-valve'
					),
				} )
			)
			.finally( () => setLoading( false ) );
	}, [ setNotice ] );

	if ( loading ) {
		return <Spinner />;
	}
	if ( ! settings ) {
		return <p>{ __( 'Unable to load settings.', 'ai-valve' ) }</p>;
	}

	function update( key, value ) {
		setSettings( ( prev ) => ( { ...prev, [ key ]: value } ) );
	}

	async function handleSave() {
		setSaving( true );
		try {
			const result = await saveSettings( settings );
			setSettings( result.settings );
			setNotice( {
				type: 'success',
				message: __( 'Settings saved.', 'ai-valve' ),
			} );
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Failed to save settings.', 'ai-valve' ),
			} );
		}
		setSaving( false );
	}

	return (
		<div style={ { marginTop: '1em' } }>
			{ /* --- General --- */ }
			<h2>{ __( 'General', 'ai-valve' ) }</h2>
			<table className="form-table">
				<tbody>
					<tr>
						<th scope="row">
							{ __( 'Enable AI Valve', 'ai-valve' ) }
						</th>
						<td>
							<ToggleControl
								checked={ !! settings.enabled }
								onChange={ ( val ) =>
									update( 'enabled', val )
								}
								label={ __(
									'Intercept and control AI requests',
									'ai-valve'
								) }
								__nextHasNoMarginBottom
							/>
							<p className="description">
								{ __(
									'When disabled, all AI requests pass through unmonitored.',
									'ai-valve'
								) }
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							{ __( 'Default policy', 'ai-valve' ) }
						</th>
						<td>
							<SelectControl
								value={ settings.default_policy }
								options={ [
									{
										label: __( 'Allow', 'ai-valve' ),
										value: 'allow',
									},
									{
										label: __( 'Deny', 'ai-valve' ),
										value: 'deny',
									},
								] }
								onChange={ ( val ) =>
									update( 'default_policy', val )
								}
								__nextHasNoMarginBottom
							/>
							<p className="description">
								{ __(
									'Applies to plugins not individually configured on the Dashboard tab.',
									'ai-valve'
								) }
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			{ /* --- Contexts --- */ }
			<h2>{ __( 'Allowed Contexts', 'ai-valve' ) }</h2>
			<p className="description" style={ { marginBottom: 12 } }>
				{ __(
					'Choose which WordPress execution contexts may trigger AI requests.',
					'ai-valve'
				) }
			</p>
			<table className="form-table">
				<tbody>
					{ [
						[
							'allow_admin',
							__( 'Admin (wp-admin)', 'ai-valve' ),
							__(
								'Requests originating from the WordPress admin dashboard.',
								'ai-valve'
							),
						],
						[
							'allow_frontend',
							__( 'Frontend', 'ai-valve' ),
							__(
								'Requests from the public-facing site.',
								'ai-valve'
							),
						],
						[
							'allow_cron',
							__( 'WP-Cron', 'ai-valve' ),
							__(
								'Scheduled background tasks via wp-cron.php.',
								'ai-valve'
							),
						],
						[
							'allow_rest',
							__( 'REST API', 'ai-valve' ),
							__(
								'Requests through the WordPress REST API.',
								'ai-valve'
							),
						],
						[
							'allow_ajax',
							__( 'AJAX', 'ai-valve' ),
							__(
								'Admin-ajax.php requests.',
								'ai-valve'
							),
						],
						[
							'allow_cli',
							__( 'WP-CLI', 'ai-valve' ),
							__(
								'Command-line requests via the wp command.',
								'ai-valve'
							),
						],
					].map( ( [ key, label, desc ] ) => (
						<tr key={ key }>
							<th scope="row">{ label }</th>
							<td>
								<ToggleControl
									checked={ !! settings[ key ] }
									onChange={ ( val ) =>
										update( key, val )
									}
									label={ __(
										'Allow AI requests',
										'ai-valve'
									) }
									__nextHasNoMarginBottom
								/>
								<p className="description">{ desc }</p>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>

			{ /* --- Budgets --- */ }
			<h2>{ __( 'Global Token Budgets', 'ai-valve' ) }</h2>
			<p className="description" style={ { marginBottom: 12 } }>
				{ __(
					'Set site-wide token limits. 0 = unlimited.',
					'ai-valve'
				) }
			</p>
			<table className="form-table">
				<tbody>
					<tr>
						<th scope="row">
							{ __( 'Daily token limit', 'ai-valve' ) }
						</th>
						<td>
							<TextControl
								type="number"
								min={ 0 }
								step={ 1 }
								value={ String(
									settings.global_daily_limit || 0
								) }
								onChange={ ( val ) =>
									update(
										'global_daily_limit',
										parseInt( val, 10 ) || 0
									)
								}
								__nextHasNoMarginBottom
							/>
							<p className="description">
								{ __(
									'Maximum tokens all plugins combined may use per day.',
									'ai-valve'
								) }
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							{ __( 'Monthly token limit', 'ai-valve' ) }
						</th>
						<td>
							<TextControl
								type="number"
								min={ 0 }
								step={ 1 }
								value={ String(
									settings.global_monthly_limit || 0
								) }
								onChange={ ( val ) =>
									update(
										'global_monthly_limit',
										parseInt( val, 10 ) || 0
									)
								}
								__nextHasNoMarginBottom
							/>
							<p className="description">
								{ __(
									'Maximum tokens all plugins combined may use per calendar month.',
									'ai-valve'
								) }
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			{ /* --- Alerts --- */ }
			<h2>{ __( 'Alerts', 'ai-valve' ) }</h2>
			<table className="form-table">
				<tbody>
					<tr>
						<th scope="row">
							{ __( 'Warning threshold', 'ai-valve' ) }
						</th>
						<td>
							<TextControl
								type="number"
								min={ 1 }
								max={ 100 }
								step={ 1 }
								value={ String(
									settings.alert_threshold_pct || 80
								) }
								onChange={ ( val ) =>
									update(
										'alert_threshold_pct',
										Math.max(
											1,
											Math.min(
												100,
												parseInt( val, 10 ) || 80
											)
										)
									)
								}
								suffix="%"
								__nextHasNoMarginBottom
							/>
							<p className="description">
								{ __(
									'Show an admin notice when token usage reaches this percentage of any budget.',
									'ai-valve'
								) }
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							{ __( 'Notification email', 'ai-valve' ) }
						</th>
						<td>
							<TextControl
								type="email"
								value={ settings.alert_email || '' }
								onChange={ ( val ) =>
									update( 'alert_email', val )
								}
								placeholder={
									window.aiValve?.adminEmail || ''
								}
								__nextHasNoMarginBottom
							/>
							<p className="description">
								{ __(
									'Receive an email when a budget is exceeded. Leave empty to disable.',
									'ai-valve'
								) }
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<p className="submit">
				<Button
					variant="primary"
					isBusy={ saving }
					disabled={ saving }
					onClick={ handleSave }
				>
					{ __( 'Save Changes', 'ai-valve' ) }
				</Button>
			</p>
		</div>
	);
}
