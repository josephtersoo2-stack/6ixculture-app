<?php

namespace App\Services;

use Exception;
use App\Models\AiAgent;
use App\Models\GatewayOption;
use App\Enums\Status;
use App\Enums\Activity;
use App\Enums\InputType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\PaginateRequest;
use App\Libraries\QueryExceptionLibrary;

class AiAgentService
{
    public object $gateway;
    protected array $aiAgentFilter = [
        'name',
        'slug',
        'status'
    ];

    protected array $exceptFilter = [
        'excepts'
    ];

    protected array $withFilter = [
        'require'
    ];

    /**
     * Auto-sync OpenRouter, Gemini, and clean up duplicate/legacy records in the database
     */
    public function syncAgents(): void
    {
        try {
            $defaultKey = env('OPENROUTER_API_KEY', '');

            // 1. De-duplicate any duplicate OpenAI entries
            $openAIs = AiAgent::where('slug', 'openai')->get();
            if ($openAIs->count() > 1) {
                $duplicates = $openAIs->slice(1);
                foreach ($duplicates as $dup) {
                    GatewayOption::where('model_id', $dup->id)
                        ->where('model_type', 'App\Models\AiAgent')
                        ->delete();
                    $dup->delete();
                }
            }

            // 2. Clean up legacy AgentRouter record if exists
            $legacyAgentRouter = AiAgent::where('slug', 'agentrouter')->first();
            if ($legacyAgentRouter) {
                GatewayOption::where('model_id', $legacyAgentRouter->id)
                    ->where('model_type', 'App\Models\AiAgent')
                    ->delete();
                $legacyAgentRouter->delete();
            }

            // 3. Ensure OpenRouter (openrouter.ai) exists
            $openRouter = AiAgent::where('slug', 'openrouter')->first();
            if (!$openRouter) {
                $openRouter = AiAgent::create([
                    'name'   => 'OpenRouter (openrouter.ai - 400+ Models)',
                    'slug'   => 'openrouter',
                    'misc'   => null,
                    'status' => Status::ACTIVE,
                ]);

                $options = [
                    [
                        'option'     => 'openrouter_api_key',
                        'type'       => InputType::TEXT,
                        'value'      => $defaultKey,
                        'activities' => '',
                    ],
                    [
                        'option'     => 'openrouter_model',
                        'type'       => InputType::TEXT,
                        'value'      => 'openai/gpt-4o-mini',
                        'activities' => '',
                    ],
                    [
                        'option'     => 'openrouter_custom_model',
                        'type'       => InputType::TEXT,
                        'value'      => '',
                        'activities' => '',
                    ],
                    [
                        'option'     => 'openrouter_status',
                        'type'       => InputType::SELECT,
                        'value'      => Activity::ENABLE,
                        'activities' => [
                            Activity::ENABLE  => 'enable',
                            Activity::DISABLE => 'disable',
                        ],
                    ],
                ];

                foreach ($options as $opt) {
                    GatewayOption::create([
                        'model_id'   => $openRouter->id,
                        'model_type' => 'App\Models\AiAgent',
                        'option'     => $opt['option'],
                        'value'      => $opt['value'],
                        'type'       => $opt['type'],
                        'activities' => is_array($opt['activities']) ? json_encode($opt['activities']) : $opt['activities'],
                    ]);
                }
            } else {
                // Ensure key and status are updated if empty
                $keyOption = GatewayOption::where('model_id', $openRouter->id)
                    ->where('option', 'openrouter_api_key')
                    ->first();
                if ($keyOption && empty($keyOption->value)) {
                    $keyOption->value = $defaultKey;
                    $keyOption->save();
                }

                $statusOption = GatewayOption::where('model_id', $openRouter->id)
                    ->where('option', 'openrouter_status')
                    ->first();
                if ($statusOption) {
                    $statusOption->value = Activity::ENABLE;
                    $statusOption->save();
                }
                $openRouter->status = Status::ACTIVE;
                $openRouter->save();
            }
        } catch (Exception $e) {
            Log::warning('AiAgent sync note: ' . $e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function list(PaginateRequest $request)
    {
        try {
            $this->syncAgents();

            $requests    = $request->all();
            $method      = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
            $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
            $orderColumn = $request->get('order_column') ?? 'id';
            $orderType   = $request->get('order_type') ?? 'asc';

            return AiAgent::with('gatewayOptions')->where(function ($query) use ($requests) {
                foreach ($requests as $key => $request) {
                    if (in_array($key, $this->aiAgentFilter)) {
                        $query->where($key, 'like', '%' . $request . '%');
                    }

                    if (in_array($key, $this->exceptFilter)) {
                        $explodes = explode('|', $request);
                        if (is_array($explodes)) {
                            foreach ($explodes as $explode) {
                                $query->where('id', '!=', $explode);
                            }
                        }
                    }

                    if (in_array($key, $this->withFilter)) {
                        $query->orWhere(['id' => $request]);
                    }
                }
            })->orderBy($orderColumn, $orderType)->$method(
                $methodValue
            );
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @param Request $request
     * @return
     * @throws Exception
     */
    public function update($validationRequests): object
    {
        try {
            if (!blank($validationRequests)) {
                foreach ($validationRequests as $key => $value) {
                    $option = GatewayOption::where('option', $key)->first();
                    if (!blank($option)) {
                        $option->value = $value;
                        $option->save();
                    }
                    if (str_contains($key, 'status')) {
                        $this->gateway = AiAgent::find($option->model_id);
                        if (!blank($this->gateway)) {
                            $this->gateway->status = $value;
                            $this->gateway->save();
                        }
                    }
                }
            }
            return $this->gateway;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }
}
