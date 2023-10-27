<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\ImportHandler;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use ReiffIntegrations\MeDaPro\Message\PropertyImportMessage;
use ReiffIntegrations\MeDaPro\Struct\CatalogMetadata;
use ReiffIntegrations\MeDaPro\Struct\ProductsStruct;
use ReiffIntegrations\Util\Context\DryRunState;
use ReiffIntegrations\Util\EntitySyncer;
use ReiffIntegrations\Util\Handler\AbstractImportHandler;
use ReiffIntegrations\Util\Mailer;
use ReiffIntegrations\Util\Message\AbstractImportMessage;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class PropertyImportHandler extends AbstractImportHandler
{
    private const DISPLAY_TYPE_DROPDOWN          = 'select'; // Shopware has no constant for this yet in PropertyGroupDefinition

    /** @var string[] */
    private array $updatedPropertyGroups = [];
    /** @var bool[] */
    private array $updatedPropertyGroupOptionIds = [];

    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $configService,
        Mailer $mailer,
        EntitySyncer $entitySyncer,
        Connection $connection,
    ) {
        parent::__construct($logger, $configService, $mailer, $entitySyncer, $connection);
    }

    public function supports(AbstractImportMessage $message): bool
    {
        return $message instanceof PropertyImportMessage;
    }

    /**
     * @param ProductsStruct $struct
     */
    public function getMessage(
        Struct $struct,
        string $archiveFileName,
        CatalogMetadata $catalogMetadata,
        Context $context
    ): PropertyImportMessage
    {
        return new PropertyImportMessage($struct, $archiveFileName, $catalogMetadata, $context);
    }

    public function __invoke(AbstractImportMessage $message): void
    {
        $this->handle($message);
    }

    /**
     * @param PropertyImportMessage $message
     */
    public function handle(AbstractImportMessage $message): void
    {
        $context         = $message->getContext();
        $catalogMetadata = $message->getCatalogMetadata();
        $properties      = $message->getProductsStruct()->getProperties();

        foreach ($properties as $property) {
            $this->updatePropertyGroup($property, $catalogMetadata);
            $this->updatePropertyGroupOptions($property, $catalogMetadata);

            if ($context->hasState(DryRunState::NAME)) {
                dump($this->entitySyncer->getOperations());

                $this->entitySyncer->reset();
            }

            $this->entitySyncer->flush($context);
        }
    }

    protected function getLogIdentifier(): string
    {
        return self::class;
    }

    private function updatePropertyGroupOptions(
        array $property,
        CatalogMetadata $catalogMetadata,
    ): void
    {
        foreach ($property['options'] as $optionId => $optionValue) {
            $updateKey = md5(
                PropertyGroupOptionDefinition::ENTITY_NAME .
                $optionId .
                $catalogMetadata->getLanguageCode()
            );

            if (array_key_exists($updateKey, $this->updatedPropertyGroupOptionIds)) {
                continue;
            }

            $upsertData = [
                'id'           => $optionId,
                'groupId'      => $property['groupId'],
                'translations' => [
                    $catalogMetadata->getLanguageCode() => [
                        'name'     => mb_substr($optionValue, 0, 255),
                        'position' => 1,
                    ],
                ],
            ];

            $this->entitySyncer->addOperation(
                PropertyGroupOptionDefinition::ENTITY_NAME,
                SyncOperation::ACTION_UPSERT,
                $upsertData
            );

            $this->updatedPropertyGroupOptionIds[$updateKey] = true;
        }
    }

    private function updatePropertyGroup(
        array $property,
        CatalogMetadata $catalogMetadata
    ): void
    {
        $updateKey = md5(
            PropertyGroupDefinition::ENTITY_NAME .
            $property['groupId'] .
            $catalogMetadata->getLanguageCode()
        );

        if (array_key_exists($updateKey, $this->updatedPropertyGroups)) {
            return;
        }

        $upsertData = [
            'id' => $property['groupId'],
            'displayType'                => self::DISPLAY_TYPE_DROPDOWN,
            'sortingType'                => PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC,
            'filterable'                 => PropertyGroupDefinition::FILTERABLE,
            'visibleOnProductDetailPage' => PropertyGroupDefinition::VISIBLE_ON_PRODUCT_DETAIL_PAGE,
            'translations'               => [
                $catalogMetadata->getLanguageCode() => [
                    'name'     => $property['name'],
                    'position' => 1,
                ],
            ],
        ];

        $this->entitySyncer->addOperation(
            PropertyGroupDefinition::ENTITY_NAME,
            SyncOperation::ACTION_UPSERT,
            $upsertData
        );

        $this->updatedPropertyGroups[$updateKey] = true;
    }
}
