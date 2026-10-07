<?php
// CLI-only read-only review. Never prints identity values or creates a mapping.
require __DIR__.'/native-student-review.php';
if (PHP_SAPI !== 'cli' || $argc !== 4
    || !preg_match('#^/home/tech4learn/releases/[a-f0-9]{40}/platform$#D', $argv[1])
    || !preg_match('/^[1-9][0-9]{0,14}$/D', $argv[2])
    || !preg_match('/^[1-9][0-9]{0,14}$/D', $argv[3])) {
    fwrite(STDERR, "Use checked-release platform path, reviewed native organisation ID and native student ID.\n");
    exit(2);
}
try {
    $root = realpath($argv[1]);
    if ($root !== $argv[1]) throw new RuntimeException();
    require $root.'/vendor/autoload.php';
    $env = Dotenv\Dotenv::parse(file_get_contents($root.'/.env'));
    if (($env['APP_ENV'] ?? '') !== 'production'
        || ($env['DB_CONNECTION'] ?? '') !== 'mysql'
        || ($env['DB_HOST'] ?? '') !== '127.0.0.1'
        || ($env['DB_PORT'] ?? '') !== '3306'
        || ($env['DB_DATABASE'] ?? '') !== 'tech4learn_exams'
        || ($env['DB_USERNAME'] ?? '') !== 'tech4learn_exams'
        || empty($env['DB_PASSWORD']) || !empty($env['DATABASE_URL'])) throw new RuntimeException();
    $db = new PDO('mysql:host=127.0.0.1;port=3306;dbname=tech4learn_exams;charset=utf8mb4', $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]);
    $db->exec('SET SESSION TRANSACTION READ ONLY');
    $db->beginTransaction();
    $query = $db->prepare('SELECT o.status AS organisation_status,o.settings,s.status AS student_status FROM organizations o JOIN students s ON s.organization_id=o.id WHERE o.id=? AND s.id=?');
    $query->execute([$argv[2], $argv[3]]);
    $ready = nativeAttendanceStudentReady($query->fetchAll(PDO::FETCH_ASSOC));
    $db->rollBack();
    echo $ready ? "PASS: active native student ownership reviewed.\n" : "BLOCKED: native student identity requires review.\n";
    exit($ready ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, "BLOCKED: private native configuration or read-only identity review failed.\n");
    exit(1);
}
