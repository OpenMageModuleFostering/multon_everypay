<?php
/**
 * @author Tanel Raja <tanel.raja@multon.ee>
 * @copyright Copyright (c) 2014, Multon (http://multon.ee/)
 */
class Multon_Everypay_Block_Adminhtml_Fieldset extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{

    protected function _getHeaderTitleHtml($element)
    {
	$element->setLegend('<img style="position: relative; top: 2px; margin-right: 5px;" src="' .
		Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN) .
		'adminhtml/default/default/images/everypay-logo.jpg" />' .
		$element->getLegend()
		);
	return parent::_getHeaderTitleHtml($element);
    }

}
