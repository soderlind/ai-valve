import { useState } from '@wordpress/element';
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import Dashboard from './components/Dashboard';
import Settings from './components/Settings';
import Logs from './components/Logs';

const TABS = [
	{ name: 'dashboard', title: __( 'Dashboard', 'ai-valve' ) },
	{ name: 'settings', title: __( 'Settings', 'ai-valve' ) },
	{ name: 'logs', title: __( 'Logs', 'ai-valve' ) },
];

export default function App() {
	const [ notice, setNotice ] = useState( null );

	return (
		<div className="wrap">
			<h1>{ __( 'AI Valve — AI Usage Control', 'ai-valve' ) }</h1>

			{ notice && (
				<div className={ `notice notice-${ notice.type } is-dismissible` }>
					<p>{ notice.message }</p>
					<button
						type="button"
						className="notice-dismiss"
						onClick={ () => setNotice( null ) }
					>
						<span className="screen-reader-text">
							{ __( 'Dismiss this notice.', 'ai-valve' ) }
						</span>
					</button>
				</div>
			) }

			<TabPanel tabs={ TABS }>
				{ ( tab ) => {
					switch ( tab.name ) {
						case 'dashboard':
							return <Dashboard setNotice={ setNotice } />;
						case 'settings':
							return <Settings setNotice={ setNotice } />;
						case 'logs':
							return <Logs setNotice={ setNotice } />;
						default:
							return null;
					}
				} }
			</TabPanel>
		</div>
	);
}
