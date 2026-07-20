export interface DisplaySettings {
    theme: string | null;
    safeAreaPercent: number;
    fontScale: number;
    showLowerThird: boolean;
    watermarkText: string | null;
}

export type DisplayRole = 'main' | 'operator' | 'confidence_monitor' | 'audience' | 'custom';

export interface Display {
    id: string;
    name: string;
    role: DisplayRole;
    settings: DisplaySettings;
    createdAt: string;
    updatedAt: string;
}
