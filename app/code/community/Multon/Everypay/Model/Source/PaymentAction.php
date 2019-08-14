<?php
class Multon_Everypay_Model_Source_PaymentAction
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'authorisation',
                'label' => 'Authorisation'
            ),
            array(
                'value' => 'charge',
                'label' => 'Charge'
            ),
        );
    }
}
