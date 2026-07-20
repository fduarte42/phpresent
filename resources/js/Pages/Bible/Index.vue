<script setup lang="ts">
import { NButton, NCard, NEmpty, NInput, NInputNumber, NPopconfirm, NSelect, NSpace, NTag } from 'naive-ui';
import type { SelectOption } from 'naive-ui';
import { computed, onMounted, ref } from 'vue';
import { useBibleStore } from '@/stores/useBibleStore';
import type { BibleBookmark, BibleTranslation, BibleVerse } from '@/types/bible';

const props = defineProps<{ translations: BibleTranslation[]; bookmarks: BibleBookmark[] }>();

const store = useBibleStore();
const translationId = ref('');
const searchQuery = ref('');
const bookmarkLabel = ref('');

const passageBook = ref('');
const passageChapter = ref<number | null>(null);
const passageStartVerse = ref<number | null>(null);
const passageEndVerse = ref<number | null>(null);

onMounted(() => {
    store.setInitial(props.translations, props.bookmarks);
    translationId.value = props.translations[0]?.id ?? '';
});

const translationOptions = computed<SelectOption[]>(() =>
    store.translations.map((translation) => ({
        label: `${translation.name} (${translation.abbreviation})`,
        value: translation.id,
    })),
);

let debounceHandle: ReturnType<typeof setTimeout> | undefined;
function onSearch(value: string): void {
    searchQuery.value = value;
    clearTimeout(debounceHandle);
    debounceHandle = setTimeout(() => {
        void store.search(translationId.value, value);
    }, 250);
}

function viewVerse(verse: BibleVerse): void {
    passageBook.value = verse.book;
    passageChapter.value = verse.chapter;
    passageStartVerse.value = null;
    passageEndVerse.value = null;
    void store.loadPassage(translationId.value, verse.book, verse.chapter);
}

function onLoadPassage(): void {
    if (passageBook.value.trim() === '' || passageChapter.value === null) {
        return;
    }

    void store.loadPassage(
        translationId.value,
        passageBook.value.trim(),
        passageChapter.value,
        passageStartVerse.value ?? undefined,
        passageEndVerse.value ?? undefined,
    );
}

async function onBookmarkPassage(): Promise<void> {
    const passage = store.currentPassage;
    if (passage === null) {
        return;
    }

    const verseNumbers = passage.verses.map((verse) => verse.verse);
    await store.createBookmark(
        translationId.value,
        passage.book,
        passage.chapter,
        passageStartVerse.value ?? Math.min(...verseNumbers),
        passageEndVerse.value ?? Math.max(...verseNumbers),
        bookmarkLabel.value.trim() || null,
    );
    bookmarkLabel.value = '';
}

function viewBookmark(bookmark: BibleBookmark): void {
    translationId.value = bookmark.translationId;
    passageBook.value = bookmark.book;
    passageChapter.value = bookmark.chapter;
    passageStartVerse.value = bookmark.startVerse;
    passageEndVerse.value = bookmark.endVerse;
    void store.loadPassage(
        bookmark.translationId,
        bookmark.book,
        bookmark.chapter,
        bookmark.startVerse ?? undefined,
        bookmark.endVerse ?? undefined,
    );
}

function bookmarkReference(bookmark: BibleBookmark): string {
    const range =
        bookmark.startVerse === null
            ? ''
            : bookmark.endVerse !== null && bookmark.endVerse !== bookmark.startVerse
              ? `:${bookmark.startVerse}-${bookmark.endVerse}`
              : `:${bookmark.startVerse}`;

    return `${bookmark.book} ${bookmark.chapter}${range}`;
}
</script>

<template>
    <n-space vertical size="large">
        <n-card title="Search">
            <n-space vertical>
                <n-space>
                    <n-select
                        v-model:value="translationId"
                        :options="translationOptions"
                        style="width: 260px"
                    />
                    <n-input
                        :value="searchQuery"
                        placeholder="Search scripture text..."
                        clearable
                        style="width: 320px"
                        @update:value="onSearch"
                    />
                </n-space>

                <n-space vertical size="small">
                    <n-button
                        v-for="(result, index) in store.searchResults"
                        :key="index"
                        text
                        style="justify-content: flex-start; width: 100%; text-align: left"
                        @click="viewVerse(result)"
                    >
                        <strong>{{ result.book }} {{ result.chapter }}:{{ result.verse }}</strong>
                        &nbsp;— {{ result.text }}
                    </n-button>
                    <n-empty
                        v-if="searchQuery.trim() !== '' && store.searchResults.length === 0"
                        description="No matches"
                        size="small"
                    />
                </n-space>
            </n-space>
        </n-card>

        <n-space :wrap="false" align="start" size="large">
            <n-card title="Passage" style="flex: 1; min-width: 0">
                <n-space vertical>
                    <n-space align="end">
                        <n-input v-model:value="passageBook" placeholder="Book (e.g. Psalm)" style="width: 160px" />
                        <n-input-number v-model:value="passageChapter" placeholder="Chapter" style="width: 110px" />
                        <n-input-number
                            v-model:value="passageStartVerse"
                            placeholder="Start verse"
                            style="width: 120px"
                        />
                        <n-input-number v-model:value="passageEndVerse" placeholder="End verse" style="width: 120px" />
                        <n-button type="primary" :loading="store.isLoadingPassage" @click="onLoadPassage">
                            Load
                        </n-button>
                    </n-space>

                    <template v-if="store.currentPassage">
                        <div style="padding: 12px; background: rgba(128, 128, 128, 0.08); border-radius: 6px">
                            <p v-for="verse in store.currentPassage.verses" :key="verse.verse" style="margin: 4px 0">
                                <n-tag size="small" style="margin-right: 8px">{{ verse.verse }}</n-tag>
                                {{ verse.text }}
                            </p>
                        </div>

                        <n-space align="center">
                            <n-input
                                v-model:value="bookmarkLabel"
                                placeholder="Optional label (e.g. Sermon text)"
                                style="width: 260px"
                            />
                            <n-button @click="onBookmarkPassage">Save Bookmark</n-button>
                        </n-space>
                    </template>
                    <n-empty v-else description="Load a passage to preview it here" />
                </n-space>
            </n-card>

            <n-card title="Bookmarks" style="width: 320px; flex-shrink: 0">
                <n-space vertical size="small">
                    <div
                        v-for="bookmark in store.bookmarks"
                        :key="bookmark.id"
                        style="display: flex; align-items: center; justify-content: space-between"
                    >
                        <n-button text style="text-align: left" @click="viewBookmark(bookmark)">
                            {{ bookmark.label ?? bookmarkReference(bookmark) }}
                        </n-button>
                        <n-popconfirm @positive-click="store.removeBookmark(bookmark.id)">
                            <template #trigger>
                                <n-button size="tiny" quaternary>Remove</n-button>
                            </template>
                            Remove this bookmark?
                        </n-popconfirm>
                    </div>
                    <n-empty v-if="store.bookmarks.length === 0" description="No bookmarks yet" size="small" />
                </n-space>
            </n-card>
        </n-space>
    </n-space>
</template>
