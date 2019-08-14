<?php

class Multon_Everypay_Block_Customer_Token extends Mage_Core_Block_Template
{

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('multon/everypay/tokens.phtml');

        $tokens = Mage::getResourceModel('everypay/token_collection')
            ->addFieldToSelect('*')
            ->addFieldToFilter('customer_id', Mage::getSingleton('customer/session')->getCustomer()->getId())
        ;

        $this->setTokens($tokens);
    }

}
