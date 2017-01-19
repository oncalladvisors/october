<?php namespace Oca\Leadscraper\Console;

use Illuminate\Console\Command;
use Monolog\Logger;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Goutte\Client as GoutteClient;
use Log;
use Storage;
use File;
use Monolog\Handler\StreamHandler;
use DB;
use Mail;
use Oca\LeadScraper\Models\Settings;
use Oca\ListManager\Models\ListOriginal;

class ProfilesBase extends Command
{
    /**
     * @var string The console command name.
     */
    //protected $name = 'leadscraper:profilesbase';

    /**
     * @var string The console command description.
     */
    protected $description = 'This won\'t work by itself';

    protected $userAgent = "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.37";
    protected $scrapeLogFileName; // dynamically set
    protected $crawler;
    protected $client;
    protected $SearchForm;
    protected $entity = array(); // the current scraped entities information
    protected $entityList = array();
    protected $listYear = '2018';

    public $scrapeLog; // used to store the option
    public $scrapeLogId;
    public $listDb; // = DB::connection('mysql2')
    public $verificationCode;

    public $baseProfilesUrl = 'http://webapp.profilesdatabase.com';
    //protected $baseProfilesUrl = 'https://requestb.in/1gm5z8c1';

    // these don't work in the constructor, so putting them here.
    public function setFireVars() {
        $this->setListDb();
        $this->setScrapeLogfile($this->option('scrapeLogId'));
        $this->scrapeLogId = $this->option('scrapeLogId');
    }

    public function login() {
        $this->verificationCode = $this->checkWaitingOrCode();

        // set goutte client
        $this->client = new GoutteClient();
        $this->client->setHeader('User-Agent', $this->userAgent); //@todo check that this actually works on requestb.in

        // get login url
        $this->crawler = $this->client->request('GET', $this->getBaseProfilesUrl() . '/login.cfm');
        $html = $this->crawler->html(); // for debug

        $form = $this->crawler->selectButton('Log In')->form();
        $html = $this->crawler->html(); // for debug

        // submit the login form
        $this->crawler = $this->client->submit($form, array('UserName' => env('PROFILES_USER'), 'Password'=> env('PROFILES_PASSWORD')));

        // check if we are actually logged in.
        $this->crawler->filter('h3')->each(function ($node) {
            if($node->text() == 'Please enter your Login information below') {
                $message = "Login failed";
                $this->myConsoleLog('error',$message);
                exit;
            }
        });
        $html = $this->crawler->html(); // for debug

        // we need to verify the browser
        $this->crawler->filter('.page-header h2')->each(function ($node) {
            if($node->text() == 'Profiles Browser Authentication'){
                $message = "Logged in, but need browser verification!";
                $this->myConsoleLog('info',$message);

                $this->verifyBrowser();
            }
        });
        $this->crawler->filter('.span6:nth-child(2) .dashTitle')->each(function ($node) {
            if($node->text() == 'New Physicians Since Last Login'){
                $message = "Logged in!";
                $this->myConsoleLog('info',$message);
            }
            else {
                $message = "Login error, exiting";
                $this->myConsoleLog('error',$message);
                exit;
            }
        });
        $html = $this->crawler->html(); // for debug
        Settings::resetDefault(); // reset settings since we have logged in.
    }

    public function getSearchPage($searchString = 'Physician Search'){
        // get search page and go for it.
        $searchLink = $this->crawler->selectLink($searchString)->link();
        $this->crawler = $this->client->click($searchLink);
        $this->SearchForm = $this->crawler->selectButton('Search')->form();
    }

    // make sure to check for duplicates before using this!
    public function saveRowInfo($mappedEntity){
        $mappedEntity['source'] = 'PR';
        $entity = $this->listDb->table('originalLists')
            ->insert($mappedEntity);
        // no need to sleep if doing excel files.
        if(empty($this->excelFolder)){
            sleep ( rand ( 0, 2)); //@todo better place for this?
        }
    }

    /**
     * @param $currentEntity
     * @param bool $fieldToCheck @todo UNTESTED
     * @return bool
     */
    public function checkEntityDuplicate ($currentEntity, $fieldToCheck = TRUE) {
        if($fieldToCheck){
            $entity = $this->listDb->table('originalLists');
            if(is_array($fieldToCheck)){
                foreach($fieldToCheck as $field){
                    $entity->where($field, '=', $currentEntity[$field]); // untested
                }
            } else {
                foreach($currentEntity as $key => $value){
                    $entity->where($key, '=', $value);
                }
            }

            $entity = $entity->get();
        } else {
            // @todo check for duplicates based on non-entity specific things (entity changes and some entities don't have email1)
            $entity = $this->listDb->table('originalLists')
                ->where('year', '=', $currentEntity['year'])
                ->where('source', '=', "PR")
                ->where('listServiceId', '=', $currentEntity['listServiceId']) // this is already mapped probably.
                ->get();
        }

        if(!empty($entity)){
            return $entity;
        }
        return false;
    }

    public function verifyBrowser(){
        // Do I already have a code from a previous run?
        $code = $this->verificationCode;
        if(empty($code)){ // no code
            // lets request one via email.
            $uri = '/proxies/Authenticate.cfc?method=SendCodeByEmail';
            $this->crawler = $this->client->request('POST', $this->getBaseProfilesUrl() . $uri);

            $code = $this->checkWaitingOrCode(true);
        }

        if(!$code){
            // I should never get this far, but if I do... run an error
            $this->myConsoleLog('error', 'I should never have gotten here 1.');
            exit();
        }

        // get get the validation code and form perams:
        $params = array('verificationCode' => $code,);

        $uri = '/proxies/Authenticate.cfc?method=VerifyCode';
        $this->crawler = $this->client->request('POST', $this->getBaseProfilesUrl() . $uri, $params); // does this end on the index page?

        $uri = '/index.cfm';
        $this->crawler = $this->client->request('GET', $this->getBaseProfilesUrl() . $uri);
    }

