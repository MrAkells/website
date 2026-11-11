/**
 * След за курсором на фоне: клетки точечной сетки рядом с указателем загораются
 * пикселями акцентного цвета и гаснут с дизерингом, пятно догоняет курсор с опозданием.
 *
 * Рисуется на холсте под содержимым страницы. Работает только там, где есть мышь,
 * и не включается, если в системе выбрано уменьшение движения.
 */

// Шаг совпадает с точечной сеткой фона в app.css.
const STEP = 22;
// Радиус пятна в клетках.
const RADIUS = 7;
// Какую долю расстояния до курсора пятно проходит за кадр: меньше — ленивее.
const CHASE = 0.16;
// Во сколько раз яркость клетки падает за кадр.
const DECAY = 0.955;
// Через сколько миллисекунд покоя пятно перестаёт подсвечивать клетки под собой.
const IDLE = 900;
const STORE_KEY = 'mrakells-fx';

// Матрица Байера 4×4: порог, выше которого клетка видна. Из-за неё край пятна
// не размывается, а рассыпается на редкие пиксели.
const BAYER = [ 0, 8, 2, 10, 12, 4, 14, 6, 3, 11, 1, 9, 15, 7, 13, 5 ].map( ( value ) => ( value + 0.5 ) / 16 );

/**
 * Создаёт след. Возвращает управление им или null, если устройству он не подходит.
 *
 * @return {{ enabled: boolean, set: ( on: boolean ) => void }|null}
 */
