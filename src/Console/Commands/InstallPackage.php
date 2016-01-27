<?php
namespace Nodes\Console\Commands;

use Illuminate\Console\Command;
use Nodes\AbstractServiceProvider as NodesAbstractServiceProvider;
use Nodes\Exceptions\InstallNodesPackageException;
use Nodes\Exceptions\InstallPackageException;

/**
 * Class InstallPackage
 *
 * @package Nodes\Console\Commands
 */
class InstallPackage extends Command
{
    /**
     * The name and signature of the console command
     *
     * @var string
     */
    protected $signature = 'nodes:package:install
                            {package : Name of package (e.g. "nodes/core")}';

    /**
     * The console command description
     *
     * @var string
     */
    protected $description = 'Install a Nodes package into your project';

    /**
     * Install package's service provider
     *
     * @author Morten Rugaard <moru@nodes.dk>
     *
     * @access public
     * @return void
     * @throws \Nodes\Exceptions\InstallPackageException
     */
    public function handle()
    {
        // Retrieve package name
        $package = $this->argument('package');

        // Validate package name
        if (!$this->validatePackageName($package)) {
            throw new InstallPackageException(sprintf('Invalid package name [%s]', $package), 400);
        }

        // Check if package is already installed.
        // If it is, we'll abort and do nothing.
        if (nodes_is_package_installed($package)) {
            return;
        }

        // Make user confirm installation of package
        if (!$this->confirm(sprintf('Do you wish to install package <comment>[%s]</comment> into your application?', $package), true)) {
            $this->output->block(sprintf('Run "php artisan nodes:package:install %s" when you\'re ready to install the package [%s].', $package, $package), 'TIP!', 'fg=white;bg=black', ' ', true);
            return;
        }

        // Install service provider for package
        $serviceProviderClass = nodes_install_service_provider($package);
        if ($serviceProviderClass === true) {
            return;
        }

        // Ask a series of installation questions
        // such as to copy config files, views etc.
        $serviceProvider = app($serviceProviderClass, [$this->getLaravel()]);
        if ($serviceProvider instanceof NodesAbstractServiceProvider) {
            // Set installer instance
            $serviceProvider->setInstaller($this);

            // Install package facades
            if (is_null(nodes_install_facades($package, $serviceProvider))) {
                $this->error('Could not localte aliases array in [config/app.php]');
            }

            // Run package install sequence
            $serviceProvider->install();
        }

        // Successfully installed package
        $this->info(sprintf('Package <comment>[%s]</comment> was successfully installed.', $package));
    }

    /**
     * Validate package name
     *
     * @author Morten Rugaard <moru@nodes.dk>
     *
     * @access protected
     * @param  string $package
     * @return boolean
     */
    protected function validatePackageName($package)
    {
        $package = explode('/', $package);
        return count($package) == 2;
    }
}