<?php

declare(strict_types=1);

namespace ReiffIntegrations\Util;

use League\Flysystem\MountManager;
use ReiffIntegrations\Util\Context\DebugState;
use ReiffIntegrations\Util\Context\DryRunState;
use Shopware\Core\Framework\Context;

class ImportArchiver
{
    private const ARCHIVE_CLEANUP_PERIOD = '2 days ago';

    public function __construct(
        private readonly MountManager $filesystem,
    ) {
    }

    public function archive(string $filename, Context $context): string
    {
        $source = sprintf('%s://%s', FilesystemFactory::FILESYSTEM_PRODUCT_IMPORT_SOURCE, $filename);

        do {
            $prefix              = (new \DateTime())->format('Ymd-His.u');
            $destinationFilename = $prefix . '-' . $filename;
            $destination         = sprintf('%s://%s', FilesystemFactory::FILESYSTEM_PRODUCT_IMPORT_ARCHIVE, $destinationFilename);
        } while ($this->filesystem->has($destination));

        if ($context->hasState(DebugState::NAME)) {
            $destination = sprintf('%s://%s', FilesystemFactory::FILESYSTEM_PRODUCT_IMPORT_SOURCE, $destinationFilename);
        }

        if (!$context->hasState(DryRunState::NAME)) {
            $this->filesystem->move($source, $destination);
        }

        return $destinationFilename;
    }

    public function error(string $filename, Context $context): string
    {
        $source = sprintf('%s://%s', FilesystemFactory::FILESYSTEM_PRODUCT_IMPORT_SOURCE, $filename);

        if (!$this->filesystem->has($source)) {
            $source = sprintf('%s://%s', FilesystemFactory::FILESYSTEM_PRODUCT_IMPORT_ARCHIVE, $filename);
        }

        if (!$this->filesystem->has($source)) {
            $source = sprintf('%s://%s', FilesystemFactory::FILESYSTEM_PRODUCT_IMPORT_ERROR, $filename);
        }

        $destination = sprintf('%s://%s', FilesystemFactory::FILESYSTEM_PRODUCT_IMPORT_ERROR, $filename);

        if ($context->hasState(DebugState::NAME)) {
            $destination = sprintf('%s://%s', FilesystemFactory::FILESYSTEM_PRODUCT_IMPORT_SOURCE, $filename);
        }

        if ($this->filesystem->has($source) && !$this->filesystem->has($destination) && !$context->hasState(DryRunState::NAME)) {
            $this->filesystem->move($source, $destination);
        }

        return $filename;
    }

    public function cleanup(Context $context): void
    {
        $cutoff = (new \DateTimeImmutable())->modify(self::ARCHIVE_CLEANUP_PERIOD);

        foreach ($this->filesystem->listContents(sprintf('%s://', FilesystemFactory::FILESYSTEM_PRODUCT_IMPORT_ARCHIVE)) as $file) {
            if ($file->type() !== 'file') {
                continue;
            }

            $lastModified = \DateTimeImmutable::createFromFormat('U', (string) $file->lastModified());

            if ($cutoff > $lastModified && !$context->hasState(DryRunState::NAME)) {
                $this->filesystem->delete(sprintf('%s://%s', FilesystemFactory::FILESYSTEM_PRODUCT_IMPORT_ARCHIVE, $file->path()));
            }
        }
    }

}
