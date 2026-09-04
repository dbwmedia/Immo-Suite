<?php
/**
 * Template Name: Single Immobilie
 */

get_header(); ?>

<div id="dbw-immo-suite" class="dbw-single-property-container">

	<?php
	while (have_posts()):
		the_post();
		$id = get_the_ID();

		// 1. Fetch ALL meta in a single query (instead of 42 individual calls)
		$all_meta = get_post_custom($id);
		$m = function($key) use ($all_meta) {
			return isset($all_meta[$key][0]) ? $all_meta[$key][0] : '';
		};

		// Pricing
		$price_kauf = $m('kaufpreis');
		$price_miete = $m('kaltmiete');
		$price_warm = $m('warmmiete');
		$hausgeld = $m('hausgeld');
		$nebenkosten = $m('nebenkosten');
		$provision = $m('provision_kaeufer');

		// Areas
		$area = $m('wohnflaeche');
		$use_area = $m('nutzflaeche');
		$land_area = $m('grundstuecksflaeche');
		$rooms = $m('anzahl_zimmer');
		$bedrooms = $m('anzahl_schlafzimmer');
		$bathrooms = $m('anzahl_badezimmer');
		$parking = $m('anzahl_stellplaetze');

		// Geo
		$plz = $m('plz');
		$city = $m('ort');
		$street = $m('strasse');
		$house_num = $m('hausnummer');
		$lat = $m('geo_breite');
		$lng = $m('geo_laenge');

		// Texts
		$text_lage = $m('text_lage');
		$text_ausstattung = $m('text_ausstattung');
		$text_sonstiges = $m('text_sonstiges');

		// Energy
		$energy_pass_art = $m('energiepass_art');
		$energy_end = $m('energiepass_endenergie');
		$energy_class = $m('energiepass_wertklasse');
		$energy_source = $m('energiepass_traeger');
		$energy_valid = $m('energiepass_gueltig_bis');
		$energy_year = $m('energiepass_baujahr');

		// Contact Person
		$contact_name = $m('kontaktperson_name');
		$contact_firstname = $m('kontaktperson_vorname');
		$contact_email = $m('kontaktperson_email');
		$contact_tel = $m('kontaktperson_tel');
		$contact_img_url = $m('kontaktperson_bild_url');

		// Gallery & Attachments Processing
		$raw_attachments = get_attached_media('image', $id);
		$contact_img_id = $m('kontaktperson_bild_id');

		$gallery_images = array();
		$floor_plans = array();

		$seen_urls = array(); // associative for O(1) lookups

		// Pre-cache attachment meta to avoid N+1 queries
		$att_ids = array_keys($raw_attachments);
		update_meta_cache('post', $att_ids);

		foreach ($raw_attachments as $att_id => $att_post) {
			// 1. Skip Contact Image (ID check match)
			if ($contact_img_id && (int) $att_id === (int) $contact_img_id) {
				continue;
			}

			$img_url = wp_get_attachment_image_url($att_id, 'large');

			// 2. Skip if no URL
			if (!$img_url)
				continue;

			// 3. Skip Contact Image (URL match fallback)
			if ($contact_img_url && $img_url === $contact_img_url) {
				continue;
			}

			// 4. Dedup by URL (prevent same image appearing multiple times due to bad imports)
			if (isset($seen_urls[$img_url])) {
				continue;
			}
			$seen_urls[$img_url] = true;

			$group = get_post_meta($att_id, '_openimmo_gruppe', true);
			$full_url = wp_get_attachment_image_url($att_id, 'full');
			$alt = get_post_meta($att_id, '_wp_attachment_image_alt', true);

			// Get srcset data for responsive images
			$srcset = wp_get_attachment_image_srcset($att_id, 'large');
			$sizes = wp_get_attachment_image_sizes($att_id, 'large');
			$img_meta = wp_get_attachment_metadata($att_id);
			$img_width = isset($img_meta['width']) ? $img_meta['width'] : '';
			$img_height = isset($img_meta['height']) ? $img_meta['height'] : '';

			$item = array(
				'id' => $att_id,
				'url' => $img_url,
				'full' => $full_url,
				'alt' => $alt,
				'srcset' => $srcset,
				'sizes' => $sizes,
				'width' => $img_width,
				'height' => $img_height,
			);

			if ($group === 'GRUNDRISS') {
				$floor_plans[] = $item;
			} else {
				// TITELBILD or BILD
				// If it's the featured image, put it first or handle main display separately
				$gallery_images[] = $item;
			}
		}

		// Sort gallery: Featured first? Or XML order? XML order is preserved in ID fetch usually or we can rely on menu_order
		// For now, simple array is fine.
		$main_image_item = !empty($gallery_images) ? $gallery_images[0] : null;
		?>

		<?php $show_address = get_theme_mod('dbw_immo_single_show_address', true); ?>

		<!-- Header -->
		<div class="dbw-single-header">
			<h1 class="dbw-single-title">
				<?php echo esc_html(get_the_title()); ?>
			</h1>
			<?php if ($show_address): ?>
			<div class="dbw-single-address">
				<span class="dbw-icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
				<?php
				$addr_parts = array_filter(array(
					implode(' ', array_filter(array($street, $house_num))),
					implode(' ', array_filter(array($plz, $city))),
				));
				echo esc_html(implode(', ', $addr_parts));
				?>
			</div>
			<?php endif; ?>
		</div>

		<!-- Gallery Slider -->
		<?php if (!empty($gallery_images) && get_theme_mod('dbw_immo_single_show_gallery', true)): ?>
			<div class="dbw-gallery-wrapper">

				<!-- Floating Buttons - Top Left -->
				<a href="<?php echo esc_url(get_post_type_archive_link('immobilie')); ?>" class="dbw-gallery-btn dbw-gallery-btn--back"
					onclick="if(history.length>1){history.back();return false;}"
					aria-label="<?php esc_attr_e('Zurück zur Übersicht', 'dbw-immo-suite'); ?>">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
						stroke-linecap="round" stroke-linejoin="round">
						<line x1="19" y1="12" x2="5" y2="12"></line>
						<polyline points="12 19 5 12 12 5"></polyline>
					</svg>
				</a>

				<!-- Floating Buttons - Top Right -->
				<div class="dbw-gallery-actions">
					<?php
					// Heart on the detail page too — same list, same sync as the cards
					if (class_exists('DBW\ImmoSuite\Frontend\Favorites')) {
						\DBW\ImmoSuite\Frontend\Favorites::render_card_button($id, 'dbw-fav-btn--single');
					}
					?>
					<?php if (get_theme_mod('dbw_immo_single_show_share', true)): ?>
						<button
							data-dbw-share
							class="dbw-gallery-btn dbw-gallery-btn--action"
							aria-label="<?php esc_attr_e('Teilen', 'dbw-immo-suite'); ?>">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
								stroke-linecap="round" stroke-linejoin="round">
								<circle cx="18" cy="5" r="3"></circle>
								<circle cx="6" cy="12" r="3"></circle>
								<circle cx="18" cy="19" r="3"></circle>
								<line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
								<line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
							</svg>
						</button>
					<?php endif; ?>

					<?php if (get_theme_mod('dbw_immo_single_show_print', true)): ?>
						<a href="<?php echo esc_url(\DBW\ImmoSuite\Frontend\PdfExpose::get_expose_url($id)); ?>"
							target="_blank" rel="noopener"
							class="dbw-gallery-btn dbw-gallery-btn--action"
							aria-label="<?php esc_attr_e('Exposé als PDF herunterladen', 'dbw-immo-suite'); ?>"
							title="<?php esc_attr_e('Exposé als PDF', 'dbw-immo-suite'); ?>">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
								stroke-linecap="round" stroke-linejoin="round">
								<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
								<polyline points="7 10 12 15 17 10"></polyline>
								<line x1="12" y1="15" x2="12" y2="3"></line>
							</svg>
						</a>
					<?php endif; ?>
				</div>

				<!-- Floating Block - Bottom Left -->
				<?php if (!empty($floor_plans)): ?>
					<a href="#dbw-floorplans" class="dbw-gallery-btn dbw-gallery-floorplan-link">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
							stroke-linecap="round" stroke-linejoin="round">
							<path
								d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48">
							</path>
						</svg>
						<?php esc_html_e('Grundrisse & Dokumente', 'dbw-immo-suite'); ?>
					</a>
				<?php endif; ?>

				<!-- Main Slider -->
				<div class="dbw-gallery-slider" id="dbwGallerySlider">
					<?php foreach ($gallery_images as $index => $img): ?>
						<button type="button" class="dbw-gallery-slide" id="slide-<?php echo (int) $index; ?>"
							onclick="dbwLightbox.open('gallery', <?php echo (int) $index; ?>)"
							aria-label="<?php echo esc_attr(sprintf(__('Bild %d in Lightbox öffnen', 'dbw-immo-suite'), $index + 1)); ?>">
							<img src="<?php echo esc_url($img['url']); ?>"
								alt="<?php echo esc_attr($img['alt'] ?: sprintf(__('%1$s — Bild %2$d', 'dbw-immo-suite'), get_the_title(), $index + 1)); ?>"
								<?php if ($img['srcset']): ?>srcset="<?php echo esc_attr($img['srcset']); ?>"<?php endif; ?>
								sizes="(max-width: 768px) 100vw, 800px"
								<?php if ($img['width'] && $img['height']): ?>width="<?php echo esc_attr($img['width']); ?>" height="<?php echo esc_attr($img['height']); ?>"<?php endif; ?>
								<?php echo ($index > 0) ? 'loading="lazy" decoding="async"' : 'fetchpriority="high"'; ?>>
							<?php if ($img['alt']): ?>
								<div class="dbw-slide-caption">
									<?php echo esc_html($img['alt']); ?>
								</div>
								<?php
							endif; ?>
						</button>
						<?php
					endforeach; ?>
				</div>

				<!-- Image Counter -->
				<?php if (count($gallery_images) > 1): ?>
					<div class="dbw-gallery-counter" aria-hidden="true">
						<span data-dbw-gal-current>1</span>/<?php echo (int) count($gallery_images); ?>
					</div>
				<?php endif; ?>

				<!-- Navigation Buttons -->
				<button class="dbw-gallery-nav dbw-gallery-nav--prev" aria-label="<?php esc_attr_e('Vorheriges Bild', 'dbw-immo-suite'); ?>">&#10094;</button>
				<button class="dbw-gallery-nav dbw-gallery-nav--next" aria-label="<?php esc_attr_e('Nächstes Bild', 'dbw-immo-suite'); ?>">&#10095;</button>

				<!-- Thumbnails Strip -->
				<div class="dbw-gallery-thumbs">
					<?php foreach ($gallery_images as $index => $img): ?>
						<button type="button" class="dbw-gallery-thumb" aria-label="<?php echo esc_attr(sprintf(__('Bild %d anzeigen', 'dbw-immo-suite'), $index + 1)); ?>">
							<?php echo wp_get_attachment_image($img['id'], 'thumbnail', false, array(
								'loading' => 'lazy',
								'decoding' => 'async',
								'alt' => $img['alt'] ?: sprintf(__('%1$s — Bild %2$d', 'dbw-immo-suite'), get_the_title(), $index + 1),
							)); ?>
						</button>
						<?php
					endforeach; ?>
				</div>
			</div>
			<?php
		endif; ?>

		<!-- Main Layout Grid -->
		<div class="dbw-details-grid">

			<!-- Left Content Content -->
			<div class="dbw-main-col">

				<!-- Key Facts Row -->
				<div class="dbw-features-list dbw-features-list--key-facts">
					<?php if ($area > 0): ?>
						<div class="dbw-feature-item">
							<span class="dbw-meta-label"><?php esc_html_e('Wohnfläche', 'dbw-immo-suite'); ?></span><br>
							<span class="dbw-meta-value">
								<?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($area, 'flaeche')); ?> m²
							</span>
						</div>
						<?php
					endif; ?>

					<?php if ($rooms > 0): ?>
						<div class="dbw-feature-item">
							<span class="dbw-meta-label"><?php esc_html_e('Zimmer', 'dbw-immo-suite'); ?></span><br>
							<span class="dbw-meta-value">
								<?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($rooms, 'zimmer')); ?>
							</span>
						</div>
						<?php
					endif; ?>

					<?php if ($land_area > 0): ?>
						<div class="dbw-feature-item">
							<span class="dbw-meta-label"><?php esc_html_e('Grundstück', 'dbw-immo-suite'); ?></span><br>
							<span class="dbw-meta-value">
								<?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($land_area, 'flaeche')); ?> m²
							</span>
						</div>
						<?php
					endif; ?>

					<?php if ($use_area > 0): ?>
						<div class="dbw-feature-item">
							<span class="dbw-meta-label"><?php esc_html_e('Nutzfläche', 'dbw-immo-suite'); ?></span><br>
							<span class="dbw-meta-value">
								<?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($use_area, 'flaeche')); ?> m²
							</span>
						</div>
						<?php
					endif; ?>
				</div>

				<div class="dbw-section">
					<h3 class="dbw-section-title"><?php esc_html_e('Beschreibung', 'dbw-immo-suite'); ?></h3>
					<div class="dbw-description">
						<?php the_content(); ?>
					</div>
				</div>

				<?php
				$features = get_post_meta($id, '_dbw_immo_features', true);
				if (!is_array($features)) $features = array();
				if ($text_ausstattung || $parking > 0 || !empty($features)):
			?>
					<div class="dbw-section">
						<h3 class="dbw-section-title"><?php esc_html_e('Ausstattung', 'dbw-immo-suite'); ?></h3>

						<?php if (!empty($features)): ?>
						<div class="dbw-features-badges">
							<?php foreach ($features as $feature): ?>
								<span class="dbw-feature-badge"><?php echo esc_html($feature); ?></span>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>

						<div class="dbw-description">
							<?php if ($parking > 0): ?>
								<p><strong><?php _e('Stellplätze:', 'dbw-immo-suite'); ?></strong>
									<?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($parking, 'zimmer')); ?>
								</p>
							<?php endif; ?>
							<?php if ($text_ausstattung): ?>
								<?php echo wp_kses_post(wpautop($text_ausstattung)); ?>
							<?php endif; ?>
						</div>
					</div>
			<?php endif; ?>

				<?php if (($text_lage || ($lat && $lng)) && get_theme_mod('dbw_immo_single_show_map', true) && $show_address): ?>
					<div class="dbw-section">
						<h3 class="dbw-section-title"><?php esc_html_e('Lage', 'dbw-immo-suite'); ?></h3>
						<?php if ($text_lage): ?>
						<div class="dbw-description">
							<?php echo wp_kses_post(wpautop($text_lage)); ?>
						</div>
						<?php endif; ?>

						<?php if ($lat && $lng):
							// Default must match Customizer + ArchiveMap (true): without a saved
							// theme_mod a false default would load OSM tiles without consent.
							$map_consent = get_theme_mod('dbw_immo_single_map_consent', true);
						?>
						<?php if ($map_consent): ?>
						<div id="dbw-map-consent" class="dbw-map-placeholder"
							data-lat="<?php echo esc_attr($lat); ?>"
							data-lng="<?php echo esc_attr($lng); ?>">
							<div class="dbw-map-placeholder__inner">
								<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
								<p class="dbw-map-placeholder__text"><?php echo esc_html(\DBW\ImmoSuite\Core\Legal::map_prompt()); ?></p>
								<button type="button" class="dbw-btn dbw-btn--ghost dbw-map-placeholder__btn" id="dbw-map-load">
									<?php esc_html_e('Karte laden', 'dbw-immo-suite'); ?>
								</button>
								<p class="dbw-map-placeholder__hint"><?php echo esc_html(\DBW\ImmoSuite\Core\Legal::map_notice()); ?></p>
							</div>
						</div>
						<?php endif; ?>
						<div id="dbw-map" style="<?php echo $map_consent ? 'display:none;' : ''; ?>"></div>
						<?php
						// Leaflet CSS/JS is enqueued in Plugin::enqueue_public_scripts (wp_head-safe)
						if ($map_consent) {
							// Consent mode: init on button click or Borlabs consent
							wp_add_inline_script('dbw-immo-map-consent', sprintf(
								'(function(){' .
								'var consent=document.getElementById("dbw-map-consent");' .
								'var mapEl=document.getElementById("dbw-map");' .
								'var btn=document.getElementById("dbw-map-load");' .
								'if(!consent||!mapEl||!btn)return;' .
								'var done=false;' .
								'function initMap(){' .
									'if(done)return;' .
									// Optimizers can reorder scripts, so Leaflet may still be pending
									'if(typeof L==="undefined"){setTimeout(initMap,100);return;}' .
									'done=true;' .
									'consent.style.display="none";' .
									'mapEl.style.display="block";' .
									'var m=L.map("dbw-map",{scrollWheelZoom:false}).setView([%1$.7F,%2$.7F],14);' .
									'L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{attribution:"&copy; <a href=\'https://www.openstreetmap.org/copyright\'>OpenStreetMap</a>",maxZoom:18}).addTo(m);' .
									'L.marker([%1$.7F,%2$.7F]).addTo(m);' .
								'}' .
								// Explicit click is consent in itself, no tool needed
								'btn.addEventListener("click",function(){' .
									'if(window.dbwImmoMapConsent)window.dbwImmoMapConsent.grant();' .
									'initMap();' .
								'});' .
								// Skip the placeholder when the consent tool already has a yes.
								// Queue instead of a direct call: this inline script may run before
								// the bridge when an optimizer rewrites script order.
								'if(window.dbwImmoMapConsent){window.dbwImmoMapConsent.onGrant(initMap);}' .
								'else{(window.dbwImmoMapConsentQueue=window.dbwImmoMapConsentQueue||[]).push(initMap);}' .
								'})();',
								// Float cast: coords land in a bare numeric JS context,
								// esc_js would not stop a breakout there
								(float) $lat, (float) $lng
							));
						} else {
							// Direct mode: init map immediately
							wp_add_inline_script('dbw-immo-map-consent', sprintf(
								'(function(){' .
								'var m=L.map("dbw-map",{scrollWheelZoom:false}).setView([%1$.7F,%2$.7F],14);' .
								'L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{attribution:"&copy; <a href=\'https://www.openstreetmap.org/copyright\'>OpenStreetMap</a>",maxZoom:18}).addTo(m);' .
								'L.marker([%1$.7F,%2$.7F]).addTo(m);' .
								'})();',
								(float) $lat, (float) $lng
							));
						}
						?>
						<?php endif; ?>

					</div>
					<?php
				endif; ?>

				<?php
				// Infrastructure Score (replaces old distance list)
				if (class_exists('DBW\ImmoSuite\Frontend\InfrastructureScore')) {
					\DBW\ImmoSuite\Frontend\InfrastructureScore::render($id);
				}
				?>

				<?php
			// Finance Calculator (only for Kaufobjekte)
			if (class_exists('DBW\ImmoSuite\Frontend\FinanceCalculator')) {
				\DBW\ImmoSuite\Frontend\FinanceCalculator::render($id);
			}
			?>

			<?php if (($energy_class || $energy_end) && get_theme_mod('dbw_immo_single_show_energy', true)): ?>
					<?php
					if (class_exists('DBW\ImmoSuite\Frontend\EnergyRenderer')) {
						echo \DBW\ImmoSuite\Frontend\EnergyRenderer::render_single_scale($id);
					}
					?>
					<?php
				endif; ?>

				<?php if (!empty($floor_plans)): ?>
					<div class="dbw-section" id="dbw-floorplans">
						<h3 class="dbw-section-title"><?php esc_html_e('Grundrisse', 'dbw-immo-suite'); ?></h3>
						<div class="dbw-gallery-grid dbw-gallery-grid--floorplans">
							<?php foreach ($floor_plans as $fp_index => $fp): ?>
								<button type="button" class="dbw-gallery-item dbw-gallery-item--floorplan" onclick="dbwLightbox.open('floorplan', <?php echo (int) $fp_index; ?>)"
									aria-label="<?php echo esc_attr(sprintf(__('Grundriss %d öffnen', 'dbw-immo-suite'), $fp_index + 1)); ?>"
									style="background-image: url(<?php echo esc_url($fp['url']); ?>);">
								</button>
								<?php
							endforeach; ?>
						</div>
					</div>
					<?php
				endif; ?>

				<?php if ($text_sonstiges): ?>
					<div class="dbw-section">
						<h3 class="dbw-section-title"><?php esc_html_e('Sonstiges', 'dbw-immo-suite'); ?></h3>
						<div class="dbw-description">
							<?php echo wp_kses_post(wpautop($text_sonstiges)); ?>
						</div>
					</div>
					<?php
				endif; ?>

			</div>

			<!-- Sidebar -->
			<aside class="dbw-sidebar">

				<!-- Highligts Box -->
				<?php
				$hl_bg_choice = get_theme_mod('dbw_immo_highlights_bg_style', 'primary');
				$hl_bg_color = ($hl_bg_choice === 'accent') ? 'var(--dbw-accent, #2ecc71)' : 'var(--dbw-primary, #0073aa)';
				$hl_text_color = get_theme_mod('dbw_immo_highlights_text_color', '#ffffff');
				?>
				<div class="dbw-highlights-card"
					style="--dbw-hl-bg: <?php echo esc_attr($hl_bg_color); ?>; --dbw-hl-text: <?php echo esc_attr($hl_text_color); ?>;">


					<h3>
						<?php esc_html_e('Highlights', 'dbw-immo-suite'); ?></h3>

					<ul>
						<?php if ($area > 0): ?>
							<li>
								<span><?php esc_html_e('Wohnfläche', 'dbw-immo-suite'); ?></span>
								<strong><?php esc_html_e('ca.', 'dbw-immo-suite'); ?> <?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($area, 'flaeche')); ?> m²</strong>
							</li>
						<?php endif; ?>

						<?php if ($rooms > 0): ?>
							<li>
								<span><?php esc_html_e('Anzahl Zimmer', 'dbw-immo-suite'); ?></span>
								<strong><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($rooms, 'zimmer')); ?></strong>
							</li>
						<?php endif; ?>

						<?php if ($bedrooms > 0): ?>
							<li>
								<span><?php esc_html_e('Anzahl Schlafzimmer', 'dbw-immo-suite'); ?></span>
								<strong><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($bedrooms, 'zimmer')); ?></strong>
							</li>
						<?php endif; ?>

						<?php if ($bathrooms > 0): ?>
							<li>
								<span><?php esc_html_e('Anzahl Badezimmer', 'dbw-immo-suite'); ?></span>
								<strong><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($bathrooms, 'zimmer')); ?></strong>
							</li>
						<?php endif; ?>

						<?php
						$energy_class_hl = $energy_class;
						if (!empty($energy_class_hl)):
							?>
							<li class="dbw-highlights-energy">
								<span><?php esc_html_e('Energieklasse', 'dbw-immo-suite'); ?></span>
								<?php
								if (class_exists('DBW\ImmoSuite\Frontend\EnergyRenderer')) {
									\DBW\ImmoSuite\Frontend\EnergyRenderer::render_archive_flag(get_the_ID());
								}
								?>
							</li>
						<?php endif; ?>

						<?php if ($price_kauf > 0): ?>
							<!-- KAUF -->
							<li>
								<span><?php esc_html_e('Kaufpreis', 'dbw-immo-suite'); ?></span>
								<strong><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($price_kauf, 'preis')); ?> €</strong>
							</li>
							<?php if ($hausgeld > 0): ?>
								<li>
									<span><?php esc_html_e('Hausgeld', 'dbw-immo-suite'); ?></span>
									<strong><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($hausgeld, 'preis')); ?> €</strong>
								</li>
							<?php endif; ?>
							<?php if ($provision): ?>
								<li class="dbw-highlights-provision">
									<span><?php esc_html_e('Käuferprovision', 'dbw-immo-suite'); ?></span>
									<strong><?php
									echo esc_html($provision);
									// Optionale Prüfung, falls XML nur "3,57%" schickt ohne "inkl. MwSt"
									if (strpos($provision, 'MwSt') === false && strpos($provision, '%') !== false) {
										echo ' ' . esc_html__('inkl. ges. MwSt.', 'dbw-immo-suite');
									}
									?></strong>
								</li>
							<?php endif; ?>

						<?php elseif ($price_miete > 0): ?>
							<!-- MIETE -->
							<li>
								<span><?php esc_html_e('Kaltmiete', 'dbw-immo-suite'); ?></span>
								<strong><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($price_miete, 'preis')); ?> €</strong>
							</li>
							<?php if ($nebenkosten > 0): ?>
								<li>
									<span><?php esc_html_e('Nebenkosten', 'dbw-immo-suite'); ?></span>
									<strong><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($nebenkosten, 'preis')); ?> €</strong>
								</li>
							<?php endif; ?>
							<?php if ($price_warm > 0): ?>
								<li>
									<span><?php esc_html_e('Warmmiete', 'dbw-immo-suite'); ?></span>
									<strong><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($price_warm, 'preis')); ?> €</strong>
								</li>
							<?php endif; ?>

						<?php else: ?>
							<!-- AUF ANFRAGE -->
							<li class="dbw-highlights-request">
								<span><?php esc_html_e('Preis', 'dbw-immo-suite'); ?></span>
								<strong><?php esc_html_e('Auf Anfrage', 'dbw-immo-suite'); ?></strong>
							</li>
						<?php endif; ?>
					</ul>

					<?php if (get_theme_mod('dbw_immo_single_show_contact', true)): ?>
						<!-- CTA inside the sticky box: price + action in one glance -->
						<button type="button"
								class="dbw-cta dbw-cta--invert dbw-highlights-cta"
								data-dbw-open-modal="<?php echo esc_attr($id); ?>">
							<?php esc_html_e('Immobilie anfragen', 'dbw-immo-suite'); ?>
						</button>
					<?php endif; ?>
				</div>

				<?php
				// Price per m² comparison widget
				if (get_theme_mod('dbw_immo_single_show_price_sqm', true) && class_exists('DBW\ImmoSuite\Frontend\PriceComparison')) {
					\DBW\ImmoSuite\Frontend\PriceComparison::render_single($id);
				}
				?>

				<div class="dbw-agent-card">
					<?php if (get_theme_mod('dbw_immo_single_show_contact', true)): ?>
						<!-- Contact Person -->
						<h4><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
							__('Ihr Ansprechpartner', 'dbw-immo-suite'),
							__('Dein Ansprechpartner', 'dbw-immo-suite')
						)); ?></h4>

						<div class="dbw-contact-flex">
							<?php if ($contact_img_url): ?>
								<img src="<?php echo esc_url($contact_img_url); ?>" alt="<?php echo esc_attr($contact_name); ?>"
									width="60" height="60" loading="lazy"
									class="dbw-contact-avatar">
								<?php
							else: ?>
								<div class="dbw-contact-avatar-placeholder">
									<span class="dbw-icon" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
								</div>
								<?php
							endif; ?>

							<div>
								<div class="dbw-contact-name">
									<?php echo esc_html($contact_firstname . ' ' . $contact_name); ?>
								</div>
								<?php if ($contact_tel): ?>
									<?php $phone = \DBW\ImmoSuite\dbw_format_phone($contact_tel); ?>
									<div class="dbw-contact-tel">
										<a href="tel:<?php echo esc_attr($phone['tel']); ?>" class="dbw-phone-link"><?php echo esc_html($phone['display']); ?></a>
									</div>
									<?php
								endif; ?>
							</div>
						</div>

						<?php
						// Render CTA buttons (open multi-step modal)
						if (class_exists('DBW\ImmoSuite\Frontend\ContactModal')) {
							\DBW\ImmoSuite\Frontend\ContactModal::render_cta_buttons($id);
						} elseif ($contact_email) {
							// Fallback: simple mailto link
							echo '<a href="mailto:' . esc_attr($contact_email) . '?subject=' . esc_attr(__('Anfrage:', 'dbw-immo-suite')) . ' ' . rawurlencode(get_the_title()) . '" class="button button-primary dbw-mailto-fallback">' . esc_html__('Anfrage senden', 'dbw-immo-suite') . '</a>';
						}
						?>
						<?php
					endif; ?>
				</div>
			</aside>

		</div>

		<?php
	endwhile; ?>

	<?php
	// Similar Properties — use $id from the main loop (get_the_ID() is unreliable after endwhile)
	if (get_theme_mod('dbw_immo_single_show_similar', true)) {
		$terms = wp_get_post_terms($id, 'objektart', array('fields' => 'ids'));
		$vermarktung = wp_get_post_terms($id, 'vermarktungsart', array('fields' => 'ids'));

		// Try progressively broader queries: full match → objektart only → any recent
		$similar_query = null;
		$attempts = array();

		// Attempt 1: Match both objektart + vermarktungsart
		if (!empty($terms) && !is_wp_error($terms) && !empty($vermarktung) && !is_wp_error($vermarktung)) {
			$attempts[] = array(
				'tax_query' => array(
					'relation' => 'AND',
					array('taxonomy' => 'objektart', 'field' => 'term_id', 'terms' => $terms),
					array('taxonomy' => 'vermarktungsart', 'field' => 'term_id', 'terms' => $vermarktung),
				),
			);
		}
		// Attempt 2: Only objektart
		if (!empty($terms) && !is_wp_error($terms)) {
			$attempts[] = array(
				'tax_query' => array(
					array('taxonomy' => 'objektart', 'field' => 'term_id', 'terms' => $terms),
				),
			);
		}
		// Attempt 3: Any recent
		$attempts[] = array('orderby' => 'date', 'order' => 'DESC');

		$base_args = array(
			'post_type'      => 'immobilie',
			'posts_per_page' => 3,
			'post__not_in'   => array($id),
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
			// no 'fields' => 'ids' — it would skip WP's meta/term cache priming
			// and cost ~10 extra queries when the cards render
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
			'no_found_rows'  => true,
		);

		foreach ($attempts as $attempt_args) {
			$similar_query = new \WP_Query(array_merge($base_args, $attempt_args));
			if ($similar_query->have_posts()) {
				break;
			}
		}

		if ($similar_query && $similar_query->have_posts()) {
			?>
			<div class="dbw-similar-properties">
				<h3 class="dbw-section-title"><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
					__('Das könnte Sie auch interessieren', 'dbw-immo-suite'),
					__('Das könnte dich auch interessieren', 'dbw-immo-suite')
				)); ?>
				</h3>
				<div class="dbw-property-grid">
					<?php
					while ($similar_query->have_posts()) {
						$similar_query->the_post();
						\DBW\ImmoSuite\Frontend\CardRenderer::render(array('show_meta_labels' => false));
					}
					?>
				</div>
			</div>
			<?php
			wp_reset_postdata();
		}
	}
	?>

