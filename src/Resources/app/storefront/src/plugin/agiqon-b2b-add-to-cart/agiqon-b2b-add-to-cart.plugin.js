import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import DeviceDetection from 'src/helper/device-detection.helper';

/**
 * Add search and sorting to orders list
 */
export default class AgiqonB2bAddToCartPlugin extends Plugin {

    /**
     * Initialize
     */
    init() {
        this._registerEventListeners();
    }

    /**
     * Register events
     * @private
     */
    _registerEventListeners() {
        console.log('b2b add to cart')
    }
}
