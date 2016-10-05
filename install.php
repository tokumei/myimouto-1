<?php
use Zend\Console\ColorInterface as Color;

/**
 * Boot Rails
 */
require __DIR__ . '/config/boot.php';

/**
 * Create console and migrator
 */
$c        = new Rails\Console\Console();
$migrator = new Rails\ActiveRecord\Migration\Migrator();


/**
 * Show splash
 */
if (!isset($argv[2]))
{
    $txColor = Color::LIGHT_WHITE;
    $bgColor = Color::GREEN;
    $c->put();
    $c->put("====================", $txColor, $bgColor);
    $c->put(" MyImouto installer ", $txColor, $bgColor);
    $c->put("====================", $txColor, $bgColor);
    $c->put();
}


/**
 * Get data for admin account
 */
if (!isset($argv[2]))
{
    $c->put("Admin account", null, Color::BLUE);
    $c->put("Please enter a name and password for the admin account.");
    $c->write("Note: ", Color::RED);
    $c->put("the password will be shown.");
    list($adminName, $adminPass) = getAdminData($c);
}
else
{
    $adminName = argv[1];
    $adminPass = argv[2];
}



/**
 * Database
 */
$c->put("Database", null, Color::BLUE);

# Install database
$c->write("Creating tables......");
$migrator->loadSchema();
$c->put('done');

# Run migrations
$c->write("Running migrations...");
$migrator->run();
$c->put('done');

# Run seeds
$c->write("Seeding..............");
$migrator->runSeeds();
$c->put('done');

# Create user in database
$c->write("Creating admin account...");
if (!isset($argv[2]))
{
    $c->put("false");
    Rails\ActiveRecord\ActiveRecord::connection()->executeSql(
        'INSERT INTO users (created_at, name, password_hash, level, show_advanced_editing) VALUES (?, ?, ?, ?, ?)',
        date('Y-m-d H:i:s'), $adminName, User::sha1($adminPass), 50, 1
    );
}
else
{
    $c->put("true");
    Rails\ActiveRecord\ActiveRecord::connection()->executeSql(
        'INSERT INTO users (created_at, name, password_hash, level, show_advanced_editing) VALUES (?, ?, ?, ?, ?)',
        date('Y-m-d H:i:s'), $argv[1], User::sha1($argv[2]), 50, 1
    );
}
Rails\ActiveRecord\ActiveRecord::connection()->executeSql(
    'INSERT INTO user_blacklisted_tags VALUES (?, ?)',
    1, implode("\r\n", CONFIG()->default_blacklists)
);
$c->put("done");


/**
 * Create /public/data folders
 */
$c->put("\n");
$c->write("Creating /public/data folders...");
$dataPath = Rails::publicPath() . '/data';
$dirs = [
    'avatars',
    'image',
    'import',
    'jpeg',
    'preview',
    'sample'
];
if (!is_dir($dataPath)) {
    mkdir($dataPath);
}
foreach ($dirs as $dir) {
    $path = $dataPath . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path);
    }
}


/**
 * Finish
 */
if (!isset($argv[2]))
{
    $c->put();
    $c->put("Installation finished.", Color::GREEN);
}

$c->put();

function nullErrorHandler() {}

function getAdminData($c)
{
    $adminName = $c->input("Account name: ");
    $adminPass = $c->input("Password: ");
    
    if ($c->confirm("Is the information correct? (y/n) ")) {
        return [$adminName, $adminPass];
    } else {
        $c->put();
        return getAdminData($c);
    }
}
