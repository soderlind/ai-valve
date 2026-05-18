import { __ } from '@wordpress/i18n';

/**
 * Format a number with locale-aware thousands separators.
 */
function fmt( n ) {
	return Number( n ).toLocaleString();
}

/**
 * Budget progress bar matching the PHP version's appearance.
 */
function BudgetBar( { used, limit, label, threshold = 80 } ) {
	if ( ! limit || limit <= 0 ) {
		return null;
	}
	const pct = Math.min( 100, Math.round( ( used / limit ) * 100 ) );
	let cls = 'soderlind-aivalve-bar soderlind-aivalve-bar--ok';
	if ( pct >= 100 ) {
		cls = 'soderlind-aivalve-bar soderlind-aivalve-bar--over';
	} else if ( pct >= threshold ) {
		cls = 'soderlind-aivalve-bar soderlind-aivalve-bar--warn';
	}
	return (
		<>
			<div
				className="soderlind-aivalve-bar-wrap"
				title={ `${ pct }% of ${ label }` }
			>
				<div className={ cls } style={ { width: `${ pct }%` } } />
			</div>
			<div className="soderlind-aivalve-sub">
				{ `${ fmt( used ) } / ${ fmt( limit ) } (${ pct }%) of ${ label }` }
			</div>
		</>
	);
}

export default function SummaryCards( { daily, monthly, budgets, threshold } ) {
	return (
		<div className="soderlind-aivalve-cards">
			<div className="soderlind-aivalve-card">
				<h3>{ __( 'Today', 'soderlind-aivalve' ) }</h3>
				<div className="soderlind-aivalve-big">
					{ fmt( daily.total_tokens ) }
				</div>
				<div className="soderlind-aivalve-sub">
					{ `${ __( 'tokens across', 'soderlind-aivalve' ) } ${ fmt(
						daily.request_count
					) } ${ __( 'requests', 'soderlind-aivalve' ) }` }
				</div>
				<div className="soderlind-aivalve-sub">
					{ `${ fmt( daily.prompt_tokens ) } ${ __(
						'prompt',
						'soderlind-aivalve'
					) } · ${ fmt( daily.completion_tokens ) } ${ __(
						'completion',
						'soderlind-aivalve'
					) }` }
				</div>
				<BudgetBar
					used={ daily.total_tokens }
					limit={ budgets.global_daily_limit }
					label={ __( 'daily budget', 'soderlind-aivalve' ) }
					threshold={ threshold }
				/>
			</div>

			<div className="soderlind-aivalve-card">
				<h3>{ __( 'This Month', 'soderlind-aivalve' ) }</h3>
				<div className="soderlind-aivalve-big">
					{ fmt( monthly.total_tokens ) }
				</div>
				<div className="soderlind-aivalve-sub">
					{ `${ __( 'tokens across', 'soderlind-aivalve' ) } ${ fmt(
						monthly.request_count
					) } ${ __( 'requests', 'soderlind-aivalve' ) }` }
				</div>
				<BudgetBar
					used={ monthly.total_tokens }
					limit={ budgets.global_monthly_limit }
					label={ __( 'monthly budget', 'soderlind-aivalve' ) }
					threshold={ threshold }
				/>
			</div>
		</div>
	);
}
