/** Queue list and reorder */
(function (ns) {
  "use strict";
  var PlayerBlock = ns.PlayerBlock;
  var SVG = ns.SVG;
  var _svg = ns._svg;
  var _create = ns._create;

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
    this._updateIndexLine();
    this._highlight();
    var self = this;
    requestAnimationFrame(function () {
      if (!self._list) {
        return;
      }
      var row = self._list.querySelector(
        '.rm-audio-playlist__item[data-queue-pos="' + String(toQi) + '"]',
      );
      var h = row && row.querySelector(".rm-audio-playlist__item-handle");
      if (h) {
        try {
          h.focus();
        } catch (e) {
          /* ignore */
        }
      }
    });
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
      var li = _create("li", "rm-audio-playlist__item", "");
      li.setAttribute("data-idx", String(trackIdx));
      li.setAttribute("data-queue-pos", String(qi));
      li.setAttribute("role", "listitem");

      var canDrag = !self.shuffle;
      var handle = document.createElement("button");
      handle.type = "button";
      handle.className =
        "rm-audio-playlist__item-handle rm-audio-playlist--has-tip";
      handle.setAttribute("draggable", canDrag ? "true" : "false");
      handle.setAttribute(
        "aria-label",
        canDrag
          ? "Reorder " + t.title + ". Alt+Arrow Up or Down, or drag."
          : "Reordering is off while shuffle is on",
      );
      handle.setAttribute(
        "data-rm-tip",
        canDrag
          ? "Drag to move, or focus and press Alt+Arrow Up / Down"
          : "Turn shuffle off to reorder tracks",
      );
      handle.disabled = !canDrag;
      handle.appendChild(_svg(SVG.grip));
      handle.addEventListener("keydown", function (e) {
        if (!canDrag) {
          return;
        }
        if (!e.altKey) {
          return;
        }
        if (e.key === "ArrowUp") {
          e.preventDefault();
          self._reorderQueue(qi, qi - 1);
        } else if (e.key === "ArrowDown") {
          e.preventDefault();
          self._reorderQueue(qi, qi + 1);
        }
      });
      handle.addEventListener("dragstart", function (e) {
        if (!canDrag) {
          e.preventDefault();
          return;
        }
        self._dragFromQi = qi;
        li.classList.add("is-dragging");
        e.dataTransfer.effectAllowed = "move";
        e.dataTransfer.setData("text/plain", String(qi));
        try {
          e.dataTransfer.setData("application/x-rm-pl", String(qi));
        } catch (err) {
          /* ignore */
        }
      });
      handle.addEventListener("dragend", function () {
        li.classList.remove("is-dragging");
        self._dragFromQi = -1;
        [].forEach.call(
          self._list.querySelectorAll(".rm-audio-playlist__item"),
          function (row) {
            row.classList.remove("is-drag-over");
          },
        );
      });
      li.appendChild(handle);

      li.appendChild(
        _create("span", "rm-audio-playlist__item-num", String(qi + 1), {
          "aria-hidden": "true",
        }),
      );

      var b = document.createElement("a");
      b.className = "rm-audio-playlist__item-title rm-audio-playlist--has-tip";
      b.setAttribute("data-rm-tip", "Play now");
      b.href = t.url;
      b.appendChild(document.createTextNode(t.title));
      b.addEventListener("click", function (ev) {
        ev.preventDefault();
        self._jumpToListIndex(trackIdx);
      });
      li.appendChild(b);

      if (t.downloadable) {
        var dlA = document.createElement("a");
        dlA.className = "rm-audio-playlist__item-dl rm-audio-playlist--has-tip";
        dlA.href = t.url;
        if (t.downloadName) {
          dlA.setAttribute("download", t.downloadName);
        }
        dlA.setAttribute("aria-label", "Download — " + t.title);
        dlA.setAttribute("data-rm-tip", "Download this track (MP3).");
        dlA.appendChild(_svg(SVG.dl));
        li.appendChild(dlA);
      }

      li.addEventListener("dragover", function (e) {
        if (self._dragFromQi < 0) {
          return;
        }
        e.preventDefault();
        e.dataTransfer.dropEffect = "move";
        [].forEach.call(
          self._list.querySelectorAll(".rm-audio-playlist__item"),
          function (row) {
            row.classList.remove("is-drag-over");
          },
        );
        li.classList.add("is-drag-over");
      });
      li.addEventListener("dragleave", function (e) {
        if (e.currentTarget.contains(e.relatedTarget)) {
          return;
        }
        li.classList.remove("is-drag-over");
      });
      li.addEventListener("drop", function (e) {
        e.preventDefault();
        li.classList.remove("is-drag-over");
        var fromStr = e.dataTransfer.getData("text/plain");
        var fromQi = parseInt(fromStr, 10);
        if (isNaN(fromQi)) {
          fromStr = e.dataTransfer.getData("application/x-rm-pl");
          fromQi = parseInt(fromStr, 10);
        }
        if (isNaN(fromQi)) {
          return;
        }
        var toQi = parseInt(li.getAttribute("data-queue-pos") || "-1", 10);
        self._reorderQueue(fromQi, toQi);
      });

      self._list.appendChild(li);
    });
  };

})(window.RmAudioPlaylist = window.RmAudioPlaylist || {});
