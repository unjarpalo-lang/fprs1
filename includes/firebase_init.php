<?php
/**
 * Firebase server initialization. Reads config/firebase.php and exposes two
 * accessors — firebase_database() and firebase_auth() — mirroring how
 * kreait/firebase-php's Factory hands back separate Database and Auth
 * service objects.
 *
 * This project has no Composer/vendor directory today, so there is no
 * official Firebase Admin SDK available (none exists for PHP without it).
 * Both accessors therefore run over plain REST via includes/FirebaseServerAuth.php.
 *
 * If you later run `composer require kreait/firebase-php`, this file
 * detects vendor/autoload.php automatically and switches to the real SDK
 * with no changes needed at any of this file's call sites — though note
 * the kreait objects have a different method API (e.g.
 * $database->getReference($path)->getValue() instead of rtdbGet($path)),
 * so code calling firebase_database()/firebase_auth() directly would need
 * a small update at that point, not just here.
 */

require_once __DIR__ . '/FirebaseServerAuth.php';

function firebase_config(): array {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/firebase.php';
    }
    return $config;
}

function firebase_has_composer_sdk(): bool {
    return file_exists(__DIR__ . '/../vendor/autoload.php')
        && class_exists(\Kreait\Firebase\Factory::class);
}

/**
 * @return \Kreait\Firebase\Contract\Database|FirebaseServerAuth
 */
function firebase_database() {
    static $instance = null;
    if ($instance !== null) return $instance;

    $config = firebase_config();
    if (firebase_has_composer_sdk()) {
        $factory = (new \Kreait\Firebase\Factory())
            ->withServiceAccount($config['service_account_path'])
            ->withDatabaseUri($config['database_url']);
        $instance = $factory->createDatabase();
    } else {
        $instance = new FirebaseServerAuth($config['web_api_key'], $config['database_url'], $config['service_account_path']);
    }
    return $instance;
}

/**
 * @return \Kreait\Firebase\Contract\Auth|FirebaseServerAuth
 */
function firebase_auth() {
    static $instance = null;
    if ($instance !== null) return $instance;

    $config = firebase_config();
    if (firebase_has_composer_sdk()) {
        $factory = (new \Kreait\Firebase\Factory())
            ->withServiceAccount($config['service_account_path']);
        $instance = $factory->createAuth();
    } else {
        // Same REST client handles both concerns in the fallback path — see
        // FirebaseServerAuth::verifyIdToken().
        $instance = firebase_database();
    }
    return $instance;
}
