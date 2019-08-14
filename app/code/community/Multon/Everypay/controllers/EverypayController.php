<?php
class Multon_Everypay_EverypayController extends Mage_Core_Controller_Front_Action
{
	/**
	 *
	 * @var Specifies model to be used to verify response from bank
	 */
	protected $_model = 'everypay/everypay';

	/**
	 *
	 * @var Specifies payment method code in magento
	 */
	protected $_code = 'multon_everypay';

	/**
	 *
	 * @var Fieldname of order number in return data (used on automatic response from bank)
	 */
	protected $orderNoField = 'order_reference';

	/**
	 *
	 * @var specifies log file name for Payment
	 */
	protected $logFile = 'everypay.log';

	/**
	 * This action redirects user to bank for payment
	 *
	 * @return void
	 */
	public function redirectAction()
	{
		/* Send order confirmation */
		if (Mage::getStoreConfig('payment/' . $this->_code . '/order_confirmation') == '1') {
			try {
				$order = Mage::getModel('sales/order');
				$order->load(
						Mage::getSingleton('checkout/session')->getLastOrderId()
				);
				$order->sendNewOrderEmail();
				$order->save();
			} catch (Exception $e) {
				Mage::log(sprintf('%s(%s): %s', __METHOD__, __LINE__, print_r($e->getMessage(), true)), null, $this->logFile);
			}
		}

		$this->loadLayout();
		$this->renderLayout();
	}

	public function callbackAction()
	{
		$params = $this->getRequest()->getParams();
		$this->log($params, __METHOD__, __LINE__);

		$orderNumber = $params[$this->orderNoField];
		if (!$orderNumber) {
			$this->log('Order number not found in \''.$this->orderNoField.'\'', __METHOD__, __LINE__);
			$this->_redirect('checkout/onepage/failure');
			return;
		}

		$this->dealWithIt($params, $orderNumber, true);
	}

	public function returnAction()
	{
		$params = $this->getRequest()->getParams();
		$this->log($params, __METHOD__, __LINE__);

		$session = Mage::getSingleton('checkout/session');
		$orderNumber = $session->getLastRealOrderId();
		if (!$orderNumber) {
			$this->log('Order number not found in session', __METHOD__, __LINE__);
			$this->_redirect('checkout/onepage/failure');
			return;
		}

		$this->dealWithIt($params, $orderNumber);
	}

	protected function dealWithIt($params, $orderNumber, $isCallback = false)
	{
		$model = Mage::getModel($this->_model);
		$model->setOrderNumber($orderNumber);
		$verify = $model->verify($params);
		switch ($verify) {
			case Multon_Everypay_Model_Everypay::_VERIFY_SUCCESS:
				if ($isCallback)
				{
					$order = Mage::getModel('sales/order')->loadByIncrementId($orderNumber);
					$customerId = $order->getCustomerId();
				} else
					$customerId = Mage::getSingleton('customer/session')->getCustomerId();

				if ($customerId && isset($params['cc_token']))
				{
					// number of customer card tokens
					$tokenCount = Mage::getResourceModel('everypay/token_collection')
							->addFieldToFilter('customer_id', $customerId)
							->getSize();

					// check if card token already exists
					if (Mage::getResourceModel('everypay/token_collection')
							->addFieldToFilter('customer_id', $customerId)
							->addFieldToFilter('cc_token', $params['cc_token'])
							->getSize() == 0)
					{
						Mage::getModel('everypay/token')
								->setCustomerId($customerId)
								->setCcToken($params['cc_token'])
								->setCcLastFourDigits($params['cc_last_four_digits'])
								->setCcYear($params['cc_year'])
								->setCcMonth($params['cc_month'])
								->setCcType($params['cc_type'])
								->setIsDefault($tokenCount ? 0 : 1) // set first one to default
								->save();
					}
				}
				$model->createInvoice();
				$this->_redirect('checkout/onepage/success');
				break;
			case Multon_Everypay_Model_Everypay::_VERIFY_CANCEL:
				$order = Mage::getModel('sales/order')->loadByIncrementId($orderNumber);
				$order->cancel()->save();
				$this->_redirect('checkout/onepage/failure');
				break;
			case Multon_Everypay_Model_Everypay::_VERIFY_CORRUPT:
			default:
				$this->_redirect('checkout/onepage/failure');
				break;
		}
	}

	protected function log($txt, $method = __METHOD__, $line = __LINE__)
	{
		Mage::log(sprintf('%s(%s)@%s: %s', $method, $line, $_SERVER['REMOTE_ADDR'], print_r($txt, true)), null, $this->logFile);
	}
}
