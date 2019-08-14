<?php

class Multon_Everypay_Block_Adminhtml_System_Config_Intro extends Mage_Adminhtml_Block_Abstract
		implements Varien_Data_Form_Element_Renderer_Interface
{

	/**
	 * Render element html
	 *
	 * @param Varien_Data_Form_Element_Abstract $element
	 * @return string
	 */
	public function render(Varien_Data_Form_Element_Abstract $element)
	{
		return '<tr><td colspan="5"><p>EveryPay is a card payment gateway service provider, '
				. 'enabling e-commerce merchants to collect credit and debit card online payments '
				. 'from their customers.</p>'
				. '<p>EveryPay, Hõbeda 6, 10125 Tallinn, Estonia</p>'
				. '<a href="https://every-pay.com/contact">https://every-pay.com/contact</a></td></tr>';
	}

}
