import './api/reiff-integrations.service';
import './module/reiff-integrations';
import './decorator/rule-condition-service-decoration';

const module = Shopware.Module.getModuleByEntityName('customer');

if (module?.manifest?.defaultSearchConfiguration) {
    module.manifest.defaultSearchConfiguration = {
        ...module.manifest.defaultSearchConfiguration,
        extensions: {
            // In case some other plugin has already done this trick; we do not want to remove theirs.
            ...(module.manifest.defaultSearchConfiguration.extensions ?? {}),
            // Add our extension fields to the omnisearch
            reiffCustomer: {
                debtorNumber: {
                    _searchable: true,
                    _score: 500,
                },
            },
        },
    };
}
