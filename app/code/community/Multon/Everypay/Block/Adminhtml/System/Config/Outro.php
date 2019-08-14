<?php

class Multon_Everypay_Block_Adminhtml_System_Config_Outro extends Mage_Adminhtml_Block_Abstract
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
		return '<tr><td colspan="5"><p>For further information please refer to the documentation or get in touch with us:</p>'
				. '<a href="https://every-pay.com/documentation">https://every-pay.com/documentation</a><br>'
				. '<a href="https://every-pay.com/contact">https://every-pay.com/contact</a></td></tr>';
	}

}
