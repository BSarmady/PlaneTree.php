//region This variables will be filled from backend and are always available
let data_service_uri = '/services/';
let data_current_user = {
    id: '',
    name: '',
    photo: '',
    organization_id: '',
    token: '',
    roles: []
};
let data_user_menu = [];
let data_current_page = '/';
let data_app_config = {twostep: false, captcha: false, ar_qa: false, ar_email: false, ar_sms: false};
//endregion

// Init
$(function () {
    attach_form_validation();
    tel_to_callto();
});

//region function translate(...)
function translate(word) {
    if (typeof data_translation !== 'undefined' && data_translation[word]) {
        return data_translation[word];
    }
    // translation is not available, use the best possible translation instead.
    return word.trimX('#').replace(/_/g, ' ').toLowerCase().ucWords();
}
//endregion

//region function displayMessage(...)
/**
 * Shows an animated message in screen
 *
 * @param type can be info, success, warning, danger or secondary types primary, secondary, light, dark
 * @param caption will be shown on top of message
 * @param message message to be shown
 * @param auto_close if it should close automatically set to a time in milliseconds
 */
function displayMessage(type, caption, message, auto_close = 0) {
    const hasCaption = caption !== '';
    // save developer from stupidity?
    auto_close = parseInt(auto_close ?? '0');
    auto_close = auto_close === 0 ? 0 : auto_close > 1000 ? auto_close : 1000;

    //region function getCssClass(...)
    function getCssClass(type) {
        let cssClass = 'toast show' + (hasCaption ? '' : ' border-0')
        switch (type.toLowerCase()) {
            case 'danger':
            case 'error':
                return cssClass + ' text-bg-danger';
            case 'success':
            case 'successful':
                return cssClass + ' text-bg-success';
            case 'info':
            case 'information':
                return cssClass + ' text-bg-info';
            case 'warn':
            case 'warning':
                return cssClass + ' text-bg-warning';
            case 'light':
                return cssClass + ' text-bg-light';
            default:
                return cssClass + ' text-bg-dark';
        }
    }
    //endregion

    // noinspection JSJQueryEfficiency
    let $messageContainer = $('.toast-container');
    if ($messageContainer.length === 0) {
        $('body').append('<div class="toast-container p-3 bottom-0 end-0" aria-live="polite" aria-atomic="true"></div>');
        $messageContainer = $('.toast-container');
    }

    let closeProp = '';
    if (auto_close) {
        closeProp = ' data-bs-config=\'{"delay":' + auto_close + ', "autohide":true}\''
    }

    let $toast = $('<div class="' + getCssClass(type) + '" role="alert" aria-live="assertive" aria-atomic="true"' + closeProp + '>'
        + (hasCaption
                ? '<div class="toast-header"><strong class="me-auto">' + caption + '</strong><small>Just now</small><button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button></div>'
                : '<div class="d-flex">'
        )
        + '<div class="toast-body overflow-auto" style="max-height:230px">' + message.replace(/\n/g, '<br />') + '</div>' +
        (hasCaption
                ? ''
                : '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>'
        )
        + '</div>');
    if (auto_close) {
        setTimeout(function () {
            $toast.remove();
        }, auto_close);
    }
    $messageContainer.prepend($toast);
}
//endregion

//region function hideMessage()
function hideMessage() {
    $('.toast-container').empty();
}
//endregion

//region function ajax(...)
/**
 * Ajax call to backend webservice
 *
 * @param url               url to webservice to call, if left empty, same website will be used
 * @param parameters        an array of parameters that will be sent to service with POST
 * @param successCallback   will be called, if data received from backend service, and it doesn't contain error header (data.error)
 * @param errorCallback     will be called, if an error happened or data from backend contains error header (data.error) if omitted, an displayMessage error will pop
 * @returns {boolean}       Always false
 */
let token = '';
function ajax(serviceUrl, parameters, successCallback, errorCallback = null) {
    $('.overlay').show();
    let headers = null;
    if (data_current_user)
        headers = {token: data_current_user.token};
    return $.ajax({
        headers: headers,
        type: 'POST',
        url: serviceUrl === '' ? data_service_uri : serviceUrl,
        data: JSON.stringify(parameters),
        contentType: 'application/json; charset=utf-8',
        dataType: 'json',
        //dataType: 'text',
        success: function (data) {
            // Developer is responsible to add "wait" css class and disable the button, we will only remove class and re-enable the button again
            $('.overlay').hide();
            $('.wait').attr('disabled', false).removeClass('wait');
            // try { debug(data); data = JSON.parse(data); } catch (e) { debug(e);}
            if (data.error) {
                if (errorCallback) {
                    errorCallback(data.error);
                } else {
                    displayMessage('error', translate('##Error##'), data.error + (data.description ? ': ' + data.description : ''), 5000);
                }
            } else {
                if (successCallback)
                    successCallback(data);
                else
                    console.error('successCallback is not defined')
            }
        },
        error: function (xhr, status, error) {
            // Developer is responsible to add "wait" css class and disable the button, we will only remove class and re-enable the button again
            //TODO Show Response text in a collapsed div with show more button
            $('.overlay').hide();
            $('.wait').attr('disabled', false).removeClass('wait');
            let Message = error;
            if (xhr && xhr.responseText)
                Message = xhr.responseText;
            if (status === 'parsererror')
                Message = 'Unexpected server response';
            if (error === 'abort')
                return false;
            if (errorCallback)
                errorCallback(Message);
            else
                displayMessage('error', 'Ajax Error', (
                        (parameters.cmd ? parameters.cmd + ',\n' : '')
                        + (error === '' ? '' : error + ',\n')
                        + htmlEncode(xhr.responseText)
                    )
                );
        }
    });
}
//endregion

