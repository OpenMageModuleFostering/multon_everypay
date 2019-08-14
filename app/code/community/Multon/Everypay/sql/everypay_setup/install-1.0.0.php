<?php

$installer = $this;

$installer->startSetup();

$tableName = $installer->getTable('everypay/nonce');

$table = $installer->getConnection()
		->newTable($tableName)
		->addColumn('nonce', Varien_Db_Ddl_Table::TYPE_TEXT, 255, array(
			'nullable' => false,
				), 'Nonce')
		->addIndex($installer->getIdxName($tableName, array('nonce'), Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE),
			'nonce',
			array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE))
;
$installer->getConnection()->createTable($table);

$installer->endSetup();
