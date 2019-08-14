<?php

class Multon_Everypay_Block_Adminhtml_System_Config_Version
    extends Mage_Adminhtml_Block_Abstract implements Varien_Data_Form_Element_Renderer_Interface
{
    /**
     * Render element html
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = sprintf('<tr class="system-fieldset-sub-head" id="row_%s"><td colspan="5"><h4 id="%s">%s</h4></td></tr>',
            $element->getHtmlId(), $element->getHtmlId(), $element->getLabel()
        );
		$html .= '<tr><td colspan="5">Everypay module version: '.Mage::getConfig()->getModuleConfig('Multon_Everypay')->version.'</td></tr>';

		return $html;
    }
}