</div> <!-- End of .dbw-single-property-container -->

<!-- Lightbox Overlay -->
<div id="dbwLightboxOverlay" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Bildergalerie', 'dbw-immo-suite'); ?>"
	style="display:none;">
	<!-- Close -->
	<button onclick="dbwLightbox.close()" class="dbw-lightbox-btn dbw-lightbox-btn--close" aria-label="<?php esc_attr_e('Schliessen', 'dbw-immo-suite'); ?>">
		<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
			stroke-linecap="round" stroke-linejoin="round">
			<line x1="18" y1="6" x2="6" y2="18"></line>
			<line x1="6" y1="6" x2="18" y2="18"></line>
		</svg>
	</button>
	<!-- Prev -->
	<button id="dbwLbPrev" onclick="dbwLightbox.prev()" class="dbw-lightbox-btn" aria-label="<?php esc_attr_e('Vorheriges Bild', 'dbw-immo-suite'); ?>">
		<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
			stroke-linecap="round" stroke-linejoin="round">
			<polyline points="15 18 9 12 15 6"></polyline>
		</svg>
	</button>
	<!-- Next -->
	<button id="dbwLbNext" onclick="dbwLightbox.next()" class="dbw-lightbox-btn" aria-label="<?php esc_attr_e('Nächstes Bild', 'dbw-immo-suite'); ?>">
		<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
			stroke-linecap="round" stroke-linejoin="round">
			<polyline points="9 18 15 12 9 6"></polyline>
		</svg>
	</button>
	<!-- Image -->
	<img id="dbwLbImage" src="" alt="">
	<!-- Counter -->
	<div id="dbwLbCounter">
	</div>
</div>

<script>
	window.dbwLightboxData = {
		gallery: <?php echo wp_json_encode(array_map(function($gi) { return array('url' => $gi['full'], 'alt' => $gi['alt']); }, $gallery_images)); ?>,
		floorplans: <?php echo wp_json_encode(array_map(function($fpi) { return array('url' => $fpi['full'], 'alt' => $fpi['alt'] ?: __('Grundriss', 'dbw-immo-suite')); }, $floor_plans)); ?>
	};
</script>

<?php
get_footer();
?>