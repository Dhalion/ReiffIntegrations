<?php

declare(strict_types=1);

namespace ReiffIntegrations\Util;

use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter as Local;
use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class FilesystemFactory
{
    public const FILESYSTEM_ORDER_EXPORT_ARCHIVE   = 'order_export_archive';
    public const FILESYSTEM_ORDER_EXPORT_ERROR     = 'order_export_error';
    public const FILESYSTEM_PRODUCT_IMPORT_SOURCE  = 'product_import_source';
    public const FILESYSTEM_PRODUCT_IMPORT_ARCHIVE = 'product_import_archive';
    public const FILESYSTEM_PRODUCT_IMPORT_ERROR   = 'product_import_error';
    public const FILESYSTEM_PRODUCT_IMPORT_MEDIA   = 'product_import_media';

    private SystemConfigService $systemConfigService;
    private LoggerInterface $logger;
    private string $environment;

    public function __construct(
        SystemConfigService $systemConfigService,
        LoggerInterface $logger,
        string $environment
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->logger              = $logger;
        $this->environment         = $environment;
    }

    public function factory(): MountManager
    {
        $filesystems = array_filter([
            self::FILESYSTEM_ORDER_EXPORT_ARCHIVE   => $this->addFilesystem(Configuration::CONFIG_KEY_ORDER_EXPORT_ARCHIVE_PATH),
            self::FILESYSTEM_ORDER_EXPORT_ERROR     => $this->addFilesystem(Configuration::CONFIG_KEY_ORDER_EXPORT_ERROR_PATH),
            self::FILESYSTEM_PRODUCT_IMPORT_SOURCE  => $this->addFilesystem(Configuration::CONFIG_KEY_FILE_IMPORT_SOURCE_PATH),
            self::FILESYSTEM_PRODUCT_IMPORT_ARCHIVE => $this->addFilesystem(Configuration::CONFIG_KEY_FILE_IMPORT_ARCHIVE_PATH),
            self::FILESYSTEM_PRODUCT_IMPORT_ERROR   => $this->addFilesystem(Configuration::CONFIG_KEY_FILE_IMPORT_ERROR_PATH),
            self::FILESYSTEM_PRODUCT_IMPORT_MEDIA   => $this->addFilesystem(Configuration::CONFIG_KEY_FILE_IMPORT_MEDIA_PATH),
        ]);

        return new MountManager($filesystems);
    }

    private function addFilesystem(string $configName): ?Filesystem
    {
        if ($this->environment === 'test') {
            return new Filesystem(new InMemoryFilesystemAdapter());
        }

        $path = $this->systemConfigService->getString($configName);

        if (!file_exists($path) || !is_dir($path)) {
            $this->logger->error('Filesystem directory not found', [
                'directory' => $path,
            ]);

            return null;
        }

        return new Filesystem(new Local($path));
    }
}
