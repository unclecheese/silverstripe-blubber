<?php
/**
 * An object representing a CSS file that is being analysed for what is lean
 * and what is "blubber."
 *
 * @author Uncle Cheese <unclecheese@leftandmain.com>
 * @package  unclecheese/silverstripe-blubber
 */
use Sabberworm\CSS\Parser;

class CSSBlubberFile extends Object
{

    /**
     * The absolute path to the CSS file
     * @var string
     */
    protected $path;

    /**
     * A reference to the parsed CSS object containing all the "good" rules
     * @var Sabberworm\CSS\Parser
     */
    protected $leanCSS;

    /**
     * A reference to the parsed CSS object containing all the "bad" rules
     * @var Sabberworm\CSS\Parser
     */
    protected $blubberCSS;

    /**
     * Constructor
     * @param string $css Absolute path to the CSS file
     */
    public function __construct($css)
    {
        $this->path = $css;

        parent::__construct();
    }

    /**
     * Begins parsing the CSS file
     */
    public function parse()
    {
        $css = new Parser(file_get_contents($this->path));

        $this->leanCSS = $css->parse();
        $this->blubberCSS = clone $this->leanCSS;
    }

    /**
     * Gets the directory name of the CSS file
     * @return string
     */
    public function getDirname()
    {
        return dirname($this->path);
    }

    /**
     * Gets the file name for the "blubber" file, containing all the unused rules
     * @return string
     */
    public function getBlubberName()
    {
        return basename($this->path, '.css').'.blubber.css';
    }

    /**
     * Gets the file name for the "leadn" file, containing all the used rules
     * @return string
     */
    public function getLeanName()
    {
        return basename($this->path, '.css').'.lean.css';
    }

    /**
     * Gets all the declaration blocks in the subject CSS file
     * @return Sabberworm\CSS\CSSList
     */
    public function getBlocks()
    {
        return $this->leanCSS->getAllDeclarationBlocks();
    }

    /**
     * Removes a declaration block from the "blubber" CSS file
     * @param  Sabberworm\CSS\DeclarationBlock $block 	 
     */
    public function removeBlubber($block)
    {
        $this->blubberCSS->remove($block);
    }

    /**
     * Removes a declaration block from the "lean" CSS file
     * @param  Sabberworm\CSS\DeclarationBlock $block 	 
     */
    public function removeLean($block)
    {
        $this->leanCSS->remove($block);
    }

    /**
     * Saves the "blubber" CSS to the filesystem	 
     */
    public function saveBlubber()
    {
        $fh = fopen($this->getDirname().'/'.$this->getBlubberName(), 'w');
        fwrite($fh, $this->blubberCSS->render());
    }

    /**
     * Saves the "lean" CSS to the filesystem	 
     */
    public function saveLean()
    {
        $fh = fopen($this->getDirname().'/'.$this->getLeanName(), 'w');
        fwrite($fh, $this->leanCSS->render());
    }
}
