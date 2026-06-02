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

		// 1. Fetch Data
		// Meta (Pricing, Areas)
		$price_kauf = get_post_meta($id, 'kaufpreis', true);
		$price_miete = get_post_meta($id, 'kaltmiete', true);
		$price_warm = get_post_meta($id, 'warmmiete', true);
		$hausgeld = get_post_meta($id, 'hausgeld', true);
		$nebenkosten = get_post_meta($id, 'nebenkosten', true);
		$provision = get_post_meta($id, 'provision_kaeufer', true);

		$area = get_post_meta($id, 'wohnflaeche', true);
		$use_area = get_post_meta($id, 'nutzflaeche', true);
		$land_area = get_post_meta($id, 'grundstuecksflaeche', true);
		$rooms = get_post_meta($id, 'anzahl_zimmer', true);
		$bedrooms = get_post_meta($id, 'anzahl_schlafzimmer', true);
		$bathrooms = get_post_meta($id, 'anzahl_badezimmer', true);
		$parking = get_post_meta($id, 'anzahl_stellplaetze', true);

		// Geo
		$plz = get_post_meta($id, 'plz', true);
		$city = get_post_meta($id, 'ort', true);
		$street = get_post_meta($id, 'strasse', true);
		$house_num = get_post_meta($id, 'hausnummer', true);
		$lat = get_post_meta($id, 'geo_breite', true);
		$lng = get_post_meta($id, 'geo_laenge', true);

		// Texts
		$text_lage = get_post_meta($id, 'text_lage', true);
		$text_ausstattung = get_post_meta($id, 'text_ausstattung', true);
		$text_sonstiges = get_post_meta($id, 'text_sonstiges', true);

		// Energy
		$energy_pass_art = get_post_meta($id, 'energiepass_art', true);
		$energy_end = get_post_meta($id, 'energiepass_endenergie', true);
		$energy_class = get_post_meta($id, 'energiepass_wertklasse', true);
		$energy_source = get_post_meta($id, 'energiepass_traeger', true);
		$energy_valid = get_post_meta($id, 'energiepass_gueltig_bis', true);
		$energy_year = get_post_meta($id, 'energiepass_baujahr', true);

		// Contact Person
		$contact_name = get_post_meta($id, 'kontaktperson_name', true);
		$contact_firstname = get_post_meta($id, 'kontaktperson_vorname', true);
		$contact_email = get_post_meta($id, 'kontaktperson_email', true);
		$contact_tel = get_post_meta($id, 'kontaktperson_tel', true);
		$contact_img_url = get_post_meta($id, 'kontaktperson_bild_url', true);

		// Gallery & Attachments Processing
		$raw_attachments = get_attached_media('image', $id);
		$contact_img_id = get_post_meta($id, 'kontaktperson_bild_id', true);

		$gallery_images = array();
		$floor_plans = array();

		$seen_urls = array();

		foreach ($raw_attachments as $att_id => $att_post) {
			$img_url = wp_get_attachment_image_url($att_id, 'large');

			// 1. Skip if no URL
			if (!$img_url)
				continue;

			// 2. Skip Contact Image (ID check match OR URL match)
			if ($contact_img_id && (int) $att_id === (int) $contact_img_id) {
				continue;
			}
			if ($contact_img_url && $img_url === $contact_img_url) {
				continue;
			}

			// 3. Dedup by URL (prevent same image appearing multiple times due to bad imports)
			if (in_array($img_url, $seen_urls)) {
				continue;
			}
			$seen_urls[] = $img_url;

			$group = get_post_meta($att_id, '_openimmo_gruppe', true);
			$img_url = wp_get_attachment_image_url($att_id, 'large');
			$full_url = wp_get_attachment_image_url($att_id, 'full');
			$alt = get_post_meta($att_id, '_wp_attachment_image_alt', true);

			$item = array(
				'id' => $att_id,
				'url' => $img_url,
				'full' => $full_url,
				'alt' => $alt
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
			<style>
				.dbw-gallery-btn {
					background: rgba(255, 255, 255, 0.95);
					backdrop-filter: blur(4px);
					transition: all 0.3s ease;
					color: #333;
				}

				.dbw-gallery-btn:hover {
					background: #ffffff;
					transform: scale(1.05);
					box-shadow: 0 6px 14px rgba(0, 0, 0, 0.15);
					color: var(--dbw-primary);
				}
			</style>
			<div class="dbw-gallery-wrapper" style="position: relative; margin-bottom: 2rem;">

				<!-- Floating Buttons - Top Left -->
				<a href="<?php echo esc_url(get_post_type_archive_link('immobilie')); ?>" class="dbw-gallery-btn"
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
						<button onclick="window.print()" class="dbw-gallery-btn"
							style="display:flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:50%; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border: none; cursor:pointer; padding:0;">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
								stroke-linecap="round" stroke-linejoin="round">
								<polyline points="6 9 6 2 18 2 18 9"></polyline>
								<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
								<rect x="6" y="14" width="12" height="8"></rect>
							</svg>
						</button>
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
						<div class="dbw-gallery-slide" id="slide-<?php echo $index; ?>"
							onclick="dbwLightbox.open('gallery', <?php echo $index; ?>)">
							<img src="<?php echo esc_url($img['full']); ?>" alt="<?php echo esc_attr($img['alt'] ?: get_the_title() . ' — Bild ' . ($index + 1)); ?>"
								<?php echo ($index > 0) ? 'loading="lazy"' : ''; ?>>
							<?php if ($img['alt']): ?>
								<div
									style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.7)); color: white; padding: 20px; font-size: 0.9rem;">
									<?php echo esc_html($img['alt']); ?>
								</div>
								<?php
							endif; ?>
						</div>
						<?php
					endforeach; ?>
				</div>

				<!-- Navigation Buttons -->
				<button class="dbw-gallery-nav dbw-gallery-nav--prev" aria-label="<?php esc_attr_e('Vorheriges Bild', 'dbw-immo-suite'); ?>" onclick="document.getElementById('dbwGallerySlider').scrollBy({left: -600, behavior: 'smooth'})">&#10094;</button>
				<button class="dbw-gallery-nav dbw-gallery-nav--next" aria-label="<?php esc_attr_e('Nächstes Bild', 'dbw-immo-suite'); ?>" onclick="document.getElementById('dbwGallerySlider').scrollBy({left: 600, behavior: 'smooth'})">&#10095;</button>

				<!-- Thumbnails Strip -->
				<div class="dbw-gallery-thumbs">
					<?php foreach ($gallery_images as $index => $img): ?>
						<div class="dbw-gallery-thumb" onclick="document.getElementById('slide-<?php echo $index; ?>').scrollIntoView({behavior: 'smooth', block: 'nearest'})">
							<img src="<?php echo esc_url($img['url']); ?>" loading="lazy" alt="<?php echo esc_attr($img['alt'] ?: get_the_title() . ' — Bild ' . ($index + 1)); ?>">
						</div>
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
								<?php echo wpautop(esc_html($text_ausstattung)); ?>
							<?php endif; ?>
						</div>
					</div>
			<?php endif; ?>

				<?php if (($text_lage || ($lat && $lng)) && get_theme_mod('dbw_immo_single_show_map', true)): ?>
					<div class="dbw-section">
						<h3 class="dbw-section-title">Lage</h3>
						<?php if ($text_lage): ?>
						<div class="dbw-description">
							<?php echo wpautop(esc_html($text_lage)); ?>
						</div>
						<?php endif; ?>

						<?php if ($lat && $lng): ?>
						<div id="dbw-map"></div>
						<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
						<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
						<script>
						(function() {
							var map = L.map('dbw-map', { scrollWheelZoom: false }).setView([<?php echo esc_js($lat); ?>, <?php echo esc_js($lng); ?>], 14);
							L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
								attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
								maxZoom: 18
							}).addTo(map);
							L.marker([<?php echo esc_js($lat); ?>, <?php echo esc_js($lng); ?>]).addTo(map);
						})();
						</script>
						<?php endif; ?>

						<!-- Infrastructure Distances -->
						<?php
						// Helper to get all distanz_ meta
						$infra_keys = array('kindergaerten', 'grundschule', 'realschule', 'gymnasium', 'einkaufsmoeglichkeiten', 'oepnv'); // Common ones
						?>
						<h4 style="margin-top:20px; font-size:1rem; color:#666;">Entfernungen</h4>
						<ul class="dbw-infra-list">
							<?php
							// Try fetching specific keys we mapped or all meta
							$custom_fields = get_post_custom($id);
							foreach ($custom_fields as $key => $val) {
								if (strpos($key, 'distanz_') === 0) {
									$label = ucfirst(str_replace('distanz_', '', $key));
									$value = $val[0];
									if (!$value)
										continue;
									echo '<li class="dbw-infra-item"><span>' . esc_html($label) . ':</span> <strong>' . esc_html($value) . ' km</strong></li>';
								}
							}
							?>
						</ul>
					</div>
					<?php
				endif; ?>

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
								<div class="dbw-gallery-item" onclick="dbwLightbox.open('floorplan', <?php echo $fp_index; ?>)"
									style="height:200px; display:block; background-image: url(<?php echo esc_url($fp['url']); ?>); background-size: contain; background-repeat:no-repeat; background-position: center; border:1px solid #ddd; cursor: pointer; border-radius: var(--dbw-radius, 8px); transition: box-shadow 0.2s;">
								</div>
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
							<?php echo wpautop(esc_html($text_sonstiges)); ?>
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

		$args = array(
			'post_type'      => 'immobilie',
			'posts_per_page' => 3,
			'post__not_in'   => array($id),
			'post_status'    => 'publish',
			'orderby'        => 'rand',
		);

		// Build tax_query from available terms
		$tax_query = array();
		if (!empty($terms) && !is_wp_error($terms)) {
			$tax_query[] = array(
				'taxonomy' => 'objektart',
				'field'    => 'term_id',
				'terms'    => $terms,
			);
		}
		if (!empty($vermarktung) && !is_wp_error($vermarktung)) {
			$tax_query[] = array(
				'taxonomy' => 'vermarktungsart',
				'field'    => 'term_id',
				'terms'    => $vermarktung,
			);
		}
		if (count($tax_query) > 1) {
			$tax_query['relation'] = 'AND';
		}
		if (!empty($tax_query)) {
			$args['tax_query'] = $tax_query;
		}

		$similar_query = new \WP_Query($args);

		// Fallback: only objektart (drop vermarktungsart)
		if (!$similar_query->have_posts() && count($tax_query) > 1) {
			$fallback_args = $args;
			unset($fallback_args['tax_query']);
			if (!empty($terms) && !is_wp_error($terms)) {
				$fallback_args['tax_query'] = array(
					array('taxonomy' => 'objektart', 'field' => 'term_id', 'terms' => $terms),
				);
			}
			$similar_query = new \WP_Query($fallback_args);
		}

		// Last fallback: any 3 recent properties
		if (!$similar_query->have_posts()) {
			$similar_query = new \WP_Query(array(
				'post_type'      => 'immobilie',
				'posts_per_page' => 3,
				'post__not_in'   => array($id),
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
			));
		}

		if ($similar_query->have_posts()) {
			?>
			<div class="dbw-similar-properties" style="margin: 4rem auto 2rem auto; font-family: inherit; padding-top: 3rem;">
				<h3 class="dbw-section-title" style="margin-bottom: 2rem; font-size: 1.5rem;"><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
					__('Das koennte Sie auch interessieren', 'dbw-immo-suite'),
					__('Das koennte dich auch interessieren', 'dbw-immo-suite')
				)); ?>
				</h3>
				<div class="dbw-property-grid"
					style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
					<?php
					while ($similar_query->have_posts()) {
						$similar_query->the_post();
						$sim_id = get_the_ID();
						$sim_price = get_post_meta($sim_id, 'kaufpreis', true);
						if (!$sim_price)
							$sim_price = get_post_meta($sim_id, 'kaltmiete', true);
						$sim_area = get_post_meta($sim_id, 'wohnflaeche', true);
						$sim_rooms = get_post_meta($sim_id, 'anzahl_zimmer', true);
						$sim_city = get_post_meta($sim_id, 'ort', true);

						$img_url = get_the_post_thumbnail_url($sim_id, 'large');
						if (!$img_url) {
							$images = get_attached_media('image', $sim_id);
							if (!empty($images)) {
								$first = reset($images);
								$img_url = wp_get_attachment_image_url($first->ID, 'large');
							}
						}
						?>
						<a href="<?php the_permalink(); ?>" class="dbw-similar-card"
							style="display: flex; flex-direction: column; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); text-decoration: none; color: inherit; transition: transform 0.2s, box-shadow 0.2s;">
							<style>
								.dbw-similar-card:hover {
									transform: translateY(-4px);
									box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
									color: inherit !important;
								}
							</style>
							<div style="height: 200px; overflow: hidden; position: relative;">
								<div class="dbw-sim-img"
									style="width: 100%; height: 100%; background-color:#eaeaea; background-image: url('<?php echo esc_url($img_url); ?>'); background-size: cover; background-position: center; transition: transform 0.4s ease;">
								</div>
								<?php if (class_exists('DBW\ImmoSuite\Frontend\EnergyRenderer')): ?>
									<?php \DBW\ImmoSuite\Frontend\EnergyRenderer::render_archive_flag($sim_id); ?>
								<?php endif; ?>
							</div>
							<div style="padding: 1.5rem; display: flex; flex-direction: column; flex-grow: 1;">
								<h4
									style="margin: 0 0 0.5rem 0; font-size: 1.1rem; line-height: 1.4; color: #333; font-weight: 700;">
									<?php the_title(); ?>
								</h4>
								<?php if ($sim_city): ?>
									<div
										style="color: #666; font-size: 0.9rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 4px;">
										<span class="dashicons dashicons-location"
											style="font-size: 16px; width: 16px; height: 16px;"></span>
										<?php echo esc_html($sim_city); ?>
									</div>
								<?php endif; ?>

								<div
									style="display: flex; justify-content: space-between; border-top: 1px solid #f0f0f0; padding-top: 1rem; margin-top: auto;">
									<div style="display: flex; gap: 1rem; color: #555; font-size: 0.95rem;">
										<?php if ($sim_area): ?>
											<span title="Wohnfläche"><strong><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($sim_area, 'flaeche')); ?></strong> m²</span>
										<?php endif; ?>
										<?php if ($sim_rooms): ?>
											<span title="Zimmer"><strong><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($sim_rooms, 'zimmer')); ?></strong> Zi.</span>
										<?php endif; ?>
									</div>
									<?php if ($sim_price): ?>
										<strong
											style="color: var(--dbw-primary); font-size: 1.1rem;"><?php echo esc_html(\DBW\ImmoSuite\dbw_format_number($sim_price, 'preis')); ?>
											€</strong>
									<?php endif; ?>
								</div>
							</div>
						</a>
						<?php
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
	(function () {
		// Image datasets
		var galleryImages = [
			<?php if (!empty($gallery_images)): ?>
					<?php foreach ($gallery_images as $gi): ?>
								<?php echo json_encode($gi['full']); ?>,
				<?php endforeach; ?>
		<?php endif; ?>
		];
		var floorplanImages = [
			<?php if (!empty($floor_plans)): ?>
					<?php foreach ($floor_plans as $fpi): ?>
								<?php echo json_encode($fpi['full']); ?>,
				<?php endforeach; ?>
		<?php endif; ?>
		];

		var overlay = document.getElementById('dbwLightboxOverlay');
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
</script>

<?php
get_footer();
?>