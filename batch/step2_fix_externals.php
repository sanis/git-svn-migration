<?php

# Usage:
# php step1_clone_repo.php repository_dump_directory/ repo_name remote_submodule_path
# e.g php batch/step2_fix_externals.php /media/b91eeaef-82c7-4ae6-9713-44ce65eb25e6/home/hassen/web_dld/ssi/ ctms /media/b91eeaef-82c7-4ae6-9713-44ce65eb25e6/home/hassen/web_dld/ssi/
# e.g php batch/step2_fix_externals.php /media/b91eeaef-82c7-4ae6-9713-44ce65eb25e6/home/hassen/web_dld/ssi/ bcpa /media/b91eeaef-82c7-4ae6-9713-44ce65eb25e6/home/hassen/web_dld/ssi/
# e.g php batch/step2_fix_externals.php /media/b91eeaef-82c7-4ae6-9713-44ce65eb25e6/home/hassen/web_dld/ssi/ ctms git@github.com:hbt/


/**
 * convert externals using file as submodules or symbolic links
 */


define('SF_ROOT_DIR', realpath(dirname(__FILE__) . '/../'));
define('SF_APP', 'frontend');
define('SF_ENVIRONMENT', 'dev');
define('SF_DEBUG', true);

require_once (SF_ROOT_DIR . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . SF_APP . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php');

$reposDir = $argv[1];
if(strcasecmp(substr($reposDir, strlen($reposDir) - strlen('/')), '/') !== 0)
    $reposDir .= '/';

define('REPOS_DIR', $reposDir);
define('REPO_NAME', strtolower(trim($argv[2])));
define('GIT_REPO_PATH', $argv[3]);

include dirname(__FILE__) . '/commons.php';

main();

function main()
{
    fixSubmodules();
    fixSymlinks();
}

function fixSubmodules()
{
    $yaml = readYAMLFile();

    createSubmodules($yaml);
    generateGitSubmodulesFile($yaml);
}

function fixSymlinks()
{
    $yaml = readYAMLFile();
    generateSymlinksFile($yaml);
}

function createSubmodules($yaml)
{
    chdir(REPOS_DIR);

    // loop through submodules
    foreach ($yaml['submodules'] as $submod)
    {
        // skip if repo already exists
        $repoPath = REPOS_DIR . $submod['real_name'];
        if (file_exists($repoPath))
        {
            echo "\n\n skipping because repository already exists at $repoPath";
        }
        else
        {
            // convert svn repo to git
            $step1Script = dirname(__FILE__) . '/step1_clone_repo.php';

            $params = array(
                'php',
                $step1Script,
                REPOS_DIR,
                $submod['url'],
                $submod['real_name']
            );

            $cmd = implode(" ", $params);
            echo shell_exec($cmd);
        }
    }
}

// create .gitmodules file for the repo
function generateGitSubmodulesFile($yaml)
{
    // loop through submodules
    chdir(REPOS_DIR . REPO_NAME);

    foreach ($yaml['submodules'] as $submod)
    {
        $cmd = 'git submodule add -f  ' . GIT_REPO_PATH . strtolower($submod['real_name']) . ' ' . $submod['path'] . '/' . $submod['name'];
        echo shell_exec($cmd);
    }

    echo shell_exec('git add .gitmodules');
    echo shell_exec('git commit -m "(svn import) -- adds .gitmodules"');
//    echo shell_exec('git push origin master');
}

// create .svn_externals_links file
function generateSymlinksFile($yaml)
{
    chdir(REPOS_DIR . REPO_NAME);
    foreach ($yaml['symlinks'] as $sym)
    {
        $link = $sym['path'] . '/' . $sym['name'];
        $target = $sym['ln_src'];

        if (file_exists($link))
            unlink($link);

        echo "\n\nsymlink $target $link\n";
        chdir(REPOS_DIR . REPO_NAME . '/' . $sym['path']);
        symlink($target, $sym['name']);
        
        chdir(REPOS_DIR . REPO_NAME);
        shell_exec('git add -f ' . $link);
    }

    echo shell_exec('git commit -m "(svn import) -- adds symlinks"');
//    echo shell_exec('git push origin master');
}

?>
