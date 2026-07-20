export interface Slide {
    lines: string[];
    sectionType: string | null;
    sectionLabel: string | null;
}

export interface SlideDeck {
    sourceType: string;
    sourceId: string | null;
    slides: Slide[];
}

export interface PresentationSession {
    id: string;
    currentDeck: SlideDeck | null;
    currentSlideIndex: number;
    isBlanked: boolean;
    isFrozen: boolean;
    lyricsHidden: boolean;
    fontSizeAdjust: number;
    emergencyMessage: string | null;
    updatedAt: string;
}
