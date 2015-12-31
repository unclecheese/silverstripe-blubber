<?php

/**
 * A simple class for rendering output to the console
 *
 * @author Uncle Cheese <unclecheese@leftandmain.com>
 * @package  unclecheese/silverstripe-blubber
 */
class Outputter
{

    /**
     * The current value of the progress output
     * 
     * @var string
     */
    protected $progressValue = null;

    /**
     * Formats simple html-like markup into coloured text
     * Supports:
     *   - <b>bold text</b>
     *   - <success>Green text</success>
     *   - <error>Red text</error>
     *   - <caution>Yellow text</caution>
     *   - <info>Cyan text</info>
     *   
     * @param  string $text
     * @return string
     */
    public static function format($text)
    {
        $text = preg_replace_callback('/<b>(.+?)<\/b>/', function ($matches) {
            return SS_Cli::text($matches[1], null, null, true);
        }, $text);

        $text = preg_replace_callback('/<success>(.+?)<\/success>/', function ($matches) {
            return SS_Cli::text($matches[1], 'green', null, true);
        }, $text);

        $text = preg_replace_callback('/<error>(.+?)<\/error>/', function ($matches) {
            return SS_Cli::text($matches[1], 'red', null, true);
        }, $text);

        $text = preg_replace_callback('/<caution>(.+?)<\/caution>/', function ($matches) {
            return SS_Cli::text($matches[1], 'yellow', null, true);
        }, $text);

        $text = preg_replace_callback('/<info>(.+?)<\/info>/', function ($matches) {
            return SS_Cli::text($matches[1], 'cyan', null, true);
        }, $text);


        return $text;
    }

    /**
     * Writes text to the output stream, followed by a new line
     * 
     * @param  string $msg 
     */
    public function writeln($msg = '')
    {
        fwrite(STDOUT, $this->format($msg).PHP_EOL);
    }

    /**
     * Writes text to the output stream
     * @param  string $msg 
     */
    public function write($msg = '')
    {
        fwrite(STDOUT, $this->format($msg));
    }

    /**
     * Asks the user a question. If a 'y' value is entered, returns true
     * 
     * @param  string $question
     * @param  string $weight   Either 'y' or 'n' can have the default value (on enter key)
     * @return boolean
     */
    public function ask($question, $weight = "y")
    {
        $question = self::format($question);
        $y = $weight == 'y' ? 'Y' : 'y';
        $n = $weight == 'n' ? 'N' : 'n';
        $answer = $this->prompt("$question [$y/$n]");
        $line = trim(strtolower($answer));
        
        if (empty($line)) {
            $line = $weight;
        }

        return in_array($line, array('y', 'yes'));
    }

    /**
     * Prompts the user for input
     * 
     * @param  string $question 
     * @return string
     */
    public function prompt($question)
    {
        $question = self::format($question);
        $this->write("$question: ");
        $handle = fopen("php://stdin", "r");
        
        return fgets($handle);
    }

    /**
     * Updates the output with new text
     * 
     * @param  string $text 
     */
    public function updateProgress($text)
    {
        $text = self::format($text);
        if ($this->progressValue) {
            $len = strlen($this->progressValue);
            fwrite(STDOUT, "\033[{$len}D");
        }
        $this->progressValue = $text;

        fwrite(STDOUT, $this->progressValue);
    }

    /**
     * A helper method for updating the value when it is a percentage
     * 
     * @param  int $done  The numerator
     * @param  int $outOf The denominator
     */
    public function updateProgressPercent($done, $outOf)
    {
        $this->updateProgress(floor((($done/$outOf)*100)).'%');
    }

    /**
     * Resets the output
     * 
     * @return InPlaceOutput
     */
    public function clearProgress()
    {
        $this->progressValue = null;
    }
}
