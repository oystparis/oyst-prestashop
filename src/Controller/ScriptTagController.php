<?php

namespace Oyst\Controller;

use Configuration;

class ScriptTagController extends AbstractOystController
{
    public function __construct()
    {
        parent::__construct();
        $this->setLogName('script-tag');
    }

    public function setUrl($params)
    {
        if (Configuration::updateValue('FC_OYST_SCRIPT_TAG_URL', $params['data']['url'])) {
            $this->respondAsJson('OK');
        } else {
            $this->respondError(400, "Error on update");
        }
    }
}
