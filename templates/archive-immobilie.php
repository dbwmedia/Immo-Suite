<?php
/**
 * Template Name: Immobilien Archiv
 */

get_header(); ?>

<div id="dbw-immo-suite" class="dbw-immo-archive-container">
	<header class="page-header">
		<h1 class="page-title"><?php post_type_archive_title(); ?></h1>
	</header>

	<?php
	if (class_exists('DBW\ImmoSuite\Frontend\Filter')) {
		\DBW\ImmoSuite\Frontend\Filter::render_filter_bar();
		\DBW\ImmoSuite\Frontend\Filter::render_archive_header();
	}
	?>

	<?php if (have_posts()): ?>
		<div class="dbw-property-grid">
			<?php
			while (have_posts()):
				the_post();
				\DBW\ImmoSuite\Frontend\CardRenderer::render();
			endwhile;
			?>
		</div>

		<?php
		if (class_exists('DBW\ImmoSuite\Frontend\Filter')) {
			\DBW\ImmoSuite\Frontend\Filter::pagination();
		} else {
			the_posts_pagination();
		}
		?>

	<?php else: ?>
		<div class="dbw-property-grid">
			<?php
			if (class_exists('DBW\ImmoSuite\Frontend\Filter')) {
				echo \DBW\ImmoSuite\Frontend\Filter::render_empty_state(); // phpcs:ignore -- escaped internally
			} else {
				echo '<p class="dbw-no-results">' . esc_html__('Keine Immobilien gefunden.', 'dbw-immo-suite') . '</p>';
			}
			?>
		</div>
	<?php endif; ?>

	<?php
	// Map view container — rendered outside the have_posts() branch so the
	// view switcher and favorites view keep working on empty result pages
	if (class_exists('DBW\ImmoSuite\Frontend\ArchiveMap')) {
		\DBW\ImmoSuite\Frontend\ArchiveMap::render();
	}
	?>

	<script>
	/* Apply the saved view synchronously — waiting for DOMContentLoaded
	   flashes the grid before switching to list/map (FOUC). */
	(function () {
		try {
			var v = localStorage.getItem('dbw_immo_view');
			if (!v || v === 'grid') return;
			var g = document.querySelector('#dbw-immo-suite .dbw-property-grid');
			if (!g) return;
			if (v === 'list') {
				g.classList.add('is-list-view');
			} else if (v === 'map') {
				var mw = document.getElementById('dbw-archive-map-wrapper');
				if (mw) { g.hidden = true; mw.hidden = false; }
			}
		} catch (e) {}
	})();
	</script>
</div>

<?php
get_footer();
