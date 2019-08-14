<?php

class Multon_Everypay_Model_Everypay extends Mage_Payment_Model_Method_Abstract
{
	const _VERIFY_SUCCESS = 1; // payment successful
	const _VERIFY_CANCEL = 2; // payment unsuccessful
	const _VERIFY_CORRUPT = 3; // wrong or corrupt response

	protected $_canAuthorize = true;
	protected $_isGateway = true;
	protected $_canUseCheckout = true;
	protected $logFile = 'everypay.log';
	protected $_code = 'multon_everypay';
	protected $_formBlockType = 'everypay/form';
	private $statuses = array(
		'completed' => self::_VERIFY_SUCCESS,
		'failed' => self::_VERIFY_CANCEL,
		'cancelled' => self::_VERIFY_CANCEL,
	);

	public function getOrderPlaceRedirectUrl()
	{
		return Mage::getUrl('everypay/everypay/redirect');
	}

	/**
	 * Assign data to info model instance
	 *
	 * @param   mixed $data
	 * @return  Mage_Payment_Model_Method_Abstract
	 */
	public function assignData($data)
	{
		$this->getInfoInstance()
				->setAdditionalInformation('everypay_use_token', $data->getData('everypay_use_token'))
				->setAdditionalInformation('everypay_save_token', $data->getData('everypay_save_token'));
	}

	/**
	 * Verifies response from Everypay
	 *
	 * @param array $params Response sent by bank and to be verified
	 *
	 * @return int
	 */
	public function verify(array $params = array())
	{
		// check for integrity first
		$fields = explode(',', $params['hmac_fields']);
		sort($fields);

		$data = '';
		foreach ($fields as $field)
			$data .= $field . '=' . $params[$field] . '&';

//		$this->log($data, __METHOD__, __LINE__);

		if ($this->getGatewayUrl() == Multon_Everypay_Model_Source_ApiUrl::$urlList['LIVE'])
		{
			$username = Mage::getStoreConfig('payment/' . $this->_code . '/api_username');
			$secret = Mage::getStoreConfig('payment/' . $this->_code . '/api_secret');
		} else
		{
			$username = Mage::getStoreConfig('payment/' . $this->_code . '/api_username_test');
			$secret = Mage::getStoreConfig('payment/' . $this->_code . '/api_secret_test');
		}

		$hmac = hash_hmac('sha1', substr($data, 0, -1), $secret);
		if ($params['hmac'] != $hmac)
		{
			$this->log('(Everypay) Invalid HMAC (Expected: ' . $hmac . ')', __METHOD__, __LINE__);
			Mage::getSingleton('checkout/session')->addError('Invalid HMAC.');
			return self::_VERIFY_CORRUPT;
		}

		// only in automatic callback message
		if (isset($params['processing_errors']))
		{
			$errors = json_decode($params['processing_errors']);
			if (!empty($errors))
				$this->log('(Everypay) Errors: ' . print_r($errors, 1), __METHOD__, __LINE__);
		}
//		if (isset($params['processing_warnings']))
//		{
//			$warnings = json_decode($params['processing_warnings']);
//			if (!empty($warnings))
//				$this->log('(Everypay) Warnings: ' . print_r($warnings, 1), __METHOD__, __LINE__);
//		}

		if (!isset($params['api_username']) || ($params['api_username'] !== $username))
		{
			$this->log('(Everypay) Invalid username', __METHOD__, __LINE__);
			Mage::getSingleton('checkout/session')->addError('Invalid username.');
			return self::_VERIFY_CORRUPT;
		}

		$now = time();
		if (($params['timestamp'] > $now) || ($params['timestamp'] < ($now - 300)))
		{
			$this->log('(Everypay) Response outdated (now: ' . $now . ', age: ' . ($now - $params['timestamp']) . ')', __METHOD__, __LINE__);
			Mage::getSingleton('checkout/session')->addError('Response outdated.');
			return self::_VERIFY_CORRUPT;
		}

		// Reference number doesn't match.
		if ($this->getOrderNumber() != $params['order_reference'])
		{
			$this->log('(Everypay): Order number doesn\'t match (potential tampering attempt). Expecting: ' . $this->getOrderNumber(), __METHOD__, __LINE__);
			Mage::getSingleton('checkout/session')->addError('Order number error.');
			return self::_VERIFY_CORRUPT;
		}

		if (!$this->verifyNonce($params['nonce']))
		{
			$this->log('(Everypay): Nonce already used.', __METHOD__, __LINE__);
			Mage::getSingleton('checkout/session')->addError('Nonce already used.');
			return self::_VERIFY_CORRUPT;
		}

		if (isset($params['account_id']) && ($params['account_id'] !== Mage::getStoreConfig('payment/' . $this->_code . '/account_id')))
		{
			$this->log('(Everypay) Invalid account ID', __METHOD__, __LINE__);
			Mage::getSingleton('checkout/session')->addError('Invalid account.');
			return self::_VERIFY_CORRUPT;
		}

		// return data is all checked and valid, now check order
		$order = Mage::getModel('sales/order')->loadByIncrementId($params['order_reference']);
		/* @var $order Mage_Sales_Model_Order */
		$orderMethod = $order->getPayment()->getMethod();
		if ($orderMethod != $this->_code)
		{
			$this->log('(Everypay): Wrong payment method. Order has: ' . $orderMethod, __METHOD__, __LINE__);
			Mage::getSingleton('checkout/session')->addError('Wrong payment method.');
			return self::_VERIFY_CORRUPT;
		}

		return $this->statuses[$params['transaction_result']];
	}

