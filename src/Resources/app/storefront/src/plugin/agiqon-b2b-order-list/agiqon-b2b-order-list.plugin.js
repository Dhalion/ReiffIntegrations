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
        accordionBodySel: '.b2b-accordion__body',
        btnSortSel: '.btn-sort'
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

            this.sortingButtons = DomAccess.querySelectorAll(
                document,
                this.options.btnSortSel
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
        this.sortingButtons.forEach((item) => {
            item.addEventListener(clickEvent, this._sortByOderNumber.bind(this));
        });
    }

    /**
     * Do sorting by event target
     * @private
     */
    _sortByOderNumber() {
        const container = document.querySelector('.b2b-order-search-results');
        const elements = Array.from(container.children);
        let sortingType;

        this.sortingButtons.forEach((item) => {
            item.classList.remove('active-sorting');
        });
        event.currentTarget.classList.add('active-sorting');

        if (event.currentTarget.dataset.sortingDirection === 'default') {
            event.currentTarget.setAttribute('data-sorting', 'asc')
        } else if (event.currentTarget.dataset.sortingDirection === 'asc') {
            event.currentTarget.setAttribute('data-sorting', 'desc')
        } else {
            event.currentTarget.setAttribute('data-sorting', 'default')
            let sorted = elements.sort((a, b) => {
                let dateA = new Date(a.dataset.sortByDate);
                let dateB = new Date(b.dataset.sortByDate);
                return dateB - dateA;
            });

            container.innerHTML = '';
            sorted.forEach(elm => container.append(elm));

            return;
        }


        // data-sort-by-order-number="{{ order.number }}"
        // data-sort-by-reference="{{ order.reference?: '-' }}"
        // data-sort-by-date="{{ order.orderDate|format_date('medium', locale=app.request.locale) }}"
        // data-sort-by-status="{{ order.status }}"
        // data-sort-by-total="{{ order.netTotal }}">


        // console.log(event.currentTarget.dataset.sortingType)

        sortingType = event.currentTarget.dataset.sortingType;

        let sorted = elements.sort((a, b) => {
            if (event.currentTarget.dataset.sortingDirection === 'asc') {
                if (sortingType === 'by-order-number') {
                    return +a.dataset.sortByOrderNumber - +b.dataset.sortByOrderNumber
                } else if (sortingType === 'by-reference') {
                    return +a.dataset.sortByReference - +b.dataset.sortByReference
                } else if (sortingType === 'by-date') {
                    let dateA = new Date(a.dataset.sortByDate);
                    let dateB = new Date(b.dataset.sortByDate);
                    return dateA - dateB;
                } else if (sortingType === 'by-status') {
                    return +a.dataset.sortByStatus - +b.dataset.sortByStatus
                } else if (sortingType === 'by-total') {
                    return +a.dataset.sortByTotal - +b.dataset.sortByTotal
                }
            } else {
                if (sortingType === 'by-order-number') {
                    return +b.dataset.sortByOrderNumber - +a.dataset.sortByOrderNumber
                } else if (sortingType === 'by-reference') {
                    return +b.dataset.sortByReference - +a.dataset.sortByReference
                } else if (sortingType === 'by-date') {
                    let dateA = new Date(a.dataset.sortByDate);
                    let dateB = new Date(b.dataset.sortByDate);
                    return dateB - dateA;
                } else if (sortingType === 'by-status') {
                    return +b.dataset.sortByStatus - +a.dataset.sortByStatus
                } else if (sortingType === 'by-total') {
                    return +b.dataset.sortByTotal - +a.dataset.sortByTotal
                }
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
}
