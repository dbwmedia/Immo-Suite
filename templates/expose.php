<?php
/**
 * Standalone Expose Template — no theme header/footer.
 * Renders a professional A4 print-optimized property expose.
 *
 * @var array $data Collected property data from PdfExpose::collect_data()
 */

if (!defined('ABSPATH')) { exit; }

$d = $data;
$fmt = 'DBW\ImmoSuite\dbw_format_number';
$phone_fmt = 'DBW\ImmoSuite\dbw_format_phone';

// Build address string
$addr_street = trim($d['address']['strasse'] . ' ' . $d['address']['hausnummer']);
$addr_city   = trim($d['address']['plz'] . ' ' . $d['address']['ort']);
$addr_full   = implode(', ', array_filter(array($addr_street, $addr_city)));

// Determine primary price
$price_label = '';
$price_value = '';
if ($d['pricing']['kaufpreis'] > 0) {
    $price_label = 'Kaufpreis';
    $price_value = $fmt($d['pricing']['kaufpreis'], 'preis') . ' &euro;';
} elseif ($d['pricing']['kaltmiete'] > 0) {
    $price_label = 'Kaltmiete';
    $price_value = $fmt($d['pricing']['kaltmiete'], 'preis') . ' &euro;';
} else {
    $price_label = 'Preis';
    $price_value = 'Auf Anfrage';
}

// Main image
$hero_img = !empty($d['images']['gallery'][0]) ? $d['images']['gallery'][0]['url'] : '';

// Energy color map
$energy_colors = array(
    'A+' => '#188a38', 'A' => '#37a431', 'B' => '#8eb32c', 'C' => '#c6cc26',
    'D' => '#eae01c', 'E' => '#f8ca12', 'F' => '#e48325', 'G' => '#c83f2a', 'H' => '#b32822',
);
$energy_class = strtoupper(trim($d['energy']['klasse']));

// Accent color from customizer
$accent = get_theme_mod('dbw_immo_color_accent', '#2573a7');
$primary = get_theme_mod('dbw_immo_color_primary', '#2c3e50');

?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html($d['title']); ?> — Expose</title>
<style>
/* ── Reset & Base ─────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    font-size: 10pt;
    line-height: 1.5;
    color: #1a1a1a;
    background: #fff;
}
img { max-width: 100%; height: auto; display: block; }

/* ── Page Setup ───────────────────────────────── */
@page {
    size: A4 portrait;
    margin: 15mm 18mm 20mm 18mm;
}
@page :first {
    margin-top: 0;
    margin-left: 0;
    margin-right: 0;
}

/* ── Utilities ────────────────────────────────── */
.expose-page {
    page-break-after: always;
    position: relative;
    min-height: 0;
}
.expose-page:last-child {
    page-break-after: auto;
}
.avoid-break {
    page-break-inside: avoid;
}
.section {
    margin-bottom: 1.8em;
    page-break-inside: avoid;
}
.section-title {
    font-size: 13pt;
    font-weight: 700;
    color: <?php echo esc_attr($primary); ?>;
    margin-bottom: 0.6em;
    padding-bottom: 0.3em;
    border-bottom: 2px solid <?php echo esc_attr($accent); ?>;
}

/* ── Cover Page ───────────────────────────────── */
.cover {
    page-break-after: always;
}
.cover-hero {
    width: calc(100% + 36mm);
    margin-left: -18mm;
    margin-top: 0;
    height: 52vh;
    object-fit: cover;
    display: block;
}
.cover-body {
    padding: 1.5em 18mm 0 18mm;
}
.cover-badge {
    display: inline-block;
    font-size: 8pt;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #fff;
    background: <?php echo esc_attr($accent); ?>;
    padding: 3px 10px;
    border-radius: 3px;
    margin-bottom: 0.8em;
}
.cover-title {
    font-size: 20pt;
    font-weight: 800;
    line-height: 1.2;
    color: <?php echo esc_attr($primary); ?>;
    margin-bottom: 0.3em;
}
.cover-address {
    font-size: 10pt;
    color: #555;
    margin-bottom: 1.5em;
}
.cover-facts {
    display: flex;
    gap: 0;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 1.5em;
}
.cover-fact {
    flex: 1;
    padding: 12px 14px;
    border-right: 1px solid #e0e0e0;
    text-align: center;
}
.cover-fact:last-child { border-right: none; }
.cover-fact-label {
    font-size: 7pt;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #888;
    margin-bottom: 2px;
}
.cover-fact-value {
    font-size: 13pt;
    font-weight: 700;
    color: <?php echo esc_attr($primary); ?>;
}
.cover-footer {
    position: absolute;
    bottom: 20mm;
    left: 18mm;
    right: 18mm;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 12px;
    border-top: 1px solid #e0e0e0;
}
.cover-logo img {
    max-height: 36px;
    width: auto;
}
.cover-org-name {
    font-size: 10pt;
    font-weight: 700;
    color: <?php echo esc_attr($primary); ?>;
}
.cover-org-url {
    font-size: 8pt;
    color: #888;
}

