/** DOM build for player chrome */
(function (ns) {
  "use strict";
  var STORAGE_VOL = ns.STORAGE_VOL;
  var SPEEDS = ns.SPEEDS;
  var DISABLE_TOOLTIPS = ns.DISABLE_TOOLTIPS;
  var SVG = ns.SVG;
  var rptSvg = ns.rptSvg;
  var _svg = ns._svg;
  var _create = ns._create;
  var PlayerBlock = ns.PlayerBlock;

  PlayerBlock.prototype._build = function () {
    var self = this;
    var idBase = this.root.id || "rmpl-" + String(Math.random()).slice(2);
    this._idBase = idBase;

    this.root.classList.add("rm-audio-playlist--ready");
    this.root.setAttribute("tabindex", "0");
    this.root.setAttribute("role", "region");
    this.root.setAttribute("aria-label", this.playlistTitle);

    this.root.innerHTML = "";
    var wrap = _create("div", "rm-audio-playlist__inner", "");
    this.root.appendChild(wrap);

    var status = _create("p", "rm-audio-playlist__status", "", {
      "aria-live": "polite",
    });
    wrap.appendChild(status);
    this._status = status;

    var top = _create("div", "rm-audio-playlist__top", "");
    self._artBtn = null;
    self._lightbox = null;
    self._boundLightboxEsc = null;
    var art;
    if (self.artworkUrl) {
      art = document.createElement("button");
      art.type = "button";
      art.className =
        "rm-audio-playlist__art rm-audio-playlist__art--has-image";
      art.setAttribute("aria-label", "View full-size playlist artwork");
      var artImg = document.createElement("img");
      artImg.className = "rm-audio-playlist__art-img";
      artImg.src = self.artworkThumbUrl;
      artImg.alt = self.artworkAlt || self.playlistTitle || "";
      artImg.loading = "lazy";
      artImg.decoding = "async";
      art.appendChild(artImg);
      self._artBtn = art;

      var lb = document.createElement("div");
      lb.className = "rm-audio-playlist__lightbox";
      lb.setAttribute("hidden", "");
      lb.setAttribute("role", "dialog");
      lb.setAttribute("aria-modal", "true");
      lb.setAttribute(
        "aria-label",
        self.artworkAlt || self.playlistTitle
          ? "Artwork: " + (self.artworkAlt || self.playlistTitle)
          : "Playlist artwork",
      );
      var bd = document.createElement("div");
      bd.className = "rm-audio-playlist__lightbox-backdrop";
      lb.appendChild(bd);
      var closeBtn = document.createElement("button");
      closeBtn.type = "button";
      closeBtn.className = "rm-audio-playlist__lightbox-close";
      closeBtn.setAttribute("aria-label", "Close");
      closeBtn.appendChild(_svg(SVG.close));
      lb.appendChild(closeBtn);
      var lbContent = document.createElement("div");
      lbContent.className = "rm-audio-playlist__lightbox-content";
      var lbImg = document.createElement("img");
      lbImg.className = "rm-audio-playlist__lightbox-img";
      lbImg.src = self.artworkUrl;
      lbImg.alt = self.artworkAlt || self.playlistTitle || "";
      lbContent.appendChild(lbImg);
      if (self.playlistTitle) {
        lbContent.appendChild(
          _create("p", "rm-audio-playlist__lightbox-cap", self.playlistTitle),
        );
      }
      lb.appendChild(lbContent);
      self.root.appendChild(lb);
      self._lightbox = lb;

      self._boundLightboxEsc = function (e) {
        if (!self._lightbox || self._lightbox.hasAttribute("hidden")) {
          return;
        }
        if (e.key === "Escape") {
          self._closeArtLightbox();
          e.preventDefault();
          return;
        }
        if (e.key !== "Tab") {
          return;
        }
        var nodes = self._lightboxFocusables();
        if (!nodes.length) {
          e.preventDefault();
          return;
        }
        var first = nodes[0];
        var last = nodes[nodes.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      };
      bd.addEventListener("click", function () {
        self._closeArtLightbox();
      });
      closeBtn.addEventListener("click", function () {
        self._closeArtLightbox();
      });
      art.addEventListener("click", function () {
        self._openArtLightbox();
      });
    } else {
      art = _create("div", "rm-audio-playlist__art", "");
      art.setAttribute("aria-hidden", "true");
    }
    var headlines = _create("div", "rm-audio-playlist__headlines", "");
    this._elPlaylistName = _create(
      "h2",
      "rm-audio-playlist__pl-name",
      this.playlistTitle,
    );
    this._nowTitle = _create("p", "rm-audio-playlist__track-line", "—", {
      "aria-label": "Current track",
    });
    this._elTrackIndex = _create("p", "rm-audio-playlist__index-line", "");
    headlines.appendChild(this._elPlaylistName);
    headlines.appendChild(this._nowTitle);
    headlines.appendChild(this._elTrackIndex);
    top.appendChild(art);
    top.appendChild(headlines);
    wrap.appendChild(top);

    var bar = _create("div", "rm-audio-playlist__progress", null, {
      role: "slider",
    });
    bar.setAttribute("aria-label", "Seek in current track");
    bar.setAttribute("aria-orientation", "horizontal");
    bar.setAttribute("aria-valuemin", "0");
    bar.setAttribute("aria-valuemax", "0");
    bar.setAttribute("aria-valuenow", "0");
    bar.setAttribute("aria-valuetext", "0:00 of 0:00");
    bar.setAttribute("tabindex", "0");
    bar.classList.add("rm-audio-playlist--has-tip");
    bar.setAttribute(
      "data-rm-tip",
      "Click or drag to jump to a position in the current track.",
    );
    var timeBar = _create("div", "rm-audio-playlist__timebar", "");
    this._tCur = _create("span", "rm-audio-playlist__time-cur", "0:00");
    var track = _create("div", "rm-audio-playlist__progress-track", "");
    track.appendChild(_create("div", "rm-audio-playlist__progress-fill", ""));
    track.appendChild(_create("div", "rm-audio-playlist__progress-thumb", ""));
    bar.appendChild(track);
    this._tDur = _create("span", "rm-audio-playlist__time-dur", "0:00");
    timeBar.appendChild(this._tCur);
    timeBar.appendChild(bar);
    timeBar.appendChild(this._tDur);
    this._progressBar = bar;
    this._progressFill = track.querySelector(
      ".rm-audio-playlist__progress-fill",
    );
    this._progressThumb = track.querySelector(
      ".rm-audio-playlist__progress-thumb",
    );
    wrap.appendChild(timeBar);

    var controlsStack = _create("div", "rm-audio-playlist__controls-stack", "");
    self._controlsStack = controlsStack;
    var waveCanvas = document.createElement("canvas");
    waveCanvas.className = "rm-audio-playlist__waveform";
    waveCanvas.setAttribute("aria-hidden", "true");
    controlsStack.appendChild(waveCanvas);
    self._waveCanvas = waveCanvas;

    var controlsFg = _create(
      "div",
      "rm-audio-playlist__controls-foreground",
      "",
    );

    var transport = _create("div", "rm-audio-playlist__transport", "");
    transport.appendChild(
      self._iconBtn(
        "Previous track",
        "rm-audio-pl-prev",
        SVG.prev,
        function () {
          self._prev();
        },
        "Previous track — or restart this one if you’re a few seconds in.",
      ),
    );
    this._playBtn = self._iconBtn(
      "Play",
      "rm-audio-pl-play rm-audio-playlist__play",
      SVG.play,
      function () {
        self._toggle();
      },
      "Start playing this list.",
    );
    transport.appendChild(this._playBtn);
    transport.appendChild(
      self._iconBtn(
        "Next track",
        "rm-audio-pl-next",
        SVG.next,
        function () {
          self._next();
        },
        "Next track in the list (or follow repeat rules).",
      ),
    );
    controlsFg.appendChild(transport);

    var skipCorners = _create("div", "rm-audio-playlist__skip-corner-wrap", "");
    var skipNeg = _create(
      "div",
      "rm-audio-playlist__skips rm-audio-playlist__skips--neg",
      "",
    );
    skipNeg.appendChild(
      self._skipBtn("-30", "Jump back 30 seconds in this track", function () {
        self._seekRel(-30);
      }),
    );
    skipNeg.appendChild(
      self._skipBtn("-10", "Jump back 10 seconds in this track", function () {
        self._seekRel(-10);
      }),
    );
    var skipPos = _create(
      "div",
      "rm-audio-playlist__skips rm-audio-playlist__skips--pos",
      "",
    );
    skipPos.appendChild(
      self._skipBtn(
        "+10",
        "Jump forward 10 seconds in this track",
        function () {
          self._seekRel(10);
        },
      ),
    );
    skipPos.appendChild(
      self._skipBtn(
        "+30",
        "Jump forward 30 seconds in this track",
        function () {
          self._seekRel(30);
        },
      ),
    );
    skipCorners.appendChild(skipNeg);
    skipCorners.appendChild(skipPos);
    controlsFg.appendChild(skipCorners);

    controlsStack.appendChild(controlsFg);
    wrap.appendChild(controlsStack);

    if (typeof ResizeObserver !== "undefined") {
      self._waveResizeObs = new ResizeObserver(function () {
        self._resizeWaveformCanvas();
      });
      self._waveResizeObs.observe(controlsStack);
    }
    window.addEventListener(
      "resize",
      self._resizeWaveformCanvasBound ||
        (self._resizeWaveformCanvasBound =
          self._resizeWaveformCanvas.bind(self)),
    );
    requestAnimationFrame(function () {
      self._resizeWaveformCanvas();
      self._drawWaveformIdle();
    });

    var toolbar = _create("div", "rm-audio-playlist__toolbar", "");
    this._shufBtn = self._iconBtn(
      "Shuffle off",
      "rm-audio-pl-shuf",
      SVG.shufOff,
      function () {
        self._toggleShuffle();
      },
      "Shuffle playback order. When you turn it on, the list reshuffles and keeps the current track first.",
    );
    this._rptBtn = self._iconBtn(
      "Repeat off",
      "rm-audio-pl-rpt",
      rptSvg("none"),
      function () {
        self._cycleRepeat();
      },
      "Click to cycle: no repeat → repeat the whole list → repeat one track → off.",
    );
    toolbar.appendChild(this._shufBtn);
    toolbar.appendChild(this._rptBtn);

    var speed = document.createElement("div");
    speed.className = "rm-audio-playlist__field";
    var spLab = _create("label", "rm-audio-playlist__field-label", "Speed", {});
    var sid = idBase + "-speed";
    spLab.setAttribute("for", sid);
    speed.appendChild(spLab);
    var sel = document.createElement("select");
    sel.id = sid;
    sel.className = "rm-audio-playlist__select";
    sel.setAttribute("aria-label", "Playback speed");
    var speedTip = _create(
      "span",
      "rm-audio-playlist--has-tip rm-audio-playlist__wrap",
      "",
    );
    speedTip.setAttribute(
      "data-rm-tip",
      "Playback speed — useful for talks, practice, or skimming. Normal is 1×.",
    );
    SPEEDS.forEach(function (s) {
      var o = document.createElement("option");
      o.value = String(s);
      o.textContent = s === 1 ? "1×" : s + "×";
      if (s === 1) o.selected = true;
      sel.appendChild(o);
    });
    sel.addEventListener("change", function () {
      self.audio.playbackRate = parseFloat(sel.value, 10) || 1;
    });
    this._speed = sel;
    speedTip.appendChild(sel);
    speed.appendChild(speedTip);
    toolbar.appendChild(speed);

    var vol = document.createElement("div");
    vol.className = "rm-audio-playlist__field rm-audio-playlist__field--grow";
    vol.appendChild(
      _create("span", "rm-audio-playlist__field-label", "Volume", {}),
    );
    var volInner = _create("div", "rm-audio-playlist__volinner", "");
    this._muteBtn = self._iconBtn(
      "Mute",
      "rm-audio-pl-mute",
      SVG.vol,
      function () {
        self.audio.muted = !self.audio.muted;
        self._syncMuteUi();
      },
      "Mute or unmute. Volume is saved in this browser. Keyboard: M when the player is focused.",
    );
    this._muteBtn.setAttribute("aria-pressed", "false");
    this._syncMuteUi = function () {
      var old = self._muteBtn.querySelector("svg");
      if (old) {
        old.remove();
      }
      self._muteBtn.insertBefore(
        _svg(self.audio.muted ? SVG.mute : SVG.vol),
        self._muteBtn.firstChild,
      );
      self._muteBtn.setAttribute(
        "aria-pressed",
        self.audio.muted ? "true" : "false",
      );
      self._muteBtn.setAttribute(
        "aria-label",
        self.audio.muted ? "Unmute" : "Mute",
      );
      self._muteBtn.setAttribute(
        "data-rm-tip",
        self.audio.muted
          ? "Unmute (restore the level from the slider)."
          : "Mute. You can also drag the slider all the way left.",
      );
    };
    var rng = document.createElement("input");
    rng.type = "range";
    rng.id = idBase + "-vol";
    rng.className = "rm-audio-playlist__range";
    rng.min = "0";
    rng.max = "1";
    rng.step = "0.01";
    rng.value = String(self._getStoredVolume());
    rng.setAttribute("aria-label", "Volume");
    var volTip = _create(
      "span",
      "rm-audio-playlist--has-tip rm-audio-playlist__wrap rm-audio-playlist__wrap--grow",
      "",
    );
    volTip.setAttribute(
      "data-rm-tip",
      "Volume. Level is saved for next time in this browser (same site).",
    );
    rng.addEventListener("input", function () {
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

    var det = document.createElement("details");
    det.className = "rm-audio-playlist__kbd";
    var sum = _create(
      "summary",
      "rm-audio-playlist__kbd-summary rm-audio-playlist--has-tip",
      "Keyboard shortcuts",
      {},
    );
    sum.setAttribute(
      "data-rm-tip",
      "Expand to read keys: Space, arrows, N/P, M. The player must be focused (click it first).",
    );
    det.appendChild(sum);
    det.appendChild(
      _create(
        "p",
        "rm-audio-playlist__kbd-body",
        "Focus the player, then: Space = play/pause. Left/Right = seek 10s (hold Shift = 30s). Up/Down = volume. N / P = next or previous track. M = mute.",
      ),
    );
    wrap.appendChild(det);

    var list = _create("ul", "rm-audio-playlist__list", "", {
      "aria-label": "Playlist queue",
    });
    this._list = list;
    var qWrap = _create("div", "rm-audio-playlist__queue", "");
    var qh = _create("p", "rm-audio-playlist__queue-h", "Up next");
    qWrap.appendChild(qh);
    qWrap.appendChild(list);
    wrap.appendChild(qWrap);

    this._rebuildQueueList();

    this._setShuffleUi();
    this._setRepeatUi();
    this._updateIndexLine();
    this._setPlayStateUi(false);
    this._syncMuteUi();

    this._ensureFloatTip();
    if (!DISABLE_TOOLTIPS) {
      this._wireFloatTipsDelegated();
    }

    this._wireAudio();
    this._wireProgress(bar);
    this._load(0, false);
  };

})(window.RmAudioPlaylist = window.RmAudioPlaylist || {});
