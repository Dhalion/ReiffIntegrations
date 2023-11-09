import K10rReiffAsyncSapOfferPlugin from './plugin/k10r-reiff-async-sap-offer.plugin';
import K10rReiffAvailabilityDisplayPlugin from './plugin/k10r-reiff-availability-display.plugin';
import K10rReiffPriceDisplayPlugin from './plugin/k10r-reiff-price-display.plugin';
import AgiqonB2bAddToCartPlugin from './plugin/agiqon-b2b-add-to-cart';
import AgiqonB2bOrderListPlugin from './plugin/agiqon-b2b-order-list/agiqon-b2b-order-list.plugin';

const { PluginManager } = window;

PluginManager.register('K10rReiffAsyncSapOfferPlugin', K10rReiffAsyncSapOfferPlugin, '[data-k10r-reiff-async-sap-offer-plugin]');
PluginManager.register('K10rReiffAvailabilityDisplayPlugin', K10rReiffAvailabilityDisplayPlugin, document.body);
PluginManager.register('K10rReiffPriceDisplayPlugin', K10rReiffPriceDisplayPlugin, '[data-k10r-reiff-price-display-plugin]');
PluginManager.register('AgiqonB2bAddToCart', AgiqonB2bAddToCartPlugin, '[data-b2b-add-to-cart]');
PluginManager.register('AgiqonB2bOrderList', AgiqonB2bOrderListPlugin, '[data-b2b-order-list]');

if (module.hot) {
    module.hot.accept();
}
