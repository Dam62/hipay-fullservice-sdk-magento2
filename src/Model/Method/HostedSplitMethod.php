<?php
/**
 * HiPay Fullservice Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Apache 2.0 Licence
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * @copyright      Copyright (c) 2016 - HiPay
 * @license        http://www.apache.org/licenses/LICENSE-2.0 Apache 2.0 Licence
 *
 */
namespace HiPay\FullserviceMagento\Model\Method;


use Magento\Framework\Exception\LocalizedException;
use \HiPay\FullserviceMagento\Model\Gateway\Factory as GatewayManagerFactory;
use HiPay\FullserviceMagento\Model\HostedMethod;


/**
 * Class Hosted Split Payment Method
 * 
 * @package HiPay\FullserviceMagento
 * @author Kassim Belghait <kassim@sirateck.com>
 * @copyright Copyright (c) 2016 - HiPay
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache 2.0 Licence
 * @link https://github.com/hipay/hipay-fullservice-sdk-magento2
 * 
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class HostedSplitMethod extends HostedMethod {
	
	const HIPAY_METHOD_CODE               = 'hipay_hostedsplit';
	
	/**
	 * @var string
	 */
	protected $_code = self::HIPAY_METHOD_CODE;	
	
	/**
	 * 
	 * @var \HiPay\FullserviceMagento\Model\PaymentProfileFactory $profilefactory
	 */
	protected $profileFactory;
	
	/**
	 * Payment Method feature
	 *
	 * @var bool
	 */
	protected $_canUseInternal = false;

	
	/**
	 * 
	 * @param \Magento\Framework\Model\Context $context
	 * @param \Magento\Framework\Registry $registry
	 * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
	 * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
	 * @param \Magento\Payment\Helper\Data $paymentData
	 * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
	 * @param \Magento\Payment\Model\Method\Logger $logger
	 * @param GatewayManagerFactory $gatewayManagerFactory
	 * @param \Magento\Framework\Url $urlBuilder
	 * @param \HiPay\FullserviceMagento\Model\Email\Sender\FraudDenySender $fraudDenySender
	 * @param \HiPay\FullserviceMagento\Model\Email\Sender\FraudAcceptSender $fraudAcceptSender
	 * @param \HiPay\FullserviceMagento\Model\Config\Factory $configFactory
	 * @param \Magento\Checkout\Model\Session $checkoutSession
	 * @param \HiPay\FullserviceMagento\Model\CardFactory $cardFactory
	 * @param \HiPay\FullserviceMagento\Model\PaymentProfileFactory $profileFactory
	 * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
	 * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
	 * @param array $data
	 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
	 */
	public function __construct(
			\Magento\Framework\Model\Context $context,
			\Magento\Framework\Registry $registry,
			\Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
			\Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
			\Magento\Payment\Helper\Data $paymentData,
			\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
			\Magento\Payment\Model\Method\Logger $logger,
			GatewayManagerFactory $gatewayManagerFactory,
			\Magento\Framework\Url $urlBuilder,
			\HiPay\FullserviceMagento\Model\Email\Sender\FraudDenySender $fraudDenySender,
			\HiPay\FullserviceMagento\Model\Email\Sender\FraudAcceptSender $fraudAcceptSender,
			\HiPay\FullserviceMagento\Model\Config\Factory $configFactory,
			\Magento\Checkout\Model\Session $checkoutSession,
			\HiPay\FullserviceMagento\Model\CardFactory $cardFactory,
			\HiPay\FullserviceMagento\Model\PaymentProfileFactory $profileFactory,
			\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
			\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
			array $data = []
			) {
				parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, 
									$paymentData, $scopeConfig, $logger, $gatewayManagerFactory,
									$urlBuilder,$fraudDenySender,$fraudAcceptSender,$configFactory,$checkoutSession,$cardFactory,$resource,$resourceCollection,$data);
				
			$this->profileFactory = $profileFactory;

	}
	
	
	protected function getAddtionalInformationKeys(){
		return array_merge(['profile_id'],$this->_additionalInformationKeys);
	}
	
	protected function _setHostedUrl(\Magento\Sales\Model\Order $order){
	
		$payment = $order->getPayment();
		$profileId = $payment->getAdditionalInformation('profile_id');
		$profile = $this->getProfile($profileId);
	
		$splitAmounts = $profile->splitAmount($payment->getOrder()->getBaseGrandTotal());
	
		if(!is_array($splitAmounts) || !count($splitAmounts)){
			throw new LocalizedException(__('Impossible to split the amount.'));
		}
		$firstSplit = current($splitAmounts);
		$payment->getOrder()->setForcedAmount((float)$firstSplit['amountToPay']);
	
		if($payment->getAdditionalInformation('card_token') != ""){
			$this->place($order->getPayment());
		}
		else{
			//Create gateway manager with order data
			$gateway = $this->_gatewayManagerFactory->create($order);
			//Call fullservice api to get hosted page url
			$hppModel = $gateway->requestHostedPaymentPage();
			$order->getPayment()->setAdditionalInformation('redirectUrl',$hppModel->getForwardUrl());
		}
	
	}
	
	protected function manualCapture(\Magento\Payment\Model\InfoInterface $payment, $amount){
		//Check if it's split payment
		//If true change captured amount
		if($payment->getAdditionalInformation('profile_id')){
			$profileId = $payment->getAdditionalInformation('profile_id');
			$profile = $this->getProfile($profileId);
	
			$splitAmounts = $profile->splitAmount($payment->getOrder()->getBaseGrandTotal());
			if(!is_array($splitAmounts) || !count($splitAmounts)){
				throw new LocalizedException(__('Impossible to split the amount.'));
			}
			$firstSplit = current($splitAmounts);
			$amount = (float)$firstSplit['amountToPay'];
	
		}
	
		return parent::manualCapture($payment, $amount);
	
	
	}
	
	/**
	 *
	 * @param int $profileId
	 * @throws LocalizedException
	 * @return \HiPay\FullserviceMagento\Model\PaymentProfile
	 */
	protected function getProfile($profileId){
	
		if(empty($profileId)){
			throw new LocalizedException(__('Payment Profile not found.'));
		}
		$profile = $this->profileFactory->create()->load($profileId);
		if(!$profile->getId()){
			throw new LocalizedException(__('Payment Profile not found.'));
		}
	
		return $profile;
	}

	
}