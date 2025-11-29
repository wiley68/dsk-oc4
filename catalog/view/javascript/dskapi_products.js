let old_vnoski;

const SELECTORS = {
    buttonContainer: '#dskapi-product-button-container',
    installmentsInput: 'dskapi_pogasitelni_vnoski_input',
    priceInput: 'dskapi_price_txt',
    hiddenPrice: 'dskapi_price',
    buttonStatus: 'dskapi_button_status',
    maxValue: 'dskapi_maxstojnost',
    eurMode: 'dskapi_eur',
    currencyCode: 'dskapi_currency_code',
    popupContainer: 'dskapi-product-popup-container',
    cid: 'dskapi_cid',
    productId: 'dskapi_product_id',
    liveUrl: 'DSKAPI_LIVEURL',
    installmentsButton: 'dskapi_vnoski_button',
    installmentValueButton: 'dskapi_vnoska_button',
    installmentInput: 'dskapi_vnoska',
    gprInput: 'dskapi_gpr',
    totalInput: 'dskapi_obshtozaplashtane',
};

/**
 * Returns a DOM element by id.
 * @param {string} id
 * @returns {HTMLElement|null}
 */
function getElement(id) {
    return document.getElementById(id);
}

/**
 * Returns a numeric value from an input element.
 * @param {string} id
 * @returns {number}
 */
function getNumericValue(id) {
    const el = getElement(id);
    return el ? parseFloat(el.value) : 0;
}

/**
 * Parses JSON safely and returns null on failure.
 * @param {string} payload
 * @returns {Object|null}
 */
function safeJsonParse(payload) {
    try {
        return JSON.parse(payload || '{}');
    } catch (err) {
        return null;
    }
}

/**
 * Shows or hides the custom button container.
 * @param {boolean} isVisible
 */
function toggleButtonVisibility(isVisible) {
    const container = document.querySelector(SELECTORS.buttonContainer);
    if (!container) {
        return;
    }

    if (typeof window.$ === 'function') {
        if (isVisible) {
            $(SELECTORS.buttonContainer).show('slow');
        } else {
            $(SELECTORS.buttonContainer).hide('slow');
        }
    } else {
        container.style.display = isVisible ? 'block' : 'none';
    }
}

/**
 * Updates all installment-related outputs.
 * @param {Object} data
 * @param {number} data.installment
 * @param {number} data.count
 * @param {number} data.gpr
 */
function updateInstallmentOutputs({ installment, count, gpr }) {
    const installmentsButton = getElement(SELECTORS.installmentsButton);
    if (installmentsButton) {
        installmentsButton.innerHTML = count;
    }

    const installmentValueButton = getElement(SELECTORS.installmentValueButton);
    if (installmentValueButton) {
        installmentValueButton.innerHTML = installment.toFixed(2);
    }

    const installmentInput = getElement(SELECTORS.installmentInput);
    if (installmentInput) {
        installmentInput.value = installment.toFixed(2);
    }

    const totalInput = getElement(SELECTORS.totalInput);
    if (totalInput) {
        totalInput.value = (installment * count).toFixed(2);
    }

    const gprInput = getElement(SELECTORS.gprInput);
    if (gprInput) {
        gprInput.value = gpr.toFixed(2);
    }
}

/**
 * Renders validation messages for option-related errors.
 * @param {Object} optionErrors
 */
function renderOptionErrors(optionErrors) {
    if (!optionErrors) {
        return;
    }

    $('.alert, .text-danger').remove();

    Object.entries(optionErrors).forEach(([key, message]) => {
        const element = $('#input-option' + key.replace('_', '-'));

        if (element.length) {
            if (element.parent().hasClass('input-group')) {
                element.parent().after(`<div class="text-danger">${message}</div>`);
            } else {
                element.after(`<div class="text-danger">${message}</div>`);
            }
        } else {
            $('#product').prepend(`<div class="text-danger">${message}</div>`);
        }
    });

    $('.text-danger').parent().addClass('has-error');
}

/**
 * Resolves the storefront language code.
 * @returns {string}
 */
