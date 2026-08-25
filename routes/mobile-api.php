<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Dashed\DashedNewsletter\Http\Controllers\Api\V1\NewsletterCampaignController;

Route::prefix('api/v1')
    ->middleware(['auth:sanctum', 'mobile.site'])
    ->group(function (): void {
        Route::get('newsletter/campaigns', [NewsletterCampaignController::class, 'index'])->middleware('ability:newsletter.read');
    });
