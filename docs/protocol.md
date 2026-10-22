# Native Blost protocol, version 1

The configured connector URL is the API base, normally `https://example.com/blost/v1`. Every request requires `Authorization: Bearer <BLOST_TOKEN>`. Serve it over HTTPS and forward the Authorization header from your proxy to PHP. The Blost client requires port 443 and a publicly reachable host, checks DNS at socket creation, refuses private addresses, does not follow redirects, and uses a 20-second deadline. A local development connector needs a public HTTPS tunnel to test from Blost.

## Test connection

`GET /blost/v1/status` returns HTTP 200:

```json
{"protocol_version":"1","name":"My website"}
```

## Publish or update

`PUT /blost/v1/articles/{uuid}` with `Content-Type: application/json`:

```json
{
  "title": "Article title",
  "content": "<h2>Heading</h2><p>Full article HTML.</p>",
  "excerpt": "Short description",
  "meta_title": "SEO title",
  "meta_description": "SEO description",
  "image_url": "https://example.com/image.webp",
  "language": "Hungarian",
  "published_at": "2026-10-22T10:00:00.000Z"
}
```

`title` and `content` must be nonempty strings. Other fields accept a string or null and default to null when omitted. `language` is the original Blost language label, not necessarily an ISO code. `image_url` must be an absolute HTTP(S) URL; data URIs and local paths are rejected. `published_at` must be a valid RFC 3339 timestamp with timezone, or null. The route UUID is the authoritative identity; it is normalized to lowercase. Unknown JSON fields are ignored for forward compatibility.

The maximum JSON body is 2 MiB. Successful create and update both return HTTP 200:

```json
{"id":"b1057c0e-7a11-4c0d-9b1e-0b10570c0de1","url":"https://example.com/blog/article-title"}
```

The ID must match the request UUID. The URL must be an absolute public article URL. Blost treats malformed responses as failures. Repeating a PUT replaces the supplied content/metadata of the same article; it must not create another post. There is no deletion API, WordPress emulation, or deferred-success response in v1. Acknowledge only durable completion.

## Errors

| Status | Meaning |
| --- | --- |
| 400 | Malformed JSON |
| 401 | Missing, incorrect, or unconfigured token |
| 413 | JSON body exceeds 2 MiB |
| 415 | Unsupported Content-Type |
| 422 | Invalid UUID or article payload |
| 5xx | Publisher/storage failure, handled and logged by the host framework |

Package errors use `{"error":"Description"}`. Authentication failures include `WWW-Authenticate: Bearer`. Package responses use `Cache-Control: no-store`. Blost reports HTTP status codes without copying remote error bodies or tokens into error messages.

Set your proxy/PHP body limit to at least 2 MiB to allow this package to enforce its limit. Check that authentication and error responses are JSON in production and that stack traces are hidden. Keep normal application logging enabled for publisher failures. Blost only records a publication after validating the connector result; if its own database update fails, a retry uses the original UUID.

## Rollout

1. Install and configure the PHP package and your publisher implementation.
2. Verify the publisher's unique UUID index, real article URL, media mapping, and SEO output.
3. Deploy Blost's `feat/native-php-connector` branch with the existing CMS encryption configuration.
4. Select Symfony / Laravel, test and save the connection.
5. Publish a test article, edit and republish it, and confirm one database record and one public URL.
6. Enable unattended publishing after verifying scheduled delivery on the target site.
