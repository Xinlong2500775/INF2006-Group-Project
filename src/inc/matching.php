<?php
/**
 * find_matches_for_lost_item()
 *
 * Shared matching logic used by both matches.php (showing a student their
 * ranked matches) and dashboard.php (showing a real "possible matches"
 * count). Pulled into one function instead of being duplicated in both
 * places, so a fix or change to the matching logic only has to happen once.
 *
 * @param mysqli $conn        active DB connection
 * @param array  $lost_item   the lost item row (needs at least 'description')
 * @param float  $min_score   drop matches below this score (0.0–1.0).
 *                            Used to filter out noise like a 12% overlap
 *                            that just shares one common word.
 * @return array list of found-item rows, each with an added 'score' key.
 */
function find_matches_for_lost_item($conn, array $lost_item, float $min_score = 0.0): array {
    $found_result = mysqli_query($conn,
        "SELECT item_id, description, category, location, item_date FROM items WHERE type = 'found' AND status = 'open'");
    $found_items = [];
    while ($row = mysqli_fetch_assoc($found_result)) {
        $found_items[] = $row;
    }

    if (empty($found_items)) {
        return [];
    }

    $descriptions = array_map(fn($f) => str_replace('|', ' ', $f['description']), $found_items);
    $joined = implode('|', $descriptions);

    $script_args = escapeshellarg(__DIR__ . '/../../analytics/match_items.py') . " "
                 . escapeshellarg($lost_item['description']) . " "
                 . escapeshellarg($joined);

    // Try python3 first (Linux/EC2), fall back to python (Windows/XAMPP).
    $output = shell_exec("python3 " . $script_args);
    if ($output === null || trim($output) === '') {
        $output = shell_exec("python " . $script_args);
    }

    $scored = json_decode($output, true) ?: [];

    $matches = [];
    foreach ($scored as $s) {
        if ($s['score'] < $min_score) continue;
        foreach ($found_items as $f) {
            if (str_replace('|', ' ', $f['description']) === $s['description']) {
                $f['score'] = $s['score'];
                $matches[] = $f;
                break;
            }
        }
    }
    return $matches;
}
?>