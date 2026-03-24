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
	let cls = 'ai-valve-bar ai-valve-bar--ok';
	if ( pct >= 100 ) {
		cls = 'ai-valve-bar ai-valve-bar--over';
	} else if ( pct >= threshold ) {
		cls = 'ai-valve-bar ai-valve-bar--warn';
	}
	return (
		<>
			<div
				className="ai-valve-bar-wrap"
				title={ `${ pct }% of ${ label }` }
			>
				<div className={ cls } style={ { width: `${ pct }%` } } />
			</div>
			<div className="ai-valve-sub">
				{ `${ fmt( used ) } / ${ fmt( limit ) } (${ pct }%) of ${ label }` }
			</div>
		</>
	);
}

export default function SummaryCards( { daily, monthly, budgets, threshold } ) {
	return (
		<div className="ai-valve-cards">
			<div className="ai-valve-card">
				<h3>{ __( 'Today', 'ai-valve' ) }</h3>
				<div className="ai-valve-big">
					{ fmt( daily.total_tokens ) }
				</div>
				<div className="ai-valve-sub">
					{ `${ __( 'tokens across', 'ai-valve' ) } ${ fmt(
						daily.request_count
					) } ${ __( 'requests', 'ai-valve' ) }` }
				</div>
				<div className="ai-valve-sub">
					{ `${ fmt( daily.prompt_tokens ) } ${ __(
						'prompt',
						'ai-valve'
					) } · ${ fmt( daily.completion_tokens ) } ${ __(
						'completion',
						'ai-valve'
					) }` }
				</div>
				<BudgetBar
					used={ daily.total_tokens }
					limit={ budgets.global_daily_limit }
					label={ __( 'daily budget', 'ai-valve' ) }
					threshold={ threshold }
				/>
			</div>

			<div className="ai-valve-card">
				<h3>{ __( 'This Month', 'ai-valve' ) }</h3>
				<div className="ai-valve-big">
					{ fmt( monthly.total_tokens ) }
				</div>
				<div className="ai-valve-sub">
					{ `${ __( 'tokens across', 'ai-valve' ) } ${ fmt(
						monthly.request_count
					) } ${ __( 'requests', 'ai-valve' ) }` }
				</div>
				<BudgetBar
					used={ monthly.total_tokens }
					limit={ budgets.global_monthly_limit }
					label={ __( 'monthly budget', 'ai-valve' ) }
					threshold={ threshold }
				/>
			</div>
		</div>
	);
}
