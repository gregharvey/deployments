<?php
/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */

use Robo\Tasks;
use CodeEnigmaLocal\Deployments as LocalDeployments;
use CodeEnigma\Deployments\common as CommonTasks;

/**
 * Undocumented class.
 */
class RoboFile extends Tasks
{

    use CommonTasks\loadTasks;

  /**
   * Deploy code to a remote server or servers
   *
   * @param string  $projectName  The short name for this project used in the paths on the server
   * @param string  $repoUrl      The URL of the Git repository where the code is kept
   * @param string  $branch       The branch of the Git repository to build from
   * @param string  $buildType    The type of build (can be anything, typically develop, stage, live, etc.)
   * @param int     $buildNumber  The current build number (should be unique and incremented from the previous build)
   * @param int     $keepBuilds   The number of builds to retain on the application servers
   * @param string  $appUrl       The URL of the application being deployed
   * @param string  $baseDomain   Flag to tell us if this is an AWS autoscale layout
   * @param string  $phpiniFile   The path of the PHP ini file to use
   * @param boolean $handleVhosts Flag to tell us if we should try to configure the web server or not
   * @param boolean $cluster      Flag to tell us if there is more than one server
   * @param boolean $autoscale    Flag to tell us if this is an AWS autoscale layout
   */
    public function build(
        string $projectName,
        string $repoUrl,
        string $branch,
        string $buildType,
        int $buildNumber,
        int $keepBuilds = 10,
        string $appUrl = "",
        string $baseDomain = "codeenigma.net",
        string $phpiniFile = "",
        bool $handleVhosts = true,
        bool $cluster = false,
        bool $autoscale = false
    ) {
        // Off we go!
        $this->yell("Starting a build");

        // Create an empty class to store variables in
        $this->config = new CommonTasks\Config();
        $this->config->projectName = $projectName;
        $this->config->repoUrl = $repoUrl;
        $this->config->branch = $branch;
        $this->config->buildType = $buildType;
        $this->config->buildNumber = $buildNumber;
        $this->config->keepBuilds = $keepBuilds;
        $this->config->appUrl = $appUrl;
        $this->config->handleVhosts = $handleVhosts;
        $this->config->cluster = $cluster;
        $this->config->autoscale = $autoscale;
        $this->config->baseDomain = $baseDomain;
        $this->config->phpiniFile = $phpiniFile;

        // The actual working directory of our build is a few levels up from where we are
        $GLOBALS['build_cwd']    = getcwd().'/../../../..';
        // Move our config to the right place for Robo.li to auto-detect
        $this->say("Moving our robo.yml file to the Robo.li directory");
        $this->_copy($GLOBALS['build_cwd'].'/build/robo.yml', './robo.yml');

        // Set web server root and app location
        $GLOBALS['www_root']   = $this->taskConfigTasks()->returnConfigItem($buildType, 'server', 'www-root', '/var/www');
        $this->config->app_location          = $this->taskConfigTasks()->returnConfigItem($buildType, 'app', 'location', 'www');
        // Fixed variables
        $GLOBALS['build_path'] = $GLOBALS['www_root'].'/'.$projectName.'_'.$buildType.'_build_'.(string) $buildNumber;
        if ($this->config->app_location) {
            $GLOBALS['app_path'] = $GLOBALS['build_path'].'/'.$this->config->app_location;
        } else {
            $GLOBALS['app_path'] = $GLOBALS['build_path'];
        }

        // Load in our config
        $this->say("Setting up the environment");
        // Set up server environment information
        $GLOBALS['ci_user']    = $this->taskConfigTasks()->returnConfigItem($buildType, 'server', 'ci-user');
        $this->config->ssh_key               = $this->taskConfigTasks()->returnConfigItem($buildType, 'server', 'ssh-key');
        // Set up web server - defaults to config for Nginx on Debian
        $this->config->web_server_restart    = $this->taskConfigTasks()->returnConfigItem($buildType, 'server', 'web-server-restart', '/etc/init.d/nginx reload');
        $this->config->vhost_base_location   = $this->taskConfigTasks()->returnConfigItem($buildType, 'server', 'vhost-base-location', '/etc/nginx/sites-available');
        $this->config->vhost_link_location   = $this->taskConfigTasks()->returnConfigItem($buildType, 'server', 'vhost-link-location', '/etc/nginx/sites-enabled');
        // Set up application information
        $this->config->notifications_email   = $this->taskConfigTasks()->returnConfigItem($buildType, 'app', 'notifications-email');
        $this->config->app_link              = $this->taskConfigTasks()->returnConfigItem($buildType, 'app', 'link', $GLOBALS['www_root'].'/live.'.$projectName.'.'.$buildType);
        $this->config->app_port              = $this->taskConfigTasks()->returnConfigItem($buildType, 'app', 'port', "80");
        // Figure out the URL for this application
        $this->config->appUrl = $this->taskConfigTasks()->returnConfigItem($buildType, 'app', 'url', $appUrl);
        if (!$this->config->appUrl) {
            $this->config->appUrl = strtolower("$projectName-$buildType.$baseDomain");
        }

        // Debug feedback
        $this->say("Build path set to '".$GLOBALS['build_path']."'");
        $this->say("App path set to '".$GLOBALS['app_path']."'");

        // Build our host and roles
        $this->taskConfigTasks()->defineHost($buildType, $autoscale);
        $this->taskConfigTasks()->defineRoles($cluster, $buildType);

        /*
         * PREPARATION STAGE
         */

        $this->preBuildProcess();

        /*
         * APPLICATION DEPLOYMENT STAGE
         */

        // Give developers an opportunity to inject some code
        $this->taskUtils()->performClientDeployHook($projectName, $buildNumber, $buildType, 'pre');
        // Adjust links to builds
        $this->taskServerTasks()->setLink($GLOBALS['build_path'], $app_link);
        // Add any other links specified in the YAML file
        $this->taskServerTasks()->setLinks($buildType);
        // Give developers an opportunity to inject some code again
        $this->taskUtils()->performClientDeployHook($projectName, $buildNumber, $buildType, 'post');

        /*
         * SERVER CONFIGURATION STAGE
         */

        // @TODO: We need a mechanism for developers to provide configs to include and
        // a path to place them at on the server
        // E.g. they might provide a web server config that depends on an included config existing (like magento)

        // Set up virtual host(s)
        if ($handleVhosts) {
            $this->taskServerTasks()->createVhost($projectName, $buildType, $appUrl, $app_link, $app_port, $web_server_restart, $vhost_base_location, $vhost_link_location);
        }

        /*
         * POST-BUILD STAGE
         */

        // Wrap it up!
        $this->yell("Build succeeded!");
        // Clean up old builds
        $this->taskUtils()->removeOldBuilds($projectName, $buildType, $buildNumber, $keepBuilds);
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    protected function preBuildProcess()
    {
        $preBuild = new LocalDeployments\preBuild();
        $preBuild->process($this->config);
    }
}
