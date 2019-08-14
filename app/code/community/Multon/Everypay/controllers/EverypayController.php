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

    /**
     * This is return action handler for Multon Payment method
     * It verifies signature and creates invoice.
     * In case of verification failure it cancels the order
     *
     * @return void
     */
    public function returnAction()
    {
        Mage::log(sprintf('%s(%s)@%s: %s', __METHOD__, __LINE__, $_SERVER['REMOTE_ADDR'], print_r($this->getRequest()->getParams(), true)), null, $this->logFile);

        $session = Mage::getSingleton('checkout/session');
        $orderId = $session->getLastRealOrderId();
        if (!$orderId) {
            $orderId = $this->getRequest()->getParam($this->orderNoField);
        }
        if (!$orderId) {
			Mage::log(sprintf('%s(%s)@%s: Order number not found in session or \'%s\'', __METHOD__, __LINE__, $_SERVER['REMOTE_ADDR'], $this->orderNoField),null, $this->logFile);
            $this->_redirect('checkout/onepage/failure');
            return;
        }
        $model = Mage::getModel($this->_model);
        $model->setOrderId($orderId);
        $verify = $model->verify($this->getRequest()->getParams());
        switch ($verify) {
            case Multon_Everypay_Model_Everypay::_VERIFY_SUCCESS:
                $model->createInvoice();
                $this->_redirect('checkout/onepage/success');
                break;
            case Multon_Everypay_Model_Everypay::_VERIFY_CANCEL:
                $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
                $order->cancel()->save();
                $this->_redirect('checkout/onepage/failure');
                break;
            case Multon_Everypay_Model_Everypay::_VERIFY_CORRUPT:
            default:
                $this->_redirect('checkout/onepage/failure');
                break;
        }
    }


}
