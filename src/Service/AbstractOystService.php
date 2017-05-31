<?php

namespace Oyst\Service;

use Context;
use Guzzle\Http\Message\Response;
use Oyst;
use Oyst\Api\AbstractOystApiClient;
use Oyst\Service\Logger\AbstractLogger;
use Oyst\Service\Logger\LogLevel;
use Oyst\Service\Serializer\SerializerInterface;

/**
 * Class AbstractOystService
 * @package Oyst\Service
 */
abstract class AbstractOystService
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @var Oyst
     */
    protected $oyst;

    /** @var  AbstractLogger */
    protected $logger;

    /** @var  SerializerInterface */
    protected $serializer;

    /**
     * Oyst\Service\AbstractOystService constructor.
     * @param Context $context
     * @param Oyst $oyst
     */
    public function __construct(Context $context, Oyst $oyst)
    {
        $this->context = $context;
        $this->oyst = $oyst;
    }

    /**
     * @param AbstractOystApiClient $object
     * @param string $method
     * @param array $params
     * @return Response
     */
    protected function requestApi(AbstractOystApiClient $object, $method, $params = array())
    {
        /** @var Response $result */
        $result = call_user_func_array(array($object, $method), $params);

        if ($this->logger instanceof AbstractLogger) {

            $thisCallArgs = null == $params ? null : func_get_arg(2);

            $messageMask = 'Request from %s %s. HTTP[%s] BODY[%s]';
            $context = array(
                'objectType' => 'OystRequest'
            );
            $requestFrom = sprintf('%s::%s(%s)',
                get_class($object),
                $method,
                null == $thisCallArgs ? '' : (
                        $this->serializer instanceof SerializerInterface ?
                            $this->serializer->serialize($params) :
                            json_encode($params, JSON_OBJECT_AS_ARRAY)
                    )
            );

            if ($object->getLastHttpCode() == 200) {
                $messageState = 'Succeed';
                $method = LogLevel::INFO;
            } else {
                $messageState = 'Failed';
                $method = LogLevel::EMERGENCY;
            }

            $message = sprintf(
                $messageMask,
                $requestFrom,
                $messageState,
                $object->getLastHttpCode(),
                $object->getBody()
            );

            call_user_func(array($this->logger, $method),
                $message,
                $context
            );
        }

        return $result;
    }

    /**
     * @return Oyst
     */
    public function getOyst()
    {
        return $this->oyst;
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
     * @param SerializerInterface $serializer
     * @return $this
     */
    public function setSerializer($serializer)
    {
        $this->serializer = $serializer;

        return $this;
    }
}