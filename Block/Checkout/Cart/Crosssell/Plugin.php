<?php
/**
 * Tweakwise (https://www.tweakwise.com/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2022 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tweakwise\Magento2Tweakwise\Block\Checkout\Cart\Crosssell;

use Closure;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\TargetRule\Block\Checkout\Cart\Crosssell;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Tweakwise\Magento2Tweakwise\Block\Catalog\Product\ProductList\AbstractRecommendationPlugin;
use Tweakwise\Magento2Tweakwise\Exception\ApiException;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Product\Recommendation\Context;
use Tweakwise\Magento2Tweakwise\Model\Client\Request\Recommendations\FeaturedRequest;
use Tweakwise\Magento2Tweakwise\Model\Client\Request\Recommendations\ProductRequest;
use Tweakwise\Magento2Tweakwise\Model\Client\RequestFactory;
use Tweakwise\Magento2Tweakwise\Model\Config;
use Magento\Checkout\Model\Session;
use Tweakwise\Magento2Tweakwise\Model\Config\TemplateFinder;

/**
 * Class is only used by commerce version
 */
class Plugin extends AbstractRecommendationPlugin
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var ProductRepositoryInterface|null
     */
    private $productRepository;

    /**
     * @var Product
     */
    private $lastAddedProduct;

    /**
     * @param Config $config
     * @param Registry $registry
     * @param Context $context
     * @param TemplateFinder $templateFinder
     * @param Session $checkoutSession
     * @param ProductRepositoryInterface|null $productRepository
     */
    public function __construct(
        Config $config,
        Registry $registry,
        Context $context,
        TemplateFinder $templateFinder,
        Session $checkoutSession,
        ?ProductRepositoryInterface $productRepository = null
    )
    {
        $this->productRepository = $productRepository;
        $this->checkoutSession  = $checkoutSession;
        $this->productRepository = $productRepository
            ?? ObjectManager::getInstance()->get(ProductRepositoryInterface::class);

        parent::__construct($config, $registry, $context, $templateFinder);
    }

    /**
     * @return string
     */
    protected function getType()
    {
        $crosssellType = $this->config->getShoppingcartCrossellType();

        if (empty($crosssellType) || $crosssellType === 'crosssell') {
            return Config::RECCOMENDATION_TYPE_SHOPPINGCART;
        }

        return Config::RECCOMENDATION_TYPE_SHOPPINGCART_FEATURED;
    }

    public function aroundGetLastAddedProduct(Crosssell $crossell, Closure $proceed)
    {
        $product = null;
        $productId = $this->_getLastAddedProductId();
        if ($productId) {
            try {
                $product = $this->productRepository->getById($productId);
            } catch (NoSuchEntityException $e) {
                $product = null;
            }
        }
        $this->lastAddedProduct = $product;
    }

    /**
     * Get last product ID that was added to cart and remove this information from session
     *
     * @return int
     */
    protected function _getLastAddedProductId()
    {
        return $this->checkoutSession->getLastAddedProductId(false);
    }

    /**
     * @param Crosssell $crosssell
     * @param $result
     * @return array
     */
    public function afterGetItemCollection(Crosssell $crosssell, $result)
    {
        if (!$this->config->isRecommendationsEnabled(Config::RECCOMENDATION_TYPE_SHOPPINGCART)) {
            return $result;
        }

        $items = [];

        $cartItems = $this->_getCartProducts();
        if ($cartItems) {
            $items = $this->getShoppingcartCrosssellItems($cartItems, $result);
        }

        $crosssell->setData('items', $items);
        return $items;
    }

    /**
     * @param array $cartProductIds
     * @param array $result
     * @return array
     */
    private function getShoppingcartCrosssellItems(array $cartProductIds, array $result)
    {
        $itmes = [];

        if ($cartProductIds) {
            if ($this->lastAddedProduct) {
                $itmes = $this->getShoppingcartCrosssellTweakwiseItems($this->lastAddedProduct, $result, $cartProductIds);
            }

            if (empty($items)) {
                foreach ($cartProductIds as $productId) {
                    try {
                        $product = $this->productRepository->getById($productId);
                    } catch (NoSuchEntityException $e) {
                        $product = null;
                    }

                    if (!empty($product)) {
                        $items = $this->getShoppingcartCrosssellTweakwiseItems($product, $result, $cartProductIds);
                    }

                    if (!empty($items)) {
                        break;
                    }
                }
            }
        }

        return $items;
    }

    /**
     * @param ProductInterface $product
     * @param array $result
     * @param array $cartItems
     * @return array
     */
    private function getShoppingcartCrosssellTweakwiseItems (ProductInterface $product, array $result, array $cartItems) {
        $items = [];

        //show featured products
        if ($this->getType() === Config::RECCOMENDATION_TYPE_SHOPPINGCART_FEATURED) {
            return $this->getFeaturedItems();
        }

        //show crosssell products
        $requestFactory = new RequestFactory(ObjectManager::getInstance(), ProductRequest::class);
        $request = $requestFactory->create();
        $request->setProduct($product);

        if (!$this->templateFinder->forProduct($product, $this->getType())) {
            return $result;
        }

        $request->setTemplate($this->templateFinder->forProduct($product, $this->getType()));
        $this->context->setRequest($request);

        try {
            $collection = $this->getCollection();
        } catch (ApiException $e) {
            return $result;
        }

        if (!empty($cartItems)) {
            $collection = $this->removeCartItems($collection, $cartItems);
        }

        foreach ($collection as $item) {
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param $collection
     * @param $filteredProducts
     * @return void
     */
    protected function removeCartItems($collection, $cartItems)
    {
        $items = $collection->getItems();

        if(!empty($cartItems)) {
            foreach ($cartItems as $cartItem) {
                unset($items[$cartItem]);
            }
        }
        return $items;
    }

    /**
     * Retrieve quote instance
     *
     * @return \Magento\Quote\Model\Quote
     */
    public function getQuote()
    {
        return $this->checkoutSession->getQuote();
    }

    /**
     * Retrieve Array of Product ids in Cart
     *
     * @return array
     */
    protected function _getCartProducts()
    {
        foreach ($this->getQuote()->getAllItems() as $quoteItem) {
            /* @var $quoteItem \Magento\Quote\Model\Quote\Item */
            $product = $quoteItem->getProduct();
            $products[] = $product->getEntityId();
        }

        return $products;
    }

    private function getFeaturedItems()
    {
        $requestFactory = new RequestFactory(ObjectManager::getInstance(), FeaturedRequest::class);
        $request = $requestFactory->create();

        $templateId = $this->config->getRecommendationsTemplate(Config::RECCOMENDATION_TYPE_SHOPPINGCART_FEATURED);
        $request->setTemplate($templateId);

        $this->context->setRequest($request);

        try {
            $collection = $this->context->getCollection();
        } catch (ApiException $e) {
            return [];
        }

        if (!empty($cartItems)) {
            $collection = $this->removeCartItems($collection, $cartItems);
        }

        foreach ($collection as $item) {
            $items[] = $item;
        }

        return $items;
    }
}
