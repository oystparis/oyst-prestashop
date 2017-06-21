<?php
/**
 * 2013-2016 Froggy Commerce
 *
 * NOTICE OF LICENSE
 *
 * You should have received a licence with this module.
 * If you didn't download this module on Froggy-Commerce.com, ThemeForest.net,
 * Addons.PrestaShop.com, or Oyst.com, please contact us immediately : contact@froggy-commerce.com
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to benefit the updates
 * for newer PrestaShop versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Froggy Commerce <contact@froggy-commerce.com>
 * @copyright 2013-2016 Froggy Commerce / 23Prod / Oyst
 * @license   GNU GENERAL PUBLIC LICENSE
 */

namespace Oyst\Transformer;

use Combination;
use Context;
use Image;
use Oyst\Classes\OystCategory;
use Oyst\Classes\OystPrice;
use Oyst\Classes\OystProduct;
use Oyst\Classes\OystSize;
use Product;
use Psr\Log\AbstractLogger;
use StockAvailable;
use Tools;

/**
 * Class ProductTransformer
 */
class ProductTransformer extends AbstractTransformer
{
    /** @var  AbstractLogger */
    private $logger;

    /**
     * ProductTransformer constructor.
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    /**
     * @param AbstractLogger $logger
     * @return $this
     */
    public function setLogger(AbstractLogger $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @param Product $product
     *
     * @return OystProduct
     */
    public function transform($product)
    {
        $oystProduct = new OystProduct();

        $oystPrice = new OystPrice($product->getPrice(true), $this->context->currency->iso_code);

        $categories = array();

        foreach (Product::getProductCategoriesFull($product->id) as $categoryInfo) {
            $oystCategory = new OystCategory(
                $categoryInfo['id_category'],
                $categoryInfo['name'],
                $categoryInfo['id_category'] == $product->id_category_default
            );
            $categories[] = $oystCategory;
        }

        if (empty($categories)) {
            if ($this->logger instanceof AbstractLogger) {
                $this->logger->alert(sprintf('No categories for product %d', $product->id));
            }
            return null;
        }

        $oystSize = new OystSize(
            $product->height > 0 ? $product->height : 1,
            $product->width > 0 ? $product->width : 1,
            $product->depth > 0 ? $product->depth : 1
        );

        // Combination fields
        $oystProduct->setRef($product->id);
        $oystProduct->setEan($product->ean13);
        $oystProduct->setWeight($product->weight);

        // Common fields
        $oystProduct->setActive($product->active);
        $oystProduct->setManufacturer($product->manufacturer_name);
        $oystProduct->setSize($oystSize);
        $oystProduct->setCondition(($product->condition == 'used' ? 'reused' : $product->condition));
        $oystProduct->setCategories($categories);
        $oystProduct->setAmountIncludingTax($oystPrice);
        $oystProduct->setAvailableQuantity(StockAvailable::getStockAvailableIdByProductId($product->id));
        $oystProduct->setTitle(is_array($product->name) ? reset($product->name) : $product->name);
        $oystProduct->setDescription(is_array($product->description) ? reset($product->description) : $product->description);
        $oystProduct->setShortDescription(is_array($product->description_short) ? reset($product->description_short) : $product->description_short);
        $oystProduct->setUrl($this->context->link->getProductLink($product));

        $images = array();
        foreach (Image::getImages($this->context->language->id, $product->id) as $image) {
            $images[] = $this->context->link->getImageLink($product->link_rewrite, $image['id_image']);
        }

        if (empty($images)) {
            $images = array(Tools::getShopDomain(true) . '/modules/oyst/view/img/no_image.png');
        }

        $oystProduct->setImages($images);

        return $oystProduct;
    }

    public function reverseTransform($value)
    {
        // Implement this method if you need it
    }

    /**
     * @param Product $product
     * @param Combination $combination
     * @return OystProduct
     */
    public function transformCombination(Product $product, Combination $combination)
    {
        $oystProductVariation = $this->transform($product);

        if ($oystProductVariation && $combination && $combination->id) {
            $oystPrice = new OystPrice($product->getPrice(true, $combination->id), $this->context->currency->iso_code);

            $oystProductVariation->setRef($combination->id);
            $oystProductVariation->setEan($combination->ean13);
            $oystProductVariation->setWeight($combination->weight);

            $oystProductVariation->setAmountIncludingTax($oystPrice);
            $oystProductVariation->setAvailableQuantity(StockAvailable::getStockAvailableIdByProductId($product->id, $combination->id));

            $images = array();
            foreach (Image::getImages($this->context->language->id, $product->id, $combination->id) as $image) {
                $images[] = $this->context->link->getImageLink($product->link_rewrite, $image['id_image']);
            }

            if (empty($images)) {
                $images = array(Tools::getShopDomain(true) . '/modules/oyst/view/img/no_image.png');
            }

            $oystProductVariation->setImages($images);
        }

        return $oystProductVariation;
    }
}
