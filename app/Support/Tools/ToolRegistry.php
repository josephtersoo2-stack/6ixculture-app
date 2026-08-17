<?php

namespace App\Support\Tools;

use App\Support\Contracts\ToolInterface;
use App\Support\DTOs\ToolCallDTO;
use App\Support\DTOs\ToolResultDTO;
use App\Support\Models\SupportAITool;
use App\Support\Models\SupportAIToolPermission;
use App\Support\Models\SupportConversation;
use App\Support\Tools\Definitions\GetMyOrdersTool;
use App\Support\Tools\Definitions\GetProductDetailsTool;
use App\Support\Tools\Definitions\SearchProductsTool;
use App\Support\Tools\Definitions\TrackMyOrderTool;

class ToolRegistry
{
    /**
     * Registered tool definitions map.
     * @var array<string, ToolInterface>
     */
    protected array $tools = [];

    public function __construct()
    {
        $this->register(new SearchProductsTool());
        $this->register(new GetProductDetailsTool());
        $this->register(new GetMyOrdersTool());
        $this->register(new TrackMyOrderTool());
    }

    public function register(ToolInterface $tool): void
    {
        $this->tools[$tool->key()] = $tool;
    }

    public function get(string $key): ?ToolInterface
    {
        return $this->tools[$key] ?? null;
    }

    /**
     * Get all registered tool instances.
     * @return array<string, ToolInterface>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Expose list of all registered and database-active tools with their schemas.
     */
    public function getActiveTools(): array
    {
        $activeTools = [];
        try {
            $dbTools = SupportAITool::active()->get()->pluck('key')->toArray();
        } catch (\Throwable $e) {
            $dbTools = array_keys($this->tools); // Fallback to all registered in testing
        }

        foreach ($this->tools as $key => $tool) {
            if (in_array($key, $dbTools)) {
                $activeTools[] = [
                    'key' => $tool->key(),
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'input_schema' => $tool->inputSchema(),
                ];
            }
        }

        return $activeTools;
    }

    /**
     * Validate call arguments against the tool schema.
     */
    public function validate(ToolCallDTO $call, ToolInterface $tool): ?string
    {
        $schema = $tool->inputSchema();
        $args = $call->arguments;

        // Basic validation for required fields
        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $req) {
                if (!isset($args[$req]) || $args[$req] === null || $args[$req] === '') {
                    return "Missing required parameter: '{$req}'";
                }
            }
        }

        // Validate types
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($args as $k => $v) {
                if ($v === null) continue;
                
                $prop = $schema['properties'][$k] ?? null;
                if ($prop) {
                    $type = $prop['type'] ?? 'string';
                    if ($type === 'integer' && !is_numeric($v)) {
                        return "Parameter '{$k}' must be an integer.";
                    }
                    if ($type === 'number' && !is_numeric($v)) {
                        return "Parameter '{$k}' must be a number.";
                    }
                    if ($type === 'array' && !is_array($v)) {
                        return "Parameter '{$k}' must be an array.";
                    }
                    if ($type === 'object' && !is_array($v)) {
                        return "Parameter '{$k}' must be an object.";
                    }
                }
            }
        }

        return null;
    }
}
