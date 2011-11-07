<?php
/**
 * This sniff checks for the camel case format and
 * the (desired) prefix 'stub' in class names.
 *
 * @package  Classes
 * @version  $Id: stubValidClassNameSniff.php 2264 2009-07-13 19:00:05Z mikey $
 */
/**
 * This sniff checks for the camel case format and
 * the (desired) prefix 'stub' in class names.
 *
 * Copied and adapted content from Squiz/Sniffs/Classes/ValidClassNameSniff
 * because of no extending possibility (no methods/attributes to overwrite).
 *
 * @package  Classes
 */
class Stubbles_Sniffs_Classes_stubValidClassNameSniff implements PHP_CodeSniffer_Sniff
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
     * @param   PHP_CodeSniffer_File  $phpcsFile  The current file being processed.
     * @param   int                   $stackPtr   The position of the current token in the
     *                                            stack passed in $tokens.
     *
     * @return  void
     * @todo    think about to change following capital letter warning to an error
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Determine the name of the class or interface. Note that we cannot
        // simply look for the first T_STRING because a class name
        // starting with the number will be multiple tokens.
        $opener    = $tokens[$stackPtr]['scope_opener'];
        $nameStart = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), $opener, true);
        $nameEnd   = $phpcsFile->findNext(T_WHITESPACE, $nameStart, $opener);
        $className = trim($phpcsFile->getTokensAsString($nameStart, ($nameEnd - $nameStart)));
        $type      = ucfirst($tokens[$stackPtr]['content']);
        // if class name is not prefixed with stub and does not start with a capital letter
        if (substr($className, 0, 4) !== 'stub' && substr($className, 0, 3) !== 'vfs' && preg_match("|^[A-Z]|", $className) === 0) {
            $phpcsFile->addError($type . ' "' . $className . '" does not start with a capital letter', $stackPtr);
        // if class name does not contain alphanumeric characters only
        } elseif (preg_match("|[^a-zA-Z0-9]|", substr($className, 1)) > 0) {
            $phpcsFile->addError($type . ' "' . $className . '" is not in camel caps format', $stackPtr);
        } elseif ($this->hasNoTwoFollowingCapitalLetters($className) === false) {
            $phpcsFile->addWarning($type . ' "' . $className . '" contains two following capital letters', $stackPtr);
        }
    }

    /**
     * check that a class name does not contain two following capital letters
     *
     * @param   string  $className
     * @return  bool
     */
    protected function hasNoTwoFollowingCapitalLetters($className)
    {
        $length          = strlen($className);
        $lastCharWasCaps = true;
        for ($i = 1; $i < $length; $i++) {
            $ascii = ord($className{$i});
            // if character ascii value is lower then 48 or greater than 57
            // the character is not a letter, so it can not be a captial.
            if (48 <= $ascii && 57 >= $ascii) {
                $isCaps = false;
            } elseif (strtoupper($className{$i}) === $className{$i}) {
                $isCaps = true;
            } else {
                $isCaps = false;
            }

            if (true === $isCaps && true === $lastCharWasCaps) {
                return false;
            }

            $lastCharWasCaps = $isCaps;
        }
        
        return true;
    }
}
?>