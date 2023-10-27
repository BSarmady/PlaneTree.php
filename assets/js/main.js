//region Initialize
$(function () {
    const $html = $('html');

    //region function top_menu_click(...)
    function top_menu_click(e) {
        const e_id = e.currentTarget.id;
        let handled = false;
        switch (e_id) {
            case 'theme_mode':
                const $theme_mode_btn = $('#theme_mode');
                if (localStorage.getItem('theme-mode') === 'light') {
                    $html.addClass('dark');
                    $theme_mode_btn.find(' use').attr('href', '#light-mode');
                    localStorage.removeItem('theme-mode');
                } else {
                    $html.removeClass('dark');
                    $theme_mode_btn.find(' use').attr('href', '#dark-mode');
                    localStorage.setItem('theme-mode', 'light');
                }
                e.preventDefault();
                break;
            case 'menu_users_sign_out':
                ajax('', {cmd: 'users.sign_out'}, (data) => {
                    if (data.redirect)
                        location.href = data.redirect;
                });
                e.preventDefault();
                break;
        }
    }
    //endregion

    $('.top-menu').topMenu({
        menu: data_user_menu,
        current_path: data_current_page,
        callback: top_menu_click
    });
    $('#menu_users').html(data_current_user.username.toTitleCase() + ' <small>(' + data_current_user.roles.join(', ').toTitleCase() + ')</small>');

    //region assign page theme and its toggle even

    if (localStorage.getItem('theme-mode') === 'light') {
        $html.removeClass('dark');
        $('#theme_mode').find(' use').attr('href', '#dark-mode');
    }
    //endregion

    InitializeDatePickers();
    InitializeSelect2();
});
//endregion

