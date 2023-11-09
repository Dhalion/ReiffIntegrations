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
        this._client = new HttpClient();

        try {
            this.tableBuyBtn = DomAccess.querySelectorAll(
                this.el,
                '.table-buy-btn'
            );
        } catch (e) {
            return;
        }

        this._registerEventListeners();
    }

    /**
     * Register events
     * @private
     */
    _registerEventListeners() {
        const clickEvent = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

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
}
