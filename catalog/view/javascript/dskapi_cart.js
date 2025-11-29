let old_vnoski;

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

function dskapi_pogasitelni_vnoski_input_focus(_old_vnoski) {
    old_vnoski = _old_vnoski;
}

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
                console.error('Грешка при парсиране на отговора:', e);
                return;
            }

            var options = responseData.dsk_options;
            var dsk_vnoska = parseFloat(responseData.dsk_vnoska);
            var dsk_gpr = parseFloat(responseData.dsk_gpr);
            var dsk_is_visible = responseData.dsk_is_visible;

            if (dsk_is_visible) {
                if (options) {
                    // Актуализиране на полетата в попъпа
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

                    // Актуализиране на полетата в бутона (ако съществуват)
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
                    alert('Избраният брой погасителни вноски е под минималния.');
                    var dskapi_vnoski_input = document.getElementById(
                        'dskapi_pogasitelni_vnoski_input'
                    );
                    if (dskapi_vnoski_input) {
                        dskapi_vnoski_input.value = old_vnoski;
                    }
                }
            } else {
                alert('Избраният брой погасителни вноски е над максималния.');
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
 * Добавя продукта в количката и пренасочва към checkout с избран платежен метод DSK
 * За използване в cart страницата - директно редирект към checkout без добавяне в количката
 *
 * @return {void}
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

function resolveBaseUrl() {
    const baseTag = document.querySelector('base');
    return baseTag && baseTag.href ? baseTag.href : window.location.origin + '/';
}

function buildAbsoluteUrl(path) {
    const normalized = path.startsWith('/') ? path.slice(1) : path;
    return new URL(normalized, resolveBaseUrl()).toString();
}

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

// Делегиране на събития за бутона "Купи на изплащане" - използваме document-level listener
// Това работи дори ако елементите се зареждат динамично и се изпълнява само веднъж
let dskapiBuyCreditHandlerBound = false;

function initDskapiCartWidget() {
    // Делегиране на събития за бутона "Купи на изплащане" - само веднъж
    if (!dskapiBuyCreditHandlerBound) {
        dskapiBuyCreditHandlerBound = true;
        document.addEventListener(
            'click',
            function (event) {
                const target = event.target;
                // Проверяваме дали кликването е върху бутона или неговите деца
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
        ); // Използваме capture phase за по-ранно intercept-ване
    }

    // Задаваме cursor стил на бутона ако съществува
    const dskapi_buy_credit = document.getElementById('dskapi_buy_credit');
    if (dskapi_buy_credit !== null) {
        dskapi_buy_credit.style.cursor = 'pointer';
    }

    // Инициализираме основния бутон btn_dskapi
    const btn_dskapi = document.getElementById('btn_dskapi');
    if (btn_dskapi !== null && btn_dskapi.dataset.dskapiBound !== '1') {
        btn_dskapi.dataset.dskapiBound = '1';

        const dskapi_button_status_el = document.getElementById(
            'dskapi_button_status'
        );
        if (!dskapi_button_status_el) {
            return; // Ако няма button_status елемент, не продължаваме
        }

        const dskapi_button_status = parseInt(dskapi_button_status_el.value) || 0;
        const dskapiProductPopupContainer = document.getElementById(
            'dskapi-product-popup-container'
        );
        const dskapi_back_credit = document.getElementById('dskapi_back_credit');

        const dskapi_price = document.getElementById('dskapi_price');
        const dskapi_maxstojnost = document.getElementById('dskapi_maxstojnost');

        if (!dskapi_price || !dskapi_maxstojnost) {
            return; // Ако няма нужните елементи, не продължаваме
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
                            'Максимално позволената цена за кредит ' +
                            parseFloat(dskapi_maxstojnost.value).toFixed(2) +
                            ' е надвишена!'
                        );
                    }
                }
                return false;
            },
            true
        ); // Използваме capture phase

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

// Функция за инициализация с няколко опита
function initDskapiCartWidgetWithRetry() {
    let attempts = 0;
    const maxAttempts = 10;

    const tryInit = function () {
        attempts++;

        // Проверяваме дали нужните елементи съществуват
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

document.addEventListener('DOMContentLoaded', function () {
    initDskapiCartWidgetWithRetry();
});

if (typeof prestashop !== 'undefined' && prestashop.on) {
    prestashop.on('updatedCart', function () {
        setTimeout(initDskapiCartWidgetWithRetry, 100);
    });
    prestashop.on('updateCart', function () {
        setTimeout(initDskapiCartWidgetWithRetry, 100);
    });
}

// Също опитваме се след пълно зареждане на страницата
window.addEventListener('load', function () {
    setTimeout(initDskapiCartWidgetWithRetry, 300);
});
