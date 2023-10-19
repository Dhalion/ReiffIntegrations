const { Application } = Shopware;
const { ApiService } = Shopware.Classes;

class ReiffIntegrationsService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'reiff-integrations') {
        super(httpClient, loginService, apiEndpoint);
    }

    resetOrderExport(orderId) {
        const apiRoute = `_action/${this.getApiBasePath()}/order/reset-export`;

        return this.httpClient.post(
            apiRoute,
            {
                orderId,
            },
            {
                headers: this.getBasicHeaders(),
            },
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}

Application.addServiceProvider('ReiffIntegrationsService', (container) => {
    const initContainer = Application.getContainer('init');

    return new ReiffIntegrationsService(initContainer.httpClient, container.loginService);
});