    public function checkWaitingOrCode($flaggedVerify = null) {
        //check if we are waiting on verification code to be entered into Settings
        // check if the command is run by code rather than cli console... (see ProfilesDocs.php)
        $runByCode = $this->option('runByCode');
        if ($runByCode) {
            $waiting = Settings::get('profiles_waiting_verification_code');
            $code = Settings::get('profiles_verification_code');
            // if profiles has flagged us for verification, setting waiting to 1 so it gets used below.
            if($flaggedVerify){
                $this->exitWaiting(1);
            }

            // if the code hasn't been entered into the UI yet, exit the command
            if ($waiting >= 1 && empty($code)) {
                $this->exitWaiting($waiting);
            }

            return $code;
        }
        // this command must be run by cli... were we flagged as verify?
        if($flaggedVerify){
            // lets ask for one then:
            $code = $this->ask('Check your email... What is the validation code?');
            return $code;
        }
        return null;  // I have nothing, so we must not be waiting and won't need a code.
    }

    public function exitWaiting($waiting){
        $this->myConsoleLog('info', 'Need verification code to be set in Settings... Exiting and STILL Waiting');
        $this->sendVerificationEmail($waiting);
        $waiting++;
        // update my waiting number
        Settings::set(['profiles_waiting_verification_code' => $waiting]);
        exit();
    }


    public function getProgramsSearchParams()
    {
        return array(
            'Search.ProfilesYear' => $this->getListYear(),
            'Search.Specialty.Specialty.Id' => '11',
            "search.itemsPerPage" => '500' // This isn't set in the default form, I add it b/c that's how they do it later via the UI, I just do it all at one time.
        );
    }

    public function getPhysSearchParams()
    {
        return array(
            'year' => $this->getListYear(),
            'specialty' => '11',
            'profilestatus' => 'PO',
            'gradlocation' => 'All',
            'h1bvisa' => 'Include',
            'j1visa' => 'Include',
            //'items_per_page' => '100', // added to see if it works it does not

            //"search.itemsPerPage" => '500' // This isn't set in the default form, I add it b/c that's how they do it later via the UI, I just do it all at one time.
            /* 2018 check on 1/12/2017
            year:2018
            specialty:11
            profilestatus:PO
            gradlocation:All
            h1bvisa:Include
            j1visa:Include
            */
        );
    }

    public function getBaseProfilesUrl(){
        return $this->baseProfilesUrl;
    }

    public function getProfilesCount()
    {
        // get profiles listed number of docs:
        $profilesCount = 0;

        if ($this->crawler->filter('.pull-left strong')->count())
            $profilesCount = (int) $this->crawler->filter('.pull-left strong')->text();

        return $profilesCount;
    }

    public function getListYear (){
        return $this->listYear;
    }

    /**
     * @param $yearid string, like: `doctorRow_click(37701, 2015);` from `onclick` if the row
     * @return array|null
     */
    public function getYearId($yearid)
    {
        // sweet regex creator: http://txt2re.com/index-php.php3?s=doctorRow_click(37701,%202015);&4&7
        $re1='.*?';	# Non-greedy match on filler
        $re2='(\\d+)';	# Integer page id
        $re3='.*?';	# Non-greedy match on filler
        $re4='((?:(?:[1]{1}\\d{1}\\d{1}\\d{1})|(?:[2]{1}\\d{3})))(?![\\d])';	# Year

        if ($c=preg_match_all ("/".$re1.$re2.$re3.$re4."/is", $yearid, $matches))
        {
            $id=$matches[1][0];
            $year=$matches[2][0];
            return array('year' => $year, 'id' => $id);
        }
        return null;
    }

    public function myConsoleLog($type, $message){
        // create a log channel
        $this->scrapeLog = new Logger('scraper');
        $this->scrapeLog->pushHandler(new StreamHandler($this->scrapeLogFile['filepath'], Logger::INFO));

        switch ($type){
            case 'error':
                $this->scrapeLog->error($message);
                Log::error($message);
                break;
            default:
                $this->scrapeLog->info($message);
                break;
        }
    }

    // switched to model so this isn't used anymore
    public function setListDb (){
        $this->listDb = DB::connection('mysql2');
    }

    public function setScrapeLogfile($name) {
        $folder = 'scrapeLogs';
        $this->scrapeLogFile['name'] = "$name.log";
        $this->scrapeLogFile['path'] = storage_path() . "/$folder";
        $this->scrapeLogFile['filepath'] = $this->scrapeLogFile['path'] . '/' . $this->scrapeLogFile['name'];
    }

    public function sendVerificationEmail($waiting) {
        $waitingCheck = $waiting / 60; // only send this email every 60 minutes.
        $whole = 0;
        if (ctype_digit($waitingCheck) ){
            // IS whole number
            $whole = 1;
        }
        if ($waiting == 1 OR $whole == 1){
            //send an email
            Mail::send('oca.leadscraper::mail.profilesVerificationNeeded', array(), function($message) {
                $message->from('get@oncalladvisors.com', 'OCA Profiles Lists');
                $message->to('polson@oncalladvisors.com');
            });
        }
    }

//    /**
//     * Get the console command options.
//     * @return array
//     */
//    protected function getOptions()
//    {
//        //{--runByCode= : Only used when running this command from code, set to 1}';
//        return [
//            ['runByCode', null, InputOption::VALUE_OPTIONAL, 'Only used when running this command from code, set to 1', null],
//            ['scrapeLogId', null, InputOption::VALUE_REQUIRED, 'The ID of the scrapeLog to associate with the filelog.', null],
//        ];
//    }


}
