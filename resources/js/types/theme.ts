export type ThemeScope = 'global' | 'song' | 'section';
export type TextAlign = 'left' | 'center' | 'right';

export interface Theme {
    id: string;
    name: string;
    scope: ThemeScope;
    songExternalId: string | null;
    sectionType: string | null;
    backgroundColor: string | null;
    backgroundMediaAssetId: string | null;
    fontFamily: string | null;
    fontColor: string | null;
    fontSizeScale: number;
    textAlign: TextAlign;
    createdAt: string;
    updatedAt: string;
}

export interface ThemeInput {
    name: string;
    scope: ThemeScope;
    songExternalId?: string | null;
    sectionType?: string | null;
    backgroundColor?: string | null;
    backgroundMediaAssetId?: string | null;
    fontFamily?: string | null;
    fontColor?: string | null;
    fontSizeScale?: number;
    textAlign?: TextAlign;
}
