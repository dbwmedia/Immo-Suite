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

		<!-- Header -->
		<div class="dbw-single-header">
			<h1 class="dbw-single-title">
				<?php the_title(); ?>
			</h1>
			<div class="dbw-single-address">
				<span class="dashicons dashicons-location"></span>
				<?php echo esc_html(implode(' ', array_filter(array($street, $house_num))) . ', ' . implode(' ', array_filter(array($plz, $city)))); ?>
			</div>
		</div>

		<!-- Gallery Slider -->
		<?php if (!empty($gallery_images) && get_theme_mod('dbw_immo_single_show_gallery', true)): ?>
			<div class="dbw-gallery-wrapper">

				<!-- Floating Buttons - Top Left -->
				<a href="<?php echo esc_url(get_post_type_archive_link('immobilie')); ?>" class="dbw-gallery-btn"
					aria-label="<?php esc_attr_e('Zurueck zur Uebersicht', 'dbw-immo-suite'); ?>"
					style="position: absolute; top: 20px; left: 20px; z-index: 1; display:flex; align-items:center; justify-content:center; width:48px; height:48px; border-radius:50%; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-decoration: none;">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
						stroke-linecap="round" stroke-linejoin="round">
						<line x1="19" y1="12" x2="5" y2="12"></line>
						<polyline points="12 19 5 12 12 5"></polyline>
					</svg>
				</a>

				<!-- Floating Buttons - Top Right -->
				<div style="position: absolute; top: 20px; right: 20px; z-index: 1; display:flex; gap: 10px;">
					<?php if (get_theme_mod('dbw_immo_single_show_share', true)): ?>
						<button
							onclick="navigator.share ? navigator.share({title: document.title, url: window.location.href}).catch(console.error) : alert('Ihr Browser unterstützt diese Funktion leider nicht direkt. Bitte kopieren Sie die URL.')"
							class="dbw-gallery-btn"
							aria-label="<?php esc_attr_e('Teilen', 'dbw-immo-suite'); ?>"
							style="display:flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:50%; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border: none; cursor:pointer; padding:0;">
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
							class="dbw-gallery-btn"
							aria-label="<?php esc_attr_e('Expose als PDF herunterladen', 'dbw-immo-suite'); ?>"
							title="<?php esc_attr_e('Expose als PDF', 'dbw-immo-suite'); ?>"
							style="display:flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:50%; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border: none; cursor:pointer; padding:0; text-decoration:none;">
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
					<a href="#dbw-floorplans" class="dbw-gallery-btn"
						style="position: absolute; bottom: 100px; left: 20px; ; display:flex; align-items:center; gap: 8px; padding: 6px 12px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); font-weight: 700; font-size: 0.9rem; text-decoration: none;">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
							stroke-linecap="round" stroke-linejoin="round">
							<path
								d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48">
							</path>
						</svg>
						Grundrisse & Dokumente
					</a>
				<?php endif; ?>

				<!-- Main Slider -->
				<div class="dbw-gallery-slider" id="dbwGallerySlider">
					<?php foreach ($gallery_images as $index => $img): ?>
						<button type="button" class="dbw-gallery-slide" id="slide-<?php echo $index; ?>"
							onclick="dbwLightbox.open('gallery', <?php echo $index; ?>)"
							aria-label="<?php echo esc_attr(sprintf(__('Bild %d in Lightbox oeffnen', 'dbw-immo-suite'), $index + 1)); ?>">
							<img src="<?php echo esc_url($img['url']); ?>"
								alt="<?php echo esc_attr($img['alt'] ?: get_the_title() . ' — Bild ' . ($index + 1)); ?>"
								<?php if ($img['srcset']): ?>srcset="<?php echo esc_attr($img['srcset']); ?>"<?php endif; ?>
								sizes="(max-width: 768px) 100vw, 800px"
								<?php if ($img['width'] && $img['height']): ?>width="<?php echo esc_attr($img['width']); ?>" height="<?php echo esc_attr($img['height']); ?>"<?php endif; ?>
								<?php echo ($index > 0) ? 'loading="lazy" decoding="async"' : 'fetchpriority="high"'; ?>>
							<?php if ($img['alt']): ?>
								<div
									style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.7)); color: white; padding: 20px; font-size: 0.9rem;">
									<?php echo esc_html($img['alt']); ?>
								</div>
								<?php
							endif; ?>
						</button>
						<?php
					endforeach; ?>
				</div>

				<!-- Navigation Buttons -->
				<button class="dbw-gallery-nav dbw-gallery-nav--prev" aria-label="<?php esc_attr_e('Vorheriges Bild', 'dbw-immo-suite'); ?>" onclick="document.getElementById('dbwGallerySlider').scrollBy({left: -600, behavior: 'smooth'})">&#10094;</button>
				<button class="dbw-gallery-nav dbw-gallery-nav--next" aria-label="<?php esc_attr_e('Nächstes Bild', 'dbw-immo-suite'); ?>" onclick="document.getElementById('dbwGallerySlider').scrollBy({left: 600, behavior: 'smooth'})">&#10095;</button>

				<!-- Thumbnails Strip -->
				<div class="dbw-gallery-thumbs">
					<?php foreach ($gallery_images as $index => $img): ?>
						<button type="button" class="dbw-gallery-thumb" onclick="document.getElementById('slide-<?php echo $index; ?>').scrollIntoView({behavior: 'smooth', block: 'nearest'})" aria-label="<?php echo esc_attr(sprintf(__('Bild %d anzeigen', 'dbw-immo-suite'), $index + 1)); ?>">
							<?php echo wp_get_attachment_image($img['id'], 'thumbnail', false, array(
								'loading' => 'lazy',
								'decoding' => 'async',
								'alt' => $img['alt'] ?: get_the_title() . ' — Bild ' . ($index + 1),
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
				<div class="dbw-features-list" style="margin-bottom: 2rem;">
					<?php if ($area > 0): ?>
						<div class="dbw-feature-item">
							<span class="dbw-meta-label">Wohnfläche</span><br>
							<span class="dbw-meta-value">
								<?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($area, 'flaeche')); ?> m²
							</span>
						</div>
						<?php
					endif; ?>

					<?php if ($rooms > 0): ?>
						<div class="dbw-feature-item">
							<span class="dbw-meta-label">Zimmer</span><br>
							<span class="dbw-meta-value">
								<?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($rooms, 'zimmer')); ?>
							</span>
						</div>
						<?php
					endif; ?>

					<?php if ($land_area > 0): ?>
						<div class="dbw-feature-item">
							<span class="dbw-meta-label">Grundstück</span><br>
							<span class="dbw-meta-value">
								<?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($land_area, 'flaeche')); ?> m²
							</span>
						</div>
						<?php
					endif; ?>

					<?php if ($use_area > 0): ?>
						<div class="dbw-feature-item">
							<span class="dbw-meta-label">Nutzfläche</span><br>
							<span class="dbw-meta-value">
								<?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($use_area, 'flaeche')); ?> m²
							</span>
						</div>
						<?php
					endif; ?>
				</div>

				<div class="dbw-section">
					<h3 class="dbw-section-title">Beschreibung</h3>
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
						<h3 class="dbw-section-title">Ausstattung</h3>

						<?php if (!empty($features)): ?>
						<div class="dbw-features-badges" style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:1.5rem;">
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

				<?php if (($text_lage || ($lat && $lng)) && get_theme_mod('dbw_immo_single_show_map', true)): ?>
					<div class="dbw-section">
						<h3 class="dbw-section-title">Lage</h3>
						<?php if ($text_lage): ?>
						<div class="dbw-description">
							<?php echo wp_kses_post(wpautop($text_lage)); ?>
						</div>
						<?php endif; ?>

						<?php if ($lat && $lng): ?>
						<div id="dbw-map"></div>
						<?php
						wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
						wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
						wp_add_inline_script('leaflet', sprintf(
							'(function(){var m=L.map("dbw-map",{scrollWheelZoom:false}).setView([%s,%s],14);L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{attribution:"&copy; <a href=\'https://www.openstreetmap.org/copyright\'>OpenStreetMap</a>",maxZoom:18}).addTo(m);L.marker([%s,%s]).addTo(m);})();',
							esc_js($lat), esc_js($lng), esc_js($lat), esc_js($lng)
						));
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
					<div class="dbw-section" id="dbw-floorplans" style="scroll-margin-top: 100px;">
						<h3 class="dbw-section-title">Grundrisse</h3>
						<div class="dbw-gallery-grid"
							style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); height:auto; gap:1rem;">
							<?php foreach ($floor_plans as $fp_index => $fp): ?>
								<button type="button" class="dbw-gallery-item" onclick="dbwLightbox.open('floorplan', <?php echo $fp_index; ?>)"
									aria-label="<?php echo esc_attr(sprintf(__('Grundriss %d oeffnen', 'dbw-immo-suite'), $fp_index + 1)); ?>"
									style="height:200px; display:block; background-image: url(<?php echo esc_url($fp['url']); ?>); background-size: contain; background-repeat:no-repeat; background-position: center; border:1px solid #ddd; cursor: pointer; border-radius: var(--dbw-radius, 8px); transition: box-shadow 0.2s; width:100%; padding:0;">
								</button>
								<?php
							endforeach; ?>
						</div>
					</div>
					<?php
				endif; ?>

				<?php if ($text_sonstiges): ?>
					<div class="dbw-section">
						<h3 class="dbw-section-title">Sonstiges</h3>
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
				<style>
					.dbw-highlights-card ul li span,
					.dbw-highlights-card ul li strong {
						color:
							<?php echo esc_attr($hl_text_color); ?>
							!important;
					}
				</style>
				<div class="dbw-highlights-card"
					style="position: relative; background-color: <?php echo esc_attr($hl_bg_color); ?>; color: <?php echo esc_attr($hl_text_color); ?>; border-radius: var(--dbw-radius, 12px); padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.15); overflow: hidden;">


					<h3
						style="color: <?php echo esc_attr($hl_text_color); ?>; margin-top: 0; margin-bottom: 1.8rem; font-size: 1.6rem; letter-spacing: -0.5px; border-bottom: 2px solid rgba(255,255,255,0.15); padding-bottom: 10px;">
						Highlights</h3>

					<ul style="list-style: none; padding: 0; margin: 0;">
						<?php if ($area > 0): ?>
							<li
								style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.2); padding: 8px 0; margin-bottom: 4px;">
								<span>Wohnfläche</span>
								<strong>ca. <?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($area, 'flaeche')); ?> m²</strong>
							</li>
						<?php endif; ?>

						<?php if ($rooms > 0): ?>
							<li
								style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.2); padding: 8px 0; margin-bottom: 4px;">
								<span>Anzahl Zimmer</span>
								<strong><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($rooms, 'zimmer')); ?></strong>
							</li>
						<?php endif; ?>

						<?php if ($bedrooms > 0): ?>
							<li
								style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.2); padding: 8px 0; margin-bottom: 4px;">
								<span>Anzahl Schlafzimmer</span>
								<strong><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($bedrooms, 'zimmer')); ?></strong>
							</li>
						<?php endif; ?>

						<?php if ($bathrooms > 0): ?>
							<li
								style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.2); padding: 8px 0; margin-bottom: 4px;">
								<span>Anzahl Badezimmer</span>
								<strong><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($bathrooms, 'zimmer')); ?></strong>
							</li>
						<?php endif; ?>

						<?php
						$energy_class_hl = get_post_meta(get_the_ID(), 'energiepass_wertklasse', true);
						if (!empty($energy_class_hl)):
							?>
							<li class="dbw-highlights-energy"
								style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.15); padding: 10px 0; margin-bottom: 4px;">
								<span>Energieklasse</span>
								<?php
								if (class_exists('DBW\ImmoSuite\Frontend\EnergyRenderer')) {
									\DBW\ImmoSuite\Frontend\EnergyRenderer::render_archive_flag(get_the_ID());
								}
								?>
							</li>
						<?php endif; ?>

						<?php if ($price_kauf > 0): ?>
							<!-- KAUF -->
							<li
								style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.2); padding: 8px 0; margin-bottom: 4px;">
								<span>Kaufpreis</span>
								<strong><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($price_kauf, 'preis')); ?> €</strong>
							</li>
							<?php if ($hausgeld > 0): ?>
								<li
									style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.2); padding: 8px 0; margin-bottom: 4px;">
									<span>Hausgeld</span>
									<strong><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($hausgeld, 'preis')); ?> €</strong>
								</li>
							<?php endif; ?>
							<?php if ($provision): ?>
								<li
									style="display: flex; flex-direction: column; border-bottom: 1px solid rgba(255,255,255,0.2); padding: 8px 0; margin-bottom: 4px;">
									<span style="display: block; margin-bottom: 4px;">Käuferprovision</span>
									<strong><?php
									echo esc_html($provision);
									// Optionale Prüfung, falls XML nur "3,57%" schickt ohne "inkl. MwSt"
									if (strpos($provision, 'MwSt') === false && strpos($provision, '%') !== false) {
										echo ' inkl. ges. MwSt.';
									}
									?></strong>
								</li>
							<?php endif; ?>

						<?php elseif ($price_miete > 0): ?>
							<!-- MIETE -->
							<li
								style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.2); padding: 8px 0; margin-bottom: 4px;">
								<span>Kaltmiete</span>
								<strong><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($price_miete, 'preis')); ?> €</strong>
							</li>
							<?php if ($nebenkosten > 0): ?>
								<li
									style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.2); padding: 8px 0; margin-bottom: 4px;">
									<span>Nebenkosten</span>
									<strong><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($nebenkosten, 'preis')); ?> €</strong>
								</li>
							<?php endif; ?>
							<?php if ($price_warm > 0): ?>
								<li
									style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.2); padding: 8px 0; margin-bottom: 4px;">
									<span>Warmmiete</span>
									<strong><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($price_warm, 'preis')); ?> €</strong>
								</li>
							<?php endif; ?>

						<?php else: ?>
							<!-- AUF ANFRAGE -->
							<li
								style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.15); padding: 10px 0; margin-bottom: 4px;">
								<span>Preis</span>
								<strong style="font-size: 1.1em;">Auf Anfrage</strong>
							</li>
						<?php endif; ?>
					</ul>
				</div>

				<div class="dbw-agent-card"
					style="background-color: transparent; border: 1px solid rgba(0,0,0,0.08); border-radius: var(--dbw-radius, 12px); padding: 1.5rem;">
					<?php if (get_theme_mod('dbw_immo_single_show_contact', true)): ?>
						<!-- Contact Person -->
						<h4 style="margin-top: 0; margin-bottom: 1rem;"><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
							__('Ihr Ansprechpartner', 'dbw-immo-suite'),
							__('Dein Ansprechpartner', 'dbw-immo-suite')
						)); ?></h4>

						<div class="dbw-contact-flex"
							style="display: flex; align-items: center; gap: 15px; margin-bottom: 1.5rem;">
							<?php if ($contact_img_url): ?>
								<img src="<?php echo esc_url($contact_img_url); ?>" alt="<?php echo esc_attr($contact_name); ?>"
									style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">
								<?php
							else: ?>
								<div
									style="width: 60px; height: 60px; border-radius: 50%; background: #eee; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #ccc;">
									<span class="dashicons dashicons-admin-users"></span>
								</div>
								<?php
							endif; ?>

							<div>
								<div style="font-weight: bold;">
									<?php echo esc_html($contact_firstname . ' ' . $contact_name); ?>
								</div>
								<?php if ($contact_tel): ?>
									<?php $phone = \DBW\ImmoSuite\dbw_format_phone($contact_tel); ?>
									<div style="font-size: 0.9rem; color: #666;">
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
							echo '<a href="mailto:' . esc_attr($contact_email) . '?subject=Anfrage: ' . rawurlencode(get_the_title()) . '" class="button button-primary" style="display:block; width:100%; padding:12px; height:auto; font-size:1rem; text-transform:uppercase; font-weight:bold; background-color:var(--dbw-accent,#0073aa); border:none; color:#fff; cursor:pointer; border-radius:4px; text-decoration:none; text-align:center;">Anfrage senden</a>';
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
			'fields'         => 'ids',
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
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
					__('Das koennte Sie auch interessieren', 'dbw-immo-suite'),
					__('Das koennte dich auch interessieren', 'dbw-immo-suite')
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
	style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.92); z-index:99999; align-items:center; justify-content:center; flex-direction:column;">
	<!-- Close -->
	<button onclick="dbwLightbox.close()" aria-label="<?php esc_attr_e('Schliessen', 'dbw-immo-suite'); ?>"
		style="position:absolute; top:20px; right:20px; background:none; border:none; color:#fff; font-size:2rem; cursor:pointer; z-index:100001; width:48px; height:48px; display:flex; align-items:center; justify-content:center; border-radius:50%; transition: background 0.2s;"
		onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='none'">
		<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
			stroke-linecap="round" stroke-linejoin="round">
			<line x1="18" y1="6" x2="6" y2="18"></line>
			<line x1="6" y1="6" x2="18" y2="18"></line>
		</svg>
	</button>
	<!-- Prev -->
	<button id="dbwLbPrev" onclick="dbwLightbox.prev()" aria-label="<?php esc_attr_e('Vorheriges Bild', 'dbw-immo-suite'); ?>"
		style="position:absolute; left:15px; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.1); backdrop-filter:blur(4px); border:none; color:#fff; width:48px; height:48px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; z-index:100001; transition: background 0.2s;"
		onmouseover="this.style.background='rgba(255,255,255,0.2)'"
		onmouseout="this.style.background='rgba(255,255,255,0.1)'">
		<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
			stroke-linecap="round" stroke-linejoin="round">
			<polyline points="15 18 9 12 15 6"></polyline>
		</svg>
	</button>
	<!-- Next -->
	<button id="dbwLbNext" onclick="dbwLightbox.next()" aria-label="<?php esc_attr_e('Nächstes Bild', 'dbw-immo-suite'); ?>"
		style="position:absolute; right:15px; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.1); backdrop-filter:blur(4px); border:none; color:#fff; width:48px; height:48px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; z-index:100001; transition: background 0.2s;"
		onmouseover="this.style.background='rgba(255,255,255,0.2)'"
		onmouseout="this.style.background='rgba(255,255,255,0.1)'">
		<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
			stroke-linecap="round" stroke-linejoin="round">
			<polyline points="9 18 15 12 9 6"></polyline>
		</svg>
	</button>
	<!-- Image -->
	<img id="dbwLbImage" src="" alt=""
		style="max-width:90vw; max-height:85vh; object-fit:contain; border-radius:4px; user-select:none; transition: opacity 0.25s ease;">
	<!-- Counter -->
	<div id="dbwLbCounter"
		style="position:absolute; bottom:20px; left:50%; transform:translateX(-50%); color:rgba(255,255,255,0.7); font-size:0.9rem; font-family:inherit;">
	</div>
</div>

<script>
	window.dbwLightboxData = {
		gallery: <?php echo wp_json_encode(array_map(function($gi) { return $gi['full']; }, $gallery_images)); ?>,
		floorplans: <?php echo wp_json_encode(array_map(function($fpi) { return $fpi['full']; }, $floor_plans)); ?>
	};
</script>

<?php
get_footer();
?>