/* ── Key Facts Table ──────────────────────────── */
.facts-table {
    width: 100%;
    border-collapse: collapse;
}
.facts-table td {
    padding: 6px 10px;
    border-bottom: 1px solid #eee;
    font-size: 9.5pt;
}
.facts-table td:first-child {
    color: #666;
    width: 45%;
}
.facts-table td:last-child {
    font-weight: 600;
    text-align: right;
}

/* ── Features Badges ──────────────────────────── */
.feature-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 1em;
}
.feature-badge {
    font-size: 8pt;
    padding: 3px 10px;
    border: 1px solid #ccc;
    border-radius: 20px;
    color: #444;
    background: #f9f9f9;
}

/* ── Energy Scale ─────────────────────────────── */
.energy-bar {
    display: flex;
    border-radius: 4px;
    overflow: hidden;
    height: 22px;
    margin: 8px 0;
}
.energy-segment {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 7.5pt;
    font-weight: 700;
    color: #fff;
}
.energy-active {
    outline: 2.5px solid #1a1a1a;
    outline-offset: -1px;
    z-index: 1;
    position: relative;
}

/* ── Image Grid ───────────────────────────────── */
.img-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
.img-grid img {
    width: 100%;
    height: 160px;
    object-fit: cover;
    border-radius: 4px;
    border: 1px solid #eee;
}
.img-grid-full img {
    height: 200px;
}

/* ── Contact Card ─────────────────────────────── */
.contact-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    background: #f9fafb;
}
.contact-avatar {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}
.contact-name {
    font-size: 11pt;
    font-weight: 700;
    color: <?php echo esc_attr($primary); ?>;
}
.contact-detail {
    font-size: 9pt;
    color: #555;
    margin-top: 2px;
}

/* ── Disclaimer ───────────────────────────────── */
.disclaimer {
    font-size: 7pt;
    color: #999;
    line-height: 1.4;
    border-top: 1px solid #e0e0e0;
    padding-top: 10px;
    margin-top: 2em;
}

/* ── Two Column Layout ────────────────────────── */
.two-col {
    display: flex;
    gap: 24px;
}
.two-col > .col-main { flex: 3; }
.two-col > .col-side { flex: 2; }

/* ── Print-only tweaks ────────────────────────── */
.print-trigger-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: <?php echo esc_attr($primary); ?>;
    color: #fff;
    text-align: center;
    padding: 12px;
    z-index: 9999;
    font-size: 14px;
}
.print-trigger-bar button {
    background: #fff;
    color: <?php echo esc_attr($primary); ?>;
    border: none;
    padding: 8px 24px;
    border-radius: 4px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    margin-left: 12px;
}
@media print {
    .print-trigger-bar { display: none !important; }
    .cover-hero {
        height: auto;
        max-height: 52vh;
    }
    body { font-size: 10pt; }
}
@media screen {
    body { max-width: 210mm; margin: 0 auto; padding: 20px; background: #eee; }
    .expose-page { background: #fff; padding: 18mm; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); min-height: 297mm; }
    .cover { padding: 0; }
    .cover-hero { width: 100%; margin-left: 0; }
    .cover-body { padding: 1.5em 18mm 0 18mm; }
    .cover-footer { left: 18mm; right: 18mm; }
    body { padding-bottom: 70px; }
}
</style>
</head>
<body>

<!-- Print Trigger Bar (screen only) -->
<div class="print-trigger-bar">
    <?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
        __('Expose bereit. Waehlen Sie im Druckdialog "Als PDF speichern".', 'dbw-immo-suite'),
        __('Expose bereit. Waehle im Druckdialog "Als PDF speichern".', 'dbw-immo-suite')
    )); ?>
    <button onclick="window.print()">PDF speichern / Drucken</button>
