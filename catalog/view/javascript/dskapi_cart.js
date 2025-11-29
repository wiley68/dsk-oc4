/**
 * DSKAPI Cart - JavaScript functionality for cart page
 * Handles installment calculations, popup interactions, and checkout redirection
 */

let old_vnoski;

/**
 * Creates a CORS-enabled XMLHttpRequest
 *
 * @param {string} method HTTP method (GET, POST, etc.)
 * @param {string} url Request URL
 * @returns {XMLHttpRequest|null} XMLHttpRequest object or null if not supported
 */
function createCORSRequest(method, url) {
    var xhr = new XMLHttpRequest();
    if ('withCredentials' in xhr) {
        xhr.open(method, url, true);
    } else if (typeof XDomainRequest != 'undefined') {
        xhr = new XDomainRequest();
        xhr.open(method, url);
    } else {
        xhr = null;
    }
    return xhr;
}

/**
 * Stores the previous number of installments when input gains focus
 *
 * @param {number} _old_vnoski Previous number of installments
 */
function dskapi_pogasitelni_vnoski_input_focus(_old_vnoski) {
    old_vnoski = _old_vnoski;
}

/**
 * Updates installment fields when the number of installments changes
 * Makes API call to get updated payment information and updates popup and button fields
 */
function dskapi_pogasitelni_vnoski_input_change() {
    const dskapi_vnoski = parseFloat(
        document.getElementById('dskapi_pogasitelni_vnoski_input').value
    );
    const dskapi_price = parseFloat(
        document.getElementById('dskapi_price_txt').value
    );
    const dskapi_cid = document.getElementById('dskapi_cid').value;
    const DSKAPI_LIVEURL = document.getElementById('DSKAPI_LIVEURL').value;
    const dskapi_product_id = document.getElementById('dskapi_product_id').value;
    var xmlhttpro = createCORSRequest(
        'GET',
        DSKAPI_LIVEURL +
        '/function/getproductcustom.php?cid=' +
        dskapi_cid +
        '&price=' +
        dskapi_price +
        '&product_id=' +
        dskapi_product_id +
        '&dskapi_vnoski=' +
        dskapi_vnoski
    );
    xmlhttpro.onreadystatechange = function () {
        if (this.readyState == 4) {
            var responseData;
            try {
                responseData = JSON.parse(this.response);
            } catch (e) {
                console.error('Error parsing response:', e);
                return;
            }

            var options = responseData.dsk_options;
            var dsk_vnoska = parseFloat(responseData.dsk_vnoska);
            var dsk_gpr = parseFloat(responseData.dsk_gpr);
            var dsk_is_visible = responseData.dsk_is_visible;

            if (dsk_is_visible) {
                if (options) {
                    // Update fields in popup
                    const dskapi_vnoska_input = document.getElementById('dskapi_vnoska');
                    const dskapi_gpr = document.getElementById('dskapi_gpr');
                    const dskapi_obshtozaplashtane_input = document.getElementById(
                        'dskapi_obshtozaplashtane'
                    );

                    if (dskapi_vnoska_input) {
                        dskapi_vnoska_input.value = dsk_vnoska.toFixed(2);
                    }
                    if (dskapi_gpr) {
                        dskapi_gpr.value = dsk_gpr.toFixed(2);
                    }
                    if (dskapi_obshtozaplashtane_input) {
                        dskapi_obshtozaplashtane_input.value = (
                            dsk_vnoska * dskapi_vnoski
                        ).toFixed(2);
                    }

                    // Update fields in button (if they exist)
                    const dskapi_vnoski_button = document.getElementById('dskapi_vnoski_button');
                    const dskapi_vnoska_button = document.getElementById('dskapi_vnoska_button');

                    if (dskapi_vnoski_button) {
                        dskapi_vnoski_button.textContent = dskapi_vnoski;
                    }
                    if (dskapi_vnoska_button) {
                        dskapi_vnoska_button.textContent = dsk_vnoska.toFixed(2);
                    }

                    old_vnoski = dskapi_vnoski;
                } else {
                    alert('The selected number of installments is below the minimum.');
                    var dskapi_vnoski_input = document.getElementById(
                        'dskapi_pogasitelni_vnoski_input'
                    );
                    if (dskapi_vnoski_input) {
                        dskapi_vnoski_input.value = old_vnoski;
                    }
                }
            } else {
                alert('The selected number of installments is above the maximum.');
                var dskapi_vnoski_input = document.getElementById(
                    'dskapi_pogasitelni_vnoski_input'
                );
                if (dskapi_vnoski_input) {
                    dskapi_vnoski_input.value = old_vnoski;
                }
            }
        }
    };
    xmlhttpro.send();
}