function getLanguageCode() {
    const params = new URLSearchParams(window.location.search);
    const urlLang = params.get('language');

    if (urlLang) {
        return urlLang.toLowerCase();
    }

    const raw =
        document.documentElement.getAttribute('lang') ||
        document.documentElement.lang ||
        '';

    if (raw) {
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

    return 'en-gb';
}

/**
 * Returns the base URL (from <base> tag or window origin).
 * @returns {string}
 */
function getBaseUrl() {
    const baseTag = document.querySelector('base');
    return baseTag && baseTag.href ? baseTag.href : window.location.origin + '/';
}

/**
 * Builds an absolute URL using the store base.
 * @param {string} path
 * @returns {string}
 */
function buildUrl(path) {
    const normalized = path.startsWith('/') ? path.slice(1) : path;
    return new URL(normalized, getBaseUrl()).toString();
}

/**
 * Serializes the product form payload.
 * @returns {string}
 */
function serializeProductForm() {
    const $form = $('#product');
    if (!$form.length) {
        return '';
    }

    return $form.find('input[name], select[name], textarea[name]').serialize();
}

/**
 * Adds the current product configuration to the cart via AJAX.
 * @returns {Promise<Object>}
 */
function addProductToCart() {
    return new Promise((resolve, reject) => {
        const payload = serializeProductForm();

        if (!payload) {
            reject({ error: { warning: 'Неуспешно добавяне на продукта.' } });
            return;
        }

        const languageCode = getLanguageCode();
        const endpoint = buildUrl(
            `index.php?route=checkout/cart.add${languageCode ? `&language=${encodeURIComponent(languageCode)}` : ''
            }`
        );

        $.ajax({
            url: endpoint,
            type: 'post',
            data: payload,
            dataType: 'json',
            success: (response) => {
                resolve(response);
            },
            error: (xhr) => {
                try {
                    const json = JSON.parse(xhr.responseText);
                    reject(json);
                } catch (error) {
                    reject({ error: { warning: xhr.statusText || 'Възникна грешка.' } });
                }
            },
        });
    });
}

/**
 * Redirects the shopper to checkout with the DSK payment flag.
 */
function redirectToCheckoutWithDskapi() {
    try {
        sessionStorage.setItem('dskapiPreferredPayment', 'mt_dskapi_credit.dskapi');
    } catch (error) {
        // ignore storage errors
    }

    const params = new URLSearchParams();
    params.set('route', 'checkout/checkout');
    const lang = getLanguageCode();

    if (lang) {
        params.set('language', lang);
    }

    params.set('dskapi', '1');

    window.location.href = buildUrl(`index.php?${params.toString()}`);
}

/**
 * Shows a Bootstrap alert message in the alert container.
 * @param {string} message - The message to display
 * @param {string} [type='danger'] - Alert type: 'danger', 'warning', 'success', 'info'
 */
function showAlert(message, type = 'danger') {
    if (!message) {
        return;
    }

    const alertContainer = $('#alert');
    if (alertContainer.length === 0) {
        // Ако няма alert контейнер, създаваме го в началото на #product
        const productContainer = $('#product');
        if (productContainer.length > 0) {
            productContainer.prepend('<div id="alert"></div>');
        } else {
            // Fallback към стандартен alert ако няма подходящ контейнер
            alert(message);
            return;
        }
    }

    const iconClass = type === 'danger' ? 'fa-solid fa-circle-exclamation' :
        type === 'warning' ? 'fa-solid fa-triangle-exclamation' :
            type === 'success' ? 'fa-solid fa-circle-check' :
                'fa-solid fa-circle-info';

    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        <i class="${iconClass}"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>`;

    $('#alert').prepend(alertHtml);

    // Автоматично скриване след 5 секунди
    setTimeout(() => {
        $('#alert .alert').fadeOut(300, function () {
            $(this).remove();
        });
    }, 5000);
}

/**
 * Handles cart addition errors and provides feedback.
 * @param {Object} response
 * @param {boolean} [showAlerts=true]
 */
function handleCartErrors(response, showAlerts = true) {
    if (!response || (!response.error && !response.redirect)) {
        if (showAlerts) {
            showAlert('Възникна неочаквана грешка. Моля, опитайте отново.');
        }
        return;
    }

    if (response.redirect) {
        window.location.href = response.redirect;
        return;
    }

    if (response.error && response.error.option && showAlerts) {
        renderOptionErrors(response.error.option);
        // Показване на алерт с всички грешки за опции
        const errorMessages = Object.values(response.error.option);
        if (errorMessages.length > 0) {
            showAlert(errorMessages.join('<br>'));
        }
    }

    if (response.error && response.error.warning && showAlerts) {
        showAlert(response.error.warning, 'warning');
    }

    // Обработка на общи грешки (string)
    if (response.error && typeof response.error === 'string' && showAlerts) {
        showAlert(response.error);
    }
}

/**
 * Converts price adjustments to the correct currency.
 * @param {number} value
 * @param {number} eurFlag
 * @param {string} currencyCode
 * @returns {number}
 */
function applyCurrencyAdjustment(value, eurFlag, currencyCode) {
    if (eurFlag === 1 && currencyCode === 'EUR') {
        return value * 1.95583;
    }

    if (eurFlag === 2 && currencyCode === 'BGN') {
        return value / 1.95583;
    }

    return value;
}

/**
 * Returns the quantity selected by the customer.
 * @returns {number}
 */
function getSelectedQuantity() {
    const quantityInput = document.getElementById('input-quantity');
    if (quantityInput) {
        const parsed = parseFloat(quantityInput.value);
        return Number.isNaN(parsed) ? 1 : parsed;
    }

    const fallback = document.querySelector('[name="quantity"]');
    if (fallback) {
        const parsed = parseFloat(fallback.value);
        return Number.isNaN(parsed) ? 1 : parsed;
    }

    return 1;
}

/**
 * Creates a cross-origin XMLHttpRequest instance.
 * @param {string} method
 * @param {string} url
 * @returns {XMLHttpRequest|null}
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
 * Remembers the currently selected installment count.
 * @param {string|number} _old_vnoski
 */
function dskapi_pogasitelni_vnoski_input_focus(_old_vnoski) {
    old_vnoski = _old_vnoski;
}

/**
 * Requests updated installment data from the bank.
 * @param {boolean} [showAlerts=true] Whether to show validation messages.
 */
function dskapi_pogasitelni_vnoski_input_change(showAlerts = true) {
    const installmentsInput = getElement(SELECTORS.installmentsInput);
    if (!installmentsInput) {
        return;
    }

    const dskapi_vnoski = parseFloat(installmentsInput.value);
    const dskapi_price = parseFloat(getElement(SELECTORS.priceInput).value);
    const dskapi_cid = getElement(SELECTORS.cid).value;
    const DSKAPI_LIVEURL = getElement(SELECTORS.liveUrl).value;
    const dskapi_product_id = getElement(SELECTORS.productId).value;

    const xmlhttpro = createCORSRequest(
        'GET',
        `${DSKAPI_LIVEURL}/function/getproductcustom.php?cid=${dskapi_cid}&price=${dskapi_price}&product_id=${dskapi_product_id}&dskapi_vnoski=${dskapi_vnoski}`
    );

    xmlhttpro.onreadystatechange = function () {
        if (this.readyState !== 4) {
            return;
        }

        const payload = safeJsonParse(this.response);
        if (!payload) {
            return;
        }

        const dsk_vnoska = parseFloat(payload.dsk_vnoska);
        const dsk_gpr = parseFloat(payload.dsk_gpr);

        if (payload.dsk_is_visible) {
            toggleButtonVisibility(true);
            if (payload.dsk_options) {
                updateInstallmentOutputs({
                    installment: dsk_vnoska,
                    count: dskapi_vnoski,
                    gpr: dsk_gpr,
                });
                old_vnoski = dskapi_vnoski;
                return;
            }

            if (showAlerts) {
                showAlert('Избраният брой погасителни вноски е под минималния.', 'warning');
            }
            installmentsInput.value = old_vnoski;
            return;
        }

        toggleButtonVisibility(false);
        if (showAlerts) {
            showAlert('Избраният брой погасителни вноски е над максималния.', 'warning');
        }
        installmentsInput.value = old_vnoski;
    };

    xmlhttpro.send();
}

document.addEventListener('DOMContentLoaded', function () {
    const btn_dskapi = document.getElementById('btn_dskapi');
    if (btn_dskapi !== null) {
        const dskapi_button_status = parseInt(
            document.getElementById('dskapi_button_status').value
        );
        const dskapiProductPopupContainer = document.getElementById(
            'dskapi-product-popup-container'
        );
        const dskapi_back_credit = document.getElementById('dskapi_back_credit');
        const dskapi_buy_credit = document.getElementById('dskapi_buy_credit');

        const dskapi_price = getElement(SELECTORS.hiddenPrice);
        const dskapi_maxstojnost = getElement(SELECTORS.maxValue);

        /**
         * Calculates current credit values and optionally opens the popup.
         * @param {boolean} [showPopup=false]
         */
        const dskapi_calculateAndUpdateProductPrice = (showPopup = false) => {
            const dskapi_eur = parseInt(document.getElementById('dskapi_eur').value);
            const dskapi_currency_code = document.getElementById(
                'dskapi_currency_code'
            ).value;
            let dskapi_price1 = dskapi_price.value;
            let dskapi_quantity = 1;

            if (document.getElementById('input-quantity') !== null) {
                dskapi_quantity = parseFloat(
                    document.getElementById('input-quantity').value
                );
            }

            const finalizeCalculation = (priceAll) => {
                const dskapi_price_txt = document.getElementById('dskapi_price_txt');
                if (!dskapi_price_txt || isNaN(priceAll)) {
                    return;
                }

                dskapi_price_txt.value = priceAll.toFixed(2);

                dskapi_pogasitelni_vnoski_input_change(showPopup);

                if (!showPopup) {
                    return;
                }

                if (
                    dskapiProductPopupContainer &&
                    priceAll <= parseFloat(dskapi_maxstojnost.value)
                ) {
                    dskapiProductPopupContainer.style.display = 'block';
                } else {
                    showAlert(
                        'Максимално позволената цена за кредит ' +
                        parseFloat(dskapi_maxstojnost.value).toFixed(2) +
                        ' е надвишена!',
                        'warning'
                    );
                }
            };

            const adjustPrice = (price) =>
                applyCurrencyAdjustment(price, dskapi_eur, dskapi_currency_code);

            const processPrice = (total_price) => {
                const adjustedPrice =
                    (parseFloat(dskapi_price1) + total_price) * dskapi_quantity;
                finalizeCalculation(adjustedPrice);
            };

            let total_price = 0;

            const handleSuccessResponse = (json) => {
                $('.alert, .text-danger').remove();
                if (json['error']) {
                    handleCartErrors({ error: json['error'] }, showPopup);
                    if (!showPopup) {
                        calculateWithoutAjax();
                    }
                    return;
                }
                if (json['success']) {
                    for (let i = 0; i < json['optionresult'].length; i++) {
                        const current_options =
                            json['optionresult'][i]['product_option_id_check'];
                        if (
                            Object.prototype.toString.call(current_options) ===
                            '[object Array]'
                        ) {
                            for (let m = 0; m < current_options.length; m++) {
                                for (
                                    let n = 0;
                                    n < json['optionresult'][i]['product_option_value'].length;
                                    n++
                                ) {
                                    const tempid = parseInt(
                                        JSON.stringify(
                                            json['optionresult'][i]['product_option_value'][n][
                                            'product_option_value_id'
                                            ]
                                        ).replace(/['"]+/g, '')
                                    );
                                    const curid = parseInt(
                                        JSON.stringify(current_options[m]).replace(/['"]+/g, '')
                                    );
                                    if (tempid == curid) {
                                        total_price += parseFloat(
                                            JSON.stringify(
                                                json['optionresult'][i]['product_option_value'][n][
                                                'price'
                                                ]
                                            ).replace(/['"]+/g, '')
                                        );
                                    }
                                }
                            }
                        } else {
                            for (
                                let j = 0;
                                j < json['optionresult'][i]['product_option_value'].length;
                                j++
                            ) {
                                const tempid = parseInt(
                                    JSON.stringify(
                                        json['optionresult'][i]['product_option_value'][j][
                                        'product_option_value_id'
                                        ]
                                    ).replace(/['"]+/g, '')
                                );
                                const curid = parseInt(
                                    JSON.stringify(current_options).replace(/['"]+/g, '')
                                );
                                if (tempid == curid) {
                                    total_price += parseFloat(
                                        JSON.stringify(
                                            json['optionresult'][i]['product_option_value'][j][
                                            'price'
                                            ]
                                        ).replace(/['"]+/g, '')
                                    );
                                }
                            }
                        }
                    }
                    total_price = adjustPrice(total_price);
                    processPrice(total_price);
                }
            };

            const calculateWithoutAjax = () => {
                total_price = adjustPrice(total_price);
                processPrice(total_price);
            };

            if (document.querySelectorAll('[id*="input-option"]').length > 0) {
                if (typeof $ !== 'undefined' && $ !== null) {
                    $.ajax({
                        url: 'index.php?route=extension/mt_dskapi_credit/module/mt_dskapi_credit.dskapiCheck',
                        type: 'post',
                        data: $(
                            "#product input[type='text'], #product input[type='hidden'], #product input[type='radio']:checked, #product input[type='checkbox']:checked, #product select, #product textarea"
                        ),
                        dataType: 'json',
                        success: function (json) {
                            handleSuccessResponse(json);
                        },
                    });
                } else {
                    calculateWithoutAjax();
                }
            } else {
                calculateWithoutAjax();
            }
        };

        window.dskapi_calculateAndUpdateProductPrice =
            dskapi_calculateAndUpdateProductPrice;

        // Изпълняваме процедурата веднъж след зареждане,
        // за да синхронизираме цената спрямо текущите опции и количества.
        setTimeout(() => {
            dskapi_calculateAndUpdateProductPrice(false);

            const installmentsInput = document.getElementById(
                'dskapi_pogasitelni_vnoski_input'
            );
            if (installmentsInput) {
                dskapi_pogasitelni_vnoski_input_change(false);
            }
        }, 100);

        btn_dskapi.addEventListener('click', (event) => {
            event.preventDefault();

            if (dskapi_button_status === 1) {
                const restoreButtonState = () => {
                    btn_dskapi.classList.remove('is-loading');
                    btn_dskapi.style.pointerEvents = '';
                };

                btn_dskapi.classList.add('is-loading');
                btn_dskapi.style.pointerEvents = 'none';

                addProductToCart()
                    .then((response) => {
                        if (response && response.success) {
                            redirectToCheckoutWithDskapi();
                        } else {
                            handleCartErrors(response);
                        }
                    })
                    .catch((error) => {
                        handleCartErrors(error);
                    })
                    .finally(restoreButtonState);

                return;
            }

            dskapi_calculateAndUpdateProductPrice(true);
        });

        dskapi_back_credit.addEventListener('click', (event) => {
            dskapiProductPopupContainer.style.display = 'none';
        });

        dskapi_buy_credit.addEventListener('click', (event) => {
            event.preventDefault();

            if (dskapiProductPopupContainer) {
                dskapiProductPopupContainer.style.display = 'none';
            }

            const restoreButtonState = () => {
                dskapi_buy_credit.classList.remove('is-loading');
                dskapi_buy_credit.disabled = false;
            };

            dskapi_buy_credit.classList.add('is-loading');
            dskapi_buy_credit.disabled = true;

            addProductToCart()
                .then((response) => {
                    if (response && response.success) {
                        redirectToCheckoutWithDskapi();
                    } else {
                        handleCartErrors(response);
                    }
                })
                .catch((error) => {
                    handleCartErrors(error);
                })
                .finally(restoreButtonState);
        });

        /**
         * Triggers a recalculation without UI alerts.
         */
        const triggerSilentRecalc = () => {
            dskapi_calculateAndUpdateProductPrice(false);
        };

        /**
         * Subscribes to quantity change/input events.
         */
        const bindQuantityListeners = () => {
            const quantityInputs = document.querySelectorAll(
                '#input-quantity, [name="quantity"]'
            );

            quantityInputs.forEach((input) => {
                ['change', 'input'].forEach((evt) => {
                    input.addEventListener(evt, triggerSilentRecalc);
                });
            });
        };

        /**
         * Subscribes to option controls that can affect pricing.
         */
        const bindOptionListeners = () => {
            const optionSelectors = [
                '#product select',
                '#product input[type="radio"]',
                '#product input[type="checkbox"]',
                '#product input[type="text"]',
                '#product input[type="hidden"]',
                '#product textarea',
            ];

            optionSelectors.forEach((selector) => {
                document.querySelectorAll(selector).forEach((element) => {
                    const events =
                        element.tagName === 'SELECT' ||
                            element.type === 'radio' ||
                            element.type === 'checkbox'
                            ? ['change']
                            : ['input', 'change'];

                    events.forEach((evt) => {
                        element.addEventListener(evt, triggerSilentRecalc);
                    });
                });
            });
        };

        bindQuantityListeners();
        bindOptionListeners();
    }
});
