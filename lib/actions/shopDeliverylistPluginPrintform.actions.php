<?php
/**
 * List of selected orders for delivery division, couriers e t.c
 *
 * @author Serge Rodovnichenko <serge@syrnik.com>
 * @version 1.0.0
 * @copyright Serge Rodovnichenko, 2015
 * @license http://www.webasyst.com/terms/#eula Webasyst
 * @package deliverylist.controllers
 */

/**
 * Printform
 */
class shopDeliverylistPluginPrintformActions extends waViewActions
{
    private $orders = array();

    protected function preExecute()
    {
        $order_ids = waRequest::request('ids', null, waRequest::TYPE_ARRAY_INT);
        if ($order_ids) {
            $collection = new shopOrdersCollection($order_ids);
            $this->orders = $collection->getOrders('*,params,items,contact');
            shopHelper::workupOrders($this->orders);
            $this->orders = $this->addWeight($this->orders);
        }
        parent::preExecute();
    }

    public function displayAction()
    {
        $this->view->assign('orders', $this->orders);
    }

    public function excelAction()
    {
        require_once(__DIR__ . '/../vendors/xlsxwriter.class.php');
        $writer = new XLSXWriter();
        $filename = 'delivery.xlsx';

        $this->getResponse()->addHeader(
            'Content-disposition',
            'attachment; filename="' . XLSXWriter::sanitize_filename($filename) . '""'
        );
        $this->getResponse()->addHeader(
            'Content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $this->getResponse()->addHeader('Content-Transfer-Encoding', 'binary');
        $this->getResponse()->addHeader('Cache-Control', 'must-revalidate');
        $this->getResponse()->addHeader('Pragma', 'public');

        $sheet_name = 'Sheet 1';

        // RUB fmt: "# ##0,00 [$руб.-419];[RED]-# ##0,00 [$руб.-419]"
        $writer->writeSheetHeader(
            $sheet_name,
            array(
                'string', // N п/п
                'string', // Номер заказа
                '# ##0,00 [$руб.-419];[RED]-# ##0,00 [$руб.-419]', // Сумма заказа
                'GENERAL', // кол-во мест
                'GENERAL', // вес заказа
                'string', // фио плкупашки
                'string', // адрес доставки
                'string', // тлф
                'string', // пусто
                'string', // примечание
                'string', // время доставки
            ),
            true
        );

        $writer->writeSheetRow($sheet_name, array('Маршрутный лист №', '','','','','','','','', date('d-m-Y')));
        $writer->writeSheetRow($sheet_name, array(
            '№ п/п',
            'Номер заказа',
            'Сумма заказа',
            'Количество мест',
            'Вес заказа',
            'ФИО покупателя',
            'Адрес доставки',
            'Телефон',
            '',
            'Примечание',
            'Время доставки',
        ));

        $idx = 1;
        foreach ($this->orders as $order) {
            $total_items = 0;
            $total_weight = 0;

            foreach ($order['items'] as $item) {
                if ($item['type'] == 'product') {
                    $total_items += $item['quantity'];
                    $total_weight += $item['quantity'] * $item['weight'];
                }
            }

            $contact = new waContact($order['contact_id']);
            $writer->writeSheetRow($sheet_name, array(
                $idx,
                $order['id_str'],
                str_replace('.', ',', $order['total']),
                str_replace('.', ',', $total_items),
                str_replace(',', '.', $total_weight),
                $order['contact']['name'],
                $order['params']['shipping_address.street'], $contact->get('phone', 'html'),
                '',
                $order['comment'],
                ''

            ));
        }
        $writer->markMergedCell($sheet_name, 0, 0, 0, 8);
        $writer->markMergedCell($sheet_name, 0, 9, 0, 10);
        $this->getResponse()->sendHeaders();
        $writer->writeToStdOut();
        die();
    }

    /**
     * Добавляет вес к товарам в заказе
     *
     * @param array $orders
     * @return array
     * @throws waException
     */
    private function addWeight($orders)
    {
        $weightDimension = shopDimension::getInstance()->getDimension('weight');
        $multiplier = 1;
        if ($weightDimension['base_unit'] != 'kg') {
            $multiplier = $weightDimension['units']['kg']['multiplier'];
        }

        // Добавим вес в базовых единицах ко всем items заказов
        $Feature = new shopFeatureModel();
        $weightFeature = $Feature->getByCode('weight');
        $WeightValues = $Feature->getValuesModel($weightFeature['type']);

        // array_column for PHP less than 5.5
        require_once(__DIR__ . '/../vendors/array_column.php');

        foreach ($orders as $key => $val) {

            if ($weightFeature) {
                $product_ids = array_column($val['items'], 'product_id');
                $sku_ids = array_column($val['items'], 'sku_id');
                $weights = $WeightValues->getProductValues($product_ids, $weightFeature['id']);

                foreach ($val['items'] as &$item) {
                    if (isset($weights['skus'][$item['sku_id']])) {
                        $item['weight'] = $weights['skus'][$item['sku_id']] * $multiplier;
                    } else {
                        $item['weight'] = isset($weights[$item['product_id']]) ?
                            $weights[$item['product_id']] * $multiplier : 0;
                    }
                }
            } else {
                foreach ($val['items'] as &$item) {
                    $item['weight'] = 0;
                }
            }
            unset($item);
            $orders[$key] = $val;
        }

        return $orders;
    }
}
