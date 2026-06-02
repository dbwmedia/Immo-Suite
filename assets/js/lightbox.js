(function () {
	var data = window.dbwLightboxData || {};
	var galleryImages = data.gallery || [];
	var floorplanImages = data.floorplans || [];

	var overlay = document.getElementById('dbwLightboxOverlay');
	if (!overlay) return;

	var lbImage = document.getElementById('dbwLbImage');
	var lbCounter = document.getElementById('dbwLbCounter');
	var currentSet = [];
	var currentIdx = 0;

	window.dbwLightbox = {
		open: function (type, index) {
			currentSet = (type === 'gallery') ? galleryImages : floorplanImages;
			currentIdx = index || 0;
			this.show();
			overlay.style.display = 'flex';
			document.body.style.overflow = 'hidden';
		},
		close: function () {
			overlay.style.display = 'none';
			document.body.style.overflow = '';
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
			setTimeout(function () {
				lbImage.src = currentSet[currentIdx];
				lbImage.onload = function () { lbImage.style.opacity = '1'; };
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
