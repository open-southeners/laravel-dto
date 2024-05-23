<?php

use Illuminate\Support\Facades\Route;
use Workbench\App\DataTransferObjects\UpdatePostWithRouteBindingData;
use Workbench\App\DataTransferObjects\UpdatePostWithTags;
use Workbench\App\DataTransferObjects\UpdateTagData;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::patch('post/{post}', function (UpdatePostWithRouteBindingData $data) {
    return response()->json($data->toArray());
})->middleware('api');

Route::patch('tags/{tag}', function (UpdateTagData $data) {
    return response()->json([
        'data' => $data->toArray(),
    ]);
})->middleware('api');
