import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import DeviceDetection from 'src/helper/device-detection.helper';

/**
 * Add search and sorting to orders list
 */
export default class AgiqonB2bOrderListPlugin extends Plugin {

    /**
     * Plugin options
     * @type {{accordionBodySel: string, accordionTitleSel: string, searchResetSel: string, accordionSel: string, searchInputSel: string}}
     */
    static options = {
        searchInputSel: '.b2b-order-search-input',
        searchResetSel: '.b2b-order-search-reset',
        accordionSel: '.b2b-accordion',
        accordionTitleSel: '.b2b-accordion__title',
        accordionBodySel: '.b2b-accordion__body'
    };

    /**
     * Initialize
     */
    init() {
        try {
            this.searchInput = DomAccess.querySelector(
                document,
                this.options.searchInputSel
            );

            this.searchReset = DomAccess.querySelector(
                document,
                this.options.searchResetSel
            );

            this.accordionItems = DomAccess.querySelectorAll(
                document,
                this.options.accordionSel
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

        this.searchInput.addEventListener('input', this._onSearchOrder.bind(this));
        this.searchReset.addEventListener(clickEvent, this._onSearchReset.bind(this));
    }

    /**
     * Search in order title and details
     * @private
     */
    _onSearchOrder() {
        let searchQuery,
            searchContent,
            accordionTitle,
            accordionBody;

        searchQuery = this.searchInput.value.toUpperCase();

        this.accordionItems.forEach((item) => {
            try {
                accordionTitle = DomAccess.querySelector(
                    item,
                    this.options.accordionTitleSel
                );
            } catch (e) {
                return;
            }

            try {
                accordionBody = DomAccess.querySelector(
                    item,
                    this.options.accordionBodySel
                );
            } catch (e) {
                return;
            }

            searchContent = accordionTitle.innerHTML + accordionBody.innerHTML;
            searchContent = searchContent.toUpperCase();

            if (searchContent.includes(searchQuery)) {
                item.style.display = "";
            } else {
                item.style.display = "none";
            }
        });
    }

    /**
     * Reset search
     * @private
     */
    _onSearchReset() {
        this.accordionItems.forEach((item) => {
            item.style.display = "";
        })
    }
}
