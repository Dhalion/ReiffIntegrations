<?php

declare(strict_types=1);

namespace ReiffIntegrations\Util;

interface Configuration
{
    public const CONFIG_KEY_ERROR_RECIPIENT = 'ReiffIntegrations.config.errorRecipient';

    public const CONFIG_KEY_API_USER_NAME  = 'ReiffIntegrations.config.apiUserName';
    public const CONFIG_KEY_API_PASSWORD   = 'ReiffIntegrations.config.apiPassword';
    public const CONFIG_KEY_API_IGNORE_SSL = 'ReiffIntegrations.config.apiIgnoreSsl';

    public const CONFIG_KEY_FILE_IMPORT_SOURCE_PATH  = 'ReiffIntegrations.config.pathImportSource';
    public const CONFIG_KEY_FILE_IMPORT_ARCHIVE_PATH = 'ReiffIntegrations.config.pathImportFileArchive';
    public const CONFIG_KEY_FILE_IMPORT_ERROR_PATH   = 'ReiffIntegrations.config.pathImportError';
    public const CONFIG_KEY_FILE_IMPORT_MEDIA_PATH   = 'ReiffIntegrations.config.pathImportMedia';
    public const CONFIG_KEY_EXPORT_ARCHIVE_ENABLED   = 'ReiffIntegrations.config.exportArchiveActive';

    public const CONFIG_KEY_OFFER_API_URL     = 'ReiffIntegrations.config.offerApiUrl';
    public const CONFIG_KEY_OFFER_PDF_API_URL = 'ReiffIntegrations.config.offerPdfApiUrl';

    public const CONFIG_KEY_ORDER_EXPORT_ARCHIVE_PATH      = 'ReiffIntegrations.config.pathOrderExportArchive';
    public const CONFIG_KEY_ORDER_EXPORT_ERROR_PATH        = 'ReiffIntegrations.config.pathOrderExportError';
    public const CONFIG_KEY_ORDER_EXPORT_MAX_ATTEMPTS      = 'ReiffIntegrations.config.orderExportMaxAttempts';
    public const CONFIG_KEY_ORDER_EXPORT_MONITORING_PERIOD = 'ReiffIntegrations.config.orderExportMonitoringPeriod';
    public const CONFIG_KEY_ORDER_EXPORT_URL               = 'ReiffIntegrations.config.orderExportUrl';
    public const CONFIG_KEY_ORDERS_API_URL                 = 'ReiffIntegrations.config.ordersApiUrl';
    public const CONFIG_KEY_ORDER_DETAILS_API_URL          = 'ReiffIntegrations.config.orderDetailsApiUrl';
    public const CONFIG_KEY_ORDER_IGNORED_PAYMENT_METHOD_IDS = 'ReiffIntegrations.config.paymentMethodIdsToIgnore';

    public const CONFIG_KEY_ROOT_CATEGORY            = 'ReiffIntegrations.config.rootCategory';
    public const CONFIG_KEY_CATEGORY_MAIN_CMS_PAGE   = 'ReiffIntegrations.config.mainCategoriesCmsPage';
    public const CONFIG_KEY_CATEGORY_NORMAL_CMS_PAGE = 'ReiffIntegrations.config.normalCategoriesCmsPage';

    public const CONFIG_KEY_PRICE_API_URL = 'ReiffIntegrations.config.priceApiUrl';
    public const CONFIG_KEY_CART_API_URL  = 'ReiffIntegrations.config.cartApiUrl';

    public const CONFIG_KEY_AVAILABILITY_API_URL = 'ReiffIntegrations.config.availabilityApiUrl';

    public const CONFIG_KEY_DELIVERY_PDF_API_URL = 'ReiffIntegrations.config.deliveryPdfApiUrl';
    public const CONFIG_KEY_INVOICE_PDF_API_URL  = 'ReiffIntegrations.config.invoicePdfApiUrl';

    public const CONFIG_KEY_API_ORDER_NUMBER_URL = 'ReiffIntegrations.config.customOrderNumberApiUrl';

    public const CONFIG_KEY_API_CONTRACT_LIST_URL   = 'ReiffIntegrations.config.contractListApiUrl';
    public const CONFIG_KEY_API_CONTRACT_STATUS_URL = 'ReiffIntegrations.config.contractStatusApiUrl';
}