/**
 * Resolves the language code from URL parameters or HTML lang attribute
 *
 * @returns {string} Normalized language code (e.g., 'en-gb', 'bg-bg')
 */
function resolveLanguageCode() {
    const params = new URLSearchParams(window.location.search);
    const urlLang = params.get('language');
    if (urlLang) {
        return urlLang.toLowerCase();
    }

    const raw =
        document.documentElement.getAttribute('lang') ||
        document.documentElement.lang ||
        '';
    if (!raw) {
        return 'en-gb';
    }

    const normalized = raw.toLowerCase();
    if (normalized.includes('-')) {
        return normalized;
    }

    const languageMap = {
        en: 'en-gb',
        bg: 'bg-bg',
        de: 'de-de',
        fr: 'fr-fr',
        it: 'it-it',
        es: 'es-es',
    };

    return languageMap[normalized] || normalized;
}

/**
 * Resolves the base URL from base tag or current origin
 *
 * @returns {string} Base URL
 */
function resolveBaseUrl() {
    const baseTag = document.querySelector('base');
    return baseTag && baseTag.href ? baseTag.href : window.location.origin + '/';
}

/**
 * Builds an absolute URL from a relative path
 *
 * @param {string} path Relative or absolute path
 * @returns {string} Absolute URL
 */
function buildAbsoluteUrl(path) {
    const normalized = path.startsWith('/') ? path.slice(1) : path;
    return new URL(normalized, resolveBaseUrl()).toString();
}

/**
 * Redirects to checkout page with DSKAPI payment method pre-selected
 * Stores preference in sessionStorage and adds dskapi=1 parameter to URL
 */
function dskapi_redirectToCheckoutWithPaymentMethod() {
    try {
        sessionStorage.setItem('dskapiPreferredPayment', 'mt_dskapi_credit.dskapi');
    } catch (error) {
        // ignore storage issues
    }

    const params = new URLSearchParams();
    params.set('route', 'checkout/checkout');
    const lang = resolveLanguageCode();
    if (lang) {
        params.set('language', lang);
    }
    params.set('dskapi', '1');

    window.location.href = buildAbsoluteUrl(`index.php?${params.toString()}`);
}

// Event delegation for "Buy on credit" button - using document-level listener
// This works even if elements are loaded dynamically and executes only once
let dskapiBuyCreditHandlerBound = false;

/**
 * Initializes DSKAPI cart widget functionality
 * Sets up event handlers for buttons and popup interactions
 */
