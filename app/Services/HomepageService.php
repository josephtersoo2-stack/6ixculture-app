<?php

namespace App\Services;

use Exception;
use App\Libraries\QueryExceptionLibrary;
use Dipokhalder\Settings\Facades\Settings;
use Illuminate\Support\Facades\Log;

class HomepageService
{
    public function list()
    {
        try {
            return Settings::group('homepage')->all();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function update($request)
    {
        try {
            $data = $request->all();
            Settings::group('homepage')->set($data);
            return Settings::group('homepage')->all();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }
}
