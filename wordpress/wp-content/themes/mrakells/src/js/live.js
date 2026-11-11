/** Игровые панели живут отдельно от закэшированного документа. */
export function createLiveServer( config, onUpdate ) {
	const panels = [ ...document.querySelectorAll( '[data-server-panel]' ) ];
	const message = document.querySelector( '[data-server-live-message]' );
	const rendered = new Map();
	let pending = null;
	let latest = null;
	let fetchedAt = 0;
	let controller = null;
	let timer = null;

	const showError = () => {
		if ( message ) {
			message.textContent = config.error;
			message.hidden = false;
		}
		panels.forEach( ( panel ) => {
			panel.setAttribute( 'aria-busy', 'false' );
			const loading = panel.querySelector( '[data-server-loading]' );
			if ( loading ) loading.textContent = config.error;
		} );
	};

	const render = ( result ) => {
		if ( ! result.panels ) return;
		panels.forEach( ( panel ) => {
			const name = panel.dataset.serverPanel;
			const html = result.panels[ name ];
			if ( typeof html !== 'string' ) return;
			// Панели приходят только из нашего endpoint и экранируются PHP-шаблонами.
			if ( rendered.get( name ) !== html ) {
				panel.innerHTML = html;
				rendered.set( name, html );
				onUpdate( panel );
			}
			panel.hidden = ! html;
			panel.setAttribute( 'aria-busy', 'false' );
		} );
		const quests = panels.find( ( panel ) => panel.dataset.serverPanel === 'quests' );
		const teams = panels.find( ( panel ) => panel.dataset.serverPanel === 'teams' );
		for ( const [ panel, other, width ] of [ [ quests, teams, 'lg:col-span-7' ], [ teams, quests, 'lg:col-span-5' ] ] ) {
			if ( ! panel ) continue;
			panel.classList.toggle( width, !! other && ! other.hidden );
			panel.classList.toggle( 'lg:col-span-12', ! other || other.hidden );
		}
		if ( message ) message.hidden = true;
	};

	const refresh = () => {
		if ( pending ) return pending;
		if ( latest && Date.now() - fetchedAt < 5000 ) return Promise.resolve( latest.server );

		controller = new AbortController();
		const timeout = setTimeout( () => controller.abort(), 10000 );
		const url = new URL( config.endpoint, location.href );
		if ( panels.length ) url.searchParams.set( 'panels', 'true' );

		pending = fetch( url, {
			cache: 'no-store',
			credentials: 'omit',
			headers: { Accept: 'application/json' },
			signal: controller.signal,
		} ).then( async ( response ) => {
			if ( ! response.ok ) throw new Error( 'Live data unavailable' );
			const result = await response.json();
			if ( typeof result.server?.online !== 'boolean' || ! Number.isFinite( result.server.checked ) || ( panels.length && ! result.panels ) ) {
				throw new Error( 'Invalid live data' );
			}
			render( result );
			latest = result;
			fetchedAt = Date.now();
			return result.server;
		} ).catch( ( error ) => {
			showError();
			throw error;
		} ).finally( () => {
			clearTimeout( timeout );
			pending = null;
		} );
		return pending;
	};

	const poll = () => {
		clearTimeout( timer );
		if ( document.hidden ) return;
		refresh().catch( () => {} ).finally( () => {
			clearTimeout( timer );
			if ( ! document.hidden ) timer = setTimeout( poll, 30000 );
		} );
	};

	if ( panels.length ) {
		poll();
		document.addEventListener( 'visibilitychange', () => {
			clearTimeout( timer );
			if ( ! document.hidden ) poll();
		} );
		window.addEventListener( 'pagehide', () => {
			clearTimeout( timer );
			controller?.abort();
		} );
		window.addEventListener( 'pageshow', ( event ) => {
			if ( event.persisted ) poll();
		} );
	}

	return { refresh };
}
