<?php

declare(strict_types=1);

namespace Corpuscom\Blost\Laravel;

use Corpuscom\Blost\Http\BlostApi;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

final class BlostController
{
    public function __construct(private readonly BlostApi $api)
    {
    }

    public function status(Request $request): JsonResponse
    {
        return $this->api->status($request);
    }

    public function publish(Request $request, string $id): JsonResponse
    {
        return $this->api->publish($request, $id);
    }
}
