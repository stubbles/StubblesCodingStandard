<?php
/**
 * This sniff checks for whitespace in functions calls:
 *
 * @package  Functions
 * @version  $Id: stubFunctionCallSignatureSniff.php 2012 2009-01-06 00:21:35Z mikey $
 */

/**
 * This sniff checks for whitespace in functions calls:
 * <ul>
 *  <li>my_function[*](...)</li>
 *  <li>my_function([*]...)</li>
 *  <li>my_function(...[*])</li>
 *  <li>my_function(...)[*]</li>
 * </ul>
 *
 * Multiline Methods should close on the same column:
 * So this is allowed:
 *
 *    stubClassLoader::load('net.stubbles.foo.bar',
 *                          'net.stubbles.foo.bar.foo',
 *                          'net.stubbles.bar.foo.bar'
 *    );
 *
 * ... and doesn't have to be:
 *
 *    stubClassLoader::load('net.stubbles.foo.bar',
 *                          'net.stubbles.foo.bar.foo',
 *                          'net.stubbles.bar.foo.bar');
 *
 * Copied and adapted content from PEAR/Sniffs/Functions/FunctionCallSignatureSniff
 * because of no extending possibility (no methods/attributes to overwrite).
 *
 * @package  Functions
 */
class Stubbles_Sniffs_Functions_stubFunctionCallSignatureSniff implements PHP_CodeSniffer_Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return  array
     */
    public function register()
    {
        return array(T_STRING);
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param   PHP_CodeSniffer_File  $phpcsFile  The file being scanned.
     * @param   int                   $stackPtr   The position of the current token in the
     *                                            stack passed in $tokens.
     * @return  void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Find the next non-empty token.
        $next = $phpcsFile->findNext(PHP_CodeSniffer_Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($tokens[$next]['code'] !== T_OPEN_PARENTHESIS) {
            // Not a function call.
            return;
        }

        if (isset($tokens[$next]['parenthesis_closer']) === false) {
            // Not a function call.
            return;
        }

        // Find the previous non-empty token.
        $previous = $phpcsFile->findPrevious(PHP_CodeSniffer_Tokens::$emptyTokens, ($stackPtr - 1), null, true);
        if ($tokens[$previous]['code'] === T_FUNCTION) {
            // It's a function definition, not a function call.
            return;
        }

        /*
         * Stubbles
         */
        if ($tokens[$previous]['code'] === T_NEW && $tokens[$previous-2]['code'] !== T_THROW) {
            // We are creating an object, not calling a function.
            // but ignore exceptions
            return;
        }

        if (($stackPtr + 1) !== $next) {
            // Checking this: $value = my_function[*](...).
            $error = 'Space before opening parenthesis of function call prohibited';
            $phpcsFile->addError($error, $stackPtr);
        }


        if ($tokens[($next + 1)]['code'] === T_WHITESPACE) {
            // Checking this: $value = my_function([*]...).
            $error = 'Space after opening parenthesis of function call prohibited';
            $phpcsFile->addError($error, $stackPtr);
        }

        $closer = $tokens[$next]['parenthesis_closer'];

        if ($tokens[($closer - 1)]['code'] === T_WHITESPACE) {
            // Checking this: $value = my_function(...[*]).
            $between = $phpcsFile->findNext(T_WHITESPACE, ($next + 1), null, true);

            // Only throw an error if there is some content between the parenthesis.
            // IE. Checking for this: $value = my_function().
            // If there is no content, then we would have thrown an error in the
            // previous IF statement because it would look like this:
            // $value = my_function( ).

            /*
             * Stubbles
             */

            // ignore functions within function calls
            if($tokens[$closer+1]['code'] !== T_SEMICOLON) {
                return;
            }

            $nextSemicolon  = $phpcsFile->findNext(T_SEMICOLON, ($stackPtr + 1));
            if($tokens[$nextSemicolon]['line'] === $tokens[$next]['line']) {
                // one line function call
                if ($between !== $closer) {
                    $error = 'Space before closing parenthesis of function call prohibited';
                    $phpcsFile->addError($error, $closer);
                }
            } else {
                // multiline function call
                // Checking this: [$foo = ]$obj->my_function(
                //                              ...
                //     indent ->           );
                // OR
                //                [$foo = ]sprintf(
                //                              ...
                //     indent ->           );
                // OR
                //                throw new fooException (
                //                      ...
                //     indent ->  );
                // OR
                //                [$foo = ]blaStatic::my_function (
                //                              ...
                //     indent ->           );

                $closingParenthesisIndent = $tokens[$nextSemicolon-1]['column'];
                $prevFunc                 = $phpcsFile->findPrevious(T_STRING, ($next - 1));
                $prevVar                  = $phpcsFile->findPrevious(T_VARIABLE, ($next - 1));
                $prevObjOp                = $phpcsFile->findPrevious(T_OBJECT_OPERATOR, ($next - 1));
                $prevThrow                = $phpcsFile->findPrevious(T_THROW, ($stackPtr - 1));
                $prevDoubleColon          = $phpcsFile->findPrevious(T_DOUBLE_COLON, ($next - 1));
                if ($prevVar && $tokens[$prevVar]['line'] === $tokens[$next]['line']) {
                    // if it is not a method call but a function call
                    if (false === $prevObjOp && $prevFunc && $tokens[$prevFunc]['line'] === $tokens[$next]['line'] && 
                            $tokens[$prevFunc]['column'] !== $closingParenthesisIndent) {
                        $error = 'Multiline Function call: Closing paranthesis indented incorrectly';
                        $phpcsFile->addError($error, $closer);
                    // if it is a method call
                    } else if (false !== $prevObjOp && $tokens[$prevVar]['column'] !== $closingParenthesisIndent) {
                        $error = 'Multiline Function call: Closing paranthesis indented incorrectly';
                        $phpcsFile->addError($error, $closer);
                    }
                } else if($prevThrow && $tokens[$prevThrow]['line'] === $tokens[$next]['line']) {
                    $exception = $phpcsFile->findNext(T_STRING, ($prevThrow + 1));
                    if($tokens[$exception]['column'] !== $closingParenthesisIndent) {
                        $error = 'Multiline Function call: Closing paranthesis indented incorrectly';
                        $phpcsFile->addError($error, $closer);
                    }
                } else if($prevDoubleColon && $tokens[$prevDoubleColon]['line'] === $tokens[$next]['line']) {
                    $staticClass = $prevDoubleColon-1;
                    if($tokens[$staticClass]['column'] !== $closingParenthesisIndent) {
                        $error = 'Multiline Function call: Closing paranthesis indented incorrectly';
                        $phpcsFile->addError($error, $closer);
                    }
                }
            }

        }

        $next = $phpcsFile->findNext(T_WHITESPACE, ($closer + 1), null, true);

        if ($tokens[$next]['code'] === T_SEMICOLON) {
            if (in_array($tokens[($closer + 1)]['code'], PHP_CodeSniffer_Tokens::$emptyTokens) === true) {
                $error = 'Space after closing parenthesis of function call prohibited';
                $phpcsFile->addError($error, $closer);
            }
        }
   }
}
?>