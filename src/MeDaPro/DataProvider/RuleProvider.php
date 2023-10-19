<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\DataProvider;

use ReiffIntegrations\Components\Rule\CustomerCustomFieldIsEmptyRule;
use ReiffIntegrations\Installer\CustomFieldInstaller;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class RuleProvider
{
    private array $ruleIds = [];

    public function __construct(private readonly EntityRepository $ruleConditionRepository)
    {
    }

    public function getRuleIdBySortimentId(?string $sortimentId, Context $context): ?string
    {
        $sortimentKey = $sortimentId ?: 'default';

        if (isset($this->ruleIds[$sortimentKey])) {
            return $this->ruleIds[$sortimentKey] ?: null;
        }

        $criteria = new Criteria();

        if ($sortimentId === null) {
            $criteria->addFilter(
                new EqualsFilter('type', CustomerCustomFieldIsEmptyRule::REIFF_CUSTOMER_CUSTOM_FIELD_EMPTY),
                new EqualsFilter('value.selectedField', CustomFieldInstaller::CUSTOMER_ASSORTMENT_ID_UUID)
            );
        }

        if ($sortimentId !== null) {
            $criteria->addFilter(
                new EqualsFilter('type', 'customerCustomField'),
                new EqualsFilter('value.renderedFieldValue', $sortimentId)
            );
        }

        /** @var null|RuleConditionEntity $ruleCondition */
        $ruleCondition = $this->ruleConditionRepository->search($criteria, $context)->first();

        $this->ruleIds[$sortimentKey] = $ruleCondition !== null ? $ruleCondition->getRuleId() : false;

        return $this->ruleIds[$sortimentKey] ?: null;
    }
}
