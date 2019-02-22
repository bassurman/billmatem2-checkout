<?php
namespace Billmate\BillmateCheckout\Model;

class Order
{
    const BM_ADDITIONAL_INFO_CODE = 'bm_payment_method';

    /**
     * @var array
     */
    protected $orderData;

    /**
     * Order constructor.
     *
     * @param \Magento\Store\Model\StoreManagerInterface                 $storeManager
     * @param \Magento\Customer\Model\CustomerFactory                    $customerFactory
     * @param \Magento\Customer\Api\CustomerRepositoryInterface          $customerRepository
     * @param \Magento\Quote\Api\CartRepositoryInterface                 $cartRepositoryInterface
     * @param \Magento\Quote\Api\CartManagementInterface                 $cartManagementInterface
     * @param \Magento\Quote\Model\Quote\Address\Rate                    $shippingRate
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender        $orderSender
     * @param \Magento\Quote\Model\ResourceModel\Quote\CollectionFactory $quoteCollectionFactory
     * @param \Billmate\BillmateCheckout\Helper\Data                     $dataHelper
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepositoryInterface,
        \Magento\Quote\Api\CartManagementInterface $cartManagementInterface,
        \Magento\Quote\Model\Quote\Address\Rate $shippingRate,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Quote\Model\ResourceModel\Quote\CollectionFactory $quoteCollectionFactory,
        \Billmate\BillmateCheckout\Helper\Data $dataHelper
    ){
        $this->_storeManager = $storeManager;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->cartManagementInterface = $cartManagementInterface;
        $this->shippingRate = $shippingRate;
        $this->orderSender = $orderSender;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->dataHelper = $dataHelper;
    }

    /**
     * @param        $orderData
     * @param string $orderId
     * @param string $paymentID
     *
     * @return int
     */
    public function create($orderId = '')
    {
        try {

            if (!$this->getOrderData()) {
                throw new \Exception('The request does not contain order data');
            }

            if ($orderId == '') {
                $orderId = $this->dataHelper->getQuote()->getReservedOrderId();
            }

            $exOrder = $this->dataHelper->getOrderByIncrementId($orderId);
            if ($exOrder->getIncrementId()){
                return;
            }

            $actualCart = $this->createCart($orderId);

            $orderId = $this->cartManagementInterface->placeOrder($actualCart->getId());

            $order = $this->dataHelper->getOrderById($orderId);

            $this->orderSender->send($order);

            $this->dataHelper->setSessionData('bm-inc-id', $order->getIncrementId());

            $orderState = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
            $order->setState($orderState)->setStatus($orderState);
            $order->save();

            return $orderId;
        } catch (\Exception $e){
            $this->dataHelper->addLog([
                'Could not create order',
                '__FILE__' => __FILE__,
                '__CLASS__' => __CLASS__,
                '__FUNCTION__' => __FUNCTION__,
                '__LINE__' => __LINE__,
                'exception.message' => $e->getMessage(),
                'exception.file' => $e->getFile(),
                'exception.line' => $e->getLine(),
            ]);
            return 0;
        }
    }

    /**
     * @param $orderId
     * @param $customer
     *
     * @return mixed
     */
    protected function createQuote($orderId, $customer)
    {
        $billmateShippingAddress = $this->dataHelper->getSessionData('billmate_shipping_address');
        $billmateBillingAddress = $this->dataHelper->getSessionData('billmate_billing_address');
        $shippingCode = $this->dataHelper->getSessionData('shipping_code');

        $orderData = $this->getOrderData();

        $actual_quote = $this->quoteCollectionFactory->create()
            ->addFieldToFilter("reserved_order_id", $orderId)->getFirstItem();

        $store = $this->_storeManager->getStore();

        $actual_quote->setCustomerEmail($customer->getEmail());
        $actual_quote->setStore($store);
        $actual_quote->setCurrency();
        $actual_quote->assignCustomer($customer);

        if ($this->dataHelper->getSessionData('billmate_applied_discount_code')) {
            $discountCode = $this->dataHelper->getSessionData('billmate_applied_discount_code');
            $actual_quote->setCouponCode($discountCode);
        }

        $actual_quote->getBillingAddress()->addData($billmateBillingAddress);

        if ($billmateShippingAddress){
            $actual_quote->getShippingAddress()->addData($billmateShippingAddress);
        } else {
            $actual_quote->getShippingAddress()->addData($billmateBillingAddress);
        }

        $this->shippingRate->setCode($shippingCode)->getPrice();
        $shippingAddress = $actual_quote->getShippingAddress();

        $billmatePaymentMethod = $this->dataHelper->getPaymentMethod();
        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod($shippingCode);
        $actual_quote->getShippingAddress()->addShippingRate($this->shippingRate);
        $actual_quote->setPaymentMethod($billmatePaymentMethod);
        $actual_quote->getPayment()->setQuote($actual_quote);
        $actual_quote->getPayment()->importData([
            'method' => $billmatePaymentMethod,
        ]);
        $actual_quote->getPayment()->setAdditionalInformation(
            self::BM_ADDITIONAL_INFO_CODE, $orderData['payment_method_name']
        );

        $actual_quote->setReservedOrderId($orderId);
        $actual_quote->collectTotals();
        $actual_quote->save();
        return $actual_quote;
    }


    protected function createCart($orderId)
    {
        $billmateShippingAddress = $this->dataHelper->getSessionData('billmate_shipping_address');
        $billmateBillingAddress = $this->dataHelper->getSessionData('billmate_billing_address');

        $customer = $this->getCustomer($this->getOrderData());
        $actualQuote = $this->createQuote($orderId, $customer);

        $cart = $this->cartRepositoryInterface->get($actualQuote->getId());
        $cart->setCustomerEmail($customer->getEmail());
        $cart->getBillingAddress()->addData($billmateBillingAddress);
        if ($billmateShippingAddress){
            $cart->getShippingAddress()->addData($billmateShippingAddress);
        } else {
            $cart->getShippingAddress()->addData($billmateBillingAddress);
        }
        $cart->getBillingAddress()->setCustomerId($customer->getId());
        $cart->getShippingAddress()->setCustomerId($customer->getId());
        $cart->setCustomerId($customer->getId());
        $cart->assignCustomer($customer);
        $cart->save();
        return $cart;
    }

    /**
     * @param $orderData
     *
     * @return \Magento\Customer\Api\Data\CustomerInterface
     */
    protected function getCustomer($orderData)
    {
        $store = $this->_storeManager->getStore();
        $websiteId = $this->_storeManager->getStore()->getWebsiteId();

        $customer = $this->customerFactory->create();
        $customer->setWebsiteId($websiteId);
        $customer->loadByEmail($orderData['email']);

        $_password = str_pad($orderData['email'], 10, rand(111,999));
        if (!$customer->getEntityId()){
            $customer->setWebsiteId($websiteId)
                ->setStore($store)
                ->setFirstname($orderData['shipping_address']['firstname'])
                ->setLastname($orderData['shipping_address']['lastname'])
                ->setEmail($orderData['email'])
                ->setPassword($_password);
            $customer->save();
        }
        $customer->setEmail($orderData['email']);
        $customer->save();

        return $this->customerRepository->getById($customer->getEntityId());
    }

    /**
     * @param $orderData
     *
     * @return $this
     */
    public function setOrderData($orderData)
    {
        $this->orderData = $orderData;
        return $this;
    }

    /**
     * @param $orderData
     *
     * @return $this
     */
    public function getOrderData()
    {
        return $this->orderData;
    }
}