</div>

<!-- ═══════════════════════════════════════════════
     PAGE 1: COVER
     ═══════════════════════════════════════════════ -->
<div class="expose-page cover">

    <?php if ($hero_img): ?>
        <img class="cover-hero" src="<?php echo esc_url($hero_img); ?>" alt="<?php echo esc_attr($d['title']); ?>">
    <?php endif; ?>

    <div class="cover-body">
        <?php
        $badge_parts = array_filter(array($d['objektart'], $d['vermarktung']));
        if (!empty($badge_parts)):
        ?>
            <div class="cover-badge"><?php echo esc_html(implode(' &middot; ', $badge_parts)); ?></div>
        <?php endif; ?>

        <h1 class="cover-title"><?php echo esc_html($d['title']); ?></h1>

        <?php if ($addr_full): ?>
            <div class="cover-address"><?php echo esc_html($addr_full); ?></div>
        <?php endif; ?>

        <!-- Key Facts Strip -->
        <div class="cover-facts">
            <?php if ($d['areas']['wohnflaeche'] > 0): ?>
                <div class="cover-fact">
                    <div class="cover-fact-label">Wohnfl&auml;che</div>
                    <div class="cover-fact-value"><?php echo esc_html($fmt($d['areas']['wohnflaeche'], 'flaeche')); ?> m&sup2;</div>
                </div>
            <?php endif; ?>

            <?php if ($d['areas']['zimmer'] > 0): ?>
                <div class="cover-fact">
                    <div class="cover-fact-label">Zimmer</div>
                    <div class="cover-fact-value"><?php echo esc_html($fmt($d['areas']['zimmer'], 'zimmer')); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($d['areas']['grundstueck'] > 0): ?>
                <div class="cover-fact">
                    <div class="cover-fact-label">Grundst&uuml;ck</div>
                    <div class="cover-fact-value"><?php echo esc_html($fmt($d['areas']['grundstueck'], 'flaeche')); ?> m&sup2;</div>
                </div>
            <?php endif; ?>

            <?php if ($d['energy']['baujahr']): ?>
                <div class="cover-fact">
                    <div class="cover-fact-label">Baujahr</div>
                    <div class="cover-fact-value"><?php echo esc_html($d['energy']['baujahr']); ?></div>
                </div>
            <?php endif; ?>

            <div class="cover-fact">
                <div class="cover-fact-label"><?php echo esc_html($price_label); ?></div>
                <div class="cover-fact-value"><?php echo $price_value; ?></div>
            </div>
        </div>
    </div>

    <!-- Branding Footer -->
    <div class="cover-footer">
        <div>
            <?php if ($d['org']['logo']): ?>
                <div class="cover-logo"><img src="<?php echo esc_url($d['org']['logo']); ?>" alt="<?php echo esc_attr($d['org']['name']); ?>"></div>
            <?php else: ?>
                <div class="cover-org-name"><?php echo esc_html($d['org']['name']); ?></div>
            <?php endif; ?>
        </div>
        <div style="text-align: right;">
            <div class="cover-org-name"><?php echo esc_html($d['org']['name']); ?></div>
            <?php if ($d['org']['url']): ?>
                <div class="cover-org-url"><?php echo esc_html(preg_replace('#^https?://#', '', $d['org']['url'])); ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════
     PAGE 2: DETAILS & DESCRIPTION
     ═══════════════════════════════════════════════ -->
