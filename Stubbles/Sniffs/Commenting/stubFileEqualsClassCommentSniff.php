<?php
/**
 * This sniff checks the equality of file and class doc block.
 *
 * @author   Richard Sternagel <richard.sternagel@1und1.de>
 * @package  Commenting
 */

/**
 * This sniff checks the equality of file and class doc block.
 *
 * @package  Commenting
 */
class Stubbles_Sniffs_Commenting_stubFileEqualsClassCommentSniff implements PHP_CodeSniffer_Sniff
{
    /**
     * tags which are only used in file doc blocks.
     *
     * @var  array<strings>
     */
    protected $excludedFileTags = array('@author', '@version');

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return  array
     */
    public function register()
    {
        return array(T_CLASS);
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param  PHP_CodeSniffer_File  $phpcsFile  The file being scanned.
     * @param  int                   $stackPtr   The position of the current token in the
     *
     * @return  void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        // exclude stubClassLoader
        if (basename($phpcsFile->getFilename(), '.php') === 'stubClassLoader') {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $fileComments  = array();
        $classComments = array();

        for($i = 0; $i < $stackPtr; $i++) {
            if ($tokens[$i]['type'] === 'T_DOC_COMMENT') {
                $line   = trim($tokens[$i]['content']);

                foreach ($this->excludedFileTags as $tag) {
                    if (preg_match('/^' .$tag .'.*/', substr($line, 2)) === 1) {
                        // don't save this line
                        continue 2;
                    }
                }

                if (1 === $i || '*/' !== end($fileComments)) {
                    $fileComments[] = $line;
                } else {
                    $classComments[] = $line;
                }
            }
        }

        // diffs only in one direction: file -> class
        $diff = array_diff($fileComments, $classComments);
        if (count($diff) > 0) {
            $keys = array_keys($diff);
            foreach ($keys as $k) {
                $error = 'File and class doc block differ in line ' . $k;
                $phpcsFile->addError($error, $stackPtr);
            }
        }
    }
}
?>