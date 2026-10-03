/** SVG icons and DOM helpers */
(function (ns) {
  "use strict";
  /**
   * Self-contained 24×24 icons, currentColor (fill or stroke).
   * Shuffle + repeat glyphs: paths from Lucide (lucide-static v0.468.0, ISC).
   * shufOff / rptOff arrowheads match shufOn’s two-stroke chevrons (same d, shifted for centering).
   */
  var SVG = {
    play: '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M7 4.5l14 7.5L7 20V4.5z"/></svg>',
    pause:
      '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M5.5 3.5h3.5v17H5.5v-17zM15 3.5h3.5v17H15v-17z"/></svg>',
    prev: '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M21.5 3.5H18v17h3.5v-17zm-4 0L4 12l13.5 8.5V3.5z"/></svg>',
    next: '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M2.5 3.5H6v17H2.5v-17zm4 0L20 12 6.5 20.5V3.5z"/></svg>',
    shufOn:
      '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m18 14 4 4-4 4"/><path d="m18 2 4 4-4 4"/><path d="M2 18h1.973a4 4 0 0 0 3.3-1.7l5.454-8.6a4 4 0 0 1 3.3-1.7H22"/><path d="M2 6h1.972a4 4 0 0 1 3.6 2.2"/><path d="M22 18h-6.041a4 4 0 0 1-3.3-1.8l-.359-.45"/></svg>',
    shufOff:
      '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 6H18"/><path d="m18 2 4 4-4 4"/><path d="M2 18H18"/><path d="m18 14 4 4-4 4"/></svg>',
    rptOff:
      '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12H18"/><path d="m18 8 4 4-4 4"/></svg>',
    rptAll:
      '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/></svg>',
    rptOne:
      '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/><path d="M11 10h1v4"/></svg>',
    vol: '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M11 5.5L6.5 9.5H3.5A1.5 1.5 0 0 0 2 11v2a1.5 1.5 0 0 0 1.5 1.5H6l4.5 3.5V5.5zM15.5 9.5a3.5 3.5 0 0 1 0 4.5M18 6a6.5 6.5 0 0 1 0 12"/></svg>',
    mute: '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M11 5.5L6.5 9.5H3.5A1.5 1.5 0 0 0 2 11v2a1.5 1.5 0 0 0 1.5 1.5H6l4.5 3.5V5.5z"/><line x1="22" y1="2" x2="2" y2="22"/></svg>',
    dl: '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
    grip: '<svg class="rm-audio-playlist__icon rm-audio-playlist__icon--grip" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><circle cx="9" cy="6" r="1.35"/><circle cx="15" cy="6" r="1.35"/><circle cx="9" cy="12" r="1.35"/><circle cx="15" cy="12" r="1.35"/><circle cx="9" cy="18" r="1.35"/><circle cx="15" cy="18" r="1.35"/></svg>',
    close:
      '<svg class="rm-audio-playlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M18 6L6 18M6 6l12 12"/></svg>',
  };

  function rptSvg(mode) {
    if (mode === "all") {
      return SVG.rptAll;
    }
    if (mode === "one") {
      return SVG.rptOne;
    }
    return SVG.rptOff;
  }

  /**
   * @param {string} svgStr Markup: single root <svg>
   * @return {Element}
   */
  function _svg(svgStr) {
    var w = document.createElement("div");
    w.innerHTML = svgStr.trim();
    return /** @type {Element} */ (w.firstChild);
  }

  /**
   * @param {string} tag
   * @param {string} cls
   * @param {string|DocumentFragment|ChildNode} inner
   * @param {object} [attr]
   */
  function _create(tag, cls, inner, attr) {
    var el = document.createElement(tag);
    if (cls) {
      el.className = cls;
    }
    if (typeof inner === "string") {
      el.appendChild(document.createTextNode(inner));
    } else if (inner) {
      el.appendChild(inner);
    }
    if (attr) {
      for (var k in attr) {
        if (Object.prototype.hasOwnProperty.call(attr, k)) {
          el.setAttribute(k, attr[k]);
        }
      }
    }
    return el;
  }

  ns.SVG = SVG;
  ns.rptSvg = rptSvg;
  ns._svg = _svg;
  ns._create = _create;

})(window.RmAudioPlaylist = window.RmAudioPlaylist || {});
