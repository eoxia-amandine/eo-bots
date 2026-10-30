/**
 * EO-BOTS creation screen: arrows at the head change the accessory, arrows at the body change the animal,
 * the bottom row changes the color; the name plate is edited in place; the dice picks everything at random.
 */
( function () {
	'use strict';

	const { __, sprintf } = window.wp.i18n;
	const data = window.eobotsCreator;
	const root = document.getElementById( 'eobots-creator' );

	if ( ! root || ! data || ! window.EoBots ) {
		return;
	}

	const { catalog } = data;
	const cfg = Object.assign( {}, data.avatar );
	let isNew = data.isNew;

	const pick = ( list ) => list[ Math.floor( Math.random() * list.length ) ];
	const wait = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );
	const esc = ( s ) => String( s ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' } )[ c ] );
	const chevron = ( left ) =>
		`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="${ left ? 'M15 5l-7 7 7 7' : 'M9 5l7 7-7 7' }"/></svg>`;
	const arrow = ( cycle, dir, label, cls = '' ) =>
		`<button type="button" class="eobots-arrow ${ cls }" data-cycle="${ cycle }" data-dir="${ dir }" aria-label="${ esc( label ) }">${ chevron( dir < 0 ) }</button>`;

	root.innerHTML = `
		<div class="eobots-creator">
			<h2 class="eobots-title"></h2>
			<section class="eobots-card" aria-label="${ esc( __( 'Customize your EO-BOT', 'eo-bots' ) ) }">
				<p class="eobots-bubble" aria-live="polite" hidden></p>
				<div class="eobots-rig">
					<div class="eobots-stage" role="img"></div>
					${ arrow( 'accessory', -1, __( 'Previous accessory', 'eo-bots' ), 'is-left is-head' ) }
					${ arrow( 'accessory', 1, __( 'Next accessory', 'eo-bots' ), 'is-right is-head' ) }
					${ arrow( 'animal', -1, __( 'Previous animal', 'eo-bots' ), 'is-left is-body' ) }
					${ arrow( 'animal', 1, __( 'Next animal', 'eo-bots' ), 'is-right is-body' ) }
				</div>
				<div class="eobots-color-row">
					${ arrow( 'color', -1, __( 'Previous color', 'eo-bots' ) ) }
					<span class="eobots-dot" aria-hidden="true"></span>
					${ arrow( 'color', 1, __( 'Next color', 'eo-bots' ) ) }
				</div>
				<p class="eobots-change is-faded" aria-live="polite"></p>
				<input class="eobots-plate" type="text" maxlength="${ catalog.nameMax }" autocomplete="off" spellcheck="false"
					aria-label="${ esc( __( 'Name of your EO-BOT', 'eo-bots' ) ) }" title="${ esc( __( 'Click to rename', 'eo-bots' ) ) }">
				<button type="button" class="eobots-dice" aria-label="${ esc( __( 'Pick at random', 'eo-bots' ) ) }" title="${ esc( __( 'Pick at random', 'eo-bots' ) ) }">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><rect x="3.5" y="3.5" width="17" height="17" rx="4"/><circle cx="8.5" cy="8.5" r="1.3" fill="currentColor"/><circle cx="15.5" cy="15.5" r="1.3" fill="currentColor"/><circle cx="12" cy="12" r="1.3" fill="currentColor"/><circle cx="15.5" cy="8.5" r="1.3" fill="currentColor"/><circle cx="8.5" cy="15.5" r="1.3" fill="currentColor"/></svg>
				</button>
			</section>
			<button type="button" class="eobots-create"><span class="eobots-spark" aria-hidden="true"></span><span>${ esc( __( 'Create my EO-BOT', 'eo-bots' ) ) }</span></button>
			<section class="eobots-brain" aria-labelledby="eobots-brain-title" hidden>
				<h3 id="eobots-brain-title">${ esc( __( 'AI model', 'eo-bots' ) ) }</h3>
				<div class="eobots-brain-body"></div>
			</section>
			<div class="eobots-modal" role="dialog" aria-modal="true" aria-labelledby="eobots-modal-title" hidden>
				<div class="eobots-modal-box">
					<div class="eobots-modal-bot" aria-hidden="true"></div>
					<h3 class="eobots-modal-title" id="eobots-modal-title"></h3>
					<p class="eobots-modal-text"></p>
					<div class="eobots-modal-actions"></div>
				</div>
			</div>
		</div>`;

	const $ = ( s ) => root.querySelector( s );
	const wrap = $( '.eobots-creator' );
	const stageEl = $( '.eobots-stage' );
	const bubble = $( '.eobots-bubble' );
	const nameInput = $( '.eobots-plate' );
	const createBtn = $( '.eobots-create' );
	const brain = $( '.eobots-brain' );
	const modal = $( '.eobots-modal' );
	const bot = new window.EoBots.Avatar( stageEl, cfg, { autoSleep: true } );

	function setLabels() {
		$( '.eobots-title' ).textContent = isNew ? __( 'Create your EO-BOT', 'eo-bots' ) : __( 'My EO-BOT', 'eo-bots' );
	}

	function update( partial ) {
		Object.assign( cfg, partial );
		const p = window.EoBots.palette( cfg.color );
		wrap.style.setProperty( '--eobots-accent', p.base );
		wrap.style.setProperty( '--eobots-accent-ink', p.ink );
		wrap.style.setProperty( '--eobots-led', p.led );
		bot.render( cfg );
		const animal = catalog.animals.find( ( a ) => a.id === cfg.animal );
		/* translators: 1: bot name, 2: animal. */
		stageEl.setAttribute( 'aria-label', sprintf( __( '%1$s, your EO-BOT %2$s', 'eo-bots' ), cfg.name, animal ? animal.label.toLowerCase() : '' ) );
	}

	function say( text, ms = 3200 ) {
		clearTimeout( bubble.timer );
		if ( ! text ) {
			bubble.hidden = true;
			return;
		}
		bubble.textContent = text;
		bubble.hidden = false;
		bubble.timer = setTimeout( () => {
			bubble.hidden = true;
		}, ms );
	}

	let changeTimer;
	function showChange( text ) {
		const el = $( '.eobots-change' );
		el.textContent = text;
		el.classList.remove( 'is-faded' );
		clearTimeout( changeTimer );
		changeTimer = setTimeout( () => el.classList.add( 'is-faded' ), 1400 );
	}

	/* Arrows */
	const CYCLES = {
		accessory: { list: catalog.accessories, key: 'accessory', id: ( x ) => x.id, label: __( 'Accessory', 'eo-bots' ) },
		animal: { list: catalog.animals, key: 'animal', id: ( x ) => x.id, label: __( 'Animal', 'eo-bots' ) },
		color: { list: catalog.colors, key: 'color', id: ( x ) => x.hex, label: __( 'Color', 'eo-bots' ) },
	};
	root.querySelectorAll( '[data-cycle]' ).forEach( ( button ) =>
		button.addEventListener( 'click', () => {
			const c = CYCLES[ button.dataset.cycle ];
			const n = c.list.length;
			let i = c.list.findIndex( ( x ) => c.id( x ).toLowerCase() === String( cfg[ c.key ] ).toLowerCase() );
			i = ( ( i < 0 ? 0 : i ) + Number( button.dataset.dir ) + n ) % n;
			update( { [ c.key ]: c.id( c.list[ i ] ) } );
			showChange( `${ c.label } · ${ c.list[ i ].label }` );
		} )
	);

	/* Name plate */
	nameInput.value = cfg.name;
	nameInput.addEventListener( 'input', () => {
		cfg.name = nameInput.value;
	} );
	nameInput.addEventListener( 'focus', () => nameInput.select() );
	nameInput.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Enter' ) {
			nameInput.blur();
		}
	} );
	nameInput.addEventListener( 'blur', () => {
		if ( ! nameInput.value.trim() ) {
			nameInput.value = cfg.name = data.avatar.name || 'Nova';
		}
	} );

	/* Dice */
	$( '.eobots-dice' ).addEventListener( 'click', () => {
		const name = pick( catalog.names.filter( ( n ) => n !== cfg.name ) );
		update( { animal: pick( catalog.animals ).id, color: pick( catalog.colors ).hex, accessory: pick( catalog.accessories ).id, name } );
		nameInput.value = name;
		bot.setState( 'happy', { duration: 1100 } );
		showChange( __( 'Surprise!', 'eo-bots' ) );
	} );

	/* Click the bot: it laughs, and gets grumpy after five quick clicks. */
	let pokes = [];
	stageEl.addEventListener( 'click', () => {
		if ( [ 'unborn', 'birth' ].includes( bot.state ) ) {
			return;
		}
		const now = performance.now();
		pokes = pokes.filter( ( p ) => now - p < 2500 ).concat( now );
		if ( pokes.length >= 5 ) {
			pokes = [];
			bot.setState( 'grumpy', { duration: 1800 } );
			say( __( 'Hey! That tickles!', 'eo-bots' ), 1800 );
		} else {
			bot.setState( 'laugh', { duration: 1100 } );
			say( pick( [ __( 'Hee hee!', 'eo-bots' ), __( 'Ha ha!', 'eo-bots' ) ] ), 1100 );
		}
	} );

	/* Popup shown once the EO-BOT is alive. */
	let lastFocus = null;

	function closeModal() {
		modal.hidden = true;
		if ( lastFocus ) {
			lastFocus.focus();
		}
	}

	function openModal( aiReady ) {
		let title;
		let text;
		let actions;
		const later = `<button type="button" class="eobots-modal-secondary" data-close>${ esc( __( 'Later', 'eo-bots' ) ) }</button>`;

		if ( aiReady ) {
			/* translators: %s: bot name. */
			title = sprintf( __( 'Hi, I am %s!', 'eo-bots' ), cfg.name );
			text = __( 'I live in the bottom right corner of your screen, on every page of your site. Click me to talk to me!', 'eo-bots' );
			actions = `<a class="eobots-modal-primary" href="${ esc( data.dashboardUrl ) }">${ esc( __( 'Let’s go!', 'eo-bots' ) ) }</a>
				<button type="button" class="eobots-modal-secondary" data-close>${ esc( __( 'Stay here', 'eo-bots' ) ) }</button>`;
		} else if ( data.canConnect ) {
			title = __( 'I just need a spark: an AI!', 'eo-bots' );
			/* translators: %s: bot name. */
			text = sprintf( __( 'To think, %s needs an AI. Connect a provider (Anthropic, OpenAI or Google) with your API key in Settings › Connectors. One key is enough for the whole site.', 'eo-bots' ), cfg.name );
			actions = `<a class="eobots-modal-primary" href="${ esc( data.connectorsUrl ) }">${ esc( __( 'Connect an AI', 'eo-bots' ) ) }</a>${ later }`;
		} else {
			title = __( 'I just need a spark: an AI!', 'eo-bots' );
			text = __( 'One last thing: ask an administrator to connect an AI provider in Settings › Connectors.', 'eo-bots' );
			actions = `<button type="button" class="eobots-modal-primary" data-close>${ esc( __( 'OK', 'eo-bots' ) ) }</button>`;
		}

		modal.querySelector( '.eobots-modal-title' ).textContent = title;
		modal.querySelector( '.eobots-modal-text' ).textContent = text;
		modal.querySelector( '.eobots-modal-actions' ).innerHTML = actions;
		new window.EoBots.Avatar( modal.querySelector( '.eobots-modal-bot' ), cfg, { crop: 'head', interactive: false } ); // eslint-disable-line no-new

		lastFocus = document.activeElement;
		modal.hidden = false;
		modal.querySelector( '.eobots-modal-actions a, .eobots-modal-actions button' ).focus();
	}

	modal.addEventListener( 'click', ( e ) => {
		if ( e.target === modal || e.target.closest( '[data-close]' ) ) {
			closeModal();
		}
	} );
	modal.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Escape' ) {
			closeModal();
		} else if ( e.key === 'Tab' ) {
			// Keep the focus inside the popup.
			const items = [ ...modal.querySelectorAll( 'a, button' ) ];
			const first = items[ 0 ];
			const last = items[ items.length - 1 ];
			if ( e.shiftKey && document.activeElement === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		}
	} );

	/* AI model, shared by the whole site (administrators only). */
	async function showBrain() {
		if ( ! data.canConnect || ! brain.hidden ) {
			return;
		}
		brain.hidden = false;
		const body = brain.querySelector( '.eobots-brain-body' );
		body.innerHTML = `<p class="eobots-brain-help">${ esc( __( 'Loading…', 'eo-bots' ) ) }</p>`;

		let res;
		try {
			res = await window.wp.apiFetch( { path: '/eobots/v1/models' } );
		} catch ( e ) {
			body.innerHTML = `<p class="eobots-brain-help">${ esc( __( 'Something went wrong.', 'eo-bots' ) ) }</p>`;
			return;
		}

		if ( ! res.aiReady ) {
			body.innerHTML = `<p class="eobots-brain-help">${ esc( __( 'No AI provider is connected yet.', 'eo-bots' ) ) }</p>
				<a class="eobots-modal-primary" href="${ esc( data.connectorsUrl ) }">${ esc( __( 'Connect an AI', 'eo-bots' ) ) }</a>`;
			return;
		}

		const groups = {};
		res.models.forEach( ( m ) => {
			( groups[ m.provider ] = groups[ m.provider ] || [] ).push( m );
		} );
		const options = Object.keys( groups )
			.map( ( provider ) => `<optgroup label="${ esc( provider ) }">${ groups[ provider ].map( ( m ) => `<option value="${ esc( m.id ) }">${ esc( m.name ) }</option>` ).join( '' ) }</optgroup>` )
			.join( '' );

		body.innerHTML = `<p class="eobots-brain-help">${ esc( __( 'Used by the EO-BOTS of every user on this site. A bigger model is smarter but uses more credits.', 'eo-bots' ) ) }</p>
			<div class="eobots-brain-row">
				<select id="eobots-model" aria-label="${ esc( __( 'AI model', 'eo-bots' ) ) }">
					<option value="">${ esc( __( 'Automatic (recommended)', 'eo-bots' ) ) }</option>${ options }
				</select>
				<span class="eobots-brain-status" aria-live="polite"></span>
			</div>
			<a class="eobots-brain-link" href="${ esc( data.connectorsUrl ) }">${ esc( __( 'Manage AI providers', 'eo-bots' ) ) }</a>`;

		const select = body.querySelector( 'select' );
		const status = body.querySelector( '.eobots-brain-status' );
		select.value = res.current;
		select.addEventListener( 'change', async () => {
			status.textContent = '';
			select.disabled = true;
			try {
				await window.wp.apiFetch( { path: '/eobots/v1/models', method: 'POST', data: { model: select.value } } );
				status.textContent = __( 'Saved', 'eo-bots' );
				bot.setState( 'happy', { duration: 1000 } );
			} catch ( e ) {
				status.textContent = ( e && e.message ) || __( 'Something went wrong.', 'eo-bots' );
			}
			select.disabled = false;
		} );
	}

	/* Save, then bring it to life. */
	createBtn.addEventListener( 'click', async () => {
		createBtn.disabled = true;
		say( null );

		let response;
		try {
			response = await window.wp.apiFetch( {
				path: '/eobots/v1/avatar',
				method: 'POST',
				data: { animal: cfg.animal, color: cfg.color, accessory: cfg.accessory, name: cfg.name },
			} );
		} catch ( error ) {
			bot.setState( 'oops', { duration: 2000 } );
			say( error && error.message ? error.message : __( 'I could not save. Try again.', 'eo-bots' ), 4000 );
			createBtn.disabled = false;
			return;
		}

		Object.assign( cfg, response.avatar );
		nameInput.value = cfg.name;

		bot.setBase( 'unborn' );
		await wait( 900 );
		bot.setState( 'birth' );
		await wait( 1500 );
		bot.base = 'idle';
		bot.setState( 'happy', { duration: 2400 } );

		isNew = false;
		setLabels();
		openModal( response.aiReady );
		showBrain();
		createBtn.disabled = false;
	} );

	setLabels();
	update( {} );
	if ( ! isNew ) {
		showBrain();
	}
} )();
