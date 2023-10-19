<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Helper;

use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;
use ReiffIntegrations\MeDaPro\DataAbstractionLayer\MediaExtension;
use ReiffIntegrations\MeDaPro\DataAbstractionLayer\ReiffMediaEntity;
use ReiffIntegrations\Util\Context\DryRunState;
use ReiffIntegrations\Util\FilesystemFactory;
use Shopware\Core\Content\Media\Exception\DuplicatedMediaFileNameException;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class MediaHelper
{
    protected EntityRepository $mediaRepository;
    protected MediaService $mediaService;
    protected Filesystem $shopwareTempFilesystem;
    protected MountManager $mountManager;
    protected string $shopwareTempPath;
    /** @var array<string, string[]> */
    private array $mediaIdsByPathAndFolder = [];

    public function __construct(
        EntityRepository $mediaRepository,
        MediaService $mediaService,
        Filesystem $shopwareTempFilesystem,
        MountManager $mountManager,
        string $shopwareTempPath
    ) {
        $this->mediaRepository        = $mediaRepository;
        $this->mediaService           = $mediaService;
        $this->shopwareTempFilesystem = $shopwareTempFilesystem;
        $this->mountManager           = $mountManager;
        $this->shopwareTempPath       = $shopwareTempPath;
    }

    public function getMediaIdByPath(string $path, string $folder, Context $context): ?string
    {
        $path = trim($path);

        if (array_key_exists($folder, $this->mediaIdsByPathAndFolder) && array_key_exists($path, $this->mediaIdsByPathAndFolder[$folder])) {
            return $this->mediaIdsByPathAndFolder[$folder][$path];
        }
        $mediaEntity = $this->fetchExistingMedia($path, $context);
        $remotePath  = $this->getRemotePath($path);

        if ($remotePath === null) {
            // throw new RuntimeException(sprintf('could not find product media at the location: %s', $path));

            // TODO: Remove me, once REIFF has ensured available media is complete
            return null;
        }

        $metadata = $this->getMetadata($remotePath);

        if (!$metadata) {
            throw new \RuntimeException(sprintf('No metadata for media %s found', $remotePath));
        }

        $hash = $this->generateMediaHash($path, $metadata);

        if ($mediaEntity === null) {
            try {
                $mediaId = $this->createMedia($path, $remotePath, $hash, $folder, $context);
            } catch(DuplicatedMediaFileNameException $exception) {
                $fileExtension = pathinfo($remotePath, PATHINFO_EXTENSION);
                $fileName = pathinfo($remotePath, PATHINFO_FILENAME);
                $media = $this->getMediaFromFolder($fileName, $fileExtension, $folder, $context);

                if(!$media) {
                    throw $exception;
                }

                $mediaId = $media->getId();
                $this->updateMedia($media, $path, $remotePath, $hash, $folder, $context, true);
            }
        } else {
            $this->updateMedia($mediaEntity, $path, $remotePath, $hash, $folder, $context);
            $mediaId = $mediaEntity->getId();
        }

        $this->mediaIdsByPathAndFolder[$folder][$path] = $mediaId;

        return $mediaId;
    }

    private function createMedia(
        string $path,
        string $remotePath,
        string $hash,
        string $folder,
        Context $context
    ): string {
        $fileContents = $this->mountManager->read($remotePath);

        if (empty($fileContents)) {
            throw new \RuntimeException(sprintf('could not load file content for media at the location: %s', $path));
        }

        $fileExtension = pathinfo($remotePath, PATHINFO_EXTENSION);

        $extensionData = [
            'hash'         => $hash,
            'originalPath' => $path,
        ];

        $fileName = pathinfo($remotePath, PATHINFO_FILENAME);
        $mimeType = $this->mountManager->mimeType($remotePath) ?: 'application/octet-stream';

        if (!$context->hasState(DryRunState::NAME)) {
            $mediaId = $this->mediaService->saveFile(
                $fileContents,
                $fileExtension,
                $mimeType,
                $fileName,
                $context,
                $folder,
                null,
                false
            );

            $updateData = [
                'id'                           => $mediaId,
                MediaExtension::EXTENSION_NAME => $extensionData,
            ];

            $this->mediaRepository->update([$updateData], $context);
        } else {
            $mediaId = Uuid::randomHex();
        }

        return $mediaId;
    }

    private function updateMedia(
        MediaEntity $mediaEntity,
        string $path,
        string $remotePath,
        string $hash,
        string $folder,
        Context $context,
        bool $force = false
    ): void {
        if (!$this->shouldUpdateMedia($mediaEntity, $hash) && !$force) {
            return;
        }

        $fileContents = $this->mountManager->read($remotePath);

        if (empty($fileContents)) {
            throw new \RuntimeException(sprintf('could not load file content for media at the location: %s', $path));
        }

        $fileName = $mediaEntity->getFileName();
        $mimeType = $this->mountManager->mimeType($remotePath) ?: 'application/octet-stream';

        if (empty($fileName)) {
            $fileName = pathinfo($remotePath, PATHINFO_FILENAME);
        }

        $fileExtension = pathinfo($path, PATHINFO_EXTENSION);

        $extensionData = [
            'hash'         => $hash,
            'originalPath' => $path,
        ];

        if (!$context->hasState(DryRunState::NAME)) {
            try {
                $this->mediaService->saveFile(
                    $fileContents,
                    $fileExtension,
                    $mimeType,
                    $fileName,
                    $context,
                    $folder,
                    $mediaEntity->getId(),
                    false
                );
            }  catch(\Exception) {
            }

            $updateData = [
                'id'                           => $mediaEntity->getId(),
                MediaExtension::EXTENSION_NAME => $extensionData,
            ];

            $this->mediaRepository->update([$updateData], $context);
        }
    }

    private function shouldUpdateMedia(MediaEntity $mediaEntity, string $hash): bool
    {
        /** @var ?ReiffMediaEntity $extension */
        $extension = $mediaEntity->getExtension(MediaExtension::EXTENSION_NAME);

        if (!$extension) {
            return true;
        }

        if (empty($extension->getHash())) {
            return true;
        }

        if ($extension->getHash() === $hash) {
            return false;
        }

        return true;
    }

    private function generateMediaHash(string $path, array $metadata): string
    {
        $parts = [
            $metadata['fileSize'] ?? Uuid::randomHex(),
            $metadata['lastModified'] ?? Uuid::randomHex(),
            $path,
        ];

        return md5(json_encode($parts, JSON_THROW_ON_ERROR));
    }

    private function getRemotePath(string $path): ?string
    {
        $currentPath = sprintf(
            '%s://%s',
            FilesystemFactory::FILESYSTEM_PRODUCT_IMPORT_MEDIA,
            str_replace('\\', '/', $path)
        );

        return $this->mountManager->has($currentPath) ? $currentPath : null;
    }

    private function fetchExistingMedia(string $path, Context $context): ?MediaEntity
    {
        $mediaCriteria = new Criteria();
        $mediaCriteria->addFilter(new EqualsFilter(sprintf('%s.originalPath', MediaExtension::EXTENSION_NAME), $path));
        $mediaCriteria->setLimit(1);

        return $this->mediaRepository->search($mediaCriteria, $context)->first();
    }

    private function getMetadata(string $path): array {
        return [
            'lastModified' => $this->mountManager->lastModified($path),
            'fileSize' => $this->mountManager->fileSize($path),
            'visibility' => $this->mountManager->visibility($path),
            'mimeType' => $this->mountManager->mimeType($path),
        ];
    }

    private function getMediaFromFolder(string $fileName, string $fileExtension, string $folder, Context $context): ?MediaEntity
    {
        $criteria = (new Criteria())
            ->addAssociation('mediaFolder.defaultFolder')
            ->addFilter(new AndFilter([
                new EqualsFilter('fileName', $fileName),
                new EqualsFilter('fileExtension', $fileExtension),
                new EqualsFilter('mediaFolder.defaultFolder.entity', $folder)
            ]));

        return $this->mediaRepository->search($criteria, $context)->first();
    }

    private function deleteMediaInFolder(string $fileName, string $fileExtension, string $folder, Context $context): void
    {
        $criteria = (new Criteria())
            ->addAssociation('mediaFolder.defaultFolder')
            ->addFilter(new AndFilter([
                new EqualsFilter('fileName', $fileName),
                new EqualsFilter('fileExtension', $fileExtension),
                new EqualsFilter('mediaFolder.defaultFolder.entity', $folder)
            ]));

        $mediaId = $this->mediaRepository->searchIds($criteria, $context)->firstId();

        if($mediaId) {
            $this->mediaRepository->delete([
                [
                    $mediaId
                ]
            ], $context);
        }
    }
}
