<?php

namespace ReiffIntegrations\MeDaPro\Finder;

use ReiffIntegrations\MeDaPro\Parser\JsonParser;
use ReiffIntegrations\MeDaPro\Struct\ImportFile;
use Shopware\Core\Defaults;
use Shopware\Core\System\Language\LanguageLoaderInterface;

class Finder
{
    public function __construct(
        private readonly LanguageLoaderInterface $languageLoader,
        private readonly JsonParser $jsonParser
    ) {
    }

    /**
     * @param string $importBasePath
     *
     * @return ImportFile[]
     */
    public function fetchImportFiles(string $importBasePath): array
    {
        $systemLanguageCode = $this->getSystemLanguageCode();

        $finder = new \Symfony\Component\Finder\Finder();
        $finder->files()->in($importBasePath);

        $files = [];
        foreach ($finder as $file) {
            $position = 100;

            $metadata = $this->jsonParser->getCatalogMetadata(
                $file->getFilenameWithoutExtension(),
                $systemLanguageCode
            );

            if ($systemLanguageCode === $metadata->getLanguageCode()) {
                $position -= 80;
            }

            if ($metadata->getSortimentId() !== null) {
                $position += 20;
            }

            $files[$file->getFilenameWithoutExtension()] = new ImportFile(
                $file,
                $metadata,
                $position
            );
        }

        uasort($files, static function (ImportFile $a, ImportFile $b): int {
            return $a->getPosition() <=> $b->getPosition();
        });

        $this->validImportFiles($files);

        return $files;
    }

    private function getSystemLanguageCode(): string
    {
        $languages = $this->languageLoader->loadLanguages();

        if (empty($languages[Defaults::LANGUAGE_SYSTEM]['code'])) {
            throw new \LogicException('No system language found');
        }

        return $languages[Defaults::LANGUAGE_SYSTEM]['code'];
    }



    /**
     * @param ImportFile[] $importFiles
     */
    private function validImportFiles(array $importFiles): void
    {
        foreach ($importFiles as $importFile) {
            $catalogMetadata = $importFile->getCatalogMetadata();

            if ($catalogMetadata->isSystemLanguage()) {
                continue;
            }

            foreach ($importFiles as $otherFile) {
                if ($otherFile->getCatalogMetadata()->getCatalogId() === $catalogMetadata->getCatalogId()) {
                    continue;
                }

                throw new \LogicException(sprintf(
                    'The SystemLanguage Catalog (%s) for the file %s is missing',
                    $catalogMetadata->getSystemLanguageCode(),
                    $importFile->getFile()->getFilename()
                ));
            }
        }
    }
}
