/**
 * Лицо на первом экране: под курсором голова из Minecraft превращается в фотографию.
 *
 * От точки, где курсор вошёл на аватар, расходится волна. Перед ней клетки головы 8×8
 * вспыхивают акцентным цветом и становятся пикселями фотографии, а за ней каждый пиксель
 * делится на четыре — 8, 16, 32, 64 в стороне — и наконец уступает место настоящей
 * фотографии. Край волны не ровный, а рассыпан по матрице Байера, как в следе за курсором
 * и в разделителе. Когда курсор уходит, волна идёт назад — от точки, где он вышел.
 *
 * Рисуется на холсте поверх аватара. Фотография в разметке не участвует: без скрипта
 * на странице остаётся только голова.
 */

// Сторона самой мелкой сетки и ступени дробления: клетка головы — восемь мелких клеток.
const FINE = 64;
const GRIDS = [ 8, 16, 32, 64 ];
// Длительность появления и возврата, мс.
const SHOW = 1150;
const HIDE = 700;
// Когда начинается каждое событие у самой точки входа, в долях длительности: замена клетки
// головы, три дробления и переход к настоящей фотографии.
const STARTS = [ 0, 0.15, 0.28, 0.4, 0.52 ];
// Сколько времени волна идёт от точки входа до дальнего угла.
const SPREAD = 0.36;
// На сколько матрица Байера сдвигает событие в каждой клетке: это и есть рваный край волны.
const JITTER = 0.11;
// Сколько клетка головы горит акцентным цветом перед заменой.
const FLASH = 0.045;

const BAYER = [ 0, 8, 2, 10, 12, 4, 14, 6, 3, 11, 1, 9, 15, 7, 13, 5 ].map( ( value ) => value / 16 );

