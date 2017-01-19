<?php namespace Oca\LeadScraper\Models;

use Model;

class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    // A unique code
    public $settingsCode = 'oca_leadscraper_settings';

    // Reference to field configuration
    public $settingsFields = 'fields.yaml';
}