/**
 * RM Audio Playlist — admin: shortcode field click-to-select.
 */
(function () {
	'use strict';
	document.addEventListener('click', function (e) {
		var t = e.target;
		if (t && t.id === 'rm-pl-shortcode-copy') {
			t.select();
		}
	});
})();
