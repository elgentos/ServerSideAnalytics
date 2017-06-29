<?php

$installer = $this;
$installer->startSetup();

$installer->getConnection()
    ->addColumn($installer->getTable('sales/order'),'ga_user_id', array(
        'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
        'nullable'  => true,
        'length'    => 255,
        'after'     => null,
        'comment'   => 'Google Analytics User ID for Server Side Analytics'
    ));
$installer->endSetup();