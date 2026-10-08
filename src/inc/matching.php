<?php
/**
 * find_matches_for_lost_item()
 *
 * Shared matching logic used by both matches.php (showing a student their
 * ranked matches) and dashboard.php (showing a real "possible matches"
 * count). Kept in one function so a change to the matching logic only has
 * to happen once.
 *
 * Each item is matched on its category + description + location combined
 * (see analytics/match_items.py). The Python script returns the position of
 * each matching found item in the list we send it, so scores are mapped back
 * by position, which stays correct even if two found items have identical
 * descriptions.
 *
 * @param mysqli $conn        active DB connection
 * @param array  $lost_item   the lost item row (category, description, location)
 * @param float  $min_score   drop matches below this score (0.0 to 1.0).
 *                            Used to filter out noise like a 12% overlap
 *                            that just shares one common word.
 * @return array list of found-item rows, each with an added 'score' key,
 *               highest score first.
 */
function matching_text(array $item): string {
    // '|' separates items when passed to Python, so it must not appear inside one
    $text = $item['category'] . ' ' . $item['description'] . ' ' . $item['location'];
    return str_replace('|', ' ', $text);
}

function find_matches_for_lost_item($conn, array $lost_item, float $min_score = 0.0): array {
    $found_result = mysqli_query($conn,
        "SELECT item_id, description, category, location, item_date FROM items WHERE type = 'found' AND status = 'open' ORDER BY item_id");
    $found_items = [];
    while ($row = mysqli_fetch_assoc($found_result)) {
        $found_items[] = $row;
    }

    if (empty($found_items)) {
        return [];
    }

    $joined = implode('|', array_map('matching_text', $found_items));

    $script_args = escapeshellarg(__DIR__ . '/../../analytics/match_items.py') . " "
                 . escapeshellarg(matching_text($lost_item)) . " "
                 . escapeshellarg($joined);

    // On Windows "python3" is often a Microsoft Store shortcut that hangs when
    // run from Apache, so it is never used there: try "python", then the "py"
    // launcher. On Linux/EC2 use "python3". Error output is discarded so only
    // the JSON result is read.
    if (PHP_OS_FAMILY === 'Windows') {
        $interpreters = ['python', 'py -3'];
        $discard_errors = ' 2>NUL';
    } else {
        $interpreters = ['python3', 'python'];
        $discard_errors = ' 2>/dev/null';
    }
    $output = null;
    foreach ($interpreters as $py) {
        $output = shell_exec($py . " " . $script_args . $discard_errors);
        if ($output !== null && str_starts_with(ltrim($output), '[')) {
            break;   // got the JSON list back
        }
    }

    $scored = json_decode((string) $output, true) ?: [];

    $matches = [];
    foreach ($scored as $s) {
        if ($s['score'] < $min_score) continue;
        if (!isset($found_items[$s['index']])) continue;
        $f = $found_items[$s['index']];
        $f['score'] = $s['score'];
        $matches[] = $f;
    }
    return $matches;
}
?>