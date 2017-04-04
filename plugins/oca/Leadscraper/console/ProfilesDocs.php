<?php namespace Oca\Leadscraper\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Oca\Leadscraper\Console\ProfilesBase;
use Excel;
use Storage;
use Oca\ListManager\Models\ListOriginal;

class ProfilesDocs extends ProfilesBase
{
    /**
     * @var string The console command name.
     */
    protected $name = 'leadscraper:profilesdocs';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';

    /**
     * The name and signature of the console command.
     *  newInPast can technically be any number, but lets use their defaults.
     * @var string
     */
    protected $signature = 'leadscraper:profilesdocs
                        {specialtyIDs : The IDs of the specialty scrape, camma separated}
                        {--person= : What is the persons name to start with \'smith, john\'}
                        {--scrapeLogId= : The ID of the scrapeLog to associate with the filelog}
                        (--newInPast= : Number of days.  Your options are: 7, 30, 60, 90.   Dont use this option for unlimited}
                        {--runByCode= : Only used when running this command from code, set to 1}
                        {--excelFolder= : The folder inside storage/app/ to import CSVs instead of from the website}
                        ';

    protected $startPerson; // used to store the option
    protected $excelFolder; // used to store the option
    protected $newInPast; // used to store the option

    protected $profilesCount;
    protected $currentSpecialtyOption;
    protected $numberOfCompleted = 0;
    protected $numberOfDup = 0;
    protected $everySpecialty = '11,25,31,42,50,52,59,65,71,79,85,86,88,104,114,121,130,140,144,146,151,156,191,167,172,173,174,179,180,182,186,189,195,202,206,207,210,226,236,237,244,251';



    /*
     * Example how to run this script with xdebug
vagrant@scotchbox:/var/www/october$ PHP_IDE_CONFIG="serverName=vagrant" XDEBUG_CONFIG="idekey=PHPSTORM"  XDEBUG_CONFIG="idekey=PHPSTORM remote_host=10.0.2.2 remote_port=9000" php5 artisan leadscraper:profilesdocs 11,25,31,42,50,52,59,65,71,79,85,86,88,104,114,121,130,140,144,146,151,156,191,167,172,173,174,179,180,182,186,189,195,202,206,207,210,226,236,237,244,251 --scrapeLogId=01192017 --runByCode=1
     */


    /**
     * Execute the console command.
     * @return void
     */
    public function fire()
    {
        $this->setFireVars();

//        $this->blankToNull();
//        exit();

        // @todo Create quality tests (incase the website changes and breaks stuff) (firstname, email, other fields, residency/fellowship)
        // @todo 7 day, 30 day, 60, 90,  etc...
        // @todo specialty converter (long, short, specialist)

        $this->startPerson = $this->option('person');
        $this->excelFolder = $this->option('excelFolder');

        if($this->excelFolder){
            $files = Storage::allFiles($this->excelFolder);
            foreach($files as $key => $file){
                $this->entityList = array(); //clear doc list
                $this->entityList = Excel::load("storage/app/$file")->get()->toArray();
                $this->processDocList(FALSE, FALSE);
            }
            exit();
        }

        $this->login();

        // get specialty ids to run
        $ids = explode(',', $this->argument('specialtyIDs'));

        foreach ($ids as $key => $specialty){
            $this->currentSpecialtyOption = $ids[$key];

            //clear doc list
            $this->entityList = array();
            // grab the list of doctors for this speciality
            $this->getDocList();

            $this->processDocList();
        }
    }

    /**
     * $scrapeIndividual - only marked false if processing CSV/Excel rows.
     */
    public function processDocList($scrapeIndividual = TRUE, $duplicateFields = TRUE)
    {
        // grab the full info of each individual doc, check for duplicates and save them.
        foreach ($this->entityList as $currentNumber => $row){
            // reset $this->entity
            $this->entity = $row; // @todo I should probably use $row everywhere or $this->entity everywhere.
            // skip if needed - check for a starting point.  Once we find it... set so we don't check this again
            if (isset($this->startPerson)){
                if ($this->startPerson == $row['name']){
                    // reset startPerson is this doesn't catch again & keep going
                    $this->startPerson = null;
                } else {
                    continue; // skip the rest of this loop
                }
            }
            //duplicate check
            $mapEntity = $this->mapEntity($row);
            $dup = $this->checkEntityDuplicate($mapEntity, $duplicateFields); // TRUE is to search for all the current items in entity (just the basics from the profile search table)

            if($dup){
                $this->numberOfDup++;
                continue; // skip duplicates.
            }

            if($scrapeIndividual){
                // $this->entity is populated here
                $this->getIndivDocsFullInfo($row); // if startPerson is set, everyone before is skipped.  false if skipped
            }
            // save to the DB
            $this->mapAndSaveRow(); //@todo don't save this for individuals, save at the end of $this->fire() in bulk
            $this->numberOfCompleted++;

            // check current number of docs
            $currentCount = $currentNumber+1;
            $message = "$currentCount of " . $this->profilesCount . " docs.  Last worked on: ."
                . $row['name'] . ' | ' . $row['specialty'];
            $this->myConsoleLog('info',$message);
        }
    }

    /**
     * @param $pageIndex
     *
     **/
    private function searchDocs($pageIndex){
        $url = $this->baseProfilesUrl . '/physiciansearch/physician_search_results.cfm?items_per_page=100';

        if(empty($pageIndex)){
            // first go to the search page.
            $this->getSearchPage();

            // get the form values and post them to search
            $params = $this->getPhysSearchParams();
            $params['specialty'] = $this->currentSpecialtyOption;

            $form = $this->crawler->selectButton('Search')->form();
            $this->crawler = $this->client->submit($form, $params);
        }

        if ($pageIndex){
            $url .= "&page=$pageIndex";
        }
        $html = $this->crawler->html();

        // go to the url again but with page index and items per page
        $this->crawler = $this->client->request('GET', $url);

        //$this->crawler = $this->client->request('POST', $url, $params);
        $html = $this->crawler->html();
    }

    // this isn't used as of 1/13/2017
    private function quickSearchDocs($pageIndex){
        // this is the original 2017 (used in 2016) way that is now outdated 1/12/2017
        $searchUrl = $this->baseProfilesUrl . '/physiciansearch/physician_search_results.cfm';

        if ($pageIndex){
            $params = array("pageIndex" => $pageIndex);
            $urlStart = '?';
        } else {
            $params = array(
                'year' => 2017,
                'specialtyId' => $this->currentSpecialtyOption,
                'geoPref' => '',
            );
            $urlStart = '?quicksearch&';
        }

        $params = http_build_query($params);

        $url = $searchUrl . $urlStart . $params;
        $this->crawler = $this->client->request('GET', $url);

        // now I need to set the page to 500 per page
        // only set this if on the first page (ie, pageIndex is 1 or not set)
        if(empty($pageIndex) || $pageIndex == 1)
            $this->crawler = $this->client->request('POST', $searchUrl, array('search.itemsPerPage' => 500));

        $html = $this->crawler->html();
    }

    private function getDocList($page = null)
    {
        // lets search the docs
        //$this->quickSearchDocs(isset($page['nextIndex']) ? $page['nextIndex'] : null);
        $this->SearchDocs(isset($page['nextIndex']) ? $page['nextIndex'] : null);

        // are their any results?
        $noresults = $this->crawler->filter('.main-content .span12 h3')->each(function ($node) {
            if(trim($node->text()) == 'Your search returned no results'){
                $message = "No results for specialty: " . $this->currentSpecialtyOption;
                $this->myConsoleLog('info',$message);
                return true;
            }
            return false;
        });

        if($noresults){
            return; // do nothing and skip
        }

        // get counts listed number of docs:
        $this->profilesCount = $this->getProfilesCount();
        $countPerPage = $this->crawler->filter('tbody tr')->count();

        // if we have a page number, go to that page number, if not calculate # of pages
        if(isset($page['nextIndex'])){
            $page['nextIndex']++;
        } else {
            $page['numberOfPages'] = ceil($this->profilesCount / $countPerPage);  // round up
            $page['nextIndex'] = 2; // the next page will be the 2nd page.
        }

        $message = "Showing $countPerPage results per page.";
        $this->myConsoleLog('info',$message);

        // grab the doctors information off the table row by row
        $this->crawler->filter('tbody tr')->each(function ($node)
        {
            // @todo queue the per doc scraping
            $row = $this->getDocRow($node);
            // set it so we can count it later... or iterate through
            $this->entityList[] = $row;
        });

        // pagination
        if($page['nextIndex'] <= $page['numberOfPages']) {
            $this->getDocList($page);
        }
    }

    private function getDocRow($nodeRow){
        $yearid = $nodeRow->filter('td:nth-child(4)')->extract(array('onclick'));
        $yearid = $this->getYearId($yearid[0]);

        // Set it all in a nice $doc array
        $row['year'] = $yearid['year'];
        $row['id'] = $yearid['id'];
        $row['name'] = $this->getColumn($nodeRow, 'td:nth-child(4)');
        $splitname = explode(',', $row['name']);
        $row['firstname'] = trim($splitname[1]);
        $row['lastname'] = trim($splitname[0]);
        $row['specialty'] = $this->getColumn($nodeRow, 'td:nth-child(5)');

        return $row;
    }

    // @todo current: test this... I changed a bunch of stuff.
    private function getIndivDocsFullInfo($row){
        // reset doc to the current row
        $this->entity = $row;

        $dpc = $this->getDocPageCrawler($row);

        // Actually get the info per page
        $this->getDocInfo($dpc);
        $this->getOtherColumns($dpc);
        $this->getEduColumns($dpc);
    }

    private function getDocPageCrawler($row){
        // url for doc
        $this->entity['year'] = $year = $row['year'];
        $this->entity['id'] = $id = $row['id'];
        $url = $this->baseProfilesUrl . "/physiciansearch/physician_details.cfm?id=$id&year=$year";

        // Doc Page Crawler
        $dpc = $this->client->request('GET', $url);
        return $dpc;
    }

    private function getDocInfo($dpc)
    {
        // get info for doc
        // The address will look something like: 448 E Ontario St, 302\r\n\t\t\t\t\tChicago, IL\r\n\t\t\t\t\t60611
        $address = explode("\r\n",$this->getColumn($dpc, 'address'));
        $city = array();
        if(isset($address[1]))
            $city = explode(',', $address[1]);

        $stateZip = '';
        if (isset($city[1]))
            $stateZip = preg_split("/\s+/", trim($city[1]));

        $this->entity['address'] = isset($address[0]) ? trim($address[0]) : $this->getColumn($dpc, 'address');
        $this->entity['city'] = isset($city[0]) ? trim($city[0]) : '';
        $this->entity['state'] = isset($stateZip[0]) ? trim($stateZip[0]) : '';
        $this->entity['zip'] = isset($stateZip[1]) ? trim($stateZip[1]) : '';
        $this->entity['email1'] = $this->getColumn($dpc, '#PhysEmail1');
        $this->entity['email2'] = $this->getColumn($dpc, '#PhysEmail2');
    }

    private function getColumn($crawler, $selector)
    {
        $text = '';
        $node = $crawler->filter($selector);
        if ($node->count())
            $text = trim($node->text());

        return $text;
    }

    private function getOtherColumns($crawler)
    {
        if ($crawler->filter('.proDetailLabel')->count()){
            $values = $crawler->filter('.proDetailLabel')->each(function ($node) {
                // get the label, then the sibling which is the value (it should only have 1 sibling, doing text() only grabs the first one)
                return array('name' => str_replace(':', '', trim($node->text())), 'value' => trim($node->nextAll()->text()));
            });

            foreach ($values as $info){
                if($info['name'] == "Hometown" && isset($newValues['Hometown']) ){
                    $info['name'] = "Spouse Hometown";
                }
                $newValues[$info['name']] = $info['value'];
            }

            // I do it this way so I fill my array in order
            foreach ($this->getHtmlColumns() as $column){
                if(isset($newValues[$column])){
                    // it could have been set previously... if not, set it.
                    if(!isset($this->entity[$column]))
                        $this->entity[$column] = $newValues[$column];
                } else {
                    // it could have been set previously... if not, set blank.
                    if(!isset($this->entity[$column]))
                        $this->entity[$column] = '';
                }
            }
        }
        else {
            $message = 'ERROR: No proDetail\'s found for ' . $this->entity['name'] . ' | ' . $this->entity['specialty'];
            $this->myConsoleLog('error',$message);
        }
        // @todo get "Addl Fellowship Plans" so we can filter out if they are going into fellowship
    }


    private function getEduColumns($crawler)
    {
        $edu = array();
        $edu = $crawler->filter('.physicianItem tbody tr')->each(function ($node) {
            $training = $node->filter('td');
//            if( $training->count() && (trim($training->text()) == "Residency" || trim($training->text()) == "Fellowship") ){
            $name = trim($training->text());
            $school = $training->nextAll()->eq(1)->text();
            return array('name' => $name, 'program' => trim($school));
        });

        // format an array better
        foreach ($edu as $row){
            $training[$row['name']] = $row['program'];
        }

        $this->entity['Residency'] = isset($training['Residency']) ? $training['Residency'] : '';
        $this->entity['Fellowship'] = isset($training['Fellowship']) ? $training['Fellowship'] : '';
    }


    public function mapAndSaveRow(){
        $entityMapped = $this->mapEntity($this->entity);
        $this->saveRowInfo($entityMapped);
    }

    public function mapEntity($entity){
        $entityMapped = array();
        $mapper = array_flip($this->getDocMapper());  // get it as htmlcolumnName => DBcolumnName
        foreach($entity as $key => $text){
            if(!isset($mapper[$key])){
                $entityMapped[$key] = trim($text);
            } else {
                $entityMapped[$mapper[$key]] = empty($text) ? NULL : trim($text);
            }
        }
        return $entityMapped;
    }

    public function getHtmlColumns()
    {
        return array(
            // These are set w/o checking this array...
            'year',
            'id',
            'name',
            'firstname',
            'lastname',
            'specialty',
            'address',
            'city',
            'state',
            'zip',
            'email1',
            'email2',

            // the ones below here match the "other" column names
            'Cell Phone',
            'Home Phone',
            'Program Phone',
            'Hospital Phone',
            'Page Phone',
            'Citizenship',
            'Hometown',
            'Gender',
            'Children',
            'Marital Status',
            'Spouse Name',
            'Spouse Hometown', // this is actually called "Hometown" on the page.
            'Occupation',

            // Other things added by me
            'Residency',
            'Fellowship',
            'Addl Fellowship Plans',
        );
    }


    // @todo this is a little duplication of getHtmlColumns()
    public function getDocMapper() {
        // db column name => html name (found on the site)
        $mapper = array(
            "source" => 'NEEDS TO BE SET MANUALLY',
            "year" => 'year',
            "listServiceId" => 'id',
            "name" => 'name',
            "firstname" => 'firstname',
            "lastname" => 'lastname',
            "specialty" => 'specialty',
            "address" => 'address',
            "city" => 'city',
            "state" => 'state',
            "zip" => 'zip',
            "email1" => 'email1',
            "email2" => 'email2',

            // the ones below here match the "other" column names
            "cellPhone" => 'Cell Phone',
            "homePhone" => 'Home Phone',
            "programPhone" => 'Program Phone',
            "hospitalPhone" => 'Hospital Phone',
            "pagePhone" => 'Page Phone',
            "citizenship" => 'Citizenship',
            "hometown" => 'Hometown',
            "gender" => 'Gender',
            "children" => 'Children',
            "maritalStatus" => 'Marital Status',
            "spouseName" => 'Spouse Name',
            "spouseHometown" => 'Spouse Hometown',// this is actually called "Hometown" on the page.
            "occupation" => 'Occupation',

            // Other things added by me
            "residency" => 'Residency',
            "fellowship" => 'Fellowship',
            "addlFellowshipPlans" => 'Addl Fellowship Plans'
        );
        return $mapper;
    }

    public function blankToNull(){
        // @todo fix this to work with models.
        $entity = ListOriginal::All()
            ->get()->toArray();

        foreach($entity as $key => $value){
            foreach ($value as $name => $data){
                if(empty($data)){
                    $entity[$key]->$name = null;
                }
            }
            $toArray = (array)$entity[$key];
//            $asdf['year'] = 2020;
//            $this->listDb->table('originalLists')->insert($asdf);
            $result = ListOriginal::where('id', '=', $entity[$key]->id)->update($toArray);
        }


        exit();
    }
}
