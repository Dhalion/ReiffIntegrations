import '../core/component/reiff-customer-custom-field-empty';

Shopware.Application.addServiceProviderDecorator('ruleConditionDataProviderService', (ruleConditionService) => {
    ruleConditionService.addCondition('reiff_customer_custom_field_empty', {
        component: 'reiff-customer-custom-field-empty',
        label: 'ReiffIntegrations.rule.reiffCustomerCustomFieldEmpty.label',
        scopes: ['customer'],
    });

    return ruleConditionService;
});
