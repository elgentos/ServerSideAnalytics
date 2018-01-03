<?php

use TheIconic\Tracking\GoogleAnalytics\Analytics;

class Elgentos_ServerSideAnalytics_Model_GAClient {

    /* Analytics object which holds transaction data */
    protected $analytics;

    /* Google Analytics Measurement Protocol API version */
    protected $version = '1';

    /* Count how many products are added to the Analytics object */
    protected $productCounter = 0;

    /**
     * Elgentos_ServerSideAnalytics_Model_GAClient constructor.
     */
    public function __construct()
    {
        /** @var Analytics analytics */
        $this->analytics = new Analytics(true);

        if (Mage::getIsDeveloperMode()) {
            // $this->analytics = new Analytics(true, true); // for dev/staging envs where dev mode is off but we don't want to send events
            $this->analytics->setDebug(true);
        }

        $this->helper = Mage::helper('elgentos_serversideanalytics/translate');
    }

    /**
     * @param Varien_Object $data
     * @throws Exception
     */
    public function setTrackingData(Varien_Object $data)
    {
        if (!$data->getTrackingId()) {
            throw new Exception ('No tracking ID set for GA client.');
        }

        if (!$data->getClientId() && !$data->getUserId()) {
            throw new Exception ('No client ID or user ID is set for GA client; at least one is necessary.');
        }

        $this->analytics->setProtocolVersion($this->version)
            ->setTrackingId($data->getTrackingId()) // 'UA-26293624-12'
            ->setIpOverride($data->getIpOverride()); // '123'

        if ($data->getClientId()) {
            $this->analytics->setClientId($data->getClientId()); // '2133506694.1448249699'
        }

        if ($data->getUserId()) {
            $this->analytics->setUserId($data->getUserId());
        }

        if ($data->getUserAgentOverride()) {
            $this->analytics->setUserAgentOverride($data->getUserAgentOverride());
        }

        if ($data->getDocumentPath()) {
            $this->analytics->setDocumentPath($data->getDocumentPath());
        }
    }

    /**
     * @param $data
     */
    public function setTransactionData($data)
    {
        $this->analytics
            ->setTransactionId($data->getTransactionId())
            ->setAffiliation($data->getAffiliation())
            ->setRevenue($data->getRevenue())
            ->setTax($data->getTax())
            ->setShipping($data->getShipping());

        if ($data->getCouponCode()) {
            $this->analytics->setCouponCode($data->getCouponCode());
        }
    }

    /**
     * @param $products
     */
    public function addProducts($products)
    {
        foreach ($products as $product) {
            $this->addProduct($product);
        }
    }

    /**
     * @param $data
     */
    public function addProduct($data)
    {
        $this->productCounter++;
        $this->analytics->addProduct($data->getData());
    }

    /**
     * @throws Exception
     */
    public function firePurchaseEvent()
    {
        if (!$this->analytics->getTransactionId()) {
            throw new Exception($this->helper->__('No tracking ID set for transaction'));
        }

        if (!$this->analytics->getClientId() && !$this->analytics->getUserId()) {
            throw new Exception($this->helper->__('No client ID or user ID set for transaction %s', $this->analytics->getTransactionId()));
        }

        if (!$this->analytics->getTrackingId()) {
            throw new Exception($this->helper->__('No tracking ID set for transaction %s', $this->analytics->getTransactionId()));
        }

        if (!$this->productCounter) {
            throw new Exception($this->helper->__('No products have been added to transaction %s', $this->analytics->getTransactionId()));
        }

        $this->analytics->setProductActionToPurchase();
        $response = $this->analytics->setEventCategory('Checkout')
            ->setEventAction('Purchase')
            ->sendEvent();

        // @codingStandardsIgnoreStart
        if (Mage::getIsDeveloperMode()) {
            Mage::log(print_r($response->getDebugResponse(), true), null, 'elgentos_serversideanalytics_debug_response.log');
        }
        // @codingStandardsIgnoreEnd
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

}