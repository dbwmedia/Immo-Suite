/**
 * Kaufnebenkosten- & Finanzierungsrechner
 * Vanilla JS, no jQuery dependency.
 */
(function () {
	'use strict';

	if (typeof dbwFinanceCalc === 'undefined') return;

	var data = dbwFinanceCalc;
	var i18n = data.i18n || {};
	var kaufpreis = parseFloat(data.kaufpreis) || 0;
	if (kaufpreis <= 0) return;

	// Configurable rates from backend settings
	var NOTAR_RATE = parseFloat(data.notarPercent) || 1.5;
	var GRUNDBUCH_RATE = parseFloat(data.grundbuchPercent) || 0.5;
	var DEFAULT_ZINSSATZ = parseFloat(data.defaultZinssatz) || 3.5;
	var DEFAULT_TILGUNG = parseFloat(data.defaultTilgung) || 2.0;
	var GEST_OVERRIDE = parseFloat(data.gestOverride) || 0;

	// PLZ prefix -> Bundesland mapping (first 2 digits)
	var PLZ_MAP = {
		'01': 'Sachsen', '02': 'Sachsen', '03': 'Brandenburg', '04': 'Sachsen',
		'06': 'Sachsen-Anhalt', '07': 'Thueringen', '08': 'Sachsen', '09': 'Sachsen',
		'10': 'Berlin', '11': 'Berlin', '12': 'Berlin', '13': 'Berlin', '14': 'Brandenburg',
		'15': 'Brandenburg', '16': 'Brandenburg', '17': 'Mecklenburg-Vorpommern',
		'18': 'Mecklenburg-Vorpommern', '19': 'Mecklenburg-Vorpommern',
		'20': 'Hamburg', '21': 'Niedersachsen', '22': 'Hamburg', '23': 'Schleswig-Holstein',
		'24': 'Schleswig-Holstein', '25': 'Schleswig-Holstein', '26': 'Niedersachsen',
		'27': 'Niedersachsen', '28': 'Bremen', '29': 'Niedersachsen',
		'30': 'Niedersachsen', '31': 'Niedersachsen', '32': 'Nordrhein-Westfalen',
		'33': 'Nordrhein-Westfalen', '34': 'Hessen', '35': 'Hessen',
		'36': 'Hessen', '37': 'Niedersachsen', '38': 'Niedersachsen', '39': 'Sachsen-Anhalt',
		'40': 'Nordrhein-Westfalen', '41': 'Nordrhein-Westfalen', '42': 'Nordrhein-Westfalen',
		'44': 'Nordrhein-Westfalen', '45': 'Nordrhein-Westfalen', '46': 'Nordrhein-Westfalen',
		'47': 'Nordrhein-Westfalen', '48': 'Nordrhein-Westfalen', '49': 'Niedersachsen',
		'50': 'Nordrhein-Westfalen', '51': 'Nordrhein-Westfalen', '52': 'Nordrhein-Westfalen',
		'53': 'Nordrhein-Westfalen', '54': 'Rheinland-Pfalz', '55': 'Rheinland-Pfalz',
		'56': 'Rheinland-Pfalz', '57': 'Nordrhein-Westfalen', '58': 'Nordrhein-Westfalen',
		'59': 'Nordrhein-Westfalen',
		'60': 'Hessen', '61': 'Hessen', '63': 'Hessen', '64': 'Hessen',
		'65': 'Hessen', '66': 'Saarland', '67': 'Rheinland-Pfalz',
		'68': 'Baden-Wuerttemberg', '69': 'Baden-Wuerttemberg',
		'70': 'Baden-Wuerttemberg', '71': 'Baden-Wuerttemberg', '72': 'Baden-Wuerttemberg',
		'73': 'Baden-Wuerttemberg', '74': 'Baden-Wuerttemberg', '75': 'Baden-Wuerttemberg',
		'76': 'Baden-Wuerttemberg', '77': 'Baden-Wuerttemberg', '78': 'Baden-Wuerttemberg',
		'79': 'Baden-Wuerttemberg',
		'80': 'Bayern', '81': 'Bayern', '82': 'Bayern', '83': 'Bayern',
		'84': 'Bayern', '85': 'Bayern', '86': 'Bayern', '87': 'Bayern',
		'88': 'Baden-Wuerttemberg', '89': 'Baden-Wuerttemberg',
		'90': 'Bayern', '91': 'Bayern', '92': 'Bayern', '93': 'Bayern',
		'94': 'Bayern', '95': 'Bayern', '96': 'Bayern', '97': 'Bayern',
		'98': 'Thueringen', '99': 'Thueringen'
	};

	var GEST_RATES = {
		'Baden-Wuerttemberg': 5.0, 'Bayern': 3.5, 'Berlin': 6.0,
		'Brandenburg': 6.5, 'Bremen': 5.0, 'Hamburg': 5.5, 'Hessen': 6.0,
		'Mecklenburg-Vorpommern': 6.0, 'Niedersachsen': 5.0,
		'Nordrhein-Westfalen': 6.5, 'Rheinland-Pfalz': 5.0, 'Saarland': 6.5,
		'Sachsen': 5.5, 'Sachsen-Anhalt': 5.0, 'Schleswig-Holstein': 6.5,
		'Thueringen': 5.0
	};

	var BUNDESLAND_DISPLAY = {
		'Baden-Wuerttemberg': 'Baden-W\u00FCrttemberg',
		'Thueringen': 'Th\u00FCringen'
	};

	function getBundesland(plz) {
		return PLZ_MAP[String(plz).substring(0, 2)] || null;
	}

	function getGestRate(bundesland) {
		if (GEST_OVERRIDE > 0) return GEST_OVERRIDE;
		return bundesland ? (GEST_RATES[bundesland] || 5.0) : 5.0;
	}

	function getBundeslandDisplay(bl) {
		return BUNDESLAND_DISPLAY[bl] || bl;
	}

	function parseProvisionPercent(raw) {
		if (!raw) return { value: 0, display: '' };
		var str = String(raw);
		var match = str.replace(',', '.').match(/([\d.]+)\s*%/);
		if (!match) return { value: 0, display: '' };
		// Keep original display string (e.g. "3,57 %") from the raw input
		var displayMatch = str.match(/([\d,.]+)\s*%/);
		var display = displayMatch ? displayMatch[1].replace('.', ',') + '\u00A0%' : fmtPct(parseFloat(match[1]));
		return { value: parseFloat(match[1]), display: display };
	}

	function fmtCur(v) {
		return v.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' \u20AC';
	}

	function fmtPct(v) {
		return v.toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '\u00A0%';
	}

	function el(id) {
		return document.getElementById(id);
	}

	function setText(id, val) {
		var e = el(id);
		if (e) e.textContent = val;
	}

	// Smoothly tween currency outputs while sliders are dragged
	var tweenState = {};
	var REDUCED_MOTION = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function setCurTween(id, target) {
		var e = el(id);
		if (!e) return;
		if (REDUCED_MOTION) {
			tweenState[id] = target;
			e.textContent = fmtCur(target);
			return;
		}
		var from = (typeof tweenState[id] === 'number') ? tweenState[id] : target;
		tweenState[id] = target;
		if (from === target) {
			e.textContent = fmtCur(target);
			return;
		}
		var start = performance.now();
		var dur = 220;
		function frame(now) {
			if (tweenState[id] !== target) return; // a newer tween took over
			var t = Math.min(1, (now - start) / dur);
			var eased = 1 - Math.pow(1 - t, 2);
			e.textContent = fmtCur(from + (target - from) * eased);
			if (t < 1) requestAnimationFrame(frame);
		}
		requestAnimationFrame(frame);
	}

	function init() {
		var container = el('dbw-finance-calculator');
		if (!container) return;

		var bundesland = getBundesland(data.plz);
		var gestRate = getGestRate(bundesland);
		var provision = parseProvisionPercent(data.provision);

		// Nebenkosten
		var gestAmount = kaufpreis * gestRate / 100;
		var notarAmount = kaufpreis * NOTAR_RATE / 100;
		var grundbuchAmount = kaufpreis * GRUNDBUCH_RATE / 100;
		var provisionAmount = kaufpreis * provision.value / 100;
		var gesamtkosten = kaufpreis + gestAmount + notarAmount + grundbuchAmount + provisionAmount;

		// Labels
		setText('dbw-calc-headline', i18n.headline);
		setText('dbw-calc-label-kaufpreis', i18n.kaufpreis);
		setText('dbw-calc-label-gest', i18n.grunderwerbsteuer);
		setText('dbw-calc-label-notar', i18n.notarkosten);
		setText('dbw-calc-label-grundbuch', i18n.grundbuchamt);
		setText('dbw-calc-label-provision', i18n.maklerprovision);
		setText('dbw-calc-label-gesamt', i18n.gesamtkosten);
		setText('dbw-calc-label-ek', i18n.eigenkapital);
		setText('dbw-calc-label-zins', i18n.zinssatz);
		setText('dbw-calc-label-tilgung', i18n.tilgung);
		setText('dbw-calc-label-darlehen', i18n.darlehenssumme);
		setText('dbw-calc-label-rate', i18n.monatliche_rate);
		setText('dbw-calc-label-zinskosten', i18n.zinskosten_10j);
		setText('dbw-calc-fin-headline', i18n.finanzierung);
		setText('dbw-calc-hinweis', i18n.hinweis);

		// Detail percentages
		if (bundesland) {
			setText('dbw-calc-gest-detail', '(' + fmtPct(gestRate) + ' \u2014 ' + getBundeslandDisplay(bundesland) + ')');
		} else if (GEST_OVERRIDE > 0) {
			setText('dbw-calc-gest-detail', '(' + fmtPct(gestRate) + ')');
		} else {
			setText('dbw-calc-gest-detail', '(' + fmtPct(gestRate) + ' \u2014 ' + i18n.bundesland_unknown + ')');
		}
		setText('dbw-calc-notar-detail', '(' + fmtPct(NOTAR_RATE) + ')');
		setText('dbw-calc-grundbuch-detail', '(' + fmtPct(GRUNDBUCH_RATE) + ')');

		// Provision
		var provisionRow = el('dbw-calc-provision-row');
		if (provision.value > 0) {
			setText('dbw-calc-provision-detail', '(' + provision.display + ')');
			setText('dbw-calc-provision', fmtCur(provisionAmount));
		} else if (provisionRow) {
			provisionRow.hidden = true;
		}

		// Fill values
		setText('dbw-calc-kaufpreis', fmtCur(kaufpreis));
		setText('dbw-calc-gest', fmtCur(gestAmount));
		setText('dbw-calc-notar', fmtCur(notarAmount));
		setText('dbw-calc-grundbuch', fmtCur(grundbuchAmount));
		setText('dbw-calc-gesamt', fmtCur(gesamtkosten));

		// Slider setup with backend defaults
		var ekSlider = el('dbw-calc-eigenkapital');
		var zinsSlider = el('dbw-calc-zinssatz');
		var tilgungSlider = el('dbw-calc-tilgung');
		if (!ekSlider || !zinsSlider || !tilgungSlider) return;

		ekSlider.max = Math.round(gesamtkosten);
		ekSlider.value = Math.round(gesamtkosten * 0.2);
		zinsSlider.value = DEFAULT_ZINSSATZ;
		tilgungSlider.value = DEFAULT_TILGUNG;

		function calculate() {
			var eigenkapital = parseFloat(ekSlider.value);
			var zinssatz = parseFloat(zinsSlider.value);
			var tilgung = parseFloat(tilgungSlider.value);

			var darlehen = Math.max(0, gesamtkosten - eigenkapital);
			var monatsrate = darlehen * (zinssatz + tilgung) / 100 / 12;

			// 10-year amortization
			var restschuld = darlehen;
			var totalZins = 0;
			for (var m = 0; m < 120; m++) {
				var mZins = restschuld * zinssatz / 100 / 12;
				var mTilg = monatsrate - mZins;
				if (mTilg < 0) mTilg = 0;
				totalZins += mZins;
				restschuld -= mTilg;
				if (restschuld <= 0) break;
			}

			setText('dbw-calc-ek-output', fmtCur(eigenkapital));
			setText('dbw-calc-zins-output', fmtPct(zinssatz));
			setText('dbw-calc-tilgung-output', fmtPct(tilgung));

			setCurTween('dbw-calc-darlehen', darlehen);
			setCurTween('dbw-calc-rate', monatsrate);
			setCurTween('dbw-calc-zinskosten', totalZins);

			updateFill(ekSlider);
			updateFill(zinsSlider);
			updateFill(tilgungSlider);
		}

		function updateFill(s) {
			var pct = ((s.value - s.min) / (s.max - s.min)) * 100;
			s.style.setProperty('--fill', pct + '%');
		}

		[ekSlider, zinsSlider, tilgungSlider].forEach(function (s) {
			s.addEventListener('input', calculate);
		});

		calculate();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
