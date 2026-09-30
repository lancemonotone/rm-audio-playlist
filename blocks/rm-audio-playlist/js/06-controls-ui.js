/** Control buttons and mode UI */
(function (ns) {
  "use strict";
  var PlayerBlock = ns.PlayerBlock;
  var SVG = ns.SVG;
  var rptSvg = ns.rptSvg;
  var _svg = ns._svg;
  var _create = ns._create;

  /**
   * @param {string} cap  Short label on the button (e.g. -10, +30).
   * @param {string} tip  Full tooltip (data-rm-tip); also used for aria-label.
   * @param {function} fn
   */
  PlayerBlock.prototype._skipBtn = function (cap, tip, fn) {
    var b = document.createElement("button");
    b.type = "button";
    b.className = "rm-audio-playlist__skip rm-audio-playlist--has-tip";
    b.setAttribute("aria-label", tip);
    b.setAttribute("data-rm-tip", tip);
    b.appendChild(_create("span", "rm-audio-playlist__skip-cap", cap));
    b.addEventListener("click", function () {
      fn();
    });
    return b;
  };

  /**
   * @param {string} [tip]  Human-readable data-rm-tip; falls back to aria.
   */
  PlayerBlock.prototype._iconBtn = function (aria, cl, svgStr, fn, tip) {
    var b = document.createElement("button");
    b.type = "button";
    b.className =
      "rm-audio-playlist__ibtn rm-audio-playlist--has-tip " + (cl || "");
    b.setAttribute("aria-label", aria);
    b.setAttribute("data-rm-tip", tip || aria);
    b.appendChild(_svg(svgStr));
    b.addEventListener("click", function (e) {
      fn(e);
    });
    return b;
  };

  PlayerBlock.prototype._setPlayStateUi = function (playing) {
    if (!this._playBtn) {
      return;
    }
    this._playBtn.setAttribute("aria-label", playing ? "Pause" : "Play");
    this._playBtn.setAttribute(
      "data-rm-tip",
      playing
        ? "Pause playback. Space bar also works when the player is focused."
        : "Play from the current position. If nothing happens, the browser may have blocked sound until you click once.",
    );
    var cur = this._playBtn.querySelector("svg");
    if (cur) {
      cur.remove();
    }
    this._playBtn.insertBefore(
      _svg(playing ? SVG.pause : SVG.play),
      this._playBtn.firstChild,
    );
  };

  PlayerBlock.prototype._setShuffleUi = function () {
    if (!this._shufBtn) {
      return;
    }
    this._shufBtn.classList.toggle("is-active", this.shuffle);
    this._shufBtn.setAttribute(
      "aria-label",
      this.shuffle ? "Shuffle on" : "Shuffle off",
    );
    this._shufBtn.setAttribute(
      "data-rm-tip",
      this.shuffle
        ? "Shuffle is on — order is random. Click to play in the original list order again."
        : "Shuffle playback order. The current track is kept first when you turn this on, then the rest is mixed.",
    );
    var cur = this._shufBtn.querySelector("svg");
    if (cur) {
      cur.remove();
    }
    this._shufBtn.insertBefore(
      _svg(this.shuffle ? SVG.shufOn : SVG.shufOff),
      this._shufBtn.firstChild,
    );
  };

  PlayerBlock.prototype._setRepeatUi = function () {
    if (!this._rptBtn) {
      return;
    }
    var lab = { none: "Repeat off", all: "Repeat playlist", one: "Repeat one" };
    var tips = {
      none: "No repeat: stops after the last track. Click to enable repeat entire playlist.",
      all: "Repeats the whole list when the last track ends. Click again to repeat only the current track.",
      one: "Repeats the current track until you click Next or change mode. Click again to turn repeat off.",
    };
    this._rptBtn.setAttribute("aria-label", lab[this.repeat]);
    this._rptBtn.setAttribute("data-rm-tip", tips[this.repeat]);
    this._rptBtn.classList.remove("is-active", "is-active-one");
    if (this.repeat === "all") {
      this._rptBtn.classList.add("is-active");
    } else if (this.repeat === "one") {
      this._rptBtn.classList.add("is-active-one");
    }
    var cur = this._rptBtn.querySelector("svg");
    if (cur) {
      cur.remove();
    }
    this._rptBtn.insertBefore(
      _svg(rptSvg(this.repeat)),
      this._rptBtn.firstChild,
    );
  };

  PlayerBlock.prototype._updateIndexLine = function () {
    if (!this._elTrackIndex) {
      return;
    }
    var n = this.order ? this.order.length : 0;
    var i = (this.oi >= 0 ? this.oi : 0) + 1;
    this._elTrackIndex.textContent = n ? "Track " + i + " of " + n : "";
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

})(window.RmAudioPlaylist = window.RmAudioPlaylist || {});
