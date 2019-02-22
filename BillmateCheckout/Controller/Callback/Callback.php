<?php
namespace Billmate\BillmateCheckout\Controller\Callback;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\DB\TransactionFactory;

class Callback extends \Billmate\BillmateCheckout\Controller\FrontCore
{
    const COUNTRY_ID = 'se';

    /**
     * @var PageFactory
     */
	protected $resultPageFactory;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
	private $productRepository;

    /**
     * @var \Billmate\BillmateCheckout\Helper\Data
     */
	protected $helper;

    /**
     * @var \Billmate\BillmateCheckout\Helper\Config
     */
	protected $configHelper;

    /**
     * @var \Magento\Sales\Api\Data\OrderInterface
     */
	protected $orderInterface;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
	protected $invoiceService;

    /**
     * @var \Billmate\BillmateCheckout\Model\Api\Billmate
     */
    protected $billmateProvider;

    /**
     * @var TransactionFactory
     */
    protected $_transactionFactory;

    /**
     * @var \Billmate\BillmateCheckout\Model\Order
     */
    protected $orderModel;
	
	public function __construct(
	    Context $context,
        PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Billmate\BillmateCheckout\Helper\Data $_helper,
        \Billmate\BillmateCheckout\Helper\Config $configHelper,
		\Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Sales\Model\Service\InvoiceService $_invoiceService,
        \Billmate\BillmateCheckout\Model\Api\Billmate $billmateProvider,
        TransactionFactory $transactionFactory,
        \Billmate\BillmateCheckout\Model\Order $orderModel
    ){
		$this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
	    $this->productRepository = $productRepository;
		$this->invoiceService = $_invoiceService;
		$this->helper = $_helper;
		$this->configHelper = $configHelper;
		$this->orderInterface = $order;
        $this->billmateProvider = $billmateProvider;
        $this->_transactionFactory = $transactionFactory;
        $this->orderModel = $orderModel;

		parent::__construct($context);
	}
	
	public function execute()
    {
        $jsonResponse = $this->resultJsonFactory->create();
        $requestData = $this->getBmRequestData();
		$hash = $this->getHashCode($requestData);

		try{
            if ($hash != $requestData['credentials']['hash']) {
                throw new \Exception(
                    __('Invalid credentials hash.')
                );
            }
            $values = array(
                "number" => $requestData['data']['number']
            );
            $paymentInfo = $this->billmateProvider->getPaymentinfo($values);

            $order = $this->helper->getOrderByIncrementId($paymentInfo['PaymentData']['orderid']);
            if (!is_string($order->getIncrementId())) {
                $orderInfo = $this->getOrderInfo($paymentInfo);
                $order_id = $this->orderModel->setOrderData($orderInfo)->create($paymentInfo['PaymentData']['orderid']);
                if (!$order_id) {
                    throw new \Exception(
                        __('An error occurred on the server. Please try to place the order again.')
                    );
                }
                $order = $this->helper->getOrderById($order_id);
            }

            $order->setData('billmate_invoice_id', $requestData['data']['number']);
            if (
                $paymentInfo['PaymentData']['status'] == 'Created'||
                ($paymentInfo['PaymentData']['status'] == 'Paid')
            ) {
                $orderState = $this->helper->getApproveStatus();
            } elseif ($paymentInfo['PaymentData']['status'] == 'Pending') {
                $orderState = $this->helper->getPendingStatus();
            } else {
                $orderState = $this->helper->getDenyStatus();
            }
            $order->setState($orderState)->setStatus($orderState);
            $order->save();
            $respMessage = _('Order status successfully updated.');

        } catch(\Exception $exception) {
            $respMessage = $exception->getMessage();
        }
        return $jsonResponse->setData($respMessage);
	}

    /**
     * @param $customerAddress
     *
     * @return array
     */
	protected function processShippingAddress($customerAddress)
    {
        $billingAddressReq = $customerAddress['Billing'];
        $billingAddress = array(
            'firstname' => $billingAddressReq['firstname'],
            'lastname' => $billingAddressReq['lastname'],
            'street' => $billingAddressReq['street'],
            'city' => $billingAddressReq['city'],
            'country_id' => $billingAddressReq['country'],
            'postcode' => $billingAddressReq['zip'],
            'telephone' => $billingAddressReq['phone'],
            'email' =>$billingAddressReq['email']
        );

        if (
            isset($customerAddress['Shipping']) &&
            isset( $customerAddress['Shipping']['firstname'])
        ) {
            $shippingAddressReq = $customerAddress['Shipping'];
            $customerAddressData = array(
                'firstname' => $shippingAddressReq['firstname'],
                'lastname' => $shippingAddressReq['lastname'],
                'street' => $shippingAddressReq['street'],
                'city' => $shippingAddressReq['city'],
                'country_id' => $shippingAddressReq['country'],
                'postcode' => $shippingAddressReq['zip'],
                'telephone' => $shippingAddressReq['phone']
            );
        } else {
            $customerAddressData = $billingAddress ;
        }

        $this->helper->setBillingAddress($billingAddress);
        $this->helper->setShippingAddress($customerAddressData);

        return $customerAddressData;
    }

    /**
     * @param $paymentInfo
     *
     * @return array
     */
    protected function getOrderInfo($paymentInfo)
    {
        $customerAddressData = $this->processShippingAddress($paymentInfo['Customer']);
        $orderInfo = array(
            'currency_id'  => $paymentInfo['PaymentData']['currency'],
            'email'        => $customerAddressData['email'],
            'shipping_address' => $customerAddressData,
            'items' => array()
        );

        $articles = $paymentInfo['Articles'];
        foreach($articles as $article) {
            if ($article['artnr'] == 'discount_code') {
                $this->helper->setSessionData('billmate_applied_discount_code', $article['title']);
            } elseif ($article['artnr'] == 'shipping_code') {
                $this->helper->setShippingMethod($article['title']);
            } else {
                if (strpos($article['artnr'], "discount") === false) {
                    $orderInfo['items'][] = [
                        'product_id' => $article['artnr'],
                        'qty' => $article['quantity'],
                        'price' => (($article['withouttax']/$article['quantity'])/100)
                    ];
                }
            }
        }

        return $orderInfo;
    }

    /**
     * @param $requestData
     *
     * @return string
     */
    protected function getHashCode($requestData)
    {
        $hash = hash_hmac('sha512', json_encode($requestData['data']), $this->configHelper->getBillmateSecret());
        return $hash;
    }
}