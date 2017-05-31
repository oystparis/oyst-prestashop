<?php

namespace Oyst\Repository;

use Combination;
use Db;
use Oyst;
use Product;

/**
 * Class Oyst\Repository\ProductRepository
 */
class ProductRepository extends AbstractOystRepository
{
    /**
     * @param int $limitProducts
     * @return array
     */
    public function getProductsNotExported($limitProducts = 2)
    {
        $limitProducts = (int)$limitProducts;
        $query = "
            SELECT 
              p.id_product,
              pa.id_product_attribute
            FROM ps_product p
            LEFT JOIN ps_product_attribute pa ON pa.id_product = p.id_product
            WHERE CONCAT(p.id_product, '-', IFNULL(pa.id_product_attribute, 0)) NOT IN (
              SELECT CONCAT(oec.productId, '-', IFNULL(oec.productAttributeId, 0))
              FROM ps_oyst_exported_catalog oec
            )
        ";

        if ($limitProducts > 0) {
            $query .= ' LIMIT ' . $limitProducts;
        }

        // Use replace to keep IDE working with database source on dev side.
        $products = $this->db->executeS(str_replace('ps_', _DB_PREFIX_, $query));

        return $products;
    }

    /**
     * @return int
     */
    public function getTotalProducts()
    {
        $query = "
            SELECT 
              count(1) totalProducts
            FROM ps_product p
            LEFT JOIN ps_product_attribute pa ON pa.id_product = p.id_product
        ";

        // Use replace to keep IDE working with database source on dev side.
        $totalProducts = $this->db->getValue(str_replace('ps_', _DB_PREFIX_, $query));
        return (int)$totalProducts;
    }

    /**
     * @param Oyst $oyst
     * @param array $baseProductToExport PrestaShop product
     * @param Oyst\Classes\OystProduct[] $oystProductsExported Oysts product really exported
     * @param $importId
     */
    public function recordSentProducts(Oyst $oyst, $baseProductToExport, $oystProductsExported, $importId)
    {
        $productIds = [];
        foreach ($baseProductToExport as $product) {
            $hasBeenExported = false;
            foreach ($oystProductsExported as $oystProduct) {
                $psProduct = new Product($product['id_product']);
                $combination = new Combination($product['id_product_attribute']);
                $baseReference = $oyst->getProductReference($psProduct, $combination);

                if ($baseReference == $oystProduct->getRef()) {
                    $hasBeenExported = true;
                    break;
                }
            }

            $productIds[] = [
                'productId' => $product['id_product'],
                'productAttributeId' => (int)$product['id_product_attribute'],
                'importId' => $importId,
                'hasBeenExported' => (int) $hasBeenExported,
            ];
        }
        $this->db->insert('oyst_exported_catalog', $productIds);
    }

    /**
     * @return array
     */
    public function getExportedProduct()
    {
        $query = '
            SELECT *
            FROM ps_oyst_exported_catalog
        ';

        $results = $this->db->executeS(str_replace('ps_', _DB_PREFIX_, $query));

        return $results;
    }

    /**
     * @return bool
     */
    public function truncateExportTable()
    {
        return Db::getInstance()->execute('TRUNCATE TABLE ' . _DB_PREFIX_ . 'oyst_exported_catalog');
    }
}