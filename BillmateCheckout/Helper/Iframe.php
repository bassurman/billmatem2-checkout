<?php

namespace Billmate\BillmateCheckout\Helper;

class Iframe extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Quote\Model\Quote\Address\Rate
     */
    protected $shippingRate;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var float
     */
	protected $shippingPrice;

    /**
     * @var bool
     */
    protected $_updateProcessRun = false;

    /**
     * @var string
     */
    protected $_apiCallMethod = 'initCheckout';

    /**
     * @var \Billmate\BillmateCheckout\Helper\Data
     */
	protected $dataHelper;

    /**
     * @var array
     */
	protected $defaultAddress = [
        'firstname' => 'Testperson',
        'lastname' => 'Approved',
        'street' => 'Teststreet',
        'city' => 'Testcity',
        'country_id' => 'SE',
        'postcode' => '12345',
        'telephone' => '0700123456'
    ];

    /**
     * Iframe constructor.
     *
     * @param \Magento\Framework\App\Helper\Context      $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Quote\Model\Quote\Address\Rate    $shippingRate
     * @param \Magento\Checkout\Model\Session            $_checkoutSession
     * @param Config                                     $configHelper
     * @param Data                                       $dataHelper
     * @param \Billmate\BillmateCheckout\Model\Api\Billmate          $billmateProvider
     * @param \Magento\Tax\Model\CalculationFactory      $taxCalculation
     */
    public function __construct(
		\Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Quote\Model\Quote\Address\Rate $shippingRate,
		\Magento\Checkout\Model\Session $_checkoutSession,
        \Billmate\BillmateCheckout\Helper\Config $configHelper,
        \Billmate\BillmateCheckout\Helper\Data $dataHelper,
        \Billmate\BillmateCheckout\Model\Api\Billmate $billmateProvider,
        \Magento\Tax\Model\CalculationFactory $taxCalculation
	){
        $this->_storeManager = $storeManager;
        $this->shippingRate = $shippingRate;
        $this->checkoutSession = $_checkoutSession;
        $this->billmateProvider = $billmateProvider;
        $this->configHelper = $configHelper;
        $this->dataHelper = $dataHelper;
        $this->taxCalculation = $taxCalculation;

        parent::__construct($context);
    }

    /**
     * @return array|mixed
     */
    public function getIframeData()
    {
        $bmRequestData = $this->prepareRequestData();
        $method = $this->getApiMethod();

        $response = $this->billmateProvider->call(
            $method,
            $bmRequestData
        );

        if (isset($response['number'])) {
            $this->setSessionData('billmate_checkout_id', $response['number']);
        }

        return $response;
	}

    /**
     * @return array
     */
	protected function prepareRequestData()
    {
        $this->dataHelper->prepareCheckout();
        $this->runCheckIsUpdateCheckout();

        $quoteAddress = $this->dataHelper->getQuote()->getShippingAddress();
        $lShippingPrice = $quoteAddress->getShippingAmount();

        $this->shippingRate->setCode($quoteAddress->getShippingMethod());
        $this->shippingPrice = $lShippingPrice;

        $this->setSessionData('shippingPrice', $lShippingPrice);
        $this->setSessionData('shipping_code', $quoteAddress->getShippingMethod());
        $this->setSessionData('billmate_shipping_tax', $quoteAddress->getShippingTaxAmount());

        if (empty($this->getQuote()->getReservedOrderId())) {
            $this->getQuote()->reserveOrderId()->save();
        }

        $data = $this->getRequestData();

        $itemsData = $this->getItemsData();
        $data['Articles'] = array_merge($data['Articles'], $itemsData);

        $shippingAddressTotal = $this->getQuote()->getShippingAddress();
        $shippingTaxRate = $this->getShippingTaxRate();

        $data['Cart'] = [
            'Shipping' => [
                'withouttax' => $this->toCents($shippingAddressTotal->getShippingAmount()),
                'taxrate' => $shippingTaxRate,
                'withtax' => $this->toCents($shippingAddressTotal->getShippingInclTax()),
            ],
            'Total' => [
                'withouttax' => $this->toCents($shippingAddressTotal->getGrandTotal() - $shippingAddressTotal->getTaxAmount()),
                'tax' => $this->toCents($shippingAddressTotal->getTaxAmount()),
                'rounding' => $this->toCents(0),
                'withtax' => $this->toCents($shippingAddressTotal->getGrandTotal()),
            ]
        ];

        return $data;
    }


    /**
     * @return array
     */
	protected function getItemsData()
    {
        $itemsData = [];
        $itemsVisible = $this->getQuote()->getAllVisibleItems();

        foreach ($itemsVisible as $item) {
            $itemsData[] = [
                'quantity' => $item->getQty(),
                'artnr' => $item->getSku(),
                'title' => $item->getName(),
                'aprice' => $this->toCents($item->getPriceInclTax()),
                'taxrate' => $item->getTaxPercent(),
                'discount' => ($item->getDiscountPercent()),
                'withouttax' => $this->toCents($item->getRowTotal())
            ];
        }

        return $itemsData;
    }

    /**
     * @return string
     */
    public function updateIframe()
    {
        $response = $this->getIframeData();

        if(isset($response['url'])) {
            return $response['url'];
        }
        return '';
    }

    /**
     * @return array
     */
    protected function getRequestData()
    {
        $data = [];
        $data['PaymentData'] = [
            'currency' => 'SEK',
            'language' => 'sv',
            'country' => 'SE',
            'orderid' => $this->getQuote()->getReservedOrderId(),
        ];

        if (!$this->_updateProcessRun) {
            $data['PaymentData']['callbackurl'] = $this->_getUrl('billmatecheckout/callback/callback');
            $data['PaymentData']['accepturl'] = $this->_getUrl('billmatecheckout/success/success/');
            $data['PaymentData']['cancelurl'] = $this->_getUrl('billmatecheckout');

            $data['CheckoutData'] = [
                'windowmode' => 'iframe',
                'sendreciept' => 'yes',
                'terms' => $this->configHelper->getTermsURL(),
                'redirectOnSuccess'=>'true',

            ];

            $privacyPolicyURL = $this->configHelper->getPPURL();
            if ($privacyPolicyURL) {
                $data['CheckoutData']['privacyPolicy'] = $privacyPolicyURL;
            }
        }

        $billmateCheckoutId = $this->getBillmateCheckoutId();
        if ($billmateCheckoutId) {
            $data['PaymentData']['number'] = $billmateCheckoutId;
        }

        $shippingAddressTotal = $this->getQuote()->getShippingAddress();
        $data['Articles'] = [
            [
                'quantity' => '1',
                'artnr' => 'shipping_code',
                'title' => $shippingAddressTotal->getShippingMethod(),
                'aprice' => '0',
                'taxrate' => '0',
                'discount' => '0',
                'withouttax' => '0'

            ]
        ];

        $discountAmount = $shippingAddressTotal->getDiscountAmount();
        if ($discountAmount) {
            $data['Articles'][] = [
                'quantity' => '1',
                'artnr' => 'discount_code',
                'title' => $shippingAddressTotal->getCouponCode()?
                    $shippingAddressTotal->getCouponCode():
                    __('Discount rules ids: ') . $shippingAddressTotal->getAppliedRuleIds(),
                'aprice' => $this->toCents($discountAmount),
                'taxrate' => '0',
                'discount' => '0',
                'withouttax' => $this->toCents($discountAmount)
            ];
        }

        return $data;
    }

    /**
     * @return int | null
     */
    protected function getBillmateCheckoutId()
    {
        return $this->getSessionData('billmate_checkout_id');
    }


    /**
     * @return $this
     */
    protected function runCheckIsUpdateCheckout()
    {
        if ($this->getBillmateCheckoutId()) {
            $this->_updateProcessRun = true;
            $this->_apiCallMethod = 'updateCheckout';
        }
        return $this;
    }

    /**
     * @return string
     */
    protected function getApiMethod()
    {
        return $this->_apiCallMethod;
    }

    /**
     * @param $key
     * @param $value
     *
     * @return \Magento\Checkout\Model\Session
     */
    protected function setSessionData($key, $value)
    {
        return $this->dataHelper->setSessionData($key, $value);
    }

    /**
     * @param $key
     * @param $value
     *
     * @return mixed
     */
    protected function getSessionData($key)
    {
        return $this->dataHelper->getSessionData($key);
    }

    /**
     * @return \Magento\Quote\Model\Quote
     */
    protected function getQuote()
    {
        return $this->dataHelper->getQuote();
    }

    /**
     * @param $price
     *
     * @return int
     */
    protected function toCents($price)
    {
        return $this->dataHelper->priceToCents($price);
    }

    /**
     * @return  \Magento\Tax\Model\Calculation
     */
    protected function getTaxCalculation()
    {
        return $this->taxCalculation->create();
    }

    /**
     * @return float
     */
    protected function getShippingTaxRate()
    {
        $currentStore = $this->_storeManager->getStore();
        $currentStoreId = $currentStore->getId();
        $taxCalculation = $this->getTaxCalculation();
        $request = $taxCalculation->getRateRequest(null, null, null, $currentStoreId);
        $shippingTaxClass = $this->configHelper->getShippingTaxClass();
        return $taxCalculation->getRate($request->setProductClassId($shippingTaxClass));
    }
}
