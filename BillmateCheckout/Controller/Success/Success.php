<?php
namespace Billmate\BillmateCheckout\Controller\Success;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Context;
use \Magento\Checkout\Model\Session as CheckoutSession;
class Success extends \Billmate\BillmateCheckout\Controller\FrontCore
{

    /**
     * @var PageFactory
     */
	protected $resultPageFactory;

    /**
     * @var \Billmate\BillmateCheckout\Helper\Data
     */
	protected $helper;

    /**
     * @var CheckoutSession
     */
	protected $checkoutSession;

    /**
     * @var \Magento\Framework\Event\Manager
     */
	protected $eventManager;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \Billmate\BillmateCheckout\Model\Order
     */
    protected $orderModel;

    /**
     * @var \Billmate\BillmateCheckout\Model\Api\Billmate
     */
    protected $billmateProvider;
	
	public function __construct(
		Context $context,
		PageFactory $resultPageFactory,
		\Magento\Framework\Event\Manager $eventManager,
		\Billmate\BillmateCheckout\Helper\Data $_helper, 
		CheckoutSession $checkoutSession,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Registry $registry,
        \Magento\Checkout\Model\Session\SuccessValidator $successValidator,
        \Billmate\BillmateCheckout\Model\Order $orderModel,
        \Billmate\BillmateCheckout\Model\Api\Billmate $billmateProvider
	) {
		$this->eventManager = $eventManager;
		$this->resultPageFactory = $resultPageFactory;
		$this->checkoutSession = $checkoutSession;
		$this->helper = $_helper;
        $this->logger = $logger;
        $this->registry = $registry;
        $this->successValidator = $successValidator;
        $this->orderModel = $orderModel;
        $this->billmateProvider = $billmateProvider;
		parent::__construct($context);
	}
	
	public function execute()
    {
        $this->helper->setSessionData('billmate_checkout_id',null);

		try{
            $requestData = $this->getBmRequestData();
            $values = array(
                "number" => $requestData['data']['number']
            );

            $paymentInfo = $this->billmateProvider->getPaymentinfo($values);

			if (!$this->helper->getSessionData('bm-inc-id')) {
				$orderData = array(
					'email' => $this->helper->getSessionData('billmate_email'),
					'shipping_address' => $this->helper->getSessionData('billmate_billing_address'),
                    'payment_method_name' => $paymentInfo['PaymentData']['method_name']
				);
				$orderId = $this->orderModel->setOrderData($orderData)->create();
                if (!$orderId) {
                    throw new \Exception(
                        __('An error occurred on the server. Please try to place the order again.')
                    );
                }

                $this->helper->setSessionData('bm_order_id', $orderId);

			}

			$order = $this->helper->getOrderByIncrementId($this->helper->getSessionData('bm-inc-id'));
            $this->registry->register('bm-inc-id', $this->helper->getSessionData('bm-inc-id'));
			$orderId = $order->getId();

			$this->eventManager->dispatch(
				'checkout_onepage_controller_success_action',
				['order_ids' => [$order->getId()]]
			);

			$this->checkoutSession->setLastSuccessQuoteId($this->helper->getQuote()->getId());
			$this->checkoutSession->setLastQuoteId($this->helper->getQuote()->getId());
			$this->checkoutSession->setLastOrderId($orderId);
		}
		catch (\Exception $e){
            $this->helper->clearBmSession();
            $this->helper->addLog([
                'note' => 'Could not redirect customer to store order confirmation page',
                '__FILE__' => __FILE__,
                '__CLASS__' => __CLASS__,
                '__FUNCTION__' => __FUNCTION__,
                '__LINE__' => __LINE__,
                'exception.message' => $e->getMessage(),
                'exception.file' => $e->getFile(),
                'exception.line' => $e->getLine(),
            ]);
           return $this->resultRedirectFactory->create()->setPath('billmatecheckout/success/error');
		}

        return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
	}
}