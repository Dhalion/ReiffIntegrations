<?php

declare(strict_types=1);

namespace ReiffIntegrations\Util;

use League\Flysystem\MountManager;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ExportArchiver
{
    public function __construct(
        private MountManager $filesystem,
        private SystemConfigService $configService,
    ) {
    }

    public function archive(string $content, string $filename): void
    {
        if (!$this->configService->getBool(Configuration::CONFIG_KEY_EXPORT_ARCHIVE_ENABLED)) {
            return;
        }

        do {
            $prefix              = (new \DateTime())->format('Ymd-His.u');
            $destinationFilename = $prefix . '-' . $filename;
            $destination         = sprintf('%s://%s', FilesystemFactory::FILESYSTEM_ORDER_EXPORT_ARCHIVE, $destinationFilename);
        } while ($this->filesystem->has($destination));

        $this->filesystem->write($destination, $content);
    }

    public function error(string $content, string $filename): string
    {
        do {
            $prefix              = (new \DateTime())->format('Ymd-His.u');
            $destinationFilename = $prefix . '-' . $filename;
            $destination         = sprintf('%s://%s', FilesystemFactory::FILESYSTEM_ORDER_EXPORT_ERROR, $destinationFilename);
        } while ($this->filesystem->has($destination));

        $this->filesystem->write($destination, $content);

        return $destinationFilename;
    }
}
