<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Dashed\DashedNewsletter\Http\Controllers\Api\V1\NewsletterAudienceController;
use Dashed\DashedNewsletter\Http\Controllers\Api\V1\NewsletterCampaignController;

Route::prefix('api/v1')
    ->middleware(['auth:sanctum', 'mobile.site'])
    ->group(function (): void {
        Route::get('newsletter/lists', [NewsletterAudienceController::class, 'lists'])->middleware('ability:newsletter.read');
        Route::get('newsletter/lists/{list}/subscribers', [NewsletterAudienceController::class, 'subscribers'])->whereNumber('list')->middleware('ability:newsletter.read');
        Route::get('newsletter/subscribers/{subscriber}', [NewsletterAudienceController::class, 'subscriber'])->whereNumber('subscriber')->middleware('ability:newsletter.read');
        Route::post('newsletter/lists/{list}/subscribers', [NewsletterAudienceController::class, 'addSubscriber'])->whereNumber('list')->middleware('ability:newsletter.write');
        Route::post('newsletter/subscribers/{subscriber}/unsubscribe', [NewsletterAudienceController::class, 'unsubscribe'])->whereNumber('subscriber')->middleware('ability:newsletter.write');
        Route::get('newsletter/lists/{list}', [NewsletterAudienceController::class, 'listDetail'])->whereNumber('list')->middleware('ability:newsletter.read');
        Route::post('newsletter/lists', [NewsletterAudienceController::class, 'storeList'])->middleware('ability:newsletter.write');
        Route::patch('newsletter/lists/{list}', [NewsletterAudienceController::class, 'updateList'])->whereNumber('list')->middleware('ability:newsletter.write');
        Route::delete('newsletter/lists/{list}', [NewsletterAudienceController::class, 'deleteList'])->whereNumber('list')->middleware('ability:newsletter.write');
        Route::patch('newsletter/subscribers/{subscriber}', [NewsletterAudienceController::class, 'updateSubscriber'])->whereNumber('subscriber')->middleware('ability:newsletter.write');
        Route::delete('newsletter/subscribers/{subscriber}', [NewsletterAudienceController::class, 'deleteSubscriber'])->whereNumber('subscriber')->middleware('ability:newsletter.write');
        Route::get('newsletter/lists/{list}/fields', [NewsletterAudienceController::class, 'fields'])->whereNumber('list')->middleware('ability:newsletter.read');
        Route::post('newsletter/lists/{list}/fields', [NewsletterAudienceController::class, 'storeField'])->whereNumber('list')->middleware('ability:newsletter.write');
        Route::post('newsletter/lists/{list}/fields/defaults', [NewsletterAudienceController::class, 'createDefaultFields'])->whereNumber('list')->middleware('ability:newsletter.write');
        Route::patch('newsletter/fields/{field}', [NewsletterAudienceController::class, 'updateField'])->whereNumber('field')->middleware('ability:newsletter.write');
        Route::delete('newsletter/fields/{field}', [NewsletterAudienceController::class, 'deleteField'])->whereNumber('field')->middleware('ability:newsletter.write');
        Route::post('newsletter/suppressions', [NewsletterAudienceController::class, 'blockAddress'])->middleware('ability:newsletter.write');
        Route::delete('newsletter/suppressions/{suppression}', [NewsletterAudienceController::class, 'unblock'])->whereNumber('suppression')->middleware('ability:newsletter.write');
        Route::get('newsletter/settings', [NewsletterAudienceController::class, 'settings'])->middleware('ability:newsletter.read');
        Route::put('newsletter/settings', [NewsletterAudienceController::class, 'updateSettings'])->middleware('ability:newsletter.write');
        Route::get('newsletter/suppressions', [NewsletterAudienceController::class, 'suppressions'])->middleware('ability:newsletter.read');
        Route::get('newsletter/segments', [NewsletterAudienceController::class, 'segments'])->middleware('ability:newsletter.read');
        Route::get('newsletter/campaigns', [NewsletterCampaignController::class, 'index'])->middleware('ability:newsletter.read');
        Route::post('newsletter/campaigns', [NewsletterCampaignController::class, 'store'])->middleware('ability:newsletter.write');
        Route::post('newsletter/campaigns/ai/compose', [NewsletterCampaignController::class, 'aiCompose'])->middleware('ability:newsletter.write');
        Route::get('newsletter/campaigns/{campaign}', [NewsletterCampaignController::class, 'show'])->whereNumber('campaign')->middleware('ability:newsletter.read');
        Route::get('newsletter/campaigns/{campaign}/statistics', [NewsletterCampaignController::class, 'statistics'])->whereNumber('campaign')->middleware('ability:newsletter.read');
        Route::get('newsletter/campaigns/{campaign}/recipients', [NewsletterCampaignController::class, 'recipients'])->whereNumber('campaign')->middleware('ability:newsletter.read');
        Route::get('newsletter/campaigns/{campaign}/unsubscribe-reasons', [NewsletterCampaignController::class, 'unsubscribeReasons'])->whereNumber('campaign')->middleware('ability:newsletter.read');
        Route::get('newsletter/campaigns/{campaign}/preview', [NewsletterCampaignController::class, 'preview'])->whereNumber('campaign')->middleware('ability:newsletter.read');
        Route::patch('newsletter/campaigns/{campaign}', [NewsletterCampaignController::class, 'update'])->whereNumber('campaign')->middleware('ability:newsletter.write');
        Route::post('newsletter/campaigns/{campaign}/send', [NewsletterCampaignController::class, 'send'])->whereNumber('campaign')->middleware('ability:newsletter.write');
        Route::post('newsletter/campaigns/{campaign}/schedule', [NewsletterCampaignController::class, 'schedule'])->whereNumber('campaign')->middleware('ability:newsletter.write');
        Route::post('newsletter/campaigns/{campaign}/cancel', [NewsletterCampaignController::class, 'cancel'])->whereNumber('campaign')->middleware('ability:newsletter.write');
        Route::post('newsletter/campaigns/{campaign}/send-test', [NewsletterCampaignController::class, 'sendTest'])->whereNumber('campaign')->middleware('ability:newsletter.write');
        Route::post('newsletter/campaigns/{campaign}/duplicate', [NewsletterCampaignController::class, 'duplicate'])->whereNumber('campaign')->middleware('ability:newsletter.write');
        Route::get('newsletter/ai/available', [NewsletterCampaignController::class, 'aiAvailable'])->middleware('ability:newsletter.read');
        Route::post('newsletter/campaigns/ai/plan', [NewsletterCampaignController::class, 'aiPlan'])->middleware('ability:newsletter.write');
    });
