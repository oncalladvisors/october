<?php namespace Oca\Leadscraper\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Oca\Leadscraper\Console\ProfilesBase;


class ProfilesWeekly extends ProfilesBase
{
    /**
     * @var string The console command name.
     */
    protected $name = 'leadscraper:profilesweekly';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';

    /**
     * Execute the console command.
     * @return void
     */
    public function fire()
    {
        $this->setFireVars();

        $this->output->writeln('Hello world!');
    }

    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }
}
