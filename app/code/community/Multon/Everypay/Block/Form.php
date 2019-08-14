<?php

class Multon_Everypay_Block_Form extends Multon_Everypay_Block_Everypay
{
	protected function _construct()
	{
		parent::_construct();
		if (Mage::getStoreConfig('payment/' . $this->_code . '/enable_token'))
			$this->setTemplate('multon/everypay/form.phtml');
	}

	/**
	 * Adds payment mehtod logotypes after method name
	 *
	 * @return string
	 */
	public function getMethodLabelAfterHtml()
	{
		if (!Mage::getStoreConfig('payment/' . $this->_code . '/show_logo'))
			return '';

		$blockHtml = sprintf(
				'<img src="%1$s"
                title="%2$s"
                alt="%2$s"
                class="payment-method-logo"/>', $this->getMethodLogoUrl(), ucfirst($this->_gateway)
		);
		return $blockHtml;
	}

	public function getTokens()
	{
        return Mage::getResourceModel('everypay/token_collection')
            ->addFieldToSelect('*')
            ->addFieldToFilter('customer_id', Mage::getSingleton('customer/session')->getCustomer()->getId())
            ->addFieldToFilter('cc_year', array('gteq' => date('Y')))
		;
	}
}
