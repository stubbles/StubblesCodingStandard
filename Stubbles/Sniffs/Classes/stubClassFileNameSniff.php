<?php
/**
 * Checks file and classname equality.
 *
 * @author   Richard Sternagel <richard.sternagel@1und1.de>
 * @package  Classes
 */
require_once('stubClassExcluder.php');
/**
 * Checks file and classname equality.
 *
 * Copied and adapted content from Squiz/Sniffs/Classes/ClassFileNameSniff
 * because of no extending possibility (no methods/attributes to overwrite).
 *
 * @package  Classes
 */
class Stubbles_Sniffs_Classes_stubClassFileNameSniff implements PHP_CodeSniffer_Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return  array
     */
    public function register()
    {
        return array(
                T_CLASS,
                T_INTERFACE,
               );
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param  PHP_CodeSniffer_File  $phpcsFile  The file being scanned.
     * @param  int                   $stackPtr   The position of the current token in the
     *                                           stack passed in $tokens.
     * @return  void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens    = $phpcsFile->getTokens();
        $decName   = $phpcsFile->findNext(T_STRING, $stackPtr);
        $fullPath  = basename($phpcsFile->getFilename());
        $fileName  = substr($fullPath, 0, strrpos($fullPath, '.'));
        $className = $tokens[$decName]['content'];
        
        if ($className !== $fileName
            && stubClassExcluder::isExcluded($className) === false) {
            $error  = ucfirst($tokens[$stackPtr]['content']);
            $error .= ' name (' . $className . ') doesn\'t match filename. Expected ';
            $error .= '"'.$tokens[$stackPtr]['content'].' ';
            $error .= $fileName.'".';
            $phpcsFile->addError($error, $stackPtr);
        }
    }
}
?>