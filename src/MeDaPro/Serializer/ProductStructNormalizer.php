<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Serializer;

use ReiffIntegrations\MeDaPro\Struct\ProductCollection;
use ReiffIntegrations\MeDaPro\Struct\ProductStruct;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ProductStructNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function normalize($object, string $format = null, array $context = []): array
    {
        return [
            'productNumber'      => $object->getProductNumber(),
            'variants'           => $this->normalizeVariants($object->getVariants()),
            'data'               => $object->getData(),
            'filePath'           => $object->getFilePath(),
            'sortimentId'        => $object->getSortimentId(),
            'catalogId'          => $object->getCatalogId(),
            'crossSellingGroups' => $object->getCrossSellingGroups(),
        ];
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return $data instanceof ProductStruct;
    }

    public function denormalize($data, string $type, string $format = null, array $context = []): ProductStruct
    {
        $variantsCollection = $this->denormalizeVariants($data['variants']);

        return new ProductStruct(
            $data['productNumber'],
            $variantsCollection,
            $data['data'],
            $data['filePath'],
            $data['sortimentId'],
            $data['catalogId'],
            $data['crossSellingGroups'] ?? []
        );
    }

    public function supportsDenormalization($data, string $type, string $format = null, array $context = []): bool
    {
        return $type === ProductStruct::class;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }

    private function normalizeVariants(ProductCollection $variantsCollection): array
    {
        $variantsData = [];

        foreach ($variantsCollection as $variant) {
            $variantsData[] = [
                'productNumber'      => $variant->getProductNumber(),
                'variants'           => $this->normalizeVariants($variant->getVariants()),
                'data'               => $variant->getData(),
                'filePath'           => $variant->getFilePath(),
                'sortimentId'        => $variant->getSortimentId(),
                'catalogId'          => $variant->getCatalogId(),
                'crossSellingGroups' => $variant->getCrossSellingGroups(),
            ];
        }

        return $variantsData;
    }

    private function denormalizeVariants(array $variantsData): ProductCollection
    {
        $variantsCollection = new ProductCollection();

        foreach ($variantsData as $variantData) {
            $nestedVariants = isset($variantData['variants'])
                ? $this->denormalizeVariants($variantData['variants'])
                : new ProductCollection();

            $variant = new ProductStruct(
                $variantData['productNumber'],
                $nestedVariants,
                $variantData['data'],
                $variantData['filePath'],
                $variantData['sortimentId'],
                $variantData['catalogId'],
                $variantData['crossSellingGroups'] ?? []
            );
            $variantsCollection->add($variant);
        }

        return $variantsCollection;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ProductStruct::class => true];
    }
}
