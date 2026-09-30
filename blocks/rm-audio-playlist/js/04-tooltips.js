/** Floating tooltips */
(function (ns) {
  "use strict";
  var PlayerBlock = ns.PlayerBlock;

  PlayerBlock.prototype._ensureFloatTip = function () {
    if (this._floatTip) {
      return this._floatTip;
    }
    var tip = document.createElement("div");
    tip.className = "rm-audio-playlist__floattip";
    tip.setAttribute("aria-hidden", "true");
    this.root.appendChild(tip);
    this._floatTip = tip;
    return tip;
  };

  PlayerBlock.prototype._positionFloatTip = function (e) {
    if (!this._floatTip) {
      return;
    }
    var pad = 12;
    var x = e.clientX + pad;
    var y = e.clientY + pad;
    var rect = this._floatTip.getBoundingClientRect();
    var vw = window.innerWidth;
    var vh = window.innerHeight;
    if (x + rect.width > vw - 8) {
      x = Math.max(8, vw - rect.width - 8);
    }
    if (y + rect.height > vh - 8) {
      y = Math.max(8, e.clientY - rect.height - pad);
    }
    this._floatTip.style.left = x + "px";
    this._floatTip.style.top = y + "px";
  };

  PlayerBlock.prototype._positionFloatTipNearElement = function (el) {
    if (!this._floatTip || !el) {
      return;
    }
    var r = el.getBoundingClientRect();
    var pad = 12;
    var tw = this._floatTip.offsetWidth;
    var th = this._floatTip.offsetHeight;
    var x = r.left + (r.width - tw) / 2;
    var y = r.bottom + pad;
    if (x < 8) {
      x = 8;
    }
    if (x + tw > window.innerWidth - 8) {
      x = Math.max(8, window.innerWidth - tw - 8);
    }
    if (y + th > window.innerHeight - 8) {
      y = Math.max(8, r.top - th - pad);
    }
    this._floatTip.style.left = x + "px";
    this._floatTip.style.top = y + "px";
  };

  PlayerBlock.prototype._hideFloatTip = function () {
    var self = this;
    var tip = this._floatTip;
    if (!tip || !tip.classList.contains("is-visible")) {
      return;
    }
    tip.classList.add("is-out");
    clearTimeout(this._tipHideTimer);
    this._tipHideTimer = setTimeout(function () {
      tip.classList.remove("is-visible", "is-out");
      tip.textContent = "";
      tip.style.left = "";
      tip.style.top = "";
      if (self._floatTipTarget === null) {
        /* noop */
      }
    }, 220);
  };

  PlayerBlock.prototype._wireFloatTipsDelegated = function () {
    if (DISABLE_TOOLTIPS) {
      return;
    }
    var self = this;
    var root = this.root;

    function showFor(el, e) {
      var text = el.getAttribute("data-rm-tip");
      if (!text) {
        return;
      }
      clearTimeout(self._tipHideTimer);
      clearTimeout(self._focusTipTimer);
      self._floatTipFromKeyboard = false;
      self._ensureFloatTip();
      self._floatTipTarget = el;
      self._floatTip.textContent = text;
      self._floatTip.classList.remove("is-out");
      self._floatTip.classList.add("is-visible");
      self._floatTip.style.transform = "";
      requestAnimationFrame(function () {
        self._positionFloatTip(e);
      });
    }

    root.addEventListener(
      "pointerover",
      function (e) {
        var el = e.target.closest(".rm-audio-playlist--has-tip[data-rm-tip]");
        if (!el || !root.contains(el)) {
          return;
        }
        if (self._floatTipTarget === el) {
          self._positionFloatTip(e);
          return;
        }
        showFor(el, e);
      },
      true,
    );

    root.addEventListener(
      "pointerout",
      function (e) {
        var tipEl = self._floatTipTarget;
        if (!tipEl) {
          return;
        }
        var rel = e.relatedTarget;
        if (rel && tipEl.contains(rel)) {
          return;
        }
        self._floatTipTarget = null;
        self._floatTipFromKeyboard = false;
        self._hideFloatTip();
      },
      true,
    );

    root.addEventListener(
      "pointermove",
      function (e) {
        if (
          !self._floatTipTarget ||
          !self._floatTip ||
          !self._floatTip.classList.contains("is-visible")
        ) {
          return;
        }
        if (!self._floatTipTarget.contains(e.target)) {
          return;
        }
        if (self._floatTipFromKeyboard) {
          self._floatTipFromKeyboard = false;
        }
        self._positionFloatTip(e);
      },
      true,
    );

    root.addEventListener("focusin", function (e) {
      var el = e.target.closest(".rm-audio-playlist--has-tip[data-rm-tip]");
      if (!el || !root.contains(el)) {
        return;
      }
      var text = el.getAttribute("data-rm-tip");
      if (!text) {
        return;
      }
      clearTimeout(self._tipHideTimer);
      clearTimeout(self._focusTipTimer);
      self._floatTipFromKeyboard = true;
      self._floatTipTarget = el;
      self._ensureFloatTip();
      self._floatTip.textContent = text;
      self._floatTip.classList.remove("is-out");
      self._floatTip.classList.add("is-visible");
      self._floatTip.style.transform = "";
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          self._positionFloatTipNearElement(el);
        });
      });
    });

    root.addEventListener("focusout", function () {
      clearTimeout(self._focusTipTimer);
      self._focusTipTimer = setTimeout(function () {
        if (!self._floatTipFromKeyboard) {
          return;
        }
        var ae = document.activeElement;
        if (
          !ae ||
          !root.contains(ae) ||
          !self._floatTipTarget ||
          !self._floatTipTarget.contains(ae)
        ) {
          self._floatTipFromKeyboard = false;
          self._floatTipTarget = null;
          self._hideFloatTip();
        }
      }, 0);
    });
  };

})(window.RmAudioPlaylist = window.RmAudioPlaylist || {});
