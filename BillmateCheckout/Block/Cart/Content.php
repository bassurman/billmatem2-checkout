<?php
namespace Billmate\BillmateCheckout\Block\Cart;

class Content extends \Magento\Checkout\Block\Onepage
{

    /**
     * @var \Billmate\BillmateCheckout\Helper\Data
     */
    protected $helper;

    /**
     * @var \Billmate\BillmateCheckout\Helper\Iframe
     */
    protected $iframeHelper;

    /**
     * @var \Magento\Catalog\Block\Product\ImageBuilder
     */
    protected $imageBuilder;

    /**
     * @var \Billmate\BillmateCheckout\Helper\Config
     */
    protected $configHelper;

    /**
     * @var \Magento\Tax\Helper\Data
     */
    protected $_taxHelper;

    /**
     * Cart constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Data\Form\FormKey             $formKey
     * @param \Magento\Checkout\Model\CompositeConfigProvider  $configProvider
     * @param \Billmate\BillmateCheckout\Helper\Data           $_helper
     * @param \Magento\Checkout\Model\Session                  $checkoutSession
     * @param \Magento\Checkout\Helper\Data                    $checkoutHelper
     * @param array                                            $layoutProcessors
     * @param array                                            $data
     */
    public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Magento\Checkout\Model\CompositeConfigProvider $configProvider,
		\Billmate\BillmateCheckout\Helper\Data $_helper,
		\Billmate\BillmateCheckout\Helper\Iframe $iframeHelper,
		\Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \Magento\Framework\Pricing\Helper\Data $priceHelper,
        \Magento\Catalog\Block\Product\ImageBuilder $imageBuilder,
        \Billmate\BillmateCheckout\Helper\Config $configHelper,
        \Magento\Tax\Helper\Data $taxHelper,
        array $layoutProcessors = [],
        array $data = []
	) {
        parent::__construct($context, $formKey, $configProvider, $layoutProcessors, $data);
		$this->helper = $_helper;
		$this->iframeHelper = $iframeHelper;
		$this->priceHelper = $priceHelper;
        $this->imageBuilder = $imageBuilder;
        $this->configHelper = $configHelper;
        $this->_taxHelper = $taxHelper;
	}

    /**
     * @return \Magento\Quote\Model\Quote\Item[]
     */
	public function getItems()
    {
        return $this->helper->getItems();
    }

    /**
     * @param $price
     *
     * @return float|string
     */
    public function formatPrice($price, $format = true, $includeContainer = false)
    {
        return $this->priceHelper->currency($price, $format, $includeContainer);
    }

    /**
     * @param $price
     *
     * @return float
     */
    public function getShippingPrice($price)
    {
        return $this->_taxHelper->getShippingPrice(
            $price,
            $this->_taxHelper->displayShippingPriceIncludingTax()
        );
    }

    /**
     * @param       $product
     * @param       $imageId
     * @param array $attributes
     *
     * @return mixed
     */
    public function getImage($product, $imageId, $attributes = [])
    {
        return $this->imageBuilder->setProduct($product)
            ->setImageId($imageId)
            ->setAttributes($attributes)
            ->create();
    }

    /**
     * @return bool
     */
    public function isEnabledButtons()
    {
        return $this->configHelper->getBtnEnable();
    }

    /**
     * @return array
     */
    public function getShippingMethodsRates()
    {
        return $this->helper->getShippingMethodsRates();
    }

    /**
     * @return bool
     */
    public function isActiveMethod($methodCode)
    {
        return $this->helper->isActiveShippingMethod($methodCode);
    }

    /**
     * @return bool
     */
    public function hasDiscount()
    {
        $quote = $this->helper->getQuote();
        return ($quote->getSubtotalWithDiscount() < $quote->getSubtotal());
    }

    /**
     * @return float
     */
    public function getDiscountValue()
    {
        return $this->helper->getQuote()
            ->getShippingAddress()
            ->getDiscountAmount();
    }

    /**
     * @return float
     */
    public function getTotalValue()
    {
        return $this->helper->getQuote()
            ->getShippingAddress()
            ->getData('grand_total');
    }

    public function getTaxValue()
    {
        $shippingAddressTotal = $this->helper->getQuote()
            ->getShippingAddress();
        return $shippingAddressTotal->getTaxAmount();
    }

    /**
     * @return float
     */
    public function getShippingValue()
    {
        return $this->helper->getQuote()
            ->getShippingAddress()
            ->getShippingInclTax();
    }
}