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

        // Добавяме колона "ДСК Банка Статус" след колоната "Статус" в header-а
        // Търсим последния </th> преди </tr> в header-а
        $columnHeader = '<th class="text-start">ДСК Банка Статус</th>';
        $output = preg_replace('/(<\/th>\s*<\/tr>\s*<tbody>)/', '</th>' . $columnHeader . '</tr><tbody>', $output, 1);

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

                // Намираме съответния ред и добавяме клетката преди </tr>
                // Търсим реда който съдържа order_id
                $pattern = '/(href="[^"]*route=sale\/order\.info[^"]*order_id=' . $order_id . '[^"]*"[^>]*>.*?<\/td>\s*<\/tr>)/s';
                $output = preg_replace($pattern, '$1' . $statusCell . '</tr>', $output, 1);
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
