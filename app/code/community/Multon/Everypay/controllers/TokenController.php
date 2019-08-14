<?php

class Multon_Everypay_TokenController extends Mage_Core_Controller_Front_Action
{

	protected function _getSession()
	{
		return Mage::getSingleton('customer/session');
	}

	public function preDispatch()
	{
		parent::preDispatch();

		if (!Mage::getSingleton('customer/session')->authenticate($this))
		{
			$this->setFlag('', 'no-dispatch', true);
		}
	}

	public function indexAction()
	{
		$this->loadLayout();
		$this->_initLayoutMessages('customer/session');
		$this->_initLayoutMessages('catalog/session');
		$this->renderLayout();
	}

	public function setDefaultAction()
	{
		$id = $this->getRequest()->getParam('token', false);
		if ($id)
		{
			$customerId = $this->_getSession()->getCustomerId();
			$token = Mage::getModel('everypay/token')->load($id);
			if ($token && ($token->getCustomerId() == $customerId))
			{
				Mage::getModel('everypay/token')
						->getCollection()
						->addFieldToFilter('customer_id', $customerId)
						->load()
						->setDataToAll('is_default', 0)
						->save();
				$token->setIsDefault(1)->save();
			}
		}
		$this->getResponse()->setRedirect(Mage::getUrl('*/*/index'));
	}

	public function deleteAction()
	{
		$id = $this->getRequest()->getParam('token', false);

		if ($id)
		{
			$token = Mage::getModel('everypay/token')->load($id);

			// Validate address_id <=> customer_id
			if ($token->getCustomerId() != $this->_getSession()->getCustomerId())
			{
				$this->_getSession()->addError($this->__('The token does not belong to this customer.'));
				$this->getResponse()->setRedirect(Mage::getUrl('*/*/index'));
				return;
			}

			try
			{
				$token->delete();
				$this->_getSession()->addSuccess($this->__('The token has been deleted.'));
			} catch (Exception $e)
			{
				$this->_getSession()->addException($e, $this->__('An error occurred while deleting the token.'));
			}
		}
		$this->getResponse()->setRedirect(Mage::getUrl('*/*/index'));
	}
}
