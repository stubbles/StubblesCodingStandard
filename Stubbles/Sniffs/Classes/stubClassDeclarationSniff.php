<?php
/**
 * Checks whitespace at the declaration of a class/interface
 *
 * @author   Richard Sternagel <richard.sternagel@1und1.de>
 * @package  Classes
 */

if (class_exists('PEAR_Sniffs_Classes_ClassDeclarationSniff', true) === false) {
    $error = 'Class PEAR_Sniffs_Classes_ClassDeclarationSniff not found';
    throw new PHP_CodeSniffer_Exception($error);
}

/**
 * Checks whitespace at the declaration of a class/interface
 *
 * Copied and adapted content from Squiz/Sniffs/Classes/ClassDeclarationSniff
 * because of no extending possibility (no methods/attributes to overwrite).
 *
 * @package  Classes
 */
class Stubbles_Sniffs_Classes_stubClassDeclarationSniff extends PEAR_Sniffs_Classes_ClassDeclarationSniff
{
    /*
     * Stubbles
     */
    /**
     * exclude special classes
     *
     * @var  array<string>
     */
    protected $excludedClasses = array('stubClassNotFoundException',
                                       'stubClassLoader'
                                 );

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
     * @param  int                   $stackPtr   The position of the current token
     *                                           in the stack passed in $tokens.
     * @return  void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        // We want all the errors from the PEAR standard, plus some of our own.
        parent::process($phpcsFile, $stackPtr);

        $tokens = $phpcsFile->getTokens();

        /*
            Check that this is the only class or interface in the file.
        */

        $nextClass = $phpcsFile->findNext(array(T_CLASS, T_INTERFACE), ($stackPtr + 1));

        if ($nextClass !== false) {
            /*
            * Stubbles
            */
            $classPtr  = $phpcsFile->findNext(T_STRING, $nextClass);
            $className = $tokens[$classPtr]['content'];

            if (in_array($className, $this->excludedClasses) === false) {
                // We have another, so an error is thrown.
                $error = 'Only one interface or class is allowed in a file';
                $phpcsFile->addError($error, $nextClass);
            }
        }

        /*
            Check alignment of the keyword and braces.
        */

        if ($tokens[($stackPtr - 1)]['code'] === T_WHITESPACE) {
            $prevContent = $tokens[($stackPtr - 1)]['content'];
            if ($prevContent !== $phpcsFile->eolChar) {
                $blankSpace = substr($prevContent, strpos($prevContent, $phpcsFile->eolChar));
                $spaces     = strlen($blankSpace);

                if (in_array($tokens[($stackPtr - 2)]['code'], array(T_ABSTRACT, T_FINAL)) === false) {
                    if ($spaces !== 0) {
                        $type  = strtolower($tokens[$stackPtr]['content']);
                        $error = "Expected 0 spaces before $type keyword; $spaces found";
                        $phpcsFile->addError($error, $stackPtr);
                    }
                } else {
                    if ($spaces !== 1) {
                        $type        = strtolower($tokens[$stackPtr]['content']);
                        $prevContent = strtolower($tokens[($stackPtr - 2)]['content']);
                        $error       = "Expected 1 space between $prevContent and $type keywords; $spaces found";
                        $phpcsFile->addError($error, $stackPtr);
                    }
                }
            }
        }

        $closeBrace = $tokens[$stackPtr]['scope_closer'];
        if ($tokens[($closeBrace - 1)]['code'] === T_WHITESPACE) {
            $prevContent = $tokens[($closeBrace - 1)]['content'];
            if ($prevContent !== $phpcsFile->eolChar) {
                $blankSpace = substr($prevContent, strpos($prevContent, $phpcsFile->eolChar));
                $spaces     = strlen($blankSpace);
                if ($spaces !== 0) {
                    $error = "Expected 0 spaces before closing brace; $spaces found";
                    $phpcsFile->addError($error, $closeBrace);
                }
            }
        }

        /*
         * Stubbles
         */
        // Check that the closing brace has one blank line after it.
        // Stubbles => omitted

        // Check the closing brace is on it's own line, but allow
        // for comments like "//end class".
        $nextContent = $phpcsFile->findNext(T_COMMENT, ($closeBrace + 1), null, true);
        if ($tokens[$nextContent]['content'] !== $phpcsFile->eolChar && $tokens[$nextContent]['line'] === $tokens[$closeBrace]['line']) {
            $type  = strtolower($tokens[$stackPtr]['content']);
            $error = "Closing $type brace must be on a line by itself";
            $phpcsFile->addError($error, $closeBrace);
        }

        /*
            Check that each of the parent classes or interfaces specified
            are spaced correctly.
        */

        // We need to map out each of the possible tokens in the declaration.
        $keyword      = $stackPtr;
        $openingBrace = $tokens[$stackPtr]['scope_opener'];
        $className    = $phpcsFile->findNext(T_STRING, $stackPtr);

        /*
            Now check the spacing of each token.
        */

        $name = strtolower($tokens[$keyword]['content']);

        // Spacing of the keyword.
        $gap = $tokens[($stackPtr + 1)]['content'];
        if (strlen($gap) !== 1) {
            $found = strlen($gap);
            $error = "Expected 1 space between $name keyword and $name name; $found found";
            $phpcsFile->addError($error, $stackPtr);
        }

        // Check after the name.
        $gap = $tokens[($className + 1)]['content'];
        if (strlen($gap) !== 1) {
            $found = strlen($gap);
            $error = "Expected 1 space after $name name; $found found";
            $phpcsFile->addError($error, $stackPtr);
        }

        // Now check each of the parents.
        $parents    = array();
        $nextParent = ($className + 1);
        while (($nextParent = $phpcsFile->findNext(array(T_STRING, T_IMPLEMENTS), ($nextParent + 1), ($openingBrace - 1))) !== false) {
            $parents[] = $nextParent;
        }

        $parentCount = count($parents);

        $nextComma = 0;
        for ($i = 0; $i < $parentCount; $i++) {
            if ($tokens[$parents[$i]]['code'] === T_IMPLEMENTS) {
                continue;
            }

            if ($tokens[($parents[$i] - 1)]['code'] !== T_WHITESPACE) {
                $name  = $tokens[$parents[$i]]['content'];
                $error = "Expected 1 space before \"$name\"; 0 found";
                $phpcsFile->addError($error, ($nextComma + 1));
            } else {
                $spaceBefore = strlen($tokens[($parents[$i] - 1)]['content']);
                if ($spaceBefore !== 1) {
                    $name  = $tokens[$parents[$i]]['content'];
                    $error = "Expected 1 space before \"$name\"; $spaceBefore found";
                    $phpcsFile->addError($error, $stackPtr);
                }
            }

            if ($tokens[($parents[$i] + 1)]['code'] !== T_COMMA) {
                if ($i !== ($parentCount - 1)) {
                    // This is not the last parent, and the comma
                    // is not where we expect it to be.
                    if ($tokens[($parents[$i] + 2)]['code'] !== T_IMPLEMENTS) {
                        $found = strlen($tokens[($parents[$i] + 1)]['content']);
                        $name  = $tokens[$parents[$i]]['content'];
                        $error = "Expected 0 spaces between \"$name\" and comma; $found found";
                        $phpcsFile->addError($error, $stackPtr);
                    }
                }

                $nextComma = $phpcsFile->findNext(T_COMMA, $parents[$i]);
            } else {
                $nextComma = ($parents[$i] + 1);
            }
        }
    }
}
?>
