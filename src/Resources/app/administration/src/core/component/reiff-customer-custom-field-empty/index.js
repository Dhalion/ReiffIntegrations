import template from './reiff-customer-custom-field-empty.html.twig';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { Criteria } = Shopware.Data;

Shopware.Component.extend('reiff-customer-custom-field-empty', 'sw-condition-base', {
    template,

    inject: ['repositoryFactory', 'feature'],

    mixins: [
        Mixin.getByName('sw-inline-snippet'),
    ],

    computed: {
        customFieldCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('customFieldSet');
            criteria.addFilter(Criteria.equals('customFieldSet.relations.entityName', 'customer'));
            criteria.addSorting(Criteria.sort('customFieldSet.name', 'ASC'));

            return criteria;
        },

        selectValues() {
            return [
                {
                    label: this.$tc('global.sw-condition.condition.yes'),
                    value: true,
                },
                {
                    label: this.$tc('global.sw-condition.condition.no'),
                    value: false,
                },
            ];
        },

        renderedField: {
            get() {
                this.ensureValueExist();
                return this.condition.value.renderedField;
            },
            set(renderedField) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, renderedField };
            },
        },

        selectedField: {
            get() {
                this.ensureValueExist();
                return this.condition.value.selectedField;
            },
            set(selectedField) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, selectedField };
            },
        },

        shouldBeEmpty: {
            get() {
                this.ensureValueExist();

                if (this.condition.value.shouldBeEmpty == null) {
                    this.condition.value.shouldBeEmpty = false;
                }

                return this.condition.value.shouldBeEmpty;
            },
            set(shouldBeEmpty) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, shouldBeEmpty };
            },
        },

        ...mapPropertyErrors('condition', [
            'value.selectedField',
        ]),
    },

    methods: {
        onFieldChange(id) {
            if (this.$refs.selectedField.resultCollection.has(id)) {
                this.renderedField = this.$refs.selectedField.resultCollection.get(id);
            } else {
                this.renderedField = null;
            }

            this.operator = null;
        },
    },
});
