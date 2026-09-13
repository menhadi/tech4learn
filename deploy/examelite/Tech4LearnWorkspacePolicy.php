<?php
namespace App\Support;

/** Shared content is a source, never an organisation's write target. */
final class Tech4LearnWorkspacePolicy
{
    public const FEATURES = ['subjects', 'questions', 'exams', 'taking', 'results'];

    public static function restrictions($items): array
    {
        if (!is_array($items) || count($items) > count(self::FEATURES)) {
            throw new \InvalidArgumentException('Invalid feature restrictions.');
        }
        foreach ($items as $item) {
            if (!is_string($item) || !in_array($item, self::FEATURES, true)) {
                throw new \InvalidArgumentException('Invalid feature restriction.');
            }
        }
        $items = array_values(array_unique($items));
        sort($items);
        return $items;
    }

    public static function mayUse(string $feature, array $restrictions): bool
    {
        return in_array($feature, self::FEATURES, true)
            && !in_array($feature, self::restrictions($restrictions), true);
    }

    public static function editAction(int $recordOrganisation, int $ownOrganisation, int $sharedOrganisation, bool $platformAdmin): string
    {
        if ($recordOrganisation <= 0 || $ownOrganisation <= 0 || $sharedOrganisation <= 0) {
            return 'deny';
        }
        if ($recordOrganisation === $sharedOrganisation) {
            return $platformAdmin ? 'edit-original' : ($ownOrganisation !== $sharedOrganisation ? 'copy' : 'deny');
        }
        return $recordOrganisation === $ownOrganisation ? 'edit-owned' : 'deny';
    }
}
