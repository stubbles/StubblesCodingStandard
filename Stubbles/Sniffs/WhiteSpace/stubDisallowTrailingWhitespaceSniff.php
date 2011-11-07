<?php
/**
 * This sniff ensures that there is no trailing whitespace at the eol.
 * Excluded are lines which contain whitespace only.
 *
 * @author   Richard Sternagel <richard.sternagel@1und1.de>
 * @package  WhiteSpace
 */

/**
 * This sniff ensures that there is no trailing whitespace at the eol.
 * Excluded are lines which contain whitespace only.
 *
 * @package  WhiteSpace
 */
class Stubbles_Sniffs_WhiteSpace_stubDisallowTrailingWhitespaceSniff implements PHP_CodeSniffer_Sniff
{
    /**
     * standard error message
     */
    const ERROR = 'Trailing Whitespace at the end of line is not allowed';

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return  array
     */
    public function register()
    {
        return array(T_WHITESPACE,
                     T_COMMENT,
                     T_DOC_COMMENT
               );
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param   PHP_CodeSniffer_File  $phpcsFile  All the tokens found in the document.
     * @param   int                   $stackPtr   The position of the current token in
     *                                            the stack passed in $tokens.
     * @return  void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if($tokens[$stackPtr]['type'] === 'T_DOC_COMMENT') {
            if (preg_match("/[^*] +$/", $tokens[$stackPtr]['content']) === 1) {
                $phpcsFile->addError(self::ERROR, $stackPtr);
            }
        }

        if($tokens[$stackPtr]['type'] === 'T_COMMENT') {
            if (preg_match("/ +$/", $tokens[$stackPtr]['content']) === 1) {
                $phpcsFile->addError(self::ERROR, $stackPtr);
            }
        }

        if ($tokens[$stackPtr]['type'] === 'T_WHITESPACE') {
            // ignore windows line breaks which get caught by another sniff
            $lineBreakWin = strpos($tokens[$stackPtr]['content'], "\r\n");
            if(is_int($lineBreakWin)) {
                return;
            }

            $lineBreakPos = strpos($tokens[$stackPtr]['content'], "\n");
            $currentLine  = $tokens[$stackPtr]['line'];
            // check for additional whitespace at line breaks
            // but ignore lines which consists of whitespace only
            if (is_int($lineBreakPos)
                && $lineBreakPos !== 0
                && $currentLine === $tokens[($stackPtr-1)]['line']) {
                $phpcsFile->addError(self::ERROR, $stackPtr);
            }
        }
    }
}
?>
