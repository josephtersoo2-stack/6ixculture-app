<?php

namespace App\Http\Resources;

use App\Libraries\AppLibrary;
use App\Models\ThemeSetting;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{

    public array $info;

    public function __construct($info)
    {
        parent::__construct($info);
        $this->info = $info;
    }

    public function toArray($request): array
    {
        $custom = [
            'app_version'                           => AppLibrary::appVersion(),
            'theme_logo'                            => $this->themeImage('theme_logo')?->logo ?? asset('/images/default/logo.png'),
            'theme_footer_logo'                     => $this->themeImage('theme_footer_logo')?->footerLogo ?? asset('/images/default/logo.png'),
            'theme_favicon_logo'                    => $this->themeImage('theme_favicon_logo')?->faviconLogo ?? asset('/images/default/logo.png'),
            'notification_audio'                    => asset('/audio/notification.mp3'),
            'image_cart'                            => asset('/images/required/empty-cart.gif'),
            'image_wishlist'                        => asset('/images/required/empty-wishlist.gif'),
            'image_app_store'                       => asset('/images/required/app-store.png'),
            'image_play_store'                      => asset('/images/required/play-store.png'),
            'image_confirm'                         => asset('/images/required/confirm.gif'),
            'image_403'                             => asset('/images/required/403.png'),
            'image_404'                             => asset('/images/required/404.png'),
            'not_found'                             => asset('/images/default/not-found/not_found.png')
        ];

        return array_merge($this->info, $custom);
    }

    public function themeImage($key)
    {
        return ThemeSetting::where(['key' => $key])->first();
    }
}
