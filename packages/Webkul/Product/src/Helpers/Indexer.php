<?php

namespace Webkul\Product\Helpers;

use Webkul\Core\Repositories\ChannelRepository;
use Webkul\Customer\Repositories\CustomerGroupRepository;
use Webkul\Product\Repositories\ProductPriceIndexRepository;
use Webkul\Product\Repositories\ProductInventoryIndexRepository;
use Webkul\Product\Helpers\Indexers\Flat\Product as FlatIndexer;
use Webkul\Product\Helpers\Indexers\Inventory\Product as InventoryIndexer;

class Indexer
{
    /**
     * Create a new command instance.
     *
     * @param  \Webkul\Core\Repositories\ChannelRepository  $channelRepository
     * @param  \Webkul\Customer\Repositories\CustomerGroupRepository  $customerGroupRepository
     * @param  \Webkul\Product\Repositories\ProductPriceIndexRepository  $productPriceIndexRepository
     * @param  \Webkul\Product\Repositories\ProductInventoryIndexRepository  $productInventoryIndexRepository
     * @param  \Webkul\Product\Helpers\Indexers\Flat\Product  $flatIndexer
     * @param  \Webkul\Product\Helpers\Indexers\Inventory\Product  $inventoryIndexer
     * @return void
     */
    public function __construct(
        protected ChannelRepository $channelRepository,
        protected CustomerGroupRepository $customerGroupRepository,
        protected ProductPriceIndexRepository $productPriceIndexRepository,
        protected ProductInventoryIndexRepository $productInventoryIndexRepository,
        protected FlatIndexer $flatIndexer,
        protected InventoryIndexer $inventoryIndexer
    )
    {
    }

    /**
     * Refresh product indexes
     *
     * @param  \Webkul\Product\Contracts\Product  $product
     * @param  array  $indexers
     * @return void
     */
    public function refresh($product, array $indexers = ['price', 'inventory'])
    {
        if (in_array('inventory', $indexers)) {
            $this->refreshInventory($product);
        }

        if (in_array('price', $indexers)) {
            $this->refreshPrice($product);
        }
    }

    /**
     * Refresh product flat indexes
     *
     * @param  \Webkul\Product\Contracts\Product  $product
     * @return void
     */
    public function refreshFlat($product)
    {
        $this->flatIndexer->refresh($product);
    }

    /**
     * Refresh product price indexes
     *
     * @param  \Webkul\Product\Contracts\Product  $product
     * @return void
     */
    public function refreshPrice($product)
    {
        $indexer = $product->getTypeInstance()
            ->getPriceIndexer()
            ->setProduct($product);

        $customerGroups = $this->customerGroupRepository->all();

        foreach ($customerGroups as $customerGroup) {
            $this->productPriceIndexRepository->updateOrCreate([
                'customer_group_id' => $customerGroup->id,
                'product_id'        => $product->id,
            ], $indexer->getIndices($customerGroup));
        }
    }

    /**
     * Refresh product inventory indices
     *
     * @param  \Webkul\Product\Contracts\Product  $product
     * @return void
     */
    public function refreshInventory($product)
    {
        if (in_array($product, ['configurable', 'bundle', 'grouped', 'booking'])) {
            return;
        }

        $channels = $this->channelRepository->all();

        foreach ($channels as $channel) {
            $this->productInventoryIndexRepository->updateOrCreate([
                'channel_id' => $channel->id,
                'product_id' => $product->id,
            ], $this->inventoryIndexer->setProduct($product)->getIndices($channel));
        }
    }
}