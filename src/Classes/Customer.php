<?php

namespace Oyst\Classes;

use Db;

class Customer
{
    /**
     * @param int $id_customer
     * @return string
     */
    public static function getIdOystFromIdCustomer($id_customer)
    {
        $res = Db::getInstance()->getValue("SELECT `oyst_customer_id` 
            FROM `"._DB_PREFIX_."oyst_customer` 
            WHERE `id_customer` = ".(int)$id_customer);
        return (empty($res) ? '' : $res);
    }

    /**
     * @param string $oyst_customer_id
     * @return int
     */
    public static function getIdCustomerFromIdCustomerOyst($oyst_customer_id)
    {
        return (int)Db::getInstance()->getValue("SELECT `id_customer` 
            FROM `"._DB_PREFIX_."oyst_customer` 
            WHERE `oyst_customer_id` = '".$oyst_customer_id."'");
    }

    public static function createOystCustomerLink($id_customer, $oyst_customer_id)
    {
        return Db::getInstance()->execute("INSERT IGNORE INTO `"._DB_PREFIX_."oyst_customer` (`id_customer`, `oyst_customer_id`) VALUES (".$id_customer.", '".$oyst_customer_id."');");
    }
}
