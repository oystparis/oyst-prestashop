{**
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
 * @license GNU GENERAL PUBLIC LICENSE
 *}
{if $oneClickActivated && $btnOneClickState}
    {if version_compare($smarty.const._PS_VERSION_,'1.6','<')}
        <script src="{$JSOystOneClick}"></script>
        <script src="{$JSOneClickUrl}"></script>
    {/if}
    <script type="text/javascript">
        var oyst = new OystOneClick({$shopUrl|cat:'/modules/oyst/oneClick.php'|json_encode}, {$product->id|json_encode});
        oyst.setExportedCombinations({$synchronizedCombination|json_encode})
        oyst.setStockManagement({$stockManagement|intval});
        oyst.setAllowOosp({$allowOosp|intval});
        oyst.setProductQuantity({$productQuantity|intval});
        oyst.setThemeBtn("{$themeBtn|escape:'html':'UTF-8'}");
        oyst.setColorBtn("{$colorBtn|escape:'html':'UTF-8'}");
        oyst.setWidthBtn("{$widthBtn|escape:'html':'UTF-8'}");
        oyst.setHeightBtn("{$heightBtn|escape:'html':'UTF-8'}");
        oyst.setPositionBtn("{$positionBtn|escape:'html':'UTF-8'}");
        oyst.prepareButton();

        window.__OYST__ = window.__OYST__ || {};
        window.__OYST__.getOneClickURL = function(callback) {
            oyst.requestOneCLick(callback);
        };
    </script>
{/if}
