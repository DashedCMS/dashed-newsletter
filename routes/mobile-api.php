<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Dashed\DashedNewsletter\Http\Controllers\Api\V1\NewsletterCampaignController;

Route::prefix('api/v1')
    ->middleware(['auth:sanctum', 'mobile.site'])
    ->group(function (): void {
        Route::get('newsletter/campaigns', [NewsletterCampaignController::class, 'index'])->middleware('ability:newsletter.read');
        Route::get('newsletter/campaigns/{campaign}', [NewsletterCampaignController::class, 'show'])->whereNumber('campaign')->middleware('ability:newsletter.read');
        Route::get('newsletter/campaigns/{campaign}/preview', [NewsletterCampaignController::class, 'preview'])->whereNumber('campaign')->middleware('ability:newsletter.read');
        Route::patch('newsletter/campaigns/{campaign}', [NewsletterCampaignController::class, 'update'])->whereNumber('campaign')->middleware('ability:newsletter.write');
        Route::post('newsletter/campaigns/{campaign}/send', [NewsletterCampaignController::class, 'send'])->whereNumber('campaign')->middleware('ability:newsletter.write');
        Route::post('newsletter/campaigns/{campaign}/schedule', [NewsletterCampaignController::class, 'schedule'])->whereNumber('campaign')->middleware('ability:newsletter.write');
        Route::post('newsletter/campaigns/{campaign}/cancel', [NewsletterCampaignController::class, 'cancel'])->whereNumber('campaign')->middleware('ability:newsletter.write');
    });
