<?php
// E2E probe: exercises the consent gate from the host, printing a JSON verdict.
require '/var/www/html/wp-load.php';
$consent = (string) get_option('blocky_allow_engine_download', '');
if ('yes' === $consent) {
    // Force a fresh build so the probe asserts the happy path, not a cache hit.
    delete_option(\Blocky\Core\Compiler\SiteStylesheet::OPTION_FILE);
    delete_option(\Blocky\Core\Compiler\SiteStylesheet::OPTION_HASH);
}
$rebuild = \Blocky\Core\Compiler\SiteStylesheet::from_globals()->rebuild();
echo json_encode([
    'consent' => $consent,
    'rebuild' => $rebuild,
    'status'  => (string) get_option('blocky_engine_status', ''),
]) . PHP_EOL;
