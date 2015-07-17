<?php

/**
 * Runs a task that analyses CSS in the theme directory for bloat.
 *
 * Stages:
 * 1. Prompt user for what CSS files should be included (i.e. exlcude any .min.css files)
 * 2. Load all the .ss templates in the manifest, and store their html contents in memory
 * 3. Given user-defined rules of actual pages to sample, load a collection of actual
 * 		URLs, using Director::test(), to get actual rendered html
 * 4. For each included stylesheet, parse it, and check each rule against all the html "samples",
 * 		both unrendered and rendered templates
 * 5. Given the "good" pile and "bad" pile, create a ".lean.css" and ".blubber.css" version of
 * 		each included CSS file
 *
 * @author  Uncle Cheese <unclechese@leftandmain.com>
 * @package  unclecheese/silverstripe-blubber
 */
class CSSBlubberTask extends CliController {

	/**
	 * A list of CSS files to ananlyst
	 * @var array
	 */
	protected $cssFiles = array();

	/**
	 * The theme to analyse
	 * @var string
	 */
	protected $theme;

	/**
	 * An absolute path to the theme directory
	 * @var string
	 */
	protected $themeDir;

	/**
	 * A reference to the file finder tool
	 * @var Symfony\Component\Finder
	 */
	protected $finder;

	/**
	 * A reference to the outputter interface
	 * @var Outputter
	 */
	protected $output;

	/**
	 * An array of HTML samples
	 * @var array
	 */
	protected $samples = array ();

	/**
	 * Constructor
	 * @param Symfony\Component\Finder $finder    The Finder dependency
	 * @param Symfony\Component\DOMCralwer $crawler   The DOMCrawler dependency
	 * @param Outputter $outputter The console output dependency
	 */
	public function __construct($finder, $crawler, $outputter) {
		$this->finder = $finder;
		$this->crawler = $crawler;
		$this->output = $outputter;		
		$this->limits = self::config()->limits;


		parent::__construct();
	}

	/**
	 * Runs the task
	 */
	public function index() {
		$this->theme = Config::inst()->get('SSViewer', 'theme');
		$this->themeDir = Controller::join_links(BASE_PATH, $this->ThemeDir());
		
		$this->gatherCSSFiles();
		$this->output->writeln();
		$this->output->write('Loading templates...');
		$this->loadTemplates();
		$this->output->write('Loading page URLs...');
		$this->output->clearProgress();
		$this->loadURLs();

		foreach($this->cssFiles as $css) {
			$file = CSSBlubberFile::create($css);

			$this->output->writeln();
			$this->output->write("Parsing file <caution>" . basename($css) . "</caution>...");
			$file->parse();
			$this->output->writeln();
			
			$blocks = $file->getBlocks();
			$totalBlocks = sizeof($blocks);
			$totalSamples = sizeof($this->samples);
			$used = $unused = 0;
						
			$this->output->writeln("==> Checking $totalBlocks declaration blocks against $totalSamples html samples... ");
						
			foreach($blocks as $block) {
				$found = false;
				foreach($block->getSelectors() as $selector) {
					$s = $selector->getSelector();
					// no pseudo elements
					if(stristr($selector,':')) continue;
					
					foreach($this->samples as $html) {						
						$this->crawler->clear();
						$this->crawler->addContent($html);
						$dom = $this->crawler->filter($s);						
						if(sizeof($dom) > 0) {													
							$used++;
							$found = true;	
							$file->removeBlubber($block);
							break 2;
						}
					}
				}
				if(!$found) {
					$unused++;
					$file->removeLean($block);
				}
				$this->output->updateProgress(sprintf(
					"      %s%% complete [Used: <success>%s</success> | Unused: <error>%s</error> | %s%% lean]",
					floor((($used+$unused)/$totalBlocks)*100),
					$used,
					$unused, 
					floor(($used/($used+$unused))*100)
				));					

			}

			$this->output->writeln();
			$this->output->writeln();

			$this->output->writeln("Writing $unused rules to <info>{$file->getBlubberName()}</info>");
			$file->saveBlubber();
			
			$this->output->writeln("Writing $used rules to <info>{$file->getLeanName()}</info>");
			$file->saveLean();
		}
	}

	/**
	 * Collects all the CSS files per the user's approval
	 */
	protected function gatherCSSFiles() {
		$this->output->writeln('Scanning theme "'.$this->theme.'" for CSS files');

		$this->finder->files()->in($this->themeDir)->name('*.css');
		
		foreach($this->finder as $file) {
			$filename = basename($file->getRealPath());
			if($this->output->ask("Include the file <caution>$filename</caution>?")) {
				$this->cssFiles[] = $file->getRealPath();
			}
		}
	}

	/**
	 * Loads all the static .ss templates as HTML into memory
	 */
	protected function loadTemplates() {		
		$manifest = SS_TemplateLoader::instance()->getManifest();
		$templates = $manifest->getTemplates();
		$total = sizeof($templates);
		$count = 0;
		$this->output->clearProgress();
		
		foreach($templates as $name => $data) {
			foreach($manifest->getCandidateTemplate($name, $this->theme) as $template) {
				$this->samples[] = $template;
			}
			$count++;			
			$this->output->updateProgressPercent($count, $total);
		}
		$this->output->writeln();

	}

	/**
	 * Loads all URLs to sample rendered content, per confirguration
	 */
	protected function loadURLs() {
		$omissions = self::config()->omit;
		$dataobjects = self::config()->extra_dataobjects;

		$i = 0;
		$classes = ClassInfo::subclassesFor('SiteTree');
		array_shift($classes);
		
		$sampler = Sampler::create($classes)
					->setDefaultLimit(self::config()->default_limit)
					->setOmissions(self::config()->omit)
					->setLimits(self::config()->limits);
		
		$list = $sampler->execute();
		$totalPages = $list->count();
		$this->output->clearProgress();

		foreach($list as $page) {
			$i++;

			if($html = $this->getSampleForObject($page)) {
				$this->samples[] = $html;
			}
			
			$this->output->updateProgress("$i / $totalPages");		
		}

		$this->output->writeln();
		
		if(!empty($dataobjects)) {
			$this->output->clearProgress();
			$i = 0;
			$sampler->setClasses($dataobjects);
			$list = $sampler->execute();
			$totalPages = $list->count();
			
			$this->output->write("Loading $totalPages DataObject URLs...");
			
			foreach($list as $object) {
				if(!$object->hasMethod('Link')) {
					$this->output->writeln("<error>{$object->ClassName} has no Link() method. Skipping.</error>");
					continue;
				}

				if($html = $this->getSampleForObject($object)) {
					$this->samples[] = $html;
				}				
				$i++;				
				$this->output->updateProgressPercent($i, $totalPages);
			}

			$this->output->writeln();
		}
	}

	/**
	 * Given a DataObject, get an actual SS_HTTPResponse of rendered HTML
	 * @param  DataObject $record 
	 * @return string             The rendered HTML
	 */
	protected function getSampleForObject(DataObject $record) {
		$response = Director::test($record->Link());

		if($response->getStatusCode() === 200) {
			return $response->getBody();
		}

		return false;
	}
}