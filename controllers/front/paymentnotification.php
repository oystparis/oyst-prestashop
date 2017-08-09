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
if (!defined('_PS_VERSION_')) {
    exit;
}

use Oyst\Repository\OrderRepository;

class OystPaymentnotificationModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        if (Tools::getValue('key') != Configuration::get('FC_OYST_HASH_KEY')) {
            die('Secure key is invalid');
        }

        $event_data = trim(str_replace("'", '', Tools::file_get_contents('php://input')));
        $event_data = Tools::jsonDecode($event_data, true);

        // We store the notification
        $notification_item = $event_data['notification'];
        $id_cart  = $notification_item['order_id'];
        $id_order = Order::getOrderByCartId($id_cart);

        try {
            if ($notification_item['success'] == 1) {
                switch ($notification_item['event_code']) {
                    // If authorisation succeed, we create the order
                    case OystPaymentNotification::EVENT_AUTHORISATION:
                        $this->convertCartToOrder($notification_item, Tools::getValue('ch'), $event_data);
                        break;
                    // If cancellation is confirmed, we cancel the order
                    case OystPaymentNotification::EVENT_CANCELLATION:
                        $this->updateOrderStatus((int)$notification_item['order_id'], Configuration::get('PS_OS_CANCELED'));
                        break;
                    // If refund is confirmed, we cancel the order
                    case OystPaymentNotification::EVENT_REFUND:
                        $oystOrderRepository = new OrderRepository(Db::getInstance());
                        $maxRefund = $oystOrderRepository->calculateOrderMaxRefund($id_cart);
                        $status = $maxRefund == 0 ? Configuration::get('PS_OS_REFUND') : Configuration::get('OYST_STATUS_PARTIAL_REFUND');

                        $this->updateOrderStatus((int)$id_cart, $status);
                        break;
                }
            }
        } catch (Exception $e) {
            $this->module->log($e->getMessage());
            die(Tools::jsonEncode(array('result' => 'ko', 'error' => $e->getMessage())));
        }

        die(Tools::jsonEncode(array('result' => 'ok')));
    }

    public function updateOrderStatus($id_cart, $id_order_state)
    {
        // Get order ID
        $id_order = Order::getOrderByCartId($id_cart);

        if ($id_order > 0 && $id_order_state > 0) {
            // Create new OrderHistory
            $history = new OrderHistory();
            $history->id_order = $id_order;
            $history->id_employee = 0;
            $history->id_order_state = (int)$id_order_state;
            $history->changeIdOrderState((int)$id_order_state, $id_order);
            $history->add();
        }
    }

    public function convertCartToOrder($payment_notification, $url_cart_hash, $event_data)
    {
        // Load cart
        $cart = new Cart((int)$payment_notification['order_id']);

        // Build cart hash
        $cart_hash = md5(Tools::jsonEncode(array($cart->id, $cart->nbProducts())));

        // Load data in context
        $this->context->cart = $cart;
        $address = new Address((int) $cart->id_address_invoice);
        $this->context->country = new Country((int) $address->id_country);
        $this->context->customer = new Customer((int) $cart->id_customer);
        $this->context->language = new Language((int) $cart->id_lang);
        $this->context->currency = new Currency((int) $cart->id_currency);

        // Load shop in context
        if (isset($cart->id_shop)) {
            $this->context->shop = new Shop($cart->id_shop);
        }

        if ($payment_notification['success'] == 'true') {
            // Build transation array
            $message = null;
            $transaction = array(
                'id_transaction' => pSQL($payment_notification['payment_id']),
                'transaction_id' => pSQL($payment_notification['order_id']),
                'total_paid' => (float)($payment_notification['amount']['value'] / 100),
                'currency' => pSQL($payment_notification['amount']['currency']),
                'payment_date' => pSQL(Tools::substr(str_replace('T', ' ', $payment_notification['event_date']), 0, 19)),
                'payment_status' => pSQL($payment_notification['success']),
            );

            // Select matching payment status
            if ($transaction['total_paid'] != $cart->getOrderTotal()) {
                $payment_status = (int) Configuration::get('PS_OS_ERROR');
                $message = $this->module->l('Price paid on Oyst is not the same that on PrestaShop.').'<br />';
            } elseif ($url_cart_hash != $cart_hash) {
                $payment_status = (int) Configuration::get('PS_OS_ERROR');
                $message = $this->module->l('Cart changed, please retry.').'<br />';
            } else {
                $payment_status = (int) Configuration::get('PS_OS_PAYMENT');
                $message = $this->module->l('Payment accepted.').'<br />';
            }

            // Set shop
            if (_PS_VERSION_ < '1.5') {
                $shop = null;
            } else {
                $shop_id = $this->context->shop->id;
                $shop = new Shop($shop_id);
            }
        } else {
            $payment_status = (int) Configuration::get('PS_OS_ERROR');
            $message = $this->module->l('Oyst payment failed.').'<br />';
        }

        // Validate order
        $this->module->validateOrder($cart->id, $payment_status, $transaction['total_paid'], $this->module->displayName, $message, $transaction, $cart->id_currency, false, $this->context->customer->secure_key, $shop);
        $id_order = Order::getOrderByCartId($cart->id);

        $insert   = array(
            'id_order'   => (int) $id_order,
            'id_cart'    => (int) $cart->id,
            'payment_id' => pSQL($payment_notification['payment_id']),
            'event_code' => pSQL($payment_notification['event_code']),
            'event_data' => pSQL(Tools::jsonEncode($event_data)),
            'date_event' => pSQL(Tools::substr(str_replace('T', ' ', $payment_notification['event_date']), 0, 19)),
            'date_add'   => date('Y-m-d H:i:s'),
        );
        Db::getInstance()->insert('oyst_payment_notification', $insert);
        $this->module->log('Payment notification received');
        $this->module->logNotification('Payment', $_GET);
    }
}