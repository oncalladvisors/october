<?php namespace Oca\LeadScraper;

use System\Classes\PluginBase;
use App;
use Config;
use Illuminate\Foundation\AliasLoader;

class Plugin extends PluginBase
{
    public function registerComponents()
    {
    }

    public function register()
    {
        $this->registerConsoleCommand('leadscraper.profiledocs', 'Oca\Leadscraper\Console\ProfilesDocs');
    }

    public function registerMailTemplates()
    {
        return [
            'oca.leadscraper::mail.profilesVerificationNeeded' => 'Email to remind me to fill out profile verification code.',
        ];
    }

    public function registerSettings()
    {
        return [
            'Profiles Verification Code' => [
                'label'       => 'Profiles Verification Code',
                'description' => 'If needed, set the verification code via the web.',
                'category'    => 'Scrapers',
                'icon'        => 'icon-cog',
                'class'       => 'Oca\Leadscraper\Models\Settings',
                'order'       => 500,
                'keywords'    => 'profiles',
                'permissions' => ['oca.leadscraper.profiles.access_settings']
            ]
        ];
    }

    /**
     * Register service provider, Twig extensions, and alias facade.
     */
    public function boot()
    {
        $this->bootPackages();
    }

    /**
     * Boots (configures and registers) any packages found within this plugin's packages.load configuration value
     *
     * @see https://luketowers.ca/blog/how-to-use-laravel-packages-in-october-plugins
     * @author Luke Towers <octobercms@luketowers.ca>
     */
    public function bootPackages()
    {
        // Get the namespace of the current plugin to use in accessing the Config of the plugin
        $pluginNamespace = str_replace('\\', '.', strtolower(__NAMESPACE__));

        // Instantiate the AliasLoader for any aliases that will be loaded
        $aliasLoader = AliasLoader::getInstance();

        // Get the packages to boot
        $packages = Config::get($pluginNamespace . '::packages');

        // Boot each package
        foreach ($packages as $name => $options) {
            // Setup the configuration for the package, pulling from this plugin's config
            if (!empty($options['config'] && !empty($options['config_namespace']))) {
                Config::set($options['config_namespace'], $options['config']);
            }

            // Register any Service Providers for the package
            if (!empty($options['providers'])) {
                foreach ($options['providers'] as $provider) {
                    App::register($provider);
                }
            }

            // Register any Aliases for the package
            if (!empty($options['aliases'])) {
                foreach ($options['aliases'] as $alias => $path) {
                    $aliasLoader->alias($alias, $path);
                }
            }
        }
    }
}
