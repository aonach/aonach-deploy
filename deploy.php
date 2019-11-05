<?php
namespace Deployer;

require_once 'recipe/common.php';
require_once 'include/opcache.php';
require_once 'include/update_code.php';

/**
 * Config of hosts
 */
inventory('hosts.yml');
foreach (Deployer::get()->hosts as $host) {
    $host->addSshOption('StrictHostKeyChecking', 'no');
}

/**
 * Configuration
 */
set('deploy_path', '~/deploy');
set('repo_path', 'src');
set('asset_locales', 'en_US en_IE');

set('symlinks', [
    '.' => 'pub/pub'
]);
set('shared_files', [
    'app/etc/env.php'
]);
set('shared_dirs', [
    'pub/media',
    'var/log'
]);

set('m2_version', function() {
    $m2version = run('{{bin/php}} {{release_path}}/bin/magento --version');
    preg_match('/((\d+\.?)+)/', $m2version, $regs);

    return $regs[0];
});

/**
 * Tasks
 */
desc('Magento2 apply patches');
task('magento:apply:patches', function() {
    run('
    cd {{release_path}}
    if [ -d patch ]; then
        for patch in patch/*.patch; do
            {{bin/git}} apply -v $patch
        done
    fi');
});

desc('Magento2 dependency injection compile');
task('magento:di:compile', function() {
    run('{{bin/php}} {{release_path}}/bin/magento setup:di:compile');
});

desc('Magento2 deploy assets');
task('magento:deploy:assets', function() {
    $additionalOptions = version_compare(get('m2_version'), '2.2', '>=') ?
        '--force --strategy=compact' : '--quiet';

    run('{{bin/php}} {{release_path}}/bin/magento setup:static-content:deploy '.
        $additionalOptions.' '.
        get('asset_locales')
    );
});

desc('Magento2 create symlinks');
task('magento:create:symlinks', function() {
    cd('{{release_path}}');
    foreach (get('symlinks') as $destination => $link) {
        run('ln -s '.$destination.' '.$link);
    }
});

desc('Magento2 upgrade database');
task('magento:upgrade:db', function() {
    run('
    if ! {{bin/php}} {{release_path}}/bin/magento setup:db:status; then
        if [ -d {{deploy_path}}/current ]; then
            {{bin/php}} {{deploy_path}}/current/bin/magento maintenance:enable
        fi
        {{bin/php}} {{release_path}}/bin/magento setup:upgrade --keep-generated
        if [ -d {{deploy_path}}/current ]; then
            {{bin/php}} {{deploy_path}}/current/bin/magento maintenance:disable
        fi
    fi');
});

desc('Magento2 cache flush');
task('magento:cache:flush', function() {
    run('{{bin/php}} {{release_path}}/bin/magento cache:flush');
    run('{{bin/php}} {{release_path}}/bin/magento cache:enable');
});

desc('Deploy your project');
task('deploy', [
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:vendors',
    'deploy:shared',
    'magento:apply:patches',
    'magento:di:compile',
    'magento:deploy:assets',
    'magento:upgrade:db',
    'magento:create:symlinks',
    'magento:cache:flush',
    'php:opcache:flush',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success'
]);
after('deploy:failed', 'deploy:unlock');
