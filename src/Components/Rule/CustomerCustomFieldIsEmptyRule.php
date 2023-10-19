<?php

declare(strict_types=1);

namespace ReiffIntegrations\Components\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;

class CustomerCustomFieldIsEmptyRule extends Rule
{
    public const REIFF_CUSTOMER_CUSTOM_FIELD_EMPTY = 'reiff_customer_custom_field_empty';

    /**
     * @internal
     */
    public function __construct(
        protected bool $shouldBeEmpty = true,
        protected array $renderedField = [],
        protected string $selectedField = ''
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return $this->shouldBeEmpty;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return $this->shouldBeEmpty;
        }

        $customerFields = $customer->getCustomFields() ?? [];

        if ($this->shouldBeEmpty) {
            return !isset($customerFields[$this->renderedField['name']])
                || $customerFields[$this->renderedField['name']] === ''
                || $this->renderedField['name'] === null;
        }

        return isset($customerFields[$this->renderedField['name']])
            && $customerFields[$this->renderedField['name']] !== ''
            && $customerFields[$this->renderedField['name']] !== null;
    }

    public function getConstraints(): array
    {
        return [
            'shouldBeEmpty' => RuleConstraints::bool(true),
            'selectedField' => [new NotBlank()],
            'renderedField' => $this->getRenderedFieldValueConstraints(),
        ];
    }

    public function getName(): string
    {
        return self::REIFF_CUSTOMER_CUSTOM_FIELD_EMPTY;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->booleanField('shouldBeEmpty');
    }

    /**
     * @return Constraint[]
     */
    private function getRenderedFieldValueConstraints(): array
    {
        if (!\is_array($this->renderedField)
            || !\array_key_exists('type', $this->renderedField)
            || $this->renderedField['type'] !== CustomFieldTypes::BOOL) {
            return [new NotBlank()];
        }

        return [];
    }
}
