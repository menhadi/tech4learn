<?php
// CLI-only, read-only configuration/schema checks; never prints secrets or records.
if (PHP_SAPI !== 'cli' || $argc !== 2) {
    fwrite(STDERR, "Usage: php check-native-readiness.php /absolute/release/platform\n");
    exit(2);
}
$root = realpath($argv[1]);
if (!$root || !is_file($root.'/vendor/autoload.php') || !is_file($root.'/.env')) {
    fwrite(STDERR, "BLOCKED: native dependencies or private environment are missing.\n");
    exit(1);
}
require $root.'/vendor/autoload.php';
$blocked = false;
$check = function (bool $ok, string $label) use (&$blocked): void {
    echo ($ok ? 'PASS: ' : 'BLOCKED: ').$label."\n";
    $blocked = $blocked || !$ok;
};
try {
    // Parse without altering process environment or booting application providers.
    $env = Dotenv\Dotenv::parse(file_get_contents($root.'/.env'));
    $check(($env['APP_ENV'] ?? '') === 'production', 'production environment');
    $check(($env['APP_DEBUG'] ?? '') === 'false', 'debug disabled');
    $key = base64_decode(substr($env['APP_KEY'] ?? '', 7), true);
    $check(str_starts_with($env['APP_KEY'] ?? '', 'base64:') && $key !== false && strlen($key) === 32, 'application encryption key');
    $check(($env['APP_URL'] ?? '') === 'https://tech4learn.com', 'canonical HTTPS website');
    $check(($env['ATTENDANCE_API_URL'] ?? '') === 'https://tech4learn.com/api/v1', 'same-domain HTTPS attendance API');
    $check(($env['SESSION_SECURE_COOKIE'] ?? '') === 'true' && ($env['SESSION_COOKIE'] ?? '') === '__Host-tech4learn_native_session' && empty($env['SESSION_DOMAIN']), 'host-only secure native session');
    $safeDb = ($env['DB_CONNECTION'] ?? '') === 'mysql'
        && ($env['DB_HOST'] ?? '') === '127.0.0.1'
        && ($env['DB_PORT'] ?? '') === '3306'
        && ($env['DB_DATABASE'] ?? '') === 'tech4learn_exams'
        && ($env['DB_USERNAME'] ?? '') === 'tech4learn_exams'
        && !empty($env['DB_PASSWORD']) && empty($env['DATABASE_URL']);
    $check($safeDb, 'dedicated local exam database and account');
    if ($blocked) {
        exit(1);
    }
    $db = new PDO('mysql:host=127.0.0.1;port=3306;dbname=tech4learn_exams;charset=utf8mb4', $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    $db->exec('SET SESSION TRANSACTION READ ONLY');
    $db->beginTransaction();
    $applied = $db->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
    $expected = array_map(fn ($file) => basename($file, '.php'), glob($root.'/database/migrations/*.php'));
    $check(count(array_diff($expected, $applied)) === 0, 'all checked native migrations applied');
    $check((int)$db->query("SELECT COUNT(*) FROM organizations WHERE domain = 'tech4learn.com' AND status = 'active'")->fetchColumn() === 1, 'active canonical website organisation');
    $check((int)$db->query("SELECT COUNT(*) FROM organizations WHERE status = 'active' AND JSON_EXTRACT(settings, '$.is_primary_platform') = true")->fetchColumn() === 1
        && (int)$db->query("SELECT COUNT(*) FROM organizations WHERE domain = 'tech4learn.com' AND status = 'active' AND JSON_EXTRACT(settings, '$.is_primary_platform') = true")->fetchColumn() === 1, 'unique active primary platform realm on canonical website');
    $ledgerExists = (int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'tech4learn_exams' AND table_name = 'foundation_platform_administrators'")->fetchColumn() === 1;
    $check($ledgerExists, 'explicit native administrator identity ledger installed');
    if ($ledgerExists) {
        $check((int)$db->query("SELECT COUNT(*) FROM foundation_platform_administrators p JOIN users u ON u.id = p.user_id JOIN organizations o ON o.id = p.organization_id WHERE o.domain = 'tech4learn.com' AND o.status = 'active' AND JSON_EXTRACT(o.settings, '$.is_primary_platform') = true AND u.is_platform_admin = 1 AND u.status = 'Active' AND u.deleted = 0")->fetchColumn() === 1, 'active native platform administrator with explicit primary identity');
    }
    $db->rollBack();
    echo "PENDING: reviewed API organisation/staff/learner links, sign-in, attendance and exam acceptance must be checked separately.\n";
    echo "PENDING: Apache routing, shared writable storage and scheduled workers require separate deployment verification.\n";
    exit($blocked ? 1 : 0);
} catch (Throwable $error) {
    // Exception messages can contain connection details or queries; keep them private.
    fwrite(STDERR, "BLOCKED: environment parsing or read-only database checks failed.\n");
    exit(1);
}
