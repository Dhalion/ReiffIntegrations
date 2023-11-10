import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import DeviceDetection from 'src/helper/device-detection.helper';
import HttpClient from 'src/service/http-client.service';

/**
 * Add to cart in B2b order detail
 */
export default class AgiqonB2bAddToCartPlugin extends Plugin {

    /**
     * Initialize
     */
    init() {
        // this._client = new HttpClient();
        //
        // try {
        //     this.tableBuyBtn = DomAccess.querySelectorAll(
        //         this.el,
        //         '.table-buy-btn'
        //     );
        // } catch (e) {
        //     return;
        // }
        //
        // this._registerEventListeners();

        this._runObserver();
    }

    /**
     * Register events
     * @private
     */
    _registerEventListeners() {
        const clickEvent = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        console.info(this.tableBuyBtn.length)

        this.tableBuyBtn.forEach((item) => {
            item.addEventListener(clickEvent, this._onSubmitForm.bind(this));
        });
    }

    /**
     * Submit form via link
     * @private
     */
    _onSubmitForm() {
        event.preventDefault();

        try {
            this.cartForm = DomAccess.querySelector(
                event.currentTarget.parentElement,
                'form'
            );
        } catch (e) {
            return;
        }

        let btn = event.currentTarget;
        btn.classList.toggle('disabled');

        let data = new FormData(this.cartForm);
        fetch(this.cartForm.action, {
            method: this.cartForm.method,
            body: data,
            headers: {
                'Accept': 'application/json'
            }
        }).then(response => {
            if (response.ok) {
                btn.classList.toggle('disabled');

                const cartWidgetPlugin = window.PluginManager.getPluginInstances('CartWidget')[0];
                if (cartWidgetPlugin) {
                    cartWidgetPlugin.fetch();
                }
            } else {
                response.json().then(data => {})
            }
        }).catch(error => {});
    }

    /**
     * Observer
     * @private
     */
    _runObserver() {
        // Options for the observer (which mutations to observe)
        const config = { attributes: true, childList: true, subtree: true };

        // Callback function to execute when mutations are observed
        const callback = (mutationList, observer) => {
            for (const mutation of mutationList) {
                if (mutation.type === 'childList') {
                    // this._triggerAccountType();
                    this.openAccordion = DomAccess.querySelector(
                        this.el,
                        '.b2b-accordion b2b-accordion--open'
                    );

                    console.info(this.openAccordion)

                    // this._client = new HttpClient();

                    try {
                        this.tableBuyBtn = DomAccess.querySelectorAll(
                            this.el,
                            '.table-buy-btn'
                        );
                    } catch (e) {
                        return;
                    }

                    console.info(this.tableBuyBtn.length)

                    // this._registerEventListeners();

                }
            }
        };

        // Create an observer instance linked to the callback function
        const observer = new MutationObserver(callback);

        // Start observing the target node for configured mutations
        // observer.observe(this.companyTabContainer, config);
        observer.observe(this.el, config);

        // Later, you can stop observing
        // observer.disconnect();
    }
}
