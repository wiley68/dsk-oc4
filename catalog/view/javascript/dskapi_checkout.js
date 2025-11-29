/**
 * DSKAPI Checkout - Management of interest schemes popup
 * Handles popup interactions, installment calculations, and dynamic field updates
 */
(function () {
    'use strict';

    let old_vnoski_checkout;
    let popupInitialized = false;

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
    function dskapi_pogasitelni_vnoski_input_focus_checkout(_old_vnoski) {
        old_vnoski_checkout = _old_vnoski;
    }

    /**
     * Updates installment fields when the number of installments changes
     * Makes API call to get updated payment information and updates popup fields
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
                        alert('The selected number of installments is below the minimum.');
                        if (installmentsInput) {
                            installmentsInput.value = old_vnoski_checkout;
                        }
                    }
                } else {
                    alert('The selected number of installments is above the maximum.');
                    if (installmentsInput) {
                        installmentsInput.value = old_vnoski_checkout;
                    }
                }
            }
        };

        xmlhttpro.send();
    }

    /**
     * Closes the popup
     *
     * @param {HTMLElement} popupContainer Popup container element
     */
    function closePopup(popupContainer) {
        if (popupContainer) {
            popupContainer.style.display = 'none';
            document.body.style.overflow = ''; // Restore scrolling
        }
    }

    /**
     * Opens the popup
     *
     * @param {HTMLElement} popupContainer Popup container element
     */
    function openPopup(popupContainer) {
        if (popupContainer) {
            popupContainer.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }
    }

    /**
     * Initializes functionality for managing the interest schemes popup
     * Sets up event handlers for opening/closing popup via link, button, outside click, and ESC key
     */
    function initInterestSchemesPopup() {
        // Prevent multiple initializations
        if (popupInitialized) {
            return;
        }

        const popupContainer = document.getElementById('dskapi-interest-schemes-popup');
        const openLink = document.getElementById('dskapi-interest-schemes-link');
        const installmentsInput = document.getElementById('dskapi_pogasitelni_vnoski_input_checkout');

        if (!popupContainer || !openLink) {
            // Retry initialization if elements are not yet available (max 10 attempts)
            if (typeof initInterestSchemesPopup.retryCount === 'undefined') {
                initInterestSchemesPopup.retryCount = 0;
            }
            if (initInterestSchemesPopup.retryCount < 10) {
                initInterestSchemesPopup.retryCount++;
                setTimeout(initInterestSchemesPopup, 100);
            }
            return;
        }

        // Mark as initialized
        popupInitialized = true;

        // Initialize old value on load
        if (installmentsInput) {
            old_vnoski_checkout = parseFloat(installmentsInput.value);
        }

        // Use event delegation for more reliable event handling
        // Open popup on link click
        openLink.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const currentPopup = document.getElementById('dskapi-interest-schemes-popup');
            if (currentPopup) {
                openPopup(currentPopup);
            }
        }, false);

        // Close popup on "Close" button click using event delegation
        document.addEventListener('click', function (e) {
            if (e.target && e.target.id === 'dskapi-interest-schemes-close') {
                e.preventDefault();
                e.stopPropagation();
                const currentPopup = document.getElementById('dskapi-interest-schemes-popup');
                closePopup(currentPopup);
            }
        }, false);

        // Close popup on click outside content
        popupContainer.addEventListener('click', function (e) {
            if (e.target === popupContainer) {
                closePopup(popupContainer);
            }
        }, false);

        // Close popup on ESC key press
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                const currentPopup = document.getElementById('dskapi-interest-schemes-popup');
                if (currentPopup && currentPopup.style.display !== 'none') {
                    closePopup(currentPopup);
                }
            }
        }, false);
    }

    // Export functions globally for use in onchange and onfocus attributes
    window.dskapi_pogasitelni_vnoski_input_focus_checkout = dskapi_pogasitelni_vnoski_input_focus_checkout;
    window.dskapi_pogasitelni_vnoski_input_change_checkout = dskapi_pogasitelni_vnoski_input_change_checkout;

    // Initialize on DOM load with multiple fallbacks
    function tryInit() {
        const popupContainer = document.getElementById('dskapi-interest-schemes-popup');
        const openLink = document.getElementById('dskapi-interest-schemes-link');

        if (popupContainer && openLink) {
            initInterestSchemesPopup();
        } else if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', tryInit);
        } else {
            // If DOM is ready but elements are not found, retry after a short delay
            setTimeout(tryInit, 100);
        }
    }

    // Start initialization
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInit);
    } else {
        // DOM is already loaded, but try immediately and retry if needed
        tryInit();
    }
})();

