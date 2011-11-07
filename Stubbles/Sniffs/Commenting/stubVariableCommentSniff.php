<?php
/**
 * Parses and verifies the variable doc comment.
 *
 * @package  Commenting
 * @author   Richard Sternagel <richard.sternagel@1und1.de>
 */

if (class_exists('PHP_CodeSniffer_Standards_AbstractVariableSniff', true) === false) {
    throw new PHP_CodeSniffer_Exception('Class PHP_CodeSniffer_Standards_AbstractVariableSniff not found');
}

if (class_exists('PHP_CodeSniffer_CommentParser_MemberCommentParser', true) === false) {
    throw new PHP_CodeSniffer_Exception('Class PHP_CodeSniffer_CommentParser_MemberCommentParser not found');
}

/**
 * Parses and verifies the variable doc comment.
 *
 * Verifies that :
 * <ul>
 *  <li>A variable doc comment exists.</li>
 *  <li>There is a blank line after the short description.</li>
 *  <li>There is a blank line between the description and the tags.</li>
 *  <li>Check the order, indentation and content of each tag.</li>
 * </ul>
 *
 * Copied and adapted content from Squiz/Sniffs/Commenting/VariableCommentSniff
 * because of no extending possibility (no methods/attributes to overwrite).
 *
 * @package  Commenting
 */

class Stubbles_Sniffs_Commenting_stubVariableCommentSniff extends PHP_CodeSniffer_Standards_AbstractVariableSniff
{
    /**
     * The header comment parser for the current file.
     *
     * @var  PHP_CodeSniffer_Comment_Parser_ClassCommentParser
     */
    protected $commentParser = null;

    /**
     * Called to process class member vars.
     *
     * @param   PHP_CodeSniffer_File  $phpcsFile  The file being scanned.
     * @param   int                   $stackPtr   The position of the current token
     *                                            in the stack passed in $tokens.
     * @return  void
     */
    public function processMemberVar(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $this->currentFile = $phpcsFile;
        $tokens            = $phpcsFile->getTokens();
        $commentToken      = array(
                              T_COMMENT,
                              T_DOC_COMMENT,
                             );

        // Extract the var comment docblock.
        $commentEnd = $phpcsFile->findPrevious($commentToken, ($stackPtr - 3));
        if ($commentEnd !== false && $tokens[$commentEnd]['code'] === T_COMMENT) {
            $phpcsFile->addError('You must use "/**" style comments for a variable comment', $stackPtr);
            return;
        } else if ($commentEnd === false || $tokens[$commentEnd]['code'] !== T_DOC_COMMENT) {
            $phpcsFile->addError('Missing variable doc comment', $stackPtr);
            return;
        } else {
            // Make sure the comment we have found belongs to us.
            $commentFor = $phpcsFile->findNext(array(T_VARIABLE, T_CLASS, T_INTERFACE), ($commentEnd + 1));
            if ($commentFor !== $stackPtr) {
                $phpcsFile->addError('Missing variable doc comment', $stackPtr);
                return;
            }
        }

        $commentStart = ($phpcsFile->findPrevious(T_DOC_COMMENT, ($commentEnd - 1), null, true) + 1);
        $comment      = $phpcsFile->getTokensAsString($commentStart, ($commentEnd - $commentStart + 1));

        // Parse the header comment docblock.
        try {
            $this->commentParser = new PHP_CodeSniffer_CommentParser_MemberCommentParser($comment, $phpcsFile);
            $this->commentParser->parse();
        } catch (PHP_CodeSniffer_CommentParser_ParserException $e) {
            $line = ($e->getLineWithinComment() + $commentStart);
            $phpcsFile->addError($e->getMessage(), $line);
            return;
        }

        $comment = $this->commentParser->getComment();
        if (is_null($comment) === true) {
            $error = 'Variable doc comment is empty';
            $phpcsFile->addError($error, $commentStart);
            return;
        }

        // Check for a comment description.
        $short = $comment->getShortComment();
        if (trim($short) === '') {
            $error = 'Missing short description in variable doc comment';
            $phpcsFile->addError($error, $commentStart);
            return;
        }

        // No extra newline before short description.
        $newlineCount = 0;
        $newlineSpan  = strspn($short, $phpcsFile->eolChar);
        if ($short !== '' && $newlineSpan > 0) {
            $line  = ($newlineSpan > 1) ? 'newlines' : 'newline';
            $error = "Extra $line found before variable comment short description";
            $phpcsFile->addError($error, ($commentStart + 1));
        }

        $newlineCount = (substr_count($short, $phpcsFile->eolChar) + 1);

        // Exactly one blank line between short and long description.
        $long = $comment->getLongComment();
        if (empty($long) === false) {
            $between        = $comment->getWhiteSpaceBetween();
            $newlineBetween = substr_count($between, $phpcsFile->eolChar);
            if ($newlineBetween !== 2) {
                $error = 'There must be exactly one blank line between descriptions in variable comment';
                $phpcsFile->addError($error, ($commentStart + $newlineCount + 1));
            }

            $newlineCount += $newlineBetween;

            /*
             * Stubbles
             */
            /*
            $testLong = trim($long);
            if (preg_match('|[A-Z]|', $testLong[0]) === 0) {
                $error = 'Variable comment long description must start with a capital letter';
                $phpcsFile->addError($error, ($commentStart + $newlineCount));
            }
            */
        }

        // Exactly one blank line before tags.
        $tags = $this->commentParser->getTagOrders();
        if (count($tags) > 1) {
            $newlineSpan = $comment->getNewlineAfter();
            if ($newlineSpan !== 2) {
                $error = 'There must be exactly one blank line before the tags in variable comment';
                if ($long !== '') {
                    $newlineCount += (substr_count($long, $phpcsFile->eolChar) - $newlineSpan + 1);
                }

                $phpcsFile->addError($error, ($commentStart + $newlineCount));
                $short = rtrim($short, $phpcsFile->eolChar.' ');
            }
        }

        // Short description must be single line and end with a full stop.
        $testShort = trim($short);
        $lastChar  = $testShort[(strlen($testShort) - 1)];
        if (substr_count($testShort, $phpcsFile->eolChar) !== 0) {
            $error = 'Variable comment short description must be on a single line';
            $phpcsFile->addError($error, ($commentStart + 1));
        }

        /*
         * Stubbles
         */
        /*
        if (preg_match('|[A-Z]|', $testShort[0]) === 0) {
            $error = 'Variable comment short description must start with a capital letter';
            $phpcsFile->addError($error, ($commentStart + 1));
        }

        if ($lastChar !== '.') {
            $error = 'Variable comment short description must end with a full stop';
            $phpcsFile->addError($error, ($commentStart + 1));
        }
        */

        // Check for unknown/deprecated tags.
        $unknownTags = $this->commentParser->getUnknown();
        foreach ($unknownTags as $errorTag) {
            // Unknown tags are not parsed, do not process further.
            $error = "@$errorTag[tag] tag is not allowed in variable comment";
            $phpcsFile->addWarning($error, ($commentStart + $errorTag['line']));
            return;
        }

        // Check each tag.
        $this->processVar($commentStart, $commentEnd);

        /*
         * Stubbles
         */
        // $this->processSince($commentStart, $commentEnd);
        // $this->processSees($commentStart);
    }

