<?php

declare(strict_types=1);

namespace Phpresent\Shared\Domain\Plugin\Bible;

use Phpresent\Shared\Domain\Plugin\PluginInterface;

/**
 * Capability a plugin implements to supply scripture text (SDD §2/§12).
 * Lives in `Shared\Domain\Plugin`, not the Bible module itself, because
 * this is the contract *other* code (`PluginRegistry`, and any future
 * remote-API provider) depends on — same reasoning that keeps
 * `PermissionInterface`/`AuditLoggerInterface` in `Shared\Domain` rather
 * than Identity's (§18.2).
 *
 * Multiple providers can be registered at once (e.g. a local fixture and a
 * future remote API), each owning a disjoint set of translation ids —
 * `Bible\Application` fans a query out across every registered provider
 * and routes by which one's `translations()` lists the requested id.
 */
interface BibleProviderInterface extends PluginInterface
{
    /**
     * @return list<BibleTranslationSummary>
     */
    public function translations(): array;

    /**
     * @return list<BibleVerseRecord>
     */
    public function search(string $translationId, string $query, int $limit = 20): array;

    public function getPassage(
        string $translationId,
        string $book,
        int $chapter,
        ?int $startVerse = null,
        ?int $endVerse = null,
    ): ?BiblePassageRecord;
}
