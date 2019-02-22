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

/*
 * Security
 */
use Oyst\Repository\ProductRepository;
use Oyst\Service\TrackingService;

if (!defined('_PS_VERSION_')) {
    exit;
}

class OystHookDisplayFooterProcessor extends FroggyHookProcessor
{
    public function run()
    {
        $step = 0;
        $assign = array();
        $oyst = new Oyst();
        $display_btn_cart = $oyst->displayBtnCart();
        $restriction_languages = $this->restrictionsLanguages();
        $restriction_currencies = $this->restrictionsCurrencies();
        $controller = Context::getContext()->controller->php_self;
        $oneClickActivated = (int)Configuration::get('OYST_ONE_CLICK_FEATURE_STATE');
        $token = hash('sha256', Tools::jsonEncode(array(Configuration::get('FC_OYST_HASH_KEY'), _COOKIE_KEY_)));

        if (Tools::getValue('step') != null) {
            $step = (int)Tools::getValue('step');
        }

        //Add tracker for confirmation page
        if (in_array($this->getPageName(), array('orderconfirmation', 'oneclickconfirmation'))) {
            $this->smarty->assign('tracker', TrackingService::getInstance()->getTrackingHtml());
            return $this->module->fcdisplay(__FILE__, 'displayFooterTracker.tpl');
        }

        if (!in_array($controller, $this->restrictionPage())) {
            return '';
        }

        // Get type btn with controller
        $suffix_conf = $this->getTypeBtn($controller, $step);


        // Manage url for 1-Click
        $assign['btnOneClickState'] = true;
        $assign['displayBtnCart'] = $display_btn_cart;
        $JSConfButton = $this->path.'views/js/ConfButton.js';
        $shopUrl = trim(Tools::getShopDomainSsl(true).__PS_BASE_URI__, '/');
        $oneClickUrl = $shopUrl.'/modules/oyst/oneClick.php?key='.$token;
        $JSOneClickUrl = trim($this->module->getOneClickUrl(), '/');
        $baseOneClickModalUrl = trim($this->module->getOneClickModalUrl(), '/');

        // Params specific for each page
        if ($controller != null && $display_btn_cart) {
            $JSOystOneClick = $this->path.'views/js/OystOneClickCart.js';

            $assign['btnOneClickState'] = true;
            $assign['oyst_label_cta'] = $this->module->l('Return shop.', 'oystHookdisplayfooterprocessor');

            $products = Context::getContext()->cart->getProducts();
        } else {
            $JSOystOneClick = $this->path.'views/js/OystOneClick.js';
            $productRepository = new ProductRepository(Db::getInstance());

            $product = new Product(Tools::getValue('id_product'));
            if (!Validate::isLoadedObject($product)) {
                return '';
            }

            $productCombinations = $product->getAttributeCombinations($this->context->language->id);

            $synchronizedCombination = array();
            foreach ($productCombinations as $combination) {
                $stockAvailable = new StockAvailable(
                    StockAvailable::getStockAvailableIdByProductId(
                        $product->id,
                        $combination['id_product_attribute']
                    )
                );
                $synchronizedCombination[$combination['id_product_attribute']] = array(
                    'quantity' => $stockAvailable->quantity
                );
            }

            //require for load Out Of Stock Information (isAvailableWhenOutOfStock)
            $product->loadStockData();

            $assign['product'] = $product;
            $assign['synchronizedCombination'] = $synchronizedCombination;
            $assign['btnOneClickState'] = $productRepository->getActive($product->id);
            $assign['productQuantity'] = StockAvailable::getQuantityAvailableByProduct($product->id);
            $assign['allowOosp'] = $product->isAvailableWhenOutOfStock((int)$product->out_of_stock);
        }

        // Params global
        $assign['oneClickUrl'] = $oneClickUrl;
        $assign['enabledBtn'] = Configuration::get('FC_OYST_BTN_'.$suffix_conf);
        $assign['oneClickActivated'] = $oneClickActivated;
        $assign['smartBtn'] = Configuration::get('FC_OYST_SMART_BTN');
        $assign['borderBtn'] = Configuration::get('FC_OYST_BORDER_BTN');
        $assign['themeBtn'] = Configuration::get('FC_OYST_THEME_BTN');
        $assign['colorBtn'] = Configuration::get('FC_OYST_COLOR_BTN');
        $assign['restriction_currencies'] = $restriction_currencies;
        $assign['restriction_languages'] = $restriction_languages;
        $assign['stockManagement'] = Configuration::get('PS_STOCK_MANAGEMENT');
        $assign['controller'] = $controller;
        $assign['error_quantity_null_text'] = Tools::displayError('Null quantity.');
        $assign['error_product_outofstock_text'] = Tools::displayError('There is not enough product in stock.');

        // Params custom global
        $assign['idBtnAddToCart'] = Configuration::get('FC_OYST_ID_BTN_'.$suffix_conf);
        $assign['idSmartBtn'] = Configuration::get('FC_OYST_ID_SMART_BTN_'.$suffix_conf);
        $assign['positionBtn'] = Configuration::get('FC_OYST_POSITION_BTN_'.$suffix_conf);
        $assign['widthBtn'] = Configuration::get('FC_OYST_WIDTH_BTN_'.$suffix_conf);
        $assign['heightBtn'] = Configuration::get('FC_OYST_HEIGHT_BTN_'.$suffix_conf);
        $assign['marginTopBtn'] = Configuration::get('FC_OYST_MARGIN_TOP_BTN_'.$suffix_conf);
        $assign['marginLeftBtn'] = Configuration::get('FC_OYST_MARGIN_LEFT_BTN_'.$suffix_conf);
        $assign['marginRightBtn'] = Configuration::get('FC_OYST_MARGIN_RIGHT_BTN_'.$suffix_conf);
        $assign['oneClickModalUrl'] = $baseOneClickModalUrl;
        $assign['sticky'] = (int)Configuration::get('FC_OYST_STICKY');

        $this->smarty->assign($assign);

        $this->context->controller->addCSS(array(
            $this->path.'views/css/oyst.css',
        ));

        if (_PS_VERSION_ >= '1.6.0.0') {
            if ($oneClickActivated  && $assign['btnOneClickState'] && $restriction_currencies && $restriction_languages && Configuration::get('FC_OYST_BTN_'.$suffix_conf)) {
                Media::addJsDef(
                    $assign
                );
                $this->context->controller->addJS(array(
                    $JSOystOneClick,
                    $JSConfButton,
                    $JSOneClickUrl,
                ));
            }
        } else {
            $this->smarty->assign(array(
                'JSOystOneClick' => $JSOystOneClick,
                'JSOneClickUrl' => $JSOneClickUrl,
            ));
        }

        $this->smarty->assign(array(
            'styles_custom' => $this->addButtonWrapperStyles()
        ));

        return $this->module->fcdisplay(__FILE__, 'displayFooter.tpl');
    }