	protected function verifyNonce($nonce)
	{
		$r = Mage::getSingleton('core/resource')
				->getConnection('read_connection')
				->query('select nonce from everypay_nonce where nonce=\'' . $nonce . '\'')
				->fetchAll();
		return (count($r) === 0);
	}

	/**
	 * This method creates invoice for current order
	 */
	public function createInvoice()
	{
		$orderNumber = $this->getOrderNumber();
		$order = Mage::getModel('sales/order')->loadByIncrementId($orderNumber);

		if (!$this->isLocked($orderNumber))
		{
			if ($order->canInvoice())
			{

				if ($this->createLock($orderNumber))
				{

					$invoice = $order->prepareInvoice();
					$invoice->pay()->register();
					$invoice->save();

					$order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
					$order->save();

					/* Release lock file right after creating invoice */
					$this->releaseLock($orderNumber);

					/* Send invoice */
					if (Mage::getStoreConfig('payment/' . $this->_code . '/invoice_confirmation') == '1')
					{
						$invoice->sendEmail(true, '');
					}

					Mage::register('current_invoice', $invoice);
				}
			} else
			{
				$this->log('Failed to create invoice for order ' . $orderNumber . '. Reason: invoice already created', __METHOD__, __LINE__);
			}
		} else
		{
			$this->log('Failed to create invoice for order ' . $orderNumber . '. Reason: order locked', __METHOD__, __LINE__);
		}
	}

	/**
	 *
	 * @param string $orderNumber
	 * @return string
	 */
	private function getLockfilePath($orderNumber)
	{
		return Mage::getBaseDir('var') . DS . 'locks' . DS . 'order_' . $orderNumber . '.lock';
	}

	/**
	 * Checks if given invoice is locked, i.e if it has
	 * a file in var/locks folder
	 *
	 * @param string $orderNumber
	 */
	public function isLocked($orderNumber)
	{
		return file_exists($this->getLockfilePath($orderNumber));
	}

	/**
	 * Locks order, i.e creates a lock file
	 * in var/locks folder
	 * @param string $orderNumber
	 */
	public function createLock($orderNumber)
	{
		$path = $this->getLockfilePath($orderNumber);
		if (!touch($this->getLockfilePath($orderNumber)))
		{
			$this->log('Failed to create lockfile ' . $path, __METHOD__, __LINE__);
			return false;
		}
		$this->log('Created lockfile ' . $path, __METHOD__, __LINE__);
		return true;
	}

	/**
	 * Releases lock for order, i.e deletes
	 * lock file from var/locks folder
	 *
	 * @param string $orderNumber
	 */
	public function releaseLock($orderNumber)
	{
		$path = $this->getLockfilePath($orderNumber);
		if (!unlink($path))
		{
			$this->log('Failed to delete lockfile ' . $path, __METHOD__, __LINE__);
			return false;
		}
		$this->log('Deleted lockfile ' . $path, __METHOD__, __LINE__);
		return true;
	}

	protected function log($t, $m, $l)
	{
		Mage::log(sprintf('%s(%s)@%s: %s', $m, $l, $_SERVER['REMOTE_ADDR'], $t), null, $this->logFile);
	}

}
