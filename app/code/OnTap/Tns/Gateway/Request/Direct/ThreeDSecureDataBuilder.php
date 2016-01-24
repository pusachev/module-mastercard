<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace OnTap\Tns\Gateway\Request\Direct;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Framework\UrlInterface;

class ThreeDSecureDataBuilder extends CardDataBuilder implements BuilderInterface
{
    const PAGE_GENERATION_MODE = 'SIMPLE';
    const PAGE_ENCODING = 'UTF_8';
    const OPERATION = 'CHECK_3DS_ENROLLMENT';
    const RESPONSE_URL = 'tns/threedsecure/reponse';

    /**
     * @var UrlInterface
     */
    protected $urlHelper;

    /**
     * ThreeDSecureDataBuilder constructor.
     * @param UrlInterface $urlHelper
     */
    public function __construct(UrlInterface $urlHelper)
    {
        $this->urlHelper = $urlHelper;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder();
        $payment = $paymentDO->getPayment();
        ContextHelper::assertOrderPayment($payment);

        return [
            'apiOperation' => self::OPERATION,
            '3DSecure' => [
                'authenticationRedirect' => [
                    'pageGenerationMode' => self::PAGE_GENERATION_MODE,
                    'responseUrl' => $this->urlHelper->getUrl(self::RESPONSE_URL),
                    'simple' => [
                        'expectedHtmlEncoding' => self::PAGE_ENCODING
                    ]
                ]
            ],
            'order' => [
                'amount' => sprintf('%.2F', SubjectReader::readAmount($buildSubject)),
                'currency' => $order->getCurrencyCode(),
            ],
            'sourceOfFunds' => [
                'provided' => [
                    'card' => [
                        'expiry' => [
                            'month' => $this->formatMonth(
                                $payment->getAdditionalInformation(CardDataBuilder::CC_EXP_MONTH)
                            ),
                            'year' => $this->formatYear(
                                $payment->getAdditionalInformation(CardDataBuilder::CC_EXP_YEAR)
                            ),
                        ],
                        'number' => $payment->getAdditionalInformation(CardDataBuilder::CC_NUMBER),
                    ],
                ],
            ],
        ];
    }
}