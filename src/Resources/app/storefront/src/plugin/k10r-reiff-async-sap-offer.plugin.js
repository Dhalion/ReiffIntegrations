import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';
import PageLoadingIndicatorUtil from 'src/utility/loading-indicator/page-loading-indicator.util';
import $ from 'jquery';
import DataTable from 'datatables.net';

window.DataTablesDe = require('../vendor/dataTablesLangDe.json');

export default class K10rReiffAsyncSapOfferPlugin extends Plugin {
    static options = {
        url: '',
        customerNumber: '',
        locale: '',
        pageLength: 25,
        offerContentSelector: '.k10r-reiff-integration-account-offer-ajax-content',
    };

    init() {
        this._client = new HttpClient();
        this._fetchOffers();
    }

    _fetchOffers() {
        PageLoadingIndicatorUtil.create();

        this._client.abort();

        const data = {
            customerNumber: this.options.customerNumber
        };

        this._client.post(this.options.url, JSON.stringify(data), (content) => this._renderOfferList(content));
    }

    _renderOfferList(response) {
        PageLoadingIndicatorUtil.remove();

        const contentEl = document.querySelector(this.options.offerContentSelector);

        if (!contentEl) {
            return;
        }

        contentEl.innerHTML = response;

        const dataTableOpts = {
            paginate: true,
            sort: true,
            searching: true,
            pageLength: this.options.pageLength,
            ordering: true,
            info: true,
            pagingType: 'numbers',
        };

        if (this.options.locale === 'de-DE') {
            dataTableOpts.language = window.DataTablesDe;
        }

        new DataTable('table.data-tables', dataTableOpts);
    }
}
