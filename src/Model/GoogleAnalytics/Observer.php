<?php

class Elgentos_ServerSideAnalytics_Model_GoogleAnalytics_Observer extends Mage_GoogleAnalytics_Model_Observer
{
    /**
     * Disable adding order information into GA block to render on checkout success pages
     *
     * @param Varien_Event_Observer $observer
     */
    public function setGoogleAnalyticsOnOrderSuccessPageView(Varien_Event_Observer $observer)
    {
        return;
    }
}