<?php

declare(strict_types=1);

namespace Phpresent\SongbookPro\Domain\Service;

/**
 * Domain-facing port for obtaining the bearer token `SongbookProGraphQLClient`
 * sends as `Authorization: Bearer <token>` (§6.1 — auth is enforced by a
 * gateway in front of the resolvers, so a missing/invalid token fails every
 * request before the GraphQL query is even parsed).
 *
 * Which OAuth/B2C grant a server-side client like Phpresent should use to
 * obtain its own token is unresolved (SDD §6.3) — MSAL.js's interactive
 * authorization-code-with-PKCE flow only makes sense for a logged-in browser
 * session. `StaticAccessTokenProvider` implements the one option that needs
 * no further confirmation from SongbookPro (a per-install token issued out
 * of band); swapping in a client-credentials or refresh-token flow later is
 * a pure Infrastructure change behind this same port.
 */
interface AccessTokenProviderInterface
{
    public function getAccessToken(): string;
}
