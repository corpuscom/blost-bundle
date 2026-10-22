<?php

declare(strict_types=1);

namespace Corpuscom\Blost\Http;

use Corpuscom\Blost\Article;
use Corpuscom\Blost\Contract\ArticlePublisher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class BlostApi
{
    public const VERSION = '1';
    public const MAX_BODY_BYTES = 2_097_152;

    public function __construct(
        private readonly ArticlePublisher $publisher,
        private readonly string $token,
        private readonly string $name = 'Blost connector',
    ) {
    }

    public function status(Request $request): JsonResponse
    {
        return $this->authenticate($request) ?? $this->json(['protocol_version' => self::VERSION, 'name' => $this->name]);
    }

    public function publish(Request $request, string $id): JsonResponse
    {
        if ($error = $this->authenticate($request)) {
            return $error;
        }
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $id)) {
            return $this->json(['error' => 'Invalid article UUID.'], 422);
        }
        if (strtolower(trim(explode(';', $request->headers->get('Content-Type', ''))[0])) !== 'application/json') {
            return $this->json(['error' => 'Content-Type must be application/json.'], 415);
        }
        // Bound reads even when Content-Length is absent or untrusted.
        $body = stream_get_contents($request->getContent(true), self::MAX_BODY_BYTES + 1);
        if ($body === false || strlen($body) > self::MAX_BODY_BYTES) {
            return $this->json(['error' => 'Request body exceeds 2 MiB.'], 413);
        }
        try {
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['error' => 'Invalid JSON.'], 400);
        }
        if (!is_array($data) || array_is_list($data)) {
            return $this->json(['error' => 'Expected a JSON object.'], 422);
        }
        foreach (['title', 'content'] as $field) {
            if (!is_string($data[$field] ?? null) || trim($data[$field]) === '') {
                return $this->json(['error' => "$field must be a non-empty string."], 422);
            }
        }
        foreach (['excerpt', 'meta_title', 'meta_description', 'image_url', 'language', 'published_at'] as $field) {
            if (isset($data[$field]) && !is_string($data[$field])) {
                return $this->json(['error' => "$field must be a string or null."], 422);
            }
        }
        $image = $data['image_url'] ?? null;
        if ($image !== null && (!filter_var($image, FILTER_VALIDATE_URL) || !in_array(parse_url($image, PHP_URL_SCHEME), ['http', 'https'], true))) {
            return $this->json(['error' => 'image_url must be an absolute HTTP(S) URL or null.'], 422);
        }
        $publishedAt = null;
        if (isset($data['published_at'])) {
            $value = $data['published_at'];
            if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
                return $this->json(['error' => 'published_at must be an RFC 3339 timestamp or null.'], 422);
            }
            try {
                $publishedAt = new \DateTimeImmutable($value);
                $errors = \DateTimeImmutable::getLastErrors();
                if ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) {
                    throw new \InvalidArgumentException();
                }
            } catch (\Exception) {
                return $this->json(['error' => 'Invalid published_at timestamp.'], 422);
            }
        }
        $article = new Article(strtolower($id), $data['title'], $data['content'], $data['excerpt'] ?? null,
            $data['meta_title'] ?? null, $data['meta_description'] ?? null, $image, $data['language'] ?? null, $publishedAt);
        // Let the framework report/log publisher exceptions as 5xx. Never acknowledge failed persistence.
        $result = $this->publisher->publish($article);

        return $this->json(['id' => $article->id, 'url' => $result->url]);
    }

    private function authenticate(Request $request): ?JsonResponse
    {
        $authorization = $request->headers->get('Authorization', '');
        if ($this->token === '' || !preg_match('/^Bearer ([^\s]+)$/iD', $authorization, $matches)
            || !hash_equals($this->token, $matches[1])) {
            return $this->json(['error' => 'Unauthorized.'], 401, ['WWW-Authenticate' => 'Bearer']);
        }

        return null;
    }

    private function json(array $data, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, ['Cache-Control' => 'no-store', ...$headers]);
    }
}
