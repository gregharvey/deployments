<?php
namespace CodeEnigma\Deployments\launcher;

use CodeEnigma\Deployments\common\ServerTasks;

class preBuildDefault {

  public function __construct() {
    $this->serverTasks = new ServerTasks();
  }

  public function process() {
    print "Hello World!\n";
  }

  /**
   * Function to create the target directory for this build
   *
   * @param string $role The server role to execute against, as set in ConfigTasks::defineRoles()
   */
  public function createBuildDirectory(
    $role = 'app_all'
    ) {
    $this->serverTasks->createBuildDirectory($role);
  }

  /**
   * Function to clone the repository to the app servers
   *
   * @param string $repo_url The URL of the Git repository to clone
   * @param string $branch Git branch to clone
   * @param string $role The server role to execute against, as set in ConfigTasks::defineRoles()
   */
  public function cloneRepo(
    $repo_url,
    $branch,
    $role = 'app_all'
    ) {
      $this->serverTasks->cloneRepo($repo_url, $branch, $role);
   }
}