//region function htmlEncode(...)
function htmlEncode(value) {
    return $('<textarea/>').text(value).html();
}
//endregion

//region function debug(...)
/**
 * Shows debug information
 *
 * @param o         object to show in debug message
 * @param toConsole if true, will be logged to console instead of alert
 */
function debug(o, toConsole = false) {
    if (toConsole) {
        console.log(JSON.stringify(o, null, 2));
    } else {
        alert(JSON.stringify(o, null, 2));
    }
}
//endregion

//region function formatDate(...)
/**
 * Format date to yyyy-mm-dd
 *
 * @param year  If year is date object, will try to parse it, if it is a number, will be used as Year
 * @param month Omitted if year is a date object
 * @param day   Omitted if year is a date object
 * @returns {string} Date formatted as yyyy-mm-dd
 */
function formatDate(year, month, day) {
    // if year is not number, a date passed to function and will treat it as date
    if (typeof (year) !== 'number') {
        day = year.getDate() - 1;
        month = year.getMonth();
        year = year.getFullYear();
    }
    return year + '-' + (month < 9 ? '0' : '') + (month + 1) + '-' + (day < 9 ? '0' : '') + (day + 1);
}
//endregion

//region function getRouteVars()
function getRouteVars() {
    // Query string is defined as anything after route separated by /
    // e.g. ?route1.route2/12/122
    return window.location.search.substring(1).toString().split('/');
}
//endregion

//region function getQueryString()
function getQueryString() {
    let vars = window.location.search.substring(1).split('&');
    let variables = {};
    for (let _value of vars) {
        let arr = _value.split('=');
        let pairs = arr.splice(0, 1);
        if (arr.length > 0)
            pairs.push(arr.join('='));
        if (variables[pairs[0]])
            if (Array.isArray(variables[pairs[0]]))
                variables[pairs[0]].push(pairs[1]);
            else
                variables[pairs[0]] = [variables[pairs[0]], pairs[1]];
        else
            variables[pairs[0]] = pairs[1];
    }
    return variables;
}
//endregion

//region function tel_to_callto()
function tel_to_callto() {
    //region For desktop users, changes tel: links (mobile call) to callto (skype) tags
    if (!(/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent))) {
        $('a[href^="tel:"]').each(function () {
            let $this = $(this);
            $this.attr('href', $this.attr('href').replace(/^tel:/, 'callto:'));
        });
    }
}
//endregion

//region function attach_form_validation()
function attach_form_validation() {
    $('.needs-validation').each(function () {
        // attach form validation event to form submit
        this.addEventListener('submit', function (event) {
            if (this.checkValidity() === false) {
                event.preventDefault();
                event.stopPropagation();
            }
            this.classList.add('was-validated');
        }, false);
    });
}
//endregion

//region function escape(...)
function escape(str) {
    return str
        .replace(/\\/g, '\\\\')
        .replace(/"/g, '\\\"')
        .replace(/\//g, '\\/')
        .replace(/\b/g, '\\b')
        .replace(/\f/g, '\\f')
        .replace(/\n/g, '\\n')
        .replace(/\r/g, '\\r')
        .replace(/\t/g, '\\t');
}
//endregion

//region function count_char_in_string(...)
function count_char_in_string(needle, stack) {
    stack += "";
    needle += "";
    if (needle.length < 1)
        return -1;

    let n = 0;
    for (let i = 0; i < stack.length; i++) {
        if (stack[i] === needle)
            n++;
    }
    return n;
}
//endregion

//region function random(...)
function random(low, high) {
    return Math.floor(Math.random() * high) + low;
}
//endregion

//region String.prototype.format
// Prototype String.format(formatString, params), matches {0},{1},{2} ... from formatString to parameters provided
if (!String.prototype.format) {
    String.prototype.format = function () {
        let args = arguments;
        return this.replace(/{(\d+)}/g, function (match, number) {
            return typeof args[number] != 'undefined' ? args[number] : match;
        });
    };
}
//endregion

//region String.prototype.toTitleCase
if (!String.prototype.toTitleCase) {
    String.prototype.toTitleCase = function () {
        return this.charAt(0).toUpperCase() + this.substring(1).toLowerCase();
    };
}
//endregion

//region String.prototype.ucWords
if (!String.prototype.ucWords) {
    String.prototype.ucWords = function () {
        return this.replace(/\b\w/g, function (l) {
            return l.toUpperCase()
        })
    };
}
//endregion

//region String.prototype.trimX
// Prototype String.trimX, ch can be Array of char or a string with multiple chars, if omitted space will be trimmed
if (!String.prototype.trimX) {
    String.prototype.trimX = function (ch = ' ') {
        //Usage:
        // trim('|hello|world|', '|');
        // trim('|hello|world   ', ['|', ' ']);
        // trim('|hello|world   ', '| ');
        let str = this.toString();
        let start = 0;
        let end = str.length;
        if (ch.length === 1) {
            while (start < end && str[start] === ch)
                start++;
            while (end > start && str[end - 1] === ch)
                end--;
            return (start > 0 || end < str.length) ? str.substring(start, end) : str;
        } else {
            while (start < end && ch.indexOf(str[start]) >= 0)
                start++;
            while (end > start && ch.indexOf(str[end - 1]) >= 0)
                end--;
            return (start > 0 || end < str.length) ? str.substring(start, end) : str;
        }
    }
}
//endregion