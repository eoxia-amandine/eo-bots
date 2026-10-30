/**
 * EO-BOTS avatar engine: draws the bot as SVG and brings it to life
 * (eyes following the pointer, blinking, moods). Shared by the creator and the floating widget.
 *
 * Usage: const bot = new window.EoBots.Avatar( element, { animal, color, accessory }, options );
 *        bot.setState( 'think' ); bot.setState( 'happy', { duration: 1500 } ); bot.setBase( 'idle' );
 */
( function () {
	'use strict';

	/* ---------- Color ---------- */
	const clamp = ( v, a, b ) => Math.min( b, Math.max( a, v ) );

	function hexToHsl( hex ) {
		let x = String( hex ).replace( '#', '' );
		if ( x.length === 3 ) {
			x = x.split( '' ).map( ( c ) => c + c ).join( '' );
		}
		const r = parseInt( x.slice( 0, 2 ), 16 ) / 255;
		const g = parseInt( x.slice( 2, 4 ), 16 ) / 255;
		const b = parseInt( x.slice( 4, 6 ), 16 ) / 255;
		const mx = Math.max( r, g, b );
		const mn = Math.min( r, g, b );
		const l = ( mx + mn ) / 2;
		let h = 0;
		let s = 0;
		if ( mx !== mn ) {
			const d = mx - mn;
			s = l > 0.5 ? d / ( 2 - mx - mn ) : d / ( mx + mn );
			if ( mx === r ) {
				h = ( g - b ) / d + ( g < b ? 6 : 0 );
			} else if ( mx === g ) {
				h = ( b - r ) / d + 2;
			} else {
				h = ( r - g ) / d + 4;
			}
			h *= 60;
		}
		return { h: Math.round( h ), s: Math.round( s * 100 ), l: Math.round( l * 100 ) };
	}

	const hsl = ( h, s, l ) => `hsl(${ h }, ${ clamp( s, 0, 100 ) }%, ${ clamp( l, 0, 100 ) }%)`;

	function palette( hex ) {
		const { h, s, l } = hexToHsl( hex );
		return {
			base: hex,
			light: hsl( h, s, l + 16 ),
			dark: hsl( h, s, l - 16 ),
			led: hsl( h, Math.max( s, 60 ), Math.max( l, 66 ) ),
			tint: hsl( h, 22, 93 ),
			tintDeep: hsl( h, 16, 80 ),
			ink: hsl( h, Math.max( s, 40 ), Math.min( l, 34 ) ),
		};
	}

	/* ---------- LED face ---------- */
	const mirror = ( p ) => p.map( ( r ) => [ ...r ].reverse().join( '' ) );
	const PAT = {
		open: [ '.XXX.', 'X.XXX', 'XXXXX', 'XXXXX', 'XXXXX', 'XXXXX', '.XXX.' ],
		big: [ '..XXX..', '.X.XXX.', 'X..XXXX', 'XXXXXXX', 'XXXXXXX', '.XXXXX.', '..XXX..' ],
		happy: [ '..XXX..', '.X...X.', 'X.....X' ],
		closed: [ 'XXXXX' ],
		sleep: [ 'X...X', '.XXX.' ],
		focus: [ '.XXXXX.', 'XXXXXXX', '.XXXXX.' ],
		sadL: [ '...XX', '..XXX', '.XXXX', 'XXXXX', '.XXX.' ],
		angryL: [ 'XX...', 'XXX..', 'XXXX.', 'XXXXX', '.XXX.' ],
		off: [],
	};
	PAT.sadR = mirror( PAT.sadL );
	PAT.angryR = mirror( PAT.angryL );

	const EXPR = {
		idle: { l: 'open', r: 'open', m: 'w', blink: true },
		listen: { l: 'big', r: 'big', m: 'w', blink: true },
		think: { l: 'open', r: 'open', m: 'flat', blink: true, gaze: [ 5, -6 ] },
		work: { l: 'focus', r: 'focus', m: 'flat' },
		happy: { l: 'happy', r: 'happy', m: 'smile' },
		laugh: { l: 'happy', r: 'happy', m: 'open' },
		oops: { l: 'sadL', r: 'sadR', m: 'o', blink: true },
		sleep: { l: 'sleep', r: 'sleep', m: 'flat', gaze: [ 0, 2 ] },
		grumpy: { l: 'angryL', r: 'angryR', m: 'flat' },
		unborn: { l: 'off', r: 'off', m: 'none', gaze: [ 0, 0 ] },
		birth: { l: 'off', r: 'off', m: 'none', gaze: [ 0, 0 ] },
	};

	function dots( pat, fill ) {
		if ( ! pat || ! pat.length ) {
			return '';
		}
		const S = 4.3;
		const R = pat.length;
		const C = pat[ 0 ].length;
		let out = '';
		pat.forEach( ( row, ri ) =>
			[ ...row ].forEach( ( ch, ci ) => {
				if ( ch === 'X' ) {
					out += `<circle cx="${ ( ( ci - ( C - 1 ) / 2 ) * S ).toFixed( 2 ) }" cy="${ ( ( ri - ( R - 1 ) / 2 ) * S ).toFixed( 2 ) }" r="1.7" fill="${ fill }"/>`;
				}
			} )
		);
		return out;
	}

	function mouth( kind, led ) {
		const st = `fill="none" stroke="${ led }" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"`;
		switch ( kind ) {
			case 'w':
				return `<path d="M111.5 125 Q115.8 130 120 125.5 Q124.2 130 128.5 125" ${ st }/>`;
			case 'smile':
				return `<path d="M110 124.5 Q120 135 130 124.5" ${ st }/>`;
			case 'open':
				return `<path d="M111 124.5 Q120 139 129 124.5 Z" fill="${ led }"/>`;
			case 'flat':
				return `<path d="M114 127.5 L126 127.5" ${ st }/>`;
			case 'o':
				return `<ellipse cx="120" cy="129" rx="3.4" ry="4" ${ st }/>`;
			default:
				return '';
		}
	}

	/* ---------- Parts ----------
	   Head parts are drawn in head space (scaled up 1.12 around the neck); body parts in body space. */
	const HEAD_T = 'translate(-14.4 3.3) scale(1.12)';
	const TAIL_T = 'translate(28.4 53.6) scale(.8)';
	const PUFF_T = 'translate(12.8 54.8) scale(.8)';
	const mir = ( s ) => `<g transform="translate(240 0) scale(-1 1)">${ s }</g>`;

	function strokeTail( F, d, tip ) {
		return `<path d="${ d }" fill="none" stroke="${ F.c.tintDeep }" stroke-width="13" stroke-linecap="round"/>
			<path d="${ d }" fill="none" stroke="#fff" stroke-width="9" stroke-linecap="round" transform="translate(-1 -1)"/>
			<path d="${ tip }" fill="none" stroke="${ F.c.base }" stroke-width="13" stroke-linecap="round"/>`;
	}

	const PARTS = {
		fox: {
			earOrigin: [ '70% 100%', '30% 100%' ],
			tailOrigin: '95% 95%',
			ears: ( F ) => `<path d="M58 84 C48 54 52 22 66 4 C86 14 104 38 110 60 Z" fill="${ F.w }"/>
				<path d="M66 4 C58 16 54 30 54 42 C68 36 84 36 96 42 C88 26 78 12 66 4 Z" fill="${ F.a }"/>
				<path d="M68 72 C63 52 65 34 72 22 C84 32 94 46 99 62 Z" fill="${ F.v }"/>
				<ellipse cx="79" cy="50" rx="6.5" ry="11" transform="rotate(-18 79 50)" fill="${ F.c.led }" opacity=".85"/>`,
			tail: ( F ) => `<g transform="${ TAIL_T }"><path d="M92 228 C56 240 22 220 20 184 C18 154 38 128 60 134 C78 140 78 166 72 186 C68 202 76 218 92 228 Z" fill="${ F.a }"/>
				<path d="M92 228 C64 236 38 226 28 204 C44 212 60 208 70 196 C70 210 78 220 92 228 Z" fill="${ F.w }"/></g>`,
		},
		cat: {
			earOrigin: [ '70% 100%', '30% 100%' ],
			tailOrigin: '95% 95%',
			ears: ( F ) => `<path d="M60 80 C54 58 56 36 64 22 C80 30 96 42 106 58 Z" fill="${ F.w }"/>
				<path d="M64 22 C60 30 58 38 58 46 C70 42 80 42 88 46 C82 36 74 28 64 22 Z" fill="${ F.a }"/>
				<path d="M69 70 C66 56 67 44 71 36 C81 42 89 50 95 60 Z" fill="${ F.v }"/>
				<ellipse cx="77" cy="55" rx="4.5" ry="8" transform="rotate(-24 77 55)" fill="${ F.c.led }" opacity=".85"/>`,
			tail: ( F ) => `<g transform="${ TAIL_T }">${ strokeTail( F, 'M92 230 C58 236 38 216 42 190 C44 174 54 164 64 168', 'M47 177 C52 168 58 166 64 168' ) }</g>`,
		},
		dog: {
			front: true,
			noPods: true,
			earOrigin: [ '60% 5%', '40% 5%' ],
			tailOrigin: '95% 95%',
			ears: ( F ) => `<path d="M68 54 C50 56 36 80 34 108 C33 126 46 136 56 128 C66 120 70 100 74 82 C76 68 74 58 68 54 Z" fill="${ F.a }"/>
				<path d="M60 66 C50 74 45 90 44 106" fill="none" stroke="#fff" stroke-opacity=".35" stroke-width="3" stroke-linecap="round"/>`,
			tail: ( F ) => `<g transform="${ TAIL_T }">${ strokeTail( F, 'M94 224 C70 226 56 206 62 182 C64 174 70 170 76 172', 'M64 178 C67 172 71 170 76 172' ) }</g>`,
		},
		rabbit: {
			earOrigin: [ '50% 100%', '50% 100%' ],
			tailOrigin: '50% 50%',
			ears: ( F ) => `<g transform="rotate(-8 92 58)">
				<path d="M80 60 C70 30 68 -2 78 -18 C88 -32 104 -18 104 10 C104 28 102 44 102 60 Z" fill="${ F.w }"/>
				<path d="M85 52 C78 32 76 8 82 -6 C88 -16 96 -8 96 12 C96 28 95 40 94 52 Z" fill="${ F.a }"/></g>`,
			tail: ( F ) => `<g transform="${ PUFF_T }"><circle cx="84" cy="224" r="15" fill="${ F.w }"/><circle cx="79" cy="218" r="5" fill="#fff" opacity=".8"/></g>`,
		},
		sheep: {
			noDome: true,
			earOrigin: [ '100% 50%', '0% 50%' ],
			tailOrigin: '50% 50%',
			ears: ( F ) => `<ellipse cx="42" cy="94" rx="22" ry="10" transform="rotate(-22 42 94)" fill="${ F.w }"/>
				<ellipse cx="40" cy="95" rx="14" ry="5" transform="rotate(-22 40 95)" fill="${ F.a }"/>`,
			extra: ( F ) =>
				[ [ 90, 52, 13 ], [ 104, 44, 15 ], [ 120, 40, 16 ], [ 136, 44, 15 ], [ 150, 52, 13 ], [ 110, 55, 10 ], [ 130, 55, 10 ] ]
					.map( ( [ x, y, r ] ) => `<circle cx="${ x }" cy="${ y }" r="${ r }" fill="${ F.w }" stroke="${ F.c.tintDeep }" stroke-width=".8"/>` )
					.join( '' ),
			tail: ( F ) => `<g transform="${ PUFF_T }"><circle cx="72" cy="214" r="10" fill="${ F.w }"/><circle cx="86" cy="210" r="9" fill="${ F.w }"/><circle cx="82" cy="224" r="13" fill="${ F.w }"/></g>`,
		},
		panda: {
			darkArms: true,
			earOrigin: [ '70% 90%', '30% 90%' ],
			tailOrigin: '50% 50%',
			ears: ( F ) => `<circle cx="70" cy="54" r="20" fill="${ F.g }"/><circle cx="70" cy="54" r="5" fill="${ F.c.led }"/>`,
			tail: ( F ) => `<g transform="${ PUFF_T }"><circle cx="84" cy="226" r="11" fill="${ F.g }"/></g>`,
		},
	};

	function body( F, A ) {
		return `<ellipse cx="84" cy="242" rx="11" ry="6" fill="${ F.g }"/><ellipse cx="156" cy="242" rx="11" ry="6" fill="${ F.g }"/>
			<ellipse cx="96" cy="227" rx="18" ry="16" fill="${ F.w }"/><ellipse cx="144" cy="227" rx="18" ry="16" fill="${ F.w }"/>
			<path d="M92 184 C88 207 92 230 104 240 L136 240 C148 230 152 207 148 184 C140 172 100 172 92 184 Z" fill="${ F.w }"/>
			<ellipse cx="120" cy="180" rx="17" ry="5.5" fill="${ F.g }"/>
			${ A.darkArms ? `<rect x="99" y="207" width="13" height="32" rx="6.5" fill="${ F.g }"/><rect x="128" y="207" width="13" height="32" rx="6.5" fill="${ F.g }"/>` : '' }
			<ellipse cx="110" cy="240" rx="10" ry="6.5" fill="${ F.g }"/><ellipse cx="130" cy="240" rx="10" ry="6.5" fill="${ F.g }"/>
			<circle cx="120" cy="203" r="8.5" fill="${ F.g }"/>
			<circle class="eb-core-glow" cx="120" cy="203" r="5.8" fill="${ F.c.led }" filter="url(#${ F.u }f)"/>
			<circle cx="118" cy="201" r="1.8" fill="#fff" opacity=".8"/>`;
	}

	function head( F, A ) {
		const pod = ( x ) =>
			`<circle cx="${ x }" cy="110" r="11.5" fill="${ F.g }"/><circle cx="${ x }" cy="110" r="7.5" fill="${ F.a }"/><circle cx="${ x - 2 }" cy="107" r="2.2" fill="#fff" opacity=".7"/>`;
		return `<path d="M50 105 C50 60 80 42 120 42 C160 42 190 60 190 105 C190 140 160 156 120 156 C80 156 50 140 50 105 Z" fill="${ F.w }"/>
			<ellipse cx="98" cy="60" rx="26" ry="9" transform="rotate(-14 98 60)" fill="#fff" opacity=".75"/>
			${ A.noDome ? '' : `<path d="M90 54 C104 47 136 47 150 54" fill="none" stroke="${ F.c.tintDeep }" stroke-width="1.4"/><ellipse cx="120" cy="51" rx="8" ry="4.5" fill="${ F.a }"/>` }
			${ A.noPods ? '' : pod( 52 ) + pod( 188 ) }`;
	}

	function accessory( id, F ) {
		const c = F.c;
		switch ( id ) {
			case 'beanie':
				return `<path d="M80 56 C78 18 162 18 160 56 Z" fill="${ F.a }"/><rect x="76" y="46" width="88" height="14" rx="7" fill="${ c.dark }"/>
					<circle cx="120" cy="20" r="9" fill="${ F.w }"/>`;
			case 'beret':
				return `<ellipse cx="126" cy="40" rx="44" ry="13" transform="rotate(-10 126 40)" fill="${ F.g }"/>
					<ellipse cx="116" cy="36" rx="20" ry="4" transform="rotate(-10 116 36)" fill="#fff" opacity=".12"/><rect x="128" y="22" width="3.5" height="8" rx="1.5" fill="#2a333c"/><circle cx="150" cy="42" r="3" fill="${ c.led }"/>`;
			case 'bowler':
				return `<path d="M92 46 C92 8 148 8 148 46 Z" fill="${ F.g }"/><rect x="92" y="36" width="56" height="8" fill="${ c.base }"/>
					<ellipse cx="120" cy="47" rx="40" ry="7" fill="#262f37"/><ellipse cx="108" cy="20" rx="8" ry="4" transform="rotate(-25 108 20)" fill="#fff" opacity=".15"/>`;
			case 'tophat':
				return `<path d="M97 46 L99 0 Q120 -4 141 0 L143 46 Z" fill="${ F.g }"/><ellipse cx="120" cy="0" rx="21" ry="4" fill="#3f4a55"/>
					<rect x="97.6" y="33" width="44.8" height="9" fill="${ c.base }"/><ellipse cx="120" cy="47" rx="40" ry="7" fill="#262f37"/>
					<path d="M105 7 L104 28" stroke="#fff" stroke-opacity=".15" stroke-width="4" stroke-linecap="round"/>`;
			case 'crown':
				return `<path d="M90 48 L86 16 L103 32 L120 8 L137 32 L154 16 L150 48 Z" fill="${ F.o }"/><rect x="89" y="40" width="62" height="9" rx="3" fill="#d39a1f"/>
					<circle cx="86" cy="16" r="3" fill="${ F.o }"/><circle cx="120" cy="8" r="3" fill="${ F.o }"/><circle cx="154" cy="16" r="3" fill="${ F.o }"/>
					<circle cx="120" cy="30" r="4" fill="${ c.base }"/><circle cx="104" cy="44.5" r="2.8" fill="${ c.base }"/><circle cx="136" cy="44.5" r="2.8" fill="${ c.base }"/>`;
			case 'headphones':
				return `<path d="M50 100 C46 10 194 10 190 100" fill="none" stroke="#2a333c" stroke-width="13" stroke-linecap="round"/>
					<path d="M50 100 C46 10 194 10 190 100" fill="none" stroke="${ F.w }" stroke-width="8" stroke-linecap="round"/>
					<rect x="35" y="86" width="26" height="42" rx="11" fill="${ F.a }"/><rect x="41" y="92" width="14" height="30" rx="7" fill="${ F.g }"/>
					<rect x="179" y="86" width="26" height="42" rx="11" fill="${ F.a }"/><rect x="185" y="92" width="14" height="30" rx="7" fill="${ F.g }"/>
					<circle cx="48" cy="107" r="3" fill="${ c.led }"/><circle cx="192" cy="107" r="3" fill="${ c.led }"/>`;
			case 'glasses':
				return `<circle cx="94" cy="104" r="17" fill="#fff" fill-opacity=".07"/><circle cx="146" cy="104" r="17" fill="#fff" fill-opacity=".07"/>
					<g fill="none" stroke="${ c.tint }" stroke-width="3.2" stroke-linecap="round"><circle cx="94" cy="104" r="17"/><circle cx="146" cy="104" r="17"/>
					<path d="M111 101 Q120 95 129 101"/><path d="M77 100 L64 95"/><path d="M163 100 L176 95"/></g>
					<path d="M84 95 L89 90" stroke="#fff" stroke-opacity=".5" stroke-width="2.4" stroke-linecap="round"/>`;
			case 'bow':
				return `<g transform="translate(152 46) rotate(18)"><path d="M0 0 C-10 -15 -26 -11 -24 0 C-26 11 -10 15 0 0 Z" fill="${ F.a }"/>
					<path d="M0 0 C10 -15 26 -11 24 0 C26 11 10 15 0 0 Z" fill="${ F.a }"/><circle r="5.5" fill="${ c.dark }"/></g>`;
			case 'hardhat':
				return `<path d="M82 50 C82 14 158 14 158 50 Z" fill="${ F.y }"/><rect x="116" y="15" width="8" height="35" rx="3" fill="#e2a000"/>
					<ellipse cx="120" cy="50" rx="46" ry="7" fill="#e9a800"/><ellipse cx="102" cy="28" rx="8" ry="4" transform="rotate(-30 102 28)" fill="#fff" opacity=".45"/>`;
			default:
				return '';
		}
	}

	/* Effects live in body space, placed around the enlarged head. */
	function fx( state, F ) {
		const c = F.c;
		const star = ( x, y, s ) =>
			`<path class="sp" d="M${ x } ${ y - s } Q${ x } ${ y } ${ x + s } ${ y } Q${ x } ${ y } ${ x } ${ y + s } Q${ x } ${ y } ${ x - s } ${ y } Q${ x } ${ y } ${ x } ${ y - s } Z" fill="${ c.base }"/>`;
		switch ( state ) {
			case 'think':
				return `<g class="fx-think" fill="${ c.base }"><circle cx="186" cy="44" r="3.2"/><circle cx="197" cy="31" r="4.4"/><circle cx="211" cy="16" r="6"/></g>`;
			case 'sleep':
				return `<g class="fx-z" fill="${ c.dark }" font-family="ui-rounded, system-ui, sans-serif" font-weight="700"><text x="178" y="52" font-size="13">z</text><text x="190" y="36" font-size="16">z</text><text x="204" y="18" font-size="20">z</text></g>`;
			case 'oops':
				return `<path class="fx-sweat" d="M192 66 C192 66 185 76 185 80 C185 84 188 87 192 87 C196 87 199 84 199 80 C199 76 192 66 192 66 Z" fill="#7cc8f2"/>`;
			case 'happy':
			case 'laugh':
				return `<g>${ star( 36, 52, 7 ) }${ star( 206, 46, 6 ) }${ star( 216, 116, 5 ) }${ star( 26, 140, 5 ) }</g>`;
			case 'grumpy':
				return `<path d="M175 58 C179 58 180 59 180 63 M189 58 C185 58 184 59 184 63 M175 70 C179 70 180 69 180 65 M189 70 C185 70 184 69 184 65" fill="none" stroke="#e5484d" stroke-width="2.6" stroke-linecap="round"/>`;
			default:
				return '';
		}
	}

	function fills( u, c ) {
		return { u, c, w: `url(#${ u }w)`, a: `url(#${ u }a)`, v: `url(#${ u }v)`, g: `url(#${ u }g)`, o: `url(#${ u }o)`, y: `url(#${ u }y)` };
	}

	function buildSvg( cfg, u, crop, isStatic ) {
		const c = palette( cfg.color );
		const F = fills( u, c );
		const A = PARTS[ cfg.animal ] || PARTS.fox;
		const full = crop !== 'head';
		const ears = `<g class="eb-ear-l" style="transform-origin:${ A.earOrigin[ 0 ] }">${ A.ears( F ) }</g><g class="eb-ear-r" style="transform-origin:${ A.earOrigin[ 1 ] }">${ mir( A.ears( F ) ) }</g>`;
		const rg = ( id, stops ) =>
			`<radialGradient id="${ u }${ id }" cx="35%" cy="28%" r="80%">${ stops.map( ( [ o, col ] ) => `<stop offset="${ o }" stop-color="${ col }"/>` ).join( '' ) }</radialGradient>`;

		return `<svg class="eb${ isStatic ? ' is-static' : '' }" viewBox="${ full ? '0 -40 240 296' : '26 -38 188 222' }" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
			<defs>
				${ rg( 'w', [ [ 0, '#ffffff' ], [ 0.6, c.tint ], [ 1, c.tintDeep ] ] ) }
				${ rg( 'a', [ [ 0, c.light ], [ 0.55, c.base ], [ 1, c.dark ] ] ) }
				${ rg( 'g', [ [ 0, '#56636f' ], [ 1, '#20282f' ] ] ) }
				${ rg( 'o', [ [ 0, '#ffe58f' ], [ 1, '#e0a526' ] ] ) }
				${ rg( 'y', [ [ 0, '#ffe066' ], [ 1, '#f2a900' ] ] ) }
				<linearGradient id="${ u }v" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3a4752"/><stop offset="1" stop-color="#161d24"/></linearGradient>
				<filter id="${ u }f" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="1.5" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
			</defs>
			${ full ? '<ellipse class="eb-shadow" cx="120" cy="247" rx="58" ry="7"/>' : '' }
			<g class="eb-root">
				${ full ? `<g class="eb-tail" style="transform-origin:${ A.tailOrigin }">${ A.tail( F ) }</g>${ body( F, A ) }` : '' }
				<g class="eb-head"><g class="eb-look"><g transform="${ HEAD_T }">
					${ A.front ? '' : ears }
					${ head( F, A ) }
					${ A.front ? ears : '' }
					${ A.extra ? A.extra( F ) : '' }
					<path d="M66 102 C66 80 88 72 120 79 C152 72 174 80 174 102 C174 128 156 141 120 139 C84 141 66 128 66 102 Z" fill="${ F.v }"/>
					<path d="M77 92 C83 83 100 80 113 83" fill="none" stroke="#fff" stroke-opacity=".14" stroke-width="3" stroke-linecap="round"/>
					<g class="eb-face" filter="url(#${ u }f)">
						<g class="eb-eye-l" transform="translate(94 104)"></g>
						<g class="eb-eye-r" transform="translate(146 104)"></g>
						<path d="M115.5 116.5 Q120 115 124.5 116.5 Q122.5 121 120 121.8 Q117.5 121 115.5 116.5 Z" fill="${ c.led }"/>
						<g class="eb-mouth"></g>
					</g>
					<g class="eb-acc"></g>
				</g></g></g>
				<g class="eb-fx"></g>
			</g>
		</svg>`;
	}

	/* ---------- Living instances ---------- */
	const LIVE = new Set();
	const AUTO_SLEEP = 5 * 60 * 1000;
	const reduceMQ = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	let uid = 0;
	let started = false;
	let px = null;
	let py = null;
	let lastMove = performance.now();

	function tick( now ) {
		LIVE.forEach( ( bot ) => {
			if ( ! bot.svg.isConnected ) {
				return;
			}
			bot.frame( px, py, reduceMQ.matches );
			if ( now > bot.nextBlink ) {
				bot.blink();
				if ( Math.random() < 0.2 ) {
					setTimeout( () => bot.blink(), 280 );
				}
				bot.nextBlink = now + 2200 + Math.random() * 4200;
			}
			if ( bot.opts.autoSleep && bot.state === 'idle' && bot.base === 'idle' && now - lastMove > AUTO_SLEEP ) {
				bot.setState( 'sleep', { auto: true } );
			}
		} );
		window.requestAnimationFrame( tick );
	}

	function start() {
		if ( started ) {
			return;
		}
		started = true;
		window.addEventListener(
			'pointermove',
			( e ) => {
				px = e.clientX;
				py = e.clientY;
				lastMove = performance.now();
				LIVE.forEach( ( bot ) => {
					if ( bot.state === 'sleep' && bot.auto ) {
						bot.setState( 'happy', { duration: 900 } );
					}
				} );
			},
			{ passive: true }
		);
		window.requestAnimationFrame( tick );
	}

	class Avatar {
		/**
		 * @param {Element} el   Container.
		 * @param {Object}  cfg  { animal, color, accessory }.
		 * @param {Object}  opts { crop: 'full'|'head', interactive: bool, autoSleep: bool }.
		 */
		constructor( el, cfg, opts = {} ) {
			this.el = el;
			this.opts = Object.assign( { crop: 'full', interactive: true, autoSleep: false }, opts );
			this.u = 'eobot' + ++uid + '_';
			this.state = 'idle';
			this.base = 'idle';
			this.auto = false;
			this.lx = 0;
			this.ly = 0;
			this.nextBlink = performance.now() + 1500 + Math.random() * 3000;
			this.render( cfg );
			if ( this.opts.interactive ) {
				LIVE.add( this );
				start();
			}
		}

		render( cfg ) {
			this.cfg = Object.assign( {}, cfg );
			this.pal = palette( this.cfg.color );
			this.el.innerHTML = buildSvg( this.cfg, this.u, this.opts.crop, ! this.opts.interactive );
			const s = ( this.svg = this.el.querySelector( 'svg' ) );
			this.eyeL = s.querySelector( '.eb-eye-l' );
			this.eyeR = s.querySelector( '.eb-eye-r' );
			this.mouthEl = s.querySelector( '.eb-mouth' );
			this.look = s.querySelector( '.eb-look' );
			this.accEl = s.querySelector( '.eb-acc' );
			this.fxEl = s.querySelector( '.eb-fx' );
			this.apply();
		}

		apply() {
			const ex = EXPR[ this.state ] || EXPR.idle;
			const F = fills( this.u, this.pal );
			this.svg.setAttribute( 'class', `eb st-${ this.state }${ this.opts.interactive ? '' : ' is-static' }` );
			this.eyeL.innerHTML = dots( PAT[ ex.l ], this.pal.led );
			this.eyeR.innerHTML = dots( PAT[ ex.r ], this.pal.led );
			this.mouthEl.innerHTML = mouth( ex.m, this.pal.led );
			// The bot puts its hard hat on by itself while it works.
			this.accEl.innerHTML = accessory( this.state === 'work' ? 'hardhat' : this.cfg.accessory, F );
			this.fxEl.innerHTML = this.opts.crop === 'head' ? '' : fx( this.state, F );
		}

		setState( st, { duration = 0, auto = false } = {} ) {
			clearTimeout( this.timer );
			this.auto = auto;
			if ( st !== this.state ) {
				this.state = st;
				this.apply();
			}
			if ( duration ) {
				this.timer = setTimeout( () => {
					this.auto = false;
					if ( this.state !== this.base ) {
						this.state = this.base;
						this.apply();
					}
				}, duration );
			}
		}

		setBase( st ) {
			this.base = st;
			this.setState( st );
		}

		blink() {
			if ( ! ( EXPR[ this.state ] || {} ).blink ) {
				return;
			}
			const closed = dots( PAT.closed, this.pal.led );
			this.eyeL.innerHTML = closed;
			this.eyeR.innerHTML = closed;
			setTimeout( () => {
				const e = EXPR[ this.state ] || EXPR.idle;
				this.eyeL.innerHTML = dots( PAT[ e.l ], this.pal.led );
				this.eyeR.innerHTML = dots( PAT[ e.r ], this.pal.led );
			}, 120 );
		}

		frame( x, y, reduce ) {
			const ex = EXPR[ this.state ] || EXPR.idle;
			let tx = 0;
			let ty = 0;
			if ( ex.gaze ) {
				[ tx, ty ] = ex.gaze;
			} else if ( x !== null ) {
				const r = this.svg.getBoundingClientRect();
				const dx = x - ( r.left + r.width / 2 );
				const dy = y - ( r.top + r.height * 0.5 );
				tx = ( dx / ( Math.abs( dx ) + 180 ) ) * 6;
				ty = ( dy / ( Math.abs( dy ) + 180 ) ) * 4.5;
			}
			this.lx += ( tx - this.lx ) * 0.12;
			this.ly += ( ty - this.ly ) * 0.12;
			this.eyeL.setAttribute( 'transform', `translate(${ ( 94 + this.lx ).toFixed( 2 ) } ${ ( 104 + this.ly ).toFixed( 2 ) })` );
			this.eyeR.setAttribute( 'transform', `translate(${ ( 146 + this.lx ).toFixed( 2 ) } ${ ( 104 + this.ly ).toFixed( 2 ) })` );
			this.look.setAttribute( 'transform', reduce ? '' : `rotate(${ ( this.lx * 0.6 ).toFixed( 2 ) } 120 178)` );
		}

		destroy() {
			LIVE.delete( this );
			clearTimeout( this.timer );
		}
	}

	window.EoBots = window.EoBots || {};
	window.EoBots.Avatar = Avatar;
	window.EoBots.palette = palette;
} )();
