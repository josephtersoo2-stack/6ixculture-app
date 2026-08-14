<?php

namespace App\Support\Contracts;

use App\Support\DTOs\ToolCallDTO;
use App\Support\DTOs\ToolResultDTO;
use App\Support\Models\SupportConversation;

interface ToolInterface
{
    /**
     * The unique identifier key of the tool (e.g. search_products, get_my_order).
     */
    public function key(): string;

    /**
     * Human readable name of the tool.
     */
    public function name(): string;

    /**
     * Tool description supplied to LLMs for function calling.
     */
    public function description(): string;

    /**
     * JSON Schema definition of acceptable input parameters.
     */
    public function inputSchema(): array;

    /**
     * Execute the tool safely within the context of a conversation and authenticated customer.
     */
    public function execute(ToolCallDTO $call, SupportConversation $conversation): ToolResultDTO;
}
