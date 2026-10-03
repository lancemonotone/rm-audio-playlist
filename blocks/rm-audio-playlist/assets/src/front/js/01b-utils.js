/** Time format and shuffle helper */
(function (ns) {
  "use strict";
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
      return "0:00";
    }
    var m = Math.floor(sec / 60);
    var s = Math.floor(sec % 60);
    return m + ":" + (s < 10 ? "0" : "") + s;
  }

  ns._shuffle = _shuffle;
  ns._fmtTime = _fmtTime;

})(window.RmAudioPlaylist = window.RmAudioPlaylist || {});
