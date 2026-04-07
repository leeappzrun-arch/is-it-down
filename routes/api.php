<?php

use App\Http\Controllers\Api\V1\RecipientController;
use App\Http\Controllers\Api\V1\RecipientGroupController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\ServiceGroupController;
use App\Http\Controllers\Api\V1\ServiceTemplateController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware('auth.api-key')
    ->group(function (): void {
        Route::apiResource('recipients', RecipientController::class)
            ->only(['index', 'show'])
            ->middleware('api.permission:recipients,read');

        Route::apiResource('recipients', RecipientController::class)
            ->only(['store', 'update', 'destroy'])
            ->middleware('api.permission:recipients,write');

        Route::apiResource('recipient-groups', RecipientGroupController::class)
            ->only(['index', 'show'])
            ->middleware('api.permission:recipients,read');

        Route::apiResource('recipient-groups', RecipientGroupController::class)
            ->only(['store', 'update', 'destroy'])
            ->middleware('api.permission:recipients,write');

        Route::apiResource('services', ServiceController::class)
            ->only(['index', 'show'])
            ->middleware('api.permission:services,read');

        Route::apiResource('services', ServiceController::class)
            ->only(['store', 'update', 'destroy'])
            ->middleware('api.permission:services,write');

        Route::apiResource('service-templates', ServiceTemplateController::class)
            ->only(['index', 'show'])
            ->middleware('api.permission:templates,read');

        Route::apiResource('service-templates', ServiceTemplateController::class)
            ->only(['store', 'update', 'destroy'])
            ->middleware('api.permission:templates,write');

        Route::apiResource('service-groups', ServiceGroupController::class)
            ->only(['index', 'show'])
            ->middleware('api.permission:services,read');

        Route::apiResource('service-groups', ServiceGroupController::class)
            ->only(['store', 'update', 'destroy'])
            ->middleware('api.permission:services,write');

        Route::apiResource('users', UserController::class)
            ->only(['index', 'show'])
            ->middleware('api.permission:users,read');

        Route::apiResource('users', UserController::class)
            ->only(['store', 'update', 'destroy'])
            ->middleware('api.permission:users,write');
    });
