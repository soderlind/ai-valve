import { useState } from '@wordpress/element';
import { Button, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { saveSettings } from '../api';

function fmt( n ) {
	return Number( n ).toLocaleString();
}

export default function PluginTable( {
	knownSlugs,
	settings,
	pluginUsage,
	setNotice,
	onSaved,
} ) {
	const policies = { ...( settings.plugin_policies || {} ) };
	const budgets = { ...( settings.plugin_budgets || {} ) };
	const defaultPolicy = settings.default_policy || 'allow';

	// Local editable state.
	const [ localPolicies, setLocalPolicies ] = useState( () => {
		const obj = {};
		knownSlugs.forEach( ( slug ) => {
			obj[ slug ] = policies[ slug ] || defaultPolicy;
		} );
		return obj;
	} );
	const [ localBudgets, setLocalBudgets ] = useState( () => {
		const obj = {};
		knownSlugs.forEach( ( slug ) => {
			obj[ slug ] = {
				daily: budgets[ slug ]?.daily || 0,
				monthly: budgets[ slug ]?.monthly || 0,
			};
		} );
		return obj;
	} );
	const [ saving, setSaving ] = useState( false );

	// Find max tokens for bar sizing.
	const maxTokens = Math.max(
		...knownSlugs.map(
			( s ) => pluginUsage[ s ]?.total_tokens || 0
		),
		0
	);

	async function handleSave() {
		setSaving( true );
		try {
			const pluginPolicies = {};
			const pluginBudgets = {};
			knownSlugs.forEach( ( slug ) => {
				pluginPolicies[ slug ] = localPolicies[ slug ] || defaultPolicy;
				pluginBudgets[ slug ] = {
					daily: parseInt( localBudgets[ slug ]?.daily, 10 ) || 0,
					monthly:
						parseInt( localBudgets[ slug ]?.monthly, 10 ) || 0,
				};
			} );
			await saveSettings( {
				...settings,
				plugin_policies: pluginPolicies,
				plugin_budgets: pluginBudgets,
			} );
			setNotice( {
				type: 'success',
				message: __( 'Plugin settings saved.', 'soderlind-aivalve' ),
			} );
			if ( onSaved ) {
				onSaved();
			}
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Failed to save plugin settings.', 'soderlind-aivalve' ),
			} );
		}
		setSaving( false );
	}

	if ( ! knownSlugs.length ) {
		return (
			<p>
				<em>
					{ __(
						'No plugins have made AI requests yet. They will appear here automatically.',
						'soderlind-aivalve'
					) }
				</em>
			</p>
		);
	}

	return (
		<>
			<h2>{ __( 'Plugins', 'soderlind-aivalve' ) }</h2>
			<p className="description" style={ { marginBottom: 12 } }>
				{ __(
					"Control each plugin's access to the AI connector and set token budgets. Plugins appear automatically after their first AI request. Limits are in tokens — set to 0 for unlimited.",
					'soderlind-aivalve'
				) }
			</p>
			<table className="widefat fixed striped">
				<thead>
					<tr>
						<th style={ { width: '22%' } }>
							{ __( 'Plugin', 'soderlind-aivalve' ) }
						</th>
						<th style={ { width: '10%' } }>
							{ __( 'Access', 'soderlind-aivalve' ) }
						</th>
						<th style={ { width: '14%', textAlign: 'right' } }>
							{ __( 'Requests', 'soderlind-aivalve' ) }
						</th>
						<th style={ { width: '14%', textAlign: 'right' } }>
							{ __( 'Tokens used', 'soderlind-aivalve' ) }
						</th>
						<th style={ { width: '20%' } }>
							{ __( 'Daily token limit', 'soderlind-aivalve' ) }
						</th>
						<th style={ { width: '20%' } }>
							{ __( 'Monthly token limit', 'soderlind-aivalve' ) }
						</th>
					</tr>
				</thead>
				<tbody>
					{ knownSlugs.map( ( slug ) => {
						const usage = pluginUsage[ slug ] || {
							request_count: 0,
							total_tokens: 0,
						};
						const barPct =
							maxTokens > 0 && usage.total_tokens > 0
								? Math.round(
										( usage.total_tokens / maxTokens ) *
											100
								  )
								: 0;
						return (
							<tr key={ slug }>
								<td>
									<code>{ slug }</code>
								</td>
								<td>
									<SelectControl
										value={
											localPolicies[ slug ] ||
											defaultPolicy
										}
										options={ [
											{
												label: __(
													'Allow',
													'soderlind-aivalve'
												),
												value: 'allow',
											},
											{
												label: __(
													'Deny',
													'soderlind-aivalve'
												),
												value: 'deny',
											},
										] }
										onChange={ ( val ) =>
											setLocalPolicies( ( p ) => ( {
												...p,
												[ slug ]: val,
											} ) )
										}
										__nextHasNoMarginBottom
										__next40pxDefaultSize
									/>
								</td>
								<td style={ { textAlign: 'right' } }>
									{ fmt( usage.request_count ) }
								</td>
								<td style={ { textAlign: 'right' } }>
									{ fmt( usage.total_tokens ) }
									{ barPct > 0 && (
										<div
											className="soderlind-aivalve-bar-wrap"
											style={ { marginTop: 4 } }
										>
											<div
												className="soderlind-aivalve-bar soderlind-aivalve-bar--ok"
												style={ {
													width: `${ barPct }%`,
												} }
											/>
										</div>
									) }
								</td>
								<td>
									<input
										type="number"
										min="0"
										step="1"
										value={
											localBudgets[ slug ]?.daily || 0
										}
										onChange={ ( e ) =>
											setLocalBudgets( ( b ) => ( {
												...b,
												[ slug ]: {
													...b[ slug ],
													daily: e.target.value,
												},
											} ) )
										}
										className="small-text"
										style={ { width: '100%' } }
										placeholder="0"
										title={ __(
											'0 = unlimited',
											'soderlind-aivalve'
										) }
									/>
								</td>
								<td>
									<input
										type="number"
										min="0"
										step="1"
										value={
											localBudgets[ slug ]?.monthly || 0
										}
										onChange={ ( e ) =>
											setLocalBudgets( ( b ) => ( {
												...b,
												[ slug ]: {
													...b[ slug ],
													monthly: e.target.value,
												},
											} ) )
										}
										className="small-text"
										style={ { width: '100%' } }
										placeholder="0"
										title={ __(
											'0 = unlimited',
											'soderlind-aivalve'
										) }
									/>
								</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>
			<p className="description" style={ { marginTop: 4 } }>
				{ __(
					'Token limits: 0 = no limit. When a limit is reached, further AI requests from that plugin are blocked until the next day or month.',
					'soderlind-aivalve'
				) }
			</p>
			<p>
				<Button
					variant="primary"
					isBusy={ saving }
					disabled={ saving }
					onClick={ handleSave }
				>
					{ __( 'Save Changes', 'soderlind-aivalve' ) }
				</Button>
			</p>
		</>
	);
}
