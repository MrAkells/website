/**
 * Строка ввода команд, клавиши и мелкие удобства. Сайт работает и без этого файла:
 * всё, что здесь включается, в разметке скрыто атрибутом hidden.
 */

import { createTrail } from './trail.js';
import { createLiveServer } from './live.js';

const THEMES = [ 'cyan', 'amber', 'green' ];
const THEME_KEY = 'mrakells-theme';

const shell = document.querySelector( '[data-shell]' );
const data = JSON.parse( shell?.querySelector( '[data-shell-data]' )?.textContent || 'null' );

// След за курсором на фоне; null — на устройстве без мыши или при уменьшенном движении.
const trail = createTrail();
const live = data?.server ? createLiveServer( data.server, formatLocalTimes ) : null;

if ( shell && data ) {
	startShell( shell, data, trail, live );
}

// На узком экране вкладки шапки прокручиваются вбок — текущая должна быть на виду.
document.querySelector( '.site-nav .current-menu-item, .site-nav .current_page_parent' )?.scrollIntoView( { block: 'nearest', inline: 'nearest' } );

startCopyButtons();
startLocalTime();

/* -------------------------------------------------------------------------
 * Строка ввода
 * ---------------------------------------------------------------------- */

function startShell( root, config, fx, liveServer ) {
	const log = root.querySelector( '[data-shell-log]' );
	const form = root.querySelector( '[data-shell-form]' );
	const input = root.querySelector( '[data-shell-input]' );
	const mode = document.querySelector( '[data-mode]' );
	const history = [];
	let cursor = 0;

	root.hidden = false;
	document.querySelectorAll( '[data-keys], [data-shell-focus]' ).forEach( ( node ) => {
		node.hidden = false;
	} );

	// Имя места без «~/»: и «cd blog», и «cd ~/blog» ведут в одно место.
	const bare = ( name ) => name.replace( /^~\/?/, '' ).replace( /\/$/, '' ).toLowerCase();
	const findPlace = ( name ) => config.places.find( ( place ) => bare( place.name ) === bare( name ) );
	const findLink = ( name ) => config.links.find( ( link ) => link.name.toLowerCase() === name.toLowerCase() );

	const print = ( text, tone = '' ) => {
		const line = document.createElement( 'div' );
		line.textContent = text;
		line.className = tone;
		log.append( line );
		log.scrollTop = log.scrollHeight;

		return line;
	};

	const printLinks = ( items ) => {
		const line = print( '' );
		line.className = 'flex flex-wrap gap-x-5';

		items.forEach( ( item ) => {
			const link = document.createElement( 'a' );
			link.href = item.url;
			link.textContent = item.name;
			link.className = 'link';
			line.append( link );
		} );
	};

	const focus = () => {
		input.focus();
		root.scrollIntoView( { block: 'nearest' } );
	};

	const commands = {
		help() {
			Object.entries( config.help ).forEach( ( [ name, text ] ) => print( name.padEnd( 7 ) + text ) );
		},

		ls() {
			printLinks( config.places );

			if ( config.links.length ) {
				printLinks( config.links );
			}
		},

		cd( name = '~' ) {
			const up = location.pathname.replace( /[^/]+\/?$/, '' );
			const place = '..' === name ? { url: up } : findPlace( name );

			if ( ! place ) {
				return print( `cd: no such file or directory: ${ name }`, 'text-warm' );
			}

			location.assign( place.url );
		},

		open( name = '' ) {
			const link = findLink( name );

			if ( ! link ) {
				return print( `open: ${ name || '?' }: not found`, 'text-warm' );
			}

			location.assign( link.url );
		},

		async ping() {
			if ( ! config.server || ! liveServer ) {
				return print( 'ping: no server configured', 'text-warm' );
			}
			const line = print( `ping ${ config.server.address }…`, 'text-muted' );
			try {
				const server = await liveServer.refresh();
				line.textContent = server.online ? `${ server.address }: online, ${ server.players }/${ server.max }` : `${ server.address }: offline`;
				line.className = server.online ? 'text-ok' : 'text-warm';
			} catch {
				line.textContent = `ping: ${ config.server.error }`;
				line.className = 'text-warm';
			}
		},

		theme( name = '' ) {
			if ( ! THEMES.includes( name ) ) {
				return print( `theme: ${ THEMES.join( ' | ' ) }`, 'text-warm' );
			}

			// Основной акцент — отсутствие атрибута, чтобы стили по умолчанию оставались одни.
			if ( THEMES[ 0 ] === name ) {
				delete document.documentElement.dataset.theme;
			} else {
				document.documentElement.dataset.theme = name;
			}

			try {
				localStorage.setItem( THEME_KEY, THEMES[ 0 ] === name ? '' : name );
			} catch {
				// Хранилище недоступно (приватный режим) — акцент действует до перезагрузки.
			}

			print( `theme: ${ name }`, 'text-accent' );
		},

		fx( state = '' ) {
			if ( ! fx ) {
				return print( 'fx: not available on this device', 'text-warm' );
			}

			if ( 'on' !== state && 'off' !== state ) {
				return print( `fx: on | off (now ${ fx.enabled ? 'on' : 'off' })`, 'text-warm' );
			}

			fx.set( 'on' === state );
			print( `fx: ${ state }`, 'text-accent' );
		},

		lang( code = '' ) {
			if ( ! config.langs[ code ] ) {
				return print( `lang: ${ Object.keys( config.langs ).join( ' | ' ) }`, 'text-warm' );
			}

			location.assign( config.langs[ code ] );
		},

		clear() {
			log.replaceChildren();
		},
	};

	const run = ( line ) => {
		const [ name, ...args ] = line.trim().split( /\s+/ );

		if ( ! name ) {
			return;
		}

		print( `$ ${ line.trim() }`, 'text-muted' );

		if ( Object.hasOwn( commands, name ) ) {
			commands[ name ]( ...args );
		} else if ( findPlace( name ) ) {
			// Имя места без команды — как автоматический cd в zsh.
			commands.cd( name );
		} else {
			print( `command not found: ${ name }`, 'text-warm' );
		}
	};

	// Дополнение по Tab: команды, а после «cd» и «open» — места и ссылки.
	const complete = () => {
		const [ name, arg ] = input.value.split( /\s+/ );
		const pools = {
			cd: config.places.map( ( place ) => bare( place.name ) ),
			open: config.links.map( ( link ) => link.name ),
			theme: THEMES,
			fx: [ 'on', 'off' ],
			lang: Object.keys( config.langs ),
		};

		if ( undefined === arg ) {
			const match = Object.keys( commands ).find( ( command ) => command.startsWith( name ) );
			input.value = match ? match + ' ' : input.value;
		} else if ( pools[ name ] ) {
			const match = pools[ name ].find( ( option ) => option && option.toLowerCase().startsWith( arg.toLowerCase() ) );
			input.value = match ? `${ name } ${ match }` : input.value;
		}
	};

	form.addEventListener( 'submit', ( event ) => {
		event.preventDefault();

		if ( input.value.trim() ) {
			history.push( input.value );
		}

		cursor = history.length;
		run( input.value );
		input.value = '';
	} );

	input.addEventListener( 'keydown', ( event ) => {
		if ( 'Escape' === event.key ) {
			input.blur();
		} else if ( 'Tab' === event.key && input.value ) {
			event.preventDefault();
			complete();
		} else if ( 'ArrowUp' === event.key || 'ArrowDown' === event.key ) {
			event.preventDefault();
			cursor = Math.max( 0, Math.min( history.length, cursor + ( 'ArrowUp' === event.key ? -1 : 1 ) ) );
			input.value = history[ cursor ] ?? '';
		}
	} );

	// Строка состояния показывает режим, как в vim: набор команды — INSERT.
	input.addEventListener( 'focus', () => mode && ( mode.textContent = 'INSERT' ) );
	input.addEventListener( 'blur', () => mode && ( mode.textContent = 'NORMAL' ) );

	document.querySelectorAll( '[data-shell-focus]' ).forEach( ( button ) => button.addEventListener( 'click', focus ) );

	// Клавиши вне полей ввода: «/» — к строке, «?» — справка, цифра — вкладка в шапке,
	// буква — ссылка с такой же клавишей на странице.
	document.addEventListener( 'keydown', ( event ) => {
		const typing = event.target.closest?.( 'input, textarea, select, [contenteditable]' );

		if ( typing || event.ctrlKey || event.metaKey || event.altKey ) {
			return;
		}

		const tabs = document.querySelectorAll( '.site-nav a' );
		const keyed = /^[a-z]$/.test( event.key ) && document.querySelector( `[data-key="${ event.key }"]` );

		if ( '/' === event.key ) {
			event.preventDefault();
			focus();
		} else if ( '?' === event.key ) {
			event.preventDefault();
			focus();
			run( 'help' );
		} else if ( /^\d$/.test( event.key ) && tabs[ Number( event.key ) ] ) {
			tabs[ Number( event.key ) ].click();
		} else if ( keyed ) {
			keyed.click();
		}
	} );
}

