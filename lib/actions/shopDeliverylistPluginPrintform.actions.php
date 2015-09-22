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
        $order_ids = waRequest::post('ids', null, waRequest::TYPE_ARRAY_INT);
        $collection = new shopOrdersCollection($order_ids);
        $this->orders = $collection->getOrders('*,params,items,contact');
        shopHelper::workupOrders($this->orders);
        $this->orders = $this->addWeight($this->orders);
        parent::preExecute();
    }

    public function displayAction()
    {
        $this->view->assign('orders', $this->orders);
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
