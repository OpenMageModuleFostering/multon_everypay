<?php

class Multon_Everypay_Block_Info extends Mage_Core_Block_Template
{

	public function getEnabledGateways()
	{
		$methods = array();
		if (Mage::getStoreConfig('payment/multon_everypay/active'))
		{
			$paymentModel = Mage::getModel('everypay/everypay');
			$paymentTitle = Mage::getStoreConfig('payment/multon_everypay/title');
			$formBlockType = $paymentModel->getFormBlockType();
			$formBlockInstance = Mage::getBlockSingleton($formBlockType);
			$methods[] = array(
				'title' => $paymentTitle,
				'code' => $paymentModel->getCode(),
				'logo' => $formBlockInstance->getMethodLogoUrl()
			);
		}
		return $methods;
	}

}
