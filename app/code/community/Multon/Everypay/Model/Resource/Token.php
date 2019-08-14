<?php

class Multon_Everypay_Model_Resource_Token extends Mage_Core_Model_Resource_Db_Abstract
{
	protected function _construct()
	{
        $this->_init('everypay/token', 'token_id');
	}
}
