<?php

namespace App\Services;

use App\Http\Requests\MailRequest;
use App\Libraries\QueryExceptionLibrary;
use Dipokhalder\EnvEditor\EnvEditor;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Dipokhalder\Settings\Facades\Settings;

class MailService
{
    public $envService;

    public function __construct(EnvEditor $envEditor)
    {
        $this->envService = $envEditor;
    }

    /**
     * @throws Exception
     */
    public function list()
    {
        try {
            return Settings::group('mail')->all();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function update(MailRequest $request)
    {
        try {
            Settings::group('mail')->set($request->validated());
            $this->envService->addData([
                'MAIL_MAILER'       => 'smtp',
                'MAIL_HOST'         => $request->mail_host,
                'MAIL_PORT'         => $request->mail_port,
                'MAIL_USERNAME'     => $request->mail_username,
                'MAIL_PASSWORD'     => $request->mail_password,
                'MAIL_ENCRYPTION'   => $request->mail_encryption,
                'MAIL_FROM_ADDRESS' => $request->mail_from_email,
                'MAIL_FROM_NAME'    => $request->mail_from_name
            ]);
            Artisan::call('optimize:clear');
            return $this->list();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * Send a test email to verify SMTP configuration
     * @throws Exception
     */
    public function sendTestMail(string $email)
    {
        try {
            $mailSettings = Settings::group('mail')->all();

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.transport' => 'smtp',
                'mail.mailers.smtp.host' => $mailSettings['mail_host'] ?? config('mail.mailers.smtp.host'),
                'mail.mailers.smtp.port' => $mailSettings['mail_port'] ?? config('mail.mailers.smtp.port'),
                'mail.mailers.smtp.username' => $mailSettings['mail_username'] ?? config('mail.mailers.smtp.username'),
                'mail.mailers.smtp.password' => $mailSettings['mail_password'] ?? config('mail.mailers.smtp.password'),
                'mail.mailers.smtp.encryption' => $mailSettings['mail_encryption'] ?? config('mail.mailers.smtp.encryption'),
                'mail.from.address' => $mailSettings['mail_from_email'] ?? config('mail.from.address'),
                'mail.from.name' => $mailSettings['mail_from_name'] ?? config('mail.from.name'),
            ]);

            $appName = config('app.name', 'ShopKing');

            \Illuminate\Support\Facades\Mail::raw(
                "Hello,\n\nThis is a test email sent from your website (" . config('app.url') . ") to verify that your SMTP Email Settings are configured correctly and working properly!\n\nSent at: " . now()->toDayDateTimeString(),
                function ($message) use ($email, $appName) {
                    $message->to($email)
                        ->subject("SMTP Test Email - " . $appName);
                }
            );

            return true;
        } catch (Exception $exception) {
            Log::error('Test mail failed: ' . $exception->getMessage());
            throw new Exception("Failed to send test email: " . $exception->getMessage(), 422);
        }
    }
}
