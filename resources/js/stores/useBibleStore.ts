import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { BibleBookmark, BiblePassage, BibleTranslation, BibleVerse } from '@/types/bible';

export const useBibleStore = defineStore('bible', () => {
    const translations = ref<BibleTranslation[]>([]);
    const bookmarks = ref<BibleBookmark[]>([]);
    const searchResults = ref<BibleVerse[]>([]);
    const currentPassage = ref<BiblePassage | null>(null);
    const isSearching = ref(false);
    const isLoadingPassage = ref(false);

    function setInitial(initialTranslations: BibleTranslation[], initialBookmarks: BibleBookmark[]): void {
        translations.value = initialTranslations;
        bookmarks.value = initialBookmarks;
    }

    async function search(translationId: string, query: string): Promise<void> {
        if (query.trim() === '') {
            searchResults.value = [];
            return;
        }

        isSearching.value = true;
        try {
            const params = new URLSearchParams({ translationId, q: query });
            const response = await fetch(`/api/bible/search?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                throw new Error(`Search failed: ${response.status}`);
            }
            const body = (await response.json()) as { data: BibleVerse[] };
            searchResults.value = body.data;
        } finally {
            isSearching.value = false;
        }
    }

    async function loadPassage(
        translationId: string,
        book: string,
        chapter: number,
        startVerse?: number,
        endVerse?: number,
    ): Promise<void> {
        isLoadingPassage.value = true;
        try {
            const params = new URLSearchParams({ translationId, book, chapter: String(chapter) });
            if (startVerse !== undefined) {
                params.set('startVerse', String(startVerse));
            }
            if (endVerse !== undefined) {
                params.set('endVerse', String(endVerse));
            }

            const response = await fetch(`/api/bible/passage?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                currentPassage.value = null;
                return;
            }

            const body = (await response.json()) as { data: BiblePassage };
            currentPassage.value = body.data;
        } finally {
            isLoadingPassage.value = false;
        }
    }

    async function createBookmark(
        translationId: string,
        book: string,
        chapter: number,
        startVerse: number | null,
        endVerse: number | null,
        label: string | null,
    ): Promise<void> {
        const response = await fetch('/api/bible/bookmarks', {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ translationId, book, chapter, startVerse, endVerse, label }),
        });

        if (!response.ok) {
            throw new Error(`Failed to save bookmark: ${response.status}`);
        }

        const body = (await response.json()) as { data: BibleBookmark };
        bookmarks.value = [body.data, ...bookmarks.value];
    }

    async function removeBookmark(id: string): Promise<void> {
        const response = await fetch(`/api/bible/bookmarks/${id}`, { method: 'DELETE' });

        if (!response.ok && response.status !== 404) {
            throw new Error(`Failed to remove bookmark: ${response.status}`);
        }

        bookmarks.value = bookmarks.value.filter((bookmark) => bookmark.id !== id);
    }

    return {
        translations,
        bookmarks,
        searchResults,
        currentPassage,
        isSearching,
        isLoadingPassage,
        setInitial,
        search,
        loadPassage,
        createBookmark,
        removeBookmark,
    };
});