    /**
     * Process the var tag.
     *
     * @param   int  $commentStart  The position in the stack where the comment started.
     * @param   int  $commentEnd    The position in the stack where the comment ended.
     *
     * @return  void
     */
    protected function processVar($commentStart, $commentEnd)
    {
        $var = $this->commentParser->getVar();

        if ($var !== null) {
            $errorPos = ($commentStart + $var->getLine());
            $index    = array_keys($this->commentParser->getTagOrders(), 'var');

            if (count($index) > 1) {
                $error = 'Only 1 @var tag is allowed in variable comment';
                $this->currentFile->addError($error, $errorPos);
                return;
            }

            if ($index[0] !== 1) {
                $error = 'The @var tag must be the first tag in a variable comment';
                $this->currentFile->addError($error, $errorPos);
            }

            $content = $var->getContent();
            if (empty($content) === true) {
                $error = 'Var type missing for @var tag in variable comment';
                $this->currentFile->addError($error, $errorPos);
                return;
            } else {
                /*
                 * Stubbles (int vs. integer / bool vs. boolean / double vs. float)
                 */
                /*
                $suggestedType = PHP_CodeSniffer::suggestType($content);
                if ($content !== $suggestedType) {
                    $error = "Expected \"$suggestedType\"; found \"$content\" for @var tag in variable comment";
                    $this->currentFile->addError($error, $errorPos);
                }
                */
            }

            $spacing = substr_count($var->getWhitespaceBeforeContent(), ' ');
            /*
             * Stubbles (space=2)
             */
            if ($spacing !== 2) {
                $error  = '@var tag indented incorrectly. ';
                $error .= "Expected 2 spaces but found $spacing.";
                $this->currentFile->addError($error, $errorPos);
            }
        } else {
            $error = 'Missing @var tag in variable comment';
            $this->currentFile->addError($error, $commentEnd);
        }
    }

    /*
     * Stubbles
     */
    // omitted
    // protected function processSince($commentStart, $commentEnd) {}
    // protected function processSees($commentStart) {}

    /**
     * Called to process a normal variable.
     *
     * Not required for this sniff.
     *
     * @param   PHP_CodeSniffer_File  $phpcsFile  The PHP_CodeSniffer file where this token was found.
     * @param   int                   $stackPtr   The position where the double quoted
     *                                             string was found.
     * @return  void
     */
    protected function processVariable(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        return;
    }

    /**
     * Called to process variables found in duoble quoted strings.
     *
     * Not required for this sniff.
     *
     * @param   PHP_CodeSniffer_File  $phpcsFile  The PHP_CodeSniffer file where this token was found.
     * @param   int                   $stackPtr   The position where the double quoted
     *                                            string was found.
     * @return  void
     */
    protected function processVariableInString(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {

    }
}
?>