<?php
namespace Deployer;

require 'recipe/common.php';

// Project name
set('application', 'llad.ch'); // overwriting it at hosts

// Project repository
set('repository', 'git@github.com:dev7ch/api.git');

// [Optional] Allocate tty for git clone. Default value is false.
set('git_tty', true);
set('keep_releases', 10);
// Shared files/dirs between deploys
set('shared_dirs', []);

// Writable dirs by web server
set('writable_dirs', []);
set('shared_files', ['.htaccess', 'config/api.php']);


// Composer

/**
 * Set custom composer bin
 */
env('bin/composer', function () {
    if (commandExist('composer')) {
        $composer = run('which composer')->toString();

        if (isVerbose()) {
            writeln("Use global installed composer: " . $composer);
        }
    }
    if (empty($composer)) {
        run("cd {{release_path}} && curl -sS https://getcomposer.org/installer | {{bin/php}}");
        $composer = '{{bin/php}} {{release_path}}/composer.phar';
    }

    if (isVerbose()) {
        $version = run("cd {{release_path}} && ".$composer." -V");
        writeln("Composer version: " . $version);
    }

    return $composer;
});


/**
 * Override default composer option in order to provide ignore platform reqs flag.
 *
 */
env('composer_options', function() {
    $args = null;
    if (has('ignorePlatformReqs')) {
        $args = ' --ignore-platform-reqs';
    }
    return 'install --no-dev --verbose --prefer-dist --optimize-autoloader --no-progress --no-interaction' . $args;
});

// Hosts

host('dev')
    ->set('application', 'dev-llad.ch')
    ->hostname('v000246.fhnw.ch')
    ->set('deploy_path', '/var/www/html/dev-llad.ch')
    // ->set('rsync_src', __DIR__ . '/dist')
    // ->set('rsync_dest','{{release_path}}')
    ->user('root')
    ->port(22)
    ->env('branch', 'llad')
    //->configFile('~/.ssh/config')
    ->identityFile('~/.ssh/id_rsa')
    ->forwardAgent(true)
    ->multiplexing(true)
    ->addSshOption('UserKnownHostsFile', '/dev/null')
    ->addSshOption('StrictHostKeyChecking', 'no');

// Tasks

desc('Deploying {{application}} ');
task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:shared',
    'deploy:writable',
    'deploy:update_code',
    'deploy:vendors',
    'deploy:clear_paths',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success'
]);


/**
 * Task: cleanup:deployefile
 *
 * Remove sensitive files after deployment.
 */
task('cleanup:deployfile', function () {
    $keepDeployer = (has('keepDeployer')) ? get('keepDeployer') : false;
    // as the deployer file can contain sensitive data about other webserver.
    if (!$keepDeployer) {
        run('rm -f {{release_path}}/deploy.php');
    }
    // remove git ignore files in readable and none readable dirs
    run('rm -rf {{release_path}}/.git');
    run('rm -f {{release_path}}/.gitignore');
    // sometimes the readme contains data about loggin informations or other privacy content.
    run('rm -f {{release_path}}/README.md');
    // the lock and json file can contain github tokens when working with private composer repos.
    run('rm -f {{release_path}}/composer.lock');
    run('rm -f {{release_path}}/composer.json');
})->desc('Remove sensitive data');

/**
 * Set deployfile cleanup
 */
after('cleanup', 'cleanup:deployfile');


// [Optional] If deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');
