/** Load, seek, shuffle, repeat, lightbox, status */
(function (ns) {
  "use strict";
  var PlayerBlock = ns.PlayerBlock;
  var SVG = ns.SVG;
  var _svg = ns._svg;
  var _shuffle = ns._shuffle;
  var _fmtTime = ns._fmtTime;

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
    this._progressFill.style.width = p * 100 + "%";
    this._progressThumb.style.left = p * 100 + "%";
    this._progressBar.setAttribute("aria-valuenow", String(a.currentTime));
    this._progressBar.setAttribute(
      "aria-valuetext",
      _fmtTime(a.currentTime) + " of " + _fmtTime(a.duration),
    );
    this._highlight();
  };

  PlayerBlock.prototype._toggleShuffle = function () {
    this.shuffle = !this.shuffle;
    if (this.shuffle) {
      var curT = this.order[this.oi];
      this.order = _shuffle(this.tracks.length);
      /*
       * If shuffle is enabled before the user has actually started playback, we should let the
       * first track be randomized too. Once playback has started (or the user has progressed),
       * keep the current track pinned as the first item so shuffle doesn't interrupt them.
       */
      if (this._userStarted) {
        var at = this.order.indexOf(curT);
        if (at > 0) {
          var tmp = this.order[0];
          this.order[0] = this.order[at];
          this.order[at] = tmp;
        }
        this.oi = 0;
		} else {
			this.oi = 0;
			this._load(this.oi, false);
		}
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
    if (this.repeat === "none") {
      this.repeat = "all";
    } else if (this.repeat === "all") {
      this.repeat = "one";
    } else {
      this.repeat = "none";
    }
    this._setRepeatUi();
  };

  PlayerBlock.prototype._currentTrackIndex = function () {
    return this.order[this.oi];
  };

  PlayerBlock.prototype._load = function (orderIndex, autoPlay) {
    var self = this;
    this._clearAdvanceTimers();
    this._advToken += 1;
    this._advLockedToken = -1;
    this._loadRetryToken = -1;
    this._timeoutRetryToken = -1;
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
    this._setStatus("Loading…");
    this._setPlayStateUi(false);
    this.audio.load();
    /*
     * Load timeout watchdog: if we never reach canplay/playing, retry once, then skip.
     * (Prevents “Loading…” hanging indefinitely.)
     */
    var token = this._advToken;
    function watchdogTick() {
      if (self._advToken !== token) {
        return;
      }
      self._loadWatchdogTimer = 0;
      if (self._timeoutRetryToken !== token) {
        self._timeoutRetryToken = token;
        self._setStatus("Still loading… retrying.");
        try {
          self.audio.load();
          if (autoPlay) {
            var p = self.audio.play();
            if (p && p.catch) p.catch(function () {});
          }
        } catch (e) {
          /* ignore */
        }
        self._loadWatchdogTimer = setTimeout(watchdogTick, 15000);
        return;
      }
      self._setStatus("Taking too long to load. Skipping…");
      if (self.repeat === "one") {
        return;
      }
      if (self.oi < self.order.length - 1) {
        self._advanceOnce(self.oi + 1);
      } else if (self.repeat === "all") {
        self._advanceOnce(0);
      }
    }
    this._loadWatchdogTimer = setTimeout(watchdogTick, 15000);
    if (autoPlay) {
      this._userStarted = true;
      var pl = this.audio.play();
      if (pl && pl.catch) {
        pl.catch(function () {
          self._setStatus("Press Play to start (browser blocked autoplay).");
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
    [].forEach.call(
      this._list.querySelectorAll(".rm-audio-playlist__item"),
      function (li) {
        var qp = li.getAttribute("data-queue-pos");
        li.classList.toggle("is-current", qp === String(oi));
      },
    );
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
    } else if (this.repeat === "all") {
      this._load(0, true);
    } else {
      this._setStatus("End of list.", 5000);
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
      this._userStarted = true;
      this._setStatus("");
      var p = this.audio.play();
      if (p && p.catch) {
        p.catch(function () {});
      }
    } else {
      this.audio.pause();
    }
  };

  PlayerBlock.prototype._lightboxFocusables = function () {
    if (!this._lightbox) {
      return [];
    }
    var sel =
      'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    return Array.prototype.slice
      .call(this._lightbox.querySelectorAll(sel))
      .filter(function (el) {
        return el.offsetParent !== null || el === document.activeElement;
      });
  };

  PlayerBlock.prototype._openArtLightbox = function () {
    if (!this._lightbox) {
      return;
    }
    this._lightbox.removeAttribute("hidden");
    document.body.style.overflow = "hidden";
    document.addEventListener("keydown", this._boundLightboxEsc, true);
    var c = this._lightbox.querySelector(".rm-audio-playlist__lightbox-close");
    if (c) {
      c.focus();
    }
  };

  PlayerBlock.prototype._closeArtLightbox = function () {
    if (!this._lightbox || this._lightbox.hasAttribute("hidden")) {
      return;
    }
    this._lightbox.setAttribute("hidden", "");
    document.body.style.overflow = "";
    document.removeEventListener("keydown", this._boundLightboxEsc, true);
    if (this._artBtn) {
      try {
        this._artBtn.focus();
      } catch (err) {
        /* ignore */
      }
    }
  };

  /**
   * @param {string} s Status text; empty hides the line (see CSS :empty).
   * @param {number} [dismissAfterMs] If set, clear this message after N ms if unchanged (ephemeral feedback).
   */
  PlayerBlock.prototype._setStatus = function (s, dismissAfterMs) {
    if (this._statusDismissTimer) {
      clearTimeout(this._statusDismissTimer);
      this._statusDismissTimer = 0;
    }
    if (this._status) {
      this._status.textContent = s;
    }
    if (dismissAfterMs && dismissAfterMs > 0 && s) {
      var self = this;
      var captured = s;
      this._statusDismissTimer = setTimeout(function () {
        self._statusDismissTimer = 0;
        if (self._status && self._status.textContent === captured) {
          self._setStatus("");
        }
      }, dismissAfterMs);
    }
  };

})(window.RmAudioPlaylist = window.RmAudioPlaylist || {});
