<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\ImportHandler;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use ReiffIntegrations\MeDaPro\Message\PropertyImportMessage;
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
    public const PROPERTY_GROUP_OPTION_ID_PREFIX = PropertyGroupOptionDefinition::ENTITY_NAME;
    private const BATCH_SIZE                     = 100;
    private const PROPERTY_GROUP_ID_PREFIX       = PropertyGroupDefinition::ENTITY_NAME;
    private const DISPLAY_TYPE_DROPDOWN          = 'select'; // Shopware has no constant for this yet in PropertyGroupDefinition

    /** @var bool[] */
    private array $existingPropertyGroupIds = [];
    /** @var bool[] */
    private array $existingPropertyGroupOptionIds = [];

    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $configService,
        Mailer $mailer,
        EntitySyncer $entitySyncer,
        Connection $connection,
        private readonly EntityRepository $propertyGroupRepository,
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
    public function getMessage(Struct $struct, string $archiveFileName, Context $context): PropertyImportMessage
    {
        return new PropertyImportMessage($struct, $archiveFileName, $context);
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
        $this->connection->beginTransaction();

        $context            = $message->getContext();
        $properties         = $message->getProductsStruct()->getProperties();
        $propertyBatchCount = 0;

        foreach ($properties as $key => $property) {
            ++$propertyBatchCount;

            $this->updatePropertyOptions($key, $property['name'], $property['options'], $context);

            if ($propertyBatchCount >= self::BATCH_SIZE) {
                if ($context->hasState(DryRunState::NAME)) {
                    dump($this->entitySyncer->getOperations());
                }

                $this->entitySyncer->flush($context);
                $propertyBatchCount = 0;
            }
        }

        if ($propertyBatchCount > 0) {
            if ($context->hasState(DryRunState::NAME)) {
                dump($this->entitySyncer->getOperations());
            }

            $this->entitySyncer->flush($context);
        }

        if ($context->hasState(DryRunState::NAME)) {
            $this->connection->rollBack();
        } else {
            $this->connection->commit();
        }
    }

    protected function getLogIdentifier(): string
    {
        return self::class;
    }

    private function updatePropertyOptions(string $propertyKey, string $propertyName, array $options, Context $context): void
    {
        $optionData = [];
        foreach ($options as $optionValue) {
            $optionId = md5(sprintf('%s-%s-%s', self::PROPERTY_GROUP_OPTION_ID_PREFIX, $propertyKey, $optionValue));

            if ($this->propertyGroupOptionExists($optionId)) {
                continue;
            }

            $optionData[] = [
                'id'           => $optionId,
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name'     => mb_substr($optionValue, 0, 255),
                        'position' => 1,
                    ],
                ],
            ];

            $this->existingPropertyGroupOptionIds[$optionId] = true;
        }

        $data = [
            'id'      => md5(sprintf('%s-%s', self::PROPERTY_GROUP_ID_PREFIX, $propertyKey)),
            'options' => $optionData,
        ];

        if (!$this->propertyGroupExists($data['id'])) {
            $data = array_merge($data, [
                'displayType'                => self::DISPLAY_TYPE_DROPDOWN,
                'sortingType'                => PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC,
                'filterable'                 => PropertyGroupDefinition::FILTERABLE,
                'visibleOnProductDetailPage' => PropertyGroupDefinition::VISIBLE_ON_PRODUCT_DETAIL_PAGE,
                'translations'               => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name'     => $propertyName,
                        'position' => 1,
                    ],
                ],
            ]);
        }

        if (count($data['options']) === 0) {
            return;
        }

        if ($context->hasState(DryRunState::NAME)) {
            $this->entitySyncer->addOperation(PropertyGroupDefinition::ENTITY_NAME, SyncOperation::ACTION_UPSERT, $data);
        } else {
            $this->propertyGroupRepository->upsert([$data], $context);
        }

        $this->existingPropertyGroupIds[$data['id']] = true;
    }

    private function propertyGroupExists(string $propertyGroupId): bool
    {
        if (!array_key_exists($propertyGroupId, $this->existingPropertyGroupIds) && $this->connection->fetchOne('SELECT 1 FROM `property_group` WHERE id = UNHEX(:id)', ['id' => $propertyGroupId])) {
            $this->existingPropertyGroupIds[$propertyGroupId] = true;
        }

        return array_key_exists($propertyGroupId, $this->existingPropertyGroupIds);
    }

    private function propertyGroupOptionExists(string $propertyGroupOptionId): bool
    {
        if (!array_key_exists($propertyGroupOptionId, $this->existingPropertyGroupOptionIds) && $this->connection->fetchOne('SELECT 1 FROM `property_group_option` WHERE id = UNHEX(:id)', ['id' => $propertyGroupOptionId])) {
            $this->existingPropertyGroupOptionIds[$propertyGroupOptionId] = true;
        }

        return array_key_exists($propertyGroupOptionId, $this->existingPropertyGroupOptionIds);
    }
}
