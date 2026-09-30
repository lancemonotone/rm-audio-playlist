/** Audio events, keyboard, volume, progress */
(function (ns) {
  "use strict";
  var PlayerBlock = ns.PlayerBlock;
  var STORAGE_VOL = ns.STORAGE_VOL;
  var SVG = ns.SVG;
  var _svg = ns._svg;
  var _fmtTime = ns._fmtTime;

  PlayerBlock.prototype._wireAudio = function () {
    var self = this;
    function clearWatchdog() {
      if (self._loadWatchdogTimer) {
        clearTimeout(self._loadWatchdogTimer);
        self._loadWatchdogTimer = 0;
      }
    }
    this.audio.addEventListener("timeupdate", function () {
      self._tick();
      /*
       * Near-end watchdog: some browsers/devices occasionally miss "ended".
       * If we're within ε seconds of the end, advance once.
       */
      if (self.repeat === "one") {
        return;
      }
      var d = self.audio.duration;
      if (!isFinite(d) || d <= 0 || self.audio.seeking) {
        return;
      }
      var eps = 0.25;
      if (self.audio.currentTime >= d - eps) {
        if (self.oi < self.order.length - 1) {
          self._advanceOnce(self.oi + 1);
        } else if (self.repeat === "all") {
          self._advanceOnce(0);
        }
      }
    });
    this.audio.addEventListener("loadedmetadata", function () {
      self._tDur.textContent = _fmtTime(self.audio.duration);
      var d = isFinite(self.audio.duration) ? self.audio.duration : 0;
      self._progressBar.setAttribute("aria-valuemax", String(d));
      self._progressBar.setAttribute(
        "aria-valuetext",
        _fmtTime(self.audio.currentTime) + " of " + _fmtTime(d),
      );
      self._resizeWaveformCanvas();
      self._drawWaveformIdle();
    });
    this.audio.addEventListener("play", function () {
      self._setPlayStateUi(true);
    });
    this.audio.addEventListener("pause", function () {
      self._setPlayStateUi(false);
      self._stopWaveformLoop();
    });
    this.audio.addEventListener("canplay", function () {
      clearWatchdog();
    });
    this.audio.addEventListener("playing", function () {
      clearWatchdog();
      if (
        self._status &&
        (self._status.textContent === "Loading…" ||
          self._status.textContent === "Buffering…")
      ) {
        self._setStatus("");
      }
      self._ensureWaveformGraph();
      if (self._audioCtx && self._audioCtx.state === "suspended") {
        self._audioCtx.resume().catch(function () {});
      }
      self._resizeWaveformCanvas();
      self._startWaveformLoop();
    });
    this.audio.addEventListener("ended", function () {
      if (self.repeat === "one") {
        self.audio.currentTime = 0;
        var p = self.audio.play();
        if (p && p.catch) p.catch(function () {});
        return;
      }
      if (self.oi < self.order.length - 1) {
        self._advanceOnce(self.oi + 1);
        return;
      }
      if (self.repeat === "all") {
        self._advanceOnce(0);
      } else {
        self._setStatus("Finished.", 5000);
      }
    });
    this.audio.addEventListener("error", function () {
      /*
       * Error strategy:
       * - Retry the same track once per load token (transient network/decode hiccups).
       * - If it still fails, skip (guarded so we don't double-advance).
       */
      clearWatchdog();
      if (self._loadRetryToken !== self._advToken) {
        self._loadRetryToken = self._advToken;
        self._setStatus("Trouble loading this track. Retrying…");
        try {
          self.audio.load();
          var p = self.audio.play();
          if (p && p.catch) p.catch(function () {});
        } catch (e) {
          /* ignore */
        }
        return;
      }
      self._setStatus("This track could not be loaded. Skipping…");
      self._errTimer = setTimeout(function () {
        self._errTimer = 0;
        if (self.repeat === "one") {
          self._setStatus("This track could not be loaded.", 5000);
          return;
        }
        if (self.oi < self.order.length - 1) {
          self._advanceOnce(self.oi + 1);
        } else if (self.repeat === "all") {
          self._advanceOnce(0);
        } else {
          self._setStatus("No more tracks to play.", 5000);
        }
      }, 2500);
    });
    this.audio.addEventListener("waiting", function () {
      self._setStatus("Buffering…");
    });
    this.audio.addEventListener("canplay", function () {
      /* Ready to play (may still be paused). Clears “Loading…” from _load(..., false) on first paint. */
      if (
        self._status &&
        (self._status.textContent === "Buffering…" ||
          self._status.textContent === "Loading…")
      ) {
        self._setStatus("");
      }
    });
    document.addEventListener("keydown", this._bind);
    this.root.addEventListener("click", function (e) {
      var t = e.target;
      /* Do not steal focus from selects/inputs/slider — native <select> closes if blurred. */
      if (t && typeof t.closest === "function") {
        if (
          t.closest("select") ||
          t.closest("input") ||
          t.closest("textarea") ||
          t.closest("button") ||
          t.closest("a[href]") ||
          t.closest("label") ||
          t.closest('[role="slider"]') ||
          t.closest("summary")
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
    if (
      !this.root.contains(document.activeElement) &&
      document.activeElement !== this.root
    ) {
      return;
    }
    var t = e.target;
    if (
      t &&
      (t.tagName === "INPUT" ||
        t.tagName === "TEXTAREA" ||
        t.tagName === "SELECT" ||
        t.isContentEditable)
    ) {
      return;
    }
    if (e.code === "Space") {
      e.preventDefault();
      this._toggle();
    } else if (e.code === "ArrowLeft") {
      e.preventDefault();
      if (e.shiftKey) {
        this._seekRel(-30);
      } else {
        this._seekRel(-10);
      }
    } else if (e.code === "ArrowRight") {
      e.preventDefault();
      if (e.shiftKey) {
        this._seekRel(30);
      } else {
        this._seekRel(10);
      }
    } else if (e.code === "ArrowUp") {
      e.preventDefault();
      this._volStep(0.05);
    } else if (e.code === "ArrowDown") {
      e.preventDefault();
      this._volStep(-0.05);
    } else if (e.key === "n" || e.key === "N") {
      this._next();
    } else if (e.key === "p" || e.key === "P") {
      this._prev();
    } else if (e.key === "m" || e.key === "M") {
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
      var x =
        (ev.touches && ev.touches[0] ? ev.touches[0].clientX : ev.clientX) -
        rect.left;
      var p = Math.min(1, Math.max(0, x / rect.width));
      self.audio.currentTime = p * self.audio.duration;
    }

    bar.addEventListener("click", sc);
    bar.addEventListener("pointerdown", function (e) {
      dragging = true;
      bar.setPointerCapture(e.pointerId);
      sc(e);
    });
    bar.addEventListener("pointermove", function (e) {
      if (dragging) {
        sc(e);
      }
    });
    bar.addEventListener("pointerup", function () {
      dragging = false;
    });
    bar.addEventListener("pointercancel", function () {
      dragging = false;
    });
  };

})(window.RmAudioPlaylist = window.RmAudioPlaylist || {});