    public function addButtonWrapperStyles()
    {
        $styles = Configuration::get('FC_OYST_CUSTOM_CSS');

        if (!$styles && $styles != '') {
            return null;
        }

        $styles = rtrim($styles, " \n\r;");

        return $styles;
    }

    /**
     * @return bool
     */
    public function restrictionsCurrencies()
    {
        $id_currency = $this->context->currency->id;
        $oyst_currencies = Configuration::get('FC_OYST_CURRENCIES');
        if ($oyst_currencies != null || $oyst_currencies != '') {
            if (false  !== strpos($oyst_currencies, ',')) {
                $currencies = explode(',', $oyst_currencies);
                $restriction_currencies = in_array($id_currency, $currencies)? true : false;
            } else {
                $restriction_currencies = $id_currency == $oyst_currencies ? true : false;
            }
        } else {
            $restriction_currencies = true;
        }

        return $restriction_currencies;
    }

    /**
     * @return bool
     */
    public function restrictionsLanguages()
    {
        $id_lang = $this->context->language->id;
        $oyst_languages = Configuration::get('FC_OYST_LANG');
        if ($oyst_languages != null || $oyst_languages != '') {
            if (false  !== strpos($oyst_languages, ',')) {
                $languages = explode(',', $oyst_languages);
                $restriction_languages = in_array($id_lang, $languages)? true : false;
            } else {
                $restriction_languages = $id_lang == $oyst_languages ? true : false;
            }
        } else {
            $restriction_languages = true;
        }

        return $restriction_languages;
    }

    /**
     * @return array
     */
    public function restrictionPage()
    {
        $access_allow = array();

        if (Configuration::get('FC_OYST_BTN_CART')) {
            $access_allow[] = 'order';
            $access_allow[] = 'order-opc';
        }

        if (Configuration::get('FC_OYST_BTN_PRODUCT')) {
            $access_allow[] = 'product';
        }

        if (Configuration::get('FC_OYST_BTN_LAYER')) {
            $access_allow[] = 'index';
            $access_allow[] = 'category';
        }

        if (Configuration::get('FC_OYST_BTN_LOGIN')) {
            $access_allow[] = 'authentication';
        }

        if (Configuration::get('FC_OYST_BTN_ADDR')) {
            $access_allow[] = 'address';
        }

        return $access_allow;
    }

    /**
     * @return string
     */
    public function getTypeBtn($controller = 'product', $step = 0)
    {
        if (($controller == 'order' && $step != 3) || $controller == 'order-opc') {
            $btn = 'CART';
        } elseif ($controller == 'order' && $step == 3) {
            $btn = 'PAYMENT';
        } elseif ($controller == 'index' || $controller == 'category') {
            $btn = 'LAYER';
        } elseif ($controller == 'authentication') {
            $btn = 'LOGIN';
        } elseif ($controller == 'address') {
            $btn = 'ADDR';
        } else {
            $btn = 'PRODUCT';
        }

        return $btn;
    }

    public function getPageName()
    {
        if (preg_match('#^'.preg_quote($this->context->shop->physical_uri, '#').'modules/([a-zA-Z0-9_-]+?)/(.*)$#', $_SERVER['REQUEST_URI'], $m)) {
            $page_name = 'module-'.$m[1].'-'.str_replace(array('.php', '/'), array('', '-'), $m[2]);
        } else {
            $page_name = Dispatcher::getInstance()->getController();
            $page_name = (preg_match('/^[0-9]/', $page_name) ? 'page_'.$page_name : $page_name);
        }
        return $page_name;
    }
}