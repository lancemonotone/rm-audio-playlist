/** Waveform analyser and canvas */
(function (ns) {
  "use strict";
  var PlayerBlock = ns.PlayerBlock;

  PlayerBlock.prototype._resizeWaveformCanvas = function () {
    if (!this._waveCanvas || !this._controlsStack) {
      return;
    }
    var stack = this._controlsStack;
    var dpr = window.devicePixelRatio || 1;
    var cssW = Math.max(1, Math.floor(stack.clientWidth));
    var cssH = Math.max(1, Math.floor(stack.clientHeight));
    this._waveCssW = cssW;
    this._waveCssH = cssH;
    var w = Math.max(1, Math.floor(cssW * dpr));
    var h = Math.max(1, Math.floor(cssH * dpr));
    if (this._waveCanvas.width !== w || this._waveCanvas.height !== h) {
      this._waveCanvas.width = w;
      this._waveCanvas.height = h;
    }
  };

  PlayerBlock.prototype._ensureWaveformGraph = function () {
    if (this._waveGraphReady || this._waveGraphFailed) {
      return;
    }
    var AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) {
      this._waveGraphFailed = true;
      return;
    }
    try {
      var ctx = new AC();
      var src = ctx.createMediaElementSource(this.audio);
      var analyser = ctx.createAnalyser();
      analyser.fftSize = 2048;
      analyser.smoothingTimeConstant = 0.72;
      src.connect(analyser);
      analyser.connect(ctx.destination);
      this._audioCtx = ctx;
      this._analyser = analyser;
      this._waveTd = new Uint8Array(analyser.fftSize);
      this._waveGraphReady = true;
    } catch (err) {
      this._waveGraphFailed = true;
    }
  };

  PlayerBlock.prototype._drawWaveformIdle = function () {
    if (!this._waveCanvas || !this._waveCssW) {
      return;
    }
    var canvas = this._waveCanvas;
    var ctx = canvas.getContext("2d");
    if (!ctx) {
      return;
    }
    var dpr = window.devicePixelRatio || 1;
    var cssW = this._waveCssW;
    var cssH = this._waveCssH;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, cssW, cssH);
    ctx.beginPath();
    ctx.strokeStyle = this._waveAccentStroke();
    ctx.globalAlpha = 0.2;
    ctx.lineWidth = 1;
    var mid = cssH * 0.5;
    ctx.moveTo(0, mid);
    ctx.lineTo(cssW, mid);
    ctx.stroke();
    ctx.globalAlpha = 1;
  };

  PlayerBlock.prototype._waveAccentStroke = function () {
    var raw = getComputedStyle(this.root)
      .getPropertyValue("--rm-accent")
      .trim();
    return raw || "#3ecfae";
  };

  PlayerBlock.prototype._drawWaveformFrame = function () {
    if (
      !this._waveCanvas ||
      !this._analyser ||
      !this._waveTd ||
      !this._waveCssW
    ) {
      return;
    }
    this._analyser.getByteTimeDomainData(this._waveTd);
    var canvas = this._waveCanvas;
    var ctx = canvas.getContext("2d");
    if (!ctx) {
      return;
    }
    var dpr = window.devicePixelRatio || 1;
    var cssW = this._waveCssW;
    var cssH = this._waveCssH;
    var mid = cssH * 0.5;
    var amp = mid * 0.92;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, cssW, cssH);
    ctx.beginPath();
    ctx.strokeStyle = this._waveAccentStroke();
    ctx.globalAlpha = 0.65;
    ctx.lineWidth = 1.35;
    ctx.lineJoin = "round";
    var n = this._waveTd.length;
    var step = cssW / (n - 1);
    var x = 0;
    for (var i = 0; i < n; i++) {
      var v = this._waveTd[i] / 128 - 1;
      var y = mid + v * amp;
      if (i === 0) {
        ctx.moveTo(x, y);
      } else {
        ctx.lineTo(x, y);
      }
      x += step;
    }
    ctx.stroke();
    ctx.globalAlpha = 1;
  };

  PlayerBlock.prototype._startWaveformLoop = function () {
    var self = this;
    if (!this._waveCanvas) {
      return;
    }
    if (!this._analyser) {
      this._drawWaveformIdle();
      return;
    }
    if (
      window.matchMedia &&
      window.matchMedia("(prefers-reduced-motion: reduce)").matches
    ) {
      this._drawWaveformIdle();
      return;
    }
    if (this._waveRaf) {
      cancelAnimationFrame(this._waveRaf);
      this._waveRaf = 0;
    }
    function tick() {
      if (self.audio.paused) {
        self._waveRaf = 0;
        self._drawWaveformIdle();
        return;
      }
      self._drawWaveformFrame();
      self._waveRaf = requestAnimationFrame(tick);
    }
    this._waveRaf = requestAnimationFrame(tick);
  };

  PlayerBlock.prototype._stopWaveformLoop = function () {
    if (this._waveRaf) {
      cancelAnimationFrame(this._waveRaf);
      this._waveRaf = 0;
    }
    this._drawWaveformIdle();
  };

})(window.RmAudioPlaylist = window.RmAudioPlaylist || {});
