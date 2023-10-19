import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';
import DomAccess from 'src/helper/dom-access.helper';

export default class K10rReiffAvailabilityDisplayPlugin extends Plugin {
    static options = {
        availabilitySelector: '[data-k10r-reiff-availability-display-plugin]',
        availabilityOptionsKey: 'data-k10r-reiff-availability-display-plugin-options',
        loadableClass: 'availability-not-loaded',
        errorSelector: '.availability--error',
        wrapperSelector: '.availability-livedata',
        contentSelector: '.live-content',
        fallbackSelector: '.availability-fallback',
        hideClass: 'd-none',
        codeClass: 'k10r-reiff-integration-delivery-information-code-',
        requestUrl: '',
        productNumber: '',
    };

    static productDeliveryData = [];

    init() {
        this.productDeliveryData = [];
        this.client = new HttpClient();

        this.prepareOptions();
        this.loadAvailabilities();
        this.registerObserver();
    }

    prepareOptions() {
        const availabilityElements = DomAccess.querySelectorAll(this.el, this.options.availabilitySelector, false) || [];

        availabilityElements.forEach((availabilityElement) => {
            if (!!this.options.requestUrl) {
                return;
            }

            const options = DomAccess.getDataAttribute(availabilityElement, this.options.availabilityOptionsKey, false);

            if (!!options) {
                this.options.requestUrl = options.requestUrl;
            }
        });
    }

    loadAvailabilities() {
        const availabilityDisplayPlugins = DomAccess.querySelectorAll(this.el, this.options.availabilitySelector, false) || [];
        const productNumbersToFetch = [];

        availabilityDisplayPlugins.forEach((availabilityDisplayEl) => {
            const options = DomAccess.getDataAttribute(availabilityDisplayEl, this.options.availabilityOptionsKey, false);

            if (!!options && availabilityDisplayEl.classList.contains(this.options.loadableClass) && options.productNumber && options.productNumber.length > 0) {
                productNumbersToFetch.push(options.productNumber);
                this.productDeliveryData.push({ productNumber: options.productNumber, el: availabilityDisplayEl });
            }
        });

        this._requestAvailabilityDataForProducts(productNumbersToFetch);
    }

    registerObserver() {
        let observerRegistered = false;
        const observer = new MutationObserver(() => {
            if (document.getElementsByClassName(this.options.loadableClass).length > 0) {
                this.loadAvailabilities();
            }
        });

        // we don't want >1k triggers for this functionality to start. Reduce triggers to around 500 on detail pages
        window.addEventListener('load', () => {
            if (!observerRegistered) {
                observer.observe(this.el, { childList: true, subtree: true });
                observerRegistered = true;
            }
        });
    }

    /**
     *
     * @param {(string)[]} productNumbers
     * @param {function} callback
     * @private
     */
    _requestAvailabilityDataForProducts(productNumbers) {
        if (productNumbers.length < 1 || this.options.requestUrl.length < 1) {
            this._handleProductAvailability(JSON.stringify({ success: false, content: null }));

            return;
        }

        if (!!this.requestTimeoutId) {
            window.clearTimeout(this.requestTimeoutId);
        }

        this.requestTimeoutId = window.setTimeout(() => {
            this.client.post(
                this.options.requestUrl.toLowerCase(),
                JSON.stringify({
                    productNumbers
                }),
                this._handleProductAvailability.bind(this),
            );
        }, 100);
    }

    /**
     * @private
     */
    _handleProductAvailability(response) {
        const parsedResponse = JSON.parse(response);

        this.productDeliveryData.forEach((mapItem) => {
            if (!parsedResponse.success) {
                this.el.querySelectorAll('.' + this.options.loadableClass).forEach((element) => {
                    element.classList.remove(this.options.loadableClass);
                });
                this._displayData(mapItem, true);

                return;
            }

            const wrapperEl = mapItem.el.querySelector(this.options.wrapperSelector);
            const contentEl = mapItem.el.querySelector(this.options.contentSelector);

            if (parsedResponse && parsedResponse.results && parsedResponse.results.length > 0) {
                parsedResponse.results.forEach((curResult) => {
                    if (curResult.productNumber === mapItem.productNumber) {
                        mapItem.el.classList.remove(this.options.loadableClass);
                        if (!!wrapperEl) {
                            wrapperEl.classList.add(this.options.codeClass + curResult.code);
                        }

                        if (!!contentEl) {
                            contentEl.innerText = curResult.translatedResult;
                            this._displayData(mapItem.el);

                            return;
                        }

                        this._displayData(mapItem.el, true);
                    }
                });
            }
        });
    }

    _displayData(element, fallback = false) {
        const liveDataEl = element.querySelector(this.options.wrapperSelector);
        const fallbackEl = element.querySelector(this.options.fallbackSelector);

        if (!!liveDataEl && !!fallbackEl) {
            if (fallback) {
                fallbackEl.classList.remove(this.options.hideClass);
                liveDataEl.classList.add(this.options.hideClass);

                return;
            }

            liveDataEl.classList.remove(this.options.hideClass);
            fallbackEl.classList.add(this.options.hideClass);
        }
    }
}