/* -------------------------------------------------------------------------
 * Кнопки «скопировать»: значение — в data-copy, подпись после копирования — в data-copied
 * ---------------------------------------------------------------------- */

function startCopyButtons() {
	document.addEventListener( 'click', async ( event ) => {
		const button = event.target.closest?.( '[data-copy]' );
		if ( ! button ) return;
		const label = button.querySelector( '[data-copy-label]' );
		const initial = label?.textContent;
		try {
			await navigator.clipboard.writeText( button.dataset.copy );
		} catch {
			// Буфер обмена недоступен — адрес остаётся выделяемым текстом.
			return;
		}

		if ( label ) {
			label.textContent = button.dataset.copied;
			setTimeout( () => ( label.textContent = initial ), 1500 );
		}
	} );
}

/* -------------------------------------------------------------------------
 * Время в часовом поясе посетителя. Сервер отдаёт его по UTC в <time data-local>,
 * здесь оно пересчитывается в местное; формат остаётся тем же, что и на сервере
 * ---------------------------------------------------------------------- */

// Объявления функций, а не константы: startLocalTime() вызывается в начале модуля.
function pad( number ) {
	return String( number ).padStart( 2, '0' );
}

function localDate( moment ) {
	return `${ moment.getFullYear() }-${ pad( moment.getMonth() + 1 ) }-${ pad( moment.getDate() ) }`;
}

function localTime( moment ) {
	return `${ pad( moment.getHours() ) }:${ pad( moment.getMinutes() ) }`;
}

// Вызывается и для всей страницы, и для панелей, пришедших из live endpoint.
function formatLocalTimes( root = document ) {
	root.querySelectorAll( 'time[data-local]' ).forEach( ( node ) => {
		const moment = new Date( node.dateTime );
		const format = {
			date: localDate,
			time: localTime,
			datetime: ( value ) => `${ localDate( value ) } ${ localTime( value ) }`,
		}[ node.dataset.local ];

		if ( format && ! Number.isNaN( moment.getTime() ) ) {
			node.textContent = format( moment );
		}
	} );
}

function startLocalTime() {
	formatLocalTimes();

	// Часы в строке состояния — тот же элемент, только время в нём идёт.
	const clock = document.querySelector( 'footer time[data-local="time"]' );

	if ( clock ) {
		setInterval( () => {
			clock.textContent = localTime( new Date() );
		}, 20000 );
	}
}
