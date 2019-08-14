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

	/**
	 * Order Id to create invoice for
	 * @var string
	 */
	protected $_orderId;

	protected $_code = 'multon_everypay';
	protected $_formBlockType = 'everypay/everypay';
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
	 * Verifies response from Everypay
	 *
	 * @param array $params Response sent by bank and to be verified
	 *
	 * @return int
	 */
	public function verify(array $params = array())
	{
		// only in automatic callback message
		if (isset($params['processing_errors']))
		{
			$errors = json_decode($params['processing_errors']);
			if (!empty($errors))
				$this->log('(Everypay) Errors: ' . print_r($errors, 1), __METHOD__, __LINE__);
		}
		if (isset($params['processing_warnings']))
		{
			$warnings = json_decode($params['processing_warnings']);
			if (!empty($warnings))
				$this->log('(Everypay) Warnings: ' . print_r($warnings, 1), __METHOD__, __LINE__);
		}

		if (!isset($params['api_username']) || ($params['api_username'] !== Mage::getStoreConfig('payment/' . $this->_code . '/api_username')))
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

		$session = Mage::getSingleton('checkout/session');
		// Reference number doesn't match.
		if ($session->getLastRealOrderId() != $params['order_reference'])
		{
			$this->log('(Everypay): Order number doesn\'t match (potential tampering attempt).', __METHOD__, __LINE__);
			Mage::getSingleton('checkout/session')->addError('Order number error.');
			return self::_VERIFY_CORRUPT;
		}

		if (!$this->verifyNonce($params['nonce']))
		{
			$this->log('(Everypay): Nonce already used.', __METHOD__, __LINE__);
			Mage::getSingleton('checkout/session')->addError('Nonce already used.');
			return self::_VERIFY_CORRUPT;
		}

		$status = $this->statuses[$params['transaction_result']];
		switch ($params['transaction_result'])
		{
			case 'completed':
			case 'failed':
				if ($params['account_id'] !== Mage::getStoreConfig('payment/' . $this->_code . '/account_id'))
				{
					$this->log('(Everypay) Invalid account ID', __METHOD__, __LINE__);
					Mage::getSingleton('checkout/session')->addError('Invalid account.');
					return self::_VERIFY_CORRUPT;
				}

				$data = 'account_id=' . $params['account_id'] . '&' .
						'amount=' . $params['amount'] . '&' .
						'api_username=' . $params['api_username'] . '&' .
						'nonce=' . $params['nonce'] . '&' .
						'order_reference=' . $params['order_reference'] . '&' .
						'payment_reference=' . $params['payment_reference'] . '&' .
						'payment_state=' . $params['payment_state'] . '&';
				if (isset($params['processing_errors']))
				{
					$data .= 'processing_errors=' . $params['processing_errors'] . '&' .
							'processing_warnings=' . $params['processing_warnings'] . '&';
				}
				$data .= 'timestamp=' . $params['timestamp'] . '&' .
						'transaction_result=' . $params['transaction_result'];
				break;
			case 'cancelled':
				$data = 'api_username=' . $params['api_username'] . '&' .
						'nonce=' . $params['nonce'] . '&' .
						'order_reference=' . $params['order_reference'] . '&' .
						'payment_state=' . $params['payment_state'] . '&' .
						'timestamp=' . $params['timestamp'] . '&' .
						'transaction_result=' . $params['transaction_result'];
				break;
		}
		$hmac = hash_hmac('sha1', $data, Mage::getStoreConfig('payment/' . $this->_code . '/api_secret'));
		if ($params['hmac'] != $hmac)
		{
			$this->log('(Everypay) Invalid HMAC', __METHOD__, __LINE__);
			Mage::getSingleton('checkout/session')->addError('Invalid HMAC.');
			return self::_VERIFY_CORRUPT;
		}

		return $status;
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
		$order = Mage::getModel('sales/order')->loadByIncrementId($this->getOrderId());

		if (!$this->isLocked($this->getOrderId()))
		{
			if ($order->canInvoice())
			{

				if ($this->createLock($this->getOrderId()))
				{

					$invoice = $order->prepareInvoice();
					$invoice->pay()->register();
					$invoice->save();

					$order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
					$order->save();

					/* Release lock file right after creating invoice */
					$this->releaseLock($this->getOrderId());

					/* Send invoice */
					if (Mage::getStoreConfig('payment/' . $this->_code . '/invoice_confirmation') == '1')
					{
						$invoice->sendEmail(true, '');
					}

					Mage::register('current_invoice', $invoice);
				}
			} else
			{
				$this->log('Failed to create invoice for order ' . $this->getOrderId() . '. Reason: invoice already created', __METHOD__, __LINE__);
			}
		} else
		{
			$this->log('Failed to create invoice for order ' . $this->getOrderId() . '. Reason: order locked', __METHOD__, __LINE__);
		}
	}

	/**
	 *
	 * @param string $orderId
	 * @return string
	 */
	private function getLockfilePath($orderId)
	{
		return Mage::getBaseDir('var') . DS . 'locks' . DS . 'order_' . $orderId . '.lock';
	}

	/**
	 * Checks if given invoice is locked, i.e if it has
	 * a file in var/locks folder
	 *
	 * @param string $orderId
	 */
	public function isLocked($orderId)
	{
		return file_exists($this->getLockfilePath($orderId));
	}

	/**
	 * Locks order, i.e creates a lock file
	 * in var/locks folder
	 * @param string $orderId
	 */
	public function createLock($orderId)
	{
		$path = $this->getLockfilePath($orderId);
		if (!touch($this->getLockfilePath($orderId)))
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
	 * @param string $orderId
	 */
	public function releaseLock($orderId)
	{
		$path = $this->getLockfilePath($orderId);
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
