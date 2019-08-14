<?php

$this->startSetup();

$tableName = $this->getTable('everypay/token');

$table = $this->getConnection()
		->newTable($tableName)
		->addColumn('token_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
				array(
			'identity' => true,
			'unsigned' => true,
			'nullable' => false,
			'primary' => true,
				), 'Token Id')
		->addColumn('customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array('unsigned' => true), 'Customer ID')
		->addColumn('cc_token', Varien_Db_Ddl_Table::TYPE_TEXT, 25, array(), 'Payment token')
		->addColumn('cc_last_four_digits', Varien_Db_Ddl_Table::TYPE_NUMERIC, array(4,0), array('unsigned' => true), 'Last four digits')
		->addColumn('cc_year', Varien_Db_Ddl_Table::TYPE_NUMERIC, array(4,0), array('unsigned' => true), 'Card expiration year (YYYY)')
		->addColumn('cc_month', Varien_Db_Ddl_Table::TYPE_NUMERIC, array(2,0), array('unsigned' => true), 'Card expiration month (MM)')
		->addColumn('cc_type', Varien_Db_Ddl_Table::TYPE_TEXT, 10, array(), 'Card type')
		->addIndex(
				$this->getIdxName($tableName, array('customer_id'), Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX), 'customer_id',
				array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX)
		)
		->addForeignKey(
				$this->getFkName('everypay/token', 'customer_id', 'customer/entity', 'entity_id'), 'customer_id',
				$this->getTable('customer/entity'), 'entity_id', Varien_Db_Ddl_Table::ACTION_CASCADE,
				Varien_Db_Ddl_Table::ACTION_CASCADE
		)
;
$con = $this->getConnection()->createTable($table);

$this->endSetup();
