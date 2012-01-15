<?php
/**
 * This sniff checks for file docblocks
 *
 * @author   Richard Sternagel <richard.sternagel@1und1.de>
 * @package  Commenting
 */

if (class_exists('PHP_CodeSniffer_CommentParser_ClassCommentParser', true) === false) {
    throw new PHP_CodeSniffer_Exception('Class PHP_CodeSniffer_CommentParser_ClassCommentParser not found');
}

/**
 * This sniff checks for file docblocks
 *
 * Verifies that :
 * <ul>
 *  <li>A doc comment exists.</li>
 *  <li>There is a blank newline after the short description.</li>
 *  <li>There is a blank newline between the long and short description.</li>
 *  <li>There is a blank newline between the long description and tags.</li>
 *  <li>Check the order of the tags.</li>
 *  <li>Check the indentation of each tag.</li>
 *  <li>Check required and optional tags and the format of their content.</li>
 * </ul>
 *
 * Copied and adapted content from PEAR/Sniffs/Commenting/ClassCommentSniff
 * because of no extending possibility (no methods/attributes to overwrite).
 *
 * @package  Commenting
 */

class Stubbles_Sniffs_Commenting_stubFileCommentSniff implements PHP_CodeSniffer_Sniff
{
    /**
     * The header comment parser for the current file.
     *
     * @var  PHP_CodeSniffer_Comment_Parser_ClassCommentParser
    */
    protected $commentParser = null;
    /**
     * The current PHP_CodeSniffer_File object we are processing.
     *
     * @var  PHP_CodeSniffer_File
     */
    protected $currentFile = null;
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return  array
     */
    public function register()
    {
        return array(T_OPEN_TAG);
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param   PHP_CodeSniffer_File  $phpcsFile  The file being scanned.
     * @param   int                   $stackPtr   The position of the current token
     *                                            in the stack passed in $tokens.
     * @return  void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $this->currentFile = $phpcsFile;

        // We are only interested if this is the first open tag.
        if ($stackPtr !== 0) {
            if ($phpcsFile->findPrevious(T_OPEN_TAG, ($stackPtr - 1)) !== false) {
                return;
            }
        }

        $tokens = $phpcsFile->getTokens();

        // Find the next non whitespace token.
        $commentStart = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);
        // Ignore vim header.
        if ($tokens[$commentStart]['code'] === T_COMMENT) {
            if (strstr($tokens[$commentStart]['content'], 'vim:') !== false) {
                $commentStart = $phpcsFile->findNext(T_WHITESPACE, ($commentStart + 1), null, true);
            }
        }

