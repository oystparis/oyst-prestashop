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

/**
 * Class OrderRepository
 */
class OrderRepository extends AbstractOystRepository
{
    public function orderCanBeCancelled($idCart, $currentState)
    {
        // The order must have an AUTHORISATION event and no CAPTURE/CANCELLATION event
        $sql = 'SELECT COUNT(DISTINCT(opn.`id_oyst_payment_notification`))'
            .' FROM `'._DB_PREFIX_.'oyst_payment_notification` opn'
            .' WHERE opn.`id_cart` = '.(int) $idCart
            .' AND opn.`event_code` = "'. OystPaymentNotification::EVENT_AUTHORISATION.'"'
            .' AND opn.`id_cart` NOT IN ('
                .'SELECT opn_bis.`id_cart`'
                .' FROM `'._DB_PREFIX_.'oyst_payment_notification` opn_bis'
                .' WHERE opn_bis.`event_code` = "'.OystPaymentNotification::EVENT_CAPTURE.'"'
                .' OR opn_bis.`event_code` = "'.OystPaymentNotification::EVENT_CANCELLATION.'"'
            .')';

        $result = $this->db->getValue($sql);

        return $result > 0 && $currentState != Configuration::get('OYST_STATUS_CANCELLATION_PENDING');
    }

    public function orderCanBeTotallyRefunded($idCart, $currentState)
    {
        // The order must have a CAPTURE event but no REFUND/CANCELLATION event
        $sql = 'SELECT COUNT(DISTINCT(opn.`id_oyst_payment_notification`))'
            .' FROM `'._DB_PREFIX_.'oyst_payment_notification` opn'
            .' WHERE opn.`id_cart` = '.(int) $idCart
            .' AND opn.`event_code` = "'.OystPaymentNotification::EVENT_CAPTURE.'"'
            .' AND opn.`id_cart` NOT IN ('
                .'SELECT opn_bis.`id_cart`'
                .' FROM `'._DB_PREFIX_.'oyst_payment_notification` opn_bis'
            .' WHERE opn_bis.`event_code` = "'.OystPaymentNotification::EVENT_REFUND.'"'
                .' OR opn_bis.`event_code` = "'.OystPaymentNotification::EVENT_CANCELLATION.'"'
            .')';

        $result = $this->db->getValue($sql);

        return $result > 0 && $currentState != Configuration::get('OYST_STATUS_PARTIAL_REFUND_PEND') && $currentState != Configuration::get('OYST_STATUS_REFUND_PENDING');
    }

    public function calculateOrderMaxRefund($idCart)
    {
        $maxRefund = 0;

        // The order must have a CAPTURE event and no CANCELLATION event
        // NB: this is almost a duplicate of the getOrderMaxRefund method
        // because from FreePay BO we allow many partial refund but not on Prestashop
        $sql = 'SELECT opn.`event_data`'
            .' FROM `'._DB_PREFIX_.'oyst_payment_notification` opn'
            .' WHERE opn.`id_cart` = '.(int) $idCart
            .' AND opn.`event_code` = "'.OystPaymentNotification::EVENT_CAPTURE.'"'
            .' AND opn.`id_cart` NOT IN ('
                .'SELECT opn_bis.`id_cart`'
                .' FROM `'._DB_PREFIX_.'oyst_payment_notification` opn_bis'
                .' WHERE opn_bis.`event_code` = "'.OystPaymentNotification::EVENT_CANCELLATION.'"'
            .')';

        // Return data of the CAPTURE event
        $result = $this->db->getValue($sql);

        if ($result) {
            $result      = json_decode($result, true);
            $totalAmount = $result['notification']['amount']['value'] / 100;
            $maxRefund   = $this->calculateMaxRefund($idCart, $totalAmount);
        }

        return $maxRefund;
    }

    public function getOrderMaxRefund($idCart, $currentState)
    {
        $maxRefund = 0;

        // The order must have a CAPTURE event and no CANCELLATION event
        // NB: the EVENT_REFUND was added to prevent multiple partial refund (due to a bug)
        // so it should be temporary
        $sql = 'SELECT opn.`event_data`'
            .' FROM `'._DB_PREFIX_.'oyst_payment_notification` opn'
            .' WHERE opn.`id_cart` = '.(int) $idCart
            .' AND opn.`event_code` = "'.OystPaymentNotification::EVENT_CAPTURE.'"'
            .' AND opn.`id_cart` NOT IN ('
                .'SELECT opn_bis.`id_cart`'
                .' FROM `'._DB_PREFIX_.'oyst_payment_notification` opn_bis'
                .' WHERE opn_bis.`event_code` = "'.OystPaymentNotification::EVENT_CANCELLATION.'"'
                .' OR opn_bis.`event_code` = "'.OystPaymentNotification::EVENT_REFUND.'"'
            .')';

        // Return data of the CAPTURE event
        $result = $this->db->getValue($sql);

        if ($result) {
            $result      = json_decode($result, true);
            $totalAmount = $result['notification']['amount']['value'] / 100;
            $maxRefund   = $this->calculateMaxRefund($idCart, $totalAmount);
        }

        if ($currentState == Configuration::get('OYST_STATUS_PARTIAL_REFUND_PEND') || $currentState == Configuration::get('OYST_STATUS_REFUND_PENDING')) {
            $maxRefund = 0;
        }

        return $maxRefund;
    }

