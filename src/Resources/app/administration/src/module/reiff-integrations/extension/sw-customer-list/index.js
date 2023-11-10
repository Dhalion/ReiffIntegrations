import template from './sw-customer-list.html.twig';

const { Component } = Shopware;

Component.override('sw-customer-list', {
    template,

    methods: {
        getCustomerColumns() {
            const columns = this.$super('getCustomerColumns');

            columns.push({
                property: 'salesOrganisation',
                dataIndex: 'extensions.reiffCustomer.salesOrganisation',
                inlineEdit: 'string',
                label: 'ReiffIntegrations.customer.list.tableHeader.salesOrganisationLabel',
                allowResize: true,
                width: '100px',
            });

            columns.push({
                property: 'debtorNumber',
                dataIndex: 'extensions.reiffCustomer.debtorNumber',
                inlineEdit: 'string',
                label: 'ReiffIntegrations.customer.list.tableHeader.debtorNumberLabel',
                allowResize: true,
                width: '100px',
            });

            columns.push({
                property: 'doubleOptInRegistration',
                dataIndex: 'doubleOptInRegistration',
                inlineEdit: 'string',
                label: 'ReiffIntegrations.customer.list.tableHeader.doubleOptInRegistrationLabel',
                allowResize: true,
                width: '50px',
            });

            columns.push({
                property: 'isOciCustomer',
                dataIndex: 'customFields.reiff_customer_is_oci',
                inlineEdit: 'string',
                label: 'ReiffIntegrations.customer.list.tableHeader.isOciCustomerLabel',
                allowResize: true,
                width: '50px',
            });

            columns.push({
                property: 'company',
                dataIndex: 'defaultBillingAddress.company',
                inlineEdit: 'string',
                label: 'ReiffIntegrations.customer.list.tableHeader.company',
                allowResize: true,
                width: '50px',
            });

            return columns;
        },
    },
});
