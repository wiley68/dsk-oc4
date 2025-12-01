<?php

namespace Opencart\Catalog\Controller\Extension\MtDskapiCredit\Event;

/**
 * Class MtDskapiCreditCheckout
 *
 * @package Opencart\Catalog\Controller\Extension\MtDskapiCredit\Event
 */
class MtDskapiCreditCheckout extends \Opencart\System\Engine\Controller
{
    /**
     * Saves dskapi=1 parameter to session if present in URL
     * This method is executed for all pages before redirect to login
     *
     * @param string &$route
     * @param array &$data
     * @return void
     */
    public function saveParam(&$route, &$data): void
    {
        // Save dskapi=1 parameter to session if present in URL
        // Only if parameter is exactly "1" to avoid accidental activations
        if (
            isset($this->request->get['dskapi']) &&
            $this->request->get['dskapi'] === '1' &&
            is_string($this->request->get['dskapi'])
        ) {
            $this->session->data['dskapi_preferred'] = true;
        }
    }

    /**
     * Saves dskapi=1 parameter to session when loading checkout page
     * Also restores the parameter in URL if saved in session but missing from URL
     *
     * @param string &$route
     * @param array &$data
     * @return void
     */
    public function init(&$route, &$data): void
    {
        // Save dskapi=1 parameter to session if present in URL (for all pages, not just checkout)
        // Only if parameter is exactly "1" to avoid accidental activations
        if (
            isset($this->request->get['dskapi']) &&
            $this->request->get['dskapi'] === '1' &&
            is_string($this->request->get['dskapi'])
        ) {
            $this->session->data['dskapi_preferred'] = true;
        }

        // Check if we are on checkout page
        if ($route !== 'checkout/checkout') {
            return;
        }

        // If we have saved parameter in session but it's not in URL, add it back
        if (
            isset($this->session->data['dskapi_preferred']) &&
            $this->session->data['dskapi_preferred'] === true &&
            (!isset($this->request->get['dskapi']) || $this->request->get['dskapi'] !== '1')
        ) {
            // Add parameter back to URL via redirect
            $getParams = $this->request->get;
            $getParams['dskapi'] = '1';
            $currentUrl = $this->url->link($route, http_build_query($getParams), true);
            $this->response->redirect($currentUrl);
            return;
        }
    }

