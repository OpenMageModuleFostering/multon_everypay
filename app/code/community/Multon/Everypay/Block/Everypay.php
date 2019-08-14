<?php

class Multon_Everypay_Block_Everypay extends Mage_Payment_Block_Form
{
	protected $_code = 'multon_everypay';
	protected $_gateway = 'everypay';

	protected function getReturnUrl()
	{
		return Mage::getUrl('everypay/everypay/return');
	}

    /**
     * Returns payment gateway URL
     *
     * @return string Gateway URL
     */
    public function getGatewayUrl()
    {
        return Mage::getStoreConfig('payment/' . $this->_code . '/gateway_url');
    }

	/**
	 * Returns payment method logo URL
	 *
	 * @return string
	 */
	public function getMethodLogoUrl()
	{
		return $this->getSkinUrl('images/multon/everypay/mastercard_visa_acceptance.jpg');
	}

    /**
     * Adds payment mehtod logotypes after method name
     *
     * @return string
     */
    public function getMethodLabelAfterHtml()
    {
		if (Mage::getStoreConfig('payment/' . $this->_code . '/hide_logo'))
				return '';
		
        $blockHtml = sprintf(
            '<img src="%1$s"
                title="%2$s"
                alt="%2$s"
                class="payment-method-logo"/>',
            $this->getMethodLogoUrl(), ucfirst($this->_gateway)
        );
        return $blockHtml;
    }

    /**
     * Checks if quick redirect is enabled and
     * returns javascript block that redirects user
     * to bank without intermediate page
     *
     * @return outstr Javascript block
     */
    public function getQuickRedirectScript()
    {
        $outstr = '';
        if (Mage::getStoreConfig('payment/' . $this->_code . '/quick_redirect'))
		{
            $outstr = '<script type="text/javascript"><!--
                if($("GatewayForm")){$("GatewayForm").submit();}
                //--></script>';
        }
        return $outstr;
    }

	/**
	 * Populates and returns array of fields to be submitted
	 * to a bank for payment
	 *
	 * @return Array
	 */
	public function getFields()
	{
		$orderId = Mage::getSingleton('checkout/session')->getLastOrderId();
		$order = Mage::getModel('sales/order')->load($orderId);
		/* @var $order Mage_Sales_Model_Order */
		switch (Mage::app()->getLocale()->getLocaleCode())
		{
			case 'et_EE':
				$language = 'et';
				break;
			case 'ru_RU':
				$language = 'ru';
				break;
			default:
				$language = 'en';
				break;
		}

		$billing = $order->getBillingAddress();
		$shipping = $order->getShippingAddress();

		$fields = array(
			'account_id' => Mage::getStoreConfig('payment/' . $this->_code . '/account_id'),
			'amount' => number_format($order->getTotalDue(), 2, '.', ''),
			'api_username' => Mage::getStoreConfig('payment/' . $this->_code . '/api_username'),
			'billing_address' => $billing->getStreetFull(),
			'billing_city' => $billing->getCity(),
			'billing_country' => $billing->getCountry(),
			'billing_postcode' => $billing->getPostcode(),
			'callback_url' => $this->getReturnUrl(),
			'customer_url' => $this->getReturnUrl(),
			'delivery_address' => $shipping->getStreetFull(),
			'delivery_city' => $shipping->getCity(),
			'delivery_country' => $shipping->getCountry(),
			'delivery_postcode' => $shipping->getPostcode(),
			'email' => $billing->getEmail(),
			'nonce' => $this->getNonce(),
			'order_reference' => $order->getIncrementId(),
			'timestamp' => time(),
			'transaction_type' => Mage::getStoreConfig('payment/' . $this->_code . '/transaction_type'),
			'user_ip' => $_SERVER['REMOTE_ADDR'],
		);

		$fields['hmac'] = $this->signData($this->prepareData($fields));
		$fields['locale'] = $language;

//		Mage::log(print_r($fields, 1), null, $this->logFile);

		return $fields;
	}

	protected function getNonce()
	{
		while (1)
		{
			$nonce = uniqid(true);
			$r = Mage::getSingleton('core/resource')
					->getConnection('read_connection')
					->query('select nonce from everypay_nonce where nonce=\'' . $nonce . '\'')
					->fetchAll();
			if (!count($r))
			{
				$r = Mage::getSingleton('core/resource')
						->getConnection('write_connection')
						->insert('everypay_nonce', array('nonce' => $nonce))
				;
				break;
			}
		}
		return $nonce;
	}

	/**
	 * Prepare data package for signing
	 *
	 * @param array $fields
	 * @return string
	 */
	protected function prepareData(array $fields)
	{
		$arr = array();
		foreach ($fields as $k => $v)
		{
			$arr[] = $k . '=' . $v;
		}
		return implode('&', $arr);
	}

	protected function signData($data)
	{
		return hash_hmac('sha1', $data, Mage::getStoreConfig('payment/' . $this->_code . '/api_secret'));
	}

}
