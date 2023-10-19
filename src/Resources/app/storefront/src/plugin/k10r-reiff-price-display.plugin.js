import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import BootstrapUtil from 'src/utility/bootstrap/bootstrap.util';

export default class K10rReiffPriceDisplayPlugin extends Plugin {
    static options = {
        errorSelector: '.price--error',
    };

    init() {
        this._displayError();
        new BootstrapUtil.initTooltip(); // reinitialize tooltip plugin for AJAX calls
    }

    _displayError() {
        const errorElement = DomAccess.querySelector(this.el, this.options.errorSelector);

        if (!errorElement) {
            return;
        }

        const title = errorElement.getAttribute('title');
        const originalTitle = errorElement.getAttribute('data-original-title');

        if ((title !== null && title !== '') || (originalTitle !== null && originalTitle !== '')) {
            this.el.classList.remove('d-none');
        }
    }
}