        if ($tokens[$commentStart]['code'] === T_CLOSE_TAG) {
            // We are only interested if this is the first open tag.
            return;
        } else if ($tokens[$commentStart]['code'] === T_COMMENT) {
            $phpcsFile->addError('You must use "/**" style comments for a file comment', ($stackPtr + 1));
            return;
        } else if ($commentStart === false || $tokens[$commentStart]['code'] !== T_DOC_COMMENT) {
            $phpcsFile->addError('Missing file doc comment', ($stackPtr + 1));
            return;
        } else {

            // Extract the header comment docblock.
            $commentEnd = ($phpcsFile->findNext(T_DOC_COMMENT, ($commentStart + 1), null, true) - 1);

            // Check if there is only 1 doc comment between the open tag and class token.
            $nextToken   = array(
                            T_ABSTRACT,
                            T_CLASS,
                            T_FUNCTION,
                            T_DOC_COMMENT,
                           );
            $commentNext = $phpcsFile->findNext($nextToken, ($commentEnd + 1));
            if ($commentNext !== false && $tokens[$commentNext]['code'] !== T_DOC_COMMENT) {
                // Found a class token right after comment doc block.
                $newlineToken = $phpcsFile->findNext(T_WHITESPACE, ($commentEnd + 1), $commentNext, false, $phpcsFile->eolChar);
                if ($newlineToken !== false) {
                    $newlineToken = $phpcsFile->findNext(T_WHITESPACE, ($newlineToken + 1), $commentNext, false, $phpcsFile->eolChar);
                    if ($newlineToken === false) {
                        // No blank line between the class token and the doc block.
                        // The doc block is most likely a class comment.
                        $phpcsFile->addError('Missing file doc comment', ($stackPtr + 1));
                        return;
                    }
                }
            }

            $comment = $phpcsFile->getTokensAsString($commentStart, ($commentEnd - $commentStart + 1));

            // Parse the header comment docblock.
            try {
                $this->commentParser = new PHP_CodeSniffer_CommentParser_ClassCommentParser($comment, $phpcsFile);
                $this->commentParser->parse();
            } catch (PHP_CodeSniffer_CommentParser_ParserException $e) {
                $line = ($e->getLineWithinComment() + $commentStart);
                $phpcsFile->addError($e->getMessage(), $line);
                return;
            }

            $comment = $this->commentParser->getComment();
            if (is_null($comment) === true) {
                $error = 'File doc comment is empty';
                $phpcsFile->addError($error, $commentStart);
                return;
            }

            // No extra newline before short description.
            $short        = $comment->getShortComment();
            $newlineCount = 0;
            $newlineSpan  = strspn($short, $phpcsFile->eolChar);
            if ($short !== '' && $newlineSpan > 0) {
                $line  = ($newlineSpan > 1) ? 'newlines' : 'newline';
                $error = "Extra $line found before file comment short description";
                $phpcsFile->addError($error, ($commentStart + 1));
            }

            $newlineCount = (substr_count($short, $phpcsFile->eolChar) + 1);

            // Exactly one blank line between short and long description.
            $long = $comment->getLongComment();
            if (empty($long) === false) {
                $between        = $comment->getWhiteSpaceBetween();
                $newlineBetween = substr_count($between, $phpcsFile->eolChar);
                if ($newlineBetween !== 2) {
                    $error = 'There must be exactly one blank line between descriptions in file comment';
                    $phpcsFile->addError($error, ($commentStart + $newlineCount + 1));
                }

                $newlineCount += $newlineBetween;
            }

            // Exactly one blank line before tags.
            $tags = $this->commentParser->getTagOrders();
            if (count($tags) > 1) {
                $newlineSpan = $comment->getNewlineAfter();
                if ($newlineSpan !== 2) {
                    $error = 'There must be exactly one blank line before the tags in file comment';
                    if ($long !== '') {
                        $newlineCount += (substr_count($long, $phpcsFile->eolChar) - $newlineSpan + 1);
                    }

                    $phpcsFile->addError($error, ($commentStart + $newlineCount));
                    $short = rtrim($short, $phpcsFile->eolChar.' ');
                }
            }

            /*
             * Stubbles
             */
            // Check the PHP Version.
            // omitted

            // Check each tag.
            $this->processTags($commentStart, $commentEnd);
        }
    }

    /*
     * Stubbbles
     */
    // added $omitTags param

    /**
     * Processes each required or optional tag.
     *
     * @param   int    $commentStart  The position in the stack where the comment started.
     * @param   int    $commentEnd    The position in the stack where the comment ended.
     * @param   array  $omitTags      Subclasses can omitted tags.
     * @return  void
     */
    protected function processTags($commentStart, $commentEnd, $omitTags = null)
    {
        // Tags in correct order and related info.
        // If you want to adapt this see origin file.
        $tags = array(
                 // See origin file for omitted tags.
                 'author'     => array(
                                  'required'       => false,
                                  'discouraged'    => true,
                                  'allow_multiple' => true,
                                  'order_text'     => 'precedes @package',
                                 ),
                 'package'    => array(
                                  'required'       => true,
                                  'allow_multiple' => false,
                                  'order_text'     => 'follows @author',
                                 ),
                 'subpackage' => array(
                                  'required'       => false,
                                  'discouraged'    => true,
                                  'allow_multiple' => false,
                                  'order_text'     => 'follows @package',
                                 ),
                 'version'    => array(
                                  'required'       => false,
                                  'discouraged'    => true,
                                  'allow_multiple' => false,
                                  'order_text'     => 'follows @package or @subpackage (is used)',
                                 ),
                 'link'       => array(
                                  'required'       => false,
                                  'allow_multiple' => true,
                                  'order_text'     => 'follows @link',
                                 ),
                 'see'        => array(
                                  'required'       => false,
                                  'allow_multiple' => true,
                                  'order_text'     => 'follows @see',
                                 ),
                 'deprecated' => array(
                                  'required'       => false,
                                  'allow_multiple' => false,
                                  'order_text'     => 'follows @subpackage or @see (if used) or @link (if used)',
                                 )/*,
                 'since'      => array(
                                  'required'       => false,
                                  'allow_multiple' => true,
                                  'order_text'     => 'follows any'
                 )*/
                );

        /*
         * Stubbles
         */
        if($omitTags !== null) {
            foreach ($omitTags as $tag) {
                if(array_key_exists($tag, $tags)) {
                    unset($tags[$tag]);
                }
            }
        }


        $docBlock    = (get_class($this) === 'PEAR_Sniffs_Commenting_FileCommentSniff') ? 'file' : 'class';
        $foundTags   = $this->commentParser->getTagOrders();
        $orderIndex  = 0;
        $indentation = array();
        $longestTag  = 0;
        $errorPos    = 0;

        foreach ($tags as $tag => $info) {

            // Required tag missing.
            if ($info['required'] === true && in_array($tag, $foundTags) === false) {
                $error = "Missing @$tag tag in $docBlock comment";
                $this->currentFile->addError($error, $commentEnd);
                continue;
            } elseif (in_array($tag, $foundTags) === true && isset($info['discouraged']) === true && true === $info['discouraged']) {
                $error = "The usage of the @$tag in any comment is discouraged";
                $this->currentFile->addWarning($error, $commentEnd);
                continue;
            }

            // Get the line number for current tag.
            $tagName = ucfirst($tag);
            if ($info['allow_multiple'] === true) {
                $tagName .= 's';
            }

            $getMethod  = 'get'.$tagName;
            $tagElement = $this->commentParser->$getMethod();
            if (is_null($tagElement) === true || empty($tagElement) === true) {
                continue;
            }

            $errorPos = $commentStart;
            if (is_array($tagElement) === false) {
                $errorPos = ($commentStart + $tagElement->getLine());
            }

            // Get the tag order.
            $foundIndexes = array_keys($foundTags, $tag);

            if (count($foundIndexes) > 1) {
                // Multiple occurance not allowed.
                if ($info['allow_multiple'] === false) {
                    $error = "Only 1 @$tag tag is allowed in a $docBlock comment";
                    $this->currentFile->addError($error, $errorPos);
                } else {
                    // Make sure same tags are grouped together.
                    $i     = 0;
                    $count = $foundIndexes[0];
                    foreach ($foundIndexes as $index) {
                        if ($index !== $count) {
                            $errorPosIndex = ($errorPos + $tagElement[$i]->getLine());
                            $error         = "@$tag tags must be grouped together";
                            $this->currentFile->addError($error, $errorPosIndex);
                        }

                        $i++;
                        $count++;
                    }
                }
            }

            // Check tag order.
            if ($foundIndexes[0] > $orderIndex) {
                $orderIndex = $foundIndexes[0];
            } else {
                if (is_array($tagElement) === true && empty($tagElement) === false) {
                    $errorPos += $tagElement[0]->getLine();
                }

                $orderText = $info['order_text'];
                $error     = "The @$tag tag is in the wrong order; the tag $orderText";
                $this->currentFile->addError($error, $errorPos);
            }

            // Store the indentation for checking.
            $len = strlen($tag);
            if ($len > $longestTag) {
                $longestTag = $len;
            }

            if (is_array($tagElement) === true) {
                foreach ($tagElement as $key => $element) {
                    $indentation[] = array(
                                      'tag'   => $tag,
                                      'space' => $this->getIndentation($tag, $element),
                                      'line'  => $element->getLine(),
                                     );
                }
            } else {
                $indentation[] = array(
                                  'tag'   => $tag,
                                  'space' => $this->getIndentation($tag, $tagElement),
                                 );
            }

            $method = 'process'.$tagName;
            if (method_exists($this, $method) === true) {
                // Process each tag if a method is defined.
                call_user_func(array($this, $method), $errorPos);
            } else {
                if (is_array($tagElement) === true) {
                    foreach ($tagElement as $key => $element) {
                        $element->process($this->currentFile, $commentStart, $docBlock);
                    }
                } else {
                     $tagElement->process($this->currentFile, $commentStart, $docBlock);
                }
            }
        }

        foreach ($indentation as $indentInfo) {
            /*
             * Stubbles
             */
            // adapted width (+2)
            if ($indentInfo['space'] !== 0 && $indentInfo['space'] !== ($longestTag + 2)) {
                $expected     = (($longestTag - strlen($indentInfo['tag'])) + 2);
                $space        = ($indentInfo['space'] - strlen($indentInfo['tag']));
                $error        = "@$indentInfo[tag] tag comment indented incorrectly. ";
                $error       .= "Expected $expected spaces but found $space.";
                $getTagMethod = 'get'.ucfirst($indentInfo['tag']);
                if ($tags[$indentInfo['tag']]['allow_multiple'] === true) {
                    $line = $indentInfo['line'];
                } else {
                    $tagElem = $this->commentParser->$getTagMethod();
                    $line    = $tagElem->getLine();
                }

                $this->currentFile->addError($error, ($commentStart + $line));
            }
        }
    }

    /**
     * Get the indentation information of each tag.
     *
     * @param  string                                    $tagName     The name of the doc comment element.
     * @param  PHP_CodeSniffer_CommentParser_DocElement  $tagElement  The doc comment element.
     *
     * @return  void
     */
    protected function getIndentation($tagName, $tagElement)
    {
        if ($tagElement instanceof PHP_CodeSniffer_CommentParser_SingleElement) {
            if ($tagElement->getContent() !== '') {
                return (strlen($tagName) + substr_count($tagElement->getWhitespaceBeforeContent(), ' '));
            }
        } else if ($tagElement instanceof PHP_CodeSniffer_CommentParser_PairElement) {
            if ($tagElement->getValue() !== '') {
                return (strlen($tagName) + substr_count($tagElement->getWhitespaceBeforeValue(), ' '));
            }
        }

        return 0;
    }

    /**
     * Process the package tag.
     *
     * @param   int   $errorPos  The line number where the error occurs.
     * @return  void
     */
    protected function processPackage($errorPos)
    {
        $package = $this->commentParser->getPackage();
        if ($package !== null) {
            $content = $package->getContent();
            if ($content !== '') {
                /*
                 * Stubbles
                 */
                // omitted body
            } else {
                $error = '@package tag must contain a name';
                $this->currentFile->addError($error, $errorPos);
            }
        }
    }

    /**
     * Process the subpackage tag.
     *
     * @param   int   $errorPos   The line number where the error occurs.
     *
     * @return  void
     */
    protected function processSubpackage($errorPos)
    {
        $package = $this->commentParser->getSubpackage();
        if ($package !== null) {
            $content = $package->getContent();
            if ($content !== '') {
                /*
                 * Stubbles
                 */
                // omitted body
            } else {
                $error = '@subpackage tag must contain a name';
                $this->currentFile->addError($error, $errorPos);
            }
        }
    }

    /*
     *  Stubbles
     */
    // omitted @author checks
    // protected function processAuthors($commentStart) { ... }

    // omitted @category checks
    // protected function processCategory($errorPos) { ... }

    // omitted @copyright checks
    // protected function processCopyrights($commentStart) { ... }

    // omitted @licence checks
    // protected function processLicense($errorPos) { ... }

    /**
     * Process the version tag.
     *
     * @param int $errorPos The line number where the error occurs.
     *
     * @return void
     */
    protected function processVersion($errorPos)
    {
        $version = $this->commentParser->getVersion();
        if ($version !== null) {
            $content = $version->getContent();
            $matches = array();
            if (empty($content) === true) {
                $error = 'Content missing for @version tag in file comment, must be at least $Id: stubFileCommentSniff.php 2065 2009-01-26 16:47:09Z mikey $';
                $this->currentFile->addError($error, $errorPos);
            } else if (strstr($content, '$Id') === false) {
                $error = 'Invalid @version tag content "' . $content . '" in file comment; consider "$Id: stubFileCommentSniff.php 2065 2009-01-26 16:47:09Z mikey $" instead';
                $this->currentFile->addError($error, $errorPos);
            }
        }

    }
}
?>