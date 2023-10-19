<?php

declare(strict_types=1);

namespace ReiffIntegrations\Installer;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\System\Unit\UnitDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CustomFieldInstaller
{
    public const PRODUCT_ECLASS51               = 'reiff_product_eclass51';
    public const PRODUCT_ECLASS71               = 'reiff_product_eclass71';
    public const PRODUCT_MATERIALFRACHTGRUPPE   = 'reiff_product_materialfrachtgruppe';
    public const PRODUCT_ANFRAGE                = 'reiff_product_anfrage';
    public const PRODUCT_BANNER_OFFER           = 'reiff_product_banner_offer';
    public const PRODUCT_BANNER_NEW             = 'reiff_product_banner_new';
    public const PRODUCT_ZUSCHNITT              = 'reiff_product_zuschnitt';
    public const PRODUCT_ABSCHNITT              = 'reiff_product_abschnitt';
    public const PRODUCT_BUTTON_CAD             = 'reiff_product_button_cad';
    public const PRODUCT_BUTTON_ZUSCHNITT       = 'reiff_product_button_zuschnitt';
    public const PRODUCT_VIDEO                  = 'reiff_product_video';
    public const PRODUCT_SHIPPING_TIME          = 'reiff_product_shipping_time';
    public const PRODUCT_PRICE_BASE_QUANTITY    = 'reiff_product_price_base_quantity';
    public const PRODUCT_MANUFACTURER_NAME_LOGO = 'reiff_product_manufacturer_name_logo';

    public const MEDIA_GEFAHRSTOFF = 'reiff_media_gefahrstoff';
    public const MEDIA_PICTOGRAM   = 'reiff_media_piktogramm';
    public const MEDIA_DOWNLOAD    = 'reiff_media_download';

    public const ORDER_COMMISSION           = 'reiff_order_commission';
    public const ORDER_COMPLETE_DELIVERY    = 'reiff_order_is_complete_delivery';
    public const ORDER_LINE_ITEM_COMMISSION = 'reiff_order_line_item_commission';

    public const PAYMENT_TERMS_OF_PAYMENT   = 'reiff_payment_terms_of_payment';

    public const CUSTOMER_PROVIDED_DEBTOR_NUMBER   = 'reiff_customer_provided_debtor_number';
    public const CUSTOMER_IS_OCI                   = 'reiff_customer_is_oci';
    public const CUSTOMER_ASSORTMENT_ID            = 'reiff_customer_assortment_id';
    public const CUSTOMER_ASSORTMENT_ID_UUID       = 'de1e758b055b462c8904beae744c9529';
    public const CUSTOMER_INDUSTRY                 = 'reiff_customer_industry';
    public const CUSTOMER_EMAIL_ELECTRONIC_INVOICE = 'reiff_customer_email_for_electronic_invoice';

    public const UNIT_SAP_IDENTIFIER = 'reiff_unit_sap_identifier';

