<?php
class Multon_Everypay_Model_Source_ConnectionType
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 0,
                'label' => 'Redirect'
            ),
            array(
                'value' => 1,
                'label' => 'iFrame'
            ),
        );
    }
}
