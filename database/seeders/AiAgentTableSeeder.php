<?php

namespace Database\Seeders;

use App\Enums\Status;
use App\Enums\Activity;
use App\Enums\InputType;
use App\Models\AiAgent;
use App\Models\GatewayOption;
use Illuminate\Database\Seeder;

class AiAgentTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */

    public array $gateways = [
        [
            "name" => "OpenAI",
            "slug" => "openai",
            "misc" => null,
            "status" => Activity::DISABLE,
            "options" => [
                [
                    "option" => 'openai_api_key',
                    "type" => InputType::TEXT,
                    "activities" => '',
                ],
                [
                    "option" => 'openai_status',
                    "type" => InputType::SELECT,
                    "value" => Activity::DISABLE,
                    "activities" => [
                        Activity::ENABLE => "enable",
                        Activity::DISABLE => "disable",
                    ]
                ]
            ]
        ],
        [
            "name" => "AgentRouter (Claude / GPT / DeepSeek)",
            "slug" => "agentrouter",
            "misc" => null,
            "status" => Activity::ENABLE,
            "options" => [
                [
                    "option" => 'agentrouter_api_key',
                    "type" => InputType::TEXT,
                    "value" => 'sk-6YW4UUyQtrMR5sI70IxsJ2ULAEnXs0ZfHulOvzqp72hcpjYR',
                    "activities" => '',
                ],
                [
                    "option" => 'agentrouter_model',
                    "type" => InputType::SELECT,
                    "value" => "claude-3-7-sonnet-20250219",
                    "activities" => [
                        "claude-3-7-sonnet-20250219" => "Claude 3.7 Sonnet (Latest)",
                        "claude-3-5-sonnet-20241022" => "Claude 3.5 Sonnet",
                        "claude-3-5-haiku-20241022"  => "Claude 3.5 Haiku",
                        "gpt-4o"                     => "OpenAI GPT-4o",
                        "gpt-4o-mini"                => "OpenAI GPT-4o Mini",
                        "deepseek-chat"              => "DeepSeek V3",
                        "deepseek-reasoner"          => "DeepSeek R1 Reasoning",
                        "custom"                     => "Custom / Manual Model",
                    ]
                ],
                [
                    "option" => 'agentrouter_custom_model',
                    "type" => InputType::TEXT,
                    "value" => '',
                    "activities" => '',
                ],
                [
                    "option" => 'agentrouter_status',
                    "type" => InputType::SELECT,
                    "value" => Activity::ENABLE,
                    "activities" => [
                        Activity::ENABLE => "enable",
                        Activity::DISABLE => "disable",
                    ]
                ]
            ]
        ],
        [
            "name" => "Gemini AI",
            "slug" => "gemini",
            "misc" => null,
            "status" => Activity::ENABLE,
            "options" => [
                [
                    "option" => 'gemini_api_key',
                    "type" => InputType::TEXT,
                    "activities" => '',
                ],
                [
                    "option" => 'gemini_model',
                    "type" => InputType::SELECT,
                    "value" => "gemini-3.7-flash",
                    "activities" => [
                        "gemini-3.7-flash"       => "Gemini 3.7 Flash",
                        "gemini-3.7-flash-lite"  => "Gemini 3.7 Flash-Lite",
                        "gemini-3.7-pro"         => "Gemini 3.7 Pro",
                        "gemini-2.5-flash-lite"  => "Gemini 2.5 Flash-Lite",
                        "gemini-2.5-flash"       => "Gemini 2.5 Flash",
                        "gemini-2.5-pro"         => "Gemini 2.5 Pro",
                        "gemini-2.0-flash"       => "Gemini 2.0 Flash",
                        "gemini-2.0-flash-lite"  => "Gemini 2.0 Flash-Lite",
                        "gemini-1.5-flash"       => "Gemini 1.5 Flash",
                        "gemini-1.5-pro"         => "Gemini 1.5 Pro",
                        "custom"                 => "Custom / Manual Model",
                    ]
                ],
                [
                    "option" => 'gemini_custom_model',
                    "type" => InputType::TEXT,
                    "activities" => '',
                ],
                [
                    "option" => 'gemini_status',
                    "type" => InputType::SELECT,
                    "value" => Activity::ENABLE,
                    "activities" => [
                        Activity::ENABLE => "enable",
                        Activity::DISABLE => "disable",
                    ]
                ]
            ]
        ],
    ];

    public function run(): void
    {
        foreach ($this->gateways as $gateway) {
            $ai = AiAgent::create([
                'name'   => $gateway['name'],
                'slug'   => $gateway['slug'],
                'misc'   => json_encode($gateway['misc']),
                'status' => Status::INACTIVE
            ]);
            $this->gatewayOption($ai->id, $gateway['options']);
        }
    }

    public function gatewayOption($id, $options): void
    {
        foreach ($options as $option) {
            GatewayOption::create([
                'model_id'   => $id,
                'model_type' => 'App\Models\AiAgent',
                'option'     => $option['option'],
                'value'      => $option['value'] ?? "",
                'type'       => $option['type'],
                'activities' => json_encode($option['activities'])
            ]);
        }
    }
}
