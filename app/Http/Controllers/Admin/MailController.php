<?php

namespace App\Http\Controllers\Admin;

use Exception;
use App\Services\MailService;
use App\Http\Requests\MailRequest;
use App\Http\Resources\MailResource;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class MailController extends AdminController implements HasMiddleware
{
    private MailService $mailService;

    public function __construct(MailService $mailService)
    {
        parent::__construct();
        $this->mailService = $mailService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:settings', only: ['index', 'update', 'sendTestMail']),
        ];
    }

    public function index()
    {
        try {
            return new MailResource($this->mailService->list());
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function update(MailRequest $request): \Illuminate\Http\Response | MailResource | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return new MailResource($this->mailService->update($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function sendTestMail(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        try {
            $this->mailService->sendTestMail($request->email);
            return response(['status' => true, 'message' => 'Test email sent successfully to ' . $request->email]);
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
