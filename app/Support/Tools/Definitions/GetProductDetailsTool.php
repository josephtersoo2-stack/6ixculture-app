<?php

namespace App\Support\Tools\Definitions;

use App\Enums\Status;
use App\Models\Product;
use App\Services\ProductService;
use App\Support\Contracts\ToolInterface;
use App\Support\DTOs\ToolCallDTO;
use App\Support\DTOs\ToolResultDTO;
use App\Support\Models\SupportConversation;

class GetProductDetailsTool implements ToolInterface
{
    protected ProductService $productService;

    public function __construct()
    {
        $this->productService = new ProductService();
    }

    public function key(): string
    {
        return 'get_product_details';
    }

    public function name(): string
    {
        return 'Get Product Details';
    }

    public function description(): string
    {
        return 'Retrieve full details, attributes, variations, size chart, and stock availability for a single product by its database ID.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'product_id' => [
                    'type' => 'integer',
                    'description' => 'The unique database ID of the product.'
                ]
            ],
            'required' => ['product_id']
        ];
    }

    public function execute(ToolCallDTO $call, SupportConversation $conversation): ToolResultDTO
    {
        try {
            $productId = (int)($call->arguments['product_id'] ?? 0);
            if ($productId <= 0) {
                return ToolResultDTO::error($call->id, $this->key(), 'Invalid product_id parameter.');
            }

            $product = Product::find($productId);
            if (!$product || (int)$product->status !== Status::ACTIVE) {
                return ToolResultDTO::error($call->id, $this->key(), 'Product not found or is currently inactive.');
            }

            // Fetch loaded relationship details using authoritative ProductService
            $productWithDetails = $this->productService->show($product);

            $variations = [];
            if ($productWithDetails->variations) {
                foreach ($productWithDetails->variations as $var) {
                    $variations[] = [
                        'id' => $var->id,
                        'name' => $var->name,
                        'price' => (float)$var->price,
                        'sku' => $var->sku,
                    ];
                }
            }

            $details = [
                'id' => $productWithDetails->id,
                'name' => $productWithDetails->name,
                'slug' => $productWithDetails->slug,
                'sku' => $productWithDetails->sku,
                'price' => (float)$productWithDetails->selling_price,
                'description' => strip_tags($productWithDetails->description),
                'category' => $productWithDetails->category?->name,
                'brand' => $productWithDetails->brand?->name,
                'image' => $productWithDetails->thumb,
                'variations' => $variations,
                'warranty' => $productWithDetails->warranty ?? 'None',
            ];

            return ToolResultDTO::success(
                $call->id,
                $this->key(),
                $details,
                ['product' => $details]
            );
        } catch (\Throwable $e) {
            return ToolResultDTO::error($call->id, $this->key(), $e->getMessage());
        }
    }
}
