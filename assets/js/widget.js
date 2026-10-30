/**
 * Floating EO-BOT and its chat panel, in admin and on the public site.
 *
 * Rendered in a shadow root so theme styles cannot break it. The browser drives the agent loop:
 * POST /chat, then /chat/continue while the server answers `continue`; `confirm` waits for the user.
 */
( function () {
	'use strict';

	const { __, sprintf } = window.wp.i18n;
	const data = window.eobotsWidget;

	if ( ! data || ! window.EoBots || document.getElementById( 'eobots-widget' ) ) {
		return;
	}

	/* ---------- Helpers ---------- */
	const esc = ( s ) => String( s ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' } )[ c ] );
	const storage = ( area ) => ( {
		get( key ) {
			try {
				return window[ area ].getItem( `eobots:${ data.userId }:${ key }` );
			} catch ( e ) {
				return null;
			}
		},
		set( key, value ) {
			try {
				if ( value === null ) {
					window[ area ].removeItem( `eobots:${ data.userId }:${ key }` );
				} else {
					window[ area ].setItem( `eobots:${ data.userId }:${ key }`, value );
				}
			} catch ( e ) {}
		},
	} );
	const local = storage( 'localStorage' );
	const session = storage( 'sessionStorage' );
	const api = ( path, body, method ) =>
		window.wp.apiFetch( { path: '/eobots/v1' + path, method: method || ( body ? 'POST' : 'GET' ), data: body } );
	const context = () => Object.assign( {}, data.context, { url: window.location.href } );

	/** Small, safe Markdown: paragraphs, lists, bold, italic, code and links. */
	function markdown( text ) {
		const inline = ( s ) =>
			s
				.replace( /`([^`]+)`/g, '<code>$1</code>' )
				.replace( /\*\*([^*]+)\*\*/g, '<strong>$1</strong>' )
				.replace( /(^|[^*])\*([^*\s][^*]*)\*/g, '$1<em>$2</em>' )
				.replace( /\[([^\]]+)\]\(((?:https?:\/\/|\/)[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>' );

		const blocks = [];
		let list = null;
		let para = [];
		const flush = () => {
			if ( para.length ) {
				blocks.push( `<p>${ para.map( inline ).join( '<br>' ) }</p>` );
				para = [];
			}
			if ( list ) {
				blocks.push( `<${ list.tag }>${ list.items.map( ( i ) => `<li>${ inline( i ) }</li>` ).join( '' ) }</${ list.tag }>` );
				list = null;
			}
		};

		esc( text )
			.split( /\r?\n/ )
			.forEach( ( line ) => {
				const bullet = line.match( /^\s*[-*•]\s+(.*)$/ );
				const number = line.match( /^\s*\d+[.)]\s+(.*)$/ );
				if ( bullet || number ) {
					const tag = bullet ? 'ul' : 'ol';
					if ( para.length || ( list && list.tag !== tag ) ) {
						flush();
					}
					list = list || { tag, items: [] };
					list.items.push( ( bullet || number )[ 1 ] );
				} else if ( ! line.trim() ) {
					flush();
				} else {
					if ( list ) {
						flush();
					}
					para.push( line );
				}
			} );
		flush();
		return blocks.join( '' );
	}

	const icon = {
		history: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M4 6h16M4 12h16M4 18h10"/></svg>',
		plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14"/></svg>',
		close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>',
		send: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 3L10 14"/><path d="M21 3l-7 18-4-7-7-4 18-7z"/></svg>',
		check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>',
		pencil: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 20h4L19 9l-4-4L4 16v4z"/><path d="M13.5 6.5l4 4"/></svg>',
		trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg>',
	};

	/* ---------- Markup ---------- */
	const host = document.createElement( 'div' );
	host.id = 'eobots-widget';
	document.body.appendChild( host );
	const shadow = host.attachShadow( { mode: 'open' } );
	const name = data.avatar.name;

	shadow.innerHTML = `
		<style>:host{all:initial}.dock{visibility:hidden}</style>
		${ data.styles.map( ( href ) => `<link rel="stylesheet" href="${ esc( href ) }">` ).join( '' ) }
		<div class="dock">
			<section class="panel" id="panel" role="dialog" aria-labelledby="bot-name" hidden>
				<header class="head">
					<span class="avatar" aria-hidden="true"></span>
					<span class="title"><strong id="bot-name">${ esc( name ) }</strong><span class="subtitle"></span></span>
					<button type="button" class="icon" data-action="history" aria-label="${ esc( __( 'Your conversations', 'eo-bots' ) ) }" title="${ esc( __( 'Your conversations', 'eo-bots' ) ) }">${ icon.history }</button>
					<button type="button" class="icon" data-action="new" aria-label="${ esc( __( 'New conversation', 'eo-bots' ) ) }" title="${ esc( __( 'New conversation', 'eo-bots' ) ) }">${ icon.plus }</button>
					<button type="button" class="icon" data-action="close" aria-label="${ esc( __( 'Close', 'eo-bots' ) ) }" title="${ esc( __( 'Close', 'eo-bots' ) ) }">${ icon.close }</button>
				</header>
				<div class="log" aria-live="polite"></div>
				<div class="history" hidden>
					<div class="tabs" role="tablist">
						<button type="button" role="tab" id="tab-conversations" data-tab="conversations" aria-controls="pane-conversations">${ esc( __( 'Conversations', 'eo-bots' ) ) }</button>
						<button type="button" role="tab" id="tab-memory" data-tab="memory" aria-controls="pane-memory">${ esc( __( 'Memory', 'eo-bots' ) ) }</button>
					</div>
					<ul class="pane" id="pane-conversations" role="tabpanel" aria-labelledby="tab-conversations"></ul>
					<div class="pane memory" id="pane-memory" role="tabpanel" aria-labelledby="tab-memory" hidden></div>
				</div>
				<div class="working" hidden><span class="typing"><i></i><i></i><i></i></span><span class="working-text"></span></div>
				<div class="sugg"></div>
				<form class="composer">
					<textarea rows="1" maxlength="8000" aria-label="${ esc( __( 'Your message', 'eo-bots' ) ) }"></textarea>
					<button type="submit" class="send" aria-label="${ esc( __( 'Send', 'eo-bots' ) ) }">${ icon.send }</button>
				</form>
			</section>
			<p class="bubble" hidden></p>
			<button type="button" class="bot" aria-expanded="false" aria-controls="panel"></button>
		</div>`;

	const $ = ( s ) => shadow.querySelector( s );
	const panel = $( '.panel' );
	const log = $( '.log' );
	const history = $( '.history' );
	const working = $( '.working' );
	const sugg = $( '.sugg' );
	const form = $( '.composer' );
	const input = $( 'textarea' );
	const botButton = $( '.bot' );
	const bubble = $( '.bubble' );

	const bot = new window.EoBots.Avatar( botButton, data.avatar, { autoSleep: true } );
	new window.EoBots.Avatar( $( '.avatar' ), data.avatar, { crop: 'head', interactive: false } ); // eslint-disable-line no-new

	const accent = window.EoBots.palette( data.avatar.color );
	$( '.dock' ).style.setProperty( '--accent', accent.base );
	$( '.dock' ).style.setProperty( '--accent-ink', accent.ink );
	$( '.dock' ).style.setProperty( '--led', accent.led );
	/* translators: %s: bot name. */
	botButton.setAttribute( 'aria-label', sprintf( __( 'Talk to %s', 'eo-bots' ), name ) );
	input.placeholder = sprintf(
		/* translators: %s: bot name. */
		__( 'Ask %s…', 'eo-bots' ),
		name
	);

	/* ---------- State ---------- */
	let conversationId = Number( local.get( 'conversation' ) ) || 0;
	let busy = false;
	let loaded = false;

	function setConversation( id ) {
		conversationId = id || 0;
		local.set( 'conversation', conversationId ? String( conversationId ) : null );
	}

	function say( text, ms = 4000 ) {
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

	function setSubtitle() {
		$( '.subtitle' ).textContent = data.aiReady ? __( 'Ready to help', 'eo-bots' ) : __( 'Waiting for an AI', 'eo-bots' );
	}

	function setWorking( text, mood ) {
		working.hidden = ! text;
		$( '.working-text' ).textContent = text || '';
		if ( mood ) {
			bot.setBase( mood );
		}
		scroll();
	}

	function setBusy( value ) {
		busy = value;
		input.disabled = value || ! data.aiReady;
		$( '.send' ).disabled = value || ! data.aiReady;
		sugg.querySelectorAll( 'button' ).forEach( ( b ) => ( b.disabled = value ) );
	}

	function scroll() {
		log.scrollTop = log.scrollHeight;
	}

	/* ---------- Rendering ---------- */
	function add( html, className ) {
		const el = document.createElement( 'div' );
		el.className = className;
		el.innerHTML = html;
		log.appendChild( el );
		scroll();
		return el;
	}

	const addUser = ( text ) => add( esc( text ).replace( /\n/g, '<br>' ), 'msg me' );
	const addBot = ( text ) => add( markdown( text ), 'msg bot' );

	function addError( text, retry ) {
		const el = add( `<p>${ esc( text ) }</p>${ retry ? `<button type="button" class="link" data-retry>${ esc( __( 'Try again', 'eo-bots' ) ) }</button>` : '' }`, 'msg error' );
		const button = el.querySelector( '[data-retry]' );
		if ( button ) {
			button.addEventListener( 'click', () => {
				button.remove();
				run( () => api( '/chat/continue', { conversation_id: conversationId, context: context() } ) );
			} );
		}
		return el;
	}

	function addAction( card ) {
		const links = card.links || {};
		const el = add(
			`<span class="card-icon">${ icon.check }</span>
			<div class="card-body">
				<strong>${ esc( card.summary ) }</strong>
				<div class="card-actions">
					${ links.view ? `<a class="btn" href="${ esc( links.view ) }" target="_blank" rel="noopener">${ esc( __( 'View', 'eo-bots' ) ) }</a>` : '' }
					${ links.edit ? `<a class="btn" href="${ esc( links.edit ) }">${ esc( __( 'Edit', 'eo-bots' ) ) }</a>` : '' }
					${ card.undoable ? `<button type="button" class="btn" data-undo>${ esc( __( 'Undo', 'eo-bots' ) ) }</button>` : '' }
				</div>
				<span class="card-state">${ card.state === 'undone' ? esc( __( 'Undone', 'eo-bots' ) ) : '' }</span>
			</div>`,
			'card action' + ( card.state === 'undone' ? ' is-undone' : '' )
		);

		const undo = el.querySelector( '[data-undo]' );
		if ( undo ) {
			undo.addEventListener( 'click', async () => {
				undo.disabled = true;
				try {
					await api( `/journal/${ card.journalId }/undo`, {} );
					el.classList.add( 'is-undone' );
					el.querySelector( '.card-state' ).textContent = __( 'Undone', 'eo-bots' );
					undo.remove();
					bot.setState( 'happy', { duration: 1200 } );
				} catch ( error ) {
					undo.disabled = false;
					addError( error.message || __( 'I could not undo this.', 'eo-bots' ), false );
				}
			} );
		}
		return el;
	}

	const confirmStates = {
		approved: __( 'Done, you said yes.', 'eo-bots' ),
		declined: __( 'Cancelled, I did not touch anything.', 'eo-bots' ),
	};

	function addConfirm( card ) {
		const el = add(
			`<strong>${ esc( card.title ) }</strong>
			${ card.summary ? `<p>${ esc( card.summary ) }</p>` : '' }
			${ card.code ? `<details class="card-code"${ card.state === 'pending' ? ' open' : '' }><summary>${ esc( __( 'See the code', 'eo-bots' ) ) }${ card.lang ? ` (${ esc( card.lang.toUpperCase() ) })` : '' }</summary><pre><code>${ esc( card.code ) }</code></pre></details>` : '' }
			<div class="card-actions">
				<button type="button" class="btn ${ card.danger ? 'danger' : 'primary' }" data-approve="1">${ esc( __( 'Yes, go ahead', 'eo-bots' ) ) }</button>
				<button type="button" class="btn" data-approve="0">${ esc( __( 'No thanks', 'eo-bots' ) ) }</button>
			</div>
			<span class="card-state"></span>`,
			'card confirm' + ( card.danger ? ' is-danger' : '' )
		);
		el.dataset.callId = card.callId;
		setConfirmState( el, card.state );

		el.querySelectorAll( '[data-approve]' ).forEach( ( button ) =>
			button.addEventListener( 'click', () => {
				el.querySelectorAll( 'button' ).forEach( ( b ) => ( b.disabled = true ) );
				run( () =>
					api( '/chat/confirm', {
						conversation_id: conversationId,
						call_id: card.callId,
						approve: button.dataset.approve === '1',
						context: context(),
					} )
				);
			} )
		);
		return el;
	}

	function setConfirmState( el, state ) {
		if ( state && state !== 'pending' ) {
			const actions = el.querySelector( '.card-actions' );
			if ( actions ) {
				actions.remove();
			}
			el.querySelector( '.card-state' ).textContent = confirmStates[ state ] || '';
			el.classList.add( 'is-' + state );
		}
	}

	function render( message ) {
		switch ( message.type ) {
			case 'user':
				return addUser( message.text );
			case 'bot':
			case 'text':
				return addBot( message.text );
			case 'action':
				return addAction( message );
			case 'confirm':
				return addConfirm( message );
			case 'error':
				return addError( message.text, !! message.retry );
		}
	}

	function setSuggestions( list ) {
		sugg.innerHTML = list.map( ( s ) => `<button type="button" class="chip">${ esc( s ) }</button>` ).join( '' );
		sugg.querySelectorAll( 'button' ).forEach( ( b ) => b.addEventListener( 'click', () => send( b.textContent ) ) );
	}

	function welcome() {
		log.innerHTML = '';
		/* translators: %s: bot name. */
		addBot( sprintf( __( 'Hi, I am %s! What can I do for you?', 'eo-bots' ), name ) );

		if ( ! data.aiReady ) {
			const el = add(
				`<p>${ esc( data.canConnect ? __( 'To think, I need an AI. Connect a provider with your API key, it takes two minutes.', 'eo-bots' ) : __( 'To think, I need an AI. Ask an administrator to connect one in Settings › Connectors.', 'eo-bots' ) ) }</p>
				${ data.canConnect ? `<a class="btn primary" href="${ esc( data.connectorsUrl ) }">${ esc( __( 'Connect an AI', 'eo-bots' ) ) }</a>` : '' }`,
				'card notice'
			);
			el.setAttribute( 'role', 'note' );
			setSuggestions( [] );
			return;
		}

		setSuggestions(
			data.context.postId
				? [ __( 'Improve this page', 'eo-bots' ), __( 'Fix the typos on this page', 'eo-bots' ), __( 'Sum up this page', 'eo-bots' ) ]
				: [ __( 'Show me around my site', 'eo-bots' ), __( 'Create an “About” page', 'eo-bots' ), __( 'What can you do?', 'eo-bots' ) ]
		);
	}

	async function loadConversation( id ) {
		showHistory( false );
		log.innerHTML = '';
		setSuggestions( [] );
		try {
			const res = await api( `/conversations/${ id }` );
			setConversation( res.conversation.id );
			if ( ! res.messages.length ) {
				welcome();
			}
			res.messages.forEach( render );
			if ( res.pending ) {
				bot.setBase( 'listen' );
			}
		} catch ( e ) {
			setConversation( 0 );
			welcome();
		}
	}

	let historyTab = 'conversations';

	function showHistory( on ) {
		history.hidden = ! on;
		log.hidden = on;
		sugg.hidden = on;
		$( '[data-action="history"]' ).setAttribute( 'aria-pressed', String( on ) );
		if ( on ) {
			showTab( historyTab );
		}
	}

	function showTab( tab ) {
		historyTab = tab;
		history.querySelectorAll( '[role="tab"]' ).forEach( ( b ) => b.setAttribute( 'aria-selected', String( b.dataset.tab === tab ) ) );
		$( '#pane-conversations' ).hidden = tab !== 'conversations';
		$( '#pane-memory' ).hidden = tab !== 'memory';
		return tab === 'memory' ? loadMemory() : loadConversations();
	}

	history.querySelectorAll( '[role="tab"]' ).forEach( ( b ) => b.addEventListener( 'click', () => showTab( b.dataset.tab ) ) );

	/* ---------- Memory ---------- */
	async function loadMemory() {
		const pane = $( '#pane-memory' );
		pane.innerHTML = `<p class="empty">${ esc( __( 'Loading…', 'eo-bots' ) ) }</p>`;
		try {
			renderMemory( await api( '/notes' ) );
		} catch ( e ) {
			pane.innerHTML = `<p class="empty">${ esc( e.message || __( 'I could not open my memory.', 'eo-bots' ) ) }</p>`;
		}
	}

	function renderMemory( res ) {
		const pane = $( '#pane-memory' );
		const format = new Intl.DateTimeFormat( document.documentElement.lang || undefined, { dateStyle: 'medium' } );
		const notes = res.notes || [];

		let html = `<p class="memory-intro">${ esc( sprintf( __( '%s follows these notes in every conversation, for everyone on the site.', 'eo-bots' ), name ) ) }</p>`;
		if ( ! notes.length ) {
			html += `<p class="empty">${ esc(
				res.canEdit
					? sprintf( __( 'Nothing yet. When %s gets something wrong, correct it and say “remember this”.', 'eo-bots' ), name )
					: __( 'Nothing yet.', 'eo-bots' )
			) }</p>`;
		} else {
			html += `<ul class="notes">${ notes
				.map(
					( n ) => `<li data-note="${ n.id }">
						<div class="note-body">
							<p class="note-text">${ esc( n.text ) }</p>
							<span class="note-meta">${ esc( [ n.author, format.format( new Date( n.created ) ) ].filter( Boolean ).join( ' · ' ) ) }</span>
						</div>
						${ res.canEdit ? `<span class="note-actions">
							<button type="button" class="icon" data-edit aria-label="${ esc( __( 'Edit this note', 'eo-bots' ) ) }">${ icon.pencil }</button>
							<button type="button" class="icon" data-forget aria-label="${ esc( __( 'Delete this note', 'eo-bots' ) ) }">${ icon.trash }</button>
						</span>` : '' }
					</li>`
				)
				.join( '' ) }</ul>`;
		}
		if ( res.canEdit && notes.length < res.max ) {
			html += `<form class="note-form">
				<textarea rows="2" maxlength="${ res.maxLength }" aria-label="${ esc( __( 'New note', 'eo-bots' ) ) }" placeholder="${ esc( __( 'A rule to follow, e.g. “Buttons always say Book now”', 'eo-bots' ) ) }"></textarea>
				<div class="note-form-foot"><span class="note-count">${ notes.length } / ${ res.max }</span><button type="submit" class="btn primary">${ esc( __( 'Add', 'eo-bots' ) ) }</button></div>
			</form>`;
		}
		pane.innerHTML = html;

		const save = async ( request, el ) => {
			el.querySelectorAll( 'button, textarea' ).forEach( ( b ) => ( b.disabled = true ) );
			try {
				renderMemory( await request() );
			} catch ( e ) {
				el.querySelectorAll( 'button, textarea' ).forEach( ( b ) => ( b.disabled = false ) );
				let error = el.querySelector( '.note-error' );
				if ( ! error ) {
					error = document.createElement( 'p' );
					error.className = 'note-error';
					el.appendChild( error );
				}
				error.textContent = e.message || __( 'Something went wrong.', 'eo-bots' );
			}
		};

		const form = pane.querySelector( '.note-form' );
		if ( form ) {
			form.addEventListener( 'submit', ( e ) => {
				e.preventDefault();
				const text = form.querySelector( 'textarea' ).value.trim();
				if ( text ) {
					save( () => api( '/notes', { text } ), form );
				}
			} );
		}

		pane.querySelectorAll( '[data-note]' ).forEach( ( li ) => {
			const id = li.dataset.note;
			const forget = li.querySelector( '[data-forget]' );
			const edit = li.querySelector( '[data-edit]' );
			if ( forget ) {
				forget.addEventListener( 'click', () => save( () => api( `/notes/${ id }`, undefined, 'DELETE' ), li ) );
			}
			if ( edit ) {
				edit.addEventListener( 'click', () => {
					const text = li.querySelector( '.note-text' ).textContent;
					li.innerHTML = `<form class="note-form is-edit">
						<textarea rows="3" maxlength="${ res.maxLength }" aria-label="${ esc( __( 'Edit this note', 'eo-bots' ) ) }"></textarea>
						<div class="note-form-foot"><button type="button" class="btn" data-cancel>${ esc( __( 'Cancel', 'eo-bots' ) ) }</button><button type="submit" class="btn primary">${ esc( __( 'Save', 'eo-bots' ) ) }</button></div>
					</form>`;
					const area = li.querySelector( 'textarea' );
					area.value = text;
					area.focus();
					li.querySelector( '[data-cancel]' ).addEventListener( 'click', () => renderMemory( res ) );
					li.querySelector( 'form' ).addEventListener( 'submit', ( e ) => {
						e.preventDefault();
						save( () => api( `/notes/${ id }`, { text: area.value.trim() } ), li );
					} );
				} );
			}
		} );
	}

	/* ---------- Conversations list ---------- */
	async function loadConversations() {
		const list = $( '#pane-conversations' );
		list.innerHTML = `<li class="empty">${ esc( __( 'Loading…', 'eo-bots' ) ) }</li>`;
		let items = [];
		try {
			items = await api( '/conversations' );
		} catch ( e ) {}

		if ( ! items.length ) {
			list.innerHTML = `<li class="empty">${ esc( __( 'No conversation yet.', 'eo-bots' ) ) }</li>`;
			return;
		}

		const format = new Intl.DateTimeFormat( document.documentElement.lang || undefined, { dateStyle: 'medium', timeStyle: 'short' } );
		list.innerHTML = items
			.map(
				( c ) => `<li${ c.id === conversationId ? ' class="is-current"' : '' }>
					<button type="button" class="open" data-id="${ c.id }"><span>${ esc( c.title ) }</span><time>${ esc( format.format( new Date( c.updatedAt ) ) ) }</time></button>
					<button type="button" class="icon" data-delete="${ c.id }" aria-label="${ esc( __( 'Delete this conversation', 'eo-bots' ) ) }">${ icon.trash }</button>
				</li>`
			)
			.join( '' );

		list.querySelectorAll( '[data-id]' ).forEach( ( b ) => b.addEventListener( 'click', () => loadConversation( Number( b.dataset.id ) ) ) );
		list.querySelectorAll( '[data-delete]' ).forEach( ( b ) =>
			b.addEventListener( 'click', async () => {
				const id = Number( b.dataset.delete );
				b.disabled = true;
				try {
					await api( `/conversations/${ id }`, undefined, 'DELETE' );
					b.closest( 'li' ).remove();
					if ( id === conversationId ) {
						setConversation( 0 );
						welcome();
					}
				} catch ( e ) {
					b.disabled = false;
				}
			} )
		);
	}

	/* ---------- Agent loop ---------- */
	function handle( res ) {
		if ( res.conversation ) {
			setConversation( res.conversation.id );
		}
		( res.events || [] ).forEach( ( event ) => {
			if ( event.type === 'tool' ) {
				setWorking( event.label, 'work' );
			} else if ( event.type === 'confirm-state' ) {
				const el = log.querySelector( `.card.confirm[data-call-id="${ CSS.escape( event.callId ) }"]` );
				if ( el ) {
					setConfirmState( el, event.state );
				}
			} else {
				render( event );
			}
		} );
	}

	async function run( request ) {
		setBusy( true );
		setSuggestions( [] );
		setWorking( __( 'Thinking…', 'eo-bots' ), 'think' );
		let status = 'error';

		try {
			let res = await request();
			for ( ;; ) {
				handle( res );
				// Lets the next page pick the work up if the user leaves anyway.
				session.set( 'running', conversationId ? String( conversationId ) : null );
				status = res.status;
				if ( status === 'busy' ) {
					// The server is still finishing a step started on the previous page.
					setWorking( __( 'Finishing what I started…', 'eo-bots' ), 'work' );
					await new Promise( ( resolve ) => setTimeout( resolve, 3000 ) );
				} else if ( status !== 'continue' ) {
					break;
				}
				res = await api( '/chat/continue', { conversation_id: conversationId, context: context() } );
			}
		} catch ( error ) {
			addError( ( error && error.message ) || __( 'Something went wrong.', 'eo-bots' ), !! conversationId );
		}

		session.set( 'running', null );
		setWorking( null );
		setBusy( false );

		if ( status === 'confirm' ) {
			bot.setBase( 'listen' );
			if ( panel.hidden ) {
				say( __( 'I need your OK to go on!', 'eo-bots' ) );
			}
		} else if ( status === 'done' ) {
			bot.setBase( 'idle' );
			bot.setState( 'happy', { duration: 1600 } );
			if ( panel.hidden ) {
				say( __( 'Done! Click me to see.', 'eo-bots' ) );
			}
		} else {
			bot.setBase( 'idle' );
			bot.setState( 'oops', { duration: 2200 } );
		}
	}

	function send( text ) {
		text = String( text ).trim();
		if ( ! text || busy || ! data.aiReady ) {
			return;
		}
		showHistory( false );
		addUser( text );
		input.value = '';
		autosize();
		run( () => api( '/chat', { conversation_id: conversationId, message: text, context: context() } ) );
	}

	/* ---------- Panel ---------- */
	function open( value ) {
		panel.hidden = ! value;
		botButton.setAttribute( 'aria-expanded', String( value ) );
		session.set( 'open', value ? '1' : null );
		say( null );

		if ( value ) {
			if ( ! loaded ) {
				loaded = true;
				if ( conversationId ) {
					loadConversation( conversationId );
				} else {
					welcome();
				}
			}
			bot.setState( 'happy', { duration: 700 } );
			if ( ! input.disabled ) {
				input.focus();
			}
		}
	}

	function autosize() {
		input.style.height = 'auto';
		// scrollHeight leaves out the borders; show the scrollbar only past the max height.
		const height = input.scrollHeight + input.offsetHeight - input.clientHeight;
		input.style.height = Math.min( height, 140 ) + 'px';
		input.style.overflowY = height > 140 ? 'auto' : 'hidden';
	}

	botButton.addEventListener( 'click', () => open( panel.hidden ) );

	$( '.head' ).addEventListener( 'click', ( e ) => {
		const button = e.target.closest( '[data-action]' );
		if ( ! button ) {
			return;
		}
		const action = button.dataset.action;
		if ( action === 'close' ) {
			open( false );
			botButton.focus();
		} else if ( action === 'new' && ! busy ) {
			setConversation( 0 );
			showHistory( false );
			welcome();
			input.focus();
		} else if ( action === 'history' ) {
			showHistory( history.hidden );
		}
	} );

	form.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		send( input.value );
	} );

	input.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Enter' && ! e.shiftKey && ! e.isComposing ) {
			e.preventDefault();
			send( input.value );
		}
	} );

	input.addEventListener( 'input', () => {
		autosize();
		if ( ! busy && [ 'idle', 'listen' ].includes( bot.base ) ) {
			bot.setBase( input.value.trim() ? 'listen' : 'idle' );
		}
	} );

	shadow.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Escape' && ! panel.hidden ) {
			open( false );
			botButton.focus();
		}
	} );

	/* ---------- Bubbles: hello on the first page of each session, then a nudge now and then. ---------- */
	const NUDGE_EVERY = 15 * 60 * 1000;
	const nudges = data.aiReady
		? [
				__( 'Hey, click me to manage your site!', 'eo-bots' ),
				__( 'Need a new page? I can build it for you.', 'eo-bots' ),
				__( 'A text to proofread? Click me!', 'eo-bots' ),
				__( 'I am getting bored… Shall we get to work?', 'eo-bots' ),
		  ]
		: [ __( 'I need an AI to think. Click me!', 'eo-bots' ) ];

	function nudge( text ) {
		if ( ! panel.hidden || busy || document.hidden || bot.state === 'sleep' ) {
			return;
		}
		bot.setState( 'happy', { duration: 1500 } );
		say( text || nudges[ Math.floor( Math.random() * nudges.length ) ], 6000 );
		local.set( 'nudged', String( Date.now() ) );
	}

	/* ---------- Boot ---------- */
	setSubtitle();
	setBusy( false );

	// Leaving the page while the bot works shows the browser's "Leave site?" warning.
	window.addEventListener( 'beforeunload', ( e ) => {
		if ( busy ) {
			e.preventDefault();
			e.returnValue = '';
		}
	} );

	const interrupted = Number( session.get( 'running' ) );
	if ( interrupted ) {
		// The user left while the bot was working: reopen the conversation and let it finish.
		setConversation( interrupted );
		loaded = true;
		open( true );
		loadConversation( interrupted ).then( () => {
			if ( conversationId === interrupted ) {
				run( () => api( '/chat/continue', { conversation_id: conversationId, context: context() } ) );
			} else {
				session.set( 'running', null );
			}
		} );
	} else if ( session.get( 'open' ) ) {
		open( true );
	} else if ( ! session.get( 'hello' ) ) {
		session.set( 'hello', '1' );
		setTimeout( () => nudge( nudges[ 0 ] ), 1500 );
	}

	setInterval( () => {
		if ( Date.now() - ( Number( local.get( 'nudged' ) ) || 0 ) > NUDGE_EVERY ) {
			nudge();
		}
	}, 60 * 1000 );
} )();
