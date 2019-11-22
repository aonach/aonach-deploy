<?php
namespace Deployer;

/**
 * Configuration
 */
set('php_version', function() {
    return run('{{bin/php}} -r "echo phpversion();"');
});
set('bin/curl', function() {
    return locateBinaryPath('curl');
});

/**
 * Tasks
 */
desc('PHP Opcache flush');
task('php:opcache:flush', function() {
    $cacheToolVersion = version_compare(get('php_version'), '7.2', '>=') ? '' : '-3.2.1';

    // Php socket to clear opcache can be located in different places
    // on different servers, just add your paths, if needed
    run('
    {{bin/curl}} -s http://gordalina.github.io/cachetool/downloads/cachetool'.$cacheToolVersion.'.phar -o cachetool.phar
    chmod +x cachetool.phar
    for sock in {~/run/*.php-fpm.sock,/var/run/$(whoami)-remi-safe-php*.sock}; do
        if [ -S $sock ]; then ./cachetool.phar opcache:reset --fcgi=$sock; fi
    done');
});
