<?php

class Multon_Everypay_Block_Everypay extends Mage_Payment_Block_Form
{
	protected $_code = 'multon_everypay';
	protected $_gateway = 'everypay';
	protected $logFile = 'everypay.log';

	protected function getReturnUrl()
	{
		return Mage::getUrl('everypay/everypay/return', array('_nosid' => true));
	}

	protected function getCallbackUrl()
	{
		return Mage::getUrl('everypay/everypay/callback', array('_nosid' => true));
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
		return $this->getSkinUrl('images/multon/everypay/mastercard_visa_acceptance.png');
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

	public function getOrder()
	{
		$orderId = Mage::getSingleton('checkout/session')->getLastOrderId();
		return Mage::getModel('sales/order')->load($orderId);
	}

	/**
	 * Populates and returns array of fields to be submitted
	 * to a bank for payment
	 *
	 * @return Array
	 */
	public function getFields()
	{
		$order = $this->getOrder();
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
		// downloadable products only orders don't have shipping address
		if (!$shipping)
			$shipping = $billing;

		if ($this->getGatewayUrl() == Multon_Everypay_Model_Source_ApiUrl::$urlList['LIVE'])
			$username = Mage::getStoreConfig('payment/' . $this->_code . '/api_username');
		else
			$username = Mage::getStoreConfig('payment/' . $this->_code . '/api_username_test');

		$use_token = $order->getPayment()->getMethodInstance()->getInfoInstance()->getAdditionalInformation('everypay_use_token');
		$save_token = !empty($use_token) ? 0 : (int)$order->getPayment()->getMethodInstance()->getInfoInstance()->getAdditionalInformation('everypay_save_token');

		$fields = array(
			'account_id' => Mage::getStoreConfig('payment/' . $this->_code . '/account_id'),
			'amount' => number_format($order->getTotalDue(), 2, '.', ''),
			'api_username' => $username,
			'billing_address' => str_replace("\n", ' ', $billing->getStreetFull()),
			'billing_city' => $billing->getCity(),
			'billing_country' => $billing->getCountry(),
			'billing_postcode' => $billing->getPostcode(),
			'callback_url' => $this->getCallbackUrl(),
			'customer_url' => $this->getReturnUrl(),
			'delivery_address' => str_replace("\n", ' ', $shipping->getStreetFull()),
			'delivery_city' => $shipping->getCity(),
			'delivery_country' => $shipping->getCountry(),
			'delivery_postcode' => $shipping->getPostcode(),
			'email' => $billing->getEmail(),
			'hmac_fields' => '',
			'nonce' => $this->getNonce(),
			'order_reference' => $order->getIncrementId(),
			'timestamp' => time(),
			'transaction_type' => 'charge',
			'user_ip' => $_SERVER['REMOTE_ADDR'],
		);

		if (!empty($use_token))
			$fields['cc_token'] = $use_token;
		else if ($save_token)
			$fields['request_cc_token'] = $save_token;

		ksort($fields);

		$fields['hmac_fields'] = implode(',',  array_keys($fields));

		$fields['hmac'] = $this->signData($this->prepareData($fields));
		$fields['locale'] = $language;
		if (Mage::getStoreConfig('payment/multon_everypay/connection_type') || isset($fields['cc_token']))
			$fields['skin_name'] = Mage::getStoreConfig('payment/multon_everypay/skin_name');

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
		if ($this->getGatewayUrl() == Multon_Everypay_Model_Source_ApiUrl::$urlList['LIVE'])
			$secret = Mage::getStoreConfig('payment/' . $this->_code . '/api_secret');
		else
			$secret = Mage::getStoreConfig('payment/' . $this->_code . '/api_secret_test');
		return hash_hmac('sha1', $data, $secret);
	}

}
