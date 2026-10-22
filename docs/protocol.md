# Native Blost protocol, version 1

This is the wire contract between Blost and the receiving application. For installation and framework configuration, see the [usage guide](../README.md).

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

| JSON field | Accepted value | Publisher property |
| --- | --- | --- |
| `title` | Required nonempty string. | `title` |
| `content` | Required nonempty HTML string. | `content` |
| `excerpt` | String or null. | `excerpt` |
| `meta_title` | String or null. | `metaTitle` |
| `meta_description` | String or null. | `metaDescription` |
| `image_url` | Absolute HTTP(S) URL or null; no data URIs or local paths. | `imageUrl` |
| `language` | String or null; original Blost label, not necessarily an ISO code. | `language` |
| `published_at` | Valid RFC 3339 timestamp with timezone, or null. | `publishedAt` (`DateTimeImmutable` or null) |

Optional fields default to null when omitted. The route UUID is the authoritative identity and becomes the publisher's `id`, normalized to lowercase. Unknown JSON fields are ignored for forward compatibility.

The maximum JSON body is 2 MiB. Successful create and update both return HTTP 200:

```json
{"id":"b1057c0e-7a11-4c0d-9b1e-0b10570c0de1","url":"https://example.com/blog/article-title"}
```

The ID must match the request UUID. The URL must be an absolute public article URL. Blost treats malformed responses as failures. Repeating a PUT replaces the supplied content/metadata of the same article; it must not create another post. Acknowledge only durable completion. Protocol v1 supports publication and updates; it has no deletion or deferred-success endpoint.

## Retry and persistence semantics

The publisher must atomically create or update by the route UUID, enforced by a unique database index. Repeating a request after a timeout or a Blost bookkeeping failure must update the same CMS record. Concurrent creates require an upsert or unique-conflict handling; a transaction alone is insufficient.

Null optional metadata clears the corresponding stored value. A null `published_at` preserves the existing first publication date or uses the current time for a new article. The optional [ready-to-use packages](packages.md) provide these persistence semantics and public blog pages. Their public routes are outside `/blost/v1` and do not require the connector token. Future-dated articles are persisted immediately and become publicly visible at their publication time.

The publisher owns those persistence semantics; the API validates and passes the values through.

HTML is preserved for the host CMS's editorial content policy. Image URLs are passed through without downloading them. Drafts waiting for approval stay in Blost until published.

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

## Deployment verification

Follow the [Blost connection steps](../README.md#4-connect-your-application-in-blost) and [direct API check](../README.md#verify-the-api-directly). Verify an initial publication and an update against one CMS record before enabling unattended delivery. The Blost deployment must include the native Symfony / Laravel connector.
