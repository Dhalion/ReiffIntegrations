import template from './sw-order-detail-general.html.twig';

const { Component, Mixin } = Shopware;

Component.override('sw-order-detail-general', {
    template,

    inject: ['ReiffIntegrationsService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isResetting: false
        };
    },

    methods: {
        resetOrderExport() {
            this.isResetting = true;

            this.ReiffIntegrationsService.resetOrderExport(this.orderId).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('ReiffIntegrations.order.actions.resetExport.title'),
                    message: this.$tc('ReiffIntegrations.order.messages.successResetExport'),
                });
            }).catch((error) => {
                this.createNotificationError({
                    title: this.$tc('ReiffIntegrations.order.actions.resetExport.title'),
                    message: error.message,
                });
            }).finally(() => {
                this.isResetting = false;
            });
        },
    },
});
