window.base_url = new URL('../', document.currentScript.src).href.replace(/\/$/, '');

window.showMessage = function (message) {
    window.alert(message);
};

window.handleAjaxError = function (xhr) {
    const response = xhr && xhr.responseJSON;
    window.showMessage(response && response.message
        ? response.message
        : 'The request could not be completed. Please try again.');
};

window.renderTableText = function (value, type) {
    const text = value == null ? '' : String(value);
    if (type !== 'display') return text;

    const element = document.createElement('span');
    element.textContent = text;
    return element.innerHTML;
};

window.formatOrderNumber = function (value) {
    const text = value == null ? '' : String(value);
    return /^\d+$/.test(text) ? `ORD-${text.padStart(4, '0')}` : '';
};

window.formatJobOrderNumber = function (value) {
    const text = value == null ? '' : String(value);
    return /^\d+$/.test(text) ? `JO-${text.padStart(4, '0')}` : '';
};

window.formatTransactionNumber = function (value) {
    const text = value == null ? '' : String(value);
    return /^\d+$/.test(text) ? `TRX-${text.padStart(4, '0')}` : '';
};

window.renderTableOrderNumber = function (value, type) {
    return window.renderTableText(
        type === 'display' || type === 'filter' ? window.formatOrderNumber(value) : value,
        type
    );
};

window.renderTableJobOrderNumber = function (value, type) {
    return window.renderTableText(
        type === 'display' || type === 'filter' ? window.formatJobOrderNumber(value) : value,
        type
    );
};

window.renderTableTransactionNumber = function (value, type) {
    return window.renderTableText(
        type === 'display' || type === 'filter' ? window.formatTransactionNumber(value) : value,
        type
    );
};

window.renderTableStatus = function (value, type) {
    const text = value == null ? '' : String(value);
    if (type !== 'display') return text;

    const statusClasses = {
        'Pending': 'north-badge-pending',
        'Processing': 'north-badge-processing',
        'Ready for Release': 'north-badge-ready-for-release',
        'Ready for Stock Out': 'north-badge-ready-for-stock-out',
        'Completed': 'north-badge-completed',
        'Released': 'north-badge-released',
        'Available': 'north-badge-available',
        'Out of Stock': 'north-badge-out-of-stock',
        'Recorded': 'north-badge-recorded',
        'Approved': 'north-badge-approved',
        'Rejected': 'north-badge-rejected',
        'Cancelled': 'north-badge-cancelled',
        'Stock In': 'north-badge-stock-in',
        'Stock Out': 'north-badge-stock-out'
    };
    const element = document.createElement('span');
    element.className = `north-badge ${statusClasses[text] || 'north-badge-neutral'}`;
    element.textContent = text;
    return element.outerHTML;
};

window.formatQuantity = function (value) {
    const text = value == null ? '' : String(value).trim();
    const match = text.match(/^(-?)(\d+)(?:\.(\d+))?$/);
    if (!match) return text;

    const whole = match[2].replace(/^0+(?=\d)/, '');
    const fraction = (match[3] || '').replace(/0+$/, '');
    if (whole === '0' && fraction === '') return '0';
    return `${match[1]}${whole}${fraction ? `.${fraction}` : ''}`;
};

window.renderTableQuantity = function (value, type) {
    return window.renderTableText(window.formatQuantity(value), type);
};

$(function () {
    const sidebarToggle = document.querySelector('.north-sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            document.body.classList.toggle('north-sidebar-collapsed');
        });
    }
});
