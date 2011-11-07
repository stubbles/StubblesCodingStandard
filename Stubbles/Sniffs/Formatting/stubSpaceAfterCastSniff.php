<?php
/**
 * This sniff ensures that there is a space after cast tokens.
 *
 * @author   Richard Sternagel <richard.sternagel@1und1.de>
 * @package  Formatting
 */

/**
 * This sniff ensures that there is a space after cast tokens.
 *
 * @package  Formatting
 */
class Stubbles_Sniffs_Formatting_stubSpaceAfterCastSniff implements PHP_CodeSniffer_Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return  array
     */
    public function register()
    {
        return PHP_CodeSniffer_Tokens::$castTokens;
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param   PHP_CodeSniffer_File   $phpcsFile  The file being scanned.
     * @param   int                    $stackPtr   The position of the current token in
     *                                             the stack passed in $tokens.
     * @return  void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[($stackPtr + 1)]['code'] !== T_WHITESPACE) {
            $error = 'A cast statement must be followed by a space';
            $phpcsFile->addError($error, $stackPtr);
        }
    }
}
?>
