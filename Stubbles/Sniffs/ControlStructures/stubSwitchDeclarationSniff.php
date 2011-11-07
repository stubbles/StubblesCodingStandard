<?php
/**
 * This sniff checks for switch structures and
 * validates them against a Stubbles specific
 * switch structure Coding Standard.
 *
 * @author   Richard Sternagel <richard.sternagel@1und1.de>
 * @package  ControlStructures
 */

/**
 * This sniff checks for switch structures and
 * validates them against a Stubbles specific
 * switch structure Coding Standard.
 *
 * Copied and adapted content from Squiz/Sniffs/ControlStructures/SwitchDeclarationSniff
 * because of no extending possibility (no methods/attributes to overwrite).
 *
 * @package  ControlStructures
 */
class Stubbles_Sniffs_ControlStructures_stubSwitchDeclarationSniff implements PHP_CodeSniffer_Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return  array
     */
    public function register()
    {
        return array(T_SWITCH);
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param  PHP_CodeSniffer_File  $phpcsFile  The file being scanned.
     * @param  int                   $stackPtr   The position of the current token in the
     *                                           stack passed in $tokens.
     *
     * @return  void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $switch        = $tokens[$stackPtr];
        $nextCase      = $stackPtr;
        $caseAlignment = ($switch['column'] + 4);

        /*
         * Stubbles (no CASE statement at all should produce an error)
         */
        $caseAvailible = $phpcsFile->findNext(T_CASE, $stackPtr+1, $switch['scope_closer']);
        if($caseAvailible === false) {
            $error = 'All SWITCH statemenst must contain at least one CASE statement';
            $phpcsFile->addError($error, $stackPtr);
        }

        while (($nextCase = $phpcsFile->findNext(array(T_CASE, T_SWITCH), ($nextCase + 1), $switch['scope_closer'])) !== false) {
            // Skip nested SWITCH statements; they are handled on their own.
            if ($tokens[$nextCase]['code'] === T_SWITCH) {
                $nextCase = $tokens[$nextCase]['scope_closer'];
                continue;
            }

            $content = $tokens[$nextCase]['content'];
            if ($content !== strtolower($content)) {
                $expected = strtolower($content);
                $error    = "CASE keyword must be lowercase; expected \"$expected\" but found \"$content\"";
                $phpcsFile->addError($error, $nextCase);
            }

            if ($tokens[$nextCase]['column'] !== $caseAlignment) {
                $error = 'CASE keyword must be indented 4 spaces from SWITCH keyword';
                $phpcsFile->addError($error, $nextCase);
            }

            if ($tokens[($nextCase + 1)]['type'] !== 'T_WHITESPACE' || $tokens[($nextCase + 1)]['content'] !== ' ') {
                $error = 'CASE keyword must be followed by a single space';
                $phpcsFile->addError($error, $nextCase);
            }

            $opener = $tokens[$nextCase]['scope_opener'];
            if ($tokens[($opener - 1)]['type'] === 'T_WHITESPACE') {
                $error = 'There must be no space before the colon in a CASE statement';
                $phpcsFile->addError($error, $nextCase);
            }

            $nextBreak = $phpcsFile->findNext(T_BREAK, ($nextCase + 1), $switch['scope_closer']);
            if ($nextBreak !== false && isset($tokens[$nextBreak]['scope_condition']) === true) {
                // Only check this BREAK statement if it matches the current CASE
                // statement. This stops the same break (used for multiple CASEs) being
                // checked more than once.

                /*
                 * Stubbles (indentation)
                 */
                if ($tokens[$nextBreak]['column'] !== ($caseAlignment+4)) {
                    $error = 'BREAK statement must be indented 4 spaces from CASE keyword';
                    $phpcsFile->addError($error, $nextBreak);
                }

                /*
                    Ensure empty CASE statements are not allowed.
                    They must have some code content in them. A comment is not
                    enough.
                */

                $foundContent = false;
                for ($i = ($tokens[$nextCase]['scope_opener'] + 1); $i < $nextBreak; $i++) {
                    if ($tokens[$i]['code'] === T_CASE) {
                        $i = $tokens[$i]['scope_opener'];
                        continue;
                    }

                    if (in_array($tokens[$i]['code'], PHP_CodeSniffer_Tokens::$emptyTokens) === false) {
                        $foundContent = true;
                        break;
                    }
                }

                if ($foundContent === false) {
                    $error = 'Empty CASE statements are not allowed';
                    $phpcsFile->addError($error, $nextCase);
                }

                /*
                    Ensure there is no blank line before
                    the BREAK statement.
                */

                $breakLine = $tokens[$nextBreak]['line'];
                $prevLine  = 0;
                for ($i = ($nextBreak - 1); $i > $stackPtr; $i--) {
                    if ($tokens[$i]['type'] !== 'T_WHITESPACE') {
                        $prevLine = $tokens[$i]['line'];
                        break;
                    }
                }

                if ($prevLine !== ($breakLine - 1)) {
                    $error = 'Blank lines are not allowed before BREAK statements';
                    $phpcsFile->addError($error, $nextBreak);
                }

                /*
                    Ensure the BREAK statement is followed by
                    a single blank line, or the end switch brace.
                */

                $breakLine = $tokens[$nextBreak]['line'];
                $nextLine  = $tokens[$tokens[$stackPtr]['scope_closer']]['line'];
                $semicolon = $phpcsFile->findNext(T_SEMICOLON, $nextBreak);
                for ($i = ($semicolon + 1); $i < $tokens[$stackPtr]['scope_closer']; $i++) {
                    if ($tokens[$i]['type'] !== 'T_WHITESPACE') {
                        $nextLine = $tokens[$i]['line'];
                        break;
                    }
                }

                if ($nextLine !== ($breakLine + 2) && $i !== $tokens[$stackPtr]['scope_closer']) {
                    $error = 'BREAK statements must be followed by a single blank line';
                    $phpcsFile->addError($error, $nextBreak);
                }

            } else {
                /*
                * Stubbles (throw/return/comment - complete else part)
                */

                /*
                  No break found so far. Ensure that last line of
                  CASE statements are followed by RETURN/THROW
                  or a comment which starts whith "// break ommited ... "
                */

                $caseOrDefaultAhead    = $phpcsFile->findNext(array(T_CASE, T_DEFAULT), $nextCase+1, $switch['scope_closer']);

                // echo $tokens[$caseOrDefaultAhead]['code'];
                // echo $tokens[$caseOrDefaultAhead]['type'];

                $requiredComment = "// break omitted";
                $next = array(
                            'return'  => null,
                            'throw'   => null,
                            'comment' => null
                        );

                $prev['return']  = $phpcsFile->findPrevious(T_RETURN, $caseOrDefaultAhead, $nextCase);
                $prev['throw']   = $phpcsFile->findPrevious(T_THROW, $caseOrDefaultAhead, $nextCase);
                $prev['comment'] = $phpcsFile->findPrevious(T_COMMENT, $caseOrDefaultAhead, $nextCase);

                // echo "return: ".  $prev['return']  ."\n";
                // echo "throw: ".   $prev['throw']   ."\n";
                // echo "comment: ". $prev['comment'] ."\n";

                if ($prev['throw']  === false &&
                    $prev['return'] === false &&
                   ($prev['comment'] === false || substr(ltrim($tokens[$prev['comment']]['content']), 0, 16) !== $requiredComment)) {
                        $error = 'CASE statement must have a BREAK, THROW or RETURN statement or the ' .
                                 'the following comment: ' . $requiredComment;
                        $phpcsFile->addError($error, $nextCase);
                } else {

                    /*
                      Ensure that found RETURN/THROW/COMMENT is followed by
                      a single blank line
                    */

                    rsort($prev);
                    $lastLineToken = $prev[0];
                    $lastLine  = $tokens[$lastLineToken]['line'];
                    $nextLine  = $tokens[$tokens[$stackPtr]['scope_closer']]['line'];
                    $lastChar  = null;

                    // find last character in line (throw/return => ';' | comment => 'Whitespace')
                    $lastChar = $phpcsFile->findNext(T_SEMICOLON, $lastLineToken, $caseOrDefaultAhead);
                    if ($lastChar === false) {
                        $lastChar = $phpcsFile->findNext(T_WHITESPACE, $lastLineToken, $caseOrDefaultAhead);
                    }

                    for ($i = ($lastChar + 1); $i < $tokens[$stackPtr]['scope_closer']; $i++) {
                        if ($tokens[$i]['type'] !== 'T_WHITESPACE') {
                            $nextLine = $tokens[$i]['line'];
                            break;
                        }
                    }

                    if ($nextLine !== ($lastLine + 2) && $i !== $tokens[$stackPtr]['scope_closer']) {
                        $error = 'THROW/RETURN/COMMENT statement must be followed by a single blank line';
                        $phpcsFile->addError($error, $lastLineToken);
                    }
                }

                $nextBreak = $tokens[$nextCase]['scope_closer'];
            }

            /*
                Ensure CASE statements are not followed by
                blank lines.
            */

            $caseLine = $tokens[$nextCase]['line'];
            $nextLine = $tokens[$nextBreak]['line'];
            for ($i = ($opener + 1); $i < $nextBreak; $i++) {
                if ($tokens[$i]['type'] !== 'T_WHITESPACE') {
                    $nextLine = $tokens[$i]['line'];
                    break;
                }
            }

            if ($nextLine !== ($caseLine + 1)) {
                $error = 'Blank lines are not allowed after CASE statements';
                $phpcsFile->addError($error, $nextCase);
            }
        }

        $default = $phpcsFile->findPrevious(T_DEFAULT, $switch['scope_closer'], $switch['scope_opener']);

        // Make sure this default belongs to us.
        if ($default !== false) {
            $conditions = array_keys($tokens[$default]['conditions']);
            $owner      = array_pop($conditions);
            if ($owner !== $stackPtr) {
                $default = false;
            }
        }

        if ($default !== false) {
            $content = $tokens[$default]['content'];
            if ($content !== strtolower($content)) {
                $expected = strtolower($content);
                $error    = "DEFAULT keyword must be lowercase; expected \"$expected\" but found \"$content\"";
                $phpcsFile->addError($error, $default);
            }

            $opener = $tokens[$default]['scope_opener'];
            if ($tokens[($opener - 1)]['type'] === 'T_WHITESPACE') {
                $error = 'There must be no space before the colon in a DEFAULT statement';
                $phpcsFile->addError($error, $default);
            }

            if ($tokens[$default]['column'] !== $caseAlignment) {
                $error = 'DEFAULT keyword must be indented 4 spaces from SWITCH keyword';
                $phpcsFile->addError($error, $default);
            }

            $nextBreak = $phpcsFile->findNext(array(T_BREAK), ($default + 1), $switch['scope_closer']);
            if ($nextBreak !== false) {

                /*
                 * Stubbles (indentation)
                 */
                if ($tokens[$nextBreak]['column'] !== ($caseAlignment+4)) {
                    $error = 'BREAK statement must be indented 4 spaces from CASE keyword';
                    $phpcsFile->addError($error, $nextBreak);
                }

                /*
                    Ensure the BREAK statement is not followed by
                    a blank line.
                */

                $breakLine = $tokens[$nextBreak]['line'];
                $nextLine  = $tokens[$tokens[$stackPtr]['scope_closer']]['line'];
                $semicolon = $phpcsFile->findNext(T_SEMICOLON, $nextBreak);
                for ($i = ($semicolon + 1); $i < $tokens[$stackPtr]['scope_closer']; $i++) {
                    if ($tokens[$i]['type'] !== 'T_WHITESPACE') {
                        $nextLine = $tokens[$i]['line'];
                        break;
                    }
                }

                if ($nextLine !== ($breakLine + 1)) {
                    $error = 'Blank lines are not allowed after the DEFAULT case\'s BREAK statement';
                    $phpcsFile->addError($error, $nextBreak);
                }
            } else {

                /*
                 * Stubbles (throw/return)
                 */
                /*
                $nextThrow = $phpcsFile->findNext(array(T_THROW), ($default + 1), $switch['scope_closer']);
                $nextReturn = $phpcsFile->findNext(array(T_RETURN), ($default + 1), $switch['scope_closer']);

                if ($nextThrow === false && $nextReturn === false) {
                    $error = 'DEFAULT case must have a BREAK statement or instead a THROW/RETURN statement';
                    $phpcsFile->addError($error, $default);
                }
                */

                $nextBreak = $tokens[$default]['scope_closer'];
            }

            /*
                Ensure empty DEFAULT statements are not allowed.
                They must (at least) have a comment describing why
                the default case is being ignored.
            */

            $foundContent = false;
            for ($i = ($tokens[$default]['scope_opener'] + 1); $i < $nextBreak; $i++) {
                if ($tokens[$i]['type'] !== 'T_WHITESPACE') {
                    $foundContent = true;
                    break;
                }
            }

            if ($foundContent === false) {
                $error = 'Comment required for empty DEFAULT case';
                $phpcsFile->addError($error, $default);
            }

            /*
                Ensure DEFAULT statements are not followed by
                blank lines.
            */

            $defaultLine = $tokens[$default]['line'];
            $nextLine    = $tokens[$nextBreak]['line'];
            for ($i = ($opener + 1); $i < $nextBreak; $i++) {
                if ($tokens[$i]['type'] !== 'T_WHITESPACE') {
                    $nextLine = $tokens[$i]['line'];
                    break;
                }
            }

            if ($nextLine !== ($defaultLine + 1)) {
                $error = 'Blank lines are not allowed after DEFAULT statements';
                $phpcsFile->addError($error, $default);
            }

        } else {
            $error = 'All SWITCH statements must contain a DEFAULT case';
            $phpcsFile->addError($error, $stackPtr);
        }

        if ($tokens[$switch['scope_closer']]['column'] !== $switch['column']) {
            $error = 'Closing brace of SWITCH statement must be aligned with SWITCH keyword';
            $phpcsFile->addError($error, $switch['scope_closer']);
        }
    }
}
?>