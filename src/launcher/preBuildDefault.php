<?php
namespace CodeEnigma\Deployments\launcher;

use CodeEnigma\Deployments\common\ServerTasks;

class preBuildDefault {

  public function __construct() {
    $this->serverTasks = new ServerTasks();
  }

  public function process($vars) {
    $this->serverTasks->createBuildDirectory();
    $this->serverTasks->cloneRepo($vars->repo_url, $vars->branch);
  }
}
