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


use Oyst\Repository\OrderRepository;
use Oyst\Repository\ProductRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Oyst\Api\OystApiClientFactory;

class OystHookDisplayBackOfficeHeaderProcessor extends FroggyHookProcessor
{
    public function run()
    {
        if (!Module::isInstalled($this->module->name) || !Module::isEnabled($this->module->name)) {
            return '';
        }

        if (Tools::isSubmit('id_order')) {
            // Check if order has been paid with Oyst
            $order = new Order(Tools::getValue('id_order'));
            if ($order->module == $this->module->name) {
                // Partial refund
                if (Tools::isSubmit('partialRefund') && isset($order)) {
                    $this->partialRefundOrder($order);
                }
            }
            return ;
        }

        $content = '';

        $oystProductRepository = new ProductRepository(Db::getInstance());
        $exportedProducts = $oystProductRepository->getExportedProduct();

        /** @var Smarty_Internal_Template $template */
        $template = Context::getContext()->smarty->createTemplate(__DIR__.'/../views/templates/hook/displayBackOfficeHeader.tpl');
        $exportDate = $this->module->getRequestedCatalogDate();
        $template->assign([
            'marginRequired' => version_compare(_PS_VERSION_, '1.5', '>'),
            'OYST_REQUESTED_CATALOG_DATE' => $exportDate ? $exportDate->format(Context::getContext()->language->date_format_full) : false,
            'OYST_IS_EXPORT_STILL_RUNNING' => $this->module->isCatalogExportStillRunning(),
            'exportedProducts' => $exportedProducts,
            'displayPanel' => $this->module->getAdminPanelInformationVisibility(),
        ]);

        $content .= $template->fetch();

        return $content;
    }

    private function partialRefundOrder($order)
    {
        $oystOrderRepository = new OrderRepository(Db::getInstance());
        $idTab = $this->context->controller->tabAccess['id_tab'];
        $tabAccess = Profile::getProfileAccess($this->context->employee->id_profile, $idTab);

        $amountToRefund = $oystOrderRepository->getAmountToRefund($order, $tabAccess);

        if ($amountToRefund > 0) {
            // Make Oyst api call
            $oystPaymentNotification = OystPaymentNotification::getOystPaymentNotificationFromCartId($order->id_cart);
            $paymentApi = OystApiClientFactory::getClient(
                OystApiClientFactory::ENTITY_PAYMENT,
                $oyst->getApiKey(),
                $oyst->getUserAgent(),
                $oyst->getApiUrl()
            );
            if (Validate::isLoadedObject($oystPaymentNotification)) {
                $currency = new Currency($order->id_currency);
                $oyst = new Oyst();
                /** @var OystPaymentApi $paymentApi */
                $response = $paymentApi->cancelOrRefund($oyst_payment_notification->payment_id, new Price($amountToRefund, $currency->iso_code));

                // Set refund status
                if ($paymentApi->getLastHttpCode() == 200) {
                    $history = new OrderHistory();
                    $history->id_order = $order->id;
                    $history->id_employee = 0;
                    $history->id_order_state = (int)Configuration::get('OYST_STATUS_PARTIAL_REFUND_PEND');
                    $history->changeIdOrderState((int)Configuration::get('OYST_STATUS_PARTIAL_REFUND_PEND'), $order->id);
                    $history->add();
                }
            }

            if ($paymentApi->getLastHttpCode() != 200) {
                unset($_POST['partialRefund']);

                Tools::redirectAdmin(Context::getContext()->link->getAdminLink('AdminOrders').'&vieworder&id_order='.$order->id);
            }
        }
    }
}
