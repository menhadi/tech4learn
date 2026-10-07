<?php
// Local-only dependency reuse. Never loads ExamElite's environment or database.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') === 'production' || $argc !== 3) {
    fwrite(STDERR, "Usage: php scripts/prepare-foundation-local.php SOURCE FOUNDATION\n");
    exit(1);
}
$source = realpath($argv[1]);
$target = realpath($argv[2]);
$local = realpath(__DIR__.'/../.local');
if (!$source || !$target || !$local || !str_starts_with(str_replace('\\', '/', $target).'/', str_replace('\\', '/', $local).'/')) {
    throw new RuntimeException('Foundation must be inside this workspace .local directory.');
}
if (!is_file($target.'/foundation-source-manifest.json')) {
    throw new RuntimeException('Wait for the application copy to complete.');
}
if (file_exists($target.'/.env') || file_exists($target.'/vendor/autoload.php')) {
    throw new RuntimeException('Local setup already exists; refusing to overwrite.');
}
if (hash_file('sha256', $source.'/composer.lock') !== hash_file('sha256', $target.'/composer.lock')) {
    throw new RuntimeException('Dependency locks differ.');
}
$tenantPath = $target.'/app/Support/Tenant.php';
$tenantSource = file_get_contents($tenantPath);
$mysqlOrdering = "FIELD(role, 'owner', 'admin', 'staff')";
$portableOrdering = "CASE role WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 WHEN 'staff' THEN 3 ELSE 0 END";
if (substr_count($tenantSource, $mysqlOrdering) !== 1) {
    throw new RuntimeException('Tenant ordering changed upstream; review the local compatibility patch.');
}
file_put_contents($tenantPath, str_replace($mysqlOrdering, $portableOrdering, $tenantSource));
mkdir($target.'/vendor', 0700, true);
mkdir($target.'/vendor/composer', 0700, true);
copy($source.'/vendor/composer/installed.json', $target.'/vendor/composer/installed.json');
$dependencyRoot = var_export(str_replace('\\', '/', $source), true);
$autoload = <<<'PHP'
<?php
// Development-only: reuse installed vendor libraries, map application code to this copy.
$upstream = UPSTREAM_SOURCE;
$base = dirname(__DIR__);
require_once $upstream.'/vendor/composer/ClassLoader.php';
$loader = new Composer\Autoload\ClassLoader($upstream.'/vendor');
$relocate = static function ($path) use ($upstream, $base) {
    $path = str_replace('\\', '/', $path);
    if (str_starts_with($path, $upstream.'/') && !str_starts_with($path, $upstream.'/vendor/')) {
        return $base.substr($path, strlen($upstream));
    }
    return $path;
};
foreach (require $upstream.'/vendor/composer/autoload_psr4.php' as $prefix => $paths) {
    $loader->setPsr4($prefix, array_map($relocate, $paths));
}
foreach (require $upstream.'/vendor/composer/autoload_namespaces.php' as $prefix => $paths) {
    $loader->set($prefix, array_map($relocate, $paths));
}
$map = require $upstream.'/vendor/composer/autoload_classmap.php';
$loader->addClassMap(array_map($relocate, $map));
$loader->register(true);
foreach (require $upstream.'/vendor/composer/autoload_files.php' as $id => $path) {
    if (empty($GLOBALS['__composer_autoload_files'][$id])) {
        $GLOBALS['__composer_autoload_files'][$id] = true;
        require $relocate($path);
    }
}
return $loader;
PHP;
file_put_contents($target.'/vendor/autoload.php', str_replace('UPSTREAM_SOURCE', $dependencyRoot, $autoload));
touch($target.'/database/foundation.sqlite');
$database = str_replace('\\', '/', $target).'/database/foundation.sqlite';
$key = base64_encode(random_bytes(32));
file_put_contents($target.'/.env', "APP_NAME=Tech4Learn\nAPP_ENV=local\nAPP_KEY=base64:$key\nAPP_DEBUG=false\nAPP_URL=http://127.0.0.1:8001\nDB_CONNECTION=sqlite\nDB_DATABASE=$database\nCACHE_DRIVER=file\nSESSION_DRIVER=file\nSESSION_COOKIE=tech4learn_foundation_session\nQUEUE_CONNECTION=sync\nMAIL_MAILER=log\nLOG_CHANNEL=single\n");
echo "Local dependency loader and fresh empty SQLite configuration created. No source configuration imported.\n";