    /**
     * Adds JavaScript code for automatically selecting dskapi payment method
     *
     * @param string &$route
     * @param array &$data
     * @param string &$output
     * @return void
     */
    public function addScript(&$route, &$data, &$output): void
    {
        // Check if we are on checkout page
        if ($route !== 'checkout/checkout') {
            return;
        }

        // Check if dskapi=1 parameter exists in URL or session
        // Only if parameter is exactly "1" to avoid accidental activations
        $has_dskapi = (isset($this->request->get['dskapi']) &&
            $this->request->get['dskapi'] === '1' &&
            is_string($this->request->get['dskapi'])) ||
            (isset($this->session->data['dskapi_preferred']) &&
                $this->session->data['dskapi_preferred'] === true);

        if (!$has_dskapi) {
            return;
        }

        // Check if output is a valid string
        if (!is_string($output) || strpos($output, '</body>') === false) {
            return;
        }

        // Add JavaScript code for automatically selecting payment method
        $saveUrl = $this->url->link('checkout/payment_method.save', 'language=' . $this->config->get('config_language'));
        $getMethodsUrl = $this->url->link('checkout/payment_method.getMethods', 'language=' . $this->config->get('config_language'));
        $confirmUrl = $this->url->link('checkout/confirm.confirm', 'language=' . $this->config->get('config_language'));
        $language = $this->config->get('config_language');

        // phpcs:ignore -- JavaScript code in string, linter errors are false positives
        $script = <<<JS
<script>
(function() {
    "use strict";
    
    // Check if jQuery is available (required for OpenCart)
    if (typeof jQuery === "undefined" || typeof $ === "undefined") {
        console.warn("DSKAPI: jQuery is not available, stopping execution");
        return;
    }
    
    // Check if dskapi=1 parameter exists in URL
    // This will work even after PHP redirect when parameter is restored from session
    try {
        const urlParams = new URLSearchParams(window.location.search);
        const dskapiParam = urlParams.get("dskapi");
        const hasDskapi = dskapiParam === "1";
        
        if (!hasDskapi) {
            return;
        }
    } catch (e) {
        console.warn("DSKAPI: Error checking URL parameters:", e);
        return;
    }
    
    // Function to check if shipping is selected
    function isShippingSelected() {
        try {
            // Check if shipping method is selected
            const shippingCode = document.querySelector("#input-shipping-code");
            // If there is no shipping method at all (product without delivery), consider shipping as "selected"
            const hasShipping = document.querySelector("#checkout-shipping-method");
            if (!hasShipping) {
                return true; // No shipping, so we can save payment
            }
            // If shipping section exists, check if it's selected
            return shippingCode && shippingCode.value && shippingCode.value !== "";
        } catch (e) {
            console.warn("DSKAPI: Error checking shipping:", e);
            return false; // On error consider shipping as not selected (safe fallback)
        }
    }
    
    // Flag to prevent multiple executions
    let isSavingPayment = false;
    let paymentSaved = false;
    
    // Function to check if payment method is already saved
    function isPaymentMethodSaved() {
        try {
            const paymentCodeInput = document.querySelector("#input-payment-code");
            return paymentCodeInput && paymentCodeInput.value === "dskapi.dskapi";
        } catch (e) {
            console.warn("DSKAPI: Error checking payment method:", e);
            return false; // On error consider it's not saved (safe fallback)
        }
    }
    
    // Function for automatically saving dskapi payment method
    function autoSaveDskapiPayment() {
        try {
            // Check if already executing or already saved
            if (isSavingPayment || paymentSaved || isPaymentMethodSaved()) {
                return false;
            }
            
            if (!isShippingSelected()) {
                return false;
            }
            
            isSavingPayment = true;
            
            // First load payment methods so they are in session
            $.ajax({
                url: "{$getMethodsUrl}",
                dataType: "json",
                timeout: 10000, // 10 seconds timeout to prevent hanging
                success: function(paymentMethodsJson) {
                    try {
                        if (!paymentMethodsJson || paymentMethodsJson["error"]) {
                            isSavingPayment = false;
                            if (paymentMethodsJson && paymentMethodsJson["error"]) {
                                console.warn("DSKAPI getMethods error: " + paymentMethodsJson["error"]);
                            }
                            return;
                        }
                        
                        // Check if dskapi payment method is available
                        if (!paymentMethodsJson["payment_methods"] || 
                            !paymentMethodsJson["payment_methods"]["dskapi"] ||
                            !paymentMethodsJson["payment_methods"]["dskapi"]["option"] ||
                            !paymentMethodsJson["payment_methods"]["dskapi"]["option"]["dskapi"]) {
                            isSavingPayment = false;
                            console.warn("DSKAPI payment method not available");
                            return;
                        }
                        
                        // Now save the payment method
                        $.ajax({
                            url: "{$saveUrl}",
                            type: "post",
                            data: { payment_method: "dskapi.dskapi" },
                            dataType: "json",
                            contentType: "application/x-www-form-urlencoded",
                            timeout: 10000, // 10 seconds timeout
                            success: function(json) {
                                try {
                                    isSavingPayment = false;
                                    
                                    if (!json) {
                                        console.warn("DSKAPI: Empty response from save");
                                        return;
                                    }
                                    
                                    if (json["redirect"]) {
                                        location = json["redirect"];
                                        return;
                                    }
                                    
                                    if (json["success"]) {
                                        paymentSaved = true;
                                        
                                        // Update input field with selected payment method
                                        const paymentMethodInput = document.querySelector("#input-payment-method");
                                        const paymentCodeInput = document.querySelector("#input-payment-code");
                                        
                                        if (paymentMethodInput && paymentCodeInput) {
                                            // Set the code
                                            paymentCodeInput.value = "dskapi.dskapi";
                                            
                                            // Set the name from loaded payment methods
                                            const dskapiMethod = paymentMethodsJson["payment_methods"]["dskapi"];
                                            if (dskapiMethod["option"] && 
                                                dskapiMethod["option"]["dskapi"] && 
                                                dskapiMethod["option"]["dskapi"]["name"]) {
                                                paymentMethodInput.value = dskapiMethod["option"]["dskapi"]["name"];
                                            }
                                            
                                            // Update confirm section only if element exists
                                            const confirmSection = document.querySelector("#checkout-confirm");
                                            if (confirmSection && typeof $ !== "undefined" && $.fn.load) {
                                                $("#checkout-confirm").load("{$confirmUrl}");
                                            }
                                        }
                                    } else if (json["error"]) {
                                        console.warn("DSKAPI save error: " + json["error"]);
                                    }
                                } catch (e) {
                                    isSavingPayment = false;
                                    console.error("DSKAPI: Error processing save response:", e);
                                }
                            },
                            error: function(xhr, ajaxOptions, thrownError) {
                                isSavingPayment = false;
                                console.warn("DSKAPI auto-save AJAX error: " + thrownError);
                            }
                        });
                    } catch (e) {
                        isSavingPayment = false;
                        console.error("DSKAPI: Error processing getMethods response:", e);
                    }
                },
                error: function(xhr, ajaxOptions, thrownError) {
                    isSavingPayment = false;
                    console.warn("DSKAPI getMethods AJAX error: " + thrownError);
                }
            });
            return true;
        } catch (e) {
            isSavingPayment = false;
            console.error("DSKAPI: Error in autoSaveDskapiPayment:", e);
            return false;
        }
    }
    
    // Function for automatically selecting dskapi radio button in popup (without automatic submission)
    function selectDskapiRadio() {
        try {
            const dskapiRadio = document.querySelector('input[name="payment_method"][value="dskapi.dskapi"]');
            if (dskapiRadio && !dskapiRadio.checked) {
                dskapiRadio.checked = true;
                return true;
            }
            return false;
        } catch (e) {
            console.warn("DSKAPI: Error selecting radio button:", e);
            return false;
        }
    }
    
    // Listen for modal window opening
    try {
        $(document).on("shown.bs.modal", "#modal-payment", function() {
            try {
                // When popup opens, automatically select DSK radio button
                setTimeout(function() {
                    try {
                        if (!selectDskapiRadio()) {
                            // If we don't succeed immediately, try again after a short time
                            let attempts = 0;
                            const maxAttempts = 20;
                            const checkInterval = setInterval(function() {
                                try {
                                    attempts++;
                                    if (selectDskapiRadio() || attempts >= maxAttempts) {
                                        clearInterval(checkInterval);
                                    }
                                } catch (e) {
                                    clearInterval(checkInterval);
                                    console.warn("DSKAPI: Error in checkInterval:", e);
                                }
                            }, 200);
                        }
                    } catch (e) {
                        console.warn("DSKAPI: Error selecting radio button in popup:", e);
                    }
                }, 100);
            } catch (e) {
                console.warn("DSKAPI: Error in modal event handler:", e);
            }
        });
    } catch (e) {
        console.warn("DSKAPI: Error registering modal event:", e);
    }
    
    // Try to save payment method automatically on page load
    // but only if shipping is already selected
    try {
        $(document).ready(function() {
            try {
                // Check if shipping is selected immediately
                if (isShippingSelected()) {
                    setTimeout(function() {
                        try {
                            autoSaveDskapiPayment();
                        } catch (e) {
                            console.warn("DSKAPI: Error attempting automatic save on load:", e);
                        }
                    }, 1000);
                }
                
                // Listen for successful shipping method save by intercepting AJAX requests
                // When shipping is saved successfully (on first selection or change), try to save payment method
                $(document).ajaxSuccess(function(event, xhr, settings) {
                    try {
                        // Check if settings and url exist
                        if (!settings || !settings.url) {
                            return;
                        }
                        
                        // Listen for successful shipping method save
                        if (settings.url.indexOf("checkout/shipping_method.save") !== -1) {
                            // Check if request was successful
                            try {
                                const response = typeof xhr.responseText === "string" ? JSON.parse(xhr.responseText) : xhr.responseText;
                                if (response && response.success) {
                                    // Reset payment flag so we can save it again on each shipping change
                                    paymentSaved = false;
                                    
                                    // Wait for confirm section loading to complete before trying to save payment method
                                    // This ensures OpenCart has finished all its operations
                                    let confirmLoadAttempts = 0;
                                    const maxConfirmAttempts = 10;
                                    
                                    const checkConfirmAndSave = setInterval(function() {
                                        try {
                                            confirmLoadAttempts++;
                                            // Check if confirm section is loaded (has content)
                                            const confirmSection = document.querySelector("#checkout-confirm");
                                            if (confirmSection && 
                                                confirmSection.innerHTML && 
                                                confirmSection.innerHTML.trim() !== "" && 
                                                isShippingSelected()) {
                                                clearInterval(checkConfirmAndSave);
                                                // Wait a bit more for everything to stabilize
                                                setTimeout(function() {
                                                    try {
                                                        if (!isPaymentMethodSaved()) {
                                                            autoSaveDskapiPayment();
                                                        }
                                                    } catch (e) {
                                                        console.warn("DSKAPI: Error attempting save after confirm load:", e);
                                                    }
                                                }, 300);
                                            } else if (confirmLoadAttempts >= maxConfirmAttempts) {
                                                clearInterval(checkConfirmAndSave);
                                                // If we can't wait for confirm, try directly (fallback)
                                                setTimeout(function() {
                                                    try {
                                                        if (isShippingSelected() && !isPaymentMethodSaved()) {
                                                            autoSaveDskapiPayment();
                                                        }
                                                    } catch (e) {
                                                        console.warn("DSKAPI: Error in fallback save attempt:", e);
                                                    }
                                                }, 1000);
                                            }
                                        } catch (e) {
                                            clearInterval(checkConfirmAndSave);
                                            console.warn("DSKAPI: Error in checkConfirmAndSave interval:", e);
                                        }
                                    }, 200);
                                }
                            } catch (e) {
                                // Ignore JSON parsing errors (may not be JSON response)
                                console.warn("DSKAPI: Error parsing shipping save response:", e);
                            }
                        }
                    } catch (e) {
                        console.warn("DSKAPI: Error in ajaxSuccess handler:", e);
                    }
                });
            } catch (e) {
                console.error("DSKAPI: Error in document.ready:", e);
            }
        });
    } catch (e) {
        console.error("DSKAPI: Error in initialization:", e);
    }
})();
</script>
JS;

        // Add script before closing </body> tag
        $output = str_replace('</body>', $script . '</body>', $output);
    }
}
