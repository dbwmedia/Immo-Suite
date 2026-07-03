(function () {
	var data = window.dbwLightboxData || {};
	var galleryImages = data.gallery || [];
	var floorplanImages = data.floorplans || [];

	var overlay = document.getElementById('dbwLightboxOverlay');
	if (!overlay) return;

	var lbImage = document.getElementById('dbwLbImage');
	var lbCounter = document.getElementById('dbwLbCounter');
	var closeBtn = overlay.querySelector('.dbw-lightbox-btn--close');
	var currentSet = [];
	var currentIdx = 0;
	var previousFocus = null;
	var showTimer = null;

	if (lbCounter) lbCounter.setAttribute('aria-live', 'polite');

	// Focus trap: keep Tab within lightbox
	var focusableEls = overlay.querySelectorAll('button');
	overlay.addEventListener('keydown', function (e) {
		if (e.key !== 'Tab' || overlay.style.display !== 'flex') return;
		var first = focusableEls[0];
		var last = focusableEls[focusableEls.length - 1];
		if (e.shiftKey) {
			if (document.activeElement === first) { last.focus(); e.preventDefault(); }
		} else {
			if (document.activeElement === last) { first.focus(); e.preventDefault(); }
		}
	});

	window.dbwLightbox = {
		open: function (type, index) {
			previousFocus = document.activeElement;
			currentSet = (type === 'gallery') ? galleryImages : floorplanImages;
			currentIdx = index || 0;
			this.show();
			overlay.style.display = 'flex';
			document.body.style.overflow = 'hidden';
			if (closeBtn) closeBtn.focus();
		},
		close: function () {
			overlay.style.display = 'none';
			document.body.style.overflow = '';
			if (previousFocus) previousFocus.focus();
		},
		prev: function () {
			currentIdx = (currentIdx - 1 + currentSet.length) % currentSet.length;
			this.show();
		},
		next: function () {
			currentIdx = (currentIdx + 1) % currentSet.length;
			this.show();
		},
		show: function () {
			lbImage.style.opacity = '0';
			// rapid prev/next: cancel the pending swap so intermediate images never load
			clearTimeout(showTimer);
			showTimer = setTimeout(function () {
				var item = currentSet[currentIdx];
				var src = (typeof item === 'string') ? item : item.url;
				var alt = (typeof item === 'string') ? '' : (item.alt || '');
				lbImage.src = src;
				lbImage.alt = alt;
				lbImage.onload = function () { lbImage.style.opacity = '1'; };
				lbImage.onerror = function () { lbImage.style.opacity = '1'; };
				lbCounter.textContent = (currentIdx + 1) + ' / ' + currentSet.length;
			}, 120);
		}
	};

	// Keyboard
	document.addEventListener('keydown', function (e) {
		if (overlay.style.display !== 'flex') return;
		if (e.key === 'Escape') dbwLightbox.close();
		if (e.key === 'ArrowLeft') dbwLightbox.prev();
		if (e.key === 'ArrowRight') dbwLightbox.next();
	});

	// Click on backdrop to close
	overlay.addEventListener('click', function (e) {
		if (e.target === overlay) dbwLightbox.close();
	});

	// Touch Swipe
	var startX = 0;
	overlay.addEventListener('touchstart', function (e) {
		startX = e.changedTouches[0].screenX;
	}, { passive: true });
	overlay.addEventListener('touchend', function (e) {
		var diff = e.changedTouches[0].screenX - startX;
		if (Math.abs(diff) > 50) {
			if (diff > 0) dbwLightbox.prev(); else dbwLightbox.next();
		}
	}, { passive: true });
})();
