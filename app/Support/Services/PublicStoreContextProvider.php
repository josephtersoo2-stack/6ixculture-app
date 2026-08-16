<?php

namespace App\Support\Services;

use App\Models\ProductCategory;
use App\Enums\Status;
use Dipokhalder\Settings\Facades\Settings;

class PublicStoreContextProvider
{
    /**
     * Get safe, public static facts about 6ixCulture for AI context grounding.
     * No sensitive credentials, internal server info, or database details are included.
     */
    public function getPublicStoreContext(): array
    {
        $categories = $this->getPublicCategories();

        return [
            'store_name' => '6ix Culture',
            'tagline' => 'Premium Urban Streetwear & Contemporary Culture Apparel',
            'currency' => 'Nigerian Naira (NGN / ₦)',
            'support_hours' => 'Mon - Sat: 8:00 AM - 8:00 PM (WAT), Sun: 12:00 PM - 6:00 PM (WAT)',
            'contact_channels' => 'Email: support@6ixculture.com.ng, WhatsApp/Live Support via web widget',
            'shipping_summary' => 'Standard nationwide delivery: 2-5 business days. Express delivery (Lagos & Abuja): 24-48 hours.',
            'return_summary' => '7-day return window for unworn items with original tags and packaging intact. Clearance and underwear items are final sale.',
            'categories' => $categories,
        ];
    }

    /**
     * Format the public facts as a concise markdown section for the prompt.
     */
    public function toMarkdown(): string
    {
        $ctx = $this->getPublicStoreContext();
        $catList = implode(', ', $ctx['categories']);

        return <<<TEXT
### 6ixCulture Official Store Information:
- **Store**: {$ctx['store_name']} ({$ctx['tagline']})
- **Currency**: {$ctx['currency']}
- **Operating Support Hours**: {$ctx['support_hours']}
- **Contact**: {$ctx['contact_channels']}
- **Shipping Policy Summary**: {$ctx['shipping_summary']}
- **Return Policy Summary**: {$ctx['return_summary']}
- **Available Product Categories**: {$catList}
TEXT;
    }

    protected function getPublicCategories(): array
    {
        try {
            $categories = ProductCategory::where('status', Status::ACTIVE)->limit(10)->pluck('name')->toArray();
            if (!empty($categories)) {
                return $categories;
            }
        } catch (\Throwable $e) {}

        return [
            'Hoodies & Sweatshirts',
            'Graphic Tees',
            'Cargo Pants & Joggers',
            'Denim & Jeans',
            'Caps & Headwear',
            'Outerwear & Jackets',
            'Accessories',
        ];
    }
}
