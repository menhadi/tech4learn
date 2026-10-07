<?php

namespace App\Support;

use Illuminate\Support\Collection;

class FlashcardStudyHierarchy
{
    public static function build(Collection $sets): Collection
    {
        $sets = self::setsWithCards($sets);

        return $sets
            ->sortBy(fn ($set) => sprintf('%010d|%010d|%010d|%010d|%s',
                (int) ($set->subject?->ordering ?: 999999),
                (int) ($set->topic?->display_order ?: 999999),
                (int) ($set->stopic?->display_order ?: 999999),
                (int) ($set->display_order ?: 999999),
                self::text($set->title)
            ))
            ->groupBy(fn ($set) => self::sectionKey($set))
            ->map(function (Collection $sectionSets, string $sectionKey) {
                $firstSectionSet = $sectionSets->first();

                $topics = $sectionSets
                    ->groupBy(fn ($set) => self::topicKey($set))
                    ->map(function (Collection $topicSets, string $topicKey) use ($sectionKey) {
                        $firstTopicSet = $topicSets->first();

                        $subtopics = $topicSets
                            ->groupBy(fn ($set) => self::subtopicKey($set))
                            ->map(function (Collection $subtopicSets, string $subtopicKey) use ($sectionKey, $topicKey) {
                                $firstSubtopicSet = $subtopicSets->first();

                                return [
                                    'key' => $subtopicKey,
                                    'label' => self::subtopicLabel($firstSubtopicSet),
                                    'params' => array_filter([
                                        'study_section' => $sectionKey,
                                        'study_topic' => $topicKey,
                                        'study_subtopic' => $subtopicKey,
                                    ]),
                                    'sets' => $subtopicSets->values(),
                                    'sets_count' => $subtopicSets->count(),
                                    'cards_count' => self::cardsCount($subtopicSets),
                                ];
                            })
                            ->filter(fn (array $subtopic) => $subtopic['cards_count'] > 0)
                            ->values();

                        return [
                            'key' => $topicKey,
                            'label' => self::topicLabel($firstTopicSet),
                            'params' => array_filter([
                                'study_section' => $sectionKey,
                                'study_topic' => $topicKey,
                            ]),
                            'subtopics' => $subtopics,
                            'sets_count' => $topicSets->count(),
                            'cards_count' => self::cardsCount($topicSets),
                        ];
                    })
                    ->filter(fn (array $topic) => $topic['cards_count'] > 0)
                    ->values();

                return [
                    'key' => $sectionKey,
                    'label' => self::sectionLabel($firstSectionSet),
                    'params' => ['study_section' => $sectionKey],
                    'topics' => $topics,
                    'sets_count' => $sectionSets->count(),
                    'cards_count' => self::cardsCount($sectionSets),
                ];
            })
            ->filter(fn (array $section) => $section['cards_count'] > 0)
            ->values();
    }

    public static function filterSets(Collection $sets, ?string $sectionKey = null, ?string $topicKey = null, ?string $subtopicKey = null): Collection
    {
        return self::setsWithCards($sets)
            ->filter(function ($set) use ($sectionKey, $topicKey, $subtopicKey) {
                if ($sectionKey && self::sectionKey($set) !== $sectionKey) {
                    return false;
                }

                if ($topicKey && self::topicKey($set) !== $topicKey) {
                    return false;
                }

                if ($subtopicKey && self::subtopicKey($set) !== $subtopicKey) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    public static function selectionTitle(Collection $hierarchy, ?string $sectionKey = null, ?string $topicKey = null, ?string $subtopicKey = null): ?string
    {
        foreach ($hierarchy as $section) {
            if ($sectionKey && $section['key'] !== $sectionKey) {
                continue;
            }

            if (! $topicKey) {
                return $section['label'];
            }

            foreach ($section['topics'] as $topic) {
                if ($topic['key'] !== $topicKey) {
                    continue;
                }

                if (! $subtopicKey) {
                    return $section['label'].' / '.$topic['label'];
                }

                foreach ($topic['subtopics'] as $subtopic) {
                    if ($subtopic['key'] === $subtopicKey) {
                        return $section['label'].' / '.$topic['label'].' / '.$subtopic['label'];
                    }
                }
            }
        }

        return null;
    }

    private static function setsWithCards(Collection $sets): Collection
    {
        return $sets
            ->filter(fn ($set) => ($set->cards?->count() ?? 0) > 0)
            ->values();
    }

    private static function sectionKey($set): string
    {
        return self::modelKey('subject', $set->subject)
            ?: self::modelKey('category', $set->category)
            ?: self::modelKey('group', $set->group)
            ?: 'general';
    }

    private static function topicKey($set): string
    {
        return self::modelKey('topic', $set->topic)
            ?: self::modelKey('subcategory', $set->subcategory)
            ?: 'all';
    }

    private static function subtopicKey($set): string
    {
        return self::modelKey('subtopic', $set->stopic) ?: 'all';
    }

    private static function sectionLabel($set): string
    {
        return self::modelText($set->subject, ['subject_name', 'name', 'title'])
            ?: self::modelText($set->category, ['title', 'name', 'category_name'])
            ?: self::modelText($set->group, ['group_name', 'name'])
            ?: 'General Study Cards';
    }

    private static function topicLabel($set): string
    {
        return self::modelText($set->topic, ['name', 'topic_name', 'title'])
            ?: self::modelText($set->subcategory, ['title', 'name', 'category_name'])
            ?: 'All Topics';
    }

    private static function subtopicLabel($set): string
    {
        return self::modelText($set->stopic, ['name', 'sub_topic_name', 'title'])
            ?: 'All Cards';
    }

    private static function cardsCount(Collection $sets): int
    {
        return (int) $sets->sum(fn ($set) => $set->cards?->count() ?? 0);
    }

    private static function modelKey(string $prefix, $model): ?string
    {
        $id = data_get($model, 'id');

        return $id ? $prefix.'-'.$id : null;
    }

    private static function modelText($model, array $fields): string
    {
        if (! $model) {
            return '';
        }

        foreach ($fields as $field) {
            $text = self::text(data_get($model, $field));

            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private static function text($value): string
    {
        if (is_array($value)) {
            return self::text($value['en'] ?? reset($value));
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return '';
            }

            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return self::text($decoded);
            }

            return $value;
        }

        return trim((string) $value);
    }
}