export function createTrail() {
	const hasMouse = matchMedia( '(hover: hover) and (pointer: fine)' ).matches;
	const calm = matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	if ( ! hasMouse || calm ) {
		return null;
	}

	const canvas = document.createElement( 'canvas' );
	const context = canvas.getContext( '2d' );

	canvas.className = 'pointer-events-none fixed inset-0 z-0 size-full';
	canvas.setAttribute( 'aria-hidden', 'true' );
	document.body.prepend( canvas );

	// Яркость клеток: ключ — номер клетки на странице, поэтому след прокручивается вместе с ней.
	const cells = new Map();
	const pointer = { x: 0, y: 0 };
	const spot = { x: 0, y: 0 };
	let accent = '';
	let glow = 0;
	let moved = 0;
	let frame = 0;
	let last = 0;
	let enabled = true;

	try {
		enabled = 'off' !== localStorage.getItem( STORE_KEY );
	} catch {
		// Хранилище недоступно — след включён по умолчанию.
	}

	const readAccent = () => {
		accent = getComputedStyle( document.documentElement ).getPropertyValue( '--color-accent' ).trim();
	};

	const resize = () => {
		const ratio = window.devicePixelRatio || 1;

		canvas.width = Math.round( innerWidth * ratio );
		canvas.height = Math.round( innerHeight * ratio );
		context.setTransform( ratio, 0, 0, ratio, 0, 0 );
	};

	const draw = ( now ) => {
		// Шаг в «кадрах по 60 в секунду», чтобы скорость не зависела от частоты экрана.
		const beat = Math.min( 4, ( now - last ) / 16.7 );
		const active = now - moved < IDLE;
		// Начало сетки — угол body: он сдвигается при прокрутке и из-за панели администратора.
		const origin = document.body.getBoundingClientRect();
		const left = Math.round( origin.left );
		const top = Math.round( origin.top );

		last = now;
		spot.x += ( pointer.x - spot.x ) * ( 1 - ( 1 - CHASE ) ** beat );
		spot.y += ( pointer.y - spot.y ) * ( 1 - ( 1 - CHASE ) ** beat );
		glow += ( ( active ? 1 : 0 ) - glow ) * ( 1 - 0.92 ** beat );

		if ( active ) {
			const column = Math.floor( ( spot.x - left ) / STEP );
			const row = Math.floor( ( spot.y - top ) / STEP );

			for ( let j = row - RADIUS; j <= row + RADIUS; j++ ) {
				for ( let i = column - RADIUS; i <= column + RADIUS; i++ ) {
					const distance = Math.hypot( ( i + 0.5 ) * STEP + left - spot.x, ( j + 0.5 ) * STEP + top - spot.y ) / ( RADIUS * STEP );

					if ( distance < 1 && i >= 0 && j >= 0 ) {
						const key = j * 4096 + i;
						cells.set( key, Math.max( cells.get( key ) || 0, ( 1 - distance ) ** 2 ) );
					}
				}
			}
		}

		context.clearRect( 0, 0, innerWidth, innerHeight );
		context.fillStyle = accent;

		if ( glow > 0.01 ) {
			const halo = context.createRadialGradient( spot.x, spot.y, 0, spot.x, spot.y, 240 );

			halo.addColorStop( 0, accent );
			halo.addColorStop( 1, 'transparent' );
			context.globalAlpha = 0.07 * glow;
			context.fillStyle = halo;
			context.fillRect( spot.x - 240, spot.y - 240, 480, 480 );
			context.fillStyle = accent;
		}

		for ( const [ key, energy ] of cells ) {
			const faded = energy * DECAY ** beat;

			if ( faded < 0.04 ) {
				cells.delete( key );
				continue;
			}

			cells.set( key, faded );

			const i = key % 4096;
			const j = Math.floor( key / 4096 );

			if ( faded < BAYER[ ( i & 3 ) + ( j & 3 ) * 4 ] * 0.8 ) {
				continue;
			}

			const x = ( i + 0.5 ) * STEP + left;
			const y = ( j + 0.5 ) * STEP + top;

			context.globalAlpha = 0.2 + 0.5 * faded;

			// Чем ярче клетка, тем крупнее фигура: точка, квадрат, плюс, блок.
			if ( faded > 0.78 ) {
				context.fillRect( x - 4, y - 4, 8, 8 );
			} else if ( faded > 0.5 ) {
				context.fillRect( x - 5, y - 1, 10, 2 );
				context.fillRect( x - 1, y - 5, 2, 10 );
			} else if ( faded > 0.25 ) {
				context.fillRect( x - 2, y - 2, 4, 4 );
			} else {
				context.fillRect( x - 1, y - 1, 2, 2 );
			}
		}

		context.globalAlpha = 1;

		// Когда всё погасло, кадры не запрашиваются — в покое след ничего не стоит.
		frame = cells.size || glow > 0.01 ? requestAnimationFrame( draw ) : 0;
	};

	const wake = () => {
		if ( enabled && ! frame ) {
			last = performance.now();
			frame = requestAnimationFrame( draw );
		}
	};

	addEventListener(
		'pointermove',
		( event ) => {
			if ( 'touch' === event.pointerType ) {
				return;
			}

			// После долгого покоя пятно появляется под курсором, а не летит к нему через весь экран.
			if ( ! frame && ! cells.size ) {
				spot.x = event.clientX;
				spot.y = event.clientY;
			}

			pointer.x = event.clientX;
			pointer.y = event.clientY;
			moved = performance.now();
			wake();
		},
		{ passive: true }
	);

	addEventListener( 'resize', resize );

	// Команда «theme» меняет атрибут на <html> — цвет следа перечитывается.
	new MutationObserver( readAccent ).observe( document.documentElement, { attributes: true, attributeFilter: [ 'data-theme' ] } );

	readAccent();
	resize();

	return {
		get enabled() {
			return enabled;
		},

		set( on ) {
			enabled = on;

			if ( ! on ) {
				cancelAnimationFrame( frame );
				frame = 0;
				glow = 0;
				cells.clear();
				context.clearRect( 0, 0, innerWidth, innerHeight );
			}

			try {
				localStorage.setItem( STORE_KEY, on ? '' : 'off' );
			} catch {
				// Хранилище недоступно — выбор действует до перезагрузки.
			}
		},
	};
}
