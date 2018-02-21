<?php

class Elgentos_ServerSideAnalytics_Model_Observer
{
    /**
     * @param $observer
     */
    public function sendPurchaseEvent($observer)
    {
        if (!Mage::getStoreConfig(Mage_GoogleAnalytics_Helper_Data::XML_PATH_ACTIVE)) {
            return;
        }

        if (!Mage::getStoreConfig(Mage_GoogleAnalytics_Helper_Data::XML_PATH_ACCOUNT)) {
            Mage::log('Google Analytics extension and ServerSideAnalytics extension are activated but no Google Analytics account number has been found.');
            return;
        }

        /** @var Mage_Sales_Model_Order_Payment $payment */
        $payment = $observer->getPayment();
        /** @var Mage_Sales_Model_Order_Invoice $invoice */
        $invoice = $observer->getInvoice();
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();

        if (!$order->getGaUserId()) {
            return;
        }

        /** @var Elgentos_ServerSideAnalytics_Model_GAClient $client */
        $client = Mage::getModel('elgentos_serversideanalytics/gAClient');

        try {
            $trackingDataObject = new Varien_Object([
                'tracking_id'   => Mage::getStoreConfig(Mage_GoogleAnalytics_Helper_Data::XML_PATH_ACCOUNT),
                'client_id'     => $order->getGaUserId(),
                'ip_override'   => $order->getRemoteIp(),
                'document_path' => '/checkout/onepage/success/'
            ]);

            Mage::dispatchEvent('elgentos_serversideanalytics_tracking_data_transport_object', ['tracking_data_object' => $trackingDataObject]);
            $client->setTrackingData($trackingDataObject);

            $client->setTransactionData(
                new Varien_Object(
                    [
                        'transaction_id' => $order->getIncrementId(),
                        'affiliation' => $order->getStoreName(),
                        'revenue' => $invoice->getBaseGrandTotal(),
                        'tax' => $invoice->getTaxAmount(),
                        'shipping' => $this->getPaidShippingCosts($invoice),
                        'coupon_code' => $order->getCouponCode()
                    ]
                )
            );

            $products = [];
            /** @var Mage_Sales_Model_Order_Invoice_Item $item */
            foreach ($invoice->getAllItems() as $item) {
                if (!$item->isDeleted() && !$item->getOrderItem()->getParentItemId()) {
                    $product = new Varien_Object([
                        'sku' => $item->getSku(),
                        'name' => $item->getName(),
                        'price' => $this->getPaidProductPrice($item->getOrderItem()),
                        'quantity' => $item->getOrderItem()->getQtyOrdered(),
                        'position' => $item->getId()
                    ]);
                    Mage::dispatchEvent('elgentos_serversideanalytics_product_item_transport_object', ['product' => $product, 'item' => $item]);
                    $products[] = $product;
                }
            }

            $client->addProducts($products);

            $client->firePurchaseEvent();
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * When Order object is saved add the GA User Id if available in the cookies.
     *
     * @param Varien_Event_Observer $observer
     */
    public function saveGaUserId($observer)
    {
        if (!Mage::getStoreConfig(Mage_GoogleAnalytics_Helper_Data::XML_PATH_ACTIVE)) {
            return;
        }

        if (!Mage::getStoreConfig(Mage_GoogleAnalytics_Helper_Data::XML_PATH_ACCOUNT)) {
            Mage::log('Google Analytics extension and ServerSideAnalytics extension are activated but no Google Analytics account number has been found.');
            return;
        }

        $order = $observer->getEvent()->getOrder();

        $gaCookie = explode('.', Mage::getModel('core/cookie')
                ->get('_ga'));

        if (empty($gaCookie) || count($gaCookie) < 4) {
            return;
        }

        list(
            $gaCookieVersion,
            $gaCookieDomainComponents,
            $gaCookieUserId,
            $gaCookieTimestamp
            ) = $gaCookie;

        if (!$gaCookieUserId || !$gaCookieTimestamp) {
            return;
        }

        $client = Mage::getModel('elgentos_serversideanalytics/gAClient');

        if ($gaCookieVersion != 'GA' . $client->getVersion()) {
            Mage::log('Google Analytics cookie version differs from Measurement Protocol API version; please upgrade.');
            return;
        }

        $gaUserId = implode('.', [$gaCookieUserId, $gaCookieTimestamp]);

        $order->setGaUserId($gaUserId);
    }

    /**
     * Get the actual price the customer also saw in it's cart.
     *
     * @param  Mage_Sales_Model_Order_Item $orderItem
     *
     * @return float
     */
    private function getPaidProductPrice(Mage_Sales_Model_Order_Item $orderItem)
    {
        return Mage::getStoreConfig('tax/display/type	') == Mage_Tax_Model_Config::DISPLAY_TYPE_EXCLUDING_TAX
            ? $orderItem->getPrice()
            : $orderItem->getPriceInclTax();
    }

    /**
     * @param  Mage_Sales_Model_Order_Invoice $invoice
     *
     * @return float
     */
    private function getPaidShippingCosts(Mage_Sales_Model_Order_Invoice $invoice)
    {
        return Mage::getStoreConfig('tax/display/type	') == Mage_Tax_Model_Config::DISPLAY_TYPE_EXCLUDING_TAX
            ? $invoice->getShippingAmount()
            : $invoice->getShippingInclTax();
    }
}
