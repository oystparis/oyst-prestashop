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

namespace Oyst\Service;

use Combination;
use Exception;
use Oyst\Classes\OystUser;
use Oyst\Classes\OneClickOrderParams;
use Oyst\Service\Http\CurrentRequest;
use Product;
use Validate;
use Oyst;
use Context;
use Currency;
use Configuration as ConfigurationP;
use Oyst\Factory\AbstractExportProductServiceFactory;

/**
 * Class Oyst\Service\OneClickService
 */
class OneClickService extends AbstractOystService
{
    const ONE_CLICK_VERSION = 2;

    /**
     * @param Product $product
     * @param $quantity
     * @param Combination|null $combination
     * @param OystUser|null $user
     * @return array
     * @throws Exception
     */
    public function authorizeNewOrder(Product $product, $quantity, Combination $combination = null, OystUser $user = null, $productLess = null, $orderParams = null, $context = null)
    {
        $response = $this->requester->call('authorizeOrder', array(
            $product->id,
            $quantity,
            Validate::isLoadedObject($combination) ? $combination->id : null,
            $user,
            self::ONE_CLICK_VERSION,
            $productLess,
            $orderParams,
            $context
        ));

        $apiClient = $this->requester->getApiClient();
        if ($apiClient->getLastHttpCode() == 200) {
            $result = array(
                'url' => $response['url'],
                'state' => true,
            );
        } else {
            $result = array(
                'error' => $apiClient->getLastError(),
                'state' => false,
            );
        }

        return $result;
    }

    /**
     * @param CurrentRequest $request
     * @return array
     */
    public function requestAuthorizeNewOrderProcess(CurrentRequest $request)
    {
        $data = array(
            'state' => false,
        );

        $product = null;
        $combination = null;
        $quantity = 0;

        if (!$request->hasRequest('oneClick')) {
            $data['error'] = 'Missing parameters';
        } elseif (!$request->hasRequest('productId')) {
            $data['error'] = 'Missing product';
        } elseif (!$request->hasRequest('productAttributeId')) {
            $data['error'] = 'Missing combination, even none selected';
        } elseif (!$request->hasRequest('quantity')) {
            $data['error'] = 'Missing quantity';
        }

        if (!isset($data['error'])) {
            $product = new Product($request->getRequestItem('productId'));
            if (!Validate::isLoadedObject($product)) {
                $data['error'] = 'Product can\'t be found';
            }

            if ($request->hasRequest('productAttributeId')) {
                $combinationId = (int) $request->getRequestItem('productAttributeId');
                if ($combinationId > 0) {
                    $combination = new Combination($request->getRequestItem('productAttributeId'));
                    if (!Validate::isLoadedObject($combination)) {
                        $data['error'] = 'Combination could not be found';
                    }
                }
            }

            $quantity = (int)$request->getRequestItem('quantity');
            if ($quantity <= 0) {
                $data['error'] = 'Bad quantity';
            }

            Context::getContext()->currency = new Currency(ConfigurationP::get('PS_CURRENCY_DEFAULT'));
            $exportProductService = AbstractExportProductServiceFactory::get(new Oyst(), Context::getContext());
            $productLess = $exportProductService->transformProductLess((int) $request->getRequestItem('productId'), (int) $request->getRequestItem('productAttributeId'));
        }

        if (isset($data['error'])) {
            $this->logger->critical(sprintf('Error occurred during oneClick process: %s', $data['error']));
        }

        if (!isset($data['error'])) {
            $oystUser = null;
            $customer = $this->context->customer;
            if ($customer->isLogged()) {
                $oystUser = (new OystUser())
                    ->setFirstName($customer->firstname)
                    ->setLastName($customer->lastname)
                    ->setLanguage($this->context->language->iso_code)
                    ->setEmail($customer->email);
            }

            if (!$productLess->isMaterialized()) {
               $oneClickOrdersParams = new OneClickOrderParams();
               $oneClickOrdersParams->setIsMaterialized($productLess->isMaterialized());
            }

            $result = $this->authorizeNewOrder($product, $quantity, $combination, $oystUser, $productLess, $oneClickOrdersParams);
            $data = array_merge($data, $result);
        }

        return $data;
    }
}
