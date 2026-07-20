export type MediaKind = 'image' | 'video' | 'audio' | 'document';

export interface MediaAsset {
    id: string;
    filename: string;
    mimeType: string;
    sizeBytes: number;
    kind: MediaKind;
    width: number | null;
    height: number | null;
    uploadedAt: string;
}