    /*
     * Example:
     *
     * [
     *     'id'     => 'UUID',
     *     'name'   => 'field_set_technical_name',
     *     'active' => true,
     *     'config' => [
     *         'label' => [
     *             'en-GB' => 'Name',
     *             'de-DE' => 'Name',
     *         ],
     *     ],
     *     'customFields' => [
     *         [
     *             'id'     => 'UUID',
     *             'name'   => 'field_name',
     *             'active' => true,
     *             'type'   => CustomFieldTypes::TEXT,
     *             'config' => [
     *                 'label' => [
     *                     'en-GB' => 'Name',
     *                     'de-DE' => 'Name',
     *                 ],
     *             ],
     *         ],
     *     ],
     * ],
     */
    private const CUSTOM_FIELDSETS = [
        [
            'id'     => 'ded9f28b57324e9e9382336750e3bff1',
            'name'   => 'REIFF Produktdaten',
            'active' => true,
            'config' => [
                'label' => [
                    'en-GB' => 'REIFF Product Data',
                    'de-DE' => 'REIFF Produktdaten',
                ],
            ],
            'relations' => [
                [
                    'id'         => 'fb39dac89d794093b0fe4fb3328a4f75',
                    'entityName' => ProductDefinition::ENTITY_NAME,
                ],
            ],
            'customFields' => [
                [
                    'id'     => '68107a10702842c2945cc28a4eb03e89',
                    'name'   => self::PRODUCT_ECLASS51,
                    'active' => true,
                    'type'   => CustomFieldTypes::TEXT,
                    'config' => [
                        'label' => [
                            'en-GB' => 'eCl@ss 5.1',
                            'de-DE' => 'eCl@ss 5.1',
                        ],
                        'customFieldPosition' => 0,
                    ],
                ],
                [
                    'id'     => '68107a10702842c2945cc28a4eb03e89',
                    'name'   => self::PRODUCT_ECLASS71,
                    'active' => true,
                    'type'   => CustomFieldTypes::TEXT,
                    'config' => [
                        'label' => [
                            'en-GB' => 'eCl@ss 7.1',
                            'de-DE' => 'eCl@ss 7.1',
                        ],
                        'customFieldPosition' => 1,
                    ],
                ],
                [
                    'id'     => '912440df850e4160b63689eb5a0b7c17',
                    'name'   => self::PRODUCT_MATERIALFRACHTGRUPPE,
                    'active' => true,
                    'type'   => CustomFieldTypes::TEXT,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Materialfrachtgruppe',
                            'de-DE' => 'Materialfrachtgruppe',
                        ],
                        'customFieldPosition' => 2,
                    ],
                ],
                [
                    'id'     => 'fb0e759fc0114d11a34f376c06098fed',
                    'name'   => self::PRODUCT_ANFRAGE,
                    'active' => true,
                    'type'   => CustomFieldTypes::BOOL,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Inquiry',
                            'de-DE' => 'Anfrage',
                        ],
                        'customFieldPosition' => 3,
                    ],
                ],
                [
                    'id'     => 'e8b4e7ad57e64c52a7a838a73ea448fd',
                    'name'   => self::PRODUCT_BANNER_OFFER,
                    'active' => true,
                    'type'   => CustomFieldTypes::BOOL,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Banner Offer',
                            'de-DE' => 'Banner Angebot',
                        ],
                        'customFieldPosition' => 4,
                    ],
                ],
                [
                    'id'     => 'b19f53ea870a4a2db166428daac86dbf',
                    'name'   => self::PRODUCT_BANNER_NEW,
                    'active' => true,
                    'type'   => CustomFieldTypes::BOOL,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Banner New Release',
                            'de-DE' => 'Banner Neuheit',
                        ],
                        'customFieldPosition' => 5,
                    ],
                ],
                [
                    'id'     => '5263acc30fed4907a86fd674882e93ae',
                    'name'   => self::PRODUCT_ZUSCHNITT,
                    'active' => true,
                    'type'   => CustomFieldTypes::BOOL,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Zuschnitt',
                            'de-DE' => 'Zuschnitt',
                        ],
                        'customFieldPosition' => 6,
                    ],
                ],
                [
                    'id'     => 'e64c6ced5b1e41d0b49f29b5760268b0',
                    'name'   => self::PRODUCT_ABSCHNITT,
                    'active' => true,
                    'type'   => CustomFieldTypes::BOOL,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Abschnitt',
                            'de-DE' => 'Abschnitt',
                        ],
                        'customFieldPosition' => 7,
                    ],
                ],
                [
                    'id'     => 'db10064efd8042e3988732b61c471b2a',
                    'name'   => self::PRODUCT_BUTTON_CAD,
                    'active' => true,
                    'type'   => CustomFieldTypes::BOOL,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Button CAD',
                            'de-DE' => 'Button CAD',
                        ],
                        'customFieldPosition' => 8,
                    ],
                ],
                [
                    'id'     => '5ae0f788ef3b4862a8b0c930158af6ff',
                    'name'   => self::PRODUCT_BUTTON_ZUSCHNITT,
                    'active' => true,
                    'type'   => CustomFieldTypes::BOOL,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Button Zuschnitt',
                            'de-DE' => 'Button Zuschnitt',
                        ],
                        'customFieldPosition' => 9,
                    ],
                ],
                [
                    'id'     => '5418afaa127243089d12b7be65e85fc1',
                    'name'   => self::PRODUCT_VIDEO,
                    'active' => true,
                    'type'   => CustomFieldTypes::TEXT,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Video',
                            'de-DE' => 'Video',
                        ],
                        'customFieldPosition' => 10,
                    ],
                ],
                [
                    'id'     => '65ec1fed2c284443855013a7326729a9',
                    'name'   => self::PRODUCT_SHIPPING_TIME,
                    'active' => true,
                    'type'   => CustomFieldTypes::INT,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Shipping Time',
                            'de-DE' => 'Lieferzeit',
                        ],
                        'customFieldPosition' => 11,
                    ],
                ],
                [
                    'id'     => 'ec8dae6208054debada4055162e08a58',
                    'name'   => self::PRODUCT_PRICE_BASE_QUANTITY,
                    'active' => true,
                    'type'   => CustomFieldTypes::INT,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Price Base Quantity',
                            'de-DE' => 'Preismenge',
                        ],
                        'customFieldPosition' => 12,
                    ],
                ],
                [
                    'id'     => '1eb0867df53940c1923e33afd5a80d1a',
                    'name'   => self::PRODUCT_MANUFACTURER_NAME_LOGO,
                    'active' => true,
                    'type'   => CustomFieldTypes::TEXT,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Logo Mapping (Manufacturer Name)',
                            'de-DE' => 'Logo Zuordnung (Hersteller Name)',
                        ],
                        'customFieldPosition' => 13,
                    ],
                ],
            ],
        ],
        [
            'id'     => '1243fca8925044b4bc89d4b902c0a666',
            'name'   => 'REIFF Mediendaten',
            'active' => true,
            'config' => [
                'label' => [
                    'en-GB' => 'REIFF Media Data',
                    'de-DE' => 'REIFF Mediendaten',
                ],
            ],
            'relations' => [
                [
                    'id'         => '2b8bdf16cae6416da14cbbf2e9f4a39a',
                    'entityName' => MediaDefinition::ENTITY_NAME,
                ],
            ],
            'customFields' => [
                [
                    'id'     => 'e1fe29cc831c4d80a1f8dc363f7ac457',
                    'name'   => self::MEDIA_GEFAHRSTOFF,
                    'active' => true,
                    'type'   => CustomFieldTypes::BOOL,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Gefahrstoff Image',
                            'de-DE' => 'Gefahrstoffbild',
                        ],
                        'customFieldPosition' => 0,
                    ],
                ],
                [
                    'id'     => 'da372905d23d4c87b21d01c3c2672124',
                    'name'   => self::MEDIA_PICTOGRAM,
                    'active' => true,
                    'type'   => CustomFieldTypes::BOOL,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Pictogram',
                            'de-DE' => 'Piktogramm',
                        ],
                        'customFieldPosition' => 1,
                    ],
                ],
                [
                    'id'     => '1132903564ee4c9087e8091db27dc87e',
                    'name'   => self::MEDIA_DOWNLOAD,
                    'active' => true,
                    'type'   => CustomFieldTypes::BOOL,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Used as Download',
                            'de-DE' => 'Genutzt als Download',
                        ],
                        'customFieldPosition' => 2,
                    ],
                ],
            ],
        ],
        [
            'id'     => 'c6b77baa82cd49e197028af5b9cd1773',
            'name'   => 'REIFF Bestelldaten',
            'active' => true,
            'config' => [
                'label' => [
                    'en-GB' => 'REIFF order data',
                    'de-DE' => 'REIFF Bestelldaten',
                ],
            ],
            'relations' => [
                [
                    'id'         => 'd6c767e6ccf24f9493579f00394ebd1f',
                    'entityName' => OrderDefinition::ENTITY_NAME,
                ],
            ],
            'customFields' => [
                [
                    'id'     => 'd392d447ccc144728f7ca96f3d55ed9d',
                    'name'   => self::ORDER_COMMISSION,
                    'active' => true,
                    'type'   => CustomFieldTypes::TEXT,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Commission note',
                            'de-DE' => 'Kommissionsvermerk',
                        ],
                        'customFieldPosition' => 0,
                    ],
                ],
                [
                    'id'     => 'f81e252954104fbdb44758511ffa1e9c',
                    'name'   => self::ORDER_COMPLETE_DELIVERY,
                    'active' => true,
                    'type'   => CustomFieldTypes::BOOL,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Is a complete delivery',
                            'de-DE' => 'Ist eine vollständige Lieferung',
                        ],
                        'customFieldPosition' => 1,
                    ],
                ],
            ],
        ],
        [
            'id' => '018ab774742f71eca13a093aad680bba',
            'name' => 'REIFF Zahlungsdaten',
            'active' => true,
            'config' => [
                'label' => [
                    'en-GB' => 'REIFF payment data',
                    'de-DE' => 'REIFF Zahlungsdaten',
                ]
            ],
            'relations' => [
                [
                    'id' => '018ab775ad4c72a7ab201863fe00e7d0',
                'entityName' => PaymentMethodDefinition::ENTITY_NAME,
                ],
            ],
            'customFields' => [
                [
                    'id'     => '018ab77712587229a6dd063e4c8675db',
                    'name'   => self::PAYMENT_TERMS_OF_PAYMENT,
                    'active' => true,
                    'type'   => CustomFieldTypes::TEXT,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Terms of payment',
                            'de-DE' => 'Zahlungsbedingung',
                        ],
                        'customFieldPosition' => 0,
                    ],
                ],
            ],
        ],
        [
            'id'     => '1405646d4f6f42f69b31b109164e1251',
            'name'   => 'REIFF Bestelldaten - Produkte',
            'active' => true,
            'config' => [
                'label' => [
                    'en-GB' => 'REIFF Order data - Products',
                    'de-DE' => 'REIFF Bestelldaten - Produkte',
                ],
            ],
            'relations' => [
                [
                    'id'         => '77e803dc193241d6a9ed6073298eeacd',
                    'entityName' => OrderDefinition::ENTITY_NAME,
                ],
            ],
            'customFields' => [
                [
                    'id'     => '35bf671158f34fb995f709ff76093f85',
                    'name'   => self::ORDER_LINE_ITEM_COMMISSION,
                    'active' => true,
                    'type'   => CustomFieldTypes::TEXT,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Commission note',
                            'de-DE' => 'Kommissionsvermerk',
                        ],
                        'customFieldPosition' => 0,
                    ],
                ],
            ],
        ],
        [
            'id'     => '32a4e49882214379959523e30e2907f5',
            'name'   => 'REIFF Maßeinheiten',
            'active' => true,
            'config' => [
                'label' => [
                    'en-GB' => 'REIFF Unit',
                    'de-DE' => 'REIFF Maßeinheiten',
                ],
            ],
            'relations' => [
                [
                    'id'         => '748ef69d876c4327a26b881ba5b43aaa',
                    'entityName' => UnitDefinition::ENTITY_NAME,
                ],
            ],
            'customFields' => [
                [
                    'id'     => 'e136b50cf9f247578431fdd86acdc898',
                    'name'   => self::UNIT_SAP_IDENTIFIER,
                    'active' => true,
                    'type'   => CustomFieldTypes::TEXT,
                    'config' => [
                        'label' => [
                            'en-GB' => 'SAP identifier',
                            'de-DE' => 'SAP Kennzeichen',
                        ],
                        'customFieldPosition' => 0,
                    ],
                ],
            ],
        ],
        [
            'id'     => '90b20473bb014217adc7bd5d162e0af4',
            'name'   => 'REIFF Kundendaten',
            'active' => true,
            'config' => [
                'label' => [
                    'en-GB' => 'REIFF Customer data',
                    'de-DE' => 'REIFF Kundendaten',
                ],
            ],
            'relations' => [
                [
                    'id'         => '58006cc270f044c08b0900e1228b1281',
                    'entityName' => CustomerDefinition::ENTITY_NAME,
                ],
            ],
            'customFields' => [
                [
                    'id'     => '2e39d2356ad34e49b9d91b6aa1b60230',
                    'name'   => self::CUSTOMER_PROVIDED_DEBTOR_NUMBER,
                    'active' => true,
                    'type'   => CustomFieldTypes::TEXT,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Registered customer number',
                            'de-DE' => 'Eingetragene Kundennummer',
                        ],
                        'customFieldPosition' => 0,
                    ],
                ],
                [
                    'id'     => '02083326aca84d19ac7bd1fbef05b5c8',
                    'name'   => self::CUSTOMER_IS_OCI,
                    'active' => true,
                    'type'   => CustomFieldTypes::BOOL,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Is an OCI customer',
                            'de-DE' => 'Ist ein OCI-Kunde',
                        ],
                        'customFieldPosition' => 1,
                    ],
                ],
                [
                    'id'     => self::CUSTOMER_ASSORTMENT_ID_UUID,
                    'name'   => self::CUSTOMER_ASSORTMENT_ID,
                    'active' => true,
                    'type'   => CustomFieldTypes::TEXT,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Assortment id',
                            'de-DE' => 'Sortiments-ID',
                        ],
                        'customFieldPosition' => 2,
                    ],
                ],
                [
                    'id'     => 'b97df8e8a2c04c5f83c5409abf9c8a89',
                    'name'   => self::CUSTOMER_INDUSTRY,
                    'active' => true,
                    'type'   => CustomFieldTypes::TEXT,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Customer industry',
                            'de-DE' => 'Branche des Kunden',
                        ],
                        'customFieldPosition' => 3,
                    ],
                ],
                [
                    'id'     => '300287d32aef45fb8cdadb17dfa02609',
                    'name'   => self::CUSTOMER_EMAIL_ELECTRONIC_INVOICE,
                    'active' => true,
                    'type'   => CustomFieldTypes::TEXT,
                    'config' => [
                        'label' => [
                            'en-GB' => 'E-mail address for electronic billing',
                            'de-DE' => 'E-Mail Adresse für elektronischen Rechnungsversand',
                        ],
                        'customFieldPosition' => 4,
                    ],
                ],
            ],
        ],
    ];
    private const DELETED_FIELD_IDS = [];

    private EntityRepository $customFieldSetRepository;
    private EntityRepository $customFieldRepository;

    public function __construct(ContainerInterface $container)
    {
        $customFieldSetRepository = $container->get('custom_field_set.repository');
        $customFieldRepository    = $container->get('custom_field.repository');

        if (!($customFieldSetRepository instanceof EntityRepository)) {
            throw new \LogicException('wrong repository type');
        }

        if (!($customFieldRepository instanceof EntityRepository)) {
            throw new \LogicException('wrong repository type');
        }

        $this->customFieldSetRepository = $customFieldSetRepository;
        $this->customFieldRepository    = $customFieldRepository;
    }

    public function install(InstallContext $context): void
    {
        $context->getContext()->scope(Context::SYSTEM_SCOPE, function (Context $context): void {
            $this->customFieldSetRepository->upsert(self::CUSTOM_FIELDSETS, $context);

            $this->deleteCustomFields($context);
        });
    }

    private function deleteCustomFields(Context $context): void
    {
        $deletion = array_map(static function (string $id) {
            return [
                'id' => $id,
            ];
        }, self::DELETED_FIELD_IDS);

        $this->customFieldRepository->delete($deletion, $context);
    }
}