function initDskapiCartWidget() {
    // Event delegation for "Buy on credit" button - only once
    if (!dskapiBuyCreditHandlerBound) {
        dskapiBuyCreditHandlerBound = true;
        document.addEventListener(
            'click',
            function (event) {
                const target = event.target;
                // Check if click is on button or its children
                if (
                    target &&
                    (target.id === 'dskapi_buy_credit' ||
                        target.closest('#dskapi_buy_credit'))
                ) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();

                    const dskapiProductPopupContainer = document.getElementById(
                        'dskapi-product-popup-container'
                    );
                    if (dskapiProductPopupContainer) {
                        dskapiProductPopupContainer.style.display = 'none';
                    }

                    dskapi_redirectToCheckoutWithPaymentMethod();
                    return false;
                }
            },
            true
        ); // Use capture phase for earlier interception
    }

    // Set cursor style on button if it exists
    const dskapi_buy_credit = document.getElementById('dskapi_buy_credit');
    if (dskapi_buy_credit !== null) {
        dskapi_buy_credit.style.cursor = 'pointer';
    }

    // Initialize main button btn_dskapi
    const btn_dskapi = document.getElementById('btn_dskapi');
    if (btn_dskapi !== null && btn_dskapi.dataset.dskapiBound !== '1') {
        btn_dskapi.dataset.dskapiBound = '1';

        const dskapi_button_status_el = document.getElementById(
            'dskapi_button_status'
        );
        if (!dskapi_button_status_el) {
            return; // If button_status element doesn't exist, don't continue
        }

        const dskapi_button_status = parseInt(dskapi_button_status_el.value) || 0;
        const dskapiProductPopupContainer = document.getElementById(
            'dskapi-product-popup-container'
        );
        const dskapi_back_credit = document.getElementById('dskapi_back_credit');

        const dskapi_price = document.getElementById('dskapi_price');
        const dskapi_maxstojnost = document.getElementById('dskapi_maxstojnost');

        if (!dskapi_price || !dskapi_maxstojnost) {
            return; // If required elements don't exist, don't continue
        }

        let dskapi_priceall = parseFloat(dskapi_price.value);

        btn_dskapi.addEventListener(
            'click',
            (event) => {
                event.preventDefault();
                event.stopPropagation();

                if (dskapi_button_status == 1) {
                    dskapi_redirectToCheckoutWithPaymentMethod();
                    return false;
                } else {
                    const dskapi_eur_el = document.getElementById('dskapi_eur');
                    const dskapi_currency_code_el = document.getElementById(
                        'dskapi_currency_code'
                    );

                    if (!dskapi_eur_el || !dskapi_currency_code_el) {
                        return false;
                    }

                    const dskapi_eur = parseInt(dskapi_eur_el.value) || 0;
                    const dskapi_currency_code = dskapi_currency_code_el.value;

                    switch (dskapi_eur) {
                        case 0:
                            break;
                        case 1:
                            if (dskapi_currency_code == 'EUR') {
                                dskapi_priceall = dskapi_priceall * 1.95583;
                            }
                            break;
                        case 2:
                        case 3:
                            if (dskapi_currency_code == 'BGN') {
                                dskapi_priceall = dskapi_priceall / 1.95583;
                            }
                            break;
                    }

                    const dskapi_price_txt = document.getElementById('dskapi_price_txt');
                    if (dskapi_price_txt) {
                        dskapi_price_txt.value = dskapi_priceall.toFixed(2);
                    }

                    if (dskapi_priceall <= parseFloat(dskapi_maxstojnost.value)) {
                        if (dskapiProductPopupContainer) {
                            dskapiProductPopupContainer.style.display = 'block';
                            dskapi_pogasitelni_vnoski_input_change();
                        }
                    } else {
                        alert(
                            'Maximum allowed price for credit ' +
                            parseFloat(dskapi_maxstojnost.value).toFixed(2) +
                            ' has been exceeded!'
                        );
                    }
                }
                return false;
            },
            true
        ); // Use capture phase

        if (dskapi_back_credit) {
            dskapi_back_credit.addEventListener(
                'click',
                (event) => {
                    event.preventDefault();
                    if (dskapiProductPopupContainer) {
                        dskapiProductPopupContainer.style.display = 'none';
                    }
                    return false;
                },
                true
            );
        }
    }
}

/**
 * Initialization function with retry mechanism
 * Attempts to initialize widget multiple times until required elements are found
 */
function initDskapiCartWidgetWithRetry() {
    let attempts = 0;
    const maxAttempts = 10;

    const tryInit = function () {
        attempts++;

        // Check if required elements exist
        const btn_dskapi = document.getElementById('btn_dskapi');
        const dskapi_buy_credit = document.getElementById('dskapi_buy_credit');
        const dskapi_button_status = document.getElementById(
            'dskapi_button_status'
        );

        if (btn_dskapi || dskapi_buy_credit || dskapi_button_status) {
            initDskapiCartWidget();
        } else if (attempts < maxAttempts) {
            setTimeout(tryInit, 200);
        }
    };

    tryInit();
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function () {
    initDskapiCartWidgetWithRetry();
});

// Re-initialize on PrestaShop cart updates (if PrestaShop is present)
if (typeof prestashop !== 'undefined' && prestashop.on) {
    prestashop.on('updatedCart', function () {
        setTimeout(initDskapiCartWidgetWithRetry, 100);
    });
    prestashop.on('updateCart', function () {
        setTimeout(initDskapiCartWidgetWithRetry, 100);
    });
}

// Also try after full page load
window.addEventListener('load', function () {
    setTimeout(initDskapiCartWidgetWithRetry, 300);
});
