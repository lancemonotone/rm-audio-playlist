/** PlayerBlock constructor and advance guards */
(function (ns) {
  "use strict";
  var _create = ns._create;

  /**
   * @param {HTMLElement} el
   * @param {object} data
   * @param {string} data.title
   * @param {Array<{url:string, title:string, downloadable?: boolean, downloadName?: string}>} data.tracks
   * @param {string} [data.artworkUrl] Full-size image (lightbox).
   * @param {string} [data.artworkThumbUrl] Smaller image for the cover chip (optional).
   * @param {string} [data.artworkAlt]
   */
  function PlayerBlock(el, data) {
    this.root = el;
    this.tracks = data.tracks;
    this.playlistTitle = data.title;
    this.artworkUrl =
      typeof data.artworkUrl === "string" ? data.artworkUrl : "";
    this.artworkThumbUrl =
      typeof data.artworkThumbUrl === "string" && data.artworkThumbUrl !== ""
        ? data.artworkThumbUrl
        : this.artworkUrl;
    this.artworkAlt =
      typeof data.artworkAlt === "string" ? data.artworkAlt : "";
    if (!this.tracks || !this.tracks.length) {
      return;
    }
    this.order = this.tracks.map(function (_, i) {
      return i;
    });
    this.oi = 0;
    this.repeat = "none";
    this.shuffle = false;
    /* True once the user has actually started playback (used for shuffle UX). */
    this._userStarted = false;
    this.audio = new Audio();
    this.audio.preload = "auto";
    /* Web Audio: required for cross-origin MP3 analysis (same-origin uploads are fine). */
    this.audio.crossOrigin = "anonymous";
    this._bind = this._onKeydown.bind(this);
    this._errTimer = 0;
    this._loadWatchdogTimer = 0;
    this._statusDismissTimer = 0;
    this._floatTip = null;
    this._floatTipTarget = null;
    this._tipHideTimer = 0;
    this._dragFromQi = -1;
    this._audioCtx = null;
    this._analyser = null;
    this._waveGraphReady = false;
    this._waveGraphFailed = false;
    this._waveRaf = 0;
    this._waveTd = null;
    this._waveCanvas = null;
    this._controlsStack = null;
    this._waveCssW = 0;
    this._waveCssH = 0;
    this._waveResizeObs = null;
    this._floatTipFromKeyboard = false;
    this._focusTipTimer = 0;
    /*
     * Track-advance guard: multiple events (ended, near-end, error, timeout) can try to advance.
     * We increment _advToken on each _load() and allow at most one advance per token.
     */
    this._advToken = 0;
    this._advLockedToken = -1;
    this._loadRetryToken = -1;
    this._timeoutRetryToken = -1;
    this._build();
  }

  PlayerBlock.prototype._clearAdvanceTimers = function () {
    if (this._errTimer) {
      clearTimeout(this._errTimer);
      this._errTimer = 0;
    }
    if (this._loadWatchdogTimer) {
      clearTimeout(this._loadWatchdogTimer);
      this._loadWatchdogTimer = 0;
    }
  };

  PlayerBlock.prototype._advanceOnce = function (nextOrderIndex) {
    if (this._advLockedToken === this._advToken) {
      return;
    }
    this._advLockedToken = this._advToken;
    this._clearAdvanceTimers();
    this._load(nextOrderIndex, true);
  };

  ns.PlayerBlock = PlayerBlock;

})(window.RmAudioPlaylist = window.RmAudioPlaylist || {});
