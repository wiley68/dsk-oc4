<?php

namespace Opencart\Admin\Controller\Extension\MtDskapiCredit\Event;

/**
 * Class MtDskapiCreditOrder
 *
 * @package Opencart\Admin\Controller\Extension\MtDskapiCredit\Event
 */
class MtDskapiCreditOrder extends \Opencart\System\Engine\Controller
{
    /**
     * Добавя банков статус в данните за списъка с ордери
     *
     * @param string &$route
     * @param array &$data
     * @return void
     */
    public function addBankStatusToList(&$route, &$data): void
    {
        if ($route !== 'sale/order') {
            return;
        }

        // Зареждаме модела
        $this->load->model('extension/mt_dskapi_credit/module/mt_dskapi_credit');

        // Ако имаме ордери в данните, добавяме банков статус за всеки
        if (isset($data['orders']) && is_array($data['orders'])) {
            $statuses = $this->model_extension_mt_dskapi_credit_module_mt_dskapi_credit->getBankStatuses();

            foreach ($data['orders'] as &$order) {
                $bankStatus = $this->model_extension_mt_dskapi_credit_module_mt_dskapi_credit->getBankStatus((int)$order['order_id']);

                if ($bankStatus) {
                    $order['dskapi_bank_status'] = [
                        'status_id' => (int)$bankStatus['order_status'],
                        'status_text' => $statuses[(int)$bankStatus['order_status']] ?? 'Неизвестен статус',
                        'updated_at' => $bankStatus['updated_at']
                    ];
                } else {
                    $order['dskapi_bank_status'] = null;
                }
            }
            unset($order); // Премахваме референцията
        }
    }

    /**
     * Добавя банков статус в данните за детайлния преглед на ордер
     *
     * @param string &$route
     * @param array &$data
     * @return void
     */
    public function addBankStatusToInfo(&$route, &$data): void
    {
        if ($route !== 'sale/order.info') {
            return;
        }

        // Зареждаме модела
        $this->load->model('extension/mt_dskapi_credit/module/mt_dskapi_credit');

        // Проверяваме дали има order_id
        if (isset($this->request->get['order_id'])) {
            $order_id = (int)$this->request->get['order_id'];
            $bankStatus = $this->model_extension_mt_dskapi_credit_module_mt_dskapi_credit->getBankStatus($order_id);
            $statuses = $this->model_extension_mt_dskapi_credit_module_mt_dskapi_credit->getBankStatuses();

            if ($bankStatus) {
                $data['dskapi_bank_status'] = [
                    'status_id' => (int)$bankStatus['order_status'],
                    'status_text' => $statuses[(int)$bankStatus['order_status']] ?? 'Неизвестен статус',
                    'created_at' => $bankStatus['created_at'],
                    'updated_at' => $bankStatus['updated_at']
                ];
            } else {
                $data['dskapi_bank_status'] = null;
            }
        }
    }

    /**
     * Добавя колона в таблицата със списъка на ордерите
     *
     * @param string &$route
     * @param array &$data
     * @param string &$output
     * @return void
     */
    public function addColumnToList(&$route, &$data, &$output): void
    {
        if ($route !== 'sale/order_list') {
            return;
        }

        if (!is_string($output)) {
            return;
        }

        // Зареждаме данните за банковите статуси
        $this->load->model('extension/mt_dskapi_credit/module/mt_dskapi_credit');
        $statuses = $this->model_extension_mt_dskapi_credit_module_mt_dskapi_credit->getBankStatuses();

        // Добавяме колона "ДСК Банка Статус" в header-а между "Status" и "Total" колоните
        $columnHeader = '<th class="text-start">ДСК Банка Статус</th>';
        // Търсим колоната "Status" и добавяме новата колона след нея преди "Total"
        // От HTML структурата: <th><a href="...">Status</a></th> ... <th class="text-end d-none d-lg-table-cell"><a href="...">Total</a></th>
        // Pattern търси Status колоната и Total колоната след нея (игнорирайки всичко между тях)
        // Използваме non-greedy match за да намерим първата Total колона след Status
        $pattern = '/(<th[^>]*><a[^>]*>Status<\/a><\/th>)(.*?)(<th[^>]*><a[^>]*>Total<\/a><\/th>)/is';
        $output = preg_replace($pattern, '$1' . $columnHeader . '$3', $output, 1);

        // Ако първият pattern не работи, опитваме се с по-общ pattern който търси Status и Total колоните
        if (strpos($output, 'ДСК Банка Статус') === false) {
            // Търсим Status колоната (може да има различни структури) и Total колоната след нея
            $pattern = '/(<th[^>]*>.*?Status.*?<\/th>)(.*?)(<th[^>]*>.*?Total.*?<\/th>)/is';
            $output = preg_replace($pattern, '$1' . $columnHeader . '$3', $output, 1);
        }

        // Добавяме данните за всеки ред - търсим редовете в tbody
        // Pattern за намиране на order_id в href
        preg_match_all('/href="[^"]*route=sale\/order\.info[^"]*order_id=(\d+)[^"]*"/', $output, $orderMatches);

        if (!empty($orderMatches[1])) {
            foreach ($orderMatches[1] as $index => $order_id) {
                $order_id = (int)$order_id;
                $bankStatus = $this->model_extension_mt_dskapi_credit_module_mt_dskapi_credit->getBankStatus($order_id);

                $statusText = '—';
                $statusClass = 'text-muted';

                if ($bankStatus) {
                    $statusId = (int)$bankStatus['order_status'];
                    $statusText = htmlspecialchars($statuses[$statusId] ?? 'Неизвестен статус', ENT_QUOTES, 'UTF-8');

                    // Добавяме цветово кодиране според статуса
                    if ($statusId >= 7) {
                        $statusClass = 'text-success'; // Успешни статуси
                    } elseif ($statusId >= 4) {
                        $statusClass = 'text-danger'; // Неуспешни статуси
                    } else {
                        $statusClass = 'text-info'; // В процес
                    }
                }

                $statusCell = '<td class="text-start"><span class="' . $statusClass . '">' . $statusText . '</span></td>';

                // Намираме реда който съдържа order_id и добавяме клетката след "Status" клетката преди "Total" клетката
                // Escape-ваме специалните символи в order_id
                $escapedOrderId = preg_quote((string)$order_id, '/');
                // Търсим реда с order_id в Action клетката, намираме Status клетката и Total клетката след нея
                // Структурата: Order ID -> Store -> Customer -> Status -> Total -> Date Added -> Date Modified -> Action
                // Status клетката е обикновено <td>Status</td> без класове, след Customer клетката
                // Търсим целия ред от <tr> до </tr> който завършва с order_id в Action клетката
                // Намираме Status клетката (която е преди Total клетката) и добавяме новата клетка след нея
                // Status клетката може да бъде Processing, Pending, Voided, Complete или друг статус
                $pattern = '/(<tr[^>]*>.*?)(<td>Processing<\/td>|<td>Pending<\/td>|<td>Voided<\/td>|<td>Complete<\/td>|<td>[^<]*<\/td>)(\s*<td class="text-end d-none d-lg-table-cell">.*?order_id=' . $escapedOrderId . '[^>]*>.*?<\/tr>)/s';
                $output = preg_replace($pattern, '$1$2' . $statusCell . '$3', $output, 1);
            }
        }
    }

