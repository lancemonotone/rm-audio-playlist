/** Boot player blocks on the page */
(function (ns) {
  "use strict";
  var PlayerBlock = ns.PlayerBlock;

  function init() {
    var nodes = document.querySelectorAll(
      ".rm-audio-playlist[data-rm-playlist]",
    );
    [].forEach.call(nodes, function (node) {
      var raw = node.getAttribute("data-rm-playlist");
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

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

})(window.RmAudioPlaylist = window.RmAudioPlaylist || {});
