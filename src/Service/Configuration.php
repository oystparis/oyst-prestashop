<?php

namespace Oyst\Service;

class Configuration
{
    const ONE_CLICK_FEATURE_STATE = 'OYST_ONE_CLICK_FEATURE_STATE';
    const ONE_CLICK_FEATURE_ENABLE = 1;
    const ONE_CLICK_FEATURE_DISABLE = 0;
    const ONE_CLICK_CARRIER = 'OYST_ONE_CLICK_CARRIER';

    const API_ENV = 'OYST_API_ENV';
    const API_ENV_PROD = 'prod';
    const API_ENV_PREPROD = 'preprod';
    const API_ENV_CUSTOM = 'custom';
    const API_KEY_PROD_FREEPAY = 'OYST_API_PROD_KEY_FREEPAY';
    const API_KEY_PREPROD_FREEPAY = 'OYST_API_PREPROD_KEY_FREEPAY';
    const API_KEY_CUSTOM_FREEPAY = 'OYST_API_CUSTOM_KEY_FREEPAY';
    const API_KEY_PROD_ONECLICK = 'OYST_API_PROD_KEY_ONECLICK';
    const API_KEY_PREPROD_ONECLICK = 'OYST_API_PREPROD_KEY_ONECLICK';
    const API_KEY_CUSTOM_ONECLICK = 'OYST_API_CUSTOM_KEY_ONECLICK';
    const API_ENDPOINT_CUSTOM = 'OYST_API_CUSTOM_ENDPOINT';

    const CATALOG_EXPORT_STATE = 'OYST_CATALOG_EXPORT_STATE';
    const CATALOG_EXPORT_RUNNING = 1;
    const CATALOG_EXPORT_DONE = 0;

    const DISPLAY_ADMIN_INFO_STATE = 'OYST_DISPLAY_ADMIN_INFO_STATE';
    const DISPLAY_ADMIN_INFO_ENABLE = 1;
    const DISPLAY_ADMIN_INFO_DISABLE = 0;

    const REQUESTED_CATALOG_DATE = 'OYST_REQUESTED_CATALOG_DATE';
}
