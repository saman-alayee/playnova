<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SettingsController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success([
            'logo_url' => Setting::logoUrl(),
            'social' => Setting::socialLinks(),
            'results_channels' => Setting::resultsChannelItems(),
            'contact_email' => Setting::get('contact_email', 'support@playnova.ir'),
            'contact_phone' => Setting::get('contact_phone', ''),
        ]);
    }
}
