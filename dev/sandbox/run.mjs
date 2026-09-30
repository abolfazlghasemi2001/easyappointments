#!/usr/bin/env node
/**
 * Development helper: runs PHP inside the project checkout using php-wasm.
 *
 * It is only meant for environments where a native PHP binary (or Docker) is not available. On a normal machine use
 * "php index.php ..." / "vendor/bin/phpunit" directly.
 *
 * Requirements (installed by dev/sandbox/setup.sh):
 *   npm install --no-save @php-wasm/node@3.1.56 @php-wasm/node-8-4@3.1.56
 *
 * Usage:
 *   node dev/sandbox/run.mjs <script.php> [args...]     run a PHP file with argv populated
 *   node dev/sandbox/run.mjs --phpunit [args...]        run the PHPUnit test runner
 *   node dev/sandbox/run.mjs --code '<php>'             run an inline snippet
 */
import { PHP } from '@php-wasm/universal';
import { loadNodeRuntime, createNodeFsMountHandler } from '@php-wasm/node';

const REPO = process.env.EA_ROOT || process.cwd();
const MOUNT = process.env.EA_MOUNT || REPO; // must match the host path so absolute autoload paths resolve

const input = process.argv.slice(2);

if (input.length === 0) {
    console.error('usage: node dev/sandbox/run.mjs <script.php> [args...] | --phpunit [args...] | --code <php>');
    process.exit(2);
}

let argv;
let body;
let phpunitBootstrap = null;

if (input[0] === '--phpunit') {
    argv = ['vendor/phpunit/phpunit/phpunit', ...input.slice(1)];

    if (!input.includes('--bootstrap')) {
        argv.push('--bootstrap', '/tmp/ea-phpunit-bootstrap.php');

        phpunitBootstrap = `<?php
// Bootstraps CodeIgniter for the test run: the router is pointed at the empty
// "test" controller so that index.php can load the framework and the PHPUnit
// framework can continue with the actual test suite.
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['argv'] = ['index.php', 'test'];
$_SERVER['argc'] = 2;
require ${JSON.stringify(MOUNT + '/index.php')};
`;
    }

    body = `exit((new PHPUnit\\TextUI\\Application())->run($argv));`;
} else if (input[0] === '--lint') {
    // Lints the given PHP files (or all the uncommitted ones). The check runs in-process with the PHP tokenizer,
    // because a php-wasm runtime cannot spawn a "php -l" subprocess.
    let files = input.slice(1);

    if (files.length === 0) {
        const { execSync } = await import('node:child_process');

        files = execSync('git status --porcelain', { cwd: REPO, encoding: 'utf8' })
            .split('\n')
            .map((line) => line.slice(3).trim())
            .filter((file) => file.endsWith('.php'));
    }

    argv = ['lint', ...files];

    body = `
$files = array_slice($argv, 1);
$failed = 0;

foreach ($files as $file) {
    $source = @file_get_contents($file);

    if ($source === false) {
        echo 'MISS ' . $file . "\n";
        $failed++;
        continue;
    }

    try {
        token_get_all($source, TOKEN_PARSE);
        echo 'OK   ' . $file . "\n";
    } catch (ParseError $error) {
        echo 'FAIL ' . $file . ' :: ' . $error->getMessage() . ' (line ' . $error->getLine() . ")\n";
        $failed++;
    }
}

echo $failed ? "Lint failed for {$failed} file(s).\n" : 'Lint OK for ' . count($files) . " file(s).\n";

exit($failed ? 1 : 0);`;
} else if (input[0] === '--code') {
    argv = ['code'];
    body = input[1];
} else {
    argv = [input[0], ...input.slice(1)];
    body = `require ${JSON.stringify(MOUNT + '/')} . $argv[0];`;
}

const runtimeId = await loadNodeRuntime('8.4', {
    emscriptenOptions: { processId: Math.floor(Math.random() * 100000) + 1 },
});
const php = new PHP(runtimeId);
await php.mount(MOUNT, createNodeFsMountHandler(REPO));

const entry = `<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
chdir(${JSON.stringify(MOUNT)});
$argv = ${JSON.stringify(argv)};
$argc = count($argv);
$_SERVER['argv'] = $argv;
$_SERVER['argc'] = $argc;
$_SERVER['PHP_SELF'] = $argv[0];
$_SERVER['SCRIPT_NAME'] = $argv[0];
$_SERVER['SCRIPT_FILENAME'] = ${JSON.stringify(MOUNT + '/')} . $argv[0];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['DOCUMENT_ROOT'] = ${JSON.stringify(MOUNT)};
if (!defined('STDERR')) { define('STDERR', fopen('php://stderr', 'w')); }
if (!defined('STDOUT')) { define('STDOUT', fopen('php://stdout', 'w')); }
if (!defined('STDIN')) { define('STDIN', fopen('php://stdin', 'r')); }
$autoload = ${JSON.stringify(MOUNT + '/vendor/autoload.php')};
if (is_file($autoload)) { require $autoload; }
${body}
`;

if (phpunitBootstrap) {
    php.writeFile('/tmp/ea-phpunit-bootstrap.php', phpunitBootstrap);
}

php.writeFile('/tmp/ea-entry.php', entry);

let result;

try {
    result = await php.run({ scriptPath: '/tmp/ea-entry.php' });
} catch (error) {
    // php-wasm throws when the script exits with a non-zero status code.
    result = error?.response ?? null;

    if (!result) {
        console.error(String(error?.message ?? error));
        process.exit(1);
    }
}

if (result.text) process.stdout.write(result.text);
if (result.errors) process.stderr.write(result.errors);
process.exit(typeof result.exitCode === 'number' ? result.exitCode : 0);
