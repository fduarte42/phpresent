<?php

declare(strict_types=1);

namespace Phpresent\Bible\Infrastructure\Plugin;

/**
 * A small, hand-picked set of well-known King James Version passages
 * (public domain) — deliberately **not** a full Bible. This exists to
 * prove `BibleProviderInterface`, search, and presentation genuinely work
 * end to end without depending on an unverified remote API (this
 * project's SongbookPro increments already document the cost of building
 * against an API shape nobody actually confirmed — see SDD §16.8a). A real
 * remote-API provider is a drop-in `BibleProviderInterface` implementation
 * once one is chosen and credentials exist; nothing about the interface
 * assumes a local/fixture data source.
 */
final class KjvFixtureData
{
    /**
     * @return array<string, array<int, array<int, string>>> book => chapter => verse => text
     */
    public static function verses(): array
    {
        return [
            'Genesis' => [
                1 => [
                    1 => 'In the beginning God created the heaven and the earth.',
                    2 => 'And the earth was without form, and void; and darkness was upon the '
                        . 'face of the deep. And the Spirit of God moved upon the face of the waters.',
                    3 => 'And God said, Let there be light: and there was light.',
                    4 => 'And God saw the light, that it was good: and God divided the light '
                        . 'from the darkness.',
                    5 => 'And God called the light Day, and the darkness he called Night. And '
                        . 'the evening and the morning were the first day.',
                ],
            ],
            'Psalm' => [
                23 => [
                    1 => 'The LORD is my shepherd; I shall not want.',
                    2 => 'He maketh me to lie down in green pastures: he leadeth me beside the '
                        . 'still waters.',
                    3 => 'He restoreth my soul: he leadeth me in the paths of righteousness for '
                        . "his name's sake.",
                    4 => 'Yea, though I walk through the valley of the shadow of death, I will '
                        . 'fear no evil: for thou art with me; thy rod and thy staff they comfort me.',
                    5 => 'Thou preparest a table before me in the presence of mine enemies: thou '
                        . 'anointest my head with oil; my cup runneth over.',
                    6 => 'Surely goodness and mercy shall follow me all the days of my life: and '
                        . 'I will dwell in the house of the LORD for ever.',
                ],
            ],
            'John' => [
                3 => [
                    16 => 'For God so loved the world, that he gave his only begotten Son, that '
                        . 'whosoever believeth in him should not perish, but have everlasting life.',
                    17 => 'For God sent not his Son into the world to condemn the world; but '
                        . 'that the world through him might be saved.',
                ],
            ],
            'Romans' => [
                8 => [
                    28 => 'And we know that all things work together for good to them that love '
                        . 'God, to them who are the called according to his purpose.',
                ],
            ],
            'Philippians' => [
                4 => [
                    13 => 'I can do all things through Christ which strengtheneth me.',
                ],
            ],
            '1 Corinthians' => [
                13 => [
                    4 => 'Charity suffereth long, and is kind; charity envieth not; charity '
                        . 'vaunteth not itself, is not puffed up,',
                    5 => 'Doth not behave itself unseemly, seeketh not her own, is not easily '
                        . 'provoked, thinketh no evil;',
                    6 => 'Rejoiceth not in iniquity, but rejoiceth in the truth;',
                    7 => 'Beareth all things, believeth all things, hopeth all things, '
                        . 'endureth all things.',
                ],
            ],
        ];
    }
}
