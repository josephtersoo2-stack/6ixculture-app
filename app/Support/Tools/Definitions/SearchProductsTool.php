<?php

namespace App\Support\Tools\Definitions;

use App\Enums\Status;
use App\Http\Requests\PaginateRequest;
use App\Services\ProductService;
use App\Support\Contracts\ToolInterface;
use App\Support\DTOs\ToolCallDTO;
use App\Support\DTOs\ToolResultDTO;
use App\Support\Models\SupportConversation;

class SearchProductsTool implements ToolInterface
{
    protected ProductService $productService;

    public function __construct()
    {
        $this->productService = new ProductService();
    }

    public function key(): string
    {
        return 'search_products';
    }

    public function name(): string
    {
        return 'Search Products';
    }

    public function description(): string
    {
        return 'Search the product catalog by query, category, or price range. Always returns a list of matching products.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Keyword query for search (matches name, description, SKU, tags).'
                ],
                'category_id' => [
                    'type' => 'integer',
                    'description' => 'Optional product category database ID.'
                ],
                'max_price' => [
                    'type' => 'number',
                    'description' => 'Optional maximum selling price in NGN.'
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Optional maximum number of products to return (default 5, max 10).'
                ],
            ],
        ];
    }

    public function execute(ToolCallDTO $call, SupportConversation $conversation): ToolResultDTO
    {
        try {
            $args = $call->arguments;
            $limit = min((int)($args['limit'] ?? 5), 10);

            // Construct PaginateRequest parameters
            $params = [
                'paginate' => 1,
                'per_page' => $limit,
                'status' => Status::ACTIVE,
            ];

            if (!empty($args['query'])) {
                $params['name'] = $args['query'];
            }

            if (!empty($args['category_id'])) {
                $params['product_category_id'] = (int)$args['category_id'];
            }

            $request = new PaginateRequest($params);
            $products = $this->productService->list($request);

            $results = [];
            foreach ($products as $prod) {
                // Ensure max price filter is respected
                if (isset($args['max_price']) && (float)$prod->selling_price > (float)$args['max_price']) {
                    continue;
                }

                $results[] = [
                    'id' => $prod->id,
                    'name' => $prod->name,
                    'slug' => $prod->slug,
                    'sku' => $prod->sku,
                    'price' => (float)$prod->selling_price,
                    'category' => $prod->category?->name,
                    'brand' => $prod->brand?->name,
                    'image' => $prod->thumb,
                    'status' => 'In Stock',
                ];
            }

            return ToolResultDTO::success(
                $call->id,
                $this->key(),
                ['products' => $results],
                ['product_list' => $results]
            );
        } catch (\Throwable $e) {
            return ToolResultDTO::error($call->id, $this->key(), $e->getMessage());
        }
    }
}
