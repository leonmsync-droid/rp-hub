<?php
declare(strict_types=1);

// One live figure per product, read from the products' own output files on the same server.
// Missing data just means no figure; the hub never fails because a product is down.
final class Stats
{
    public static function for(string $key): ?array
    {
        return match ($key) {
            'cs2' => self::cs2(),
            'github' => self::github(),
            default => null,
        };
    }

    // Sponsor Lens: matches with a measured audience, from the stats.json its hourly summary job writes.
    private static function cs2(): ?array
    {
        foreach (self::sponsorLensDirs() as $dir) {
            $stats = is_file("{$dir}/storage/stats.json") ? json_decode((string) file_get_contents("{$dir}/storage/stats.json"), true) : null;
            if (!empty($stats['matches_measured'])) {
                return [
                    'value' => number_format((int) $stats['matches_measured']),
                    'label' => 'matches measured · ' . number_format((int) $stats['teams_tracked']) . ' teams',
                ];
            }
        }
        return null;
    }

    // The next CS2 Major as PandaScore lists it, from the next_major.json Sponsor Lens's hourly sync writes.
    // Null until PandaScore lists one, or if the file is missing; the hand-typed countdown is used then.
    public static function nextMajor(): ?array
    {
        foreach (self::sponsorLensDirs() as $dir) {
            $file = "{$dir}/storage/next_major.json";
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            if (is_array($data) && !empty($data['major']['begin_at'])) {
                return $data['major'];
            }
        }
        return null;
    }

    private static function sponsorLensDirs(): array
    {
        return array_filter([getenv('SPONSOR_LENS_DIR') ?: '', dirname(__DIR__, 2) . '/sponsor-lens', dirname(__DIR__, 3) . '/sponsor-lens']);
    }

    // Rising Repos: how many repos the latest score file ranks.
    private static function github(): ?array
    {
        $dir = getenv('RP_GITHUB_DIR') ?: dirname(__DIR__, 2) . '/rp-github';
        $files = glob($dir . '/data/scores/????-??-??.json') ?: [];
        if (!$files) {
            return null;
        }
        sort($files);
        $rows = json_decode((string) file_get_contents(end($files)), true);
        if (!is_array($rows) || !$rows) {
            return null;
        }
        return ['value' => number_format(count($rows)), 'label' => 'repos ranked on ' . gmdate('j M', strtotime(basename(end($files), '.json')))];
    }
}
