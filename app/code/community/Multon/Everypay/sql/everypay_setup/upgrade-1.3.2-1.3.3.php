<?php

$this->getConnection()->modifyColumn(
		$this->getTable('everypay/token'),
		'cc_type',
		array(
			'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
			'length' => 11,
			'nullable' => false,
			'comment' => 'Card Type'
		)
);

$this->getConnection()->modifyColumn(
		$this->getTable('everypay/token'),
		'cc_last_four_digits',
		array(
			'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
			'length' => 4,
			'nullable' => false,
			'comment' => 'Last 4 digits of credit card'
		)
);
