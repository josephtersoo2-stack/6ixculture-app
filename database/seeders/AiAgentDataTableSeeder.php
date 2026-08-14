<?php

namespace Database\Seeders;


use App\Enums\Activity;
use App\Models\AiAgent;
use App\Models\GatewayOption;
use Dipokhalder\EnvEditor\EnvEditor;
use Illuminate\Database\Seeder;

class AiAgentDataTableSeeder extends Seeder
{

    public array $gateways = [
        [
            "slug"    => "agentrouter",
            "status"  => Activity::ENABLE,
            "options" => [
                [
                    "option" => 'openrouter_api_key',
                    "value"  => '',
                ],
                [
                    "option" => 'agentrouter_model',
                    "value"  => 'claude-3-7-sonnet-20250219',
                ],
                [
                    "option" => 'agentrouter_status',
                    "value"  => Activity::ENABLE
                ],
            ]
        ],
        [
            "slug"    => "openai",
            "status"  => Activity::DISABLE,
            "options" => [
                [
                    "option" => 'openai_api_key',
                    "value"  => '',
                ],
                [
                    "option" => 'openai_status',
                    "value"  => Activity::DISABLE
                ],
            ]
        ]
    ];

    public function run(): void
    {
        $envService = new EnvEditor();
        if ($envService->getValue('DEMO')) {
            foreach ($this->gateways as $gateway) {
                $aiAgent = AiAgent::where(['slug' => $gateway['slug']])->first();
                if ($aiAgent) {
                    $aiAgent->status = $gateway['status'];
                    $aiAgent->save();
                }
                $this->agentOption($gateway['options']);
            }
        }
    }

    public function agentOption($options): void
    {
        if (!blank($options)) {
            foreach ($options as $option) {
                $gatewayOption = GatewayOption::where(['option' => $option['option']])->first();
                if ($gatewayOption) {
                    $gatewayOption->value = $option['value'];
                    $gatewayOption->save();
                }
            }
        }
    }
}
