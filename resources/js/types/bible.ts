export interface BibleTranslation {
    providerId: string;
    id: string;
    name: string;
    abbreviation: string;
    language: string;
}

export interface BibleVerse {
    book: string;
    chapter: number;
    verse: number;
    text: string;
}

export interface BiblePassage {
    book: string;
    chapter: number;
    verses: BibleVerse[];
}

export interface BibleBookmark {
    id: string;
    translationId: string;
    book: string;
    chapter: number;
    startVerse: number | null;
    endVerse: number | null;
    label: string | null;
    createdAt: string;
}
