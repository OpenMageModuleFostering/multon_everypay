<?php

$this->getConnection()->addColumn($this->getTable('everypay/token'), 'is_default', array(
    'type' => Varien_Db_Ddl_Table::TYPE_BOOLEAN,
    'unsigned' => true,
    'nullable' => false,
    'default' => 0,
	'comment' => 'Default payment token'
));
