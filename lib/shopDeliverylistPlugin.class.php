<?php
/**
 * List of selected orders for delivery division, couriers e t.c
 *
 * @author Serge Rodovnichenko <serge@syrnik.com>
 * @version 1.0.0
 * @copyright Serge Rodovnichenko, 2015
 * @license http://www.webasyst.com/terms/#eula Webasyst
 * @package deliverylist
 */

/**
 * Main plugin class
 */
class shopDeliverylistPlugin extends shopPlugin
{

    public function hookBackendOrders()
    {
        $sidebar_bottom_li = wa()->getView()->fetch($this->path . '/templates/hooks/sidebar_bottom_li.html');

        return array('sidebar_bottom_li' => $sidebar_bottom_li);
    }
}
