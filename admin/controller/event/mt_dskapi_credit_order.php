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
     * Adds bank status to the order list data
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

        // Load the model
        $this->load->model('extension/mt_dskapi_credit/module/mt_dskapi_credit');

        // If we have orders in the data, add bank status for each
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
            unset($order); // Remove the reference
        }
    }

    /**
     * Adds bank status to the order detail view data
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

        // Load the model
        $this->load->model('extension/mt_dskapi_credit/module/mt_dskapi_credit');

        // Check if order_id exists
        if (isset($this->request->get['order_id'])) {
            $order_id = (int)$this->request->get['order_id'];
            $bankStatus = $this->model_extension_mt_dskapi_credit_module_mt_dskapi_credit->getBankStatus($order_id);
            $statuses = $this->model_extension_mt_dskapi_credit_module_mt_dskapi_credit->getBankStatuses();

            // Запазваме информацията в регистъра само ако има статус (Registry приема само обекти)
            if ($bankStatus) {
                $this->registry->set('dskapi_bank_status_info', (object) [
                    'status_id' => (int)$bankStatus['order_status'],
                    'status_text' => $statuses[(int)$bankStatus['order_status']] ?? 'Неизвестен статус',
                    'created_at' => $bankStatus['created_at'],
                    'updated_at' => $bankStatus['updated_at']
                ]);
            }
        }
    }

    /**
     * Adds a column to the order list table
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

        // Load bank status data
        $this->load->model('extension/mt_dskapi_credit/module/mt_dskapi_credit');
        $statuses = $this->model_extension_mt_dskapi_credit_module_mt_dskapi_credit->getBankStatuses();

        // Add "Банка ДСК Статус" column in the header between "Status" and "Total" columns
        $columnHeader = '<th class="text-start">Банка ДСК Статус</th>';
        // Find the "Status" column and add the new column after it before "Total"
        // From HTML structure: <th><a href="...">Status</a></th> ... <th class="text-end d-none d-lg-table-cell"><a href="...">Total</a></th>
        // Pattern searches for Status column and Total column after it (ignoring everything between them)
        // Use non-greedy match to find the first Total column after Status
        $pattern = '/(<th[^>]*><a[^>]*>Status<\/a><\/th>)(.*?)(<th[^>]*><a[^>]*>Total<\/a><\/th>)/is';
        $output = preg_replace($pattern, '$1' . $columnHeader . '$3', $output, 1);

        // If the first pattern doesn't work, try a more general pattern that searches for Status and Total columns
        if (strpos($output, 'Банка ДСК Статус') === false) {
            // Search for Status column (may have different structures) and Total column after it
            $pattern = '/(<th[^>]*>.*?Status.*?<\/th>)(.*?)(<th[^>]*>.*?Total.*?<\/th>)/is';
            $output = preg_replace($pattern, '$1' . $columnHeader . '$3', $output, 1);
        }

        // Add data for each row - search for rows in tbody
        // Pattern to find order_id in href
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

                    // Add color coding based on status
                    if ($statusId >= 7) {
                        $statusClass = 'text-success'; // Successful statuses
                    } elseif ($statusId >= 4) {
                        $statusClass = 'text-danger'; // Failed statuses
                    } else {
                        $statusClass = 'text-info'; // In progress
                    }
                }

                $statusCell = '<td class="text-start"><span class="' . $statusClass . '">' . $statusText . '</span></td>';

                // Find the row that contains order_id and add the cell after "Status" cell before "Total" cell
                // Escape special characters in order_id
                $escapedOrderId = preg_quote((string)$order_id, '/');
                // Search for the row with order_id in Action cell, find Status cell and Total cell after it
                // Structure: Order ID -> Store -> Customer -> Status -> Total -> Date Added -> Date Modified -> Action
                // Status cell is usually <td>Status</td> without classes, after Customer cell
                // Search for the entire row from <tr> to </tr> that ends with order_id in Action cell
                // Find Status cell (which is before Total cell) and add the new cell after it
                // Status cell can be Processing, Pending, Voided, Complete or other status
                $pattern = '/(<tr[^>]*>.*?)(<td>Processing<\/td>|<td>Pending<\/td>|<td>Voided<\/td>|<td>Complete<\/td>|<td>[^<]*<\/td>)(\s*<td class="text-end d-none d-lg-table-cell">.*?order_id=' . $escapedOrderId . '[^>]*>.*?<\/tr>)/s';
                $output = preg_replace($pattern, '$1$2' . $statusCell . '$3', $output, 1);
            }
        }
    }

    /**
     * Adds a field to the order detail view
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

        // Load bank status data
        $this->load->model('extension/mt_dskapi_credit/module/mt_dskapi_credit');

        if (isset($this->request->get['order_id'])) {
            $order_id = (int)$this->request->get['order_id'];
            $bankStatus = $this->model_extension_mt_dskapi_credit_module_mt_dskapi_credit->getBankStatus($order_id);
            $statuses = $this->model_extension_mt_dskapi_credit_module_mt_dskapi_credit->getBankStatuses();

            $statusHtml = '';

            if ($bankStatus) {
                $statusId = (int)$bankStatus['order_status'];
                $statusText = htmlspecialchars($statuses[$statusId] ?? 'Неизвестен статус', ENT_QUOTES, 'UTF-8');

                // Add color coding
                $statusClass = 'text-info';
                if ($statusId >= 7) {
                    $statusClass = 'text-success';
                } elseif ($statusId >= 4) {
                    $statusClass = 'text-danger';
                }

                $statusHtml = '<div class="row mb-3">
                    <label class="col-sm-2 col-form-label">Банка ДСК Статус</label>
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
                    <label class="col-sm-2 col-form-label">Банка ДСК Статус</label>
                    <div class="col-sm-10">
                        <div class="form-control-plaintext text-muted">—</div>
                    </div>
                </div>';
            }

            // Add the field after the payment information section
            // Search for the payment method section - usually has a label with "Payment Method" or similar
            // Add after the payment method section or order status
            if (preg_match('/(<div class="row mb-3">.*?<label[^>]*>.*?(?:Payment|Плащане|Статус)[^<]*<\/label>.*?<\/div>\s*<\/div>\s*<\/div>)/s', $output, $matches)) {
                // Add after the found section
                $matchText = $matches[0];
                $output = str_replace($matchText, $matchText . $statusHtml, $output);
            } elseif (preg_match('/(<div class="row mb-3">.*?<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>)/s', $output, $matches)) {
                // Fallback - add after the first information section
                $matchText = $matches[0];
                $output = str_replace($matchText, $matchText . $statusHtml, $output);
            } else {
                // Fallback - add before closing </form> or </div>
                $output = preg_replace('/(<\/form>|<\/div>\s*<\/div>\s*<\/div>\s*<\/div>)/', $statusHtml . '$1', $output, 1);
            }
        }
    }
}
