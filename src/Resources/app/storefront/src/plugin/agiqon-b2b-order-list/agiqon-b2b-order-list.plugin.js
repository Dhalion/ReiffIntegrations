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

        // search selectors
        searchInputSel: '.b2b-order-search-input',
        searchResetSel: '.b2b-order-search-reset',

        // sorting and accordion selectors
        accordionContSel: '.b2b-order-search-results',
        accordionSel: '.b2b-accordion',
        accordionTitleSel: '.b2b-accordion__title',
        accordionBodySel: '.b2b-accordion__body',
        btnSortSel: '.btn-sort',
        btnSortActiveCls: 'active-sorting'
    };

    /**
     * Initialize
     */
    init() {
        try {

            // search elements
            this.searchInput = DomAccess.querySelector(
                document,
                this.options.searchInputSel
            );
            this.searchReset = DomAccess.querySelector(
                document,
                this.options.searchResetSel
            );

            // sorting elements
            this.accordionItems = DomAccess.querySelectorAll(
                document,
                this.options.accordionSel
            );
            this.sortingButtons = DomAccess.querySelectorAll(
                document,
                this.options.btnSortSel
            );
        } catch (e) {
            return;
        }

        this._registerEventListeners();
        this._runObserver();
    }

    /**
     * Register events
     * @private
     */
    _registerEventListeners() {
        const clickEvent = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        this.searchInput.addEventListener('input', this._onSearchOrder.bind(this));
        this.searchReset.addEventListener(clickEvent, this._onSearchReset.bind(this));

        this.sortingButtons.forEach((item) => {
            item.addEventListener(clickEvent, this._sortBy.bind(this));
        });
    }

    /**
     * Re-initialize plugins
     * @private
     */
    _initPlugins() {
        window.PluginManager.initializePlugins();
    }

    /**
     * Do sorting by event target
     * @private
     */
    _sortBy() {
        let container,
            sortingType;

        try {
            container = DomAccess.querySelector(
                document,
                this.options.accordionContSel
            );
        } catch (e) {
            return;
        }

        // get accordion elements
        const elements = Array.from(container.children);

        // set sort button active class
        this.sortingButtons.forEach((item) => {
            if (event.currentTarget.dataset.sortingType !== item.dataset.sortingType) {
                item.classList.remove(this.options.btnSortActiveCls);
                item.setAttribute('data-sorting-direction', 'default');
            }
        });
        event.currentTarget.classList.add(this.options.btnSortActiveCls);

        // update sorting button sorting direction
        if (event.currentTarget.dataset.sortingDirection === 'default') {
            event.currentTarget.setAttribute('data-sorting-direction', 'asc');
        } else if (event.currentTarget.dataset.sortingDirection === 'asc') {
            event.currentTarget.setAttribute('data-sorting-direction', 'desc');
        } else {
            this.sortingButtons.forEach((item) => {
                item.setAttribute('data-sorting-direction', 'default');
            });

            let sorted = elements.sort((a, b) => {
                let dateA = new Date(a.dataset.sortByDate);
                let dateB = new Date(b.dataset.sortByDate);
                return dateB - dateA;
            });

            container.innerHTML = '';
            sorted.forEach(elm => container.append(elm));

            return;
        }

        // get sorting type and sort accordion elements
        sortingType = event.currentTarget.dataset.sortingType;
        let sorted = elements.sort((a, b) => {
            if (event.currentTarget.dataset.sortingDirection === 'asc') {
                if (sortingType === 'by-order-number') {
                    return a.dataset.sortByOrderNumber - b.dataset.sortByOrderNumber
                } else if (sortingType === 'by-reference') {
                    return a.dataset.sortByReference - b.dataset.sortByReference
                } else if (sortingType === 'by-date') {
                    let dateA = new Date(a.dataset.sortByDate);
                    let dateB = new Date(b.dataset.sortByDate);
                    return dateA - dateB;
                } else if (sortingType === 'by-status') {
                    return a.dataset.sortByStatus - b.dataset.sortByStatus
                } else if (sortingType === 'by-total') {
                    return a.dataset.sortByTotal - b.dataset.sortByTotal
                }
            } else if (event.currentTarget.dataset.sortingDirection === 'desc') {
                if (sortingType === 'by-order-number') {
                    return b.dataset.sortByOrderNumber - a.dataset.sortByOrderNumber
                } else if (sortingType === 'by-reference') {
                    return b.dataset.sortByReference - a.dataset.sortByReference
                } else if (sortingType === 'by-date') {
                    let dateA = new Date(a.dataset.sortByDate);
                    let dateB = new Date(b.dataset.sortByDate);
                    return dateB - dateA;
                } else if (sortingType === 'by-status') {
                    return b.dataset.sortByStatus - a.dataset.sortByStatus
                } else if (sortingType === 'by-total') {
                    return b.dataset.sortByTotal - a.dataset.sortByTotal
                }
            } else {
                let dateA = new Date(a.dataset.sortByDate);
                let dateB = new Date(b.dataset.sortByDate);
                return dateB - dateA;
            }
        });

        container.innerHTML = '';
        sorted.forEach(elm => container.append(elm));
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

                console.info(mutation.type)

                if (mutation.type === 'attributes') {
                    this.openAccordion = DomAccess.querySelector(
                        this.el,
                        '.b2b-accordion--open'
                    );
                    if (this.openAccordion) {
                        this._initPlugins();
                    }
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
