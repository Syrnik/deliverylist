<?php

class shopDeliverylistPlugin extends shopPlugin
{

    public function hookBackendOrders()
    {
        $sidebar_bottom_li = wa()->getView()->fetch($this->path . '/templates/hooks/sidebar_bottom_li.html');

        return array('sidebar_bottom_li' => $sidebar_bottom_li);
    }
}