export function startFace() {
	const root = document.querySelector( '[data-face]' );

	if ( ! root ) {
		return;
	}

	const calm = matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	const canvas = document.createElement( 'canvas' );
	const context = canvas.getContext( '2d' );
	// Два слоя 64×64: пиксели на всех ступенях дробления и маска клеток, где уже видна фотография.
	const blocks = layer();
	const mask = layer();
	const photo = new Image();
	// Цвета пикселей фотографии на каждой ступени, от 8×8 до 64×64.
	let mips = null;
	// Моменты событий для каждой клетки каждой ступени; пересчитываются под точку входа.
	let times = null;
	let accent = [ 0, 0, 0 ];
	// 0 — голова, 1 — фотография.
	let progress = 0;
	let target = 0;
	let frame = 0;
	let last = 0;

	canvas.className = 'pointer-events-none absolute inset-0 size-full';
	canvas.setAttribute( 'aria-hidden', 'true' );
	root.append( canvas );

	function layer() {
		const node = document.createElement( 'canvas' );

		node.width = FINE;
		node.height = FINE;

		const pen = node.getContext( '2d', { willReadFrequently: true } );

		return { node, pen, image: pen.createImageData( FINE, FINE ) };
	}

	const fit = () => {
		const side = Math.round( root.clientWidth * ( window.devicePixelRatio || 1 ) );

		if ( side && side !== canvas.width ) {
			canvas.width = side;
			canvas.height = side;
		}
	};

	// Уменьшенные копии фотографии. Усредняются вручную: так цвет крупного пикселя — честное
	// среднее по его площади в любом браузере.
	const buildMips = () => {
		const scale = 4;
		const side = FINE * scale;

		blocks.node.width = side;
		blocks.node.height = side;
		blocks.pen.drawImage( photo, 0, 0, side, side );

		const source = blocks.pen.getImageData( 0, 0, side, side ).data;

		blocks.node.width = FINE;
		blocks.node.height = FINE;

		return GRIDS.map( ( grid ) => {
			const span = side / grid;
			const colors = new Uint8ClampedArray( grid * grid * 3 );

			for ( let y = 0; y < grid; y++ ) {
				for ( let x = 0; x < grid; x++ ) {
					const sum = [ 0, 0, 0 ];

					for ( let j = y * span; j < ( y + 1 ) * span; j++ ) {
						for ( let i = x * span; i < ( x + 1 ) * span; i++ ) {
							sum[ 0 ] += source[ ( j * side + i ) * 4 ];
							sum[ 1 ] += source[ ( j * side + i ) * 4 + 1 ];
							sum[ 2 ] += source[ ( j * side + i ) * 4 + 2 ];
						}
					}

					colors.set( sum.map( ( value ) => value / ( span * span ) ), ( y * grid + x ) * 3 );
				}
			}

			return colors;
		} );
	};

	// Расписание волны от точки (x, y) в долях стороны аватара. При возврате расстояние
	// считается наоборот: прогресс идёт назад, и первыми гаснут клетки у точки выхода.
	const plan = ( x, y, reverse ) => {
		const reach = Math.hypot( Math.max( x, 1 - x ), Math.max( y, 1 - y ) );
		const total = STARTS[ STARTS.length - 1 ] + SPREAD + JITTER;

		times = STARTS.map( ( start, event ) => {
			// Замена клетки головы и первое дробление считаются по сетке 8×8, дальше — по своей.
			const grid = GRIDS[ Math.max( 0, event - 1 ) ];
			const moments = new Float32Array( grid * grid );

			for ( let j = 0; j < grid; j++ ) {
				for ( let i = 0; i < grid; i++ ) {
					const distance = Math.hypot( ( i + 0.5 ) / grid - x, ( j + 0.5 ) / grid - y ) / reach;
					const wave = reverse ? 1 - distance : distance;

					moments[ j * grid + i ] = ( start + SPREAD * wave + JITTER * BAYER[ ( i & 3 ) + ( j & 3 ) * 4 ] ) / total;
				}
			}

			return moments;
		} );
	};

	const readAccent = () => {
		const pen = mask.pen;

		pen.fillStyle = getComputedStyle( document.documentElement ).getPropertyValue( '--color-accent' ).trim();
		pen.fillRect( 0, 0, 1, 1 );
		accent = pen.getImageData( 0, 0, 1, 1 ).data;
	};

	const draw = () => {
		const side = canvas.width;

		context.clearRect( 0, 0, side, side );

		if ( progress <= 0 || ! times ) {
			return;
		}

		if ( progress >= 1 ) {
			context.imageSmoothingEnabled = true;
			context.imageSmoothingQuality = 'high';
			context.drawImage( photo, 0, 0, side, side );

			return;
		}

		const pixels = blocks.image.data;
		const holes = mask.image.data;
		let sharp = false;

		for ( let y = 0; y < FINE; y++ ) {
			for ( let x = 0; x < FINE; x++ ) {
				const at = ( y * FINE + x ) * 4;
				const head = ( y >> 3 ) * 8 + ( x >> 3 );
				const flip = times[ 0 ][ head ];

				pixels[ at + 3 ] = 0;
				holes[ at + 3 ] = 0;

				// Волна ещё не дошла: видна клетка головы под холстом.
				if ( progress < flip ) {
					continue;
				}

				pixels[ at + 3 ] = 255;

				if ( progress < flip + FLASH ) {
					pixels[ at ] = accent[ 0 ];
					pixels[ at + 1 ] = accent[ 1 ];
					pixels[ at + 2 ] = accent[ 2 ];
					continue;
				}

				// Пиксель дробится, только когда раздробился тот, в который он входит.
				let level = 0;

				while ( level < 3 && progress >= times[ level + 1 ][ ( y >> ( 3 - level ) ) * GRIDS[ level ] + ( x >> ( 3 - level ) ) ] ) {
					level++;
				}

				if ( 3 === level && progress >= times[ 4 ][ y * FINE + x ] ) {
					pixels[ at + 3 ] = 0;
					holes[ at + 3 ] = 255;
					sharp = true;
					continue;
				}

				const from = ( ( y >> ( 3 - level ) ) * GRIDS[ level ] + ( x >> ( 3 - level ) ) ) * 3;

				pixels[ at ] = mips[ level ][ from ];
				pixels[ at + 1 ] = mips[ level ][ from + 1 ];
				pixels[ at + 2 ] = mips[ level ][ from + 2 ];
			}
		}

		// Настоящая фотография остаётся только в клетках из маски, крупные пиксели ложатся рядом.
		if ( sharp ) {
			mask.pen.putImageData( mask.image, 0, 0 );
			context.imageSmoothingEnabled = true;
			context.imageSmoothingQuality = 'high';
			context.drawImage( photo, 0, 0, side, side );
			context.imageSmoothingEnabled = false;
			context.globalCompositeOperation = 'destination-in';
			context.drawImage( mask.node, 0, 0, side, side );
			context.globalCompositeOperation = 'source-over';
		}

		blocks.pen.putImageData( blocks.image, 0, 0 );
		context.imageSmoothingEnabled = false;
		context.drawImage( blocks.node, 0, 0, side, side );
	};

	const tick = ( now ) => {
		const delta = ( now - last ) / ( target ? SHOW : HIDE );

		last = now;
		progress = target ? Math.min( 1, progress + delta ) : Math.max( 0, progress - delta );
		draw();
		frame = progress === target ? 0 : requestAnimationFrame( tick );
	};

	// Точка входа или выхода курсора в долях стороны аватара.
	let origin = { x: 0.5, y: 0.5 };

	const go = ( to, event ) => {
		if ( event ) {
			const box = root.getBoundingClientRect();

			origin = {
				x: Math.min( 1, Math.max( 0, ( event.clientX - box.left ) / box.width ) ),
				y: Math.min( 1, Math.max( 0, ( event.clientY - box.top ) / box.height ) ),
			};
		}

		target = to;

		// Фотография ещё грузится — анимация начнётся, когда она придёт, если курсор не ушёл.
		if ( ! mips ) {
			return;
		}

		// Новая волна строится только из покоя: посреди анимации она просто разворачивается.
		if ( ! frame && progress !== to ) {
			plan( origin.x, origin.y, ! to );
			// Акцент мог смениться командой «theme».
			readAccent();
			fit();
		}

		if ( calm ) {
			progress = to;
			draw();
		} else if ( ! frame && progress !== to ) {
			last = performance.now();
			frame = requestAnimationFrame( tick );
		}
	};

	root.addEventListener( 'pointerenter', ( event ) => 'touch' !== event.pointerType && go( 1, event ) );
	root.addEventListener( 'pointerleave', ( event ) => 'touch' !== event.pointerType && go( 0, event ) );
	// На сенсорном экране наведения нет — фотография переключается касанием, волна идёт от пальца.
	root.addEventListener( 'pointerup', ( event ) => 'touch' === event.pointerType && go( target ? 0 : 1, event ) );

	// Аватар меняет размер на границе lg — холст подстраивается и перерисовывается.
	new ResizeObserver( () => {
		fit();
		draw();
	} ).observe( root );

	photo.decoding = 'async';
	photo.fetchPriority = 'low';
	photo.addEventListener( 'load', () => {
		mips = buildMips();

		if ( target ) {
			go( 1 );
		}
	} );
	photo.src = root.dataset.face;
}