//region top-menu Component
(function ($) {
    $.fn.topMenu = function (options = {}) {

        const settings = $.extend({
            menu: [],
            current_path: '/',
            callback: null
        }, options);
        let $this = $(this);

        //region function buildMenuId(...)
        function buildMenuId(id) {
            return 'menu_' + id.trimX('/').replace(/[\/\s#-]/g, '_');
        }
        //endregion

        //region function get_children_menu(...)
        function get_children_menu(parent_id) {
            let _html = '';
            for (let menu of settings.menu) {
                const menu_id = buildMenuId(menu.route);
                if (menu.parent !== parent_id)
                    continue;
                if (menu.title === '-')
                    _html += '<li><hr class="dropdown-divider"></li>';
                else if (!menu.route || menu.route === '') {
                    _html += '<li class="dropdown-item"><a  id="' + menu_id + '" href="#">' + menu.title + '</a></li>';
                } else {
                    const activeClass = settings.current_path === menu.route ? ' active' : '';
                    _html += '<li><a id="' + menu_id + '" class="dropdown-item' + activeClass + '" href="' + menu.route + '">' + menu.title + '</a></li>';
                }
            }
            if (_html !== '')
                _html = '<ul class="dropdown-menu">' + _html + '</ul>';
            return _html;
        }
        //endregion

        //region initialize
        if (!settings.menu || settings.menu.length < 1)
            return;
        if (settings.current_path === '')
            settings.current_path = '/';

        //sort settings.menu
        settings.menu.sort((a, b) => {
            const by_order = parseInt(a.order ?? '0') - parseInt(b.order ?? '0');
            if (by_order !== 0)
                return by_order
            return (a.title ?? '').localeCompare(b.title ?? '');
        });

        let _html = '';
        for (let menu of settings.menu) {
            if (!menu.parent || menu.parent === '/') {
                const _children = get_children_menu(menu.route);
                const activeClass = settings.current_path === menu.route ? ' active' : '';
                const menu_id = buildMenuId(menu.route);
                if (_children === '') {
                    _html += '<li class="nav-item"><a id="' + menu_id + '" class="nav-link' + activeClass + '" href="' + menu.route + '">' + menu.title + '</a></li>';
                } else {
                    _html += '<li class="nav-item dropdown"><a id="' + menu_id + '" class="nav-link dropdown-toggle' + activeClass + '" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">' + menu.title + '</a>' + _children + '</li>';
                }
            }
        }
        _html += '<li class="me-2"><a id="theme_mode" class="nav-link" href="#"><svg fill="currentColor" width="16" height="16"><use href="#light-mode"/></svg></a></li>'

        $this.html(_html);
        if (settings.callback) {
            $this.on('click', 'a', settings.callback);
        }
        //endregion
    };
}(jQuery));
//endregion

//region InitializeDatePickers()
function InitializeDatePickers() {
    const defaultOptions = {
        allowOneSidedRange: false,
        autohide: true,
        //beforeShowDay: null,
        //beforeShowDecade: null,
        //beforeShowMonth: null,
        //beforeShowYear: null,
        clearButton: true,
        //dateDelimiter: ',',
        //datesDisabled: [],
        //daysOfWeekDisabled: [],
        //daysOfWeekHighlighted: [],
        //defaultViewDate: today,
        //enableOnReadonly: true,
        format: 'yyyy-mm-dd',
        language: 'en',
        //maxDate: null,
        //maxNumberOfDates: 1,
        //maxView: 3,
        //minDate: null,
        nextArrow: '<i class="fa fa-chevron-right"></i>',
        //orientation: 'auto',
        pickLevel: 0,
        prevArrow: '<i class="fa fa-chevron-left"></i>',
        showDaysOfWeek: true,
        showOnClick: true,
        showOnFocus: true,
        //startView: 0,
        //title: '',
        todayButton: true,
        todayButtonMode: 1,
        todayHighlight: true,
        updateOnBlur: true,
        //weekNumbers: 0,
        //weekStart: 0,
        buttonClass: 'btn btn-secondary'
    };
    $('.date-picker').each(function () {
        new Datepicker(this, defaultOptions);
    })
}
//endregion

//region function InitializeSelect2()
function InitializeSelect2() {
    $('.select2').each(function () {
        $(this).select2({
            theme: "bootstrap-5",
            placeholder: this.getAttribute('aria-label'),
            allowClear: true
        });
    });
    $('.select2-sm').each(function () {
        //debug(this.getAttribute('aria-label'));
        $(this).select2({
            theme: "bootstrap-5",
            placeholder: this.getAttribute('aria-label'),
            allowClear: true,
            containerCssClass: "select2--small", // For Select2 v4.0
            selectionCssClass: "select2--small", // For Select2 v4.1
            dropdownCssClass: "select2--small",
        });
    });
}
//endregion

//region function buildExportMenu(...)
function buildExportMenu(table, title, pdf_export_options = {}) {
    const export_types = [
        {type: 'csv', text: translate('##EXPORT_TO_CSV##'), icon: 'fa fa-file-text-o'},
        {type: 'json', text: translate('##EXPORT_TO_JSON##'), icon: 'fa fa-file-o'},
        {type: 'html', text: translate('##EXPORT_TO_HTML##'), icon: 'fa fa-file-excel-o'},
        {type: 'xlsx', text: translate('##EXPORT_TO_EXCEL##'), icon: 'fa fa-file-excel-o'},
        {text: '-'},
        {type: 'pdf', text: translate('##EXPORT_TO_PDF##'), icon: 'fa fa-file-pdf-o'}
    ];

    let _html = '';
    for (let export_type of export_types) {
        if (export_type.text === '-') {
            _html += '<div class="dropdown-divider"></div>';
        } else {
            _html += '<a href="#" data-type="' + export_type.type + '" class="dropdown-item"><i class="' + export_type.icon + '"></i> ' + export_type.text + '</a>';
        }
    }
    $('.mnu-export').html(_html).click(function (e) {
        let elem = $(e.target);
        if (e.target.tagName === 'I')
            elem = $(e.target).parent();
        else if (e.target.tagName !== 'A')
            return;
        let export_type = elem.data('type');
        if (table.getDataCount() < 1) {
            displayMessage('error', translate('##ERROR##'), translate('##ERROR_NOTHING_TO_EXPORT##'), 1000);
            return false;
        }
        switch (export_type) {
            case 'csv':
                return table.download("csv", title + '.csv');
            case 'json':
                return table.download("json", title + '.json');
            case 'xlsx':
                return table.download("xlsx", title + '.xlsx', {sheetName: title.substring(0, 25)});
            case 'pdf':
                return table.download("pdf", title + '.pdf', {
                    orientation: "landscape",
                    jsPDF: pdf_export_options,
                    autoTable: { //advanced table styling
                        styles: {
                            columnWidth: 'nowrap'
                        }
                    }
                });
            case 'html':
                return table.download("html", title + '.html', {style: true});
        }
    });
}
//endregion

//region function createGrid(...)
/**
 * Returns table component
 *
 * @param {any} containerId table element selector string
 * @param {any} options table options
 */
function createGrid(containerId, options) {
    const defaults = $.extend({}, {
        layout: 'fitDataStretch',
        //layout: 'fitColumns',
        selectable: false,
        //responsiveLayout: 'collapse',
        resizableColumns: 'header',
        placeholder: 'Data is not Available at the moment',
        pagination: 'local',
        paginationSize: 15,
        paginationSizeSelector: [10, 15, 20, 50, 100],
        movableColumns: true,
        tooltips: true,
        langs: {
            'en-US': {
                'pagination': {
                    'first': '<i class="fa fa-caret-left"></i><i class="fa fa-caret-left"></i>',
                    'first_title': '',
                    'prev': '<i class="fa fa-caret-left"></i>',
                    'prev_title': '',
                    'next': '<i class="fa fa-caret-right"></i>',
                    'next_title': '',
                    'last': '<i class="fa fa-caret-right"></i><i class="fa fa-caret-right"></i>',
                    'last_title': '',
                    'all': 'All',
                },
            },
        },
        columns: [],
        locale: 'en-US',
        data: [],
    }, options);
    let table = new Tabulator(containerId, defaults);
    table.on('renderComplete', function () {
        $('.tabulator-headers').addClass('bg-primary-subtle rounded-top-2');
        $('.tabulator-footer').addClass('bg-primary-subtle rounded-bottom-2');
        $('.tabulator-page').addClass('btn btn-outline-secondary');
    });
    if (options.row_click !== undefined)
        table.on("rowClick", options.row_click);
    return table;
}
//endregion

