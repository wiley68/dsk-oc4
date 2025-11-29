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
     * Запазва параметър dskapi=1 в session ако е наличен в URL-а
     * Този метод се изпълнява за всички страници преди редиректа към логин
     *
     * @param string &$route
     * @param array &$data
     * @return void
     */
    public function saveParam(&$route, &$data): void
    {
        // Запазваме параметъра dskapi=1 в session-а ако е наличен в URL-а
        // Само ако параметърът е точно "1" за да избегнем случайни активации
        if (
            isset($this->request->get['dskapi']) &&
            $this->request->get['dskapi'] === '1' &&
            is_string($this->request->get['dskapi'])
        ) {
            $this->session->data['dskapi_preferred'] = true;
        }
    }

    /**
     * Запазва параметър dskapi=1 в session при зареждане на checkout страницата
     * Също така възстановява параметъра в URL-а ако е запазен в session но липсва в URL-а
     *
     * @param string &$route
     * @param array &$data
     * @return void
     */
    public function init(&$route, &$data): void
    {
        // Запазваме параметъра dskapi=1 в session-а ако е наличен в URL-а (за всички страници, не само checkout)
        // Само ако параметърът е точно "1" за да избегнем случайни активации
        if (
            isset($this->request->get['dskapi']) &&
            $this->request->get['dskapi'] === '1' &&
            is_string($this->request->get['dskapi'])
        ) {
            $this->session->data['dskapi_preferred'] = true;
        }

        // Проверяваме дали сме на checkout страницата
        if ($route !== 'checkout/checkout') {
            return;
        }

        // Ако имаме запазен параметър в session но не е в URL-а, добавяме го обратно
        if (
            isset($this->session->data['dskapi_preferred']) &&
            $this->session->data['dskapi_preferred'] === true &&
            (!isset($this->request->get['dskapi']) || $this->request->get['dskapi'] !== '1')
        ) {
            // Добавяме параметъра обратно в URL-а чрез редирект
            $getParams = $this->request->get;
            $getParams['dskapi'] = '1';
            $currentUrl = $this->url->link($route, http_build_query($getParams), true);
            $this->response->redirect($currentUrl);
            return;
        }
    }

    /**
     * Добавя JavaScript код за автоматично избиране на payment метода dskapi
     *
     * @param string &$route
     * @param array &$data
     * @param string &$output
     * @return void
     */
    public function addScript(&$route, &$data, &$output): void
    {
        // Проверяваме дали сме на checkout страницата
        if ($route !== 'checkout/checkout') {
            return;
        }

        // Проверяваме дали има параметър dskapi=1 в URL-а или в session-а
        // Само ако параметърът е точно "1" за да избегнем случайни активации
        $has_dskapi = (isset($this->request->get['dskapi']) &&
            $this->request->get['dskapi'] === '1' &&
            is_string($this->request->get['dskapi'])) ||
            (isset($this->session->data['dskapi_preferred']) &&
                $this->session->data['dskapi_preferred'] === true);

        if (!$has_dskapi) {
            return;
        }

        // Проверяваме дали output е валиден string
        if (!is_string($output) || strpos($output, '</body>') === false) {
            return;
        }

        // Добавяме JavaScript код за автоматично избиране на payment метода
        $saveUrl = $this->url->link('checkout/payment_method.save', 'language=' . $this->config->get('config_language'));
        $getMethodsUrl = $this->url->link('checkout/payment_method.getMethods', 'language=' . $this->config->get('config_language'));
        $confirmUrl = $this->url->link('checkout/confirm.confirm', 'language=' . $this->config->get('config_language'));
        $language = $this->config->get('config_language');

        $script = '
<script>
(function() {
    "use strict";
    
    // Проверяваме дали jQuery е наличен (необходимо за OpenCart)
    if (typeof jQuery === "undefined" || typeof $ === "undefined") {
        console.warn("DSKAPI: jQuery не е наличен, спираме изпълнението");
        return;
    }
    
    // Проверяваме дали има параметър dskapi=1 в URL-а
    // Това ще работи и след редиректа от PHP когато параметърът е възстановен от session
    try {
        const urlParams = new URLSearchParams(window.location.search);
        const dskapiParam = urlParams.get("dskapi");
        const hasDskapi = dskapiParam === "1";
        
        if (!hasDskapi) {
            return;
        }
    } catch (e) {
        console.warn("DSKAPI: Грешка при проверка на URL параметри:", e);
        return;
    }
    
    // Функция за проверка дали shipping е избран
    function isShippingSelected() {
        try {
            // Проверяваме дали има shipping метод избран
            const shippingCode = document.querySelector("#input-shipping-code");
            // Ако няма shipping метод изобщо (продукт без доставка), считаме че shipping е "избран"
            const hasShipping = document.querySelector("#checkout-shipping-method");
            if (!hasShipping) {
                return true; // Няма shipping, значи можем да запишем payment
            }
            // Ако има shipping секция, проверяваме дали е избран
            return shippingCode && shippingCode.value && shippingCode.value !== "";
        } catch (e) {
            console.warn("DSKAPI: Грешка при проверка на shipping:", e);
            return false; // При грешка считаме че shipping не е избран (безопасен fallback)
        }
    }
    
    // Флаг за предотвратяване на многократно изпълнение
    let isSavingPayment = false;
    let paymentSaved = false;
    
    // Функция за проверка дали payment метода вече е записан
    function isPaymentMethodSaved() {
        try {
            const paymentCodeInput = document.querySelector("#input-payment-code");
            return paymentCodeInput && paymentCodeInput.value === "dskapi.dskapi";
        } catch (e) {
            console.warn("DSKAPI: Грешка при проверка на payment метод:", e);
            return false; // При грешка считаме че не е записан (безопасен fallback)
        }
    }
    
    // Функция за автоматично записване на dskapi payment метода
    function autoSaveDskapiPayment() {
        try {
            // Проверяваме дали вече не се изпълнява или вече е записан
            if (isSavingPayment || paymentSaved || isPaymentMethodSaved()) {
                return false;
            }
            
            if (!isShippingSelected()) {
                return false;
            }
            
            isSavingPayment = true;
            
            // Първо зареждаме payment методите за да бъдат в session-а
            $.ajax({
                url: "' . $getMethodsUrl . '",
                dataType: "json",
                timeout: 10000, // 10 секунди timeout за да не виси безкрайно
                success: function(paymentMethodsJson) {
                    try {
                        if (!paymentMethodsJson || paymentMethodsJson["error"]) {
                            isSavingPayment = false;
                            if (paymentMethodsJson && paymentMethodsJson["error"]) {
                                console.warn("DSKAPI getMethods error: " + paymentMethodsJson["error"]);
                            }
                            return;
                        }
                        
                        // Проверяваме дали dskapi payment методът е наличен
                        if (!paymentMethodsJson["payment_methods"] || 
                            !paymentMethodsJson["payment_methods"]["dskapi"] ||
                            !paymentMethodsJson["payment_methods"]["dskapi"]["option"] ||
                            !paymentMethodsJson["payment_methods"]["dskapi"]["option"]["dskapi"]) {
                            isSavingPayment = false;
                            console.warn("DSKAPI payment method not available");
                            return;
                        }
                        
                        // Сега записваме payment метода
                        $.ajax({
                            url: "' . $saveUrl . '",
                            type: "post",
                            data: { payment_method: "dskapi.dskapi" },
                            dataType: "json",
                            contentType: "application/x-www-form-urlencoded",
                            timeout: 10000, // 10 секунди timeout
                            success: function(json) {
                                try {
                                    isSavingPayment = false;
                                    
                                    if (!json) {
                                        console.warn("DSKAPI: Празен отговор от save");
                                        return;
                                    }
                                    
                                    if (json["redirect"]) {
                                        location = json["redirect"];
                                        return;
                                    }
                                    
                                    if (json["success"]) {
                                        paymentSaved = true;
                                        
                                        // Обновяваме input полето с избрания payment метод
                                        const paymentMethodInput = document.querySelector("#input-payment-method");
                                        const paymentCodeInput = document.querySelector("#input-payment-code");
                                        
                                        if (paymentMethodInput && paymentCodeInput) {
                                            // Задаваме кода
                                            paymentCodeInput.value = "dskapi.dskapi";
                                            
                                            // Задаваме името от заредените payment методи
                                            const dskapiMethod = paymentMethodsJson["payment_methods"]["dskapi"];
                                            if (dskapiMethod["option"] && 
                                                dskapiMethod["option"]["dskapi"] && 
                                                dskapiMethod["option"]["dskapi"]["name"]) {
                                                paymentMethodInput.value = dskapiMethod["option"]["dskapi"]["name"];
                                            }
                                            
                                            // Обновяваме confirm секцията само ако елементът съществува
                                            const confirmSection = document.querySelector("#checkout-confirm");
                                            if (confirmSection && typeof $ !== "undefined" && $.fn.load) {
                                                $("#checkout-confirm").load("' . $confirmUrl . '");
                                            }
                                        }
                                    } else if (json["error"]) {
                                        console.warn("DSKAPI save error: " + json["error"]);
                                    }
                                } catch (e) {
                                    isSavingPayment = false;
                                    console.error("DSKAPI: Грешка при обработка на save отговор:", e);
                                }
                            },
                            error: function(xhr, ajaxOptions, thrownError) {
                                isSavingPayment = false;
                                console.warn("DSKAPI auto-save AJAX error: " + thrownError);
                            }
                        });
                    } catch (e) {
                        isSavingPayment = false;
                        console.error("DSKAPI: Грешка при обработка на getMethods отговор:", e);
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
            console.error("DSKAPI: Грешка в autoSaveDskapiPayment:", e);
            return false;
        }
    }
    
    // Функция за автоматично избиране на dskapi radio бутона в popup-а (без автоматично изпращане)
    function selectDskapiRadio() {
        try {
            const dskapiRadio = document.querySelector(\'input[name="payment_method"][value="dskapi.dskapi"]\');
            if (dskapiRadio && !dskapiRadio.checked) {
                dskapiRadio.checked = true;
                return true;
            }
            return false;
        } catch (e) {
            console.warn("DSKAPI: Грешка при избиране на radio бутона:", e);
            return false;
        }
    }
    
    // Слушаме за отваряне на модалното прозорче
    try {
        $(document).on("shown.bs.modal", "#modal-payment", function() {
            try {
                // При отваряне на popup-а автоматично избираме ДСК радио бутона
                setTimeout(function() {
                    try {
                        if (!selectDskapiRadio()) {
                            // Ако не успеем веднага, опитваме се отново след кратко време
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
                                    console.warn("DSKAPI: Грешка в checkInterval:", e);
                                }
                            }, 200);
                        }
                    } catch (e) {
                        console.warn("DSKAPI: Грешка при избиране на radio бутона в popup:", e);
                    }
                }, 100);
            } catch (e) {
                console.warn("DSKAPI: Грешка в modal event handler:", e);
            }
        });
    } catch (e) {
        console.warn("DSKAPI: Грешка при регистриране на modal event:", e);
    }
    
    // Опитваме се да запишем payment метода автоматично при зареждане на страницата
    // но само ако shipping е вече избран
    try {
        $(document).ready(function() {
            try {
                // Проверяваме дали shipping е избран веднага
                if (isShippingSelected()) {
                    setTimeout(function() {
                        try {
                            autoSaveDskapiPayment();
                        } catch (e) {
                            console.warn("DSKAPI: Грешка при опит за автоматично записване при зареждане:", e);
                        }
                    }, 1000);
                }
                
                // Слушаме за успешно записване на shipping метода чрез intercept на AJAX заявките
                // Когато shipping се запише успешно (при първи избор или при смяна), опитваме се да запишем payment метода
                $(document).ajaxSuccess(function(event, xhr, settings) {
                    try {
                        // Проверяваме дали има settings и url
                        if (!settings || !settings.url) {
                            return;
                        }
                        
                        // Слушаме за успешно записване на shipping метода
                        if (settings.url.indexOf("checkout/shipping_method.save") !== -1) {
                            // Проверяваме дали заявката е успешна
                            try {
                                const response = typeof xhr.responseText === "string" ? JSON.parse(xhr.responseText) : xhr.responseText;
                                if (response && response.success) {
                                    // Ресетваме флага за payment за да можем да го запишем отново при всяка смяна на shipping
                                    paymentSaved = false;
                                    
                                    // Изчакваме завършването на зареждането на confirm секцията преди да опитаме да запишем payment метода
                                    // Това гарантира че OpenCart е завършил всичките си операции
                                    let confirmLoadAttempts = 0;
                                    const maxConfirmAttempts = 10;
                                    
                                    const checkConfirmAndSave = setInterval(function() {
                                        try {
                                            confirmLoadAttempts++;
                                            // Проверяваме дали confirm секцията е заредена (има съдържание)
                                            const confirmSection = document.querySelector("#checkout-confirm");
                                            if (confirmSection && 
                                                confirmSection.innerHTML && 
                                                confirmSection.innerHTML.trim() !== "" && 
                                                isShippingSelected()) {
                                                clearInterval(checkConfirmAndSave);
                                                // Изчакваме още малко за да се стабилизира всичко
                                                setTimeout(function() {
                                                    try {
                                                        if (!isPaymentMethodSaved()) {
                                                            autoSaveDskapiPayment();
                                                        }
                                                    } catch (e) {
                                                        console.warn("DSKAPI: Грешка при опит за запис след confirm зареждане:", e);
                                                    }
                                                }, 300);
                                            } else if (confirmLoadAttempts >= maxConfirmAttempts) {
                                                clearInterval(checkConfirmAndSave);
                                                // Ако не успеем да изчакаме confirm, опитваме се директно (fallback)
                                                setTimeout(function() {
                                                    try {
                                                        if (isShippingSelected() && !isPaymentMethodSaved()) {
                                                            autoSaveDskapiPayment();
                                                        }
                                                    } catch (e) {
                                                        console.warn("DSKAPI: Грешка при fallback опит за запис:", e);
                                                    }
                                                }, 1000);
                                            }
                                        } catch (e) {
                                            clearInterval(checkConfirmAndSave);
                                            console.warn("DSKAPI: Грешка в checkConfirmAndSave interval:", e);
                                        }
                                    }, 200);
                                }
                            } catch (e) {
                                // Игнорираме грешки при парсиране на JSON (може да не е JSON отговор)
                                console.warn("DSKAPI: Грешка при парсиране на shipping save отговор:", e);
                            }
                        }
                    } catch (e) {
                        console.warn("DSKAPI: Грешка в ajaxSuccess handler:", e);
                    }
                });
            } catch (e) {
                console.error("DSKAPI: Грешка в document.ready:", e);
            }
        });
    } catch (e) {
        console.error("DSKAPI: Грешка при инициализация:", e);
    }
})();
</script>';

        // Добавяме скрипта преди затварящия </body> таг
        $output = str_replace('</body>', $script . '</body>', $output);
    }
}
