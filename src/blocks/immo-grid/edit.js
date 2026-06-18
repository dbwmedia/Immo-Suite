import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, RangeControl, SelectControl, TextControl, Placeholder } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

export default function Edit({ attributes, setAttributes }) {
    const { postsPerPage, marketing, propertyType, hidePrice, showDate, onlyHighlights, location, locationSource, columns, showCtaButton, ctaButtonText, ctaButtonUrl } = attributes;
    const blockProps = useBlockProps();

    // Fetch taxonomy terms dynamically
    const marketingTerms = useSelect((select) => {
        return select('core').getEntityRecords('taxonomy', 'vermarktungsart', { per_page: -1 });
    }, []);

    const propertyTypeTerms = useSelect((select) => {
        return select('core').getEntityRecords('taxonomy', 'objektart', { per_page: -1 });
    }, []);

    const locationTerms = useSelect((select) => {
        return select('core').getEntityRecords('taxonomy', 'ort', { per_page: -1 });
    }, []);

    const marketingOptions = [
        { label: __('Alle', 'dbw-immo-suite'), value: '' }
    ];
    if (marketingTerms) {
        marketingTerms.forEach((term) => {
            marketingOptions.push({ label: term.name, value: term.slug });
        });
    }

    const typeOptions = [
        { label: __('Alle', 'dbw-immo-suite'), value: '' }
    ];
    if (propertyTypeTerms) {
        propertyTypeTerms.forEach((term) => {
            typeOptions.push({ label: term.name, value: term.slug });
        });
    }

    const locationOptions = [
        { label: __('Alle Orte', 'dbw-immo-suite'), value: '' }
    ];
    if (locationTerms) {
        locationTerms.forEach((term) => {
            locationOptions.push({ label: term.name + ' (' + term.count + ')', value: term.slug });
        });
    }

    const isLocationAuto = locationSource === 'current';

    return (
        <div {...blockProps}>
            <InspectorControls>
                <PanelBody title={__('Darstellung', 'dbw-immo-suite')}>
                    <RangeControl
                        label={__('Anzahl Immobilien', 'dbw-immo-suite')}
                        value={postsPerPage}
                        onChange={(value) => setAttributes({ postsPerPage: value })}
                        min={1}
                        max={24}
                    />
                    <RangeControl
                        label={__('Spalten', 'dbw-immo-suite')}
                        value={columns}
                        onChange={(value) => setAttributes({ columns: value })}
                        min={1}
                        max={4}
                    />
                    <ToggleControl
                        label={__('Preis ausblenden', 'dbw-immo-suite')}
                        checked={hidePrice}
                        onChange={(value) => setAttributes({ hidePrice: value })}
                    />
                    <ToggleControl
                        label={__('Einstelldatum anzeigen', 'dbw-immo-suite')}
                        checked={showDate}
                        onChange={(value) => setAttributes({ showDate: value })}
                    />
                </PanelBody>
                <PanelBody title={__('Filter', 'dbw-immo-suite')} initialOpen={true}>
                    <p className="components-base-control__help">{__('Nur Immobilien anzeigen, die folgende Kriterien erfuellen:', 'dbw-immo-suite')}</p>
                    <ToggleControl
                        label={__('Nur Highlights anzeigen', 'dbw-immo-suite')}
                        help={__('Zeigt nur Immobilien an, bei denen "Als Highlight markieren" gesetzt ist.', 'dbw-immo-suite')}
                        checked={onlyHighlights}
                        onChange={(value) => setAttributes({ onlyHighlights: value })}
                    />
                    <ToggleControl
                        label={__('Ort automatisch aus aktueller Seite', 'dbw-immo-suite')}
                        help={isLocationAuto
                            ? __('Der Ort wird automatisch aus der aufgerufenen Standort- oder Term-Seite uebernommen.', 'dbw-immo-suite')
                            : __('Aktivieren, um den Ort dynamisch aus der aktuellen Seite zu beziehen (z.B. fuer Standort-Templates).', 'dbw-immo-suite')
                        }
                        checked={isLocationAuto}
                        onChange={(value) => setAttributes({
                            locationSource: value ? 'current' : 'manual',
                            location: value ? '' : location,
                        })}
                    />
                    {!isLocationAuto && (
                        <SelectControl
                            label={__('Ort / Stadt', 'dbw-immo-suite')}
                            help={__('Ideal fuer Geo-Landing-Pages: Zeige nur Immobilien in einer bestimmten Stadt.', 'dbw-immo-suite')}
                            value={location}
                            options={locationOptions}
                            onChange={(value) => setAttributes({ location: value })}
                        />
                    )}
                    <SelectControl
                        label={__('Vermarktungsart', 'dbw-immo-suite')}
                        value={marketing}
                        options={marketingOptions}
                        onChange={(value) => setAttributes({ marketing: value })}
                    />
                    <SelectControl
                        label={__('Objektart', 'dbw-immo-suite')}
                        value={propertyType}
                        options={typeOptions}
                        onChange={(value) => setAttributes({ propertyType: value })}
                    />
                </PanelBody>
                <PanelBody title={__('CTA-Button', 'dbw-immo-suite')} initialOpen={false}>
                    <ToggleControl
                        label={__('Button "Zu allen Immobilien" anzeigen', 'dbw-immo-suite')}
                        checked={showCtaButton}
                        onChange={(value) => setAttributes({ showCtaButton: value })}
                    />
                    {showCtaButton && (
                        <>
                            <TextControl
                                label={__('Button-Text', 'dbw-immo-suite')}
                                value={ctaButtonText}
                                placeholder={__('Zu allen Immobilien', 'dbw-immo-suite')}
                                onChange={(value) => setAttributes({ ctaButtonText: value })}
                            />
                            <TextControl
                                label={__('Button-URL (optional)', 'dbw-immo-suite')}
                                help={__('Leer lassen fuer automatischen Link zum Immobilien-Archiv.', 'dbw-immo-suite')}
                                value={ctaButtonUrl}
                                placeholder="/immobilien/"
                                onChange={(value) => setAttributes({ ctaButtonUrl: value })}
                            />
                        </>
                    )}
                </PanelBody>
            </InspectorControls>

            <Placeholder
                icon="index-card"
                label={__('Immo Grid', 'dbw-immo-suite')}
                instructions={__('Zeigt aktuelle Immobilien als Karten-Grid an. Vorschau nur im Frontend sichtbar.', 'dbw-immo-suite')}
            />
        </div>
    );
}
