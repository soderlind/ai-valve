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
						'soderlind-aivalve'
					),
				} )
			)
			.finally( () => setLoading( false ) );
	}, [ setNotice ] );

	if ( loading ) {
		return <Spinner />;
	}
	if ( ! settings ) {
		return <p>{ __( 'Unable to load settings.', 'soderlind-aivalve' ) }</p>;
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
				message: __( 'Settings saved.', 'soderlind-aivalve' ),
			} );
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Failed to save settings.', 'soderlind-aivalve' ),
			} );
		}
		setSaving( false );
	}

	return (
		<div style={ { marginTop: '1em' } }>
			{ /* --- General --- */ }
			<h2>{ __( 'General', 'soderlind-aivalve' ) }</h2>
			<table className="form-table">
				<tbody>
					<tr>
						<th scope="row">
							{ __( 'Enable AI Valve', 'soderlind-aivalve' ) }
						</th>
						<td>
							<ToggleControl
								checked={ !! settings.enabled }
								onChange={ ( val ) =>
									update( 'enabled', val )
								}
								label={ __(
									'Intercept and control AI requests',
									'soderlind-aivalve'
								) }
								__nextHasNoMarginBottom
							/>
							<p className="description">
								{ __(
									'When disabled, all AI requests pass through unmonitored.',
									'soderlind-aivalve'
								) }
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							{ __( 'Default policy', 'soderlind-aivalve' ) }
						</th>
						<td>
							<SelectControl
								value={ settings.default_policy }
								options={ [
									{
										label: __( 'Allow', 'soderlind-aivalve' ),
										value: 'allow',
									},
									{
										label: __( 'Deny', 'soderlind-aivalve' ),
										value: 'deny',
									},
								] }
								onChange={ ( val ) =>
									update( 'default_policy', val )
								}
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
							<p className="description">
								{ __(
									'Applies to plugins not individually configured on the Dashboard tab.',
									'soderlind-aivalve'
								) }
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			{ /* --- Contexts --- */ }
			<h2>{ __( 'Allowed Contexts', 'soderlind-aivalve' ) }</h2>
			<p className="description" style={ { marginBottom: 12 } }>
				{ __(
					'Choose which WordPress execution contexts may trigger AI requests.',
					'soderlind-aivalve'
				) }
			</p>
			<table className="form-table">
				<tbody>
					{ [
						[
							'allow_admin',
							__( 'Admin (wp-admin)', 'soderlind-aivalve' ),
							__(
								'Requests originating from the WordPress admin dashboard.',
								'soderlind-aivalve'
							),
						],
						[
							'allow_frontend',
							__( 'Frontend', 'soderlind-aivalve' ),
							__(
								'Requests from the public-facing site.',
								'soderlind-aivalve'
							),
						],
						[
							'allow_cron',
							__( 'WP-Cron', 'soderlind-aivalve' ),
							__(
								'Scheduled background tasks via wp-cron.php.',
								'soderlind-aivalve'
							),
						],
						[
							'allow_rest',
							__( 'REST API', 'soderlind-aivalve' ),
							__(
								'Requests through the WordPress REST API.',
								'soderlind-aivalve'
							),
						],
						[
							'allow_ajax',
							__( 'AJAX', 'soderlind-aivalve' ),
							__(
								'Admin-ajax.php requests.',
								'soderlind-aivalve'
							),
						],
						[
							'allow_cli',
							__( 'WP-CLI', 'soderlind-aivalve' ),
							__(
								'Command-line requests via the wp command.',
								'soderlind-aivalve'
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
										'soderlind-aivalve'
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
			<h2>{ __( 'Global Token Budgets', 'soderlind-aivalve' ) }</h2>
			<p className="description" style={ { marginBottom: 12 } }>
				{ __(
					'Set site-wide token limits. 0 = unlimited.',
					'soderlind-aivalve'
				) }
			</p>
			<table className="form-table">
				<tbody>
					<tr>
						<th scope="row">
							{ __( 'Daily token limit', 'soderlind-aivalve' ) }
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
									'soderlind-aivalve'
								) }
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							{ __( 'Monthly token limit', 'soderlind-aivalve' ) }
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
									'soderlind-aivalve'
								) }
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			{ /* --- Alerts --- */ }
			<h2>{ __( 'Alerts', 'soderlind-aivalve' ) }</h2>
			<table className="form-table">
				<tbody>
					<tr>
						<th scope="row">
							{ __( 'Warning threshold', 'soderlind-aivalve' ) }
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
									'soderlind-aivalve'
								) }
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							{ __( 'Notification email', 'soderlind-aivalve' ) }
						</th>
						<td>
							<TextControl
								type="email"
								value={ settings.alert_email || '' }
								onChange={ ( val ) =>
									update( 'alert_email', val )
								}
								placeholder={
									window.soderlindAivalveAdmin?.adminEmail || ''
								}
								__nextHasNoMarginBottom
							/>
							<p className="description">
								{ __(
									'Receive an email when a budget is exceeded. Leave empty to disable.',
									'soderlind-aivalve'
								) }
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			{ /* --- Log Retention --- */ }
			<h2>{ __( 'Log Retention', 'soderlind-aivalve' ) }</h2>
			<table className="form-table">
				<tbody>
					<tr>
						<th scope="row">
							{ __( 'Retention period', 'soderlind-aivalve' ) }
						</th>
						<td>
							<TextControl
								type="number"
								min={ 0 }
								step={ 1 }
								value={ String(
									settings.log_retention_days || 0
								) }
								onChange={ ( val ) =>
									update(
										'log_retention_days',
										parseInt( val, 10 ) || 0
									)
								}
								__nextHasNoMarginBottom
							/>
							<p className="description">
								{ __(
									'Automatically delete logs older than this many days. 0 = keep forever.',
									'soderlind-aivalve'
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
					{ __( 'Save Changes', 'soderlind-aivalve' ) }
				</Button>
			</p>
		</div>
	);
}