    public function calculateMaxRefund($idCart, $totalAmount)
    {
        $maxRefund = $totalAmount;

        $sql = 'SELECT opn.`event_data`'
            .' FROM `'._DB_PREFIX_.'oyst_payment_notification` opn'
            .' WHERE opn.`id_cart` = '.(int) $idCart
            .' AND opn.`event_code` = "'.OystPaymentNotification::EVENT_REFUND.'"';

        $result = $this->db->query($sql);

        while ($row = $this->db->nextRow($result)) {
            $data       = json_decode($row['event_data'], true);
            $maxRefund -= $data['notification']['amount']['value'] / 100;
        }

        return $maxRefund;
    }

    public function getAmountToRefund($order, $tabAccess)
    {
        if (version_compare(_PS_VERSION_, '1.6.0') >= 0) {
            $amountToRefund = $this->getAmountToRefundRecentVersion($tabAccess, $order);
        } else {
            $amountToRefund = $this->getAmountToRefundOldVersion($tabAccess);
        }

        return $amountToRefund;
    }

    private function getAmountToRefundRecentVersion($tabAccess, $order)
    {
        if ($tabAccess['edit'] != '1') {
            return 0;
        }

        $refunds = Tools::getValue('partialRefundProduct');

        if (!(Tools::isSubmit('partialRefundProduct') && $refunds && is_array($refunds))) {
            return 0;
        }

        $amount = 0;
        $order_detail_list = array();
        $full_quantity_list = array();
        foreach ($refunds as $id_order_detail => $amount_detail) {
            $quantity = Tools::getValue('partialRefundProductQuantity');
            if (!$quantity[$id_order_detail]) {
                continue;
            }

            $full_quantity_list[$id_order_detail] = (int)$quantity[$id_order_detail];

            $order_detail_list[$id_order_detail] = array(
                'quantity' => (int)$quantity[$id_order_detail],
                'id_order_detail' => (int)$id_order_detail
            );

            $order_detail = new OrderDetail((int)$id_order_detail);
            if (empty($amount_detail)) {
                $order_detail_list[$id_order_detail]['unit_price'] = (!Tools::getValue('TaxMethod') ? $order_detail->unit_price_tax_excl : $order_detail->unit_price_tax_incl);
                $order_detail_list[$id_order_detail]['amount'] = $order_detail->unit_price_tax_incl * $order_detail_list[$id_order_detail]['quantity'];
            } else {
                $order_detail_list[$id_order_detail]['amount'] = (float)str_replace(',', '.', $amount_detail);
                $order_detail_list[$id_order_detail]['unit_price'] = $order_detail_list[$id_order_detail]['amount'] / $order_detail_list[$id_order_detail]['quantity'];
            }
            $amount += $order_detail_list[$id_order_detail]['amount'];
        }

        $shipping_cost_amount = (float)str_replace(',', '.', Tools::getValue('partialRefundShippingCost')) ? (float)str_replace(',', '.', Tools::getValue('partialRefundShippingCost')) : false;

        if ($amount == 0 && $shipping_cost_amount == 0) {
            return 0;
        }

        if ((int)Tools::getValue('refund_voucher_off') == 1) {
            $amount -= (float)Tools::getValue('order_discount_price');
        } elseif ((int)Tools::getValue('refund_voucher_off') == 2) {
            $amount = (float)Tools::getValue('refund_voucher_choose');
        }

        if ($shipping_cost_amount > 0) {
            if (!Tools::getValue('TaxMethod')) {
                $tax = new Tax();
                $tax->rate = $order->carrier_tax_rate;
                $tax_calculator = new TaxCalculator(array($tax));
                $amount += $tax_calculator->addTaxes($shipping_cost_amount);
            } else {
                $amount += $shipping_cost_amount;
            }
        }

        if ($amount > 0) {
            if (Tools::isSubmit('generateDiscountRefund')) {
                $amount = 0;
            }

            return $amount;
        } else {
            return 0;
        }
    }

    private function getAmountToRefundOldVersion($tabAccess)
    {
        if ($tabAccess['edit'] != '1' || !is_array(Tools::getValue('partialRefundProduct'))) {
            return 0;
        }

        $amount = 0;
        $order_detail_list = array();
        foreach (Tools::getValue('partialRefundProduct') as $id_order_detail => $amount_detail) {
            $order_detail_list[$id_order_detail]['quantity'] = (int)Tools::getValue('partialRefundProductQuantity')[$id_order_detail];

            if (empty($amount_detail)) {
                $order_detail = new OrderDetail((int)$id_order_detail);
                $order_detail_list[$id_order_detail]['amount'] = $order_detail->unit_price_tax_incl * $order_detail_list[$id_order_detail]['quantity'];
            } else {
                $order_detail_list[$id_order_detail]['amount'] = (float)$amount_detail;
            }

            $amount += $order_detail_list[$id_order_detail]['amount'];
        }

        $shipping_cost_amount = (float)str_replace(',', '.', Tools::getValue('partialRefundShippingCost'));
        if ($shipping_cost_amount > 0) {
            $amount += $shipping_cost_amount;
        }

        if ($amount <= 0 || Tools::isSubmit('generateDiscountRefund')) {
            return 0;
        }

        return $amount > 0 ? $amount : 0;
    }
}
