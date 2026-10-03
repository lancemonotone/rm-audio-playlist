/** Control buttons and mode UI */
(function (ns) {
  "use strict";
  var PlayerBlock = ns.PlayerBlock;
  var STORAGE_VOL = ns.STORAGE_VOL;
  var SVG = ns.SVG;
  var rptSvg = ns.rptSvg;
  var _svg = ns._svg;
  var _create = ns._create;

  PlayerBlock.prototype._skipBtn = function (cap, aria, fn) {
    var b = document.createElement("button");
    b.type = "button";
    b.className = "rm-audio-playlist__skip";
    b.setAttribute("aria-label", aria);
    b.appendChild(_create("span", "rm-audio-playlist__skip-cap", cap));
    b.addEventListener("click", fn);
    return b;
  };

  PlayerBlock.prototype._iconBtn = function (aria, cl, svgStr, fn) {
    var b = document.createElement("button");
    b.type = "button";
    b.className = "rm-audio-playlist__ibtn " + (cl || "");
    b.setAttribute("aria-label", aria);
    b.appendChild(_svg(svgStr));
    b.addEventListener("click", fn);
    return b;
  };

  PlayerBlock.prototype._setPlayStateUi = function (playing) {
    if (!this._playBtn) {
      return;
    }
    this._playBtn.setAttribute("aria-label", playing ? "Pause" : "Play");
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
    this._rptBtn.setAttribute("aria-label", lab[this.repeat]);
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
      /* private mode / blocked storage */
    }
    return Math.min(1, Math.max(0, v));
  };

})(window.RmAudioPlaylist = window.RmAudioPlaylist || {});
