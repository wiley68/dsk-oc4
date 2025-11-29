/**
 * DSKAPI Checkout - Управление на попъпа с лихвени схеми
 */
(function () {
    'use strict';

    let old_vnoski_checkout;

    /**
     * Създава CORS заявка
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
     * Запомня текущо избрания брой вноски
     */
    function dskapi_pogasitelni_vnoski_input_focus_checkout(_old_vnoski) {
        old_vnoski_checkout = _old_vnoski;
    }

    /**
     * Актуализира данните при промяна на избора на вноски
     */
    function dskapi_pogasitelni_vnoski_input_change_checkout() {
        const installmentsInput = document.getElementById('dskapi_pogasitelni_vnoski_input_checkout');
        if (!installmentsInput) {
            return;
        }

        const dskapi_vnoski = parseFloat(installmentsInput.value);
        const dskapi_price = parseFloat(document.getElementById('dskapi_price_txt_checkout').value);
        const dskapi_cid = document.getElementById('dskapi_cid_checkout').value;
        const DSKAPI_LIVEURL = document.getElementById('DSKAPI_LIVEURL_checkout').value;
        const dskapi_product_id = document.getElementById('dskapi_product_id_checkout').value;

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
                        const dskapi_vnoska_input = document.getElementById('dskapi_vnoska_checkout');
                        const dskapi_gpr = document.getElementById('dskapi_gpr_checkout');
                        const dskapi_obshtozaplashtane_input = document.getElementById('dskapi_obshtozaplashtane_checkout');
                        const dskapi_price_txt_display = document.getElementById('dskapi_price_txt_checkout_display');

                        if (dskapi_vnoska_input) {
                            dskapi_vnoska_input.value = dsk_vnoska.toFixed(2);
                        }
                        if (dskapi_gpr) {
                            dskapi_gpr.value = dsk_gpr.toFixed(2);
                        }
                        if (dskapi_obshtozaplashtane_input) {
                            dskapi_obshtozaplashtane_input.value = (dsk_vnoska * dskapi_vnoski).toFixed(2);
                        }
                        if (dskapi_price_txt_display) {
                            dskapi_price_txt_display.value = dskapi_price.toFixed(2);
                        }

                        old_vnoski_checkout = dskapi_vnoski;
                    } else {
                        alert('Избраният брой погасителни вноски е под минималния.');
                        if (installmentsInput) {
                            installmentsInput.value = old_vnoski_checkout;
                        }
                    }
                } else {
                    alert('Избраният брой погасителни вноски е над максималния.');
                    if (installmentsInput) {
                        installmentsInput.value = old_vnoski_checkout;
                    }
                }
            }
        };

        xmlhttpro.send();
    }

    /**
     * Инициализира функционалността за управление на попъпа с лихвени схеми
     */
    function initInterestSchemesPopup() {
        const popupContainer = document.getElementById('dskapi-interest-schemes-popup');
        const openLink = document.getElementById('dskapi-interest-schemes-link');
        const closeButton = document.getElementById('dskapi-interest-schemes-close');
        const installmentsInput = document.getElementById('dskapi_pogasitelni_vnoski_input_checkout');

        if (!popupContainer || !openLink) {
            return;
        }

        // Инициализиране на старата стойност при зареждане
        if (installmentsInput) {
            old_vnoski_checkout = parseFloat(installmentsInput.value);
        }

        // Отваряне на попъпа при клик върху линка
        openLink.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            popupContainer.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Предотвратява скролване на фона
        });

        // Затваряне на попъпа при клик върху бутона "Затвори"
        if (closeButton) {
            closeButton.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                closePopup();
            });
        }

        // Затваряне на попъпа при клик извън съдържанието
        popupContainer.addEventListener('click', function (e) {
            if (e.target === popupContainer) {
                closePopup();
            }
        });

        // Затваряне на попъпа при натискане на ESC
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && popupContainer.style.display === 'block') {
                closePopup();
            }
        });

        /**
         * Затваря попъпа
         */
        function closePopup() {
            popupContainer.style.display = 'none';
            document.body.style.overflow = ''; // Възстановява скролването
        }
    }

    // Експортиране на функциите глобално за използване в onchange и onfocus атрибутите
    window.dskapi_pogasitelni_vnoski_input_focus_checkout = dskapi_pogasitelni_vnoski_input_focus_checkout;
    window.dskapi_pogasitelni_vnoski_input_change_checkout = dskapi_pogasitelni_vnoski_input_change_checkout;

    // Инициализация при зареждане на DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initInterestSchemesPopup);
    } else {
        initInterestSchemesPopup();
    }
})();

