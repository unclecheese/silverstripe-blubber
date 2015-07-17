<?php

/**
 * Gets a database sample for each given class of DataObject it is provided
 *
 * @author UncleCheese <unclecheese@leftandmain.com>
 * @package  unclecheese/silverstripe-blubber
 */
class Sampler extends Object {

	/**
	 * A map of class names and their their respective sample sizes
	 *
	 * e.g.
	 *  array (
	 *  	'BlogPost' => 10,
	 *  	'Product' => 30
	 *  );
	 * @var array
	 */
	protected $limits = array ();

	/**
	 * The list of classes being sampled
	 * @var array
	 */
	protected $classes;

	/**
	 * Classes to omit, e.g. "RedirectorPage"
	 * @var array
	 */
	protected $omissions = array ();

	/**
	 * The default limit for any given class that does not have a limit specified in $limits
	 * @var integer
	 */
	protected $defaultLimit = 10;

	/**
	 * Constructor
	 * 
	 * @param array $classes 
	 */
	public function __construct($classes) {
		$this->classes = $classes;

		parent::__construct();
	}

	/**
	 * Executes the query, gets the samples
	 * @return ArrayList
	 */
	public function execute() {
		$results = ArrayList::create();
		foreach($this->classes as $c) {

			if($this->isOmitted($c)) continue;

			$list = DataList::create($c)
						->filter('ClassName', $c)
						->limit($this->getLimitFor($c))
						->sort("RAND()");

			foreach($list as $record) {
				$results->push($record);
			}
		}

		return $results;		
	}

	/**
	 * Returns true if a class is omitted
	 * 
	 * @param  string  $c The class name
	 * @return boolean    
	 */
	protected function isOmitted($c) {
		foreach($this->omissions as $o) {
			if($c == $o || is_subclass_of($c, $o)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets the limit for a given class
	 * 
	 * @param  string $class 
	 * @return int
	 */
	protected function getLimitFor($class) {
		if(isset($this->limits[$class])) return $this->limits[$class];

		return $this->defaultLimit;
	}

	/**
	 * Sets the list of omitted classes
	 * 
	 * @param array $classes
	 * @return  Sampler
	 */
	public function setOmissions($classes) {
		$this->omissions = $classes;

		return $this;
	}

	/**
	 * Sets the default limit
	 * 
	 * @param int $limit
	 * @return Sampler
	 */
	public function setDefaultLimit($limit) {
		$this->defaultLimit = $limit;

		return $this;
	}

	/**
	 * Sets the map of limits
	 * 
	 * @param array $limits [description]
	 * @return  Sampler
	 */
	public function setLimits($limits) {
		$this->limits = $limits;

		return $this;
	}

	/**
	 * Sets the list of classes
	 * 
	 * @param array $classes
	 * @return  Sampler
	 */
	public function setClasses($classes) {
		$this->classes = $classes;

		return $this;
	}
}