    /**
     * Добавя поле в детайлния преглед на ордер
     *
     * @param string &$route
     * @param array &$data
     * @param string &$output
     * @return void
     */
    public function addFieldToInfo(&$route, &$data, &$output): void
    {
        if ($route !== 'sale/order_info') {
            return;
        }

        if (!is_string($output)) {
            return;
        }

        // Зареждаме данните за банковия статус
        $this->load->model('extension/mt_dskapi_credit/module/mt_dskapi_credit');

        if (isset($this->request->get['order_id'])) {
            $order_id = (int)$this->request->get['order_id'];
            $bankStatus = $this->model_extension_mt_dskapi_credit_module_mt_dskapi_credit->getBankStatus($order_id);
            $statuses = $this->model_extension_mt_dskapi_credit_module_mt_dskapi_credit->getBankStatuses();

            $statusHtml = '';

            if ($bankStatus) {
                $statusId = (int)$bankStatus['order_status'];
                $statusText = htmlspecialchars($statuses[$statusId] ?? 'Неизвестен статус', ENT_QUOTES, 'UTF-8');

                // Добавяме цветово кодиране
                $statusClass = 'text-info';
                if ($statusId >= 7) {
                    $statusClass = 'text-success';
                } elseif ($statusId >= 4) {
                    $statusClass = 'text-danger';
                }

                $statusHtml = '<div class="row mb-3">
                    <label class="col-sm-2 col-form-label">ДСК Банка Статус</label>
                    <div class="col-sm-10">
                        <div class="form-control-plaintext">
                            <span class="' . $statusClass . ' fw-bold">' . $statusText . '</span>';

                if ($bankStatus['updated_at']) {
                    $statusHtml .= '<br><small class="text-muted">Последна актуализация: ' . htmlspecialchars($bankStatus['updated_at'], ENT_QUOTES, 'UTF-8') . '</small>';
                }

                $statusHtml .= '</div>
                    </div>
                </div>';
            } else {
                $statusHtml = '<div class="row mb-3">
                    <label class="col-sm-2 col-form-label">ДСК Банка Статус</label>
                    <div class="col-sm-10">
                        <div class="form-control-plaintext text-muted">—</div>
                    </div>
                </div>';
            }

            // Добавяме полето след секцията с информация за плащането
            // Търсим секцията с payment метода - обикновено има label с "Payment Method" или подобно
            // Добавяме след секцията с payment метода или order status
            if (preg_match('/(<div class="row mb-3">.*?<label[^>]*>.*?(?:Payment|Плащане|Статус)[^<]*<\/label>.*?<\/div>\s*<\/div>\s*<\/div>)/s', $output, $matches)) {
                // Добавяме след намерената секция
                $matchText = $matches[0];
                $output = str_replace($matchText, $matchText . $statusHtml, $output);
            } elseif (preg_match('/(<div class="row mb-3">.*?<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>)/s', $output, $matches)) {
                // Fallback - добавяме след първата секция с информация
                $matchText = $matches[0];
                $output = str_replace($matchText, $matchText . $statusHtml, $output);
            } else {
                // Fallback - добавяме преди затварящия </form> или </div>
                $output = preg_replace('/(<\/form>|<\/div>\s*<\/div>\s*<\/div>\s*<\/div>)/', $statusHtml . '$1', $output, 1);
            }
        }
    }
}
