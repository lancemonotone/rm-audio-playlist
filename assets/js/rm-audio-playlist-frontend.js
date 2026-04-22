/**
 * RM Audio Playlist — vanilla player (one <audio> per block).
 */
(function () {
	'use strict';

	var STORAGE_VOL = 'rm-audio-pl-vol-1';
	var SPEEDS = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];

	/**
	 * Self-contained 24×24 icons, currentColor (fill or stroke).
	 * Shuffle + repeat glyphs: paths from Lucide (lucide-static v0.468.0, ISC).
	 * shufOff / rptOff arrowheads match shufOn’s two-stroke chevrons (same d, shifted for centering).
	 */
	var SVG = {
		play: '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M7 4.5l14 7.5L7 20V4.5z"/></svg>',
		pause: '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M5.5 3.5h3.5v17H5.5v-17zM15 3.5h3.5v17H15v-17z"/></svg>',
		prev: '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M21.5 3.5H18v17h3.5v-17zm-4 0L4 12l13.5 8.5V3.5z"/></svg>',
		next: '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M2.5 3.5H6v17H2.5v-17zm4 0L20 12 6.5 20.5V3.5z"/></svg>',
		shufOn:
			'<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m18 14 4 4-4 4"/><path d="m18 2 4 4-4 4"/><path d="M2 18h1.973a4 4 0 0 0 3.3-1.7l5.454-8.6a4 4 0 0 1 3.3-1.7H22"/><path d="M2 6h1.972a4 4 0 0 1 3.6 2.2"/><path d="M22 18h-6.041a4 4 0 0 1-3.3-1.8l-.359-.45"/></svg>',
		shufOff:
			'<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 6H18"/><path d="m18 2 4 4-4 4"/><path d="M2 18H18"/><path d="m18 14 4 4-4 4"/></svg>',
		rptOff:
			'<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12H18"/><path d="m18 8 4 4-4 4"/></svg>',
		rptAll:
			'<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/></svg>',
		rptOne:
			'<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/><path d="M11 10h1v4"/></svg>',
		vol: '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M11 5.5L6.5 9.5H3.5A1.5 1.5 0 0 0 2 11v2a1.5 1.5 0 0 0 1.5 1.5H6l4.5 3.5V5.5zM15.5 9.5a3.5 3.5 0 0 1 0 4.5M18 6a6.5 6.5 0 0 1 0 12"/></svg>',
		mute: '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M11 5.5L6.5 9.5H3.5A1.5 1.5 0 0 0 2 11v2a1.5 1.5 0 0 0 1.5 1.5H6l4.5 3.5V5.5z"/><line x1="22" y1="2" x2="2" y2="22"/></svg>',
		dl: '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
		grip:
			'<svg class="rm-audio-playlist__icon rm-audio-playlist__icon--grip" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><circle cx="9" cy="6" r="1.35"/><circle cx="15" cy="6" r="1.35"/><circle cx="9" cy="12" r="1.35"/><circle cx="15" cy="12" r="1.35"/><circle cx="9" cy="18" r="1.35"/><circle cx="15" cy="18" r="1.35"/></svg>',
	};

	function rptSvg(mode) {
		if (mode === 'all') {
			return SVG.rptAll;
		}
		if (mode === 'one') {
			return SVG.rptOne;
		}
		return SVG.rptOff;
	}

	/**
	 * @param {string} svgStr Markup: single root <svg>
	 * @return {Element}
	 */
	function _svg(svgStr) {
		var w = document.createElement('div');
		w.innerHTML = svgStr.trim();
		return /** @type {Element} */ (w.firstChild);
	}

	/**
	 * @param {string} tag
	 * @param {string} cls
	 * @param {string|DocumentFragment|ChildNode} inner
	 * @param {object} [attr]
	 */
	function _create(tag, cls, inner, attr) {
		var el = document.createElement(tag);
		if (cls) {
			el.className = cls;
		}
		if (typeof inner === 'string') {
			el.appendChild(document.createTextNode(inner));
		} else if (inner) {
			el.appendChild(inner);
		}
		if (attr) {
			for (var k in attr) {
				if (Object.prototype.hasOwnProperty.call(attr, k)) {
					el.setAttribute(k, attr[k]);
				}
			}
		}
		return el;
	}

	/**
	 * @param {HTMLElement} el
	 * @param {object} data
	 * @param {string} data.title
	 * @param {Array<{url:string, title:string, downloadable?: boolean, downloadName?: string}>} data.tracks
	 * @param {string} [data.artworkUrl]
	 * @param {string} [data.artworkAlt]
	 */
	function PlayerBlock(el, data) {
		this.root = el;
		this.tracks = data.tracks;
		this.playlistTitle = data.title;
		this.artworkUrl = typeof data.artworkUrl === 'string' ? data.artworkUrl : '';
		this.artworkAlt = typeof data.artworkAlt === 'string' ? data.artworkAlt : '';
		if (!this.tracks || !this.tracks.length) {
			return;
		}
		this.order = this.tracks.map(function (_, i) {
			return i;
		});
		this.oi = 0;
		this.repeat = 'none';
		this.shuffle = false;
		this.audio = new Audio();
		this.audio.preload = 'metadata';
		this._bind = this._onKeydown.bind(this);
		this._errTimer = 0;
		this._floatTip = null;
		this._floatTipTarget = null;
		this._tipHideTimer = 0;
		this._dragFromQi = -1;
		this._build();
	}

	PlayerBlock.prototype._build = function () {
		var self = this;
		var idBase = this.root.id || 'rmpl-' + String(Math.random()).slice(2);
		this._idBase = idBase;

		this.root.classList.add('rm-audio-playlist--ready');
		this.root.setAttribute('tabindex', '0');
		this.root.setAttribute('role', 'region');
		this.root.setAttribute('aria-label', this.playlistTitle);

		this.root.innerHTML = '';
		var wrap = _create('div', 'rm-audio-playlist__inner', '');
		this.root.appendChild(wrap);

		var status = _create('p', 'rm-audio-playlist__status', '', { 'aria-live': 'polite' });
		wrap.appendChild(status);
		this._status = status;

		var top = _create('div', 'rm-audio-playlist__top', '');
		var art = _create('div', 'rm-audio-playlist__art', '');
		if (self.artworkUrl) {
			art.classList.add('rm-audio-playlist__art--has-image');
			var artImg = document.createElement('img');
			artImg.className = 'rm-audio-playlist__art-img';
			artImg.src = self.artworkUrl;
			artImg.alt = self.artworkAlt || self.playlistTitle || '';
			artImg.loading = 'lazy';
			artImg.decoding = 'async';
			art.appendChild(artImg);
		} else {
			art.setAttribute('aria-hidden', 'true');
		}
		var headlines = _create('div', 'rm-audio-playlist__headlines', '');
		this._elPlaylistName = _create('h2', 'rm-audio-playlist__pl-name', this.playlistTitle);
		this._nowTitle = _create('p', 'rm-audio-playlist__track-line', '—', { 'aria-label': 'Current track' });
		this._elTrackIndex = _create('p', 'rm-audio-playlist__index-line', '');
		headlines.appendChild(this._elPlaylistName);
		headlines.appendChild(this._nowTitle);
		headlines.appendChild(this._elTrackIndex);
		top.appendChild(art);
		top.appendChild(headlines);
		wrap.appendChild(top);

		var bar = _create('div', 'rm-audio-playlist__progress', null, { role: 'slider' });
		bar.setAttribute('aria-label', 'Seek in current track');
		bar.setAttribute('aria-valuemin', '0');
		bar.setAttribute('aria-valuemax', '0');
		bar.setAttribute('tabindex', '0');
		bar.classList.add('rm-audio-playlist--has-tip', 'rm-audio-playlist__tip--below');
		bar.setAttribute(
			'data-rm-tip',
			'Click or drag to jump to a position in the current track.'
		);
		var timeBar = _create('div', 'rm-audio-playlist__timebar', '');
		this._tCur = _create('span', 'rm-audio-playlist__time-cur', '0:00');
		var track = _create('div', 'rm-audio-playlist__progress-track', '');
		track.appendChild(_create('div', 'rm-audio-playlist__progress-fill', ''));
		track.appendChild(_create('div', 'rm-audio-playlist__progress-thumb', ''));
		bar.appendChild(track);
		this._tDur = _create('span', 'rm-audio-playlist__time-dur', '0:00');
		timeBar.appendChild(this._tCur);
		timeBar.appendChild(bar);
		timeBar.appendChild(this._tDur);
		this._progressBar = bar;
		this._progressFill = track.querySelector('.rm-audio-playlist__progress-fill');
		this._progressThumb = track.querySelector('.rm-audio-playlist__progress-thumb');
		wrap.appendChild(timeBar);

		var transport = _create('div', 'rm-audio-playlist__transport', '');
		transport.appendChild(
			self._iconBtn(
				'Previous track',
				'rm-audio-pl-prev',
				SVG.prev,
				function () { self._prev(); },
				'Previous track — or restart this one if you’re a few seconds in.'
			)
		);
		this._playBtn = self._iconBtn(
			'Play',
			'rm-audio-pl-play rm-audio-playlist__play',
			SVG.play,
			function () { self._toggle(); },
			'Start playing this list.'
		);
		transport.appendChild(this._playBtn);
		transport.appendChild(
			self._iconBtn(
				'Next track',
				'rm-audio-pl-next',
				SVG.next,
				function () { self._next(); },
				'Next track in the list (or follow repeat rules).'
			)
		);
		wrap.appendChild(transport);

		var skipRow = _create('div', 'rm-audio-playlist__skips', '');
		skipRow.appendChild(
			self._skipBtn('-30', 'Jump back 30 seconds in this track', function () { self._seekRel(-30); })
		);
		skipRow.appendChild(
			self._skipBtn('-10', 'Jump back 10 seconds in this track', function () { self._seekRel(-10); })
		);
		skipRow.appendChild(
			self._skipBtn('+10', 'Jump forward 10 seconds in this track', function () { self._seekRel(10); })
		);
		skipRow.appendChild(
			self._skipBtn('+30', 'Jump forward 30 seconds in this track', function () { self._seekRel(30); })
		);
		wrap.appendChild(skipRow);

		var toolbar = _create('div', 'rm-audio-playlist__toolbar', '');
		this._shufBtn = self._iconBtn(
			'Shuffle off',
			'rm-audio-pl-shuf',
			SVG.shufOff,
			function () { self._toggleShuffle(); },
			'Shuffle playback order. When you turn it on, the list reshuffles and keeps the current track first.'
		);
		this._rptBtn = self._iconBtn(
			'Repeat off',
			'rm-audio-pl-rpt',
			rptSvg('none'),
			function () { self._cycleRepeat(); },
			'Click to cycle: no repeat → repeat the whole list → repeat one track → off.'
		);
		toolbar.appendChild(this._shufBtn);
		toolbar.appendChild(this._rptBtn);

		var speed = document.createElement('div');
		speed.className = 'rm-audio-playlist__field';
		var spLab = _create('label', 'rm-audio-playlist__field-label', 'Speed', {});
		var sid = idBase + '-speed';
		spLab.setAttribute('for', sid);
		speed.appendChild(spLab);
		var sel = document.createElement('select');
		sel.id = sid;
		sel.className = 'rm-audio-playlist__select';
		sel.setAttribute('aria-label', 'Playback speed');
		var speedTip = _create('span', 'rm-audio-playlist--has-tip rm-audio-playlist__tip--above rm-audio-playlist__wrap', '');
		speedTip.setAttribute(
			'data-rm-tip',
			'Playback speed — useful for talks, practice, or skimming. Normal is 1×.'
		);
		SPEEDS.forEach(function (s) {
			var o = document.createElement('option');
			o.value = String(s);
			o.textContent = s === 1 ? '1×' : s + '×';
			if (s === 1) o.selected = true;
			sel.appendChild(o);
		});
		sel.addEventListener('change', function () {
			self.audio.playbackRate = parseFloat(sel.value, 10) || 1;
		});
		this._speed = sel;
		speedTip.appendChild(sel);
		speed.appendChild(speedTip);
		toolbar.appendChild(speed);

		var vol = document.createElement('div');
		vol.className = 'rm-audio-playlist__field rm-audio-playlist__field--grow';
		vol.appendChild(_create('span', 'rm-audio-playlist__field-label', 'Volume', {}));
		var volInner = _create('div', 'rm-audio-playlist__volinner', '');
		this._muteBtn = self._iconBtn(
			'Mute',
			'rm-audio-pl-mute',
			SVG.vol,
			function () {
				self.audio.muted = !self.audio.muted;
				self._syncMuteUi();
			},
			'Mute or unmute. Volume is saved in this browser. Keyboard: M when the player is focused.'
		);
		this._muteBtn.setAttribute('aria-pressed', 'false');
		this._syncMuteUi = function () {
			var old = self._muteBtn.querySelector('svg');
			if (old) {
				old.remove();
			}
			self._muteBtn.insertBefore(_svg(self.audio.muted ? SVG.mute : SVG.vol), self._muteBtn.firstChild);
			self._muteBtn.setAttribute('aria-pressed', self.audio.muted ? 'true' : 'false');
			self._muteBtn.setAttribute('aria-label', self.audio.muted ? 'Unmute' : 'Mute');
			self._muteBtn.setAttribute(
				'data-rm-tip',
				self.audio.muted
					? 'Unmute (restore the level from the slider).'
					: 'Mute. You can also drag the slider all the way left.'
			);
		};
		var rng = document.createElement('input');
		rng.type = 'range';
		rng.id = idBase + '-vol';
		rng.className = 'rm-audio-playlist__range';
		rng.min = '0';
		rng.max = '1';
		rng.step = '0.01';
		rng.value = String(self._getStoredVolume());
		rng.setAttribute('aria-label', 'Volume');
		var volTip = _create('span', 'rm-audio-playlist--has-tip rm-audio-playlist__tip--above rm-audio-playlist__wrap rm-audio-playlist__wrap--grow', '');
		volTip.setAttribute(
			'data-rm-tip',
			'Volume. Level is saved for next time in this browser (same site).'
		);
		rng.addEventListener('input', function () {
			var v = parseFloat(rng.value, 10);
			if (isNaN(v)) return;
			self.audio.volume = v;
			self.audio.muted = v < 0.001;
			self._syncMuteUi();
			try {
				localStorage.setItem(STORAGE_VOL, String(v));
			} catch (e) {
				/* ignore */
			}
		});
		this._vol = rng;
		volTip.appendChild(rng);
		volInner.appendChild(this._muteBtn);
		volInner.appendChild(volTip);
		vol.appendChild(volInner);
		toolbar.appendChild(vol);
		wrap.appendChild(toolbar);

		var det = document.createElement('details');
		det.className = 'rm-audio-playlist__kbd';
		var sum = _create('summary', 'rm-audio-playlist__kbd-summary rm-audio-playlist--has-tip', 'Keyboard shortcuts', {});
		sum.setAttribute(
			'data-rm-tip',
			'Expand to read keys: Space, arrows, N/P, M. The player must be focused (click it first).'
		);
		det.appendChild(sum);
		det.appendChild(
			_create(
				'p',
				'rm-audio-playlist__kbd-body',
				'Focus the player, then: Space = play/pause. Left/Right = seek 10s (hold Shift = 30s). Up/Down = volume. N / P = next or previous track. M = mute.'
			)
		);
		wrap.appendChild(det);

		var list = _create('ul', 'rm-audio-playlist__list', '', { 'aria-label': 'Playlist queue' });
		this._list = list;
		var qWrap = _create('div', 'rm-audio-playlist__queue', '');
		var qh = _create('p', 'rm-audio-playlist__queue-h', 'Up next');
		qWrap.appendChild(qh);
		qWrap.appendChild(list);
		wrap.appendChild(qWrap);

		this._rebuildQueueList();

		this._setShuffleUi();
		this._setRepeatUi();
		this._updateIndexLine();
		this._setPlayStateUi(false);
		this._syncMuteUi();

		this._wireAudio();
		this._wireProgress(bar);
		this._load(0, false);
	};

	PlayerBlock.prototype._ensureFloatTip = function () {
		if (this._floatTip) {
			return this._floatTip;
		}
		var tip = document.createElement('div');
		tip.className = 'rm-audio-playlist__floattip';
		tip.setAttribute('aria-hidden', 'true');
		this.root.appendChild(tip);
		this._floatTip = tip;
		return tip;
	};

	PlayerBlock.prototype._positionFloatTip = function (e) {
		if (!this._floatTip) {
			return;
		}
		var pad = 12;
		var x = e.clientX + pad;
		var y = e.clientY + pad;
		var rect = this._floatTip.getBoundingClientRect();
		var vw = window.innerWidth;
		var vh = window.innerHeight;
		if (x + rect.width > vw - 8) {
			x = Math.max(8, vw - rect.width - 8);
		}
		if (y + rect.height > vh - 8) {
			y = Math.max(8, e.clientY - rect.height - pad);
		}
		this._floatTip.style.left = x + 'px';
		this._floatTip.style.top = y + 'px';
	};

	PlayerBlock.prototype._hideFloatTip = function () {
		var self = this;
		var tip = this._floatTip;
		if (!tip || !tip.classList.contains('is-visible')) {
			return;
		}
		tip.classList.add('is-out');
		clearTimeout(this._tipHideTimer);
		this._tipHideTimer = setTimeout(function () {
			tip.classList.remove('is-visible', 'is-out');
			tip.textContent = '';
			tip.style.left = '';
			tip.style.top = '';
			if (self._floatTipTarget === null) {
				/* noop */
			}
		}, 220);
	};

	PlayerBlock.prototype._bindCursorTip = function (el) {
		var self = this;
		if (!el) {
			return;
		}
		this._ensureFloatTip();
		el.addEventListener('pointerenter', function (e) {
			var text = el.getAttribute('data-rm-tip');
			if (!text) {
				return;
			}
			clearTimeout(self._tipHideTimer);
			self._floatTipTarget = el;
			self._floatTip.textContent = text;
			self._floatTip.classList.remove('is-out');
			self._floatTip.classList.add('is-visible');
			self._floatTip.style.transform = '';
			requestAnimationFrame(function () {
				self._positionFloatTip(e);
			});
		});
		el.addEventListener('pointermove', function (e) {
			if (self._floatTipTarget !== el) {
				return;
			}
			self._positionFloatTip(e);
		});
		el.addEventListener('pointerleave', function () {
			if (self._floatTipTarget !== el) {
				return;
			}
			self._floatTipTarget = null;
			self._hideFloatTip();
		});
	};

	PlayerBlock.prototype._reorderQueue = function (fromQi, toQi) {
		if (fromQi === toQi || fromQi < 0 || toQi < 0) {
			return;
		}
		if (fromQi >= this.order.length || toQi >= this.order.length) {
			return;
		}
		var playing = this.order[this.oi];
		var tid = this.order[fromQi];
		var next = this.order.filter(function (_, i) {
			return i !== fromQi;
		});
		next.splice(toQi, 0, tid);
		this.order = next;
		this.oi = this.order.indexOf(playing);
		if (this.oi < 0) {
			this.oi = 0;
		}
		if (this.shuffle) {
			this.shuffle = false;
			this._setShuffleUi();
		}
		this._rebuildQueueList();
		this._highlight();
	};

	PlayerBlock.prototype._rebuildQueueList = function () {
		var self = this;
		if (!this._list) {
			return;
		}
		while (this._list.firstChild) {
			this._list.removeChild(this._list.firstChild);
		}
		this.order.forEach(function (trackIdx, qi) {
			var t = self.tracks[trackIdx];
			if (!t) {
				return;
			}
			var li = _create('li', 'rm-audio-playlist__item', '');
			li.setAttribute('data-idx', String(trackIdx));
			li.setAttribute('data-queue-pos', String(qi));
			li.setAttribute('role', 'listitem');

			var canDrag = !self.shuffle;
			var handle = document.createElement('span');
			handle.className = 'rm-audio-playlist__item-handle';
			handle.setAttribute('draggable', canDrag ? 'true' : 'false');
			handle.setAttribute('aria-label', canDrag ? 'Drag to reorder' : 'Reordering is off while shuffle is on');
			handle.setAttribute('data-rm-tip', canDrag ? 'Drag to move this track up or down' : 'Turn shuffle off to reorder tracks');
			handle.appendChild(_svg(SVG.grip));
			handle.addEventListener('dragstart', function (e) {
				if (!canDrag) {
					e.preventDefault();
					return;
				}
				self._dragFromQi = qi;
				li.classList.add('is-dragging');
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData('text/plain', String(qi));
				try {
					e.dataTransfer.setData('application/x-rm-pl', String(qi));
				} catch (err) {
					/* ignore */
				}
			});
			handle.addEventListener('dragend', function () {
				li.classList.remove('is-dragging');
				self._dragFromQi = -1;
				[].forEach.call(self._list.querySelectorAll('.rm-audio-playlist__item'), function (row) {
					row.classList.remove('is-drag-over');
				});
			});
			self._bindCursorTip(handle);
			li.appendChild(handle);

			li.appendChild(_create('span', 'rm-audio-playlist__item-num', String(qi + 1), { 'aria-hidden': 'true' }));

			var b = document.createElement('a');
			b.className =
				'rm-audio-playlist__item-title rm-audio-playlist--has-tip rm-audio-playlist__tip--cursor';
			b.setAttribute('data-rm-tip', 'Play now');
			b.href = t.url;
			b.appendChild(document.createTextNode(t.title));
			b.addEventListener('click', function (ev) {
				ev.preventDefault();
				self._jumpToListIndex(trackIdx);
			});
			self._bindCursorTip(b);
			li.appendChild(b);

			if (t.downloadable) {
				var dlA = document.createElement('a');
				dlA.className = 'rm-audio-playlist__item-dl rm-audio-playlist--has-tip rm-audio-playlist__tip--cursor';
				dlA.href = t.url;
				if (t.downloadName) {
					dlA.setAttribute('download', t.downloadName);
				}
				dlA.setAttribute('aria-label', 'Download — ' + t.title);
				dlA.setAttribute('data-rm-tip', 'Download this track (MP3).');
				dlA.appendChild(_svg(SVG.dl));
				self._bindCursorTip(dlA);
				li.appendChild(dlA);
			}

			li.addEventListener('dragover', function (e) {
				if (self._dragFromQi < 0) {
					return;
				}
				e.preventDefault();
				e.dataTransfer.dropEffect = 'move';
				[].forEach.call(self._list.querySelectorAll('.rm-audio-playlist__item'), function (row) {
					row.classList.remove('is-drag-over');
				});
				li.classList.add('is-drag-over');
			});
			li.addEventListener('dragleave', function (e) {
				if (e.currentTarget.contains(e.relatedTarget)) {
					return;
				}
				li.classList.remove('is-drag-over');
			});
			li.addEventListener('drop', function (e) {
				e.preventDefault();
				li.classList.remove('is-drag-over');
				var fromStr = e.dataTransfer.getData('text/plain');
				var fromQi = parseInt(fromStr, 10);
				if (isNaN(fromQi)) {
					fromStr = e.dataTransfer.getData('application/x-rm-pl');
					fromQi = parseInt(fromStr, 10);
				}
				if (isNaN(fromQi)) {
					return;
				}
				var toQi = parseInt(li.getAttribute('data-queue-pos') || '-1', 10);
				self._reorderQueue(fromQi, toQi);
			});

			self._list.appendChild(li);
		});
	};

	/**
	 * @param {string} cap  Short label on the button (e.g. -10, +30).
	 * @param {string} tip  Full tooltip (data-rm-tip); also used for aria-label.
	 * @param {function} fn
	 */
	PlayerBlock.prototype._skipBtn = function (cap, tip, fn) {
		var b = document.createElement('button');
		b.type = 'button';
		b.className = 'rm-audio-playlist__skip rm-audio-playlist--has-tip';
		b.setAttribute('aria-label', tip);
		b.setAttribute('data-rm-tip', tip);
		b.appendChild(_create('span', 'rm-audio-playlist__skip-cap', cap));
		b.addEventListener('click', function () { fn(); });
		return b;
	};

	/**
	 * @param {string} [tip]  Human-readable data-rm-tip; falls back to aria.
	 */
	PlayerBlock.prototype._iconBtn = function (aria, cl, svgStr, fn, tip) {
		var b = document.createElement('button');
		b.type = 'button';
		b.className = 'rm-audio-playlist__ibtn rm-audio-playlist--has-tip ' + (cl || '');
		b.setAttribute('aria-label', aria);
		b.setAttribute('data-rm-tip', tip || aria);
		b.appendChild(_svg(svgStr));
		b.addEventListener('click', function (e) { fn(e); });
		return b;
	};

	PlayerBlock.prototype._setPlayStateUi = function (playing) {
		if (!this._playBtn) {
			return;
		}
		this._playBtn.setAttribute('aria-label', playing ? 'Pause' : 'Play');
		this._playBtn.setAttribute(
			'data-rm-tip',
			playing
				? 'Pause playback. Space bar also works when the player is focused.'
				: 'Play from the current position. If nothing happens, the browser may have blocked sound until you click once.'
		);
		var cur = this._playBtn.querySelector('svg');
		if (cur) {
			cur.remove();
		}
		this._playBtn.insertBefore(_svg(playing ? SVG.pause : SVG.play), this._playBtn.firstChild);
	};

	PlayerBlock.prototype._setShuffleUi = function () {
		if (!this._shufBtn) {
			return;
		}
		this._shufBtn.classList.toggle('is-active', this.shuffle);
		this._shufBtn.setAttribute('aria-label', this.shuffle ? 'Shuffle on' : 'Shuffle off');
		this._shufBtn.setAttribute(
			'data-rm-tip',
			this.shuffle
				? 'Shuffle is on — order is random. Click to play in the original list order again.'
				: 'Shuffle playback order. The current track is kept first when you turn this on, then the rest is mixed.'
		);
		var cur = this._shufBtn.querySelector('svg');
		if (cur) {
			cur.remove();
		}
		this._shufBtn.insertBefore(_svg(this.shuffle ? SVG.shufOn : SVG.shufOff), this._shufBtn.firstChild);
	};

	PlayerBlock.prototype._setRepeatUi = function () {
		if (!this._rptBtn) {
			return;
		}
		var lab = { none: 'Repeat off', all: 'Repeat playlist', one: 'Repeat one' };
		var tips = {
			none: 'No repeat: stops after the last track. Click to enable repeat entire playlist.',
			all: 'Repeats the whole list when the last track ends. Click again to repeat only the current track.',
			one: 'Repeats the current track until you click Next or change mode. Click again to turn repeat off.',
		};
		this._rptBtn.setAttribute('aria-label', lab[this.repeat]);
		this._rptBtn.setAttribute('data-rm-tip', tips[this.repeat]);
		this._rptBtn.classList.remove('is-active', 'is-active-one');
		if (this.repeat === 'all') {
			this._rptBtn.classList.add('is-active');
		} else if (this.repeat === 'one') {
			this._rptBtn.classList.add('is-active-one');
		}
		var cur = this._rptBtn.querySelector('svg');
		if (cur) {
			cur.remove();
		}
		this._rptBtn.insertBefore(_svg(rptSvg(this.repeat)), this._rptBtn.firstChild);
	};

	PlayerBlock.prototype._updateIndexLine = function () {
		if (!this._elTrackIndex) {
			return;
		}
		var n = this.order ? this.order.length : 0;
		var i = (this.oi >= 0 ? this.oi : 0) + 1;
		this._elTrackIndex.textContent = n ? 'Track ' + i + ' of ' + n : '';
	};

	PlayerBlock.prototype._getStoredVolume = function () {
		var v = 0.9;
		try {
			var s = localStorage.getItem(STORAGE_VOL);
			if (s !== null) {
				var p = parseFloat(s, 10);
				if (!isNaN(p)) v = p;
			}
		} catch (e) {
			/* ignore */
		}
		return Math.min(1, Math.max(0, v));
	};

	PlayerBlock.prototype._wireAudio = function () {
		var self = this;
		this.audio.addEventListener('timeupdate', function () {
			self._tick();
		});
		this.audio.addEventListener('loadedmetadata', function () {
			self._tDur.textContent = _fmtTime(self.audio.duration);
			var d = isFinite(self.audio.duration) ? self.audio.duration : 0;
			self._progressBar.setAttribute('aria-valuemax', String(d));
		});
		this.audio.addEventListener('play', function () {
			self._setPlayStateUi(true);
		});
		this.audio.addEventListener('pause', function () {
			self._setPlayStateUi(false);
		});
		this.audio.addEventListener('playing', function () {
			if (self._status && (self._status.textContent === 'Loading…' || self._status.textContent === 'Buffering…')) {
				self._setStatus('');
			}
		});
		this.audio.addEventListener('ended', function () {
			if (self.repeat === 'one') {
				self.audio.currentTime = 0;
				var p = self.audio.play();
				if (p && p.catch) p.catch(function () {});
				return;
			}
			if (self.oi < self.order.length - 1) {
				self._load(self.oi + 1, true);
				return;
			}
			if (self.repeat === 'all') {
				self._load(0, true);
			} else {
				self._setStatus('Finished.');
			}
		});
		this.audio.addEventListener('error', function () {
			self._setStatus('This track could not be loaded. Skipping in 1s or use next.');
			var errT = setTimeout(function () {
				if (self.oi < self.order.length - 1) {
					self._load(self.oi + 1, true);
				} else {
					self._setStatus('No more tracks to play.');
				}
			}, 1000);
			self._errTimer = errT;
		});
		this.audio.addEventListener('waiting', function () {
			self._setStatus('Buffering…');
		});
		this.audio.addEventListener('canplay', function () {
			if (self._status && self._status.textContent === 'Buffering…') {
				self._setStatus('');
			}
		});
		document.addEventListener('keydown', this._bind);
		this.root.addEventListener('click', function (e) {
			var t = e.target;
			/* Do not steal focus from selects/inputs/slider — native <select> closes if blurred. */
			if (t && typeof t.closest === 'function') {
				if (
					t.closest('select') ||
					t.closest('input') ||
					t.closest('textarea') ||
					t.closest('button') ||
					t.closest('a[href]') ||
					t.closest('label') ||
					t.closest('[role="slider"]') ||
					t.closest('summary')
				) {
					return;
				}
			}
			try {
				self.root.focus();
			} catch (err) {
				/* ignore */
			}
		});
	};

	PlayerBlock.prototype._onKeydown = function (e) {
		if (!this.root.contains(document.activeElement) && document.activeElement !== this.root) {
			return;
		}
		var t = e.target;
		if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) {
			return;
		}
		if (e.code === 'Space') {
			e.preventDefault();
			this._toggle();
		} else if (e.code === 'ArrowLeft') {
			e.preventDefault();
			if (e.shiftKey) {
				this._seekRel(-30);
			} else {
				this._seekRel(-10);
			}
		} else if (e.code === 'ArrowRight') {
			e.preventDefault();
			if (e.shiftKey) {
				this._seekRel(30);
			} else {
				this._seekRel(10);
			}
		} else if (e.code === 'ArrowUp') {
			e.preventDefault();
			this._volStep(0.05);
		} else if (e.code === 'ArrowDown') {
			e.preventDefault();
			this._volStep(-0.05);
		} else if (e.key === 'n' || e.key === 'N') {
			this._next();
		} else if (e.key === 'p' || e.key === 'P') {
			this._prev();
		} else if (e.key === 'm' || e.key === 'M') {
			this.audio.muted = !this.audio.muted;
			this._syncMuteUi();
		}
	};

	PlayerBlock.prototype._volStep = function (delta) {
		var v = Math.min(1, Math.max(0, this.audio.volume + delta));
		this._vol.value = String(v);
		this.audio.volume = v;
		this.audio.muted = v < 0.001;
		if (this._syncMuteUi) {
			this._syncMuteUi();
		}
		try {
			localStorage.setItem(STORAGE_VOL, String(v));
		} catch (e) {
			/* ignore */
		}
	};

	PlayerBlock.prototype._wireProgress = function (bar) {
		var self = this;
		var dragging = false;

		function sc(ev) {
			if (!isFinite(self.audio.duration) || self.audio.duration <= 0) {
				return;
			}
			var rect = bar.getBoundingClientRect();
			var x = (ev.touches && ev.touches[0] ? ev.touches[0].clientX : ev.clientX) - rect.left;
			var p = Math.min(1, Math.max(0, x / rect.width));
			self.audio.currentTime = p * self.audio.duration;
		}

		bar.addEventListener('click', sc);
		bar.addEventListener('pointerdown', function (e) {
			dragging = true;
			bar.setPointerCapture(e.pointerId);
			sc(e);
		});
		bar.addEventListener('pointermove', function (e) {
			if (dragging) {
				sc(e);
			}
		});
		bar.addEventListener('pointerup', function () {
			dragging = false;
		});
		bar.addEventListener('pointercancel', function () {
			dragging = false;
		});
	};

	PlayerBlock.prototype._seekRel = function (sec) {
		if (!isFinite(this.audio.duration)) {
			return;
		}
		var t = this.audio.currentTime + sec;
		t = Math.max(0, Math.min(this.audio.duration, t));
		this.audio.currentTime = t;
	};

	PlayerBlock.prototype._tick = function () {
		var a = this.audio;
		if (!isFinite(a.duration) || a.duration <= 0) {
			return;
		}
		var p = a.currentTime / a.duration;
		this._tCur.textContent = _fmtTime(a.currentTime);
		this._progressFill.style.width = p * 100 + '%';
		this._progressThumb.style.left = p * 100 + '%';
		this._progressBar.setAttribute('aria-valuenow', String(a.currentTime));
		this._highlight();
	};

	PlayerBlock.prototype._toggleShuffle = function () {
		this.shuffle = !this.shuffle;
		if (this.shuffle) {
			var curT = this.order[this.oi];
			this.order = _shuffle(this.tracks.length);
			var at = this.order.indexOf(curT);
			if (at > 0) {
				var tmp = this.order[0];
				this.order[0] = this.order[at];
				this.order[at] = tmp;
			}
			this.oi = 0;
		} else {
			var playingTid = this.order[this.oi];
			this.order = this.tracks.map(function (_, i) {
				return i;
			});
			this.oi = this.order.indexOf(playingTid);
			if (this.oi < 0) {
				this.oi = 0;
			}
		}
		this._setShuffleUi();
		this._updateIndexLine();
		this._rebuildQueueList();
		this._highlight();
	};

	PlayerBlock.prototype._cycleRepeat = function () {
		if (this.repeat === 'none') {
			this.repeat = 'all';
		} else if (this.repeat === 'all') {
			this.repeat = 'one';
		} else {
			this.repeat = 'none';
		}
		this._setRepeatUi();
	};

	PlayerBlock.prototype._currentTrackIndex = function () {
		return this.order[this.oi];
	};

	PlayerBlock.prototype._load = function (orderIndex, autoPlay) {
		var self = this;
		if (this._errTimer) {
			clearTimeout(this._errTimer);
			this._errTimer = 0;
		}
		this.oi = orderIndex;
		if (this.oi < 0) {
			this.oi = 0;
		}
		if (this.oi >= this.order.length) {
			this.oi = 0;
		}
		var idx = this.order[this.oi];
		var t = this.tracks[idx];
		if (!t) {
			return;
		}
		this.audio.src = t.url;
		this._nowTitle.textContent = t.title;
		this._applyVol();
		this._updateIndexLine();
		this._highlight();
		this._setStatus('Loading…');
		this._setPlayStateUi(false);
		this.audio.load();
		if (autoPlay) {
			var pl = this.audio.play();
			if (pl && pl.catch) {
				pl.catch(function () {
					self._setStatus('Press Play to start (browser blocked autoplay).');
				});
			}
		}
	};

	PlayerBlock.prototype._applyVol = function () {
		var v = parseFloat(this._vol.value, 10);
		if (!isNaN(v)) {
			this.audio.volume = v;
		}
		if (this._speed) {
			this.audio.playbackRate = parseFloat(this._speed.value, 10) || 1;
		}
	};

	PlayerBlock.prototype._highlight = function () {
		if (!this._list) {
			return;
		}
		var oi = this.oi;
		[].forEach.call(this._list.querySelectorAll('.rm-audio-playlist__item'), function (li) {
			var qp = li.getAttribute('data-queue-pos');
			li.classList.toggle('is-current', qp === String(oi));
		});
	};

	PlayerBlock.prototype._jumpToListIndex = function (trackIndex) {
		this.oi = this.order.indexOf(trackIndex);
		if (this.oi < 0) {
			this.oi = 0;
		}
		this._load(this.oi, true);
	};

	PlayerBlock.prototype._next = function () {
		if (this.oi < this.order.length - 1) {
			this._load(this.oi + 1, true);
		} else if (this.repeat === 'all') {
			this._load(0, true);
		} else {
			this._setStatus('End of list.');
		}
	};

	PlayerBlock.prototype._prev = function () {
		if (this.audio.currentTime > 2) {
			this.audio.currentTime = 0;
			return;
		}
		if (this.oi > 0) {
			this._load(this.oi - 1, true);
		} else {
			this.audio.currentTime = 0;
		}
	};

	PlayerBlock.prototype._toggle = function () {
		if (this.audio.paused) {
			this._setStatus('');
			var p = this.audio.play();
			if (p && p.catch) {
				p.catch(function () {});
			}
		} else {
			this.audio.pause();
		}
	};

	PlayerBlock.prototype._setStatus = function (s) {
		if (this._status) {
			this._status.textContent = s;
		}
	};

	function _shuffle(n) {
		var a = [];
		for (var i = 0; i < n; i++) {
			a.push(i);
		}
		for (var j = a.length - 1; j > 0; j--) {
			var k = Math.floor(Math.random() * (j + 1));
			var t = a[j];
			a[j] = a[k];
			a[k] = t;
		}
		return a;
	}

	function _fmtTime(sec) {
		if (!isFinite(sec) || sec < 0) {
			return '0:00';
		}
		var m = Math.floor(sec / 60);
		var s = Math.floor(sec % 60);
		return m + ':' + (s < 10 ? '0' : '') + s;
	}

	function init() {
		var nodes = document.querySelectorAll('.rm-audio-playlist[data-rm-playlist]');
		[].forEach.call(nodes, function (node) {
			var raw = node.getAttribute('data-rm-playlist');
			if (!raw) {
				return;
			}
			var data;
			try {
				data = JSON.parse(raw);
			} catch (e) {
				return;
			}
			new PlayerBlock(node, data);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