<div class="expose-page">

    <div class="two-col">
        <div class="col-main">

            <!-- Description -->
            <?php if ($d['texts']['beschreibung']): ?>
                <div class="section">
                    <h2 class="section-title">Beschreibung</h2>
                    <div style="font-size: 9.5pt; line-height: 1.6;">
                        <?php echo wp_kses_post(wpautop($d['texts']['beschreibung'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Equipment -->
            <?php if (!empty($d['features']) || $d['texts']['ausstattung']): ?>
                <div class="section">
                    <h2 class="section-title">Ausstattung</h2>
                    <?php if (!empty($d['features'])): ?>
                        <div class="feature-badges">
                            <?php foreach ($d['features'] as $f): ?>
                                <span class="feature-badge"><?php echo esc_html($f); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($d['texts']['ausstattung']): ?>
                        <div style="font-size: 9.5pt; line-height: 1.6;">
                            <?php echo wp_kses_post(wpautop($d['texts']['ausstattung'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>

        <div class="col-side">
            <!-- Price Details -->
            <div class="section">
                <h2 class="section-title">Eckdaten</h2>
                <table class="facts-table">
                    <?php if ($d['areas']['wohnflaeche'] > 0): ?>
                        <tr><td>Wohnfl&auml;che</td><td><?php echo esc_html($fmt($d['areas']['wohnflaeche'], 'flaeche')); ?> m&sup2;</td></tr>
                    <?php endif; ?>
                    <?php if ($d['areas']['nutzflaeche'] > 0): ?>
                        <tr><td>Nutzfl&auml;che</td><td><?php echo esc_html($fmt($d['areas']['nutzflaeche'], 'flaeche')); ?> m&sup2;</td></tr>
                    <?php endif; ?>
                    <?php if ($d['areas']['grundstueck'] > 0): ?>
                        <tr><td>Grundst&uuml;ck</td><td><?php echo esc_html($fmt($d['areas']['grundstueck'], 'flaeche')); ?> m&sup2;</td></tr>
                    <?php endif; ?>
                    <?php if ($d['areas']['zimmer'] > 0): ?>
                        <tr><td>Zimmer</td><td><?php echo esc_html($fmt($d['areas']['zimmer'], 'zimmer')); ?></td></tr>
                    <?php endif; ?>
                    <?php if ($d['areas']['schlafzimmer'] > 0): ?>
                        <tr><td>Schlafzimmer</td><td><?php echo esc_html($fmt($d['areas']['schlafzimmer'], 'zimmer')); ?></td></tr>
                    <?php endif; ?>
                    <?php if ($d['areas']['badezimmer'] > 0): ?>
                        <tr><td>Badezimmer</td><td><?php echo esc_html($fmt($d['areas']['badezimmer'], 'zimmer')); ?></td></tr>
                    <?php endif; ?>
                    <?php if ($d['areas']['stellplaetze'] > 0): ?>
                        <tr><td>Stellpl&auml;tze</td><td><?php echo esc_html($fmt($d['areas']['stellplaetze'], 'zimmer')); ?></td></tr>
                    <?php endif; ?>

                    <!-- Spacing row -->
                    <tr><td colspan="2" style="border: none; padding: 4px 0;"></td></tr>

                    <?php if ($d['pricing']['kaufpreis'] > 0): ?>
                        <tr><td>Kaufpreis</td><td><?php echo esc_html($fmt($d['pricing']['kaufpreis'], 'preis')); ?> &euro;</td></tr>
                        <?php if ($d['pricing']['hausgeld'] > 0): ?>
                            <tr><td>Hausgeld</td><td><?php echo esc_html($fmt($d['pricing']['hausgeld'], 'preis')); ?> &euro;</td></tr>
                        <?php endif; ?>
                        <?php if ($d['pricing']['provision']): ?>
                            <tr><td>K&auml;uferprovision</td><td><?php
                                echo esc_html($d['pricing']['provision']);
                                if (strpos($d['pricing']['provision'], 'MwSt') === false && strpos($d['pricing']['provision'], '%') !== false) {
                                    echo ' inkl. ges. MwSt.';
                                }
                            ?></td></tr>
                        <?php endif; ?>
                    <?php elseif ($d['pricing']['kaltmiete'] > 0): ?>
                        <tr><td>Kaltmiete</td><td><?php echo esc_html($fmt($d['pricing']['kaltmiete'], 'preis')); ?> &euro;</td></tr>
                        <?php if ($d['pricing']['nebenkosten'] > 0): ?>
                            <tr><td>Nebenkosten</td><td><?php echo esc_html($fmt($d['pricing']['nebenkosten'], 'preis')); ?> &euro;</td></tr>
                        <?php endif; ?>
                        <?php if ($d['pricing']['warmmiete'] > 0): ?>
                            <tr><td>Warmmiete</td><td><?php echo esc_html($fmt($d['pricing']['warmmiete'], 'preis')); ?> &euro;</td></tr>
                        <?php endif; ?>
                    <?php else: ?>
                        <tr><td>Preis</td><td>Auf Anfrage</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ═══════════════════════════════════════════════
     PAGE 3: LOCATION & ENERGY
     ═══════════════════════════════════════════════ -->
<?php if ($d['texts']['lage'] || !empty($d['distances']) || $d['energy']['klasse'] || $d['energy']['endenergie']): ?>
<div class="expose-page">

    <?php if ($d['texts']['lage'] || !empty($d['distances'])): ?>
        <div class="section">
            <h2 class="section-title">Lage</h2>
            <?php if ($d['texts']['lage']): ?>
                <div style="font-size: 9.5pt; line-height: 1.6; margin-bottom: 1em;">
                    <?php echo wp_kses_post(wpautop($d['texts']['lage'])); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($d['distances'])): ?>
                <h3 style="font-size: 10pt; font-weight: 600; margin-bottom: 0.5em; color: #444;">Entfernungen</h3>
                <table class="facts-table">
                    <?php foreach ($d['distances'] as $label => $value): ?>
                        <tr><td><?php echo esc_html($label); ?></td><td><?php echo esc_html($value); ?> km</td></tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <?php
            // Infrastructure Score (compact expose version)
            // $post_id is in scope from PdfExpose::render_expose()
            if (isset($post_id) && class_exists('DBW\ImmoSuite\Frontend\InfrastructureScore')) {
                \DBW\ImmoSuite\Frontend\InfrastructureScore::render_expose($post_id, $accent, $primary);
            }
            ?>
        </div>
    <?php endif; ?>

    <?php if ($d['energy']['klasse'] || $d['energy']['endenergie']): ?>
        <div class="section">
            <h2 class="section-title">Energie &amp; Heizung</h2>
            <table class="facts-table" style="margin-bottom: 10px;">
                <?php if ($d['energy']['baujahr']): ?>
                    <tr><td>Baujahr</td><td><?php echo esc_html($d['energy']['baujahr']); ?></td></tr>
                <?php endif; ?>
                <?php if ($d['energy']['art']): ?>
                    <tr><td>Ausweistyp</td><td><?php echo esc_html(ucfirst(strtolower($d['energy']['art'])) . 'sausweis'); ?></td></tr>
                <?php endif; ?>
                <?php if ($d['energy']['endenergie']): ?>
                    <tr><td>Endenergieverbrauch</td><td><?php echo esc_html($d['energy']['endenergie']); ?> kWh/(m&sup2;&middot;a)</td></tr>
                <?php endif; ?>
                <?php if ($d['energy']['traeger']): ?>
                    <tr><td>Energietr&auml;ger</td><td><?php echo esc_html(ucwords(strtolower(str_replace('_', ' ', $d['energy']['traeger'])))); ?></td></tr>
                <?php endif; ?>
                <?php if ($d['energy']['gueltig']): ?>
                    <tr><td>G&uuml;ltig bis</td><td><?php echo esc_html(date_i18n('d.m.Y', strtotime($d['energy']['gueltig']))); ?></td></tr>
                <?php endif; ?>
                <?php if ($energy_class): ?>
                    <tr><td>Energieeffizienzklasse</td><td><strong><?php echo esc_html($energy_class); ?></strong></td></tr>
                <?php endif; ?>
            </table>

            <?php if ($energy_class && isset($energy_colors[$energy_class])): ?>
                <div class="energy-bar">
                    <?php foreach ($energy_colors as $key => $color): ?>
                        <div class="energy-segment<?php echo ($key === $energy_class) ? ' energy-active' : ''; ?>"
                             style="background-color: <?php echo esc_attr($color); ?>;">
                            <?php echo esc_html($key); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($d['texts']['sonstiges']): ?>
        <div class="section">
            <h2 class="section-title">Sonstiges</h2>
            <div style="font-size: 9.5pt; line-height: 1.6;">
                <?php echo wp_kses_post(wpautop($d['texts']['sonstiges'])); ?>
            </div>
        </div>
    <?php endif; ?>

</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════
     PAGE 4: IMAGES & FLOOR PLANS
     ═══════════════════════════════════════════════ -->
<?php
$gallery_for_grid = array_slice($d['images']['gallery'], 1, 6); // Skip hero, show up to 6
if (!empty($gallery_for_grid) || !empty($d['images']['floorplans'])):
?>
<div class="expose-page">

    <?php if (!empty($gallery_for_grid)): ?>
        <div class="section">
            <h2 class="section-title">Bildergalerie</h2>
            <div class="img-grid">
                <?php foreach ($gallery_for_grid as $img): ?>
                    <img src="<?php echo esc_url($img['url']); ?>" alt="<?php echo esc_attr($img['alt']); ?>">
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($d['images']['floorplans'])): ?>
        <div class="section">
            <h2 class="section-title">Grundrisse</h2>
            <div class="img-grid img-grid-full">
                <?php foreach ($d['images']['floorplans'] as $fp): ?>
                    <img src="<?php echo esc_url($fp['url']); ?>" alt="<?php echo esc_attr($fp['alt']); ?>" style="object-fit: contain; background: #f5f5f5;">
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════
     LAST PAGE: CONTACT & DISCLAIMER
     ═══════════════════════════════════════════════ -->
<div class="expose-page">

    <div class="section">
        <h2 class="section-title"><?php echo esc_html(\DBW\ImmoSuite\dbw_anrede(
            __('Ihr Ansprechpartner', 'dbw-immo-suite'),
            __('Dein Ansprechpartner', 'dbw-immo-suite')
        )); ?></h2>

        <div class="contact-card">
            <?php if ($d['contact']['bild']): ?>
                <img class="contact-avatar" src="<?php echo esc_url($d['contact']['bild']); ?>" alt="<?php echo esc_attr($d['contact']['vorname'] . ' ' . $d['contact']['name']); ?>">
            <?php endif; ?>
            <div>
                <div class="contact-name"><?php echo esc_html(trim($d['contact']['vorname'] . ' ' . $d['contact']['name'])); ?></div>
                <?php if ($d['contact']['tel']): ?>
                    <?php $phone = $phone_fmt($d['contact']['tel']); ?>
                    <div class="contact-detail"><?php echo esc_html($phone['display']); ?></div>
                <?php endif; ?>
                <?php if ($d['contact']['email']): ?>
                    <div class="contact-detail"><?php echo esc_html($d['contact']['email']); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Company Info -->
    <div class="section" style="margin-top: 2em;">
        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 1em;">
            <?php if ($d['org']['logo']): ?>
                <img src="<?php echo esc_url($d['org']['logo']); ?>" alt="<?php echo esc_attr($d['org']['name']); ?>" style="max-height: 48px; width: auto;">
            <?php endif; ?>
            <div>
                <div style="font-size: 12pt; font-weight: 700; color: <?php echo esc_attr($primary); ?>;"><?php echo esc_html($d['org']['name']); ?></div>
                <?php
                $org_addr = implode(', ', array_filter(array(
                    $d['org']['street'],
                    trim($d['org']['zip'] . ' ' . $d['org']['city']),
                )));
                if ($org_addr):
                ?>
                    <div style="font-size: 9pt; color: #555;"><?php echo esc_html($org_addr); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <table class="facts-table" style="max-width: 350px;">
            <?php if ($d['org']['phone']): ?>
                <?php $org_phone = $phone_fmt($d['org']['phone']); ?>
                <tr><td>Telefon</td><td><?php echo esc_html($org_phone['display']); ?></td></tr>
            <?php endif; ?>
            <?php if ($d['org']['email']): ?>
                <tr><td>E-Mail</td><td><?php echo esc_html($d['org']['email']); ?></td></tr>
            <?php endif; ?>
            <?php if ($d['org']['url']): ?>
                <tr><td>Web</td><td><?php echo esc_html(preg_replace('#^https?://#', '', $d['org']['url'])); ?></td></tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Disclaimer -->
    <div class="disclaimer">
        Alle Angaben sind ohne Gew&auml;hr und basieren auf Informationen, die uns vom Eigent&uuml;mer &uuml;bermittelt wurden.
        Wir &uuml;bernehmen keine Gew&auml;hr f&uuml;r die Vollst&auml;ndigkeit, Richtigkeit und Aktualit&auml;t dieser Angaben.
        Irrtum und Zwischen&auml;nderungen vorbehalten. Dieses Expos&eacute; ist nur f&uuml;r den Empf&auml;nger bestimmt.
        Eine Weitergabe an Dritte ist ohne unsere ausdr&uuml;ckliche Zustimmung nicht gestattet.
        <br><br>
        Stand: <?php echo esc_html(date_i18n('d.m.Y')); ?> &mdash; <?php echo esc_html($d['org']['name']); ?>
    </div>

</div>

<script>
// Auto-trigger print dialog after images load
window.addEventListener('load', function() {
    // Small delay to ensure rendering is complete
    setTimeout(function() { window.print(); }, 600);
});
</script>
</body>
</html>
